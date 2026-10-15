<?php
/** &#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430; "&#x43e;&#x444;&#x43e;&#x440;&#x43c;&#x438;&#x442;&#x44c; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x443;" &#x441; &#x43b;&#x435;&#x43d;&#x434;&#x438;&#x43d;&#x433;&#x430; (index.html/dostavka.html, &#x431;&#x435;&#x437; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b;). */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error("\u{41c}\u{435}\u{442}\u{43e}\u{434} \u{43d}\u{435} \u{43f}\u{43e}\u{434}\u{434}\u{435}\u{440}\u{436}\u{438}\u{432}\u{430}\u{435}\u{442}\u{441}\u{44f}.", 405);
}

$payload = capi_json_input();
$name = trim((string) ($payload['name'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$city = trim((string) ($payload['city'] ?? ''));
$destCity = trim((string) ($payload['dest_city'] ?? ''));
$weight = trim((string) ($payload['weight'] ?? ''));
$comment = trim((string) ($payload['comment'] ?? ''));

if (mb_strlen($name) < 2) {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{438}\u{43c}\u{44f}.");
}
if (mb_strlen($phone) < 5) {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}.");
}

// &#x41f;&#x440;&#x43e;&#x441;&#x442;&#x430;&#x44f; &#x437;&#x430;&#x449;&#x438;&#x442;&#x430; &#x43e;&#x442; &#x441;&#x43f;&#x430;&#x43c;&#x430;: &#x43d;&#x435; &#x431;&#x43e;&#x43b;&#x44c;&#x448;&#x435; &#x43e;&#x434;&#x43d;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x441; &#x43e;&#x434;&#x43d;&#x43e;&#x433;&#x43e; &#x431;&#x440;&#x430;&#x443;&#x437;&#x435;&#x440;&#x430; &#x437;&#x430; 2 &#x43c;&#x438;&#x43d;&#x443;&#x442;&#x44b;
// (&#x43a;&#x430;&#x43a; &#x43d;&#x430; &#x440;&#x435;&#x430;&#x43b;&#x44c;&#x43d;&#x43e;&#x43c; &#x441;&#x430;&#x439;&#x442;&#x435; &#x441;&#x435;&#x439;&#x447;&#x430;&#x441;) &#x2014; &#x43e;&#x442;&#x432;&#x435;&#x447;&#x430;&#x435;&#x43c; &#x43a;&#x430;&#x43a; &#x43e;&#x431;&#x44b;&#x447;&#x43d;&#x43e;, &#x43f;&#x440;&#x43e;&#x441;&#x442;&#x43e; &#x43d;&#x435; &#x434;&#x443;&#x431;&#x43b;&#x438;&#x440;&#x443;&#x435;&#x43c; &#x437;&#x430;&#x43f;&#x438;&#x441;&#x44c;.
capi_start_session();
$lastKey = 'last_order_request_at';
if (isset($_SESSION[$lastKey]) && (time() - $_SESSION[$lastKey]) < 120) {
    capi_respond(['ok' => true]);
}
$_SESSION[$lastKey] = time();
$cfg = crm_config();

$stmt = $pdo->prepare('INSERT INTO site_order_requests (name, phone, city, dest_city, weight, comment) VALUES (?,?,?,?,?,?)');
$stmt->execute([$name, $phone, $city ?: null, $destCity ?: null, $weight ?: null, $comment ?: null]);

$lines = ["\u{41d}\u{43e}\u{432}\u{430}\u{44f} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430} (\u{43b}\u{435}\u{43d}\u{434}\u{438}\u{43d}\u{433}): {$name}, {$phone}"];
if ($city !== '') { $lines[] = "\u{41e}\u{442}\u{43a}\u{443}\u{434}\u{430}: {$city}"; }
if ($destCity !== '') { $lines[] = "\u{41a}\u{443}\u{434}\u{430}: {$destCity}"; }
if ($weight !== '') { $lines[] = "\u{412}\u{435}\u{441}: {$weight} \u{43a}\u{433}"; }
if ($comment !== '') { $lines[] = "\u{41a}\u{43e}\u{43c}\u{43c}\u{435}\u{43d}\u{442}\u{430}\u{440}\u{438}\u{439}: {$comment}"; }
crm_notify_owner(implode("\n", $lines));

// &#x41f;&#x43e;&#x434;&#x442;&#x432;&#x435;&#x440;&#x436;&#x434;&#x430;&#x435;&#x43c; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x443; &#x43f;&#x440;&#x438;&#x451;&#x43c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x441;&#x440;&#x430;&#x437;&#x443; &#x43f;&#x43e; &#x432;&#x441;&#x435;&#x43c; &#x43a;&#x430;&#x43d;&#x430;&#x43b;&#x430;&#x43c; (MAX/WhatsApp/Telegram/email).
crm_notify_client_lead($pdo, $phone, "\u{ab}" . ($cfg['company_name'] ?? "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}") . "\u{bb}: \u{43f}\u{440}\u{438}\u{43d}\u{44f}\u{43b}\u{438} \u{432}\u{430}\u{448}\u{443} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{443}, {$name}! \u{421}\u{43a}\u{43e}\u{440}\u{43e} \u{441}\u{432}\u{44f}\u{436}\u{435}\u{43c}\u{441}\u{44f} \u{441} \u{432}\u{430}\u{43c}\u{438} \u{434}\u{43b}\u{44f} \u{443}\u{442}\u{43e}\u{447}\u{43d}\u{435}\u{43d}\u{438}\u{44f} \u{434}\u{435}\u{442}\u{430}\u{43b}\u{435}\u{439}.");

capi_respond(['ok' => true]);
