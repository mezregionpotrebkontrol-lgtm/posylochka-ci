<?php
/**
 * Общий старт для задач планировщика (cron). Запуск только из командной строки.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Только для запуска из планировщика.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/max.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/clientapi.php';
require_once __DIR__ . '/../includes/audit.php';

date_default_timezone_set(crm_config()['timezone'] ?? 'Europe/Moscow');

function cron_log(string $line): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
}
