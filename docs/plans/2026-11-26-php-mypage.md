# Plan：PHP 実装 第3弾（マイページ・ダウンロード）

- 日付：2026-11-26（ガント No.23 業務機能実装(PHP) 11/12〜12/2。チェックリスト F93「マイページ・ダウンロード」。No.25 単体テストが 11/30 開始）
- 作業場所：`/Applications/XAMPP/xamppfiles/htdocs/playduo`、ブランチ `wip/php-mypage`（基点 `8deced8` ＝ `style/css-tweaks` の先端。Figma 版ヘッダー・CSS の上に積む）、基線 298 tests 緑
- 期待結果の根拠（優先順）：テスト仕様書（利用者）第2版 線上版（木本 OneDrive、11/26 読込、U11〜U16・COM・SC）→ `docs/画面とPHPの対応表.md`（ファイル名・name・変数名）→ `sql/schema.sql` → 本 Plan §2.0 の決定。実装コードは根拠にしない
- 第1・2弾の作法を踏襲：画面ファイルは表示変数を用意するだけ、ロジックは `includes/`、関数は `int $now` を受ける、SQL はプリペアド、HTML は対応表どおりの name。見た目（CSS）は本 Plan の範囲外（HTML は Figma U11〜U16 の構造に合わせた class を付けるだけ）

---

## 1. 目標と非目標

### 目標
1. U11 マイページ（購入履歴／ライブラリ）`mypage.php`：左メニュー、購入したゲーム一覧（件数・購入日）、選んだゲームの詳細（画像・名称・対応OS・購入日・注文番号・購入時の価格・ダウンロード・注意書き）、完了メッセージ欄
2. ダウンロード `download.php?id=商品ID`（画面なし）：本人が購入した商品だけ、配布ファイルを送る。非公開（販売終了）になった商品も可
3. U12 会員情報の変更 `account_edit.php`：ニックネーム・メール・電話、現在のパスワードで本人確認
4. U13 パスワードの変更 `password_change.php`：現在のパスワード・新しいパスワード・確認
5. U16 退会 `withdraw.php`：注意事項、現在のパスワード、同意チェック、論理削除＋カート削除＋ログアウト
6. 配布ファイルのダミー（`downloads/gNN.zip`）と、Web から直接取れないようにする `.htaccess`

### 非目標
- CSS（吉松 No.22 の範囲。HTML の class は Figma の構造どおりに付けておく）
- 管理者画面（チェックリスト F94・F95、次の Plan）
- U13・U16 の「パスワード表示切替アイコン」（U13-002 は JS＝吉松。PHP は `type="password"` の欄を置くまで）
- ページング（U11-014 は低優先・要確認 Q22。商品は 8 件なので 1 会員の履歴も最大 8 件。全件をスクロールで表示）
- `sql/schema.sql` の変更（山﨑 ER 準拠）
- 退会会員の個人情報の削除・匿名化（論理削除のみ。業務フロー 10/14 決定）

---

## 2. 設計

### 2.0 未決の要確認事項への推奨回答（Codex 評審で確定 → 趙の確認後、テスト仕様書「要確認事項」に回答を書く）

