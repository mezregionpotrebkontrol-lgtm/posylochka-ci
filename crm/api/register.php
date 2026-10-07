<?php
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
[$client, $err] = capi_register_client($pdo, $payload);
if ($err !== null) {
    capi_error($err);
}

capi_login_client($pdo, (int) $client['id']);
capi_respond(['ok' => true, 'user' => capi_client_public($client)]);
