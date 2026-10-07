<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$invoice = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        http_response_code(404);
        die('Счёт не найден.');
    }
}

$clients = $pdo->query('SELECT id, name, phone FROM clients ORDER BY name')->fetchAll();

$prefillOrder = null;
$orderIdParam = (int) ($_GET['order_id'] ?? 0);
if (!$invoice && $orderIdParam) {
    $os = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $os->execute([$orderIdParam]);
    $prefillOrder = $os->fetch();
}

if (!$invoice && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $orderId = $_POST['order_id'] !== '' ? (int) $_POST['order_id'] : null;
    $amount = (float) ($_POST['amount'] ?? 0);

    if (!$clientId || $amount <= 0) {
        crm_flash_set('Выберите клиента и укажите сумму больше нуля.', 'err');
    } else {
        $number = crm_next_invoice_number($pdo);
        $stmt = $pdo->prepare('INSERT INTO invoices (number, order_id, client_id, amount, status, created_by) VALUES (?,?,?,?,\'sent\',?)');
        $stmt->execute([$number, $orderId, $clientId, $amount, $user['id']]);
        $newId = (int) $pdo->lastInsertId();
        crm_flash_set('Счёт ' . $number . ' создан.');
        crm_redirect('/crm/invoice.php?id=' . $newId);
    }
}

if ($invoice && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    crm_csrf_check();
    $amount = (float) ($_POST['payment_amount'] ?? $invoice['amount']);
    $method = trim($_POST['method'] ?? 'перевод');

    $pdo->prepare('INSERT INTO payments (invoice_id, amount, method, recorded_by) VALUES (?,?,?,?)')
        ->execute([$id, $amount, $method ?: 'перевод', $user['id']]);
    $pdo->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$id]);
    if ($invoice['order_id']) {
        $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$invoice['order_id']]);
    }
    crm_flash_set('Оплата зафиксирована.');
    crm_redirect('/crm/invoice.php?id=' . $id);
}

if ($invoice && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    crm_csrf_check();
    $pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    crm_flash_set('Счёт отменён.');
    crm_redirect('/crm/invoice.php?id=' . $id);
}

$payments = [];
if ($invoice) {
    $p = $pdo->prepare('SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at DESC');
    $p->execute([$id]);
    $payments = $p->fetchAll();
}

$pageTitle = $invoice ? ('Счёт ' . $invoice['number']) : 'Новый счёт';
$activeNav = 'finance';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/finance.php">← Все счета</a></p>

<?php if (!$invoice): ?>

<div class="card">
  <h3 style="margin-top:0;">Новый счёт на оплату</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <?php if ($prefillOrder): ?>
      <input type="hidden" name="order_id" value="<?= (int)$prefillOrder['id'] ?>">
      <p class="text-muted">Счёт привязан к заявке №<?= (int)$prefillOrder['id'] ?> (<?= e($prefillOrder['from_city']) ?> → <?= e($prefillOrder['to_city']) ?>)</p>
    <?php else: ?>
      <input type="hidden" name="order_id" value="">
    <?php endif; ?>
    <label>Клиент</label>
    <select name="client_id" required>
      <option value="">— выберите клиента —</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $prefillOrder && (int)$prefillOrder['client_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Сумма, ₽</label>
    <input type="number" step="0.01" name="amount" required value="<?= $prefillOrder && $prefillOrder['price'] !== null ? e($prefillOrder['price']) : '' ?>">
    <div class="form-actions"><button class="btn" type="submit">Выставить счёт</button></div>
  </form>
</div>

<?php else: ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <?php $cls = ['draft'=>'badge-grey','sent'=>'badge-blue','paid'=>'badge-green','cancelled'=>'badge-red'][$invoice['status']] ?? 'badge-grey'; ?>
      <span class="badge <?= $cls ?>" style="font-size:.85rem;"><?= e(crm_invoice_status_label($invoice['status'])) ?></span>
    </div>
    <div style="display:flex;gap:8px;">
      <a class="btn small secondary" href="/crm/invoice-print.php?id=<?= (int)$invoice['id'] ?>" target="_blank">Печать / PDF</a>
      <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'cancelled'): ?>
        <form method="post" class="inline" onsubmit="return confirm('Отменить счёт?');">
          <?= crm_csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <button class="btn small secondary" type="submit">Отменить</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <table>
    <tr><th style="width:200px;">Номер</th><td><?= e($invoice['number']) ?></td></tr>
    <tr><th>Клиент</th><td><a href="/crm/client.php?id=<?= (int)$invoice['client_id'] ?>"><?php
        $cl = $pdo->prepare('SELECT name FROM clients WHERE id=?'); $cl->execute([$invoice['client_id']]); echo e($cl->fetchColumn());
    ?></a></td></tr>
    <?php if ($invoice['order_id']): ?>
    <tr><th>Заявка</th><td><a href="/crm/order.php?id=<?= (int)$invoice['order_id'] ?>">#<?= (int)$invoice['order_id'] ?></a></td></tr>
    <?php endif; ?>
    <tr><th>Сумма</th><td><strong><?= crm_money((float)$invoice['amount']) ?></strong></td></tr>
    <tr><th>Создан</th><td><?= crm_date($invoice['created_at']) ?></td></tr>
    <?php if ($invoice['paid_at']): ?>
    <tr><th>Оплачен</th><td><?= crm_date($invoice['paid_at']) ?></td></tr>
    <?php endif; ?>
  </table>
</div>

<?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'cancelled'): ?>
<div class="card">
  <h3 style="margin-top:0;">Зафиксировать оплату</h3>
  <form method="post" class="form-row" style="align-items:end;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="mark_paid">
    <div><label>Сумма, ₽</label><input type="number" step="0.01" name="payment_amount" value="<?= e($invoice['amount']) ?>"></div>
    <div><label>Способ оплаты</label><input type="text" name="method" value="перевод"></div>
    <div class="form-actions"><button class="btn" type="submit">Отметить оплаченным</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($payments): ?>
<div class="card">
  <h3 style="margin-top:0;">Платежи</h3>
  <table>
    <thead><tr><th>Дата</th><th>Сумма</th><th>Способ</th></tr></thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr><td><?= crm_date($p['paid_at']) ?></td><td><?= crm_money((float)$p['amount']) ?></td><td><?= e($p['method']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
