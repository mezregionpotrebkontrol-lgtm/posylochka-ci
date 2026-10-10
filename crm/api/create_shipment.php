<?php
/**
 * &#x41f;&#x440;&#x43e;&#x441;&#x442;&#x43e;&#x435; &#x43e;&#x444;&#x43e;&#x440;&#x43c;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x431;&#x435;&#x437; &#x43e;&#x43d;&#x43b;&#x430;&#x439;&#x43d;-&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b; (&#x444;&#x43e;&#x440;&#x43c;&#x430; "orderForm" &#x432; app.html &#x2014;
 * &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44c; &#x438;&#x437; &#x414;&#x435;&#x440;&#x431;&#x435;&#x43d;&#x442;&#x430;, &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44c; &#x432; &#x421;&#x41f;&#x431; &#x43f;&#x440;&#x438;&#x445;&#x43e;&#x434;&#x438;&#x442; &#x441;&#x430;&#x43c; &#x437;&#x430; &#x433;&#x440;&#x443;&#x437;&#x43e;&#x43c;/&#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;).
 * Payload: {service, name, phone, address, weight, comment, origin_city, dest_city}
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error("\u{41c}\u{435}\u{442}\u{43e}\u{434} \u{43d}\u{435} \u{43f}\u{43e}\u{434}\u{434}\u{435}\u{440}\u{436}\u{438}\u{432}\u{430}\u{435}\u{442}\u{441}\u{44f}.", 405);
}

$payload = capi_json_input();
$service = trim((string) ($payload['service'] ?? ''));
$name = trim((string) ($payload['name'] ?? ''));
$phoneDigits = crm_phone_digits($payload['phone'] ?? null);
$address = trim((string) ($payload['address'] ?? ''));
$weightRaw = trim((string) ($payload['weight'] ?? ''));
$comment = trim((string) ($payload['comment'] ?? ''));
$originCity = trim((string) ($payload['origin_city'] ?? '')) ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}";
$destCity = trim((string) ($payload['dest_city'] ?? '')) ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}";

$allowedServices = ["\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43a}\u{430}", "\u{41f}\u{440}\u{43e}\u{434}\u{443}\u{43a}\u{442}\u{44b}", 'B2B'];
if (!in_array($service, $allowedServices, true)) {
    capi_error("\u{412}\u{44b}\u{431}\u{435}\u{440}\u{438}\u{442}\u{435} \u{432}\u{438}\u{434} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{44f}.");
}
if ($name === '' || !$phoneDigits) {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{438}\u{43c}\u{44f} \u{438} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}.");
}
if (capi_order_rate_limited($pdo, $phoneDigits)) {
    capi_error("\u{421}\u{43b}\u{438}\u{448}\u{43a}\u{43e}\u{43c} \u{43c}\u{43d}\u{43e}\u{433}\u{43e} \u{437}\u{430}\u{44f}\u{432}\u{43e}\u{43a} \u{441} \u{44d}\u{442}\u{43e}\u{433}\u{43e} \u{43d}\u{43e}\u{43c}\u{435}\u{440}\u{430} \u{437}\u{430} \u{43f}\u{43e}\u{441}\u{43b}\u{435}\u{434}\u{43d}\u{438}\u{435} 10 \u{43c}\u{438}\u{43d}\u{443}\u{442}. \u{41f}\u{43e}\u{43f}\u{440}\u{43e}\u{431}\u{443}\u{439}\u{442}\u{435} \u{43f}\u{43e}\u{437}\u{436}\u{435} \u{438}\u{43b}\u{438} \u{43f}\u{43e}\u{437}\u{432}\u{43e}\u{43d}\u{438}\u{442}\u{435} \u{43d}\u{430}\u{43c}.", 429);
}

$weightKg = null;
if ($weightRaw !== '' && preg_match('/[\d.,]+/', $weightRaw, $m)) {
    $weightKg = (float) str_replace(',', '.', $m[0]);
}

$clientId = capi_find_or_create_client($pdo, $name, $phoneDigits);
[$orderId, $trackCode] = capi_create_order_row(
    $pdo, $clientId, $originCity, $destCity, $address ?: null,
    $weightKg, null, null, $comment !== '' ? $comment : null, 'site', $service
);

crm_notify_owner("\u{41d}\u{43e}\u{432}\u{430}\u{44f} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$orderId} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430} (\u{431}\u{435}\u{437} \u{43e}\u{43d}\u{43b}\u{430}\u{439}\u{43d}-\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b}): {$originCity} \u{2192} {$destCity}"
    . ($weightRaw !== '' ? ", {$weightRaw}" : '') . ". \u{422}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}: +{$phoneDigits}. \u{422}\u{440}\u{435}\u{43a}: {$trackCode}.");

capi_respond(['ok' => true, 'track' => $trackCode]);
