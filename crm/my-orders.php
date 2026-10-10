<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/clientapi.php';
$user = crm_require_role(['courier', 'admin']);
$pdo = crm_db();

// Курьеру можно менять статус только на эти значения
$courierAllowedStatuses = ['accepted', 'collecting', 'in_transit', 'delivered'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    crm_csrf_check();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND courier_id = ?');
    $stmt->execute([$orderId, $user['id']]);
    $ord = $stmt->fetch();

    if ($ord && in_array($newStatus, $courierAllowedStatuses, true)) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?,?,?,?)')
            ->execute([$orderId, $newStatus, $user['id'], 'Изменено курьером']);
        crm_notify_client_status($pdo, $ord, $newStatus);
        if ($newStatus === 'delivered') {
            crm_notify_owner('Заявка №' . $orderId . ' (' . $ord['from_city'] . ' → ' . $ord['to_city'] . ') доставлена курьером «' . $user['name'] . '».');
        }
        crm_flash_set('Статус заявки №' . $orderId . ' обновлён.');
    } else {
        crm_flash_set('Не удалось изменить статус.', 'err');
    }
    crm_redirect('/crm/my-orders.php');
}

$stmt = $pdo->prepare("SELECT o.*, c.name AS client_name, c.phone AS client_phone
    FROM orders o JOIN clients c ON c.id = o.client_id
    WHERE o.courier_id = ? AND o.status NOT IN ('delivered','cancelled')
    ORDER BY o.planned_date IS NULL, o.planned_date, o.created_at");
$stmt->execute([$user['id']]);
$activeOrders = $stmt->fetchAll();

$doneStmt = $pdo->prepare("SELECT o.*, c.name AS client_name
    FROM orders o JOIN clients c ON c.id = o.client_id
    WHERE o.courier_id = ? AND o.status IN ('delivered','cancelled')
    ORDER BY o.updated_at DESC LIMIT 30");
$doneStmt->execute([$user['id']]);
$doneOrders = $doneStmt->fetchAll();

$pageTitle = 'Мои доставки';
$activeNav = 'my-orders';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Текущие заявки (<?= count($activeOrders) ?>)</h3>
  <?php if (!$activeOrders): ?>
    <div class="empty-state">Пока нет назначенных заявок.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>№</th><th>Клиент</th><th>Маршрут</th><th>Адрес получения</th><th>Дата</th><th>Статус</th><th>Действие</th></tr></thead>
      <tbody>
        <?php foreach ($activeOrders as $o): ?>
        <tr>
          <td>#<?= (int)$o['id'] ?></td>
          <td><?= e($o['client_name']) ?><br><span class="text-muted"><?= e($o['client_phone']) ?></span></td>
          <td><?= e($o['from_city']) ?> → <?= e($o['to_city']) ?></td>
          <td><?= e($o['to_address']) ?></td>
          <td><?= $o['planned_date'] ? crm_date($o['planned_date'], 'd.m.Y') : '—' ?></td>
          <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
          <td>
            <form method="post" class="inline">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <?php if ($o['status'] === 'new'): ?>
                <input type="hidden" name="status" value="accepted">
                <button class="btn small" type="submit">Принять</button>
              <?php elseif ($o['status'] === 'accepted'): ?>
                <input type="hidden" name="status" value="collecting">
                <button class="btn small" type="submit">Начать сбор груза</button>
              <?php elseif ($o['status'] === 'collecting'): ?>
                <input type="hidden" name="status" value="in_transit">
                <button class="btn small" type="submit">В пути</button>
              <?php elseif ($o['status'] === 'in_transit'): ?>
                <input type="hidden" name="status" value="delivered">
                <button class="btn small" type="submit">Доставлено</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Завершённые (последние 30)</h3>
  <?php if (!$doneOrders): ?>
    <div class="empty-state">Пока нет завершённых заявок.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>№</th><th>Клиент</th><th>Статус</th></tr></thead>
      <tbody>
        <?php foreach ($doneOrders as $o): ?>
        <tr>
          <td>#<?= (int)$o['id'] ?></td>
          <td><?= e($o['client_name']) ?></td>
          <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
