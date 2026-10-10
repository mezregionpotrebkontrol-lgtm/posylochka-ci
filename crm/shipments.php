<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$run = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM shipment_runs WHERE id = ?');
    $stmt->execute([$id]);
    $run = $stmt->fetch();
    if (!$run) {
        http_response_code(404);
        die('Рейс не найден.');
    }
}

// Создание нового рейса вручную
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $stmt = $pdo->prepare('INSERT INTO shipment_runs (from_city, to_city, run_date, notes, created_by) VALUES (?,?,?,?,?)');
    $stmt->execute([
        trim($_POST['from_city'] ?? '') ?: 'Дербент',
        trim($_POST['to_city'] ?? '') ?: 'Санкт-Петербург',
        $_POST['run_date'] !== '' ? $_POST['run_date'] : null,
        trim($_POST['notes'] ?? '') ?: null,
        $user['id'],
    ]);
    $newId = (int) $pdo->lastInsertId();
    crm_flash_set('Рейс №' . $newId . ' создан.');
    crm_redirect('/crm/shipments.php?id=' . $newId);
}

// Смена статуса рейса
if ($run && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    crm_csrf_check();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['forming', 'in_transit', 'completed'], true)) {
        $pdo->prepare('UPDATE shipment_runs SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        // Если рейс выехал/завершён — переносим статусы заявок, которые ещё не продвинулись дальше.
        if ($newStatus === 'in_transit') {
            $upd = $pdo->prepare("UPDATE orders SET status = 'in_transit' WHERE shipment_run_id = ? AND status IN ('new','accepted','collecting')");
            $upd->execute([$id]);
            $ordersInRun = $pdo->prepare('SELECT id FROM orders WHERE shipment_run_id = ?');
            $ordersInRun->execute([$id]);
            foreach ($ordersInRun->fetchAll() as $row) {
                $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'in_transit', ?, 'Рейс выехал')")
                    ->execute([$row['id'], $user['id']]);
            }
        } elseif ($newStatus === 'completed') {
            $upd = $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE shipment_run_id = ? AND status IN ('new','accepted','collecting','in_transit')");
            $upd->execute([$id]);
            $ordersInRun = $pdo->prepare('SELECT id FROM orders WHERE shipment_run_id = ?');
            $ordersInRun->execute([$id]);
            foreach ($ordersInRun->fetchAll() as $row) {
                $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'delivered', ?, 'Рейс завершён')")
                    ->execute([$row['id'], $user['id']]);
            }
        }
        crm_flash_set('Статус рейса изменён на «' . crm_shipment_run_status_label($newStatus) . '».');
    }
    crm_redirect('/crm/shipments.php?id=' . $id);
}

// Удалить заявку из рейса (из карточки рейса)
if ($run && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_order') {
    crm_csrf_check();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $pdo->prepare('UPDATE orders SET shipment_run_id = NULL WHERE id = ? AND shipment_run_id = ?')->execute([$orderId, $id]);
    crm_flash_set('Заявка убрана из рейса.');
    crm_redirect('/crm/shipments.php?id=' . $id);
}

// Автоматическая группировка: собираем ещё не распределённые заявки одного маршрута
// и даты (статус new/accepted/collecting) в новые рейсы — по 2 и более заявок на группу.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_group') {
    crm_csrf_check();
    $rows = $pdo->query("SELECT id, from_city, to_city, planned_date FROM orders
        WHERE shipment_run_id IS NULL AND status IN ('new','accepted','collecting') AND planned_date IS NOT NULL")->fetchAll();
    $groups = [];
    foreach ($rows as $r) {
        $key = $r['from_city'] . '|' . $r['to_city'] . '|' . $r['planned_date'];
        $groups[$key][] = $r;
    }
    $created = 0;
    $grouped = 0;
    foreach ($groups as $key => $groupOrders) {
        if (count($groupOrders) < 2) {
            continue; // одной заявке отдельный рейс не нужен
        }
        [$fromCity, $toCity, $planDate] = explode('|', $key);
        $ins = $pdo->prepare('INSERT INTO shipment_runs (from_city, to_city, run_date, notes, created_by) VALUES (?,?,?,?,?)');
        $ins->execute([$fromCity, $toCity, $planDate, 'Создан автоматически при группировке заявок', $user['id']]);
        $runId = (int) $pdo->lastInsertId();
        $created++;
        foreach ($groupOrders as $o) {
            $pdo->prepare('UPDATE orders SET shipment_run_id = ? WHERE id = ?')->execute([$runId, $o['id']]);
            $grouped++;
        }
    }
    if ($created > 0) {
        crm_flash_set('Создано рейсов: ' . $created . ', в них объединено заявок: ' . $grouped . '.');
    } else {
        crm_flash_set('Не найдено заявок с совпадающим маршрутом и датой для объединения (нужно минимум 2 заявки на маршрут+дату).', 'err');
    }
    crm_redirect('/crm/shipments.php');
}

