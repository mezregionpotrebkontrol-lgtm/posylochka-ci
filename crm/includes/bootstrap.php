<?php
/**
 * Общий старт для всех страниц CRM.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$crmConfig = crm_config();
date_default_timezone_set($crmConfig['timezone'] ?? 'Europe/Moscow');

crm_start_session();
