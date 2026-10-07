<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$order = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        http_response_code(404);
        die('Заявка не найдена.');
    }
}

$clients = $pdo->query('SELECT id, name, phone FROM clients ORDER BY name')->fetchAll();
$couriers = $pdo->query("SELECT id, name FROM users WHERE role = 'courier' AND active = 1 ORDER BY name")->fetchAll();

// Создание новой заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $clientId = (int) ($_POST['client_id'] ?? 0);
    if (!$clientId) {
        crm_flash_set('Выберите клиента.', 'err');
    } else {
        $stmt = $pdo->prepare('INSERT INTO orders
            (client_id, created_by, from_city, to_city, from_address, to_address, cargo_description, weight_kg, declared_value, price, planned_date, comment, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'new\')');
        $stmt->execute([
            $clientId,
            $user['id'],
            trim($_POST['from_city'] ?? '') ?: 'Дербент',
            trim($_POST['to_city'] ?? '') ?: 'Санкт-Петербург',
            trim($_POST['from_address'] ?? '') ?: null,
            trim($_POST['to_address'] ?? '') ?: null,
            trim($_POST['cargo_description'] ?? '') ?: null,
            $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null,
            $_POST['declared_value'] !== '' ? (float) $_POST['declared_value'] : null,
            $_POST['price'] !== '' ? (float) $_POST['price'] : null,
            $_POST['planned_date'] !== '' ? $_POST['planned_date'] : null,
            trim($_POST['comment'] ?? '') ?: null,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, \'new\', ?, \'Заявка создана\')')
            ->execute([$newId, $user['id']]);
        crm_flash_set('Заявка №' . $newId . ' создана.');
        crm_redirect('/crm/order.php?id=' . $newId);
    }
}

// Обновление существующей заявки (данные)
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    crm_csrf_check();
    $stmt = $pdo->prepare('UPDATE orders SET from_city=?, to_city=?, from_address=?, to_address=?, cargo_description=?, weight_kg=?, declared_value=?, price=?, planned_date=?, comment=? WHERE id=?');
    $stmt->execute([
        trim($_POST['from_city'] ?? '') ?: 'Дербент',
        trim($_POST['to_city'] ?? '') ?: 'Санкт-Петербург',
        trim($_POST['from_address'] ?? '') ?: null,
        trim($_POST['to_address'] ?? '') ?: null,
        trim($_POST['cargo_description'] ?? '') ?: null,
        $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null,
        $_POST['declared_value'] !== '' ? (float) $_POST['declared_value'] : null,
        $_POST['price'] !== '' ? (float) $_POST['price'] : null,
        $_POST['planned_date'] !== '' ? $_POST['planned_date'] : null,
        trim($_POST['comment'] ?? '') ?: null,
        $id,
    ]);
    crm_flash_set('Заявка обновлена.');
    crm_redirect('/crm/order.php?id=' . $id);
}

// Назначение курьера
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_courier') {
    crm_csrf_check();
    $courierId = $_POST['courier_id'] !== '' ? (int) $_POST['courier_id'] : null;
    $pdo->prepare('UPDATE orders SET courier_id = ? WHERE id = ?')->execute([$courierId, $id]);
    crm_flash_set('Курьер обновлён.');
    crm_redirect('/crm/order.php?id=' . $id);
}

// Смена статуса
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    crm_csrf_check();
    $newStatus = $_POST['status'] ?? '';
    $allowed = ['new','accepted','in_transit','delivered','cancelled'];
    if (in_array($newStatus, $allowed, true)) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?,?,?,?)')
            ->execute([$id, $newStatus, $user['id'], trim($_POST['status_comment'] ?? '') ?: null]);
        crm_notify_client_status($pdo, $order, $newStatus);
        crm_flash_set('Статус изменён на «' . crm_order_status_label($newStatus) . '».');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

// Отметка оплаты прямо из заявки
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_payment') {
    crm_csrf_check();
    $newVal = $order['payment_status'] === 'paid' ? 'unpaid' : 'paid';
    $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute([$newVal, $id]);
    crm_redirect('/crm/order.php?id=' . $id);
}

$history = [];
if ($order) {
    $h = $pdo->prepare('SELECT h.*, u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE order_id = ? ORDER BY changed_at DESC');
    $h->execute([$id]);
    $history = $h->fetchAll();
}

$preselectClientId = (int) ($_GET['client_id'] ?? 0);

$pageTitle = $order ? ('Заявка №' . $order['id']) : 'Новая заявка';
$activeNav = 'orders';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/orders.php">← Все заявки</a></p>

<?php if (!$order): ?>

