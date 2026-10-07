<?php
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$phoneDigits = crm_phone_digits($payload['phone'] ?? null);
$code = trim((string) ($payload['code'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if (!$phoneDigits || $code === '' || $password === '') {
    capi_error('Заполните телефон, код и новый пароль.');
}

$err = capi_verify_phone_reset($pdo, $phoneDigits, $code, $password);
if ($err !== null) {
    capi_error($err);
}

capi_respond(['ok' => true]);
