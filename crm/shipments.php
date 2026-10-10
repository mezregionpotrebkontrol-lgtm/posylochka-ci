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
        die("\u{420}\u{435}\u{439}\u{441} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}.");
    }
}

// &#x421;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x438;&#x435; &#x43d;&#x43e;&#x432;&#x43e;&#x433;&#x43e; &#x440;&#x435;&#x439;&#x441;&#x430; &#x432;&#x440;&#x443;&#x447;&#x43d;&#x443;&#x44e;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $stmt = $pdo->prepare('INSERT INTO shipment_runs (from_city, to_city, run_date, notes, created_by) VALUES (?,?,?,?,?)');
    $stmt->execute([
        trim($_POST['from_city'] ?? '') ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}",
        trim($_POST['to_city'] ?? '') ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}",
        $_POST['run_date'] !== '' ? $_POST['run_date'] : null,
        trim($_POST['notes'] ?? '') ?: null,
        $user['id'],
    ]);
    $newId = (int) $pdo->lastInsertId();
    crm_flash_set("\u{420}\u{435}\u{439}\u{441} \u{2116}" . $newId . " \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}.");
    crm_redirect('/crm/shipments.php?id=' . $newId);
}

// &#x421;&#x43c;&#x435;&#x43d;&#x430; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430; &#x440;&#x435;&#x439;&#x441;&#x430;
if ($run && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    crm_csrf_check();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['forming', 'in_transit', 'completed'], true)) {
        $pdo->prepare('UPDATE shipment_runs SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        // &#x415;&#x441;&#x43b;&#x438; &#x440;&#x435;&#x439;&#x441; &#x432;&#x44b;&#x435;&#x445;&#x430;&#x43b;/&#x437;&#x430;&#x432;&#x435;&#x440;&#x448;&#x451;&#x43d; &#x2014; &#x43f;&#x435;&#x440;&#x435;&#x43d;&#x43e;&#x441;&#x438;&#x43c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x44b; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;, &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x44b;&#x435; &#x435;&#x449;&#x451; &#x43d;&#x435; &#x43f;&#x440;&#x43e;&#x434;&#x432;&#x438;&#x43d;&#x443;&#x43b;&#x438;&#x441;&#x44c; &#x434;&#x430;&#x43b;&#x44c;&#x448;&#x435;.
        if ($newStatus === 'in_transit') {
            $upd = $pdo->prepare("UPDATE orders SET status = 'in_transit' WHERE shipment_run_id = ? AND status IN ('new','accepted','collecting')");
            $upd->execute([$id]);
            $ordersInRun = $pdo->prepare('SELECT id FROM orders WHERE shipment_run_id = ?');
            $ordersInRun->execute([$id]);
            foreach ($ordersInRun->fetchAll() as $row) {
                $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'in_transit', ?, '\u{420}\u{435}\u{439}\u{441} \u{432}\u{44b}\u{435}\u{445}\u{430}\u{43b}')")
                    ->execute([$row['id'], $user['id']]);
            }
        } elseif ($newStatus === 'completed') {
            $upd = $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE shipment_run_id = ? AND status IN ('new','accepted','collecting','in_transit')");
            $upd->execute([$id]);
            $ordersInRun = $pdo->prepare('SELECT id FROM orders WHERE shipment_run_id = ?');
            $ordersInRun->execute([$id]);
            foreach ($ordersInRun->fetchAll() as $row) {
                $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'delivered', ?, '\u{420}\u{435}\u{439}\u{441} \u{437}\u{430}\u{432}\u{435}\u{440}\u{448}\u{451}\u{43d}')")
                    ->execute([$row['id'], $user['id']]);
            }
        }
        crm_flash_set("\u{421}\u{442}\u{430}\u{442}\u{443}\u{441} \u{440}\u{435}\u{439}\u{441}\u{430} \u{438}\u{437}\u{43c}\u{435}\u{43d}\u{451}\u{43d} \u{43d}\u{430} \u{ab}" . crm_shipment_run_status_label($newStatus) . "\u{bb}.");
    }
    crm_redirect('/crm/shipments.php?id=' . $id);
}

// &#x423;&#x434;&#x430;&#x43b;&#x438;&#x442;&#x44c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; &#x438;&#x437; &#x440;&#x435;&#x439;&#x441;&#x430; (&#x438;&#x437; &#x43a;&#x430;&#x440;&#x442;&#x43e;&#x447;&#x43a;&#x438; &#x440;&#x435;&#x439;&#x441;&#x430;)
if ($run && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_order') {
    crm_csrf_check();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $pdo->prepare('UPDATE orders SET shipment_run_id = NULL WHERE id = ? AND shipment_run_id = ?')->execute([$orderId, $id]);
    crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{443}\u{431}\u{440}\u{430}\u{43d}\u{430} \u{438}\u{437} \u{440}\u{435}\u{439}\u{441}\u{430}.");
    crm_redirect('/crm/shipments.php?id=' . $id);
}

