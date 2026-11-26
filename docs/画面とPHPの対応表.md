# 画面と PHP の対応表（HTML 担当・PHP 担当の約束ごと）

HTML/CSS を作るときは、この表の「ファイル名」「name」「変数名」に合わせてください。
PHP 側はこの名前で値を受け取り、表示します。名前が違うと値が届かないので、変えたいときは先にチャットで相談してください。

- 元にした資料：画面設計書（利用者・管理者）v1.1（11/4）、DB 定義 `sql/schema.sql`
- 第2回レビュー対応で追加：U14 の入力項目変更、U17・U18（認証アプリ）
- 11/26 追加（PHP 第3弾・マイページ）：U11 の `$library` の列と `$selected` の決め方、U11〜U16 共通の `$mypage_active`（下の表に【追加】と書いた行）
- 11/17 追加（PHP 第2弾・商品〜購入）：ヘッダーの `$cart_count`、U03 の `$add_block`・`$in_cart`・`$cart_summary`、U08 の hidden `confirm_token`、U07・U08 の `$item_count`、U09 の `$reason_counts`、U10 の `$order_number`（下の表に【追加】と書いた行）

---

## 1. 共通ルール

### フォルダとファイル

| 置き場所 | 中身 |
|---|---|
| `/`（ルート） | 利用者画面の PHP（1画面＝1ファイル） |
| `/admin/` | 管理者画面の PHP |
| `/includes/` | 共通部品（`header.php`・`footer.php`・`admin_header.php`・`admin_footer.php`・`db.php` など） |
| `/assets/css/` | `style.css`（利用者）・`admin.css`（管理者） |
| `/assets/js/` | 自作の JS（入力チェックなど） |
| `/assets/js/vendor/` | 外部ライブラリ（`qrcode.min.js`） |
| `/assets/games/` | 商品画像 |

### 画面ファイルの書き方

- 画面は `.html` ではなく **`.php`** で作る（中身は HTML のままで大丈夫）
- ヘッダー・フッターは各画面にコピーしない。次の2行で読み込む
  ```php
  <?php include __DIR__ . '/includes/header.php'; ?>
  ...画面ごとの HTML...
  <?php include __DIR__ . '/includes/footer.php'; ?>
  ```
- 画面タイトルは `$page_title` に入れておく（header.php の `<title>` で使う）
- PHP の値を HTML に出すときは必ず `h()` を通す（XSS 対策）
  ```php
  <p><?= h($member['nickname']) ?></p>
  ```

### フォームの共通ルール

- 送信は `method="post"`、送り先は基本その画面自身（`action=""`）
- どのフォームにも次の1行を入れる（不正送信対策）
  ```html
  <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
  ```
- 検索フォーム（U01・U02・A08・A10）だけは `method="get"`（URL に条件を残すため）

### どの画面でも使う変数（PHP 側が用意する）

| 変数 | 中身 | HTML 側での使い方 |
|---|---|---|
| `$errors` | 入力エラー（`['email' => 'メールアドレスの形式が正しくありません']` のような配列） | 各入力欄の下に `<?php if (!empty($errors['email'])): ?><p class="error"><?= h($errors['email']) ?></p><?php endif; ?>` |
| `$old` | エラーで戻ったときの入力値 | `value="<?= h($old['email'] ?? '') ?>"`（パスワード欄には使わない） |
| `$message` | 完了メッセージ（「登録が完了しました」など） | 画面上部の「メッセージ表示欄」 |
| `$login_member` | ログイン中の会員（未ログインなら `null`） | ヘッダーの表示切替（ログイン／会員登録 ⇔ マイページ／ログアウト） |
| `$cart_count` | 【追加】カートに入っている商品の数（未ログインは 0） | ヘッダーの「カート（N）」。ログイン中だけ `<span class="cart-count">` で出す |

---

## 2. 利用者画面

