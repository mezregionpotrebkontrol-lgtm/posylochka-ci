<?php
/**
 * &#x41f;&#x440;&#x438;&#x451;&#x43c; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x43e;&#x43c; &#x43d;&#x430; &#x43c;&#x435;&#x441;&#x442;&#x435; &#x443; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f; &#x2014; &#x443;&#x43f;&#x440;&#x43e;&#x449;&#x451;&#x43d;&#x43d;&#x44b;&#x439;
 * &#x440;&#x435;&#x436;&#x438;&#x43c; &#x434;&#x43b;&#x44f; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x43e;&#x432;, &#x43f;&#x43e; &#x430;&#x43d;&#x430;&#x43b;&#x43e;&#x433;&#x438;&#x438; &#x441; crm/reception.php (&#x442;&#x430;&#x431; &#xab;&#x41f;&#x440;&#x438;&#x451;&#x43c;&#xbb; &#x434;&#x43b;&#x44f; &#x43e;&#x43f;&#x435;&#x440;&#x430;&#x442;&#x43e;&#x440;&#x43e;&#x432;).
 * &#x414;&#x43b;&#x44f; &#x441;&#x43b;&#x443;&#x447;&#x430;&#x435;&#x432;, &#x43a;&#x43e;&#x433;&#x434;&#x430; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440; &#x437;&#x430;&#x431;&#x438;&#x440;&#x430;&#x435;&#x442; &#x433;&#x440;&#x443;&#x437; &#x443; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x43d;&#x430;&#x43f;&#x440;&#x44f;&#x43c;&#x443;&#x44e; &#x43d;&#x430; &#x43c;&#x435;&#x441;&#x442;&#x435;,
 * &#x431;&#x435;&#x437; &#x437;&#x430;&#x440;&#x430;&#x43d;&#x435;&#x435; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x43d;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x432; CRM. &#x421;&#x442;&#x43e;&#x438;&#x43c;&#x43e;&#x441;&#x442;&#x44c; &#x441;&#x447;&#x438;&#x442;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x442;&#x43e;&#x439; &#x436;&#x435; &#x444;&#x43e;&#x440;&#x43c;&#x443;&#x43b;&#x43e;&#x439;,
 * &#x447;&#x442;&#x43e; &#x438; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435; (includes/pricing-formula.php). &#x421;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x43d;&#x430;&#x44f; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x441;&#x440;&#x430;&#x437;&#x443; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x430;&#x435;&#x442;&#x441;&#x44f;
 * &#x44d;&#x442;&#x43e;&#x43c;&#x443; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x443; &#x438; &#x43f;&#x435;&#x440;&#x435;&#x445;&#x43e;&#x434;&#x438;&#x442; &#x432; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441; &#xab;&#x441;&#x431;&#x43e;&#x440; &#x433;&#x440;&#x443;&#x437;&#x430;&#xbb; (collecting), &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x434;&#x430;&#x43b;&#x44c;&#x448;&#x435; &#x435;&#x44e;
 * &#x43c;&#x43e;&#x436;&#x43d;&#x43e; &#x431;&#x44b;&#x43b;&#x43e; &#x432;&#x435;&#x441;&#x442;&#x438; &#x43e;&#x431;&#x44b;&#x447;&#x43d;&#x44b;&#x43c; &#x43f;&#x443;&#x442;&#x451;&#x43c; &#x438;&#x437; crm/my-orders.php (&#x412; &#x43f;&#x443;&#x442;&#x438; / &#x414;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x43e;, &#x444;&#x43e;&#x442;&#x43e;, &#x43f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c;).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/clientapi.php';
require_once __DIR__ . '/includes/pricing-formula.php';
$user = crm_require_role(['courier', 'admin']);
$pdo = crm_db();

$packTypeLabels = [
    'none'      => "\u{411}\u{435}\u{437} \u{443}\u{43f}\u{430}\u{43a}\u{43e}\u{432}\u{43a}\u{438}",
    'bag'       => "\u{424}\u{438}\u{440}\u{43c}\u{435}\u{43d}\u{43d}\u{44b}\u{439} \u{43f}\u{430}\u{43a}\u{435}\u{442}",
    'docs'      => "\u{41f}\u{430}\u{43a}\u{435}\u{442} \u{434}\u{43b}\u{44f} \u{434}\u{43e}\u{43a}\u{443}\u{43c}\u{435}\u{43d}\u{442}\u{43e}\u{432}",
    'box_s'     => "\u{41a}\u{43e}\u{440}\u{43e}\u{431}\u{43a}\u{430} S",
    'box_m'     => "\u{41a}\u{43e}\u{440}\u{43e}\u{431}\u{43a}\u{430} M",
    'box_l'     => "\u{41a}\u{43e}\u{440}\u{43e}\u{431}\u{43a}\u{430} L",
    'bubble'    => "\u{41f}\u{443}\u{437}\u{44b}\u{440}\u{447}\u{430}\u{442}\u{430}\u{44f} \u{43f}\u{43b}\u{451}\u{43d}\u{43a}\u{430}",
    'thermobox' => "\u{422}\u{435}\u{440}\u{43c}\u{43e}\u{431}\u{43e}\u{43a}\u{441}",
];

$acceptedOrder = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'accept') {
    crm_csrf_check();

    $name = trim($_POST['client_name'] ?? '');
    $phoneDigits = crm_phone_digits($_POST['client_phone'] ?? '');
    $fromCity = trim($_POST['from_city'] ?? '') ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}";
    $toCity = trim($_POST['to_city'] ?? '') ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}";
    $toAddress = trim($_POST['to_address'] ?? '') ?: null;
    $recipientName = trim($_POST['recipient_name'] ?? '') ?: null;
    $recipientPhone = crm_phone_digits($_POST['recipient_phone'] ?? '') ?: null;
    $weight = $_POST['weight_kg'] !== '' ? (float) str_replace(',', '.', $_POST['weight_kg']) : 0.0;
    $placesCount = max(1, (int) ($_POST['places_count'] ?? 1));
    $cargoDescription = trim($_POST['cargo_description'] ?? '') ?: null;
    $comment = trim($_POST['comment'] ?? '');

    $packType = array_key_exists($_POST['pack_type'] ?? '', $packTypeLabels) ? $_POST['pack_type'] : 'none';
    $fragile = !empty($_POST['fragile']);
    $inventory = !empty($_POST['inventory']);
    $sms = !empty($_POST['sms']);
    $isInsured = !empty($_POST['is_insured']);
    $insuredAmount = $isInsured && $_POST['insured_amount'] !== '' ? (float) str_replace(',', '.', $_POST['insured_amount']) : 0.0;

    $addons = [
        'pack_type'    => $packType,
        'fragile'      => $fragile,
        'inventory'    => $inventory,
        'sms'          => $sms,
        'insure_value' => $isInsured ? $insuredAmount : 0,
    ];

    if ($name === '' || !$phoneDigits) {
        crm_flash_set("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{438}\u{43c}\u{44f} \u{438} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{438}\u{442}\u{435}\u{43b}\u{44f} (\u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d} - 10-11 \u{446}\u{438}\u{444}\u{440}).", 'err');
    } elseif ($weight <= 0) {
        crm_flash_set("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{432}\u{435}\u{441} \u{433}\u{440}\u{443}\u{437}\u{430} \u{431}\u{43e}\u{43b}\u{44c}\u{448}\u{435} \u{43d}\u{443}\u{43b}\u{44f}.", 'err');
    } else {
        $totals = crm_calc_checkout_total($fromCity, $toCity, $weight, $addons);
        if ($totals === null) {
            crm_flash_set("\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{440}\u{430}\u{441}\u{441}\u{447}\u{438}\u{442}\u{430}\u{442}\u{44c} \u{441}\u{442}\u{43e}\u{438}\u{43c}\u{43e}\u{441}\u{442}\u{44c} - \u{43f}\u{440}\u{43e}\u{432}\u{435}\u{440}\u{44c}\u{442}\u{435} \u{433}\u{43e}\u{440}\u{43e}\u{434}\u{430} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{44f} \u{438} \u{43d}\u{430}\u{437}\u{43d}\u{430}\u{447}\u{435}\u{43d}\u{438}\u{44f}.", 'err');
        } else {
            $extraNotes = [];
            $extraNotes[] = "\u{423}\u{43f}\u{430}\u{43a}\u{43e}\u{432}\u{43a}\u{430}: " . $packTypeLabels[$packType];
            if ($fragile) { $extraNotes[] = "\u{425}\u{440}\u{443}\u{43f}\u{43a}\u{43e}\u{435}"; }
            if ($inventory) { $extraNotes[] = "\u{41e}\u{43f}\u{438}\u{441}\u{44c} \u{432}\u{43b}\u{43e}\u{436}\u{435}\u{43d}\u{438}\u{44f}"; }
            if ($sms) { $extraNotes[] = "SMS-\u{443}\u{432}\u{435}\u{434}\u{43e}\u{43c}\u{43b}\u{435}\u{43d}\u{438}\u{44f}"; }
            $fullComment = trim($comment . ($comment !== '' ? "\n" : '') . implode(', ', $extraNotes));

            $clientId = capi_find_or_create_client($pdo, $name, $phoneDigits);
            $insuranceFee = $isInsured && $insuredAmount > 0 ? (float) $totals['insure'] : null;

            [$orderId, $trackCode] = capi_create_order_row(
                $pdo, $clientId, $fromCity, $toCity, $toAddress,
                $weight, $isInsured && $insuredAmount > 0 ? $insuredAmount : null, (float) $totals['total'],
                $fullComment, 'courier', null,
                $isInsured && $insuredAmount > 0, $isInsured && $insuredAmount > 0 ? $insuredAmount : null, $insuranceFee,
                $recipientName, $recipientPhone
            );

            $pdo->prepare('UPDATE orders SET places_count = ?, cargo_description = ?, status = ?, courier_id = ?, pickup_type = ? WHERE id = ?')
                ->execute([$placesCount, $cargoDescription, 'collecting', $user['id'], 'courier', $orderId]);
            crm_ensure_order_packages($pdo, $orderId, $placesCount);
            $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'collecting', ?, '\u{413}\u{440}\u{443}\u{437} \u{437}\u{430}\u{431}\u{440}\u{430}\u{43d} \u{43a}\u{443}\u{440}\u{44c}\u{435}\u{440}\u{43e}\u{43c} \u{43d}\u{430} \u{43c}\u{435}\u{441}\u{442}\u{435}')")
                ->execute([$orderId, $user['id']]);

            $paymentChoice = $_POST['payment_now'] ?? '';
            if (in_array($paymentChoice, ['cash', 'terminal'], true)) {
                $pdo->prepare("UPDATE orders SET payment_status = 'paid', payment_method = ? WHERE id = ?")
                    ->execute([$paymentChoice, $orderId]);
            } elseif ($paymentChoice === 'postpaid') {
                $pdo->prepare("UPDATE orders SET payment_status = 'postpaid' WHERE id = ?")->execute([$orderId]);
            }

            if (!empty($_FILES['pickup_photos']) && is_array($_FILES['pickup_photos']['name'] ?? null)) {
                crm_save_order_photos($pdo, $orderId, 'pickup', $_FILES['pickup_photos'], $user['id']);
            }

            crm_notify_owner("\u{41a}\u{443}\u{440}\u{44c}\u{435}\u{440} " . $user['name'] . " \u{437}\u{430}\u{431}\u{440}\u{430}\u{43b} \u{433}\u{440}\u{443}\u{437}, \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} No" . $orderId . ": " . $name . ', ' . $fromCity . ' -> ' . $toCity . ', ' . $weight . " \u{43a}\u{433}, " . round((float) $totals['total']) . " \u{440}\u{443}\u{431}. \u{422}\u{440}\u{435}\u{43a}: " . $trackCode . '.');

            crm_flash_set("\u{413}\u{440}\u{443}\u{437} \u{43f}\u{440}\u{438}\u{43d}\u{44f}\u{442}, \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $orderId . " \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430} \u{438} \u{437}\u{430}\u{43a}\u{440}\u{435}\u{43f}\u{43b}\u{435}\u{43d}\u{430} \u{437}\u{430} \u{432}\u{430}\u{43c}\u{438}. \u{421}\u{442}\u{43e}\u{438}\u{43c}\u{43e}\u{441}\u{442}\u{44c}: " . crm_money((float) $totals['total']) . '.');
            crm_redirect('/crm/courier-pickup.php?accepted=' . $orderId);
        }
    }
}

$acceptedId = (int) ($_GET['accepted'] ?? 0);
if ($acceptedId) {
    $stmt = $pdo->prepare('SELECT o.*, c.name AS client_name, c.phone AS client_phone FROM orders o JOIN clients c ON c.id = o.client_id WHERE o.id = ? AND o.courier_id = ?');
    $stmt->execute([$acceptedId, $user['id']]);
    $acceptedOrder = $stmt->fetch();
}

$pageTitle = "\u{41f}\u{440}\u{438}\u{451}\u{43c} \u{433}\u{440}\u{443}\u{437}\u{430} \u{43d}\u{430} \u{43c}\u{435}\u{441}\u{442}\u{435}";
$activeNav = 'courier-pickup';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($acceptedOrder): ?>
<div class="card" style="border:2px solid var(--green);">
  <h3 style="margin-top:0;">&#x413;&#x440;&#x443;&#x437; &#x43f;&#x440;&#x438;&#x43d;&#x44f;&#x442; &#x2014; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x2116;<?= (int) $acceptedOrder['id'] ?></h3>
  <p>
    <?= e($acceptedOrder['client_name']) ?>, <?= e($acceptedOrder['from_city']) ?> &#x2192; <?= e($acceptedOrder['to_city']) ?>,
    <?= e(rtrim(rtrim(number_format((float) $acceptedOrder['weight_kg'], 2, '.', ''), '0'), '.')) ?> &#x43a;&#x433;
    &#x2014; <strong><?= crm_money((float) $acceptedOrder['price']) ?></strong>
  </p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a class="btn" href="/crm/waybill.php?id=<?= (int) $acceptedOrder['id'] ?>" target="_blank">&#x1f5a8; &#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x43d;&#x430;&#x43a;&#x43b;&#x430;&#x434;&#x43d;&#x43e;&#x439;</a>
    <a class="btn secondary" href="/crm/my-orders.php">&#x41c;&#x43e;&#x438; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</a>
    <a class="btn secondary" href="/crm/courier-pickup.php">+ &#x41f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x44c; &#x441;&#x43b;&#x435;&#x434;&#x443;&#x44e;&#x449;&#x438;&#x439; &#x433;&#x440;&#x443;&#x437;</a>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0;">&#x41f;&#x440;&#x438;&#x451;&#x43c; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43e;&#x442; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f;</h3>
  <p class="text-muted" style="margin-top:-10px;">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x441;&#x440;&#x430;&#x437;&#x443; &#x431;&#x443;&#x434;&#x435;&#x442; &#x437;&#x430;&#x43a;&#x440;&#x435;&#x43f;&#x43b;&#x435;&#x43d;&#x430; &#x437;&#x430; &#x432;&#x430;&#x43c;&#x438; &#x438; &#x43f;&#x43e;&#x44f;&#x432;&#x438;&#x442;&#x441;&#x44f; &#x432; &#xab;&#x41c;&#x43e;&#x438;&#x445; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x430;&#x445;&#xbb; &#x441;&#x43e; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x43e;&#x43c; &#xab;&#x421;&#x431;&#x43e;&#x440; &#x433;&#x440;&#x443;&#x437;&#x430;&#xbb;.</p>
  <form method="post" enctype="multipart/form-data" id="accept-form">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="accept">

    <div class="form-row">
      <div>
        <label>&#x418;&#x43c;&#x44f; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f;</label>
        <input type="text" name="client_name" required>
      </div>
      <div>
        <label>&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f;</label>
        <input type="tel" name="client_phone" placeholder="+7 900 000-00-00" required>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</label>
        <input type="text" name="from_city" id="r-from" value="&#x414;&#x435;&#x440;&#x431;&#x435;&#x43d;&#x442;" required>
      </div>
      <div>
        <label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x44f;</label>
        <input type="text" name="to_city" id="r-to" value="&#x421;&#x430;&#x43d;&#x43a;&#x442;-&#x41f;&#x435;&#x442;&#x435;&#x440;&#x431;&#x443;&#x440;&#x433;" required>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>&#x410;&#x434;&#x440;&#x435;&#x441; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
        <input type="text" name="to_address">
      </div>
      <div>
        <label>&#x41a;&#x43e;&#x43b;&#x438;&#x447;&#x435;&#x441;&#x442;&#x432;&#x43e; &#x43c;&#x435;&#x441;&#x442;</label>
        <input type="number" name="places_count" value="1" min="1" step="1">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>&#x418;&#x43c;&#x44f; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
        <input type="text" name="recipient_name">
      </div>
      <div>
        <label>&#x422;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
        <input type="tel" name="recipient_phone" placeholder="+7 900 000-00-00">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>&#x412;&#x435;&#x441; &#x433;&#x440;&#x443;&#x437;&#x430;, &#x43a;&#x433;</label>
        <input type="number" step="0.01" name="weight_kg" id="r-weight" required>
      </div>
      <div>
        <label>&#x423;&#x43f;&#x430;&#x43a;&#x43e;&#x432;&#x43a;&#x430;</label>
        <select name="pack_type" id="r-pack">
          <?php foreach ($packTypeLabels as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label>&#x41e;&#x43f;&#x438;&#x441;&#x430;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x430; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
    <input type="text" name="cargo_description">

    <div class="form-row">
      <div>
        <label style="font-weight:400;"><input type="checkbox" name="fragile" id="r-fragile" style="width:auto;display:inline;margin-right:6px;"> &#x425;&#x440;&#x443;&#x43f;&#x43a;&#x438;&#x439; &#x433;&#x440;&#x443;&#x437;</label>
        <label style="font-weight:400;margin-top:6px;"><input type="checkbox" name="inventory" id="r-inventory" style="width:auto;display:inline;margin-right:6px;"> &#x41e;&#x43f;&#x438;&#x441;&#x44c; &#x432;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x438;&#x44f;</label>
        <label style="font-weight:400;margin-top:6px;"><input type="checkbox" name="sms" id="r-sms" style="width:auto;display:inline;margin-right:6px;"> SMS-&#x443;&#x432;&#x435;&#x434;&#x43e;&#x43c;&#x43b;&#x435;&#x43d;&#x438;&#x44f; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x443;</label>
      </div>
      <div>
        <label style="font-weight:400;"><input type="checkbox" name="is_insured" id="r-insured" style="width:auto;display:inline;margin-right:6px;"> &#x417;&#x430;&#x441;&#x442;&#x440;&#x430;&#x445;&#x43e;&#x432;&#x430;&#x442;&#x44c; &#x433;&#x440;&#x443;&#x437;</label>
        <div id="r-insured-value-wrap" style="display:none;margin-top:6px;">
          <label>&#x41e;&#x431;&#x44a;&#x44f;&#x432;&#x43b;&#x435;&#x43d;&#x43d;&#x430;&#x44f; &#x446;&#x435;&#x43d;&#x43d;&#x43e;&#x441;&#x442;&#x44c;, &#x20bd;</label>
          <input type="number" step="0.01" name="insured_amount" id="r-insured-amount">
        </div>
      </div>
    </div>

    <label>&#x41a;&#x43e;&#x43c;&#x43c;&#x435;&#x43d;&#x442;&#x430;&#x440;&#x438;&#x439; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
    <textarea name="comment"></textarea>

    <label>&#x41a;&#x430;&#x43a; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x438;&#x432;&#x430;&#x435;&#x442;&#x441;&#x44f;</label>
    <select name="payment_now">
      <option value="">&#x41f;&#x43e;&#x43a;&#x430; &#x43d;&#x435; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x43e; (&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x43f;&#x440;&#x438; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x438;)</option>
      <option value="cash">&#x41d;&#x430;&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x43c;&#x438; &#x441;&#x435;&#x439;&#x447;&#x430;&#x441;</option>
      <option value="terminal">&#x41a;&#x430;&#x440;&#x442;&#x43e;&#x439; &#x447;&#x435;&#x440;&#x435;&#x437; &#x442;&#x435;&#x440;&#x43c;&#x438;&#x43d;&#x430;&#x43b; &#x441;&#x435;&#x439;&#x447;&#x430;&#x441;</option>
      <option value="postpaid">&#x41f;&#x43e;&#x441;&#x442;&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x430; (&#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x438;&#x442; &#x43f;&#x43e;&#x441;&#x43b;&#x435; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;)</option>
    </select>

    <label>&#x424;&#x43e;&#x442;&#x43e; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43f;&#x440;&#x438; &#x43f;&#x440;&#x438;&#x451;&#x43c;&#x435; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
    <input type="file" name="pickup_photos[]" accept="image/*" capture="environment" multiple>

    <div class="card" id="r-calc" style="background:#f0f2f9;margin:16px 0;">
      <div class="text-muted">&#x421;&#x442;&#x43e;&#x438;&#x43c;&#x43e;&#x441;&#x442;&#x44c; &#x440;&#x430;&#x441;&#x441;&#x447;&#x438;&#x442;&#x44b;&#x432;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x430;&#x432;&#x442;&#x43e;&#x43c;&#x430;&#x442;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x438; &#x43f;&#x43e; &#x432;&#x435;&#x441;&#x443;, &#x440;&#x430;&#x441;&#x441;&#x442;&#x43e;&#x44f;&#x43d;&#x438;&#x44e; &#x438; &#x434;&#x43e;&#x43f;. &#x43e;&#x43f;&#x446;&#x438;&#x44f;&#x43c;.</div>
      <div id="r-calc-result" style="font-size:1.3rem;font-weight:800;margin-top:6px;">&#x2014;</div>
    </div>

    <div class="form-actions"><button class="btn" type="submit">&#x41f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x44c; &#x433;&#x440;&#x443;&#x437; &#x438; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x442;&#x44c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443;</button></div>
  </form>
</div>

<script>
(function () {
  var form = document.getElementById('accept-form');
  var resultEl = document.getElementById('r-calc-result');
  var insuredChk = document.getElementById('r-insured');
  var insuredWrap = document.getElementById('r-insured-value-wrap');
  var timer = null;

  insuredChk.addEventListener('change', function () {
    insuredWrap.style.display = insuredChk.checked ? 'block' : 'none';
    scheduleRecalc();
  });

  function scheduleRecalc() {
    clearTimeout(timer);
    timer = setTimeout(recalc, 350);
  }

  function recalc() {
    var weight = document.getElementById('r-weight').value;
    if (!weight || parseFloat(weight) <= 0) {
      resultEl.textContent = '\\u2014';
      return;
    }
    var params = new URLSearchParams({
      from_city: document.getElementById('r-from').value,
      to_city: document.getElementById('r-to').value,
      weight_kg: weight,
      pack_type: document.getElementById('r-pack').value,
      fragile: document.getElementById('r-fragile').checked ? '1' : '',
      inventory: document.getElementById('r-inventory').checked ? '1' : '',
      sms: document.getElementById('r-sms').checked ? '1' : '',
      insure_value: insuredChk.checked ? (document.getElementById('r-insured-amount').value || '0') : '0'
    });
    fetch('/crm/reception-price.php?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok && data.totals) {
          resultEl.textContent = Math.round(data.totals.total).toLocaleString('ru-RU') + ' \\u20bd';
        } else {
          resultEl.textContent = '\\u041d\\u0435 \\u0443\\u0434\\u0430\\u043b\\u043e\\u0441\\u044c \\u0440\\u0430\\u0441\\u0441\\u0447\\u0438\\u0442\\u0430\\u0442\\u044c \\u2014 \\u043f\\u0440\\u043e\\u0432\\u0435\\u0440\\u044c\\u0442\\u0435 \\u0433\\u043e\\u0440\\u043e\\u0434\\u0430';
        }
      })
      .catch(function () { resultEl.textContent = '\\u2014'; });
  }

  form.querySelectorAll('input, select').forEach(function (el) {
    el.addEventListener('input', scheduleRecalc);
    el.addEventListener('change', scheduleRecalc);
  });
})();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
