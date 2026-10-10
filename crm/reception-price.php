<?php
/**
 * &#x412;&#x43d;&#x443;&#x442;&#x440;&#x435;&#x43d;&#x43d;&#x438;&#x439; AJAX-&#x44d;&#x43d;&#x434;&#x43f;&#x43e;&#x438;&#x43d;&#x442; &#x434;&#x43b;&#x44f; crm/reception.php: &#x43e;&#x442;&#x434;&#x430;&#x451;&#x442; &#x43f;&#x440;&#x435;&#x434;&#x432;&#x430;&#x440;&#x438;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x439;
 * &#x440;&#x430;&#x441;&#x447;&#x451;&#x442; &#x441;&#x442;&#x43e;&#x438;&#x43c;&#x43e;&#x441;&#x442;&#x438; &#x43f;&#x43e; &#x442;&#x43e;&#x439; &#x436;&#x435; &#x444;&#x43e;&#x440;&#x43c;&#x443;&#x43b;&#x435;, &#x447;&#x442;&#x43e; &#x438; &#x441;&#x435;&#x440;&#x432;&#x435;&#x440; &#x43f;&#x440;&#x438; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x438;&#x438; &#x437;&#x430;&#x43a;&#x430;&#x437;&#x430;
 * (includes/pricing-formula.php) &#x2014; &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x43e;&#x43f;&#x435;&#x440;&#x430;&#x442;&#x43e;&#x440; &#x432;&#x438;&#x434;&#x435;&#x43b; &#x442;&#x43e;&#x447;&#x43d;&#x443;&#x44e; &#x446;&#x435;&#x43d;&#x443; &#x435;&#x449;&#x451; &#x434;&#x43e;
 * &#x441;&#x43e;&#x445;&#x440;&#x430;&#x43d;&#x435;&#x43d;&#x438;&#x44f; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;. &#x422;&#x440;&#x435;&#x431;&#x443;&#x435;&#x442; &#x432;&#x445;&#x43e;&#x434;&#x430; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x430; (admin/operator), &#x432; &#x43e;&#x442;&#x43b;&#x438;&#x447;&#x438;&#x435;
 * &#x43e;&#x442; &#x43f;&#x443;&#x431;&#x43b;&#x438;&#x447;&#x43d;&#x43e;&#x433;&#x43e; /crm/api/price-quote.php.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pricing-formula.php';
crm_require_role(['admin', 'operator', 'courier']);

header('Content-Type: application/json; charset=utf-8');

$fromCity = trim($_GET['from_city'] ?? '') ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}";
$toCity = trim($_GET['to_city'] ?? '') ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}";
$weight = isset($_GET['weight_kg']) && $_GET['weight_kg'] !== ''
    ? (float) str_replace(',', '.', $_GET['weight_kg'])
    : 0.0;

$addons = [
    'pack_type'    => $_GET['pack_type'] ?? 'none',
    'fragile'      => !empty($_GET['fragile']),
    'inventory'    => !empty($_GET['inventory']),
    'sms'          => !empty($_GET['sms']),
    'insure_value' => isset($_GET['insure_value']) && $_GET['insure_value'] !== ''
        ? (float) str_replace(',', '.', $_GET['insure_value'])
        : 0.0,
];

$totals = $weight > 0 ? crm_calc_checkout_total($fromCity, $toCity, $weight, $addons) : null;

echo json_encode(['ok' => true, 'totals' => $totals], JSON_UNESCAPED_UNICODE);
