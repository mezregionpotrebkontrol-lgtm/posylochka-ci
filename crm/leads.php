<?php
/**
 * Заявки с лендинга (доставка без оплаты + B2B), которые пришли через
 * submit_order_request.php / submit_business_request.php. Это просто лог
 * обращений с сайта — на отличие от "Заявок" (orders), здесь нет статуса
 * и трекинга, это сырые лиды для обработки менеджером (их дублирует MAX-
 * уведомление владельцу в момент поступления).
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$orderRequests = $pdo->query('SELECT * FROM site_order_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();
$businessRequests = $pdo->query('SELECT * FROM business_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();

$pageTitle = 'Заявки с сайта';
$activeNav = 'leads';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Заявки на доставку с лендинга (<?= count($orderRequests) ?>)</h3>
  <?php if (!$orderRequests): ?>
    <div class="empty-state">Пока нет заявок.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Когда</th><th>Имя</th><th>Телефон</th><th>Откуда</th><th>Куда</th><th>Вес</th><th>Комментарий</th></tr></thead>
      <tbody>
        <?php foreach ($orderRequests as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['city'] ?? '—') ?></td>
          <td><?= e($r['dest_city'] ?? '—') ?></td>
          <td><?= e($r['weight'] ?? '—') ?></td>
          <td><?= e($r['comment'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Заявки на сотрудничество (B2B) (<?= count($businessRequests) ?>)</h3>
  <?php if (!$businessRequests): ?>
    <div class="empty-state">Пока нет заявок.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Когда</th><th>Организация</th><th>Контакт</th><th>Телефон</th><th>Email</th><th>Объём</th><th>Города</th></tr></thead>
      <tbody>
        <?php foreach ($businessRequests as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><?= e($r['company']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['email'] ?? '—') ?></td>
          <td><?= e($r['volume'] ?? '—') ?></td>
          <td><?= e($r['cities'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