| Q | 推奨回答 | 理由 |
|---|---|---|
| Q22 並び順 | `ORDER BY o.order_date DESC, o.order_id DESC, d.product_id ASC`（order_details に連番列は無い：Codex P1-2） | U11-004 の暫定どおり。同日内も決定的にする |
| Q22 0 件 | 「購入したゲームはまだありません」＋「ゲームを探す」（U02）を出す。詳細欄は出さない。エラーにしない | U11-009 |
| Q22 件数が多い | 現時点の小規模データ（商品 8 件）ではページングを省略し全件をスクロール。件数が増えたときの課題として残す（Codex P2-12） | 名称の折り返しは CSS 側 |
| Q22 販売終了 | 一覧に出し、ダウンロードもできる（U11-006 確定済みと同じ） | 画面設計書 U11 |
| Q22 実体 | `products.file_path`（`downloads/gNN.zip`）の ZIP をそのまま送る。開発用は中身が説明テキスト 1 枚のダミー ZIP を同梱 | ER どおり。本物のゲームは無い |
| Q18 (a) | 現在の登録値を入力欄に初期表示（パスワード欄は空） | U12-001 |
| Q18 (b) | 変更なしでも保存でき、完了メッセージを出す | 判定を増やさない |
| Q18 (c) | ニックネーム・電話は空欄で保存すると削除（NULL） | 任意項目 |
| Q18 (d) | ログイン・再設定の失敗回数には数えない（アカウントはロックしない）。代わりに **セッションごとに** 現在パスワードの失敗を数え、5 回で 10 分間 U12・U13・U16 の照合を止める（「しばらくしてからお試しください」）。失敗は error_log に会員ID だけ残す | Codex P1-10（奪取セッションからの総当たり防止）。アカウント DoS は避ける |
| Q19 | 現在と同じパスワードへの変更は**許可**（U13・U15 とも）。同じ値でも新しいハッシュで保存し、現セッション ID を付け替える。**他端末のセッションは維持**（全端末失効は schema 変更が要るので範囲外） | Codex 同意（Q19・P1-7） |
| Q21 同意 | 初期は未チェック。未チェックで送るとエラー（「注意事項に同意してください」） | U16-001・U16-006 |
| Q21 完了後 | ログアウトしてトップへ、メッセージ「退会が完了しました」 | 推定どおり |
| Q21 再登録 | 同じメールでは再登録できない（「このメールアドレスは登録済みです」） | `members.email` は UNIQUE、退会は論理削除。行を残す以上、再登録を許すには ER 変更が要る |
| Q21 カート・他端末 | 退会と同じトランザクションでカートを削除。他端末のセッションは次の画面表示で未ログイン扱い（bootstrap の `find_login_member` が退会済みを返さない＝既存動作） | 業務フロー 10/8「退会時はカート削除」 |
| Q26 キャンセル | U12・U13 の「キャンセル」、U16 の「退会せずに戻る」は U11（`mypage.php`）へ戻る（保存しない） | マイページ配下の画面 |
| Q05 ダウンロード | 未ログインでダウンロード URL → ログイン画面、ログイン後は U11 に戻る（ファイルは自動では送らない） | U11-012。戻り先にダウンロードを入れるとログイン直後に勝手にファイルが落ちる |

### 2.1 ファイル構成（追加・変更）

```
includes/
  library_repo.php     member_library(pdo, memberId)          … U11 一覧（購入日の新しい順・購入時価格・注文ID・注文日・商品の画像/OS/file_path）
                       find_library_item(pdo, memberId, productId) … 本人が購入した 1 件（なければ null）
  download.php         download_file_path(string filePath): ?string … downloads/ 配下に解決できたときだけ絶対パス（../ や絶対パスは null）
  account.php          validate_account_input(post) → errors・input（U12。メール・ニックネーム・電話の規則は U04 と同じ関数を使う）
                       update_account(pdo, memberId, input, currentPassword) → 'ok'|'wrong_password'|'email_taken'
                       change_password(pdo, memberId, currentPassword, newPassword) → 'ok'|'wrong_password'
                       withdraw_member(pdo, memberId, currentPassword) → 'ok'|'wrong_password'|'already'
  member_repo.php      （追加）find_account(pdo, memberId)、update_profile(...)、email_taken_by_other(...)、mark_withdrawn(...)
  validation.php       （リファクタ）U04 のメール・ニックネーム・電話の判定を小関数に分け、U04・U12 で共有（DRY）
  auth_guard.php       LOGIN_RETURN_PAGES に account_edit・password_change・withdraw を追加
mypage.php             （差し替え）U11
download.php           （新規）ダウンロード
account_edit.php       （新規）U12
password_change.php    （新規）U13
withdraw.php           （新規）U16
includes/mypage_menu.php （新規）U11〜U16 共通の左メニュー（選択中の項目を $mypage_active で渡す）
downloads/gNN.zip ×8   （新規）ダミー配布ファイル（tools/make_dummy_downloads.php で生成して commit）
.htaccess              （変更）downloads/ を既存の RewriteRule の並びに足して 404（1 か所だけで止める：Codex P2-14）
tests/...              下記 §3
docs/画面とPHPの対応表.md （変更）U11 の $library 各列、U16 の hidden、$mypage_active を【追加】で追記
```