| 画面ID | 画面名 | ファイル |
|---|---|---|
| U01 | トップ | `index.php` |
| U02 | 検索結果（比較一覧） | `search.php` |
| U03 / U03-a | 商品詳細／カート追加完了 | `product.php?id=商品ID` |
| U04 | 会員登録 | `register.php` |
| U05 | 会員登録確認 | `register_confirm.php` |
| U17 | 認証アプリ設定（新規） | `register_totp.php` |
| U18 | 復旧コード表示（新規） | `register_recovery.php` |
| U06 / U06-a / U06-b | ログイン／エラー／ロック中 | `login.php` |
| — | ログアウト（画面なし） | `logout.php` |
| U07 / U07-a | カート／空 | `cart.php` |
| U08 | 購入内容確認 | `purchase_confirm.php` |
| U09 | 購入できない商品の確認 | `purchase_error.php` |
| U10 | 購入完了 | `purchase_complete.php` |
| U11 | マイページ（購入履歴） | `mypage.php` |
| — | ダウンロード（画面なし） | `download.php?id=商品ID` |
| U12 | 会員情報の変更 | `account_edit.php` |
| U13 | パスワードの変更 | `password_change.php` |
| U14 | パスワード再設定（本人確認） | `password_reset.php` |
| U15 | パスワード再設定（新パスワード） | `password_reset_new.php` |
| U16 | 退会 | `withdraw.php` |

### 入力項目（name）

| 画面 | 項目 | 部品 | name |
|---|---|---|---|
| U01・U02 | 検索キーワード | text | `keyword` |
| U01・U02 | プレイ人数 | checkbox（複数） | `players[]`（値：`2` / `3-4` / `5plus`） |
| U01・U02 | 対応OS | checkbox（複数） | `os[]`（値：`windows` / `mac`） |
| U01・U02 | 遊び方 | checkbox（複数） | `play_style[]`（値：`協力` / `対戦`） |
| U01・U02 | クロスプレイ | checkbox | `crossplay`（値：`1`） |
| U02 | ジャンル | checkbox（複数） | `genre[]`（値：`FPS` / `MMO` / `クラフト` / `対戦(PVP)` / `レース`） |
| U02 | 価格（下限・上限） | number ×2 | `price_min` / `price_max` |
| U02 | 並び替え | select | `sort`（値：`recommend` / `new` / `price_asc` / `price_desc`） |
| U03 | カートに追加 | button（form） | hidden `product_id` |
| U04 | 氏名 | text | `name` |
| U04 | メールアドレス | email | `email` |
| U04 | パスワード | password | `password` |
| U04 | パスワード（確認用） | password | `password_confirm` |
| U04 | 生年月日（年・月・日） | select ×3 | `birth_year` / `birth_month` / `birth_day` |
| U04 | ニックネーム（任意） | text | `nickname` |
| U04 | 電話番号（任意） | tel | `phone` |
| U05 | 修正する／登録する | button ×2 | `action`（値：`back` / `register`） |
| U17 | 認証アプリの6桁 | text（`inputmode="numeric" maxlength="6"`） | `totp_code` |
| U18 | 次へ | button | （入力なし） |
| U06 | メールアドレス | email | `email` |
| U06 | パスワード | password | `password` |
| U07 | 削除 | button（行ごとの form） | hidden `product_id`・`action=delete` |
| U08 | 購入を確定する | button | 【追加】hidden `confirm_token`（PHP が用意する `$confirm_token` をそのまま入れる。二重送信防止） |
| U09 | 対象商品を削除してカートへ | button | （入力なし） |
| U12 | ニックネーム | text | `nickname` |
| U12 | メールアドレス | email | `email` |
| U12 | 電話番号 | tel | `phone` |
| U12 | 現在のパスワード | password | `current_password` |
| U13 | 現在のパスワード | password | `current_password` |
| U13 | 新しいパスワード | password | `new_password` |
| U13 | 新しいパスワード（確認） | password | `new_password_confirm` |
| U14 | メールアドレス | email | `email` |
| U14 | 認証アプリの6桁 または 復旧コード | text | `code`（生年月日の欄は削除） |
| U15 | 新しいパスワード | password | `new_password` |
| U15 | 新しいパスワード（確認用） | password | `new_password_confirm` |
| U16 | 現在のパスワード | password | `current_password` |
| U16 | 同意チェック | checkbox | `agree`（値：`1`） |

