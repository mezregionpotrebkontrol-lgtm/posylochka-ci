<?php
/**
 * &#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x441; &#x43b;&#x435;&#x43d;&#x434;&#x438;&#x43d;&#x433;&#x430; (&#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x430; &#x431;&#x435;&#x437; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b; + B2B), &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x44b;&#x435; &#x43f;&#x440;&#x438;&#x448;&#x43b;&#x438; &#x447;&#x435;&#x440;&#x435;&#x437;
 * submit_order_request.php / submit_business_request.php. &#x42d;&#x442;&#x43e; &#x43f;&#x440;&#x43e;&#x441;&#x442;&#x43e; &#x43b;&#x43e;&#x433;
 * &#x43e;&#x431;&#x440;&#x430;&#x449;&#x435;&#x43d;&#x438;&#x439; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430; &#x2014; &#x43d;&#x430; &#x43e;&#x442;&#x43b;&#x438;&#x447;&#x438;&#x435; &#x43e;&#x442; "&#x417;&#x430;&#x44f;&#x432;&#x43e;&#x43a;" (orders), &#x437;&#x434;&#x435;&#x441;&#x44c; &#x43d;&#x435;&#x442; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430;
 * &#x438; &#x442;&#x440;&#x435;&#x43a;&#x438;&#x43d;&#x433;&#x430;, &#x44d;&#x442;&#x43e; &#x441;&#x44b;&#x440;&#x44b;&#x435; &#x43b;&#x438;&#x434;&#x44b; &#x434;&#x43b;&#x44f; &#x43e;&#x431;&#x440;&#x430;&#x431;&#x43e;&#x442;&#x43a;&#x438; &#x43c;&#x435;&#x43d;&#x435;&#x434;&#x436;&#x435;&#x440;&#x43e;&#x43c; (&#x438;&#x445; &#x434;&#x443;&#x431;&#x43b;&#x438;&#x440;&#x443;&#x435;&#x442; MAX-
 * &#x443;&#x432;&#x435;&#x434;&#x43e;&#x43c;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x432;&#x43b;&#x430;&#x434;&#x435;&#x43b;&#x44c;&#x446;&#x443; &#x432; &#x43c;&#x43e;&#x43c;&#x435;&#x43d;&#x442; &#x43f;&#x43e;&#x441;&#x442;&#x443;&#x43f;&#x43b;&#x435;&#x43d;&#x438;&#x44f;).
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

// &#x41a;&#x43e;&#x43d;&#x432;&#x435;&#x440;&#x442;&#x430;&#x446;&#x438;&#x44f; &#x43b;&#x438;&#x434;&#x430; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430; &#x432; &#x43f;&#x43e;&#x43b;&#x43d;&#x43e;&#x446;&#x435;&#x43d;&#x43d;&#x443;&#x44e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; (orders). &#x421;&#x43e;&#x437;&#x434;&#x430;&#x451;&#x442; (&#x438;&#x43b;&#x438; &#x43d;&#x430;&#x445;&#x43e;&#x434;&#x438;&#x442;
// &#x43f;&#x43e; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x443;) &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x438; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; &#x441;&#x43e; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x43e;&#x43c; "new", &#x43e;&#x442;&#x43c;&#x435;&#x447;&#x430;&#x435;&#x442; &#x43b;&#x438;&#x434; &#x43a;&#x430;&#x43a; &#x43e;&#x431;&#x440;&#x430;&#x431;&#x43e;&#x442;&#x430;&#x43d;&#x43d;&#x44b;&#x439;.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'convert_order_request') {
    crm_csrf_check();
    $leadId = (int) ($_POST['lead_id'] ?? 0);
    $lStmt = $pdo->prepare('SELECT * FROM site_order_requests WHERE id = ?');
    $lStmt->execute([$leadId]);
    $lead = $lStmt->fetch();
    if (!$lead) {
        crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{430}.", 'err');
    } elseif ($lead['converted_order_id']) {
        crm_flash_set("\u{42d}\u{442}\u{430} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{443}\u{436}\u{435} \u{431}\u{44b}\u{43b}\u{430} \u{43f}\u{435}\u{440}\u{435}\u{43d}\u{435}\u{441}\u{435}\u{43d}\u{430} \u{432} CRM (\u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . (int) $lead['converted_order_id'] . ').', 'err');
    } else {
        $phoneDigits = crm_phone_digits($lead['phone']);
        $clientId = null;
        if ($phoneDigits) {
            $cStmt = $pdo->prepare("SELECT id FROM clients WHERE REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-','') LIKE ?");
            $cStmt->execute(['%' . substr($phoneDigits, -10)]);
            $existing = $cStmt->fetch();
            if ($existing) {
                $clientId = (int) $existing['id'];
            }
        }
        if (!$clientId) {
            $pdo->prepare('INSERT INTO clients (name, phone, created_by) VALUES (?,?,?)')
                ->execute([$lead['name'], $lead['phone'], $user['id']]);
            $clientId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO orders
            (client_id, created_by, from_city, to_city, weight_kg, comment, status)
            VALUES (?,?,?,?,?,?,\'new\')')
            ->execute([
                $clientId,
                $user['id'],
                $lead['city'] ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}",
                $lead['dest_city'] ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}",
                $lead['weight'] !== null && $lead['weight'] !== '' && is_numeric($lead['weight']) ? (float) $lead['weight'] : null,
                trim(($lead['comment'] ? $lead['comment'] . "\n\n" : '') . "\u{418}\u{441}\u{442}\u{43e}\u{447}\u{43d}\u{438}\u{43a}: \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430} \u{2116}" . $leadId),
            ]);
        $newOrderId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'new', ?, '\u{421}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430} \u{430}\u{432}\u{442}\u{43e}\u{43c}\u{430}\u{442}\u{438}\u{447}\u{435}\u{441}\u{43a}\u{438} \u{438}\u{437} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}')")
            ->execute([$newOrderId, $user['id']]);
        $pdo->prepare('UPDATE site_order_requests SET converted_order_id = ? WHERE id = ?')->execute([$newOrderId, $leadId]);
        crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $newOrderId . " \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430} \u{43d}\u{430} \u{43e}\u{441}\u{43d}\u{43e}\u{432}\u{435} \u{43e}\u{431}\u{440}\u{430}\u{449}\u{435}\u{43d}\u{438}\u{44f} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}.");
    }
    crm_redirect('/crm/leads.php');
}

$orderRequests = $pdo->query('SELECT * FROM site_order_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();
$businessRequests = $pdo->query('SELECT * FROM business_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();

$pageTitle = "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}";
$activeNav = 'leads';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x43d;&#x430; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x443; &#x441; &#x43b;&#x435;&#x43d;&#x434;&#x438;&#x43d;&#x433;&#x430; (<?= count($orderRequests) ?>)</h3>
  <?php if (!$orderRequests): ?>
    <div class="empty-state">&#x41f;&#x43e;&#x43a;&#x430; &#x43d;&#x435;&#x442; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>&#x41a;&#x43e;&#x433;&#x434;&#x430;</th><th>&#x418;&#x43c;&#x44f;</th><th>&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;</th><th>&#x41e;&#x442;&#x43a;&#x443;&#x434;&#x430;</th><th>&#x41a;&#x443;&#x434;&#x430;</th><th>&#x412;&#x435;&#x441;</th><th>&#x41a;&#x43e;&#x43c;&#x43c;&#x435;&#x43d;&#x442;&#x430;&#x440;&#x438;&#x439;</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orderRequests as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['city'] ?? "\u{2014}") ?></td>
          <td><?= e($r['dest_city'] ?? "\u{2014}") ?></td>
          <td><?= e($r['weight'] ?? "\u{2014}") ?></td>
          <td><?= e($r['comment'] ?? '') ?></td>
          <td>
            <?php if (!empty($r['converted_order_id'])): ?>
              <a class="btn small secondary" href="/crm/order.php?id=<?= (int) $r['converted_order_id'] ?>">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x2116;<?= (int) $r['converted_order_id'] ?></a>
            <?php else: ?>
              <form method="post" class="inline">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="convert_order_request">
                <input type="hidden" name="lead_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small" type="submit">&#x421;&#x43e;&#x437;&#x434;&#x430;&#x442;&#x44c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; &#x432; CRM</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x43d;&#x430; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x447;&#x435;&#x441;&#x442;&#x432;&#x43e; (B2B) (<?= count($businessRequests) ?>)</h3>
  <?php if (!$businessRequests): ?>
    <div class="empty-state">&#x41f;&#x43e;&#x43a;&#x430; &#x43d;&#x435;&#x442; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>&#x41a;&#x43e;&#x433;&#x434;&#x430;</th><th>&#x41e;&#x440;&#x433;&#x430;&#x43d;&#x438;&#x437;&#x430;&#x446;&#x438;&#x44f;</th><th>&#x41a;&#x43e;&#x43d;&#x442;&#x430;&#x43a;&#x442;</th><th>&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;</th><th>Email</th><th>&#x41e;&#x431;&#x44a;&#x451;&#x43c;</th><th>&#x413;&#x43e;&#x440;&#x43e;&#x434;&#x430;</th></tr></thead>
      <tbody>
        <?php foreach ($businessRequests as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><?= e($r['company']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['email'] ?? "\u{2014}") ?></td>
          <td><?= e($r['volume'] ?? "\u{2014}") ?></td>
          <td><?= e($r['cities'] ?? "\u{2014}") ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
