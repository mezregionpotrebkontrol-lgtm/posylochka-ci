<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/clientapi.php';
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
        if ($newStatus === 'delivered') {
            crm_notify_owner('Заявка №' . $id . ' (' . $order['from_city'] . ' → ' . $order['to_city'] . ') отмечена как доставленная.');
        }
        crm_flash_set('Статус изменён на «' . crm_order_status_label($newStatus) . '».');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

// Отметка оплаты прямо из заявки
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    crm_csrf_check();
    $allowedMethods = ['online', 'cash', 'terminal', 'invoice'];
    $method = $_POST['payment_method'] ?? '';
    if (!in_array($method, $allowedMethods, true)) {
        crm_flash_set('Выберите способ оплаты.', 'err');
    } else {
        $pdo->prepare('UPDATE orders SET payment_status = ?, payment_method = ? WHERE id = ?')->execute(['paid', $method, $id]);
        crm_flash_set('Заявка отмечена оплаченной (' . crm_payment_method_label($method) . ').');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unmark_paid') {
    crm_csrf_check();
    $pdo->prepare('UPDATE orders SET payment_status = ?, payment_method = NULL WHERE id = ?')->execute(['unpaid', $id]);
    crm_flash_set('Отметка оплаты снята.');
    crm_redirect('/crm/order.php?id=' . $id);
}

// Постоплата — договорились, что клиент заплатит после получения груза
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_postpaid') {
    crm_csrf_check();
    $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute(['postpaid', $id]);
    crm_flash_set('Заявка отмечена как постоплата (клиент заплатит после доставки).');
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unmark_postpaid') {
    crm_csrf_check();
    $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute(['unpaid', $id]);
    crm_flash_set('Отметка постоплаты снята.');
    crm_redirect('/crm/order.php?id=' . $id);
}

