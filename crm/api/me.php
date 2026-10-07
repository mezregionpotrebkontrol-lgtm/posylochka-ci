<?php
require_once __DIR__ . '/_bootstrap.php';

$client = capi_current_client($pdo);
capi_respond(['ok' => true, 'user' => $client ? capi_client_public($client) : null]);
