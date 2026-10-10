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
        if ($newStatus === 'delivered' && !empty($_POST['signature_data'])
            && str_starts_with($_POST['signature_data'], 'data:image/png;base64,')) {
            $pdo->prepare('UPDATE orders SET status = ?, recipient_signature = ? WHERE id = ?')
                ->execute([$newStatus, $_POST['signature_data'], $orderId]);
        } else {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
        }
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?,?,?,?)')
            ->execute([$orderId, $newStatus, $user['id'], "\u{418}\u{437}\u{43c}\u{435}\u{43d}\u{435}\u{43d}\u{43e} \u{43a}\u{443}\u{440}\u{44c}\u{435}\u{440}\u{43e}\u{43c}"]);
        crm_notify_client_status($pdo, $ord, $newStatus);
        if ($newStatus === 'collecting' && !empty($_FILES['pickup_photos'])) {
            crm_save_order_photos($pdo, $orderId, 'pickup', $_FILES['pickup_photos'], $user['id']);
        }
        if ($newStatus === 'delivered' && !empty($_FILES['delivery_photos'])) {
            crm_save_order_photos($pdo, $orderId, 'delivery', $_FILES['delivery_photos'], $user['id']);
        }
        if ($newStatus === 'delivered') {
            crm_notify_owner("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $orderId . ' (' . $ord['from_city'] . " \u{2192} " . $ord['to_city'] . ") \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{430} \u{43a}\u{443}\u{440}\u{44c}\u{435}\u{440}\u{43e}\u{43c} \u{ab}" . $user['name'] . "\u{bb}.");
        }
        crm_flash_set("\u{421}\u{442}\u{430}\u{442}\u{443}\u{441} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{2116}" . $orderId . " \u{43e}\u{431}\u{43d}\u{43e}\u{432}\u{43b}\u{451}\u{43d}.");
    } else {
        crm_flash_set("\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{438}\u{437}\u{43c}\u{435}\u{43d}\u{438}\u{442}\u{44c} \u{441}\u{442}\u{430}\u{442}\u{443}\u{441}.", 'err');
    }
    crm_redirect('/crm/my-orders.php');
}

