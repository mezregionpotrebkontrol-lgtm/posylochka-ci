<?php
/**
 * Webhook ЮKassa: уведомление о смене статуса платежа. Согласно
 * рекомендациям безопасности ЮKassa, тело запроса НЕ считается
 * доверенным — сервер сам запрашивает актуальный статус платежа по его id.
 * Настройте URL этого файла в личном кабинете ЮKassa → Настройки → HTTP-уведомления.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$body = capi_json_input();
$paymentId = $body['object']['id'] ?? null;
if (!$paymentId) {
    http_response_code(400);
    exit;
}

[$payment, $err] = crm_yookassa_get_payment($paymentId);
if ($err !== null || empty($payment['status'])) {
    crm_yookassa_log('Webhook: не удалось проверить платёж ' . $paymentId . ': ' . $err);
    http_response_code(200); // отвечаем 200, чтобы ЮKassa не долбила повторами бесконечно
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM yookassa_payments WHERE yk_payment_id = ? LIMIT 1');
$stmt->execute([$paymentId]);
$row = $stmt->fetch();
if (!$row) {
    crm_yookassa_log('Webhook: платёж не найден в базе ' . $paymentId);
    http_response_code(200);
    exit;
}

$status = $payment['status'];
$pdo->prepare('UPDATE yookassa_payments SET status = ? WHERE id = ?')->execute([$status, $row['id']]);

if ($status === 'succeeded' && $row['status'] !== 'succeeded') {
    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $orderStmt->execute([(int) $row['order_id']]);
    $order = $orderStmt->fetch();
    if ($order) {
        $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$order['id']]);
        if ($order['status'] === 'new') {
            $pdo->prepare("UPDATE orders SET status = 'accepted' WHERE id = ?")->execute([$order['id']]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, \'accepted\', NULL, \'Оплата получена онлайн\')')
                ->execute([$order['id']]);
            crm_notify_client_status($pdo, $order, 'accepted');
        }
        crm_notify_owner('Оплата получена по заявке №' . $order['id'] . ' (' . $row['amount'] . ' ₽).');
    }
}

http_response_code(200);
