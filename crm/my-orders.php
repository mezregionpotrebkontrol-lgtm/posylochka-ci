<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/clientapi.php';
$user = crm_require_role(['courier', 'admin']);
$pdo = crm_db();

// &#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x443; &#x43c;&#x43e;&#x436;&#x43d;&#x43e; &#x43c;&#x435;&#x43d;&#x44f;&#x442;&#x44c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x43d;&#x430; &#x44d;&#x442;&#x438; &#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x44f;
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
            ->execute([$orderId, $newStatus, $user['id'], "\u{418}\u{437}\u{43c}\u{435}\u{43d}\u{435}\u{43d}\u{43e} \u{43a}\u{443}\u{440}\u{44c}\u{435}\u{440}\u{43e}\u{43c}"]);
        crm_notify_client_status($pdo, $ord, $newStatus);
        if ($newStatus === 'delivered') {
            crm_notify_owner("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $orderId . ' (' . $ord['from_city'] . " \u{2192} " . $ord['to_city'] . ") \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{430} \u{43a}\u{443}\u{440}\u{44c}\u{435}\u{440}\u{43e}\u{43c} \u{ab}" . $user['name'] . "\u{bb}.");
        }
        crm_flash_set("\u{421}\u{442}\u{430}\u{442}\u{443}\u{441} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{2116}" . $orderId . " \u{43e}\u{431}\u{43d}\u{43e}\u{432}\u{43b}\u{451}\u{43d}.");
    } else {
        crm_flash_set("\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{438}\u{437}\u{43c}\u{435}\u{43d}\u{438}\u{442}\u{44c} \u{441}\u{442}\u{430}\u{442}\u{443}\u{441}.", 'err');
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

$pageTitle = "\u{41c}\u{43e}\u{438} \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43a}\u{438}";
$activeNav = 'my-orders';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">&#x422;&#x435;&#x43a;&#x443;&#x449;&#x438;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (<?= count($activeOrders) ?>)</h3>
  <?php if (!$activeOrders): ?>
    <div class="empty-state">&#x41f;&#x43e;&#x43a;&#x430; &#x43d;&#x435;&#x442; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x43d;&#x44b;&#x445; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>&#x2116;</th><th>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;</th><th>&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;</th><th>&#x410;&#x434;&#x440;&#x435;&#x441; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f;</th><th>&#x414;&#x430;&#x442;&#x430;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x414;&#x435;&#x439;&#x441;&#x442;&#x432;&#x438;&#x435;</th></tr></thead>
      <tbody>
        <?php foreach ($activeOrders as $o): ?>
        <tr>
          <td>#<?= (int)$o['id'] ?></td>
          <td><?= e($o['client_name']) ?><br><span class="text-muted"><?= e($o['client_phone']) ?></span></td>
          <td><?= e($o['from_city']) ?> &#x2192; <?= e($o['to_city']) ?></td>
          <td><?= e($o['to_address']) ?></td>
          <td><?= $o['planned_date'] ? crm_date($o['planned_date'], 'd.m.Y') : "\u{2014}" ?></td>
          <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
          <td>
            <form method="post" class="inline">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <?php if ($o['status'] === 'new'): ?>
                <input type="hidden" name="status" value="accepted">
                <button class="btn small" type="submit">&#x41f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x44c;</button>
              <?php elseif ($o['status'] === 'accepted'): ?>
                <input type="hidden" name="status" value="collecting">
                <button class="btn small" type="submit">&#x41d;&#x430;&#x447;&#x430;&#x442;&#x44c; &#x441;&#x431;&#x43e;&#x440; &#x433;&#x440;&#x443;&#x437;&#x430;</button>
              <?php elseif ($o['status'] === 'collecting'): ?>
                <input type="hidden" name="status" value="in_transit">
                <button class="btn small" type="submit">&#x412; &#x43f;&#x443;&#x442;&#x438;</button>
              <?php elseif ($o['status'] === 'in_transit'): ?>
                <input type="hidden" name="status" value="delivered">
                <button class="btn small" type="submit">&#x414;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x43e;</button>
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
  <h3 style="margin-top:0;">&#x417;&#x430;&#x432;&#x435;&#x440;&#x448;&#x451;&#x43d;&#x43d;&#x44b;&#x435; (&#x43f;&#x43e;&#x441;&#x43b;&#x435;&#x434;&#x43d;&#x438;&#x435; 30)</h3>
  <?php if (!$doneOrders): ?>
    <div class="empty-state">&#x41f;&#x43e;&#x43a;&#x430; &#x43d;&#x435;&#x442; &#x437;&#x430;&#x432;&#x435;&#x440;&#x448;&#x451;&#x43d;&#x43d;&#x44b;&#x445; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>&#x2116;</th><th>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th></tr></thead>
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
