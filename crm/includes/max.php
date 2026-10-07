<?php
/**
 * Отправка уведомлений клиентам в MAX через Green API.
 *
 * Настройки читаются из config.php (ключ 'green_api'). Если apiUrl не
 * заполнен или интеграция выключена — сообщения просто не отправляются,
 * это не ломает работу CRM (смена статуса всё равно сохраняется).
 */

function crm_max_config(): array
{
    $cfg = crm_config();
    $defaults = [
        'enabled'          => false,
        'idInstance'       => '',
        'apiTokenInstance' => '',
        'apiUrl'           => '',
    ];
    return array_merge($defaults, $cfg['green_api'] ?? []);
}

function crm_max_log(string $line): void
{
    $path = __DIR__ . '/../max-notify.log';
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/**
 * Приводит телефон клиента к chatId Green API вида 79001234567@c.us.
 * Поддерживает российские/белорусские номера. Возвращает null, если
 * номер нельзя однозначно привести (слишком короткий, буквы и т.п.).
 */
function crm_phone_to_chat_id(?string $phone): ?string
{
    $digits = crm_phone_digits($phone);
    return $digits ? $digits . '@c.us' : null;
}

/**
 * Отправляет текстовое сообщение в MAX через Green API.
 * Никогда не бросает исключение — при любой ошибке возвращает false
 * и пишет причину в max-notify.log.
 */
function crm_send_max_message(string $chatId, string $text): bool
{
    $cfg = crm_max_config();

    if (empty($cfg['enabled'])) {
        crm_max_log("Пропущено (интеграция выключена в config.php): $chatId");
        return false;
    }
    if ($cfg['idInstance'] === '' || $cfg['apiTokenInstance'] === '') {
        crm_max_log("Пропущено (не заполнены idInstance/apiTokenInstance): $chatId");
        return false;
    }
    if ($cfg['apiUrl'] === '' || stripos($cfg['apiUrl'], 'ЗАМЕНИТЕ') !== false) {
        crm_max_log("Пропущено (не заполнен apiUrl из консоли Green API): $chatId");
        return false;
    }

    $url = rtrim($cfg['apiUrl'], '/') . '/waInstance' . $cfg['idInstance'] . '/sendMessage/' . $cfg['apiTokenInstance'];
    $payload = json_encode(['chatId' => $chatId, 'message' => $text], JSON_UNESCAPED_UNICODE);

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            crm_max_log("Ошибка отправки ($httpCode) $chatId: " . ($error ?: $response));
            return false;
        }
        crm_max_log("Отправлено $chatId: " . mb_substr($text, 0, 60));
        return true;
    } catch (Throwable $e) {
        crm_max_log("Исключение при отправке $chatId: " . $e->getMessage());
        return false;
    }
}

function crm_max_status_message(string $companyName, int $orderId, string $fromCity, string $toCity, string $status): ?string
{
    $route = $fromCity . ' → ' . $toCity;
    switch ($status) {
        case 'new':
            return "«{$companyName}»: приняли вашу заявку №{$orderId} ({$route}). Сообщим, когда она будет принята в обработку.";
        case 'accepted':
            return "«{$companyName}»: заявка №{$orderId} ({$route}) принята в обработку.";
        case 'in_transit':
            return "«{$companyName}»: груз по заявке №{$orderId} ({$route}) в пути.";
        case 'delivered':
            return "«{$companyName}»: заявка №{$orderId} ({$route}) доставлена. Спасибо, что выбираете нас!";
        case 'cancelled':
            return "«{$companyName}»: заявка №{$orderId} ({$route}) отменена. Если это ошибка — свяжитесь с нами.";
        default:
            return null;
    }
}

/**
 * Находит клиента по заявке и, если у него есть телефон, шлёт ему
 * уведомление о новом статусе. Безопасно вызывать всегда — при
 * отсутствии телефона или выключенной интеграции просто ничего не делает.
 */
function crm_notify_client_status(PDO $pdo, array $order, string $newStatus): void
{
    try {
        $stmt = $pdo->prepare('SELECT phone FROM clients WHERE id = ?');
        $stmt->execute([$order['client_id']]);
        $client = $stmt->fetch();
        if (!$client || empty($client['phone'])) {
            return;
        }
        $chatId = crm_phone_to_chat_id($client['phone']);
        if (!$chatId) {
            crm_max_log('Пропущено (не удалось распознать номер телефона): client_id=' . $order['client_id']);
            return;
        }
        $cfg = crm_config();
        $companyName = $cfg['company_name'] ?? 'Посылочка';
        $text = crm_max_status_message($companyName, (int) $order['id'], $order['from_city'] ?? '', $order['to_city'] ?? '', $newStatus);
        if ($text === null) {
            return;
        }
        crm_send_max_message($chatId, $text);
    } catch (Throwable $e) {
        crm_max_log('Исключение в crm_notify_client_status: ' . $e->getMessage());
    }
}
