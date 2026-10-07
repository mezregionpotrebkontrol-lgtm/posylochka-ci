<?php
/**
 * Клиент API ЮKassa (создание платежа, проверка статуса).
 * Настройки — в config.php, ключ 'yookassa' (shopId + secretKey из личного
 * кабинета ЮKassa). Документация: https://yookassa.ru/developers/api
 */

function crm_yookassa_config(): array
{
    $cfg = crm_config();
    $defaults = ['enabled' => false, 'shop_id' => '', 'secret_key' => ''];
    return array_merge($defaults, $cfg['yookassa'] ?? []);
}

function crm_yookassa_ready(): bool
{
    $cfg = crm_yookassa_config();
    return !empty($cfg['enabled'])
        && $cfg['shop_id'] !== '' && stripos($cfg['shop_id'], 'ЗАМЕНИТЕ') === false
        && $cfg['secret_key'] !== '' && stripos($cfg['secret_key'], 'ЗАМЕНИТЕ') === false;
}

function crm_yookassa_log(string $line): void
{
    $path = __DIR__ . '/../yookassa.log';
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/**
 * @return array{0:?array,1:?string} [ответ ЮKassa или null, текст ошибки или null]
 */
function crm_yookassa_request(string $method, string $path, ?array $body = null, ?string $idempotenceKey = null): array
{
    $cfg = crm_yookassa_config();
    if (!crm_yookassa_ready()) {
        return [null, 'Приём онлайн-оплаты временно недоступен (не настроен платёжный провайдер).'];
    }

    $base = rtrim($cfg['base_url'] ?? 'https://api.yookassa.ru/v3', '/');
    $url = $base . '/' . ltrim($path, '/');
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($cfg['shop_id'] . ':' . $cfg['secret_key']),
    ];
    if ($idempotenceKey !== null) {
        $headers[] = 'Idempotence-Key: ' . $idempotenceKey;
    }

    try {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            crm_yookassa_log("Сетевая ошибка $method $path: $error");
            return [null, 'Не удалось связаться с платёжным провайдером. Попробуйте позже.'];
        }
        $json = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            crm_yookassa_log("Ошибка $httpCode $method $path: $response");
            return [null, ($json['description'] ?? 'Ошибка платёжного провайдера.')];
        }
        return [is_array($json) ? $json : [], null];
    } catch (Throwable $e) {
        crm_yookassa_log('Исключение: ' . $e->getMessage());
        return [null, 'Ошибка при создании платежа. Попробуйте позже.'];
    }
}

/**
 * Создаёт платёж на сумму $amount (руб.) с редиректом после оплаты на $returnUrl.
 * @return array{0:?array,1:?string} [ответ ЮKassa (payment) или null, ошибка или null]
 */
function crm_yookassa_create_payment(float $amount, string $description, string $returnUrl, array $metadata = []): array
{
    $body = [
        'amount'       => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'RUB'],
        'capture'      => true,
        'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
        'description'  => mb_substr($description, 0, 128),
        'metadata'     => $metadata,
    ];
    return crm_yookassa_request('POST', '/payments', $body, bin2hex(random_bytes(16)));
}

/** Запрашивает у ЮKassa текущий статус платежа — никогда не доверяем телу вебхука напрямую. */
function crm_yookassa_get_payment(string $paymentId): array
{
    return crm_yookassa_request('GET', '/payments/' . rawurlencode($paymentId));
}
