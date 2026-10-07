<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    http_response_code(404);
    die('Клиент не найден.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    crm_csrf_check();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        crm_flash_set('Укажите имя или название клиента.', 'err');
    } else {
        $stmt = $pdo->prepare('UPDATE clients SET name=?, phone=?, email=?, address=?, notes=? WHERE id=?');
        $stmt->execute([$name, $phone ?: null, $email ?: null, $address ?: null, $notes ?: null, $id]);
        crm_flash_set('Данные клиента обновлены.');
        crm_redirect('/crm/client.php?id=' . $id);
    }
}

$ordersStmt = $pdo->prepare('SELECT * FROM orders WHERE client_id = ? ORDER BY created_at DESC');
$ordersStmt->execute([$id]);
$orders = $ordersStmt->fetchAll();

$pageTitle = $client['name'];
$activeNav = 'clients';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/clients.php">← Все клиенты</a></p>

<div class="card">
  <h3 style="margin-top:0;">Данные клиента</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="form-row">
      <div><label>Имя / название</label><input type="text" name="name" required value="<?= e($client['name']) ?>"></div>
      <div><label>Телефон</label><input type="tel" name="phone" value="<?= e($client['phone']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Email</label><input type="email" name="email" value="<?= e($client['email']) ?>"></div>
      <div><label>Адрес</label><input type="text" name="address" value="<?= e($client['address']) ?>"></div>
    </div>
    <label>Заметки</label>
    <textarea name="notes"><?= e($client['notes']) ?></textarea>
    <div class="form-actions"><button class="btn" type="submit">Сохранить</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">
    История заказов
    <a class="btn small" style="float:right;" href="/crm/order.php?client_id=<?= (int)$client['id'] ?>">+ Новая заявка</a>
  </h3>
  <?php if (!$orders): ?>
    <div class="empty-state">Заказов пока нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Маршрут</th><th>Статус</th><th>Оплата</th><th>Сумма</th><th>Создана</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><?= e($o['from_city']) ?> → <?= e($o['to_city']) ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td><?= $o['payment_status'] === 'paid' ? '<span class="badge badge-green">Оплачен</span>' : '<span class="badge badge-grey">Не оплачен</span>' ?></td>
        <td><?= crm_money($o['price'] !== null ? (float)$o['price'] : null) ?></td>
        <td><?= crm_date($o['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
