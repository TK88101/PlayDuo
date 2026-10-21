# Play Duo

友達と一緒に始める PC ゲーム専門店（システム開発演習Ⅰ チーム開発・マルチラボ）。
PHP / HTML / CSS / JavaScript / MySQL（フレームワークなし）。

## 開発環境の作り方

XAMPP（Apache と MySQL）を使う。

1. **ソースを htdocs に置く**
   - Windows：`C:\xampp\htdocs` で `git clone https://github.com/TK88101/PlayDuo.git playduo`
   - Mac：`/Applications/XAMPP/xamppfiles/htdocs` で同じコマンド（htdocs への書き込みに `sudo` が要る場合あり）
2. **接続設定を作る**：`config/config.sample.php` をコピーして `config/config.php` にする。XAMPP の初期設定（root・パスワードなし）なら中身はそのままでよい。`config.php` は Git に入れない
3. **DB を作る**：XAMPP で Apache と MySQL を起動 → `http://localhost/phpmyadmin/` → 新規作成 → 名前 `playduo`、照合順序 `utf8mb4_general_ci`
4. **確認**：`http://localhost/playduo/tools/db_check.php` を開いて「OK：playduo に接続しました」と出れば完了

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