### 表示に使う変数（PHP 側が用意する）

| 画面 | 変数 | 中身（使う列） |
|---|---|---|
| U01 | `$recommend_products` | 商品の配列（`product_id`・`name`・`image`・`price`・`min_players`・`max_players`・`os_windows`・`os_mac`） |
| U02 | `$products`・`$total_count` | 検索結果の配列と件数（列は U01 と同じ＋`genre`・`play_style`・`crossplay_flag`・`play_time`） |
| U03 | `$product` | 商品1件（`products` の全列） |
| U03-a | `$added`（true/false） | カート追加直後なら true（ポップアップを出す） |
| U03 | `$add_block` | 【追加】追加できない理由（「購入済みです」「年齢区分（CERO C）の対象年齢に達していないため購入できません」）。`null` 以外ならボタンを `disabled` にして理由を出す |
| U03 | `$in_cart`（true/false） | 【追加】カートに入っていれば true（ボタンを「カートに追加済み」にする） |
| U03-a | `$cart_summary` | 【追加】`['count' => 点数, 'total' => 小計]`（ポップアップの「カート内の商品：N点」「小計」） |
| U05 | `$input` | U04 の入力内容（`name`・`email`・`birth_date`・`nickname`・`phone`） |
| U17 | `$totp_secret`・`$totp_uri` | 手入力用の秘密鍵と、QR コードにする文字列（`<div id="qrcode" data-uri="<?= h($totp_uri) ?>"></div>` を置けば JS で描画） |
| U18 | `$recovery_codes` | 復旧コード10個の配列（この画面でしか出ない） |
| U06 | `$locked`（true/false） | ロック中なら true（U06-b の表示） |
| U07 | `$cart_items`・`$total` | カートの商品（`product_id`・`name`・`image`・`price`・`can_buy`・`reason`）と合計金額 |
| U07 | `$item_count` | 【追加】購入できる商品の点数（販売終了などは数えない）。`$cart_items` が空なら U07-a の表示 |
| U08 | `$cart_items`・`$total`・`$item_count` | U07 と同じ（購入できない商品があると U08 には来ない） |
| U08 | `$confirm_token` | 【追加】フォームの hidden に入れる |
| U09 | `$ng_items` | 買えない商品（`name`・`reason`。reason は `販売終了` / `購入済み` / `年齢制限`） |
| U09 | `$reason_counts` | 【追加】理由ごとの点数（`['購入済み' => 1, '年齢制限' => 1]`） |
| U10 | `$order`・`$order_items` | 注文（`order_id`・`order_date`）と購入した商品（`product_name`・`price`） |
| U10 | `$order_number` | 【追加】表示用の注文番号（R＋注文日 yyMMdd＋通し番号。例 R261117001） |
| U11 | `$library`・`$selected` | 購入済み商品の配列と、選んだ商品1件 |
| U11 | `$library` の列 | 【追加】`product_id`・`name`（購入時の商品名）・`price`（購入時の価格）・`order_id`・`order_date`・`order_number`（R＋yyMMdd＋注文ID）・`image`・`os_windows`・`os_mac`。並びは購入日時の新しい順 |
| U11 | `$selected` の決め方 | 【追加】`mypage.php?id=商品ID` の商品（自分の一覧にあるときだけ）。無ければ先頭。0 件なら `null`（「購入したゲームはまだありません」を出す） |
| U11 | ダウンロード | 【追加】`download.php?id=商品ID` へのリンク（`<a>`）。ボタンにしない |
| U11〜U16 | `$mypage_active` | 【追加】左メニューの選択中の項目（`library` / `account` / `password` / `withdraw`）。メニューは `includes/mypage_menu.php` を include する |
| U12 | `$member` | 現在の会員情報（`nickname`・`email`・`phone`） |