<div class="card">
  <h3 style="margin-top:0;">Новая заявка на доставку</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label>Клиент</label>
    <select name="client_id" required>
      <option value="">— выберите клиента —</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $preselectClientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> <?= $c['phone'] ? '(' . e($c['phone']) . ')' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <p class="text-muted" style="margin-top:-10px;">Нет нужного клиента? <a href="/crm/clients.php">Добавьте его здесь</a>, затем вернитесь.</p>

    <div class="form-row">
      <div><label>Город отправления</label><input type="text" name="from_city" value="Дербент"></div>
      <div><label>Город назначения</label><input type="text" name="to_city" value="Санкт-Петербург"></div>
    </div>
    <div class="form-row">
      <div><label>Адрес отправления</label><input type="text" name="from_address"></div>
      <div><label>Адрес получения</label><input type="text" name="to_address"></div>
    </div>
    <label>Описание груза</label>
    <input type="text" name="cargo_description" placeholder="например: коробка, 2 места">
    <div class="form-row">
      <div><label>Вес, кг</label><input type="number" step="0.1" name="weight_kg"></div>
      <div><label>Объявленная ценность, ₽</label><input type="number" step="0.01" name="declared_value"></div>
    </div>
    <div class="form-row">
      <div><label>Стоимость доставки, ₽</label><input type="number" step="0.01" name="price"></div>
      <div><label>Планируемая дата доставки</label><input type="date" name="planned_date"></div>
    </div>
    <label>Комментарий</label>
    <textarea name="comment"></textarea>
    <div class="form-actions"><button class="btn" type="submit">Создать заявку</button></div>
  </form>
</div>

<?php else: ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <span class="badge <?= crm_order_status_class($order['status']) ?>" style="font-size:.85rem;"><?= e(crm_order_status_label($order['status'])) ?></span>
      <?php if ($order['payment_status'] === 'paid'): ?>
        <span class="badge badge-green" style="font-size:.85rem;">Оплачен</span>
      <?php else: ?>
        <span class="badge badge-grey" style="font-size:.85rem;">Не оплачен</span>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;">
      <form method="post" class="inline">
        <?= crm_csrf_field() ?>
        <input type="hidden" name="action" value="toggle_payment">
        <button class="btn small secondary" type="submit"><?= $order['payment_status'] === 'paid' ? 'Снять отметку оплаты' : 'Отметить оплаченным' ?></button>
      </form>
      <a class="btn small" href="/crm/invoice.php?order_id=<?= (int)$order['id'] ?>">Выставить счёт</a>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Клиент: <a href="/crm/client.php?id=<?= (int)$order['client_id'] ?>"><?php
    $cl = $pdo->prepare('SELECT name FROM clients WHERE id = ?'); $cl->execute([$order['client_id']]);
    echo e($cl->fetchColumn());
  ?></a></h3>

  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="form-row">
      <div><label>Город отправления</label><input type="text" name="from_city" value="<?= e($order['from_city']) ?>"></div>
      <div><label>Город назначения</label><input type="text" name="to_city" value="<?= e($order['to_city']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Адрес отправления</label><input type="text" name="from_address" value="<?= e($order['from_address']) ?>"></div>
      <div><label>Адрес получения</label><input type="text" name="to_address" value="<?= e($order['to_address']) ?>"></div>
    </div>
    <label>Описание груза</label>
    <input type="text" name="cargo_description" value="<?= e($order['cargo_description']) ?>">
    <div class="form-row">
      <div><label>Вес, кг</label><input type="number" step="0.1" name="weight_kg" value="<?= e($order['weight_kg']) ?>"></div>
      <div><label>Объявленная ценность, ₽</label><input type="number" step="0.01" name="declared_value" value="<?= e($order['declared_value']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Стоимость доставки, ₽</label><input type="number" step="0.01" name="price" value="<?= e($order['price']) ?>"></div>
      <div><label>Планируемая дата доставки</label><input type="date" name="planned_date" value="<?= e($order['planned_date']) ?>"></div>
    </div>
    <label>Комментарий</label>
    <textarea name="comment"><?= e($order['comment']) ?></textarea>
    <div class="form-actions"><button class="btn" type="submit">Сохранить изменения</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Курьер и маршрут</h3>
  <form method="post" class="form-row" style="align-items:end;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="assign_courier">
    <div>
      <label>Назначенный курьер</label>
      <select name="courier_id">
        <option value="">— не назначен —</option>
        <?php foreach ($couriers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$order['courier_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions"><button class="btn secondary" type="submit">Назначить</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Изменить статус</h3>
  <form method="post" class="form-row" style="align-items:end;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="change_status">
    <div>
      <label>Новый статус</label>
      <select name="status">
        <?php foreach (['new','accepted','in_transit','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(crm_order_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Комментарий (необязательно)</label>
      <input type="text" name="status_comment">
    </div>
    <div class="form-actions"><button class="btn secondary" type="submit">Обновить статус</button></div>
  </form>

  <?php if ($history): ?>
  <table style="margin-top:10px;">
    <thead><tr><th>Дата</th><th>Статус</th><th>Кто изменил</th><th>Комментарий</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= crm_date($h['changed_at']) ?></td>
        <td><span class="badge <?= crm_order_status_class($h['status']) ?>"><?= e(crm_order_status_label($h['status'])) ?></span></td>
        <td><?= e($h['user_name'] ?? '—') ?></td>
        <td><?= e($h['comment']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
