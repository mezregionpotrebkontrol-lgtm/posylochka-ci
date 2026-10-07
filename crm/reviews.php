<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['hide', 'show', 'delete'], true)) {
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            crm_flash_set('Отзыв удалён.');
        } else {
            $pdo->prepare('UPDATE reviews SET is_published = ? WHERE id = ?')
                ->execute([$action === 'show' ? 1 : 0, $id]);
            crm_flash_set($action === 'show' ? 'Отзыв опубликован.' : 'Отзыв скрыт с сайта.');
        }
    }
    crm_redirect('/crm/reviews.php');
}

// Отзывы с сайта публикуются сразу (без модерации, как на реальном сайте) —
// здесь можно скрыть неподходящий или удалить его.
$published = $pdo->query("SELECT * FROM reviews WHERE is_published = 1 ORDER BY created_at DESC LIMIT 100")->fetchAll();
$hidden = $pdo->query("SELECT * FROM reviews WHERE is_published = 0 ORDER BY created_at DESC LIMIT 50")->fetchAll();

$pageTitle = 'Отзывы';
$activeNav = 'reviews';
require __DIR__ . '/includes/layout_top.php';

function render_review_row(array $r, bool $isPublished): void
{
    echo '<tr>';
    echo '<td>' . e($r['author_name']) . '</td>';
    echo '<td>' . str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) . '</td>';
    echo '<td style="max-width:360px;">' . nl2br(e($r['review_text'])) . '</td>';
    echo '<td>' . ($r['photo_path'] ? '<a href="/api/' . e($r['photo_path']) . '" target="_blank">фото</a>' : '—') . '</td>';
    echo '<td>' . crm_date($r['created_at']) . '</td>';
    echo '<td>';
    echo '<form method="post" class="inline-row">' . crm_csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $r['id'] . '">'
        . '<button class="btn small" type="submit" name="action" value="' . ($isPublished ? 'hide' : 'show') . '">'
        . ($isPublished ? 'Скрыть' : 'Опубликовать') . '</button>'
        . '<button class="btn small secondary" type="submit" name="action" value="delete" onclick="return confirm(\'Удалить отзыв?\');">Удалить</button>'
        . '</form>';
    echo '</td></tr>';
}
?>

<div class="card">
  <h3 style="margin-top:0;">Опубликованные (<?= count($published) ?>)</h3>
  <p class="text-muted" style="margin-top:-8px;">Отзывы с сайта публикуются сразу, без модерации — здесь можно скрыть неподходящий.</p>
  <?php if (!$published): ?>
    <div class="empty-state">Пока нет отзывов.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Автор</th><th>Оценка</th><th>Текст</th><th>Фото</th><th>Когда</th><th>Действие</th></tr></thead>
      <tbody><?php foreach ($published as $r) { render_review_row($r, true); } ?></tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Скрытые (<?= count($hidden) ?>)</h3>
  <?php if (!$hidden): ?>
    <div class="empty-state">Пока нет.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Автор</th><th>Оценка</th><th>Текст</th><th>Фото</th><th>Когда</th><th>Действие</th></tr></thead>
      <tbody><?php foreach ($hidden as $r) { render_review_row($r, false); } ?></tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
