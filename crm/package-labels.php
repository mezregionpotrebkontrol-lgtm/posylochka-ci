<?php
/**
 * &#x41f;&#x435;&#x447;&#x430;&#x442;&#x43d;&#x44b;&#x435; &#x44d;&#x442;&#x438;&#x43a;&#x435;&#x442;&#x43a;&#x438; &#x433;&#x440;&#x443;&#x437;&#x43e;&#x43c;&#x435;&#x441;&#x442; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;: &#x43f;&#x43e; &#x43e;&#x434;&#x43d;&#x43e;&#x439; &#x43d;&#x430; &#x43a;&#x430;&#x436;&#x434;&#x43e;&#x435; &#x43c;&#x435;&#x441;&#x442;&#x43e;, &#x441;&#x43e;
 * &#x448;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434;&#x43e;&#x43c; (QR-&#x43a;&#x43e;&#x434;, &#x433;&#x435;&#x43d;&#x435;&#x440;&#x438;&#x440;&#x443;&#x435;&#x442;&#x441;&#x44f; &#x432; &#x431;&#x440;&#x430;&#x443;&#x437;&#x435;&#x440;&#x435; &#x447;&#x435;&#x440;&#x435;&#x437; qrcode.js &#x441; CDN,
 * &#x431;&#x435;&#x437; &#x43e;&#x431;&#x440;&#x430;&#x449;&#x435;&#x43d;&#x438;&#x44f; PHP-&#x441;&#x435;&#x440;&#x432;&#x435;&#x440;&#x430; &#x43a; &#x432;&#x43d;&#x435;&#x448;&#x43d;&#x438;&#x43c; &#x441;&#x435;&#x442;&#x44f;&#x43c;) &#x438; &#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x432;&#x43e;&#x439; &#x438;&#x43d;&#x444;&#x43e;&#x440;&#x43c;&#x430;&#x446;&#x438;&#x435;&#x439;
 * &#x43f;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435;. &#x41e;&#x442;&#x43a;&#x440;&#x44b;&#x432;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x438;&#x437; order.php &#x43a;&#x43d;&#x43e;&#x43f;&#x43a;&#x43e;&#x439; &#xab;&#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x44d;&#x442;&#x438;&#x43a;&#x435;&#x442;&#x43e;&#x43a;&#xbb;.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$orderId = (int) ($_GET['order_id'] ?? 0);
$stmt = $pdo->prepare('SELECT o.*, c.name AS client_name FROM orders o JOIN clients c ON c.id = o.client_id WHERE o.id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    die("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{430}.");
}

crm_ensure_order_packages($pdo, $orderId, max(1, (int) $order['places_count']));

$pkgStmt = $pdo->prepare('SELECT * FROM order_packages WHERE order_id = ? ORDER BY seq');
$pkgStmt->execute([$orderId]);
$packages = $pkgStmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>&#x42d;&#x442;&#x438;&#x43a;&#x435;&#x442;&#x43a;&#x438; &#x2014; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x2116;<?= (int) $orderId ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  :root{--brand:#0a2472;}
  *{box-sizing:border-box;}
  body{font-family:Arial,Helvetica,sans-serif;color:#1c2438;margin:16px;font-size:.88rem;}
  .no-print{margin-bottom:16px;}
  .no-print button{background:var(--brand);color:#fff;border:none;border-radius:8px;padding:10px 18px;font-size:.9rem;font-weight:700;cursor:pointer;}
  .labels{display:flex;flex-wrap:wrap;gap:14px;}
  .label{width:300px;border:2px solid var(--brand);border-radius:10px;padding:12px 14px;page-break-inside:avoid;}
  .label .company{font-weight:800;color:var(--brand);font-size:.95rem;margin-bottom:6px;}
  .label .row{margin-bottom:4px;}
  .label .lbl{color:#6b7490;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;}
  .label .val{font-size:.95rem;font-weight:600;}
  .label .qr{display:flex;justify-content:center;margin:10px 0;}
  .label .barcode-text{text-align:center;font-weight:800;font-size:1.05rem;letter-spacing:.03em;}
  .label .seq{text-align:center;color:#6b7490;font-size:.78rem;}
  @media print { .no-print{display:none;} }
</style>
</head>
<body>

<div class="no-print">
  <button type="button" onclick="window.print()">&#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x432;&#x441;&#x435;&#x445; &#x44d;&#x442;&#x438;&#x43a;&#x435;&#x442;&#x43e;&#x43a;</button>
</div>

<div class="labels">
  <?php foreach ($packages as $pkg): ?>
  <div class="label">
    <div class="company"><?= e(crm_config()['company_name'] ?? "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}") ?></div>
    <div class="row"><span class="lbl">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430;</span><br><span class="val">&#x2116;<?= (int) $orderId ?> &#x2014; <?= e($order['from_city']) ?> &#x2192; <?= e($order['to_city']) ?></span></div>
    <div class="row"><span class="lbl">&#x41f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44c;</span><br><span class="val"><?= e($order['client_name']) ?></span></div>
    <div class="qr" id="qr-<?= (int) $pkg['id'] ?>"></div>
    <div class="barcode-text"><?= e($pkg['barcode']) ?></div>
    <div class="seq">&#x41c;&#x435;&#x441;&#x442;&#x43e; <?= (int) $pkg['seq'] ?> &#x438;&#x437; <?= (int) count($packages) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<script>
<?php foreach ($packages as $pkg): ?>
new QRCode(document.getElementById('qr-<?= (int) $pkg['id'] ?>'), {
  text: <?= json_encode($pkg['barcode'], JSON_UNESCAPED_UNICODE) ?>,
  width: 140,
  height: 140,
});
<?php endforeach; ?>
</script>

</body>
</html>
