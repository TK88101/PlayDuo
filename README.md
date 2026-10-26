# Play Duo

友達と一緒に始める PC ゲーム専門店（システム開発演習Ⅰ チーム開発・マルチラボ）。
PHP / HTML / CSS / JavaScript / MySQL（フレームワークなし）。

---

## はじめての人へ：自分の PC で動かすまで（約 20 分）

**ゴール**：ブラウザで `http://localhost/playduo/tools/db_check.php` を開いて
「**OK：playduo に接続しました**」と出ること。出たら準備完了です。

上から順番にやってください。Windows と Mac で違うところは分けて書いてあります。

### ステップ 1：ソフトを 2 つ入れる（最初の 1 回だけ）

| ソフト | 何に使う | ダウンロード |
|---|---|---|
| **XAMPP** | PHP と MySQL を自分の PC で動かす | https://www.apachefriends.org/jp/ |
| **Git** | GitHub からソースを持ってくる | Windows：https://git-scm.com/downloads ／ Mac：下の説明 |

- **XAMPP**：インストーラーの設定は全部そのまま「次へ」でよい
- **Git（Windows）**：インストーラーの設定は全部そのまま「Next」でよい
- **Git（Mac）**：「ターミナル」アプリを開いて `git --version` と打つ。入っていなければ「インストールしますか？」と出るので「インストール」を押す

### ステップ 2：ソースを持ってくる（clone）

XAMPP の **`htdocs` フォルダ**の中に置きます。ここに置いたものだけがブラウザで開けます。

**Windows**：スタートメニューで「cmd」と検索して「コマンドプロンプト」を開き、1 行ずつコピーして Enter

```
cd C:\xampp\htdocs
git clone https://github.com/TK88101/PlayDuo.git playduo
```

**Mac**：「ターミナル」を開き、1 行ずつコピーして Enter

```
cd /Applications/XAMPP/xamppfiles/htdocs
git clone https://github.com/TK88101/PlayDuo.git playduo
```

> Mac で `Permission denied` と出たら、代わりに次の 2 行を打つ（パスワードは Mac のログインパスワード。打っても画面には何も出ないが、そのまま Enter）
> ```
> sudo git clone https://github.com/TK88101/PlayDuo.git playduo
> sudo chown -R "$(whoami)" playduo
> ```

終わると `htdocs` の中に `playduo` フォルダができています。

### ステップ 3：接続設定ファイルを作る

`config` フォルダの中の `config.sample.php` を**コピー**して、名前を `config.php` にします。
ステップ 2 と同じ画面で、続けて打ってください。

**Windows**

```
cd playduo
copy config\config.sample.php config\config.php
```

**Mac**

```
cd playduo
cp config/config.sample.php config/config.php
```

中身は書き換えなくて大丈夫です（XAMPP の初期設定に合わせてあります）。
`config.php` は人によって中身が違うファイルなので、GitHub には上がらないようになっています。

### ステップ 4：XAMPP を起動して、データベースを作る

1. XAMPP のコントロールパネルを開く
   - Windows：スタートメニューの「XAMPP Control Panel」
   - Mac：アプリケーション → XAMPP → `manager-osx`（「Manage Servers」タブ）
2. **Apache** と **MySQL** の「Start」を押す（Windows は名前が緑色になれば OK、Mac は「Running」になれば OK）
3. ブラウザで http://localhost/phpmyadmin/ を開く
4. 左上の「**新規作成**」をクリック（英語表示なら「New」）
5. データベース名に `playduo` と入力、右の選択欄で `utf8mb4_general_ci` を選んで「作成」（英語表示なら「Create」）

### ステップ 5：確認

ブラウザで http://localhost/playduo/tools/db_check.php を開く。

「**OK：playduo に接続しました**」と出れば完了です。
「NG」と出たら、下の「うまくいかないとき」を見てください。

---

## うまくいかないとき

| 症状 | 原因と直し方 |
|---|---|
| `git` が見つからない／認識されない | Git が入っていない。ステップ 1 をやり直し、**コマンドプロンプトを一度閉じて開き直す** |
| Apache が Start できない（赤字のエラー） | 他のソフトがポート 80 を使っている。Skype などを終了して再挑戦。だめなら班のチャットで相談 |
| MySQL が Start できない | 別の MySQL が動いている。PC を再起動してから XAMPP だけ起動してみる |
| ページが「Not Found」 | フォルダの場所か名前が違う。`htdocs` の中に `playduo`（小文字）があるか確認 |
| NG：`config/config.php がありません` | ステップ 3 をやっていない |
| NG：`Unknown database 'playduo'` | ステップ 4 のデータベース作成をやっていない（名前のつづりも確認） |
| NG：`Connection refused`／`No such file or directory`／`対象のコンピューターによって拒否` | MySQL が起動していない。ステップ 4 の 2 を確認 |
| NG：`Access denied for user 'root'` | MySQL に自分でパスワードを設定している人だけ。`config/config.php` の `password` にそのパスワードを書く |

解決しないときは、**エラーの画面のスクショ**を班のチャットに貼ってください。

---

## 2 回目からの始め方

1. XAMPP で **Apache** と **MySQL** を Start
2. 最新のソースを取り込む（`playduo` フォルダで）

   ```
   git pull
   ```

3. 作業が終わったら XAMPP で Stop を押してよい

---

## フォルダ構成

| フォルダ | 内容 |
|---|---|
| `config/` | 接続設定（`config.php` は各自で作る） |
| `includes/` | 共通 PHP（`db.php`＝DB 接続） |
| `assets/` | ゲーム画像・商品データ |
| `docs/` | 業務フロー図などの資料 |
| `tools/` | 開発用ツール（`db_check.php` など） |

画面・CSS・JS・SQL のフォルダは、作り始めるときに追加する。

## 決まりごと

- DB へのアクセスは `includes/db.php` の `db()` を使い、SQL は必ずプリペアドステートメントで書く
- 画面に出す値は `htmlspecialchars()` でエスケープする