// &#x410;&#x432;&#x442;&#x43e;&#x43c;&#x430;&#x442;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x430;&#x44f; &#x433;&#x440;&#x443;&#x43f;&#x43f;&#x438;&#x440;&#x43e;&#x432;&#x43a;&#x430;: &#x441;&#x43e;&#x431;&#x438;&#x440;&#x430;&#x435;&#x43c; &#x435;&#x449;&#x451; &#x43d;&#x435; &#x440;&#x430;&#x441;&#x43f;&#x440;&#x435;&#x434;&#x435;&#x43b;&#x451;&#x43d;&#x43d;&#x44b;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x43e;&#x434;&#x43d;&#x43e;&#x433;&#x43e; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x430;
// &#x438; &#x434;&#x430;&#x442;&#x44b; (&#x441;&#x442;&#x430;&#x442;&#x443;&#x441; new/accepted/collecting) &#x432; &#x43d;&#x43e;&#x432;&#x44b;&#x435; &#x440;&#x435;&#x439;&#x441;&#x44b; &#x2014; &#x43f;&#x43e; 2 &#x438; &#x431;&#x43e;&#x43b;&#x435;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a; &#x43d;&#x430; &#x433;&#x440;&#x443;&#x43f;&#x43f;&#x443;.
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
            continue; // &#x43e;&#x434;&#x43d;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435; &#x43e;&#x442;&#x434;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441; &#x43d;&#x435; &#x43d;&#x443;&#x436;&#x435;&#x43d;
        }
        [$fromCity, $toCity, $planDate] = explode('|', $key);
        $ins = $pdo->prepare('INSERT INTO shipment_runs (from_city, to_city, run_date, notes, created_by) VALUES (?,?,?,?,?)');
        $ins->execute([$fromCity, $toCity, $planDate, "\u{421}\u{43e}\u{437}\u{434}\u{430}\u{43d} \u{430}\u{432}\u{442}\u{43e}\u{43c}\u{430}\u{442}\u{438}\u{447}\u{435}\u{441}\u{43a}\u{438} \u{43f}\u{440}\u{438} \u{433}\u{440}\u{443}\u{43f}\u{43f}\u{438}\u{440}\u{43e}\u{432}\u{43a}\u{435} \u{437}\u{430}\u{44f}\u{432}\u{43e}\u{43a}", $user['id']]);
        $runId = (int) $pdo->lastInsertId();
        $created++;
        foreach ($groupOrders as $o) {
            $pdo->prepare('UPDATE orders SET shipment_run_id = ? WHERE id = ?')->execute([$runId, $o['id']]);
            $grouped++;
        }
    }
    if ($created > 0) {
        crm_flash_set("\u{421}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{43e} \u{440}\u{435}\u{439}\u{441}\u{43e}\u{432}: " . $created . ", \u{432} \u{43d}\u{438}\u{445} \u{43e}\u{431}\u{44a}\u{435}\u{434}\u{438}\u{43d}\u{435}\u{43d}\u{43e} \u{437}\u{430}\u{44f}\u{432}\u{43e}\u{43a}: " . $grouped . '.');
    } else {
        crm_flash_set("\u{41d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{43e} \u{437}\u{430}\u{44f}\u{432}\u{43e}\u{43a} \u{441} \u{441}\u{43e}\u{432}\u{43f}\u{430}\u{434}\u{430}\u{44e}\u{449}\u{438}\u{43c} \u{43c}\u{430}\u{440}\u{448}\u{440}\u{443}\u{442}\u{43e}\u{43c} \u{438} \u{434}\u{430}\u{442}\u{43e}\u{439} \u{434}\u{43b}\u{44f} \u{43e}\u{431}\u{44a}\u{435}\u{434}\u{438}\u{43d}\u{435}\u{43d}\u{438}\u{44f} (\u{43d}\u{443}\u{436}\u{43d}\u{43e} \u{43c}\u{438}\u{43d}\u{438}\u{43c}\u{443}\u{43c} 2 \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{43d}\u{430} \u{43c}\u{430}\u{440}\u{448}\u{440}\u{443}\u{442}+\u{434}\u{430}\u{442}\u{443}).", 'err');
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

$pageTitle = $run ? ("\u{420}\u{435}\u{439}\u{441} \u{2116}" . $run['id']) : "\u{421}\u{431}\u{43e}\u{440}\u{43d}\u{44b}\u{435} \u{440}\u{435}\u{439}\u{441}\u{44b}";
$activeNav = 'shipments';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/shipments.php">&#x2190; &#x412;&#x441;&#x435; &#x440;&#x435;&#x439;&#x441;&#x44b;</a></p>

<?php if (!$run): ?>

<div class="card">
  <h3 style="margin-top:0;">&#x41d;&#x43e;&#x432;&#x44b;&#x439; &#x441;&#x431;&#x43e;&#x440;&#x43d;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441;</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div><label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="from_city" value="&#x414;&#x435;&#x440;&#x431;&#x435;&#x43d;&#x442;"></div>
      <div><label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="to_city" value="&#x421;&#x430;&#x43d;&#x43a;&#x442;-&#x41f;&#x435;&#x442;&#x435;&#x440;&#x431;&#x443;&#x440;&#x433;"></div>
    </div>
    <label>&#x414;&#x430;&#x442;&#x430; &#x440;&#x435;&#x439;&#x441;&#x430;</label>
    <input type="date" name="run_date">
    <label>&#x417;&#x430;&#x43c;&#x435;&#x442;&#x43a;&#x430; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
    <input type="text" name="notes">
    <div class="form-actions"><button class="btn" type="submit">&#x421;&#x43e;&#x437;&#x434;&#x430;&#x442;&#x44c; &#x440;&#x435;&#x439;&#x441;</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x410;&#x432;&#x442;&#x43e;&#x43c;&#x430;&#x442;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x430;&#x44f; &#x433;&#x440;&#x443;&#x43f;&#x43f;&#x438;&#x440;&#x43e;&#x432;&#x43a;&#x430; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;</h3>
  <p class="text-muted">&#x421;&#x43e;&#x431;&#x435;&#x440;&#x451;&#x442; &#x435;&#x449;&#x451; &#x43d;&#x435; &#x440;&#x430;&#x441;&#x43f;&#x440;&#x435;&#x434;&#x435;&#x43b;&#x451;&#x43d;&#x43d;&#x44b;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (&#x43d;&#x43e;&#x432;&#x430;&#x44f; / &#x43f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x430; / &#x441;&#x431;&#x43e;&#x440; &#x433;&#x440;&#x443;&#x437;&#x430;) &#x441; &#x43e;&#x434;&#x438;&#x43d;&#x430;&#x43a;&#x43e;&#x432;&#x44b;&#x43c; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x43e;&#x43c;
    &#x438; &#x43f;&#x43b;&#x430;&#x43d;&#x438;&#x440;&#x443;&#x435;&#x43c;&#x43e;&#x439; &#x434;&#x430;&#x442;&#x43e;&#x439; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438; &#x432; &#x43d;&#x43e;&#x432;&#x44b;&#x435; &#x440;&#x435;&#x439;&#x441;&#x44b; &#x2014; &#x43f;&#x43e; &#x43a;&#x430;&#x436;&#x434;&#x43e;&#x43c;&#x443; &#x441;&#x43e;&#x432;&#x43f;&#x430;&#x434;&#x430;&#x44e;&#x449;&#x435;&#x43c;&#x443; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x443; &#x438; &#x434;&#x430;&#x442;&#x435;, &#x433;&#x434;&#x435; &#x435;&#x441;&#x442;&#x44c; &#x43c;&#x438;&#x43d;&#x438;&#x43c;&#x443;&#x43c;
    2 &#x442;&#x430;&#x43a;&#x438;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;.</p>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="auto_group">
    <button class="btn secondary" type="submit">&#x421;&#x433;&#x440;&#x443;&#x43f;&#x43f;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x442;&#x44c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x430;&#x432;&#x442;&#x43e;&#x43c;&#x430;&#x442;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x438;</button>
  </form>
</div>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</label>
      <select name="status">
        <option value="">&#x412;&#x441;&#x435;</option>
        <?php foreach (['forming','in_transit','completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(crm_shipment_run_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">&#x41f;&#x440;&#x438;&#x43c;&#x435;&#x43d;&#x438;&#x442;&#x44c;</button>
    <a class="btn secondary" href="/crm/shipments.php">&#x421;&#x431;&#x440;&#x43e;&#x441;&#x438;&#x442;&#x44c;</a>
  </form>
</div>

<div class="card">
  <?php if (!$runs): ?>
    <div class="empty-state">&#x420;&#x435;&#x439;&#x441;&#x43e;&#x432; &#x43d;&#x435; &#x43d;&#x430;&#x439;&#x434;&#x435;&#x43d;&#x43e;.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>&#x2116;</th><th>&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;</th><th>&#x414;&#x430;&#x442;&#x430;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x417;&#x430;&#x44f;&#x432;&#x43e;&#x43a; &#x432; &#x440;&#x435;&#x439;&#x441;&#x435;</th></tr></thead>
    <tbody>
      <?php foreach ($runs as $r): ?>
      <tr>
        <td><a href="/crm/shipments.php?id=<?= (int)$r['id'] ?>">&#x2116;<?= (int)$r['id'] ?></a></td>
        <td><?= e($r['from_city']) ?> &#x2192; <?= e($r['to_city']) ?></td>
        <td><?= $r['run_date'] ? crm_date($r['run_date'], 'd.m.Y') : "\u{2014}" ?></td>
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
      <strong style="margin-left:8px;"><?= e($run['from_city']) ?> &#x2192; <?= e($run['to_city']) ?></strong>
      <?php if ($run['run_date']): ?><span class="text-muted"> &#xb7; <?= crm_date($run['run_date'], 'd.m.Y') ?></span><?php endif; ?>
    </div>
    <form method="post" class="inline-row">
      <?= crm_csrf_field() ?>
      <input type="hidden" name="action" value="change_status">
      <select name="status">
        <?php foreach (['forming','in_transit','completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $run['status'] === $s ? 'selected' : '' ?>><?= e(crm_shipment_run_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn small secondary" type="submit">&#x41e;&#x431;&#x43d;&#x43e;&#x432;&#x438;&#x442;&#x44c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;</button>
    </form>
  </div>
  <?php if ($run['notes']): ?><p class="text-muted" style="margin-top:12px;margin-bottom:0;"><?= e($run['notes']) ?></p><?php endif; ?>
  <?php if (in_array($run['status'], ['in_transit', 'completed'], true)): ?>
    <p class="text-muted" style="margin-top:10px;margin-bottom:0;">&#x41f;&#x440;&#x438; &#x43f;&#x435;&#x440;&#x435;&#x445;&#x43e;&#x434;&#x435; &#x432; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441; &#xab;&#x412; &#x43f;&#x443;&#x442;&#x438;&#xbb; &#x432;&#x441;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x440;&#x435;&#x439;&#x441;&#x430; &#x430;&#x432;&#x442;&#x43e;&#x43c;&#x430;&#x442;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x438; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x44e;&#x442; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441; &#xab;&#x412; &#x43f;&#x443;&#x442;&#x438;&#xbb;, &#x430; &#x43f;&#x440;&#x438; &#xab;&#x417;&#x430;&#x432;&#x435;&#x440;&#x448;&#x451;&#x43d;&#xbb; &#x2014; &#xab;&#x414;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x430;&#xbb;.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x432; &#x440;&#x435;&#x439;&#x441;&#x435;</h3>
  <?php if (!$runOrders): ?>
    <div class="empty-state">&#x412; &#x44d;&#x442;&#x43e;&#x43c; &#x440;&#x435;&#x439;&#x441;&#x435; &#x43f;&#x43e;&#x43a;&#x430; &#x43d;&#x435;&#x442; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;. &#x414;&#x43e;&#x431;&#x430;&#x432;&#x44c;&#x442;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; &#x438;&#x437; &#x435;&#x451; &#x43a;&#x430;&#x440;&#x442;&#x43e;&#x447;&#x43a;&#x438; (&#xab;&#x421;&#x431;&#x43e;&#x440;&#x43d;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441;&#xbb;) &#x438;&#x43b;&#x438; &#x438;&#x441;&#x43f;&#x43e;&#x43b;&#x44c;&#x437;&#x443;&#x439;&#x442;&#x435; &#x430;&#x432;&#x442;&#x43e;&#x433;&#x440;&#x443;&#x43f;&#x43f;&#x438;&#x440;&#x43e;&#x432;&#x43a;&#x443;.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>&#x2116;</th><th>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;</th><th>&#x413;&#x440;&#x443;&#x437;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($runOrders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><?= e($o['client_name']) ?></td>
        <td><?= e($o['cargo_description']) ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td>
          <form method="post" class="inline" onsubmit="return confirm("\u{423}\u{431}\u{440}\u{430}\u{442}\u{44c} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{443} \u{438}\u{437} \u{440}\u{435}\u{439}\u{441}\u{430}?");">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="remove_order">
            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
            <button class="btn small secondary" type="submit">&#x423;&#x431;&#x440;&#x430;&#x442;&#x44c; &#x438;&#x437; &#x440;&#x435;&#x439;&#x441;&#x430;</button>
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
