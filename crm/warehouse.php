<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

// Добавить пункт (любой город России — список не ограничен).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'point_create') {
    crm_csrf_check();
    if ($user['role'] !== 'admin') {
        crm_flash_set('Добавлять пункты может только администратор.', 'err');
        crm_redirect('/crm/warehouse.php');
    }
    $name = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '') ?: null;
    if ($name === '' || $city === '') {
        crm_flash_set('Укажите название и город пункта.', 'err');
    } else {
        $stmt = $pdo->prepare('INSERT INTO warehouse_points (name, city, address) VALUES (?,?,?)');
        $stmt->execute([$name, $city, $address]);
        crm_flash_set('Пункт «' . $name . '» добавлен.');
    }
    crm_redirect('/crm/warehouse.php?point_id=' . (int) $pdo->lastInsertId());
}

// Изменить/отключить пункт.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'point_update') {
    crm_csrf_check();
    if ($user['role'] !== 'admin') {
        crm_flash_set('Изменять пункты может только администратор.', 'err');
        crm_redirect('/crm/warehouse.php');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '') ?: null;
    if ($id && $name !== '' && $city !== '') {
        $stmt = $pdo->prepare('UPDATE warehouse_points SET name=?, city=?, address=?, active=? WHERE id=?');
        $stmt->execute([$name, $city, $address, isset($_POST['active']) ? 1 : 0, $id]);
        crm_flash_set('Пункт обновлён.');
    } else {
        crm_flash_set('Укажите название и город пункта.', 'err');
    }
    crm_redirect('/crm/warehouse.php?point_id=' . $id);
}

$points = $pdo->query('SELECT * FROM warehouse_points WHERE active = 1 ORDER BY city')->fetchAll();
$allPoints = $user['role'] === 'admin'
    ? $pdo->query('SELECT * FROM warehouse_points ORDER BY active DESC, city')->fetchAll()
    : [];
$pointId = (int) ($_GET['point_id'] ?? ($points[0]['id'] ?? 0));

