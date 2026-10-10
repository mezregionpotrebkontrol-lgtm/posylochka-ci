<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$statusFilter = $_GET['status'] ?? '';
$courierFilter = $_GET['courier'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['new','accepted','in_transit','delivered','cancelled'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($courierFilter !== '') {
    $where[] = 'o.courier_id = ?';
    $params[] = (int) $courierFilter;
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

$pageTitle = 'Заявки на доставку';
$activeNav = 'orders';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>Поиск</label>
      <input type="text" name="q" placeholder="Клиент, телефон, № заявки" value="<?= e($search) ?>" style="min-width:220px;">
    </div>
    <div class="field">
      <label>Статус</label>
      <select name="status">
        <option value="">Все</option>
        <?php foreach (['new','accepted','in_transit','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(crm_order_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Курьер</label>
      <select name="courier">
        <option value="">Все</option>
        <?php foreach ($couriers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (string)$courierFilter === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Применить</button>
    <a class="btn secondary" href="/crm/orders.php">Сбросить</a>
    <a class="btn" href="/crm/order.php" style="margin-left:auto;">+ Новая заявка</a>
  </form>
</div>

<div class="card">
  <?php if (!$orders): ?>
    <div class="empty-state">Заявок не найдено.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>№</th><th>Клиент</th><th>Маршрут</th><th>Курьер</th><th>Статус</th><th>Оплата</th><th>Сумма</th><th>Создана</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><a href="/crm/client.php?id=<?= (int)$o['client_id'] ?>"><?= e($o['client_name']) ?></a><br><span class="text-muted"><?= e($o['client_phone']) ?></span></td>
        <td><?= e($o['from_city']) ?> → <?= e($o['to_city']) ?></td>
        <td><?= $o['courier_name'] ? e($o['courier_name']) : '<span class="text-muted">не назначен</span>' ?></td>
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
