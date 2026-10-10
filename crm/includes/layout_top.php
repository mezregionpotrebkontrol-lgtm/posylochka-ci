<?php
/**
 * &#x412;&#x435;&#x440;&#x445;&#x43d;&#x44f;&#x44f; &#x447;&#x430;&#x441;&#x442;&#x44c; layout. &#x41f;&#x435;&#x440;&#x435;&#x43c;&#x435;&#x43d;&#x43d;&#x44b;&#x435;, &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x44b;&#x435; &#x434;&#x43e;&#x43b;&#x436;&#x43d;&#x430; &#x437;&#x430;&#x434;&#x430;&#x442;&#x44c; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x430; &#x434;&#x43e; include:
 *   $pageTitle (string) &#x2014; &#x437;&#x430;&#x433;&#x43e;&#x43b;&#x43e;&#x432;&#x43e;&#x43a; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x44b;
 *   $activeNav (string) &#x2014; &#x43a;&#x43b;&#x44e;&#x447; &#x430;&#x43a;&#x442;&#x438;&#x432;&#x43d;&#x43e;&#x433;&#x43e; &#x43f;&#x443;&#x43d;&#x43a;&#x442;&#x430; &#x43c;&#x435;&#x43d;&#x44e;
 */
$user = crm_current_user();
$companyName = crm_config()['company_name'] ?? "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}";
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? $companyName) ?> &#x2014; CRM <?= e($companyName) ?></title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="stylesheet" href="/crm/assets/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand"><img src="/crm/assets/logo.png" alt="<?= e($companyName) ?>"> <span><?= e($companyName) ?></span></div>
    <nav>
      <?php if (in_array($user['role'], ['admin','operator'], true)): ?>
        <a href="/crm/index.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">&#x413;&#x43b;&#x430;&#x432;&#x43d;&#x430;&#x44f;</a>
        <a href="/crm/orders.php" class="<?= ($activeNav ?? '') === 'orders' ? 'active' : '' ?>">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438;</a>
        <a href="/crm/shipments.php" class="<?= ($activeNav ?? '') === 'shipments' ? 'active' : '' ?>">&#x420;&#x435;&#x439;&#x441;&#x44b;</a>
        <a href="/crm/clients.php" class="<?= ($activeNav ?? '') === 'clients' ? 'active' : '' ?>">&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x44b;</a>
        <a href="/crm/finance.php" class="<?= ($activeNav ?? '') === 'finance' ? 'active' : '' ?>">&#x424;&#x438;&#x43d;&#x430;&#x43d;&#x441;&#x44b;</a>
        <a href="/crm/warehouse.php" class="<?= ($activeNav ?? '') === 'warehouse' ? 'active' : '' ?>">&#x421;&#x43a;&#x43b;&#x430;&#x434;</a>
        <a href="/crm/packages-scan.php" class="<?= ($activeNav ?? '') === 'packages-scan' ? 'active' : '' ?>">&#x421;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x435; &#x43c;&#x435;&#x441;&#x442;</a>
        <a href="/crm/leads.php" class="<?= ($activeNav ?? '') === 'leads' ? 'active' : '' ?>">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430;</a>
        <a href="/crm/reviews.php" class="<?= ($activeNav ?? '') === 'reviews' ? 'active' : '' ?>">&#x41e;&#x442;&#x437;&#x44b;&#x432;&#x44b;</a>
        <a href="/crm/claims.php" class="<?= ($activeNav ?? '') === 'claims' ? 'active' : '' ?>">&#x41f;&#x440;&#x435;&#x442;&#x435;&#x43d;&#x437;&#x438;&#x438;</a>
        <a href="/crm/reports.php" class="<?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">&#x41e;&#x442;&#x447;&#x451;&#x442;&#x44b;</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="/crm/pricing.php" class="<?= ($activeNav ?? '') === 'pricing' ? 'active' : '' ?>">&#x426;&#x435;&#x43d;&#x44b;</a>
        <a href="/crm/users.php" class="<?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">&#x421;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x438;</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'courier'): ?>
        <a href="/crm/my-orders.php" class="<?= ($activeNav ?? '') === 'my-orders' ? 'active' : '' ?>">&#x41c;&#x43e;&#x438; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</a>
        <a href="/crm/packages-scan.php" class="<?= ($activeNav ?? '') === 'packages-scan' ? 'active' : '' ?>">&#x421;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x438;&#x435; &#x43c;&#x435;&#x441;&#x442;</a>
      <?php endif; ?>
    </nav>
    <div class="user-box">
      <span class="role"><?= e(crm_role_label($user['role'])) ?></span>
      <strong><?= e($user['name']) ?></strong><br>
      <a href="/crm/logout.php">&#x412;&#x44b;&#x439;&#x442;&#x438;</a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <h1><?= e($pageTitle ?? '') ?></h1>
    </div>
    <div class="content">
      <?php $flash = crm_flash_get(); if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>
