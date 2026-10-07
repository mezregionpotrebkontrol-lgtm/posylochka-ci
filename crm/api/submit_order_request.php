<?php
/** Заявка "оформить доставку" с лендинга (index.html/dostavka.html, без оплаты). */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$name = trim((string) ($payload['name'] ?? ''));
$phone = trim((string) ($payload['phone'] ?? ''));
$city = trim((string) ($payload['city'] ?? ''));
$destCity = trim((string) ($payload['dest_city'] ?? ''));
$weight = trim((string) ($payload['weight'] ?? ''));
$comment = trim((string) ($payload['comment'] ?? ''));

if (mb_strlen($name) < 2) {
    capi_error('Укажите имя.');
}
if (mb_strlen($phone) < 5) {
    capi_error('Укажите телефон.');
}

// Простая защита от спама: не больше одной заявки с одного браузера за 2 минуты
// (как на реальном сайте сейчас) — отвечаем как обычно, просто не дублируем запись.
capi_start_session();
$lastKey = 'last_order_request_at';
if (isset($_SESSION[$lastKey]) && (time() - $_SESSION[$lastKey]) < 120) {
    capi_respond(['ok' => true]);
}
$_SESSION[$lastKey] = time();

$stmt = $pdo->prepare('INSERT INTO site_order_requests (name, phone, city, dest_city, weight, comment) VALUES (?,?,?,?,?,?)');
$stmt->execute([$name, $phone, $city ?: null, $destCity ?: null, $weight ?: null, $comment ?: null]);

$lines = ["Новая заявка с сайта (лендинг): {$name}, {$phone}"];
if ($city !== '') { $lines[] = "Откуда: {$city}"; }
if ($destCity !== '') { $lines[] = "Куда: {$destCity}"; }
if ($weight !== '') { $lines[] = "Вес: {$weight} кг"; }
if ($comment !== '') { $lines[] = "Комментарий: {$comment}"; }
crm_notify_owner(implode("\n", $lines));

capi_respond(['ok' => true]);
