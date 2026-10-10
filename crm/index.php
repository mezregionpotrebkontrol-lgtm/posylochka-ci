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

$pageTitle = "\u{413}\u{43b}\u{430}\u{432}\u{43d}\u{430}\u{44f}";
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="grid-stats">
  <div class="stat"><div class="num"><?= (int)$stats['cnt_new'] ?></div><div class="label">&#x41d;&#x43e;&#x432;&#x44b;&#x445; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_accepted'] ?></div><div class="label">&#x41f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x43e;, &#x436;&#x434;&#x443;&#x442; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43a;&#x438;</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_collecting'] ?></div><div class="label">&#x418;&#x434;&#x451;&#x442; &#x441;&#x431;&#x43e;&#x440; &#x433;&#x440;&#x443;&#x437;&#x430;</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_in_transit'] ?></div><div class="label">&#x412; &#x43f;&#x443;&#x442;&#x438;</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_delivered_today'] ?></div><div class="label">&#x414;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x43e; &#x441;&#x435;&#x433;&#x43e;&#x434;&#x43d;&#x44f;</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['cnt_clients'] ?></div><div class="label">&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x43e;&#x432; &#x432; &#x431;&#x430;&#x437;&#x435;</div></div>
  <div class="stat"><div class="num"><?= crm_money((float)$stats['pending_amount']) ?></div><div class="label">&#x41e;&#x436;&#x438;&#x434;&#x430;&#x435;&#x442; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b;</div></div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">&#x41f;&#x43e;&#x441;&#x43b;&#x435;&#x434;&#x43d;&#x438;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;</h3>
    <a class="btn small" href="/crm/order.php">+ &#x41d;&#x43e;&#x432;&#x430;&#x44f; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430;</a>
  </div>
  <?php if (!$recentOrders): ?>
    <div class="empty-state">&#x417;&#x430;&#x44f;&#x432;&#x43e;&#x43a; &#x43f;&#x43e;&#x43a;&#x430; &#x43d;&#x435;&#x442; &#x2014; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x439;&#x442;&#x435; &#x43f;&#x435;&#x440;&#x432;&#x443;&#x44e;.</div>
  <?php else: ?>
  <table style="margin-top:14px;">
    <thead><tr><th>&#x2116;</th><th>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;</th><th>&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x421;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x430;</th></tr></thead>
    <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><?= e($o['client_name']) ?></td>
        <td><?= e($o['from_city']) ?> &#x2192; <?= e($o['to_city']) ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td><?= crm_date($o['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
