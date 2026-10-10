<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

if ($user['role'] === 'courier') {
    crm_redirect('/crm/my-orders.php');
}

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE status = 'new') AS cnt_new,
    (SELECT COUNT(*) FROM orders WHERE status = 'accepted') AS cnt_accepted,
    (SELECT COUNT(*) FROM orders WHERE status = 'collecting') AS cnt_collecting,
    (SELECT COUNT(*) FROM orders WHERE status = 'in_transit') AS cnt_in_transit,
    (SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND DATE(updated_at) = CURDATE()) AS cnt_delivered_today,
    (SELECT COUNT(*) FROM clients) AS cnt_clients,
    (SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status IN ('draft','sent')) AS pending_amount
")->fetch();

$recentOrders = $pdo->query("SELECT o.*, c.name AS client_name FROM orders o JOIN clients c ON c.id = o.client_id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();

$pageTitle = 'Главная';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="grid-stats">
  <div class="stat"><div class="num"><?= (int)$stats['cnt_new'] ?></div><div class="label">Новых заявок</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_accepted'] ?></div><div class="label">Принято, ждут отправки</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_collecting'] ?></div><div class="label">Идёт сбор груза</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_in_transit'] ?></div><div class="label">В пути</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_delivered_today'] ?></div><div class="label">Доставлено сегодня</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_clients'] ?></div><div class="label">Клиентов в базе</div></div>
  <div class="stat"><div class="num"><?= crm_money((float)$stats['pending_amount']) ?></div><div class="label">Ожидает оплаты</div></div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">Последние заявки</h3>
    <a class="btn small" href="/crm/order.php">+ Новая заявка</a>
  </div>
  <?php if (!$recentOrders): ?>
    <div class="empty-state">Заявок пока нет — создайте первую.</div>
  <?php else: ?>
  <table style="margin-top:14px;">
    <thead><tr><th>№</th><th>Клиент</th><th>Маршрут</th><th>Статус</th><th>Создана</th></tr></thead>
    <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><?= e($o['client_name']) ?></td>
        <td><?= e($o['from_city']) ?> → <?= e($o['to_city']) ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td><?= crm_date($o['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
