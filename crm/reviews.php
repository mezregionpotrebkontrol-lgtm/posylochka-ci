<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['approve', 'reject', 'delete'], true)) {
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            crm_flash_set('Отзыв удалён.');
        } else {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$status, $id]);
            crm_flash_set($action === 'approve' ? 'Отзыв опубликован.' : 'Отзыв отклонён.');
        }
    }
    crm_redirect('/crm/reviews.php');
}

$pending = $pdo->query("SELECT * FROM reviews WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();
$published = $pdo->query("SELECT * FROM reviews WHERE status != 'pending' ORDER BY created_at DESC LIMIT 50")->fetchAll();

$pageTitle = 'Отзывы';
$activeNav = 'reviews';
require __DIR__ . '/includes/layout_top.php';

function render_review_row(array $r, bool $pending): void
{
    echo '<tr>';
    echo '<td>' . e($r['author_name']) . '</td>';
    echo '<td>' . str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) . '</td>';
    echo '<td style="max-width:360px;">' . nl2br(e($r['review_text'])) . '</td>';
    echo '<td>' . ($r['photo_path'] ? '<a href="/' . e($r['photo_path']) . '" target="_blank">фото</a>' : '—') . '</td>';
    echo '<td>' . crm_date($r['created_at']) . '</td>';
    echo '<td>';
    if ($pending) {
        echo '<form method="post" class="inline-row">' . crm_csrf_field()
            . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
            . '<button class="btn small" type="submit" name="action" value="approve">Опубликовать</button>'
            . '<button class="btn small secondary" type="submit" name="action" value="reject">Отклонить</button>'
            . '</form>';
    } else {
        echo '<span class="text-muted">' . ($r['status'] === 'approved' ? 'Опубликован' : 'Отклонён') . '</span> ';
        echo '<form method="post" class="inline-row" style="display:inline;" onsubmit="return confirm(\'Удалить отзыв?\');">' . crm_csrf_field()
            . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
            . '<button class="btn small secondary" type="submit" name="action" value="delete">Удалить</button>'
            . '</form>';
    }
    echo '</td></tr>';
}
?>

<div class="card">
  <h3 style="margin-top:0;">Ждут модерации (<?= count($pending) ?>)</h3>
  <?php if (!$pending): ?>
    <div class="empty-state">Новых отзывов нет.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Автор</th><th>Оценка</th><th>Текст</th><th>Фото</th><th>Когда</th><th>Действие</th></tr></thead>
      <tbody><?php foreach ($pending as $r) { render_review_row($r, true); } ?></tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Опубликованные / отклонённые (последние 50)</h3>
  <?php if (!$published): ?>
    <div class="empty-state">Пока нет.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Автор</th><th>Оценка</th><th>Текст</th><th>Фото</th><th>Когда</th><th>Действие</th></tr></thead>
      <tbody><?php foreach ($published as $r) { render_review_row($r, false); } ?></tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
