<?php
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$phoneDigits = crm_phone_digits($payload['phone'] ?? null);
if (!$phoneDigits) {
    capi_error('Укажите корректный номер телефона.');
}

capi_request_phone_reset_code($pdo, $phoneDigits);

// Не раскрываем, зарегистрирован ли номер — ответ всегда одинаковый.
capi_respond(['ok' => true, 'message' => 'Если такой телефон зарегистрирован, мы отправили на него код (в MAX и по SMS).']);