// Приём груза в пункте
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'receive') {
    crm_csrf_check();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $ptId = (int) ($_POST['point_id'] ?? 0);
    if ($orderId && $ptId) {
        $stmt = $pdo->prepare('INSERT INTO warehouse_events (order_id, point_id, event_type, weight_kg, note, user_id)
            VALUES (?,?,\'received\',?,?,?)');
        $stmt->execute([
            $orderId, $ptId,
            $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null,
            trim($_POST['note'] ?? '') ?: null,
            $user['id'],
        ]);
        crm_flash_set('Груз по заявке №' . $orderId . ' принят в пункте.');
    } else {
        crm_flash_set('Выберите заявку.', 'err');
    }
    crm_redirect('/crm/warehouse.php?point_id=' . $ptId);
}

// Выдача получателю
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'issue') {
    crm_csrf_check();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $ptId = (int) ($_POST['point_id'] ?? 0);
    $recipient = trim($_POST['recipient_name'] ?? '');
    if ($orderId && $ptId && $recipient !== '') {
        $stmt = $pdo->prepare('INSERT INTO warehouse_events (order_id, point_id, event_type, note, recipient_name, user_id)
            VALUES (?,?,\'issued\',?,?,?)');
        $stmt->execute([
            $orderId, $ptId,
            trim($_POST['note'] ?? '') ?: null,
            $recipient,
            $user['id'],
        ]);
        crm_flash_set('Заявка №' . $orderId . ' выдана получателю «' . $recipient . '».');
    } else {
        crm_flash_set('Укажите заявку и имя получателя.', 'err');
    }
    crm_redirect('/crm/warehouse.php?point_id=' . $ptId);
}

// Заявки, сейчас находящиеся в этом пункте (последнее событие = "принято" здесь)
$onHand = [];
$notYetReceived = [];
if ($pointId) {
    $stmt = $pdo->prepare("SELECT we.*, o.from_city, o.to_city, o.status AS order_status, cl.name AS client_name, cl.phone AS client_phone
        FROM warehouse_events we
        INNER JOIN (SELECT order_id, MAX(id) AS max_id FROM warehouse_events GROUP BY order_id) latest
            ON latest.max_id = we.id
        JOIN orders o ON o.id = we.order_id
        JOIN clients cl ON cl.id = o.client_id
        WHERE we.point_id = ? AND we.event_type = 'received'
        ORDER BY we.created_at DESC");
    $stmt->execute([$pointId]);
    $onHand = $stmt->fetchAll();

    // Заявки, которые ещё не отмечались как принятые в этом пункте (для формы приёма)
    $onHandIds = array_map(fn($r) => (int) $r['order_id'], $onHand);
    $placeholders = $onHandIds ? implode(',', array_fill(0, count($onHandIds), '?')) : '0';
    $sql = "SELECT o.id, o.from_city, o.to_city, cl.name AS client_name
        FROM orders o JOIN clients cl ON cl.id = o.client_id
        WHERE o.status NOT IN ('delivered','cancelled')" . ($onHandIds ? " AND o.id NOT IN ($placeholders)" : '') . "
        ORDER BY o.created_at DESC LIMIT 50";
    $stmt2 = $pdo->prepare($sql);
    $stmt2->execute($onHandIds);
    $notYetReceived = $stmt2->fetchAll();
}

// Последние события (общая история по пункту)
$recent = [];
if ($pointId) {
    $stmt = $pdo->prepare("SELECT we.*, u.name AS user_name FROM warehouse_events we
        LEFT JOIN users u ON u.id = we.user_id
        WHERE we.point_id = ? ORDER BY we.created_at DESC LIMIT 30");
    $stmt->execute([$pointId]);
    $recent = $stmt->fetchAll();
}

$pageTitle = 'Склад';
$activeNav = 'warehouse';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Пункт</h3>
  <div class="inline-row" style="gap:10px;">
    <?php foreach ($points as $p): ?>
      <a class="btn <?= $p['id'] == $pointId ? '' : 'secondary' ?> small" href="/crm/warehouse.php?point_id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['city']) ?>)</a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($user['role'] === 'admin'): ?>
<div class="card">
  <h3 style="margin-top:0;">Пункты приёма/выдачи — любой город России</h3>
  <?php if ($allPoints): ?>
    <table>
      <thead><tr><th>Пункт</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($allPoints as $p): ?>
        <tr>
          <td>
            <form method="post" class="inline-row">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="point_update">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <label>Название <input type="text" name="name" value="<?= e($p['name']) ?>" required style="width:160px;"></label>
              <label>Город <input type="text" name="city" value="<?= e($p['city']) ?>" required style="width:150px;"></label>
              <label>Адрес <input type="text" name="address" value="<?= e($p['address'] ?? '') ?>" placeholder="необязательно" style="width:220px;"></label>
              <label><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>> вкл</label>
              <button class="btn small" type="submit">Сохранить</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">Пунктов пока нет.</div>
  <?php endif; ?>
  <p class="text-muted" style="margin-top:12px;">Отключённый пункт («Вкл» снят) скрывается из списка выше, но его история сохраняется.</p>

  <h4>Добавить пункт</h4>
  <form method="post" class="form-row">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="point_create">
    <label>Название<input type="text" name="name" placeholder="Например: Пункт Москва" required></label>
    <label>Город<input type="text" name="city" placeholder="Например: Москва" required></label>
    <label style="grid-column:1/-1;">Адрес (необязательно)<input type="text" name="address" placeholder="Улица, дом"></label>
    <div style="grid-column:1/-1;"><button class="btn" type="submit">Добавить пункт</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($pointId): ?>
<div class="card">
  <h3 style="margin-top:0;">На складе сейчас (<?= count($onHand) ?>)</h3>
  <?php if (!$onHand): ?>
    <div class="empty-state">Сейчас в этом пункте ничего не числится.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>№ заявки</th><th>Клиент</th><th>Маршрут</th><th>Принято</th><th>Вес</th><th>Действие</th></tr></thead>
      <tbody>
        <?php foreach ($onHand as $w): ?>
        <tr>
          <td><a href="/crm/order.php?id=<?= (int) $w['order_id'] ?>">#<?= (int) $w['order_id'] ?></a></td>
          <td><?= e($w['client_name']) ?><br><span class="text-muted"><?= e($w['client_phone']) ?></span></td>
          <td><?= e($w['from_city']) ?> → <?= e($w['to_city']) ?></td>
          <td><?= crm_date($w['created_at']) ?></td>
          <td><?= $w['weight_kg'] !== null ? e((string) $w['weight_kg']) . ' кг' : '—' ?></td>
          <td>
            <form method="post" class="inline-row" onsubmit="return confirm('Выдать заявку №<?= (int) $w['order_id'] ?> получателю?');">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="issue">
              <input type="hidden" name="point_id" value="<?= (int) $pointId ?>">
              <input type="hidden" name="order_id" value="<?= (int) $w['order_id'] ?>">
              <input type="text" name="recipient_name" placeholder="Имя получателя" required style="width:160px;">
              <button class="btn small" type="submit">Выдать</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Принять груз в пункте</h3>
  <?php if (!$notYetReceived): ?>
    <div class="empty-state">Нет заявок, ожидающих приёма.</div>
  <?php else: ?>
    <form method="post" class="form-row">
      <?= crm_csrf_field() ?>
      <input type="hidden" name="action" value="receive">
      <input type="hidden" name="point_id" value="<?= (int) $pointId ?>">
      <label>Заявка
        <select name="order_id" required>
          <option value="">— выберите —</option>
          <?php foreach ($notYetReceived as $o): ?>
            <option value="<?= (int) $o['id'] ?>">№<?= (int) $o['id'] ?> — <?= e($o['client_name']) ?> (<?= e($o['from_city']) ?> → <?= e($o['to_city']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Вес, кг (необязательно)<input type="number" step="0.01" name="weight_kg"></label>
      <label style="grid-column:1/-1;">Заметка<input type="text" name="note" placeholder="Например: 2 коробки, целостность в норме"></label>
      <div style="grid-column:1/-1;"><button class="btn" type="submit">Принять в пункт</button></div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">История пункта (последние 30)</h3>
  <?php if (!$recent): ?>
    <div class="empty-state">Пока нет событий.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Когда</th><th>Заявка</th><th>Событие</th><th>Кто</th><th>Детали</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><a href="/crm/order.php?id=<?= (int) $r['order_id'] ?>">#<?= (int) $r['order_id'] ?></a></td>
          <td><?= $r['event_type'] === 'received' ? 'Принято' : 'Выдано' ?></td>
          <td><?= e($r['user_name'] ?? '—') ?></td>
          <td>
            <?php if ($r['event_type'] === 'issued'): ?>
              Получатель: <?= e($r['recipient_name']) ?>
            <?php endif; ?>
            <?= $r['note'] ? ' · ' . e($r['note']) : '' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
