<?php
/**
 * Простое оформление заявки без онлайн-оплаты (форма "orderForm" в app.html —
 * отправитель из Дербента, получатель в СПб приходит сам за грузом/курьер).
 * Payload: {service, name, phone, address, weight, comment, origin_city, dest_city}
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$service = trim((string) ($payload['service'] ?? ''));
$name = trim((string) ($payload['name'] ?? ''));
$phoneDigits = crm_phone_digits($payload['phone'] ?? null);
$address = trim((string) ($payload['address'] ?? ''));
$weightRaw = trim((string) ($payload['weight'] ?? ''));
$comment = trim((string) ($payload['comment'] ?? ''));
$originCity = trim((string) ($payload['origin_city'] ?? '')) ?: 'Дербент';
$destCity = trim((string) ($payload['dest_city'] ?? '')) ?: 'Санкт-Петербург';

$allowedServices = ['Посылка', 'Продукты', 'B2B'];
if (!in_array($service, $allowedServices, true)) {
    capi_error('Выберите вид отправления.');
}
if ($name === '' || !$phoneDigits) {
    capi_error('Укажите имя и телефон.');
}
if (capi_order_rate_limited($pdo, $phoneDigits)) {
    capi_error('Слишком много заявок с этого номера за последние 10 минут. Попробуйте позже или позвоните нам.', 429);
}

$weightKg = null;
if ($weightRaw !== '' && preg_match('/[\d.,]+/', $weightRaw, $m)) {
    $weightKg = (float) str_replace(',', '.', $m[0]);
}

$clientId = capi_find_or_create_client($pdo, $name, $phoneDigits);
[$orderId, $trackCode] = capi_create_order_row(
    $pdo, $clientId, $originCity, $destCity, $address ?: null,
    $weightKg, null, null, $comment !== '' ? $comment : null, 'site', $service
);

crm_notify_owner("Новая заявка №{$orderId} с сайта (без онлайн-оплаты): {$originCity} → {$destCity}"
    . ($weightRaw !== '' ? ", {$weightRaw}" : '') . ". Телефон: +{$phoneDigits}. Трек: {$trackCode}.");

capi_respond(['ok' => true, 'track' => $trackCode]);