### 2.2 U11 マイページ
- `require_login($login_member, ['page' => 'mypage'])`、`send_no_store_headers()`（ログアウト・退会後の戻るで出さない：COM-003・U16-010）。**U12・U13・U16・download も全部 no-store**（Codex P1-9）
- `$library` ＝ `member_library()`。各行：`product_id`・`name`（**order_details.product_name＝購入時の名称**：Codex P2-11）・`image`・`os_windows`・`os_mac`（現行 products）・`price`（**order_details.price＝購入時**）・`order_id`・`order_date`・`order_number`（format_order_number）
- `$selected` ＝ `?id=` を `parse_positive_int()` で読み（配列・0・負数・文字列は無視：Codex P2-13）、その商品が `$library` にあればその行、なければ先頭（0 件なら null）。他会員の商品 ID を渡しても自分の一覧に無いので出ない（U11-011）
- メッセージ（U12・U13 の完了）は `$message_in_page = true` で右パネル上部に出す（U11-010）
- 一覧の各行は `mypage.php?id=…` へのリンク（選択中は `aria-current="true"`）。JS 無しで切替（U11-003）

### 2.3 ダウンロード
- GET `download.php?id=商品ID`。`id` は `parse_positive_int()`、不正は 404。未ログイン → `require_login(..., ['page' => 'mypage'])`（§2.0 Q05）
- `find_library_item()` で本人の購入を確認。無い・ID 不正 → 404（「この商品は見つかりません」。他会員の購入かどうかを区別しない）
- 非公開（public_flag=0）でも購入済みなら可（U11-006）
- `download_file_path()`：file_path は `downloads/` ＋ `[A-Za-z0-9_-]+\.zip` の形だけ受ける。canonical root（`realpath(downloads)`）を確定し、`realpath(file)` が `root . DIRECTORY_SEPARATOR` で始まり、`is_file()`・`is_readable()` のときだけ採用（Codex P1-3）。送信ファイル名は検証済み basename。ファイルが無い・読めない → ログ付き 404
- 認可・パス解決・`filesize()` をすべて済ませてからヘッダーを送る。出力バッファを全部捨て、`Content-Type: application/zip`・`Content-Disposition: attachment; filename="gNN.zip"`・`X-Content-Type-Options: nosniff`・no-store・`Content-Length` → `readfile()` → 直後に `exit`（Codex P1-4）
- 退会後は bootstrap で未ログイン扱い → ログイン要求（U16-005）

### 2.4 U12 会員情報の変更
- 表示：`$member`（nickname・email・phone）。POST エラー時は `$old` を優先（パスワードは戻さない）
- 入力規則：U04 と同じ（メール必須・254 以内・形式／ニックネーム任意 2〜20／電話任意 PHONE_PATTERN）。氏名・生年月日は受け取らない（送られても無視：U12-014）
- 本人確認：`current_password` 未入力 → エラー、照合失敗 → 「現在のパスワードが正しくありません」、保存しない（U12-008・009）
- メール重複：自分以外の会員（退会済みを含む＝UNIQUE）と重複 → 「このメールアドレスは登録済みです」（U12-006）
- 保存は 1 トランザクション（会員行を FOR UPDATE → パスワード照合 → 重複確認 → UPDATE）。UNIQUE 違反（同時に同じメール）も「登録済み」に読み替える
- 成功 → flash「会員情報を変更しました」→ `mypage.php`（U12-002）

### 2.5 U13 パスワードの変更
- 必須 3 欄、新パスワードは `validate_new_password()`（U04・U15 と同じ規則：8〜72・英字と数字）
- 照合失敗 → 「現在のパスワードが正しくありません」
- 成功 → `password_hash`、更新は FOR UPDATE の中で。`renew_session_for_auth()`（セッション ID の付け替え）、flash「パスワードを変更しました」→ `mypage.php`（U13-003）
- 進行中のパスワード再設定（U15）は password_fingerprint が変わるので自動で無効（U15-014、既存の仕組み）

