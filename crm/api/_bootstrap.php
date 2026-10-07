<?php
/**
 * Общий старт для публичных API-эндпоинтов сайта/приложения.
 * В отличие от includes/bootstrap.php (для CRM), здесь НЕТ требования
 * входа сотрудника — эти файлы вызывает сайт от имени посетителя/клиента.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/max.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/yookassa.php';
require_once __DIR__ . '/../includes/clientapi.php';

$crmConfig = crm_config();
date_default_timezone_set($crmConfig['timezone'] ?? 'Europe/Moscow');

capi_cors();

$pdo = crm_db();