$runOrders = [];
if ($run) {
    $oStmt = $pdo->prepare('SELECT o.*, c.name AS client_name FROM orders o JOIN clients c ON c.id = o.client_id WHERE o.shipment_run_id = ? ORDER BY o.id');
    $oStmt->execute([$id]);
    $runOrders = $oStmt->fetchAll();
}

$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['forming', 'in_transit', 'completed'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
$sql = 'SELECT sr.*, (SELECT COUNT(*) FROM orders o WHERE o.shipment_run_id = sr.id) AS orders_count FROM shipment_runs sr';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY sr.run_date IS NULL, sr.run_date DESC, sr.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$runs = $stmt->fetchAll();

$pageTitle = $run ? ('Рейс №' . $run['id']) : 'Сборные рейсы';
$activeNav = 'shipments';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/shipments.php">← Все рейсы</a></p>

<?php if (!$run): ?>

<div class="card">
  <h3 style="margin-top:0;">Новый сборный рейс</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div><label>Город отправления</label><input type="text" name="from_city" value="Дербент"></div>
      <div><label>Город назначения</label><input type="text" name="to_city" value="Санкт-Петербург"></div>
    </div>
    <label>Дата рейса</label>
    <input type="date" name="run_date">
    <label>Заметка (необязательно)</label>
    <input type="text" name="notes">
    <div class="form-actions"><button class="btn" type="submit">Создать рейс</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Автоматическая группировка заявок</h3>
  <p class="text-muted">Соберёт ещё не распределённые заявки (новая / принята / сбор груза) с одинаковым маршрутом
    и планируемой датой доставки в новые рейсы — по каждому совпадающему маршруту и дате, где есть минимум
    2 такие заявки.</p>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="auto_group">
    <button class="btn secondary" type="submit">Сгруппировать заявки автоматически</button>
  </form>
</div>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>Статус</label>
      <select name="status">
        <option value="">Все</option>
        <?php foreach (['forming','in_transit','completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(crm_shipment_run_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Применить</button>
    <a class="btn secondary" href="/crm/shipments.php">Сбросить</a>
  </form>
</div>

<div class="card">
  <?php if (!$runs): ?>
    <div class="empty-state">Рейсов не найдено.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Маршрут</th><th>Дата</th><th>Статус</th><th>Заявок в рейсе</th></tr></thead>
    <tbody>
      <?php foreach ($runs as $r): ?>
      <tr>
        <td><a href="/crm/shipments.php?id=<?= (int)$r['id'] ?>">№<?= (int)$r['id'] ?></a></td>
        <td><?= e($r['from_city']) ?> → <?= e($r['to_city']) ?></td>
        <td><?= $r['run_date'] ? crm_date($r['run_date'], 'd.m.Y') : '—' ?></td>
        <td><span class="badge <?= crm_shipment_run_status_class($r['status']) ?>"><?= e(crm_shipment_run_status_label($r['status'])) ?></span></td>
        <td><?= (int) $r['orders_count'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php else: ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <span class="badge <?= crm_shipment_run_status_class($run['status']) ?>" style="font-size:.85rem;"><?= e(crm_shipment_run_status_label($run['status'])) ?></span>
      <strong style="margin-left:8px;"><?= e($run['from_city']) ?> → <?= e($run['to_city']) ?></strong>
      <?php if ($run['run_date']): ?><span class="text-muted"> · <?= crm_date($run['run_date'], 'd.m.Y') ?></span><?php endif; ?>
    </div>
    <form method="post" class="inline-row">
      <?= crm_csrf_field() ?>
      <input type="hidden" name="action" value="change_status">
      <select name="status">
        <?php foreach (['forming','in_transit','completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $run['status'] === $s ? 'selected' : '' ?>><?= e(crm_shipment_run_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn small secondary" type="submit">Обновить статус</button>
    </form>
  </div>
  <?php if ($run['notes']): ?><p class="text-muted" style="margin-top:12px;margin-bottom:0;"><?= e($run['notes']) ?></p><?php endif; ?>
  <?php if (in_array($run['status'], ['in_transit', 'completed'], true)): ?>
    <p class="text-muted" style="margin-top:10px;margin-bottom:0;">При переходе в статус «В пути» все заявки рейса автоматически получают статус «В пути», а при «Завершён» — «Доставлена».</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Заявки в рейсе</h3>
  <?php if (!$runOrders): ?>
    <div class="empty-state">В этом рейсе пока нет заявок. Добавьте заявку из её карточки («Сборный рейс») или используйте автогруппировку.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Клиент</th><th>Груз</th><th>Статус</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($runOrders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><?= e($o['client_name']) ?></td>
        <td><?= e($o['cargo_description']) ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td>
          <form method="post" class="inline" onsubmit="return confirm('Убрать заявку из рейса?');">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="remove_order">
            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
            <button class="btn small secondary" type="submit">Убрать из рейса</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