### 2.6 U16 退会
- 必須：`current_password`、`agree=1`。未チェック → 「注意事項に同意してください」
- `withdraw_member()`：1 トランザクションで 会員行 FOR UPDATE（退会済みなら 'already'）→ パスワード照合 → `withdrawal_flag=1` → `DELETE FROM carts WHERE member_id=?`
- 成功 → commit → `destroy_session()` → `start_app_session()`（新しい cookie）→ flash「退会が完了しました」→ `index.php`（Codex P1-6）
- 二重送信（U16-011）：2 回目は 1 回目でログアウト済みのため CSRF/ログイン要求で止まる。同時の 2 リクエストは FOR UPDATE＋'already' で 1 回だけ更新
- 退会後：ログイン不可・再設定不可は既存（`find_active_member_*` が withdrawal_flag=0 だけを見る）

### 2.7 HTML（CSS は範囲外）
- Figma U11〜U16 の構造：`<div class="mypage-layout">` に `<nav class="mypage-menu">`（4 項目、選択中 `aria-current="page"`）と `<section class="mypage-panel">`。フォームは対応表の name。メッセージ欄は既存の `.message` / `.form-alert`
- 文言は画面設計書・テスト仕様書の期待結果に出てくるもの（「購入したゲーム（N件）」「ダウンロード」、U13 の説明 2 文、U16 の注意事項 3 点・警告文）をそのまま使う

---

## 3. 任務清單（TDD：各項目 RED → GREEN）

| # | タスク | DoD |
|---|---|---|
| T1 | `library_repo.php`：member_library / find_library_item | Integration：並び順（日時降順・同注文は登録順）、購入時価格が商品価格の変更に影響されない、販売終了も含む、他会員の購入は出ない、0 件で [] |
| T2 | download_file_path | Unit：downloads 配下の正常系、`../` 経由・絶対パス・存在しないファイルは null |
| T3 | ダミー ZIP 生成ツール＋ルート `.htaccess` | seed.sql の file_path すべてに ZIP があり開ける（テストで検査：Codex P2-15）、HtaccessTest に `downloads/g01.zip` ブロックの行を追加して緑、**Apache 実機で `curl http://localhost/playduo/downloads/g01.zip` が 404（手動）** |
| T4 | validation のリファクタ（メール・ニックネーム・電話の純粋小関数だけ切り出す：Codex P2-16） | 既存 ValidationTest・AuthFlowTest が無修正で緑（文言・trim・NULL 変換が変わらない） |
| T5 | account.php：validate_account_input / update_account | Unit＋Integration：各入力エラー、現在PW 未入力・誤り、メール重複（他会員・退会済み）、変更なし保存、空欄で NULL、生年月日・氏名は変わらない |
| T6 | change_password | Integration：誤り、正常（新で照合可・旧で不可）、同じパスワード許可 |
| T7 | withdraw_member＋inactive 応答＋bootstrap の後始末 | Integration：誤り、正常（flag=1・カート削除）、2 回目は 'already'、退会後 attempt_login 不可、退会済みの会員に change_password/update_account は 'inactive'（Codex P1-5）、**別接続で会員行をロック中は退会・パスワード変更が待たされる**（既存 ShopTest の holder＋lock wait timeout の方式）、メール重複の UNIQUE 違反を email_taken に読み替え。E2E：退会済みと分かったセッションは auth フローの状態も消える（Codex P1-8） |
| T7b | 現在パスワードのセッション内スロットル | Unit：5 回目の失敗で以降 10 分は照合せず throttled、10 分後に解除、成功しても他の会員には影響しない |
| T8 | 画面 5 つ＋共通メニュー＋auth_guard | E2E：U11 一覧・詳細・選択・0 件・メッセージ、他会員 ID、未ログイン→ログイン→U11、ダウンロード（200・Content-Disposition・中身）／他会員・未購入・未ログイン・退会後は不可、U12・U13・U16 の正常系と主な異常系、CSRF 無しは拒否 |
| T9 | 対応表の追記 | 【追加】行が入っている |