// Рассрочка — график платежей
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_installment') {
    crm_csrf_check();
    $amount = (float) str_replace(',', '.', $_POST['amount'] ?? '0');
    $dueDate = $_POST['due_date'] !== '' ? $_POST['due_date'] : null;
    if ($amount <= 0) {
        crm_flash_set('Укажите сумму платежа больше нуля.', 'err');
    } else {
        $pdo->prepare('INSERT INTO order_installments (order_id, due_date, amount) VALUES (?,?,?)')
            ->execute([$id, $dueDate, $amount]);
        // Отмечаем, что заявка оплачивается в рассрочку (если ещё не отмечена иначе).
        if ($order['payment_method'] !== 'installment' || $order['payment_status'] === 'unpaid') {
            $pdo->prepare('UPDATE orders SET payment_method = ?, payment_status = ? WHERE id = ?')
                ->execute(['installment', 'unpaid', $id]);
        }
        crm_flash_set('Платёж добавлен в график рассрочки.');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_installment_paid') {
    crm_csrf_check();
    $instId = (int) ($_POST['installment_id'] ?? 0);
    $chk = $pdo->prepare('SELECT id FROM order_installments WHERE id = ? AND order_id = ?');
    $chk->execute([$instId, $id]);
    if ($chk->fetch()) {
        $pdo->prepare('UPDATE order_installments SET status = "paid", paid_at = NOW() WHERE id = ?')->execute([$instId]);
        // Если график полностью оплачен — переводим заявку в "Оплачен".
        $totals = crm_order_installments_totals($pdo, $id);
        if ($totals['cnt'] > 0 && $totals['paid_cnt'] === $totals['cnt']) {
            $pdo->prepare('UPDATE orders SET payment_status = "paid", payment_method = "installment" WHERE id = ?')->execute([$id]);
        }
        crm_flash_set('Платёж отмечен оплаченным.');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unmark_installment_paid') {
    crm_csrf_check();
    $instId = (int) ($_POST['installment_id'] ?? 0);
    $chk = $pdo->prepare('SELECT id FROM order_installments WHERE id = ? AND order_id = ?');
    $chk->execute([$instId, $id]);
    if ($chk->fetch()) {
        $pdo->prepare('UPDATE order_installments SET status = "pending", paid_at = NULL WHERE id = ?')->execute([$instId]);
        // Если заявка была отмечена "Оплачен" по рассрочке — возвращаем в "Не оплачен".
        if ($order['payment_status'] === 'paid' && $order['payment_method'] === 'installment') {
            $pdo->prepare('UPDATE orders SET payment_status = "unpaid" WHERE id = ?')->execute([$id]);
        }
        crm_flash_set('Отметка оплаты по платежу снята.');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_installment') {
    crm_csrf_check();
    $instId = (int) ($_POST['installment_id'] ?? 0);
    $pdo->prepare("DELETE FROM order_installments WHERE id = ? AND order_id = ? AND status = 'pending'")->execute([$instId, $id]);
    crm_flash_set('Платёж удалён из графика.');
    crm_redirect('/crm/order.php?id=' . $id);
}

$history = [];
$installments = [];
$installmentTotals = ['total' => 0, 'paid' => 0, 'remaining' => 0, 'cnt' => 0, 'paid_cnt' => 0];
$orderClaims = [];
if ($order) {
    $h = $pdo->prepare('SELECT h.*, u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE order_id = ? ORDER BY changed_at DESC');
    $h->execute([$id]);
    $history = $h->fetchAll();

    $instStmt = $pdo->prepare('SELECT * FROM order_installments WHERE order_id = ? ORDER BY due_date IS NULL, due_date, id');
    $instStmt->execute([$id]);
    $installments = $instStmt->fetchAll();
    $installmentTotals = crm_order_installments_totals($pdo, $id);

    $claimsStmt = $pdo->prepare('SELECT * FROM claims WHERE order_id = ? ORDER BY created_at DESC');
    $claimsStmt->execute([$id]);
    $orderClaims = $claimsStmt->fetchAll();
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
      <span class="badge <?= crm_payment_status_class($order['payment_status']) ?>" style="font-size:.85rem;">
        <?= e(crm_payment_status_label($order['payment_status'])) ?><?php if ($order['payment_status'] === 'paid'): ?> — <?= e(crm_payment_method_label($order['payment_method'])) ?><?php endif; ?>
      </span>
      <?php if ($order['payment_method'] === 'installment' && $installmentTotals['cnt'] > 0): ?>
        <span class="badge badge-blue" style="font-size:.85rem;">Рассрочка: оплачено <?= crm_money($installmentTotals['paid']) ?> из <?= crm_money($installmentTotals['total']) ?></span>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <?php if ($order['payment_status'] === 'paid'): ?>
        <form method="post" class="inline">
          <?= crm_csrf_field() ?>
          <input type="hidden" name="action" value="unmark_paid">
          <button class="btn small secondary" type="submit">Снять отметку оплаты</button>
        </form>
      <?php else: ?>
        <form method="post" class="inline" style="display:flex;gap:6px;align-items:center;">
          <?= crm_csrf_field() ?>
          <input type="hidden" name="action" value="mark_paid">
          <select name="payment_method" required>
            <option value="">— способ оплаты —</option>
            <option value="cash">Наличный расчёт</option>
            <option value="terminal">Оплата через терминал</option>
            <option value="invoice">Оплата по счёту</option>
            <option value="online">Онлайн на сайте</option>
          </select>
          <button class="btn small secondary" type="submit">Отметить оплаченным</button>
        </form>
        <?php if ($order['payment_status'] === 'postpaid'): ?>
          <form method="post" class="inline">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="unmark_postpaid">
            <button class="btn small secondary" type="submit">Снять отметку постоплаты</button>
          </form>
        <?php else: ?>
          <form method="post" class="inline">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="mark_postpaid">
            <button class="btn small secondary" type="submit">Постоплата (после доставки)</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <a class="btn small" href="/crm/invoice.php?order_id=<?= (int)$order['id'] ?>">Выставить счёт</a>
      <a class="btn small secondary" href="/crm/waybill.php?id=<?= (int)$order['id'] ?>" target="_blank">Печать накладной</a>
      <a class="btn small secondary" href="/crm/claim.php?order_id=<?= (int)$order['id'] ?>">+ Претензия</a>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Рассрочка — график платежей</h3>
  <?php if (!$installments): ?>
    <div class="empty-state">Рассрочка не оформлена.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Дата платежа</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($installments as $inst): ?>
        <tr>
          <td><?= $inst['due_date'] ? crm_date($inst['due_date'], 'd.m.Y') : '—' ?></td>
          <td><?= crm_money((float) $inst['amount']) ?></td>
          <td>
            <?php if ($inst['status'] === 'paid'): ?>
              <span class="badge badge-green">Оплачен <?= $inst['paid_at'] ? crm_date($inst['paid_at'], 'd.m.Y') : '' ?></span>
            <?php else: ?>
              <span class="badge badge-grey">Ожидает</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php if ($inst['status'] === 'paid'): ?>
              <form method="post" class="inline">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="unmark_installment_paid">
                <input type="hidden" name="installment_id" value="<?= (int) $inst['id'] ?>">
                <button class="btn small secondary" type="submit">Снять оплату</button>
              </form>
            <?php else: ?>
              <form method="post" class="inline">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="mark_installment_paid">
                <input type="hidden" name="installment_id" value="<?= (int) $inst['id'] ?>">
                <button class="btn small secondary" type="submit">Отметить оплаченным</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Удалить платёж из графика?');">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="delete_installment">
                <input type="hidden" name="installment_id" value="<?= (int) $inst['id'] ?>">
                <button class="btn small danger" type="submit">Удалить</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="text-muted" style="margin-top:10px;">
      Оплачено <?= crm_money($installmentTotals['paid']) ?> из <?= crm_money($installmentTotals['total']) ?>
      (осталось <?= crm_money($installmentTotals['remaining']) ?>). Когда оплачены все платежи графика —
      заявка автоматически отмечается «Оплачен».
    </p>
  <?php endif; ?>

  <form method="post" class="form-row" style="align-items:end;margin-top:12px;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="add_installment">
    <div><label>Дата платежа</label><input type="date" name="due_date"></div>
    <div><label>Сумма, ₽</label><input type="number" step="0.01" name="amount" required></div>
    <div class="form-actions"><button class="btn secondary" type="submit">Добавить платёж в график</button></div>
  </form>
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

<div class="card">
  <h3 style="margin-top:0;">
    Претензии по заявке
    <a class="btn small" style="float:right;" href="/crm/claim.php?order_id=<?= (int) $order['id'] ?>">+ Новая претензия</a>
  </h3>
  <?php if (!$orderClaims): ?>
    <div class="empty-state">Претензий по этой заявке нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Причина</th><th>Статус</th><th>Компенсация</th><th>Срок ответа</th><th>Создана</th></tr></thead>
    <tbody>
      <?php foreach ($orderClaims as $cl): ?>
      <tr>
        <td><a href="/crm/claim.php?id=<?= (int) $cl['id'] ?>">№<?= (int) $cl['id'] ?></a></td>
        <td><?= e(crm_claim_reason_label($cl['reason'])) ?></td>
        <td><span class="badge <?= crm_claim_status_class($cl['status']) ?>"><?= e(crm_claim_status_label($cl['status'])) ?></span></td>
        <td><?= crm_money($cl['compensation_amount'] !== null ? (float) $cl['compensation_amount'] : null) ?></td>
        <td><?= $cl['response_due_date'] ? crm_date($cl['response_due_date'], 'd.m.Y') : '—' ?></td>
        <td><?= crm_date($cl['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
