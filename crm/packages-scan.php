<?php
/**
 * &#x421;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x43e;&#x43c;&#x435;&#x441;&#x442; &#x441;&#x43a;&#x43b;&#x430;&#x434;&#x43e;&#x43c;/&#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x43e;&#x43c;: &#x440;&#x443;&#x447;&#x43d;&#x43e;&#x439; &#x432;&#x432;&#x43e;&#x434; &#x448;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434;&#x430; &#x438;&#x43b;&#x438;
 * &#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x435; &#x43a;&#x430;&#x43c;&#x435;&#x440;&#x43e;&#x439; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x430; (html5-qrcode &#x441; CDN). &#x41e;&#x431;&#x43d;&#x43e;&#x432;&#x43b;&#x44f;&#x435;&#x442; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;
 * &#x43a;&#x43e;&#x43d;&#x43a;&#x440;&#x435;&#x442;&#x43d;&#x43e;&#x433;&#x43e; order_packages &#x43f;&#x43e; &#x448;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434;&#x443; &#x432;&#x438;&#x434;&#x430; PSL<order_id>-<seq>.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator', 'courier']);
$pdo = crm_db();

$allowedStatuses = ['packed', 'loaded', 'delivered'];
$message = null;
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan') {
    crm_csrf_check();
    $barcode = trim((string) ($_POST['barcode'] ?? ''));
    $targetStatus = $_POST['target_status'] ?? '';

    if ($barcode === '') {
        $message = "\u{412}\u{432}\u{435}\u{434}\u{438}\u{442}\u{435} \u{438}\u{43b}\u{438} \u{43e}\u{442}\u{441}\u{43a}\u{430}\u{43d}\u{438}\u{440}\u{443}\u{439}\u{442}\u{435} \u{448}\u{442}\u{440}\u{438}\u{445}\u{43a}\u{43e}\u{434}.";
        $messageType = 'err';
    } elseif (!in_array($targetStatus, $allowedStatuses, true)) {
        $message = "\u{412}\u{44b}\u{431}\u{435}\u{440}\u{438}\u{442}\u{435} \u{441}\u{442}\u{430}\u{442}\u{443}\u{441}, \u{43a}\u{43e}\u{442}\u{43e}\u{440}\u{44b}\u{439} \u{43d}\u{443}\u{436}\u{43d}\u{43e} \u{43f}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{438}\u{442}\u{44c}.";
        $messageType = 'err';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM order_packages WHERE barcode = ?');
        $stmt->execute([$barcode]);
        $pkg = $stmt->fetch();
        if (!$pkg) {
            $message = "\u{413}\u{440}\u{443}\u{437}\u{43e}\u{43c}\u{435}\u{441}\u{442}\u{43e} \u{441}\u{43e} \u{448}\u{442}\u{440}\u{438}\u{445}\u{43a}\u{43e}\u{434}\u{43e}\u{43c} \"{$barcode}\" \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{43e}.";
            $messageType = 'err';
        } else {
            $pdo->prepare('UPDATE order_packages SET status = ?, scanned_at = NOW(), scanned_by = ? WHERE id = ?')
                ->execute([$targetStatus, $user['id'], $pkg['id']]);
            $message = "\u{41c}\u{435}\u{441}\u{442}\u{43e} {$barcode} (\u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$pkg['order_id']}): \u{441}\u{442}\u{430}\u{442}\u{443}\u{441} \u{43e}\u{431}\u{43d}\u{43e}\u{432}\u{43b}\u{451}\u{43d} \u{43d}\u{430} \u{ab}" . crm_package_status_label($targetStatus) . "\u{bb}.";
            $messageType = 'ok';
        }
    }
}

$recentStmt = $pdo->prepare('SELECT op.*, o.from_city, o.to_city FROM order_packages op
    JOIN orders o ON o.id = op.order_id
    WHERE op.scanned_by = ? ORDER BY op.scanned_at DESC LIMIT 20');
$recentStmt->execute([$user['id']]);
$recent = $recentStmt->fetchAll();

$pageTitle = "\u{421}\u{43a}\u{430}\u{43d}\u{438}\u{440}\u{43e}\u{432}\u{430}\u{43d}\u{438}\u{435} \u{43c}\u{435}\u{441}\u{442}";
$activeNav = 'packages-scan';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">&#x421;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x43e;&#x43c;&#x435;&#x441;&#x442;&#x430;</h3>

  <?php if ($message): ?>
    <div class="flash <?= $messageType === 'err' ? 'err' : 'ok' ?>" style="margin-bottom:12px;"><?= e($message) ?></div>
  <?php endif; ?>

  <form method="post" id="scan-form">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="scan">
    <div class="form-row" style="align-items:end;">
      <div>
        <label>&#x428;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434; &#x43c;&#x435;&#x441;&#x442;&#x430;</label>
        <input type="text" name="barcode" id="barcode-input" autofocus autocomplete="off" placeholder="PSL47-01">
      </div>
      <div>
        <label>&#x41d;&#x43e;&#x432;&#x44b;&#x439; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;</label>
        <select name="target_status">
          <option value="packed">&#x423;&#x43f;&#x430;&#x43a;&#x43e;&#x432;&#x430;&#x43d;&#x43e;</option>
          <option value="loaded" selected>&#x417;&#x430;&#x433;&#x440;&#x443;&#x436;&#x435;&#x43d;&#x43e; &#x432; &#x440;&#x435;&#x439;&#x441;</option>
          <option value="delivered">&#x412;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x43e;</option>
        </select>
      </div>
      <div class="form-actions"><button class="btn" type="submit">&#x41f;&#x440;&#x438;&#x43c;&#x435;&#x43d;&#x438;&#x442;&#x44c;</button></div>
    </div>
  </form>

  <div style="margin-top:16px;">
    <button type="button" class="btn secondary" id="camera-toggle-btn" onclick="crmToggleCamera()">&#x412;&#x43a;&#x43b;&#x44e;&#x447;&#x438;&#x442;&#x44c; &#x43a;&#x430;&#x43c;&#x435;&#x440;&#x443; &#x434;&#x43b;&#x44f; &#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x44f;</button>
    <div id="camera-reader" style="max-width:360px;margin-top:10px;display:none;"></div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x41f;&#x43e;&#x441;&#x43b;&#x435;&#x434;&#x43d;&#x438;&#x435; &#x43e;&#x442;&#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x43d;&#x44b;&#x435; &#x43c;&#x43d;&#x43e;&#x439; &#x43c;&#x435;&#x441;&#x442;&#x430;</h3>
  <?php if (!$recent): ?>
    <div class="empty-state">&#x41f;&#x43e;&#x43a;&#x430; &#x43d;&#x438;&#x447;&#x435;&#x433;&#x43e; &#x43d;&#x435; &#x43e;&#x442;&#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x43e;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>&#x428;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434;</th><th>&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x41a;&#x43e;&#x433;&#x434;&#x430;</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td><code><?= e($r['barcode']) ?></code></td>
          <td>&#x2116;<?= (int) $r['order_id'] ?> (<?= e($r['from_city']) ?> &#x2192; <?= e($r['to_city']) ?>)</td>
          <td><span class="badge <?= crm_package_status_class($r['status']) ?>"><?= e(crm_package_status_label($r['status'])) ?></span></td>
          <td><?= e(crm_date($r['scanned_at'], 'd.m.Y H:i')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
var crmHtml5QrCode = null;
var crmCameraOn = false;

function crmToggleCamera() {
  var box = document.getElementById('camera-reader');
  var btn = document.getElementById('camera-toggle-btn');
  if (crmCameraOn) {
    if (crmHtml5QrCode) {
      crmHtml5QrCode.stop().catch(function () {});
    }
    box.style.display = 'none';
    btn.textContent = '&#x412;&#x43a;&#x43b;&#x44e;&#x447;&#x438;&#x442;&#x44c; &#x43a;&#x430;&#x43c;&#x435;&#x440;&#x443; &#x434;&#x43b;&#x44f; &#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x44f;';
    crmCameraOn = false;
    return;
  }
  box.style.display = 'block';
  btn.textContent = '&#x412;&#x44b;&#x43a;&#x43b;&#x44e;&#x447;&#x438;&#x442;&#x44c; &#x43a;&#x430;&#x43c;&#x435;&#x440;&#x443;';
  crmCameraOn = true;
  crmHtml5QrCode = new Html5Qrcode('camera-reader');
  crmHtml5QrCode.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: 220 },
    function (decodedText) {
      document.getElementById('barcode-input').value = decodedText;
      document.getElementById('scan-form').submit();
    },
    function () { /* ignore per-frame scan failures */ }
  ).catch(function (err) {
    alert('&#x41d;&#x435; &#x443;&#x434;&#x430;&#x43b;&#x43e;&#x441;&#x44c; &#x432;&#x43a;&#x43b;&#x44e;&#x447;&#x438;&#x442;&#x44c; &#x43a;&#x430;&#x43c;&#x435;&#x440;&#x443;: ' + err);
    box.style.display = 'none';
    btn.textContent = '&#x412;&#x43a;&#x43b;&#x44e;&#x447;&#x438;&#x442;&#x44c; &#x43a;&#x430;&#x43c;&#x435;&#x440;&#x443; &#x434;&#x43b;&#x44f; &#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x44f;';
    crmCameraOn = false;
  });
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
