<?php
/**
 * &#x41f;&#x435;&#x447;&#x430;&#x442;&#x43d;&#x44b;&#x439; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x43d;&#x44b;&#x439; &#x43b;&#x438;&#x441;&#x442; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430; &#x43d;&#x430; &#x434;&#x435;&#x43d;&#x44c;: &#x441;&#x43f;&#x438;&#x441;&#x43e;&#x43a; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;, &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x43d;&#x44b;&#x445;
 * &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x443;, &#x441; &#x43f;&#x43b;&#x430;&#x43d;&#x438;&#x440;&#x443;&#x435;&#x43c;&#x43e;&#x439; &#x434;&#x430;&#x442;&#x43e;&#x439; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438; = &#x432;&#x44b;&#x431;&#x440;&#x430;&#x43d;&#x43d;&#x430;&#x44f; &#x434;&#x430;&#x442;&#x430; (&#x43f;&#x43e; &#x443;&#x43c;&#x43e;&#x43b;&#x447;&#x430;&#x43d;&#x438;&#x44e;
 * &#x441;&#x435;&#x433;&#x43e;&#x434;&#x43d;&#x44f;). &#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440; &#x432;&#x438;&#x434;&#x438;&#x442; &#x441;&#x432;&#x43e;&#x439; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;, &#x430;&#x434;&#x43c;&#x438;&#x43d;/&#x43e;&#x43f;&#x435;&#x440;&#x430;&#x442;&#x43e;&#x440; &#x43c;&#x43e;&#x436;&#x435;&#x442; &#x432;&#x44b;&#x431;&#x440;&#x430;&#x442;&#x44c;
 * &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430; &#x438; &#x434;&#x430;&#x442;&#x443; &#x447;&#x435;&#x440;&#x435;&#x437; &#x43f;&#x430;&#x440;&#x430;&#x43c;&#x435;&#x442;&#x440;&#x44b; &#x437;&#x430;&#x43f;&#x440;&#x43e;&#x441;&#x430;.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['courier', 'admin', 'operator']);
$pdo = crm_db();

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$isPrivileged = in_array($user['role'], ['admin', 'operator'], true);
$courierId = $isPrivileged ? (int) ($_GET['courier_id'] ?? 0) : (int) $user['id'];

$couriers = [];
if ($isPrivileged) {
    $couriers = $pdo->query("SELECT id, name FROM users WHERE role = 'courier' AND active = 1 ORDER BY name")->fetchAll();
}

$orders = [];
if ($courierId) {
    $stmt = $pdo->prepare("SELECT o.*, c.name AS client_name, c.phone AS client_phone
        FROM orders o JOIN clients c ON c.id = o.client_id
        WHERE o.courier_id = ? AND o.planned_date = ? AND o.status NOT IN ('delivered','cancelled')
        ORDER BY o.to_address");
    $stmt->execute([$courierId, $date]);
    $orders = $stmt->fetchAll();
}

$courierName = '';
foreach ($couriers as $c) {
    if ((int) $c['id'] === $courierId) {
        $courierName = $c['name'];
    }
}
if (!$courierName && !$isPrivileged) {
    $courierName = $user['name'];
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x43d;&#x44b;&#x439; &#x43b;&#x438;&#x441;&#x442; &#x2014; <?= e($date) ?></title>
<style>
  :root{--brand:#0a2472;}
  *{box-sizing:border-box;}
  body{font-family:Arial,Helvetica,sans-serif;color:#1c2438;max-width:860px;margin:24px auto;padding:0 16px;font-size:.88rem;}
  .no-print{margin-bottom:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap;}
  .no-print button, .no-print a.btn{background:var(--brand);color:#fff;border:none;border-radius:8px;padding:10px 18px;font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;}
  .no-print select, .no-print input{padding:8px;border:1px solid #c9d0e3;border-radius:6px;}
  h1{font-size:1.2rem;margin-bottom:2px;}
  .sub{color:#6b7490;margin-bottom:16px;}
  table{width:100%;border-collapse:collapse;font-size:.85rem;}
  th,td{border:1px solid #c9d0e3;padding:8px 10px;text-align:left;vertical-align:top;}
  th{background:#f2f4fa;}
  .sign-row td{height:50px;}
  @media print { .no-print{display:none;} }
</style>
</head>
<body>

<form class="no-print" method="get">
  <div>
    <label>&#x414;&#x430;&#x442;&#x430;</label><br>
    <input type="date" name="date" value="<?= e($date) ?>">
  </div>
  <?php if ($isPrivileged): ?>
  <div>
    <label>&#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440;</label><br>
    <select name="courier_id">
      <option value="">&#x2014; &#x432;&#x44b;&#x431;&#x435;&#x440;&#x438;&#x442;&#x435; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430; &#x2014;</option>
      <?php foreach ($couriers as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $courierId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <button type="submit">&#x41f;&#x43e;&#x43a;&#x430;&#x437;&#x430;&#x442;&#x44c;</button>
  <button type="button" onclick="window.print()">&#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c;</button>
</form>

<h1>&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x43d;&#x44b;&#x439; &#x43b;&#x438;&#x441;&#x442; &#x43d;&#x430; <?= e(crm_date($date, 'd.m.Y')) ?></h1>
<p class="sub">&#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440;: <?= e($courierName ?: "\u{2014}") ?><?= $courierId ? " \u{b7} \u{437}\u{430}\u{44f}\u{432}\u{43e}\u{43a}: " . count($orders) : '' ?></p>

<?php if (!$courierId): ?>
  <p>&#x412;&#x44b;&#x431;&#x435;&#x440;&#x438;&#x442;&#x435; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430;, &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x443;&#x432;&#x438;&#x434;&#x435;&#x442;&#x44c; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x43d;&#x44b;&#x439; &#x43b;&#x438;&#x441;&#x442;.</p>
<?php elseif (!$orders): ?>
  <p>&#x41d;&#x430; &#x44d;&#x442;&#x443; &#x434;&#x430;&#x442;&#x443; &#x443; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430; &#x43d;&#x435;&#x442; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442; / &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;</th>
      <th>&#x410;&#x434;&#x440;&#x435;&#x441; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</th>
      <th>&#x413;&#x440;&#x443;&#x437;</th>
      <th>&#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x430;</th>
      <th>&#x41f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f;</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td>&#x2116;<?= (int) $o['id'] ?></td>
      <td><?= e($o['client_name']) ?><br><?= e($o['client_phone']) ?></td>
      <td><?= e($o['to_address']) ?></td>
      <td><?= e($o['cargo_description']) ?><?= $o['places_count'] > 1 ? ' (' . (int) $o['places_count'] . " \u{43c}\u{435}\u{441}\u{442}" . ')' : '' ?></td>
      <td>
        <?= e(crm_payment_status_label($o['payment_status'])) ?>
        <?php if (!empty($o['is_cod'])): ?>
          <br><strong>&#x41d;&#x41f;: <?= crm_money((float) ($o['cod_amount'] ?? 0)) ?></strong>
        <?php endif; ?>
      </td>
      <td class="sign-row"></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

</body>
</html>
