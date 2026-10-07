<?php
require_once __DIR__ . '/_bootstrap.php';

capi_logout_client();
capi_respond(['ok' => true]);
