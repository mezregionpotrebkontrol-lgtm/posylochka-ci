<?php
/**
 * Смена пароля по токену из email-ссылки. Страница с формой (reset-password.html)
 * уже есть на сайте и сама шлёт сюда POST {token, password}.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$token = trim((string) ($payload['token'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($token === '' || $password === '') {
    capi_error('Некорректная ссылка или пустой пароль.');
}

$err = capi_consume_email_reset_token($pdo, $token, $password);
if ($err !== null) {
    capi_error($err);
}

capi_respond(['ok' => true]);
