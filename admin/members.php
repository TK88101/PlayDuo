<?php
// A08 会員管理一覧（GET で検索。参照のみ）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_member_repo.php';
require_admin($admin_id);

$page_title = '会員管理';
$keyword = is_string($_GET['keyword'] ?? null) ? trim_all($_GET['keyword']) : '';
$members = search_members(db(), $keyword);
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<h1>会員管理</h1>
<form method="get" action="" class="admin-search">
    <input type="search" name="keyword" value="<?= h($keyword) ?>" placeholder="氏名・メールアドレスで検索">
    <button type="submit">検索</button>
</form>
<?php if ($members === []): ?>
    <p class="empty">該当する会員はいません</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>会員ID</th><th>氏名</th><th>メールアドレス</th><th>生年月日</th><th>登録日</th><th>購入数</th><th>状態</th><th>操作</th></tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member): ?>
                <tr class="member-row">
                    <td><?= h(member_code((int) $member['member_id'])) ?></td>
                    <td><?= h($member['name']) ?></td>
                    <td><?= h($member['email']) ?></td>
                    <td><?= h(display_date($member['birth_date'])) ?></td>
                    <td><?= h(display_date($member['created_at'])) ?></td>
                    <td><?= h($member['purchase_count']) ?></td>
                    <td><?= h(member_status_label($member)) ?></td>
                    <td><a href="member.php?id=<?= h($member['member_id']) ?>">詳細</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
