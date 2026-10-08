<?php
/**
 * Онлайн-оплата заявки ("Оплатить онлайн" на сайте). Принимает ДВА возможных
 * формата запроса (сайт использует разные формы на разных страницах):
 *
 *  - app.html (личный кабинет, полная форма): {name, phone, city: "A → B, адрес",
 *    weight, comment, addons:{...}, origin_zone, dest_zone, route_km}
 *  - index.html/dostavka.html (упрощённая форма): {name, phone, city: "A",
 *    dest_city: "B", weight, comment} — без addons.
 *
 * Зоны/километраж/итог, присланные браузером, ИГНОРИРУЮТСЯ: сервер всегда
 * пересчитывает их сам по тем же данным и формуле (includes/pricing-formula.php),
 * иначе сумму платежа можно подменить в devtools.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();

$name = trim((string) ($payload['name'] ?? ''));
$phoneDigits = crm_phone_digits($payload['phone'] ?? null);
$cityRaw = trim((string) ($payload['city'] ?? ''));
$destCityRaw = trim((string) ($payload['dest_city'] ?? ''));
$weight = (float) ($payload['weight'] ?? 0);
$comment = trim((string) ($payload['comment'] ?? ''));
$addons = is_array($payload['addons'] ?? null) ? $payload['addons'] : [];

if ($name === '' || !$phoneDigits) {
    capi_error('Укажите имя и телефон.');
}

$toAddress = null;
if ($destCityRaw !== '') {
    // Упрощённая форма: city и dest_city — отдельные поля.
    $originCity = $cityRaw;
    $destCity = $destCityRaw;
} else {
    // Полная форма: city = "Откуда → Куда, адрес".
    [$originCity, $destCity, $toAddress] = capi_split_combined_city($cityRaw);
}

if ($originCity === '' || $destCity === '' || $weight <= 0) {
    capi_error('Укажите города отправления/получения и вес груза.');
}

$totals = crm_calc_checkout_total($originCity, $destCity, $weight, $addons);
if ($totals === null) {
    capi_error('Не удалось рассчитать стоимость — проверьте города (выберите из подсказок) и вес.');
}

if (capi_order_rate_limited($pdo, $phoneDigits)) {
    capi_error('Слишком много заявок с этого номера за последние 10 минут. Попробуйте позже или позвоните нам.', 429);
}

if (!crm_yookassa_ready()) {
    capi_error('Приём онлайн-оплаты временно недоступен. Пожалуйста, оформите заявку без оплаты или свяжитесь с нами.', 503);
}

$clientId = capi_find_or_create_client($pdo, $name, $phoneDigits);
$declaredValue = !empty($addons['insure_value']) ? (float) $addons['insure_value'] : null;

[$orderId, $trackCode] = capi_create_order_row(
    $pdo, $clientId, $originCity, $destCity, $toAddress,
    $weight, $declaredValue, $totals['total'], $comment, 'site'
);

$cfg = crm_config();
$baseUrl = rtrim($cfg['site']['base_url'] ?? '', '/');
$returnUrl = $baseUrl . '/app.html?paid_track=' . rawurlencode($trackCode);

[$payment, $err] = crm_yookassa_create_payment(
    (float) $totals['total'],
    'Заявка №' . $orderId . ' (' . $originCity . ' → ' . $destCity . ')',
    $returnUrl,
    ['order_id' => $orderId, 'track_code' => $trackCode],
    '+' . $phoneDigits
);

if ($err !== null || empty($payment['id']) || empty($payment['confirmation']['confirmation_url'])) {
    capi_error($err ?: 'Не удалось создать платёж. Попробуйте позже.', 502);
}

$pdo->prepare('INSERT INTO yookassa_payments (order_id, yk_payment_id, amount, confirmation_url, status) VALUES (?,?,?,?,?)')
    ->execute([$orderId, $payment['id'], $totals['total'], $payment['confirmation']['confirmation_url'], $payment['status'] ?? 'pending']);

crm_notify_owner("Новая заявка №{$orderId} с сайта (оплата онлайн): {$originCity} → {$destCity}, {$weight} кг, {$totals['total']} ₽. Трек: {$trackCode}.");

capi_respond(['ok' => true, 'confirmation_url' => $payment['confirmation']['confirmation_url'], 'track' => $trackCode]);