## 4. 驗收標準
- `vendor/bin/phpunit` 全緑（基線 298 から増えるのみ、既存の修正は T4 のリファクタに伴うものだけ）
- 上の DoD の各テストが存在して緑
- 手動：テスト会員でログイン→購入→U11→ダウンロードで ZIP が保存される

## 5. 測試策略
- Unit（`tests/Unit/`）：download_file_path、validate_account_input
- Integration（`tests/Integration/`、TestDb）：library_repo、update_account、change_password、withdraw_member
- E2E（`tests/E2E/`、AppServer＋HttpClient）：画面遷移・ダウンロード・権限
- カバレッジ：pcov が無く計測不能（既知、趙の許可待ち）。新規関数はすべて Unit/Integration で通す

## 6. 影響面・リスク・回退
- 影響：`validation.php`（リファクタ）、`auth_guard.php`、`.htaccess`、`mypage.php` の差し替え（既存 E2E「暫定マイページ 200」は U11 の 200 で引き続き通る）
- リスク：ダウンロードのパス解決（`realpath` で閉じ込め）、退会の二重実行（FOR UPDATE）、メール変更の同時実行（UNIQUE 違反を捕まえる）
- 回退：`wip/php-mypage` は push しない。問題があればブランチごと捨てる

## 7. 並行候補
- なし（T5〜T7 は account.php・member_repo.php を共有、T8 は全部に依存。直列で行う）

---

## 付録 A：Codex 評審記録

### 第 1 ラウンド（gpt-5.6-terra / medium、16 条：P0×1・P1×9・P2×6）
| # | 指摘 | 裁定 |
|---|---|---|
| P0-1 | downloads を document root 外へ | **修正採用**：リポジトリ＝document root（XAMPP、組員は clone で動かす）なので外には置けない。代わりに既存の sql/docs と同じルート RewriteRule 1 か所で 404、Apache 実機の curl 確認を DoD に追加。※第 2 ラウンドで反論を送ったが Codex の利用上限で回答なし＝**未経 Codex 第 2 輪評審（主 session 裁定）** |
| P1-2 | order_detail_id は schema に無い | 採用（order_id・product_id で並べる） |
| P1-3 | realpath の prefix 判定 | 採用 |
| P1-4 | 送信前後の状態 | 採用 |
| P1-5 | 退会との競合で wrong_password 誤表示・並行テスト | 採用（'inactive'）。並行テストは第 2 ラウンドで「単一接続のみ」と反論したが、既存 ShopTest に別接続ロックのテストがある（Codex が提示した証拠）ため反論を撤回し、同じ方式で 1〜2 本追加 |
| P1-6 | destroy_session 後の flash | 採用 |
| P1-7 | パスワード変更時の他端末 | 採用（維持を明記） |
| P1-8 | 退会済み判明時に member_id だけ消している | 採用（clear_auth_flows＋session ID 付け替え） |
| P1-9 | no-store の範囲 | 採用 |
| P1-10 | 現在パスワードの無制限試行 | 採用（セッション単位スロットル＋ログ。IP 単位は保存先が要るので範囲外） |
| P2-11 | 履歴の名称は購入時の product_name | 採用 |
| P2-12 | 8 件前提の書き方 | 採用 |
| P2-13 | id の型 | 採用（parse_positive_int） |
| P2-14 | .htaccess 二重 | 採用（ルート 1 か所） |
| P2-15 | ZIP の commit | 修正採用（組員の手動確認のため commit、seed の file_path と一致するかをテストで検査） |
| P2-16 | validation リファクタの範囲 | 採用（純粋小関数だけ） |

§2.0 への評価：Q18(d) 条件付き妥当（→スロットル追加）、Q19 妥当、Q21 概ね妥当（→手順具体化）、Q05 妥当。

### 第 2 ラウンド
- P0-1・P1-5 への反論を送信 → Codex 利用上限（15:46 まで）で回答なし。P1-5 は上記のとおり自ら撤回。**P0-1 の裁定は Codex 未確認**のまま、趙への報告で明示する
