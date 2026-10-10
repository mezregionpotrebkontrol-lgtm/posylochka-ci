<?php
/**
 * &#x41e;&#x43d;&#x43b;&#x430;&#x439;&#x43d;-&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; ("&#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x438;&#x442;&#x44c; &#x43e;&#x43d;&#x43b;&#x430;&#x439;&#x43d;" &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435;). &#x41f;&#x440;&#x438;&#x43d;&#x438;&#x43c;&#x430;&#x435;&#x442; &#x414;&#x412;&#x410; &#x432;&#x43e;&#x437;&#x43c;&#x43e;&#x436;&#x43d;&#x44b;&#x445;
 * &#x444;&#x43e;&#x440;&#x43c;&#x430;&#x442;&#x430; &#x437;&#x430;&#x43f;&#x440;&#x43e;&#x441;&#x430; (&#x441;&#x430;&#x439;&#x442; &#x438;&#x441;&#x43f;&#x43e;&#x43b;&#x44c;&#x437;&#x443;&#x435;&#x442; &#x440;&#x430;&#x437;&#x43d;&#x44b;&#x435; &#x444;&#x43e;&#x440;&#x43c;&#x44b; &#x43d;&#x430; &#x440;&#x430;&#x437;&#x43d;&#x44b;&#x445; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x430;&#x445;):
 *
 *  - app.html (&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x439; &#x43a;&#x430;&#x431;&#x438;&#x43d;&#x435;&#x442;, &#x43f;&#x43e;&#x43b;&#x43d;&#x430;&#x44f; &#x444;&#x43e;&#x440;&#x43c;&#x430;): {name, phone, city: "A &#x2192; B, &#x430;&#x434;&#x440;&#x435;&#x441;",
 *    weight, comment, addons:{...}, origin_zone, dest_zone, route_km}
 *  - index.html/dostavka.html (&#x443;&#x43f;&#x440;&#x43e;&#x449;&#x451;&#x43d;&#x43d;&#x430;&#x44f; &#x444;&#x43e;&#x440;&#x43c;&#x430;): {name, phone, city: "A",
 *    dest_city: "B", weight, comment} &#x2014; &#x431;&#x435;&#x437; addons.
 *
 * &#x417;&#x43e;&#x43d;&#x44b;/&#x43a;&#x438;&#x43b;&#x43e;&#x43c;&#x435;&#x442;&#x440;&#x430;&#x436;/&#x438;&#x442;&#x43e;&#x433;, &#x43f;&#x440;&#x438;&#x441;&#x43b;&#x430;&#x43d;&#x43d;&#x44b;&#x435; &#x431;&#x440;&#x430;&#x443;&#x437;&#x435;&#x440;&#x43e;&#x43c;, &#x418;&#x413;&#x41d;&#x41e;&#x420;&#x418;&#x420;&#x423;&#x42e;&#x422;&#x421;&#x42f;: &#x441;&#x435;&#x440;&#x432;&#x435;&#x440; &#x432;&#x441;&#x435;&#x433;&#x434;&#x430;
 * &#x43f;&#x435;&#x440;&#x435;&#x441;&#x447;&#x438;&#x442;&#x44b;&#x432;&#x430;&#x435;&#x442; &#x438;&#x445; &#x441;&#x430;&#x43c; &#x43f;&#x43e; &#x442;&#x435;&#x43c; &#x436;&#x435; &#x434;&#x430;&#x43d;&#x43d;&#x44b;&#x43c; &#x438; &#x444;&#x43e;&#x440;&#x43c;&#x443;&#x43b;&#x435; (includes/pricing-formula.php),
 * &#x438;&#x43d;&#x430;&#x447;&#x435; &#x441;&#x443;&#x43c;&#x43c;&#x443; &#x43f;&#x43b;&#x430;&#x442;&#x435;&#x436;&#x430; &#x43c;&#x43e;&#x436;&#x43d;&#x43e; &#x43f;&#x43e;&#x434;&#x43c;&#x435;&#x43d;&#x438;&#x442;&#x44c; &#x432; devtools.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error("\u{41c}\u{435}\u{442}\u{43e}\u{434} \u{43d}\u{435} \u{43f}\u{43e}\u{434}\u{434}\u{435}\u{440}\u{436}\u{438}\u{432}\u{430}\u{435}\u{442}\u{441}\u{44f}.", 405);
}

$payload = capi_json_input();

$name = trim((string) ($payload['name'] ?? ''));
$phoneDigits = crm_phone_digits($payload['phone'] ?? null);
$cityRaw = trim((string) ($payload['city'] ?? ''));
$destCityRaw = trim((string) ($payload['dest_city'] ?? ''));
$weight = (float) ($payload['weight'] ?? 0);
$comment = trim((string) ($payload['comment'] ?? ''));
$addons = is_array($payload['addons'] ?? null) ? $payload['addons'] : [];

if ($name === '' || !$phoneDigits) {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{438}\u{43c}\u{44f} \u{438} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}.");
}

$toAddress = null;
if ($destCityRaw !== '') {
    // &#x423;&#x43f;&#x440;&#x43e;&#x449;&#x451;&#x43d;&#x43d;&#x430;&#x44f; &#x444;&#x43e;&#x440;&#x43c;&#x430;: city &#x438; dest_city &#x2014; &#x43e;&#x442;&#x434;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x435; &#x43f;&#x43e;&#x43b;&#x44f;.
    $originCity = $cityRaw;
    $destCity = $destCityRaw;
} else {
    // &#x41f;&#x43e;&#x43b;&#x43d;&#x430;&#x44f; &#x444;&#x43e;&#x440;&#x43c;&#x430;: city = "&#x41e;&#x442;&#x43a;&#x443;&#x434;&#x430; &#x2192; &#x41a;&#x443;&#x434;&#x430;, &#x430;&#x434;&#x440;&#x435;&#x441;".
    [$originCity, $destCity, $toAddress] = capi_split_combined_city($cityRaw);
}

if ($originCity === '' || $destCity === '' || $weight <= 0) {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{433}\u{43e}\u{440}\u{43e}\u{434}\u{430} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{44f}/\u{43f}\u{43e}\u{43b}\u{443}\u{447}\u{435}\u{43d}\u{438}\u{44f} \u{438} \u{432}\u{435}\u{441} \u{433}\u{440}\u{443}\u{437}\u{430}.");
}

$totals = crm_calc_checkout_total($originCity, $destCity, $weight, $addons);
if ($totals === null) {
    capi_error("\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{440}\u{430}\u{441}\u{441}\u{447}\u{438}\u{442}\u{430}\u{442}\u{44c} \u{441}\u{442}\u{43e}\u{438}\u{43c}\u{43e}\u{441}\u{442}\u{44c} \u{2014} \u{43f}\u{440}\u{43e}\u{432}\u{435}\u{440}\u{44c}\u{442}\u{435} \u{433}\u{43e}\u{440}\u{43e}\u{434}\u{430} (\u{432}\u{44b}\u{431}\u{435}\u{440}\u{438}\u{442}\u{435} \u{438}\u{437} \u{43f}\u{43e}\u{434}\u{441}\u{43a}\u{430}\u{437}\u{43e}\u{43a}) \u{438} \u{432}\u{435}\u{441}.");
}

if (capi_order_rate_limited($pdo, $phoneDigits)) {
    capi_error("\u{421}\u{43b}\u{438}\u{448}\u{43a}\u{43e}\u{43c} \u{43c}\u{43d}\u{43e}\u{433}\u{43e} \u{437}\u{430}\u{44f}\u{432}\u{43e}\u{43a} \u{441} \u{44d}\u{442}\u{43e}\u{433}\u{43e} \u{43d}\u{43e}\u{43c}\u{435}\u{440}\u{430} \u{437}\u{430} \u{43f}\u{43e}\u{441}\u{43b}\u{435}\u{434}\u{43d}\u{438}\u{435} 10 \u{43c}\u{438}\u{43d}\u{443}\u{442}. \u{41f}\u{43e}\u{43f}\u{440}\u{43e}\u{431}\u{443}\u{439}\u{442}\u{435} \u{43f}\u{43e}\u{437}\u{436}\u{435} \u{438}\u{43b}\u{438} \u{43f}\u{43e}\u{437}\u{432}\u{43e}\u{43d}\u{438}\u{442}\u{435} \u{43d}\u{430}\u{43c}.", 429);
}

if (!crm_yookassa_ready()) {
    capi_error("\u{41f}\u{440}\u{438}\u{451}\u{43c} \u{43e}\u{43d}\u{43b}\u{430}\u{439}\u{43d}-\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b} \u{432}\u{440}\u{435}\u{43c}\u{435}\u{43d}\u{43d}\u{43e} \u{43d}\u{435}\u{434}\u{43e}\u{441}\u{442}\u{443}\u{43f}\u{435}\u{43d}. \u{41f}\u{43e}\u{436}\u{430}\u{43b}\u{443}\u{439}\u{441}\u{442}\u{430}, \u{43e}\u{444}\u{43e}\u{440}\u{43c}\u{438}\u{442}\u{435} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{443} \u{431}\u{435}\u{437} \u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b} \u{438}\u{43b}\u{438} \u{441}\u{432}\u{44f}\u{436}\u{438}\u{442}\u{435}\u{441}\u{44c} \u{441} \u{43d}\u{430}\u{43c}\u{438}.", 503);
}

$clientId = capi_find_or_create_client($pdo, $name, $phoneDigits);
$declaredValue = !empty($addons['insure_value']) ? (float) $addons['insure_value'] : null;
$isInsured = $declaredValue !== null && $declaredValue > 0;
$insuranceFee = $isInsured ? (float) ($totals['insure'] ?? 0) : null;

[$orderId, $trackCode] = capi_create_order_row(
    $pdo, $clientId, $originCity, $destCity, $toAddress,
    $weight, $declaredValue, $totals['total'], $comment, 'site', null,
    $isInsured, $declaredValue, $insuranceFee
);

$cfg = crm_config();
$baseUrl = rtrim($cfg['site']['base_url'] ?? '', '/');
$returnUrl = $baseUrl . '/app.html?paid_track=' . rawurlencode($trackCode);

[$payment, $err] = crm_yookassa_create_payment(
    (float) $totals['total'],
    "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $orderId . ' (' . $originCity . " \u{2192} " . $destCity . ')',
    $returnUrl,
    ['order_id' => $orderId, 'track_code' => $trackCode],
    '+' . $phoneDigits
);

if ($err !== null || empty($payment['id']) || empty($payment['confirmation']['confirmation_url'])) {
    capi_error($err ?: "\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{441}\u{43e}\u{437}\u{434}\u{430}\u{442}\u{44c} \u{43f}\u{43b}\u{430}\u{442}\u{451}\u{436}. \u{41f}\u{43e}\u{43f}\u{440}\u{43e}\u{431}\u{443}\u{439}\u{442}\u{435} \u{43f}\u{43e}\u{437}\u{436}\u{435}.", 502);
}

$pdo->prepare('INSERT INTO yookassa_payments (order_id, yk_payment_id, amount, confirmation_url, status) VALUES (?,?,?,?,?)')
    ->execute([$orderId, $payment['id'], $totals['total'], $payment['confirmation']['confirmation_url'], $payment['status'] ?? 'pending']);

crm_notify_owner("\u{41d}\u{43e}\u{432}\u{430}\u{44f} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$orderId} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430} (\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{430} \u{43e}\u{43d}\u{43b}\u{430}\u{439}\u{43d}): {$originCity} \u{2192} {$destCity}, {$weight} \u{43a}\u{433}, {$totals['total']} \u{20bd}. \u{422}\u{440}\u{435}\u{43a}: {$trackCode}.");

capi_respond(['ok' => true, 'confirmation_url' => $payment['confirmation']['confirmation_url'], 'track' => $trackCode]);
