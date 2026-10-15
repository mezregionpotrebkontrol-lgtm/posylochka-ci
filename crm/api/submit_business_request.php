<?php
/** &#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x43d;&#x430; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x447;&#x435;&#x441;&#x442;&#x432;&#x43e; (B2B-&#x43c;&#x43e;&#x434;&#x430;&#x43b;&#x43a;&#x430; &#x43d;&#x430; &#x43b;&#x435;&#x43d;&#x434;&#x438;&#x43d;&#x433;&#x435;). */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error("\u{41c}\u{435}\u{442}\u{43e}\u{434} \u{43d}\u{435} \u{43f}\u{43e}\u{434}\u{434}\u{435}\u{440}\u{436}\u{438}\u{432}\u{430}\u{435}\u{442}\u{441}\u{44f}.", 405);
}

$payload = capi_json_input();
$company = trim((string) ($payload['company'] ?? ''));
$name = trim((string) ($payload['name'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$volume = trim((string) ($payload['volume'] ?? ''));
$cities = trim((string) ($payload['cities'] ?? ''));
$comment = trim((string) ($payload['comment'] ?? ''));

if ($company === '' || $name === '' || $phone === '') {
    capi_error("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{43e}\u{440}\u{433}\u{430}\u{43d}\u{438}\u{437}\u{430}\u{446}\u{438}\u{44e}, \u{43a}\u{43e}\u{43d}\u{442}\u{430}\u{43a}\u{442}\u{43d}\u{43e}\u{435} \u{43b}\u{438}\u{446}\u{43e} \u{438} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}.");
}

$stmt = $pdo->prepare('INSERT INTO business_requests (company, name, phone, email, volume, cities, comment) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$company, $name, $phone, $email ?: null, $volume ?: null, $cities ?: null, $comment ?: null]);

$lines = ["\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43d}\u{430} \u{441}\u{43e}\u{442}\u{440}\u{443}\u{434}\u{43d}\u{438}\u{447}\u{435}\u{441}\u{442}\u{432}\u{43e} (B2B): {$company}, \u{43a}\u{43e}\u{43d}\u{442}\u{430}\u{43a}\u{442} {$name}, {$phone}"];
if ($email !== '') { $lines[] = "Email: {$email}"; }
if ($volume !== '') { $lines[] = "\u{41e}\u{431}\u{44a}\u{451}\u{43c}: {$volume} \u{43a}\u{433}/\u{43c}\u{435}\u{441}"; }
if ($cities !== '') { $lines[] = "\u{413}\u{43e}\u{440}\u{43e}\u{434}\u{430}: {$cities}"; }
if ($comment !== '') { $lines[] = "\u{41a}\u{43e}\u{43c}\u{43c}\u{435}\u{43d}\u{442}\u{430}\u{440}\u{438}\u{439}: {$comment}"; }
crm_notify_owner(implode("\n", $lines));

// &#x41f;&#x43e;&#x434;&#x442;&#x432;&#x435;&#x440;&#x436;&#x434;&#x430;&#x435;&#x43c; &#x43a;&#x43e;&#x43d;&#x442;&#x430;&#x43a;&#x442;&#x43d;&#x43e;&#x43c;&#x443; &#x43b;&#x438;&#x446;&#x443; &#x43f;&#x440;&#x438;&#x451;&#x43c; B2B-&#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x43f;&#x43e; &#x432;&#x441;&#x435;&#x43c; &#x43a;&#x430;&#x43d;&#x430;&#x43b;&#x430;&#x43c; (email &#x443;&#x436;&#x435; &#x435;&#x441;&#x442;&#x44c; &#x432; &#x444;&#x43e;&#x440;&#x43c;&#x435;).
crm_notify_client_lead($pdo, $phone, "\u{ab}" . (crm_config()['company_name'] ?? "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}") . "\u{bb}: \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43d}\u{430} \u{441}\u{43e}\u{442}\u{440}\u{443}\u{434}\u{43d}\u{438}\u{447}\u{435}\u{441}\u{442}\u{432}\u{43e} \u{43e}\u{442} {$company} \u{43f}\u{440}\u{438}\u{43d}\u{44f}\u{442}\u{430}. \u{421}\u{432}\u{44f}\u{436}\u{435}\u{43c}\u{441}\u{44f} \u{441} \u{432}\u{430}\u{43c}\u{438} \u{432} \u{431}\u{43b}\u{438}\u{436}\u{430}\u{439}\u{448}\u{435}\u{435} \u{432}\u{440}\u{435}\u{43c}\u{44f}.", $email !== '' ? $email : null);

capi_respond(['ok' => true]);
