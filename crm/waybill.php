<?php
/**
 * &#x41f;&#x435;&#x447;&#x430;&#x442;&#x43d;&#x430;&#x44f; &#x43d;&#x430;&#x43a;&#x43b;&#x430;&#x434;&#x43d;&#x430;&#x44f; &#x43d;&#x430; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x435; + &#x43a;&#x432;&#x438;&#x442;&#x430;&#x43d;&#x446;&#x438;&#x44f; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430;, &#x430;&#x432;&#x442;&#x43e;&#x437;&#x430;&#x43f;&#x43e;&#x43b;&#x43d;&#x44f;&#x435;&#x442;&#x441;&#x44f;
 * &#x434;&#x430;&#x43d;&#x43d;&#x44b;&#x43c;&#x438; &#x43a;&#x43e;&#x43d;&#x43a;&#x440;&#x435;&#x442;&#x43d;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (orders + clients). &#x41e;&#x442;&#x43a;&#x440;&#x44b;&#x432;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x438;&#x437; &#x43a;&#x430;&#x440;&#x442;&#x43e;&#x447;&#x43a;&#x438;
 * &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (order.php) &#x43a;&#x43d;&#x43e;&#x43f;&#x43a;&#x43e;&#x439; &#xab;&#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x43d;&#x430;&#x43a;&#x43b;&#x430;&#x434;&#x43d;&#x43e;&#x439;&#xbb;, &#x441;&#x430;&#x43c;&#x430; &#x43f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x2014; &#x448;&#x442;&#x430;&#x442;&#x43d;&#x44b;&#x43c;
 * window.print() &#x431;&#x440;&#x430;&#x443;&#x437;&#x435;&#x440;&#x430;, &#x43e;&#x442;&#x434;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x439; PDF-&#x444;&#x430;&#x439;&#x43b; &#x433;&#x435;&#x43d;&#x435;&#x440;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x442;&#x44c; &#x43d;&#x435; &#x43d;&#x443;&#x436;&#x43d;&#x43e;.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/clientapi.php';
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT o.*, c.name AS client_name, c.phone AS client_phone
    FROM orders o JOIN clients c ON c.id = o.client_id WHERE o.id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    die("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{430}.");
}

// &#x41d;&#x430;&#x43a;&#x43b;&#x430;&#x434;&#x43d;&#x443;&#x44e; &#x43c;&#x43e;&#x436;&#x435;&#x442; &#x43e;&#x442;&#x43a;&#x440;&#x44b;&#x442;&#x44c; &#x43b;&#x438;&#x431;&#x43e; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a; (&#x43a;&#x430;&#x43a; &#x440;&#x430;&#x43d;&#x44c;&#x448;&#x435;), &#x43b;&#x438;&#x431;&#x43e; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442; &#x438;&#x437;
// &#x43b;&#x438;&#x447;&#x43d;&#x43e;&#x433;&#x43e; &#x43a;&#x430;&#x431;&#x438;&#x43d;&#x435;&#x442;&#x430; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435; &#x2014; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x43f;&#x43e; &#x441;&#x432;&#x43e;&#x435;&#x439; &#x441;&#x43e;&#x431;&#x441;&#x442;&#x432;&#x435;&#x43d;&#x43d;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435;.
$employee = crm_current_user();
if ($employee && !in_array($employee['role'], ['admin', 'operator', 'courier'], true)) {
    $employee = null;
}
if (!$employee) {
    $client = capi_current_client($pdo);
    if (!$client || (int) $client['id'] !== (int) $order['client_id']) {
        http_response_code(403);
        die("\u{414}\u{43e}\u{441}\u{442}\u{443}\u{43f} \u{437}\u{430}\u{43f}\u{440}\u{435}\u{449}\u{451}\u{43d}.");
    }
}

$trackLabel = $order['track_code'] ?: ("\u{2116}" . $order['id']);
$service = $order['service'] ?? '';

function wb_check(bool $on): string
{
    return $on ? "\u{2611}" : "\u{2610}";
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>&#x41d;&#x430;&#x43a;&#x43b;&#x430;&#x434;&#x43d;&#x430;&#x44f; &#x2014; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x2116;<?= (int) $order['id'] ?></title>
<style>
  :root{--brand:#0a2472;}
  *{box-sizing:border-box;}
  body{font-family:Arial,Helvetica,sans-serif;color:#1c2438;max-width:780px;margin:24px auto;padding:0 16px;font-size:.88rem;}
  .no-print{margin-bottom:16px;}
  .no-print button{background:var(--brand);color:#fff;border:none;border-radius:8px;padding:10px 18px;font-size:.9rem;font-weight:700;cursor:pointer;}
  .sheet{border:1px solid #c9d0e3;border-radius:10px;padding:18px 20px;margin-bottom:22px;}
  .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--brand);padding-bottom:10px;margin-bottom:10px;}
  .head .brand{font-size:1.15rem;font-weight:800;color:var(--brand);}
  .head .tagline{font-size:.78rem;color:#6b7490;}
  .head .contacts{text-align:right;font-size:.8rem;color:#444;}
  .title-row{display:flex;justify-content:space-between;align-items:center;margin:10px 0 14px;}
  .title-row h1{font-size:1.05rem;margin:0;}
  .track-box{border:2px solid var(--brand);border-radius:6px;padding:6px 14px;text-align:center;}
  .track-box .lbl{font-size:.68rem;color:#6b7490;text-transform:uppercase;letter-spacing:.04em;}
  .track-box .code{font-size:1.1rem;font-weight:800;color:var(--brand);}
  .track-box svg{display:block;margin-top:4px;}
  .meta-row{display:flex;gap:24px;margin-bottom:14px;font-size:.84rem;}
  .meta-row div{flex:1;}
  .meta-row .lbl{color:#6b7490;font-size:.74rem;}
  .two-col{display:flex;gap:24px;margin-bottom:14px;}
  .two-col .col{flex:1;border:1px solid #e3e7f1;border-radius:8px;padding:10px 12px;}
  .two-col .col h4{margin:0 0 8px;font-size:.8rem;color:var(--brand);text-transform:uppercase;letter-spacing:.03em;}
  .two-col .field{margin-bottom:8px;}
  .two-col .field .lbl{font-size:.72rem;color:#6b7490;}
  .two-col .field .val{font-size:.9rem;border-bottom:1px solid #c9d0e3;min-height:1.3em;padding-bottom:2px;}
  .cargo-types{display:flex;flex-wrap:wrap;gap:14px;margin:10px 0;font-size:.85rem;}
  .cargo-grid{display:grid;grid-template-columns:1fr 1fr 2fr;gap:14px;margin-bottom:14px;}
  .cargo-grid .box{border:1px solid #e3e7f1;border-radius:8px;padding:10px 12px;}
  .cargo-grid .lbl{font-size:.72rem;color:#6b7490;}
  .cargo-grid .val{font-size:1rem;font-weight:700;}
  .addons{font-size:.8rem;margin-bottom:14px;line-height:1.7;}
  .cost-table{width:100%;border-collapse:collapse;margin-bottom:12px;}
  .cost-table td{padding:6px 4px;font-size:.88rem;border-bottom:1px dashed #d7dcea;}
  .cost-table td:last-child{text-align:right;font-weight:700;width:140px;}
  .cost-table tr.total td{font-size:1rem;border-bottom:none;border-top:2px solid var(--brand);padding-top:8px;}
  .pay-row{font-size:.85rem;margin-bottom:16px;}
  .sign-row{display:flex;justify-content:space-between;margin-top:26px;font-size:.78rem;color:#444;}
  .sign-row div{flex:1;border-top:1px solid #999;padding-top:4px;margin-right:16px;}
  .sign-row div:last-child{margin-right:0;}
  .cut{text-align:center;color:#999;font-size:.75rem;margin:4px 0 18px;border-top:1px dashed #bbb;padding-top:6px;}
  .receipt{font-size:.84rem;}
  .receipt .grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;margin:10px 0;}
  .receipt .lbl{color:#6b7490;font-size:.72rem;}
  .receipt .val{border-bottom:1px solid #c9d0e3;min-height:1.3em;}
  .receipt .note{font-size:.78rem;color:#555;margin-top:10px;line-height:1.5;}
  .footer-note{text-align:center;font-size:.72rem;color:#8a92ab;margin-top:6px;}
  @media print{
    .no-print{display:none;}
    body{margin:0;max-width:none;}
    .sheet{border:none;border-radius:0;page-break-inside:avoid;}
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#x1f5a8; &#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c;</button>
  <a href="/crm/order.php?id=<?= (int) $order['id'] ?>" style="margin-left:12px;">&#x2190; &#x41d;&#x430;&#x437;&#x430;&#x434; &#x43a; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435;</a>
</div>

<div class="sheet">
  <div class="head">
    <div>
      <div class="brand">&#x41f;&#x41e;&#x421;&#x42b;&#x41b;&#x41e;&#x427;&#x41a;&#x410;</div>
      <div class="tagline">&#x414;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x430; &#x441; &#x442;&#x435;&#x43c;&#x43f;&#x435;&#x440;&#x430;&#x442;&#x443;&#x440;&#x43d;&#x44b;&#x43c; &#x440;&#x435;&#x436;&#x438;&#x43c;&#x43e;&#x43c; &#xb7; &#x414;&#x435;&#x440;&#x431;&#x435;&#x43d;&#x442; &#x2014; &#x421;&#x430;&#x43d;&#x43a;&#x442;-&#x41f;&#x435;&#x442;&#x435;&#x440;&#x431;&#x443;&#x440;&#x433;, &#x41c;&#x43e;&#x441;&#x43a;&#x432;&#x430;, &#x432;&#x441;&#x44f; &#x420;&#x43e;&#x441;&#x441;&#x438;&#x44f;</div>
    </div>
    <div class="contacts">
      &#x418;&#x41f; &#x41a;&#x430;&#x437;&#x430;&#x447;&#x435;&#x43d;&#x43a;&#x43e; &#x41d;&#x430;&#x442;&#x430;&#x43b;&#x438;&#x44f; &#x41d;&#x438;&#x43a;&#x43e;&#x43b;&#x430;&#x435;&#x432;&#x43d;&#x430;<br>
      &#x422;&#x435;&#x43b;.: +7 (812) 711-18-19<br>
      &#x43c;&#x43e;&#x44f;-&#x43f;&#x43e;&#x441;&#x44b;&#x43b;&#x43e;&#x447;&#x43a;&#x430;.&#x440;&#x444;
    </div>
  </div>

  <div class="title-row">
    <h1>&#x41d;&#x410;&#x41a;&#x41b;&#x410;&#x414;&#x41d;&#x410;&#x42f; &#x41d;&#x410; &#x41e;&#x422;&#x41f;&#x420;&#x410;&#x412;&#x41b;&#x415;&#x41d;&#x418;&#x415;</h1>
    <div class="track-box">
      <div class="lbl">&#x422;&#x440;&#x435;&#x43a;-&#x43d;&#x43e;&#x43c;&#x435;&#x440;</div>
      <div class="code"><?= e($trackLabel) ?></div>
      <svg class="barcode" data-code="<?= e($trackLabel) ?>"></svg>
    </div>
  </div>

  <div class="meta-row">
    <div><div class="lbl">&#x414;&#x430;&#x442;&#x430; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x430;</div><?= crm_date($order['created_at'], 'd.m.Y') ?></div>
    <div><div class="lbl">&#x41f;&#x443;&#x43d;&#x43a;&#x442; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x430;</div>&nbsp;</div>
    <div><div class="lbl">&#x41f;&#x440;&#x438;&#x43d;&#x44f;&#x43b; (&#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;)</div><?= e($employee['name'] ?? '') ?></div>
  </div>

  <div class="two-col">
    <div class="col">
      <h4>&#x41e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44c;</h4>
      <div class="field"><div class="lbl">&#x424;&#x418;&#x41e;</div><div class="val"><?= e($order['client_name']) ?></div></div>
      <div class="field"><div class="lbl">&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;</div><div class="val"><?= e($order['client_phone']) ?></div></div>
      <div class="field"><div class="lbl">&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</div><div class="val"><?= e($order['from_city']) ?><?= $order['from_address'] ? ', ' . e($order['from_address']) : '' ?></div></div>
    </div>
    <div class="col">
      <h4>&#x41f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44c;</h4>
      <div class="field"><div class="lbl">&#x424;&#x418;&#x41e;</div><div class="val">&nbsp;</div></div>
      <div class="field"><div class="lbl">&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;</div><div class="val">&nbsp;</div></div>
      <div class="field"><div class="lbl">&#x413;&#x43e;&#x440;&#x43e;&#x434; / &#x430;&#x434;&#x440;&#x435;&#x441; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</div><div class="val"><?= e($order['to_city']) ?><?= $order['to_address'] ? ', ' . e($order['to_address']) : '' ?></div></div>
    </div>
  </div>

  <div class="cargo-types">
    <span><?= wb_check($service === '') ?> &#x424;&#x438;&#x437;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x43e;&#x435; &#x43b;&#x438;&#x446;&#x43e;</span>
    <span><?= wb_check($service === "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43a}\u{430}") ?> &#x41f;&#x43e;&#x441;&#x44b;&#x43b;&#x43a;&#x430;</span>
    <span><?= wb_check($service === "\u{41f}\u{440}\u{43e}\u{434}\u{443}\u{43a}\u{442}\u{44b}") ?> &#x41f;&#x440;&#x43e;&#x434;&#x443;&#x43a;&#x442;&#x44b; &#x43f;&#x438;&#x442;&#x430;&#x43d;&#x438;&#x44f;</span>
    <span><?= wb_check($service === 'B2B') ?> &#x414;&#x43b;&#x44f; &#x44e;&#x440;. &#x43b;&#x438;&#x446; (B2B)</span>
  </div>

  <div class="cargo-grid">
    <div class="box"><div class="lbl">&#x412;&#x435;&#x441;, &#x43a;&#x433;</div><div class="val"><?= $order['weight_kg'] !== null ? e(rtrim(rtrim(number_format((float)$order['weight_kg'], 2, '.', ''), '0'), '.')) : "\u{2014}" ?></div></div>
    <div class="box"><div class="lbl">&#x41e;&#x431;&#x44a;&#x44f;&#x432;&#x43b;&#x435;&#x43d;&#x43d;&#x430;&#x44f; &#x446;&#x435;&#x43d;&#x43d;&#x43e;&#x441;&#x442;&#x44c;, &#x20bd;</div><div class="val"><?= $order['declared_value'] !== null ? crm_money((float) $order['declared_value']) : "\u{2014}" ?></div></div>
    <div class="box"><div class="lbl">&#x41e;&#x43f;&#x438;&#x441;&#x430;&#x43d;&#x438;&#x435; &#x441;&#x43e;&#x434;&#x435;&#x440;&#x436;&#x438;&#x43c;&#x43e;&#x433;&#x43e;</div><div class="val" style="font-weight:400;font-size:.88rem;"><?= e($order['cargo_description']) ?: '&nbsp;' ?></div></div>
  </div>

  <div class="addons">
    &#x2610; &#x422;&#x435;&#x440;&#x43c;&#x43e;&#x443;&#x43f;&#x430;&#x43a;&#x43e;&#x432;&#x43a;&#x430; / &#x442;&#x435;&#x440;&#x43c;&#x43e;&#x431;&#x43e;&#x43a;&#x441; &nbsp;&nbsp; &#x2610; &#x425;&#x440;&#x443;&#x43f;&#x43a;&#x438;&#x439; &#x433;&#x440;&#x443;&#x437; &nbsp;&nbsp; &#x2610; &#x41e;&#x43f;&#x438;&#x441;&#x44c; &#x434;&#x43e;&#x43a;&#x443;&#x43c;&#x435;&#x43d;&#x442;&#x43e;&#x432; / &#x432;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x438;&#x439;<br>
    &#x2610; SMS-&#x443;&#x432;&#x435;&#x434;&#x43e;&#x43c;&#x43b;&#x435;&#x43d;&#x438;&#x44f; &#x43e; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x435; &nbsp;&nbsp; &#x2610; &#x421;&#x442;&#x440;&#x430;&#x445;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x430;
  </div>

  <table class="cost-table">
    <tr><td>&#x421;&#x442;&#x43e;&#x438;&#x43c;&#x43e;&#x441;&#x442;&#x44c; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</td><td><?= $order['price'] !== null ? crm_money((float) $order['price']) : "______________ \u{20bd}" ?></td></tr>
    <tr><td>&#x414;&#x43e;&#x43f;&#x43e;&#x43b;&#x43d;&#x438;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x435; &#x443;&#x441;&#x43b;&#x443;&#x433;&#x438;</td><td>______________ &#x20bd;</td></tr>
    <tr class="total"><td>&#x418;&#x442;&#x43e;&#x433;&#x43e; &#x43a; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x435;</td><td><?= $order['price'] !== null ? crm_money((float) $order['price']) : "______________ \u{20bd}" ?></td></tr>
  </table>

  <div class="pay-row">
    <?= wb_check($order['payment_method'] === 'cash') ?> &#x41d;&#x430;&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x439; &#x440;&#x430;&#x441;&#x447;&#x451;&#x442; &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'terminal') ?> &#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x447;&#x435;&#x440;&#x435;&#x437; &#x442;&#x435;&#x440;&#x43c;&#x438;&#x43d;&#x430;&#x43b; &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'invoice') ?> &#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x43f;&#x43e; &#x441;&#x447;&#x451;&#x442;&#x443; &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'online') ?> &#x41e;&#x43d;&#x43b;&#x430;&#x439;&#x43d; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435; &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'installment') ?> &#x420;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x430;<br>
    <?= wb_check($order['payment_status'] === 'postpaid') ?> &#x41f;&#x43e;&#x441;&#x442;&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x430; (&#x43f;&#x43e;&#x441;&#x43b;&#x435; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f; &#x433;&#x440;&#x443;&#x437;&#x430;)
  </div>

  <div class="sign-row">
    <div>&#x41f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f; &#x2014; &#x433;&#x440;&#x443;&#x437; &#x438; &#x443;&#x441;&#x43b;&#x43e;&#x432;&#x438;&#x44f; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438; &#x43f;&#x43e;&#x434;&#x442;&#x432;&#x435;&#x440;&#x436;&#x434;&#x430;&#x44e;</div>
    <div>&#x41f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x430; &#x43f;&#x443;&#x43d;&#x43a;&#x442;&#x430; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x430; / &#x43f;&#x435;&#x447;&#x430;&#x442;&#x44c;</div>
  </div>
</div>

<div class="cut">&#x2702; &#x43b;&#x438;&#x43d;&#x438;&#x44f; &#x43e;&#x442;&#x440;&#x44b;&#x432;&#x430; &#x2014; &#x43a;&#x432;&#x438;&#x442;&#x430;&#x43d;&#x446;&#x438;&#x44f; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x43d;&#x438;&#x436;&#x435;</div>

<div class="sheet receipt">
  <div class="title-row">
    <h1 style="font-size:.95rem;">&#x41a;&#x412;&#x418;&#x422;&#x410;&#x41d;&#x426;&#x418;&#x42f; &#x41e; &#x41f;&#x420;&#x418;&#x401;&#x41c;&#x415; &#x413;&#x420;&#x423;&#x417;&#x410;</h1>
    <div class="track-box">
      <div class="lbl">&#x422;&#x440;&#x435;&#x43a;-&#x43d;&#x43e;&#x43c;&#x435;&#x440;</div>
      <div class="code"><?= e($trackLabel) ?></div>
      <svg class="barcode" data-code="<?= e($trackLabel) ?>"></svg>
    </div>
  </div>
  <div class="grid">
    <div><div class="lbl">&#x414;&#x430;&#x442;&#x430; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x430;</div><div class="val"><?= crm_date($order['created_at'], 'd.m.Y') ?></div></div>
    <div><div class="lbl">&#x41c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;</div><div class="val"><?= e($order['from_city']) ?> &#x2192; <?= e($order['to_city']) ?></div></div>
    <div><div class="lbl">&#x41e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44c;</div><div class="val"><?= e($order['client_name']) ?></div></div>
    <div><div class="lbl">&#x412;&#x435;&#x441;, &#x43a;&#x433;</div><div class="val"><?= $order['weight_kg'] !== null ? e($order['weight_kg']) : "\u{2014}" ?></div></div>
    <div><div class="lbl">&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;</div><div class="val"><?= e($order['client_phone']) ?></div></div>
    <div><div class="lbl">&#x418;&#x442;&#x43e;&#x433;&#x43e; &#x43a; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x435;</div><div class="val"><?= $order['price'] !== null ? crm_money((float) $order['price']) : "\u{2014}" ?></div></div>
  </div>
  <div class="note">
    &#x421;&#x43e;&#x445;&#x440;&#x430;&#x43d;&#x438;&#x442;&#x435; &#x44d;&#x442;&#x443; &#x43a;&#x432;&#x438;&#x442;&#x430;&#x43d;&#x446;&#x438;&#x44e; &#x434;&#x43e; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f; &#x433;&#x440;&#x443;&#x437;&#x430;. &#x41e;&#x442;&#x441;&#x43b;&#x435;&#x434;&#x438;&#x442;&#x44c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f; &#x2014; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435; &#x43c;&#x43e;&#x44f;-&#x43f;&#x43e;&#x441;&#x44b;&#x43b;&#x43e;&#x447;&#x43a;&#x430;.&#x440;&#x444; &#x432; &#x440;&#x430;&#x437;&#x434;&#x435;&#x43b;&#x435; &#xab;&#x41e;&#x442;&#x441;&#x43b;&#x435;&#x434;&#x438;&#x442;&#x44c;&#xbb;, &#x443;&#x43a;&#x430;&#x437;&#x430;&#x432; &#x442;&#x440;&#x435;&#x43a;-&#x43d;&#x43e;&#x43c;&#x435;&#x440;, &#x43b;&#x438;&#x431;&#x43e; &#x432; &#x43c;&#x43e;&#x431;&#x438;&#x43b;&#x44c;&#x43d;&#x43e;&#x43c; &#x43f;&#x440;&#x438;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x438;&#x438; &#xab;&#x41f;&#x43e;&#x441;&#x44b;&#x43b;&#x43e;&#x447;&#x43a;&#x430;&#xbb;. &#x412;&#x43e;&#x43f;&#x440;&#x43e;&#x441;&#x44b; &#x43f;&#x43e; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x435; &#x2014; &#x43f;&#x43e; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x443; +7 (812) 711-18-19.
  </div>
</div>

<div class="footer-note">&#x41f;&#x41e;&#x421;&#x42b;&#x41b;&#x41e;&#x427;&#x41a;&#x410; &#xb7; &#x418;&#x41f; &#x41a;&#x430;&#x437;&#x430;&#x447;&#x435;&#x43d;&#x43a;&#x43e; &#x41d;&#x430;&#x442;&#x430;&#x43b;&#x438;&#x44f; &#x41d;&#x438;&#x43a;&#x43e;&#x43b;&#x430;&#x435;&#x432;&#x43d;&#x430; &#xb7; &#x43c;&#x43e;&#x44f;-&#x43f;&#x43e;&#x441;&#x44b;&#x43b;&#x43e;&#x447;&#x43a;&#x430;.&#x440;&#x444; &#xb7; &#x414;&#x43e;&#x43a;&#x443;&#x43c;&#x435;&#x43d;&#x442; &#x44f;&#x432;&#x43b;&#x44f;&#x435;&#x442;&#x441;&#x44f; &#x43f;&#x43e;&#x434;&#x442;&#x432;&#x435;&#x440;&#x436;&#x434;&#x435;&#x43d;&#x438;&#x435;&#x43c; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x430; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43a; &#x43f;&#x435;&#x440;&#x435;&#x432;&#x43e;&#x437;&#x43a;&#x435;</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.querySelectorAll('svg.barcode').forEach(function (el) {
  var code = el.getAttribute('data-code') || '';
  if (!code || typeof JsBarcode === 'undefined') return;
  try {
    JsBarcode(el, code, {
      format: 'CODE128',
      width: 1.6,
      height: 36,
      displayValue: false,
      margin: 0
    });
  } catch (e) { /* &#x435;&#x441;&#x43b;&#x438; &#x43a;&#x43e;&#x434; &#x441;&#x43e;&#x434;&#x435;&#x440;&#x436;&#x438;&#x442; &#x441;&#x438;&#x43c;&#x432;&#x43e;&#x43b;&#x44b;, &#x43d;&#x435; &#x43f;&#x43e;&#x434;&#x445;&#x43e;&#x434;&#x44f;&#x449;&#x438;&#x435; CODE128, &#x43f;&#x440;&#x43e;&#x441;&#x442;&#x43e; &#x43d;&#x435; &#x440;&#x438;&#x441;&#x443;&#x435;&#x43c; &#x448;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434; */ }
});
</script>

</body>
</html>
