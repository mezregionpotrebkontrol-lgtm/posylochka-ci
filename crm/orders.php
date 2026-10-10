<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$statusFilter = $_GET['status'] ?? '';
$courierFilter = $_GET['courier'] ?? '';
$pickupFilter = $_GET['pickup'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['new','accepted','collecting','in_transit','delivered','cancelled'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($courierFilter !== '') {
    $where[] = 'o.courier_id = ?';
    $params[] = (int) $courierFilter;
}
if ($pickupFilter !== '' && in_array($pickupFilter, ['self','courier'], true)) {
    $where[] = 'o.pickup_type = ?';
    $params[] = $pickupFilter;
}
if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR o.id = ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = ctype_digit($search) ? (int) $search : 0;
}

$sql = 'SELECT o.*, c.name AS client_name, c.phone AS client_phone, u.name AS courier_name
        FROM orders o
        JOIN clients c ON c.id = o.client_id
        LEFT JOIN users u ON u.id = o.courier_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY o.created_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$couriers = $pdo->query("SELECT id, name FROM users WHERE role = 'courier' AND active = 1 ORDER BY name")->fetchAll();

$pageTitle = "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{43d}\u{430} \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43a}\u{443}";
$activeNav = 'orders';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>&#x41f;&#x43e;&#x438;&#x441;&#x43a;</label>
      <input type="text" name="q" placeholder="&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;, &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;, &#x2116; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;" value="<?= e($search) ?>" style="min-width:220px;">
    </div>
    <div class="field">
      <label>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</label>
      <select name="status">
        <option value="">&#x412;&#x441;&#x435;</option>
        <?php foreach (['new','accepted','collecting','in_transit','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(crm_order_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440;</label>
      <select name="courier">
        <option value="">&#x412;&#x441;&#x435;</option>
        <?php foreach ($couriers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (string)$courierFilter === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&#x41f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x430;</label>
      <select name="pickup">
        <option value="">&#x412;&#x441;&#x435;</option>
        <option value="self" <?= $pickupFilter === 'self' ? 'selected' : '' ?>>&#x421;&#x430;&#x43c;&#x43e;&#x441;&#x442;&#x43e;&#x44f;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;</option>
        <option value="courier" <?= $pickupFilter === 'courier' ? 'selected' : '' ?>>&#x412;&#x44b;&#x435;&#x437;&#x434;&#x43d;&#x43e;&#x439; &#x430;&#x432;&#x442;&#x43e;&#x441;&#x431;&#x43e;&#x440;</option>
      </select>
    </div>
    <button class="btn secondary" type="submit">&#x41f;&#x440;&#x438;&#x43c;&#x435;&#x43d;&#x438;&#x442;&#x44c;</button>
    <a class="btn secondary" href="/crm/orders.php">&#x421;&#x431;&#x440;&#x43e;&#x441;&#x438;&#x442;&#x44c;</a>
    <a class="btn secondary" href="/crm/orders.php?pickup=courier">&#x412;&#x44b;&#x435;&#x437;&#x434;&#x43d;&#x43e;&#x439; &#x430;&#x432;&#x442;&#x43e;&#x441;&#x431;&#x43e;&#x440;</a>
    <a class="btn" href="/crm/order.php" style="margin-left:auto;">+ &#x41d;&#x43e;&#x432;&#x430;&#x44f; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430;</a>
  </form>
</div>

<div class="card">
  <?php if (!$orders): ?>
    <div class="empty-state">&#x417;&#x430;&#x44f;&#x432;&#x43e;&#x43a; &#x43d;&#x435; &#x43d;&#x430;&#x439;&#x434;&#x435;&#x43d;&#x43e;.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>&#x2116;</th><th>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;</th><th>&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;</th><th>&#x41f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x435;</th><th>&#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x430;</th><th>&#x421;&#x443;&#x43c;&#x43c;&#x430;</th><th>&#x421;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x430;</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><a href="/crm/client.php?id=<?= (int)$o['client_id'] ?>"><?= e($o['client_name']) ?></a><br><span class="text-muted"><?= e($o['client_phone']) ?></span></td>
        <td><?= e($o['from_city']) ?> &#x2192; <?= e($o['to_city']) ?></td>
        <td><span class="badge <?= crm_pickup_type_class($o['pickup_type'] ?? 'self') ?>"><?= e(crm_pickup_type_label($o['pickup_type'] ?? 'self')) ?></span></td>
        <td><?= $o['courier_name'] ? e($o['courier_name']) : "<span class=\"text-muted\">\u{43d}\u{435} \u{43d}\u{430}\u{437}\u{43d}\u{430}\u{447}\u{435}\u{43d}</span>" ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td>
          <span class="badge <?= crm_payment_status_class($o['payment_status']) ?>"><?= e(crm_payment_status_label($o['payment_status'])) ?></span>
          <?php if ($o['payment_status'] === 'paid' || $o['payment_method']): ?>
            <br><span class="text-muted" style="font-size:.78rem;"><?= e(crm_payment_method_label($o['payment_method'])) ?></span>
          <?php endif; ?>
        </td>
        <td><?= crm_money($o['price'] !== null ? (float)$o['price'] : null) ?></td>
        <td><?= crm_date($o['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
