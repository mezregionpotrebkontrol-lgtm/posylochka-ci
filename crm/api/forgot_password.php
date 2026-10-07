<?php
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$email = trim((string) ($payload['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    capi_error('Укажите корректный email.');
}

capi_request_email_reset($pdo, $email);

// Не раскрываем, зарегистрирован ли email — ответ всегда одинаковый.
capi_respond(['ok' => true, 'message' => 'Если такой email зарегистрирован, мы отправили на него ссылку для восстановления пароля.']);
