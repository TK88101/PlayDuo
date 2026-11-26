<?php
// U11 マイページ（購入履歴／ライブラリ）。Plan 2026-11-26 §2.2
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/library_repo.php';
send_no_store_headers();

$login = require_login($login_member, ['page' => 'mypage']);
$page_title = 'マイページ';
$message_in_page = true;   // U12・U13 の完了メッセージは右パネルの上に出す（U11-010）
$mypage_active = 'library';

$library = member_library(db(), (int) $login['member_id']);
// 選んだゲーム：?id= が自分の一覧にあればそれ、なければ先頭（他会員の ID・不正な値は先頭になる：U11-011）
$selected_id = parse_positive_int($_GET['id'] ?? null);
$selected = $library[0] ?? null;
foreach ($library as $row) {
    if ((int) $row['product_id'] === $selected_id) {
        $selected = $row;
        break;
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>マイページ</h1>
<div class="mypage-layout">
    <?php include __DIR__ . '/includes/mypage_menu.php'; ?>
    <section class="mypage-panel">
        <div class="panel-head">
            <h2>購入履歴（ライブラリ）</h2>
            <?php if (!empty($message)): ?>
                <p class="message"><?= h($message) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($library === []): ?>
            <div class="library-empty">
                <p>購入したゲームはまだありません</p>
                <a class="button-primary" href="search.php">ゲームを探す</a>
            </div>
        <?php else: ?>
            <div class="library">
                <div class="library-list">
                    <p class="library-count">購入したゲーム（<?= h(count($library)) ?>件）</p>
                    <ul>
                        <?php foreach ($library as $row): ?>
                            <li>
                                <a href="mypage.php?id=<?= h($row['product_id']) ?>"<?= $row === $selected ? ' aria-current="true"' : '' ?>>
                                    <span class="library-name"><?= h($row['name']) ?></span>
                                    <span class="library-date">購入日：<?= h(display_date(substr($row['order_date'], 0, 10))) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="library-detail">
                    <div class="library-detail-top">
                        <img src="assets/games/<?= h($selected['image']) ?>" alt="">
                        <div class="library-spec">
                            <h3><?= h($selected['name']) ?></h3>
                            <dl>
                                <dt>対応OS</dt><dd><?= h(os_detail_label($selected)) ?></dd>
                                <dt>購入日</dt><dd><?= h(display_date(substr($selected['order_date'], 0, 10))) ?></dd>
                                <dt>注文番号</dt><dd class="order-number"><?= h($selected['order_number']) ?></dd>
                                <dt>購入時の価格</dt><dd><?= h(yen($selected['price'])) ?></dd>
                            </dl>
                        </div>
                    </div>
                    <a href="download.php?id=<?= h($selected['product_id']) ?>" class="button-primary">ダウンロード</a>
                    <p class="library-note">※ログイン中・購入済みの場合のみダウンロードできます。非公開になったゲームもダウンロードできます。</p>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
