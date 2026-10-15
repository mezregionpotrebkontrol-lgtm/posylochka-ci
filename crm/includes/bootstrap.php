<?php
/**
 * &#x41e;&#x431;&#x449;&#x438;&#x439; &#x441;&#x442;&#x430;&#x440;&#x442; &#x434;&#x43b;&#x44f; &#x432;&#x441;&#x435;&#x445; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446; CRM.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/max.php';
require_once __DIR__ . '/email.php';

$crmConfig = crm_config();
date_default_timezone_set($crmConfig['timezone'] ?? 'Europe/Moscow');

crm_start_session();