---

## 3. 管理者画面（`/admin/` の中）

| 画面ID | 画面名 | ファイル |
|---|---|---|
| A01 | 管理者ログイン | `admin/login.php` |
| A02 | 管理メニュー | `admin/index.php` |
| A03 | 商品管理一覧 | `admin/products.php` |
| A04 / A05 | 非公開確認／再公開確認 | `admin/product_visibility.php?id=商品ID` |
| A06 / A06-a | 商品登録・編集 | `admin/product_edit.php`（新規）／`admin/product_edit.php?id=商品ID`（編集） |
| A07 | 商品登録・編集確認 | `admin/product_confirm.php` |
| A08 | 会員管理一覧 | `admin/members.php` |
| A09 | 会員詳細 | `admin/member.php?id=会員ID` |
| A10 | 注文管理一覧 | `admin/orders.php` |
| A11 | 注文詳細 | `admin/order.php?id=注文ID` |
| — | ログアウト | `admin/logout.php` |

### 入力項目（name）

| 画面 | 項目 | 部品 | name |
|---|---|---|---|
| A01 | 管理者ID | text | `login_id` |
| A01 | パスワード | password | `password` |
| A04・A05 | 非公開にする／公開する | button | hidden `product_id`・`action`（値：`hide` / `show`） |
| A06 | タイトル | text | `name` |
| A06 | 英語名 | text | `title_en` |
| A06 | ジャンル | select | `genre` |
| A06 | 価格（円） | number | `price` |
| A06 | 容量（GB） | number（`step="0.1"`） | `capacity` |
| A06 | 発売日 | date | `release_date` |
| A06 | 開発元 | text | `developer` |
| A06 | 年齢区分（CERO） | select | `age_rating` |
| A06 | 説明文 | textarea | `description` |
| A06 | 配布ファイル（ZIP） | file | `game_file` |
| A06 | プレイ人数（最小・最大） | number ×2 | `min_players` / `max_players` |
| A06 | 遊び方 | select | `play_style` |
| A06 | 1回のプレイ時間 | text | `play_time` |
| A06 | 対応OS | checkbox ×2 | `os_windows`（値：`1`）／`os_mac`（値：`1`） |
| A06 | クロスプレイ | checkbox | `crossplay_flag`（値：`1`） |
| A06 | カバー画像 | file | `image` |
| A06 | スクリーンショット1〜3 | file ×3 | `screenshot1` / `screenshot2` / `screenshot3` |
| A06 | （フォーム） | — | ファイルを送るので `<form method="post" enctype="multipart/form-data">` |
| A07 | 修正する／登録する | button ×2 | `action`（値：`back` / `save`） |
| A08 | 検索キーワード | text（GET） | `keyword` |
| A10 | 期間（開始・終了） | date ×2（GET） | `date_from` / `date_to` |
| A10 | 会員 | text（GET） | `member` |
| A10 | 商品 | text（GET） | `product` |

### 表示に使う変数（PHP 側が用意する）

| 画面 | 変数 | 中身 |
|---|---|---|
| A03 | `$products` | 全商品（非公開も含む。`public_flag` で公開／非公開を出し分け） |
| A04・A05 | `$product` | 対象の商品1件 |
| A06-a | `$product` | 編集する商品1件（初期値に使う） |
| A07 | `$input` | A06 の入力内容 |
| A08 | `$members` | 会員の配列（`member_id`・`name`・`email`・`created_at`・`withdrawal_flag`） |
| A09 | `$member`・`$orders` | 会員1件とその購入履歴 |
| A10 | `$orders` | 注文の配列（`order_id`・`order_date`・会員名・合計金額） |
| A11 | `$order`・`$order_items` | 注文1件と明細（`product_name`・`price`） |

---

## 4. 変えたいとき

- name や変数名を変えたい・足したいときは、作る前にチャットで一言ください（趙が PHP 側をそろえます）
- 画面が増えたときはこの表に行を足してください
