<?php
/**
 * Верхняя часть layout. Переменные, которые должна задать страница до include:
 *   $pageTitle (string) — заголовок страницы
 *   $activeNav (string) — ключ активного пункта меню
 */
$user = crm_current_user();
$companyName = crm_config()['company_name'] ?? 'Посылочка';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? $companyName) ?> — CRM <?= e($companyName) ?></title>
<link rel="stylesheet" href="/crm/assets/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">📦 <?= e($companyName) ?></div>
    <nav>
      <?php if (in_array($user['role'], ['admin','operator'], true)): ?>
        <a href="/crm/index.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Главная</a>
        <a href="/crm/orders.php" class="<?= ($activeNav ?? '') === 'orders' ? 'active' : '' ?>">Заявки</a>
        <a href="/crm/clients.php" class="<?= ($activeNav ?? '') === 'clients' ? 'active' : '' ?>">Клиенты</a>
        <a href="/crm/finance.php" class="<?= ($activeNav ?? '') === 'finance' ? 'active' : '' ?>">Финансы</a>
        <a href="/crm/warehouse.php" class="<?= ($activeNav ?? '') === 'warehouse' ? 'active' : '' ?>">Склад</a>
        <a href="/crm/leads.php" class="<?= ($activeNav ?? '') === 'leads' ? 'active' : '' ?>">Заявки с сайта</a>
        <a href="/crm/reviews.php" class="<?= ($activeNav ?? '') === 'reviews' ? 'active' : '' ?>">Отзывы</a>
        <a href="/crm/reports.php" class="<?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>">Отчёты</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="/crm/pricing.php" class="<?= ($activeNav ?? '') === 'pricing' ? 'active' : '' ?>">Цены</a>
        <a href="/crm/users.php" class="<?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>">Сотрудники</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'courier'): ?>
        <a href="/crm/my-orders.php" class="<?= ($activeNav ?? '') === 'my-orders' ? 'active' : '' ?>">Мои доставки</a>
      <?php endif; ?>
    </nav>
    <div class="user-box">
      <span class="role"><?= e(crm_role_label($user['role'])) ?></span>
      <strong><?= e($user['name']) ?></strong><br>
      <a href="/crm/logout.php">Выйти</a>
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
