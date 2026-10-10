<?php
/**
 * &#x41f;&#x443;&#x431;&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x439; &#x442;&#x440;&#x435;&#x43a;&#x438;&#x43d;&#x433; &#x43f;&#x43e; &#x43d;&#x43e;&#x43c;&#x435;&#x440;&#x443; (track_code), &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x44b;&#x439; &#x432;&#x44b;&#x434;&#x430;&#x451;&#x442;&#x441;&#x44f; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x443; &#x43f;&#x440;&#x438;
 * &#x441;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x438;&#x438; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;. GET /api/track_shipment.php?code=PSLXXXXXX
 * &#x41e;&#x442;&#x432;&#x435;&#x447;&#x430;&#x435;&#x442; &#x442;&#x435;&#x43c; &#x436;&#x435; &#x444;&#x43e;&#x440;&#x43c;&#x430;&#x442;&#x43e;&#x43c;, &#x447;&#x442;&#x43e; &#x436;&#x434;&#x451;&#x442; &#x441;&#x430;&#x439;&#x442;: {step_index, dates:[5 &#x441;&#x442;&#x440;&#x43e;&#x43a;/null]}.
 */
require_once __DIR__ . '/_bootstrap.php';

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{43d}\u{43e}\u{43c}\u{435}\u{440} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{44f}.", 404);
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE track_code = ? LIMIT 1');
$stmt->execute([$code]);
$order = $stmt->fetch();
if (!$order) {
    capi_error("\u{41e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{435} \u{441} \u{442}\u{430}\u{43a}\u{438}\u{43c} \u{43d}\u{43e}\u{43c}\u{435}\u{440}\u{43e}\u{43c} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{43e}.", 404);
}

$stmt = $pdo->prepare('SELECT status, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC');
$stmt->execute([(int) $order['id']]);
$history = $stmt->fetchAll();

// &#x428;&#x430;&#x433;&#x438; &#x43f;&#x43e;&#x43a;&#x430;&#x437;&#x430; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435;, &#x432; &#x43f;&#x43e;&#x440;&#x44f;&#x434;&#x43a;&#x435;: &#x43f;&#x440;&#x438;&#x43d;&#x44f;&#x442;&#x43e; &#x2192; &#x437;&#x430;&#x433;&#x440;&#x443;&#x436;&#x435;&#x43d;&#x43e; &#x2192; &#x432; &#x43f;&#x443;&#x442;&#x438; &#x2192; &#x43f;&#x440;&#x438;&#x431;&#x44b;&#x43b;&#x43e; &#x2192; &#x432;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x43e;.
// &#x423; CRM &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; 4 &#x440;&#x430;&#x431;&#x43e;&#x447;&#x438;&#x445; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430; (new/accepted/in_transit/delivered), &#x43f;&#x43e;&#x44d;&#x442;&#x43e;&#x43c;&#x443;
// "&#x437;&#x430;&#x433;&#x440;&#x443;&#x436;&#x435;&#x43d;&#x43e;"/"&#x43f;&#x440;&#x438;&#x431;&#x44b;&#x43b;&#x43e;" &#x43d;&#x435; &#x438;&#x43c;&#x435;&#x44e;&#x442; &#x43e;&#x442;&#x434;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;&#x439; &#x434;&#x430;&#x442;&#x44b; &#x2014; &#x43e;&#x441;&#x442;&#x430;&#x44e;&#x442;&#x441;&#x44f; &#x43f;&#x443;&#x441;&#x442;&#x44b;&#x43c;&#x438; &#x434;&#x43e; &#x432;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f;.
$statusToStep = ['new' => 0, 'accepted' => 1, 'collecting' => 1, 'in_transit' => 2, 'delivered' => 4, 'cancelled' => null];
$dates = [null, null, null, null, null];
$stepIndex = 0;
foreach ($history as $row) {
    $step = $statusToStep[$row['status']] ?? null;
    if ($step === null) {
        continue;
    }
    $dates[$step] = $row['changed_at'];
    $stepIndex = max($stepIndex, $step);
}

// &#x416;&#x438;&#x432;&#x430;&#x44f; &#x433;&#x435;&#x43e;&#x43f;&#x43e;&#x437;&#x438;&#x446;&#x438;&#x44f; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430; &#x2014; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x43f;&#x43e;&#x43a;&#x430; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; "&#x432; &#x43f;&#x443;&#x442;&#x438;" &#x438; &#x43a;&#x43e;&#x43e;&#x440;&#x434;&#x438;&#x43d;&#x430;&#x442;&#x44b;
// &#x43d;&#x435; &#x441;&#x442;&#x430;&#x440;&#x448;&#x435; 10 &#x43c;&#x438;&#x43d;&#x443;&#x442; (&#x447;&#x442;&#x43e;&#x431;&#x44b; &#x43d;&#x435; &#x43f;&#x43e;&#x43a;&#x430;&#x437;&#x44b;&#x432;&#x430;&#x442;&#x44c; &#x443;&#x441;&#x442;&#x430;&#x440;&#x435;&#x432;&#x448;&#x435;&#x435;/&#x447;&#x443;&#x436;&#x43e;&#x435; &#x43c;&#x435;&#x441;&#x442;&#x43e;&#x43f;&#x43e;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x438;&#x435;).
$courierLocation = null;
if ($order['status'] === 'in_transit' && $order['courier_id']) {
    $locStmt = $pdo->prepare('SELECT lat, lng, updated_at FROM courier_locations
        WHERE courier_id = ? AND updated_at >= (NOW() - INTERVAL 10 MINUTE)');
    $locStmt->execute([(int) $order['courier_id']]);
    $loc = $locStmt->fetch();
    if ($loc) {
        $courierLocation = ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'updated_at' => $loc['updated_at']];
    }
}

capi_respond(['ok' => true, 'step_index' => $stepIndex, 'dates' => $dates, 'courier_location' => $courierLocation]);
