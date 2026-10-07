<?php
/**
 * GET /crm/api/price-quote.php?from_city=Дербент&to_city=Санкт-Петербург&weight_kg=5
 * Используется калькулятором стоимости на сайте — единственный источник цен,
 * тот же, которым пользуется CRM при создании заявки.
 * Ответ: { ok:true, price: 1234.5 } либо { ok:true, price: null } если
 * маршрут ещё не настроен администратором.
 */
require_once __DIR__ . '/_bootstrap.php';

$fromCity = trim($_GET['from_city'] ?? '') ?: 'Дербент';
$toCity = trim($_GET['to_city'] ?? '') ?: 'Санкт-Петербург';
$weightKg = isset($_GET['weight_kg']) && $_GET['weight_kg'] !== '' ? (float) $_GET['weight_kg'] : null;

$price = capi_calc_price($pdo, $fromCity, $toCity, $weightKg);

capi_respond(['ok' => true, 'price' => $price]);
