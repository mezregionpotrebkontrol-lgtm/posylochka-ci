<?php
/** Заявка на сотрудничество (B2B-модалка на лендинге). */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
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
    capi_error('Укажите организацию, контактное лицо и телефон.');
}

$stmt = $pdo->prepare('INSERT INTO business_requests (company, name, phone, email, volume, cities, comment) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$company, $name, $phone, $email ?: null, $volume ?: null, $cities ?: null, $comment ?: null]);

$lines = ["Заявка на сотрудничество (B2B): {$company}, контакт {$name}, {$phone}"];
if ($email !== '') { $lines[] = "Email: {$email}"; }
if ($volume !== '') { $lines[] = "Объём: {$volume} кг/мес"; }
if ($cities !== '') { $lines[] = "Города: {$cities}"; }
if ($comment !== '') { $lines[] = "Комментарий: {$comment}"; }
crm_notify_owner(implode("\n", $lines));

capi_respond(['ok' => true]);