// &#x413;&#x435;&#x43e;&#x43f;&#x43e;&#x437;&#x438;&#x446;&#x438;&#x44f; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430; &#x434;&#x43b;&#x44f; &#x436;&#x438;&#x432;&#x43e;&#x439; &#x43a;&#x430;&#x440;&#x442;&#x44b; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435; (&#x432;&#x44b;&#x437;&#x44b;&#x432;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x444;&#x43e;&#x43d;&#x43e;&#x43c;, JS &#x43d;&#x438;&#x436;&#x435;).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_location') {
    crm_csrf_check();
    $lat = (float) ($_POST['lat'] ?? 0);
    $lng = (float) ($_POST['lng'] ?? 0);
    if ($lat !== 0.0 && $lng !== 0.0) {
        $pdo->prepare('INSERT INTO courier_locations (courier_id, lat, lng, updated_at) VALUES (?,?,?,NOW())
            ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), updated_at = NOW()')
            ->execute([$user['id'], $lat, $lng]);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

$stmt = $pdo->prepare("SELECT o.*, c.name AS client_name, c.phone AS client_phone
    FROM orders o JOIN clients c ON c.id = o.client_id
    WHERE o.courier_id = ? AND o.status NOT IN ('delivered','cancelled')
    ORDER BY o.planned_date IS NULL, o.planned_date, o.created_at");
$stmt->execute([$user['id']]);
$activeOrders = $stmt->fetchAll();
$hasInTransit = false;
foreach ($activeOrders as $o) {
    if ($o['status'] === 'in_transit') {
        $hasInTransit = true;
        break;
    }
}

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
  <h3 style="margin-top:0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>&#x422;&#x435;&#x43a;&#x443;&#x449;&#x438;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (<?= count($activeOrders) ?>)</span>
    <a class="btn small secondary" href="/crm/route-sheet.php" target="_blank">&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x43d;&#x44b;&#x439; &#x43b;&#x438;&#x441;&#x442; &#x43d;&#x430; &#x441;&#x435;&#x433;&#x43e;&#x434;&#x43d;&#x44f;</a>
  </h3>
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
            <form method="post" class="inline" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <?php if ($o['status'] === 'new'): ?>
                <input type="hidden" name="status" value="accepted">
                <button class="btn small" type="submit">&#x41f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x44c;</button>
              <?php elseif ($o['status'] === 'accepted'): ?>
                <input type="hidden" name="status" value="collecting">
                <input type="file" name="pickup_photos[]" accept="image/*" capture="environment" multiple style="max-width:130px;font-size:11px;" title="&#x424;&#x43e;&#x442;&#x43e; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43f;&#x440;&#x438; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x435;">
                <button class="btn small" type="submit">&#x41d;&#x430;&#x447;&#x430;&#x442;&#x44c; &#x441;&#x431;&#x43e;&#x440; &#x433;&#x440;&#x443;&#x437;&#x430;</button>
              <?php elseif ($o['status'] === 'collecting'): ?>
                <input type="hidden" name="status" value="in_transit">
                <button class="btn small" type="submit">&#x412; &#x43f;&#x443;&#x442;&#x438;</button>
              <?php elseif ($o['status'] === 'in_transit'): ?>
                <input type="hidden" name="status" value="delivered">
                <input type="hidden" name="signature_data" class="signature-data-input">
                <input type="file" name="delivery_photos[]" accept="image/*" capture="environment" multiple style="max-width:130px;font-size:11px;" title="&#x424;&#x43e;&#x442;&#x43e; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43f;&#x440;&#x438; &#x432;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x438;">
                <button class="btn small" type="button" onclick="crmOpenSignaturePad(this.closest('form'))">&#x414;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x43e;</button>
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

<div id="signature-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:8px;padding:16px;max-width:420px;width:92%;">
    <h3 style="margin-top:0;">&#x41f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f;</h3>
    <p class="text-muted" style="margin-top:-8px;">&#x41f;&#x43e;&#x43f;&#x440;&#x43e;&#x441;&#x438;&#x442;&#x435; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f; &#x43f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x430;&#x442;&#x44c;&#x441;&#x44f; &#x43f;&#x430;&#x43b;&#x44c;&#x446;&#x435;&#x43c; &#x438;&#x43b;&#x438; &#x441;&#x442;&#x438;&#x43b;&#x443;&#x441;&#x43e;&#x43c; &#x43d;&#x438;&#x436;&#x435;.</p>
    <canvas id="signature-canvas" width="360" height="180" style="border:1px solid #ccc;border-radius:6px;width:100%;touch-action:none;background:#fff;"></canvas>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
      <button type="button" class="btn small secondary" onclick="crmClearSignaturePad()">&#x41e;&#x447;&#x438;&#x441;&#x442;&#x438;&#x442;&#x44c;</button>
      <button type="button" class="btn small secondary" onclick="crmCloseSignaturePad()">&#x41e;&#x442;&#x43c;&#x435;&#x43d;&#x430;</button>
      <button type="button" class="btn small" onclick="crmConfirmSignaturePad()">&#x41f;&#x43e;&#x434;&#x442;&#x432;&#x435;&#x440;&#x434;&#x438;&#x442;&#x44c; &#x438; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x44c;</button>
    </div>
  </div>
</div>
<script>
(function () {
  var canvas = document.getElementById('signature-canvas');
  var ctx = canvas.getContext('2d');
  var drawing = false;
  var activeForm = null;

  function pos(evt) {
    var rect = canvas.getBoundingClientRect();
    var scaleX = canvas.width / rect.width;
    var scaleY = canvas.height / rect.height;
    var clientX = evt.touches ? evt.touches[0].clientX : evt.clientX;
    var clientY = evt.touches ? evt.touches[0].clientY : evt.clientY;
    return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
  }

  function start(evt) {
    drawing = true;
    var p = pos(evt);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    evt.preventDefault();
  }
  function move(evt) {
    if (!drawing) return;
    var p = pos(evt);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    evt.preventDefault();
  }
  function end() { drawing = false; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);

  window.crmOpenSignaturePad = function (form) {
    activeForm = form;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('signature-modal-overlay').style.display = 'flex';
  };
  window.crmClearSignaturePad = function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  };
  window.crmCloseSignaturePad = function () {
    document.getElementById('signature-modal-overlay').style.display = 'none';
    activeForm = null;
  };
  window.crmConfirmSignaturePad = function () {
    if (!activeForm) return;
    var dataUrl = canvas.toDataURL('image/png');
    var input = activeForm.querySelector('.signature-data-input');
    if (input) input.value = dataUrl;
    document.getElementById('signature-modal-overlay').style.display = 'none';
    activeForm.submit();
  };
})();
</script>

<?php if ($hasInTransit): ?>
<script>
(function () {
  var CRM_CSRF = <?= json_encode(crm_csrf_token()) ?>;
  var lastSent = 0;
  function sendLocation(lat, lng) {
    var now = Date.now();
    if (now - lastSent < 15000) return; // &#x43d;&#x435; &#x447;&#x430;&#x449;&#x435; &#x440;&#x430;&#x437;&#x430; &#x432; 15 &#x441;&#x435;&#x43a;&#x443;&#x43d;&#x434;
    lastSent = now;
    var body = new URLSearchParams();
    body.set('action', 'update_location');
    body.set('csrf', CRM_CSRF);
    body.set('lat', String(lat));
    body.set('lng', String(lng));
    fetch('/crm/my-orders.php', { method: 'POST', credentials: 'same-origin', body: body }).catch(function () {});
  }
  if ('geolocation' in navigator) {
    navigator.geolocation.watchPosition(
      function (pos) { sendLocation(pos.coords.latitude, pos.coords.longitude); },
      function () { /* &#x433;&#x435;&#x43e;&#x43b;&#x43e;&#x43a;&#x430;&#x446;&#x438;&#x44f; &#x43d;&#x435;&#x434;&#x43e;&#x441;&#x442;&#x443;&#x43f;&#x43d;&#x430;/&#x437;&#x430;&#x43f;&#x440;&#x435;&#x449;&#x435;&#x43d;&#x430; &#x2014; &#x43f;&#x440;&#x43e;&#x441;&#x442;&#x43e; &#x43d;&#x435; &#x448;&#x43b;&#x451;&#x43c; &#x43a;&#x43e;&#x43e;&#x440;&#x434;&#x438;&#x43d;&#x430;&#x442;&#x44b; */ },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 }
    );
  }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
