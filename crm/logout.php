<?php
require_once __DIR__ . '/includes/bootstrap.php';
crm_logout();
crm_redirect('/crm/login.php');
