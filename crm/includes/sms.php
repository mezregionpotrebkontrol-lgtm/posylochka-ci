<?php
/**
 * Отправка SMS через SMS.ru (простой GET-запрос).
 * Настройки — в config.php, ключ 'sms_ru'. Если api_id не заполнен или
 * интеграция выключена — сообщение просто не отправляется (не ломает запрос).
 */

function crm_sms_config(): array
{
    $cfg = crm_config();
    $defaults = ['enabled' => false, 'api_id' => ''];
    return array_merge($defaults, $cfg['sms_ru'] ?? []);
}

function crm_sms_log(string $line): void
{
    $path = __DIR__ . '/../sms-notify.log';
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/**
 * Отправляет SMS на номер (в любом читаемом формате — сами приведём к 7XXXXXXXXXX).
 * Никогда не бросает исключение — при ошибке возвращает false и пишет в лог.
 */
function crm_send_sms(string $phone, string $text): bool
{
    $cfg = crm_sms_config();
    $digits = crm_phone_digits($phone);

    if (empty($cfg['enabled'])) {
        crm_sms_log("Пропущено (интеграция выключена в config.php): $phone");
        return false;
    }
    if ($cfg['api_id'] === '' || stripos($cfg['api_id'], 'ЗАМЕНИТЕ') !== false) {
        crm_sms_log("Пропущено (не заполнен api_id из личного кабинета SMS.ru): $phone");
        return false;
    }
    if (!$digits) {
        crm_sms_log("Пропущено (не удалось распознать номер телефона): $phone");
        return false;
    }

    $base = rtrim($cfg['base_url'] ?? 'https://sms.ru/sms/send', '/');
    $url = $base . '?' . http_build_query([
        'api_id' => $cfg['api_id'],
        'to'     => $digits,
        'msg'    => $text,
        'json'   => 1,
    ]);

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            crm_sms_log("Ошибка отправки ($httpCode) $digits: " . ($error ?: $response));
            return false;
        }
        $json = json_decode($response, true);
        if (!is_array($json) || ($json['status'] ?? '') !== 'OK') {
            crm_sms_log("Ошибка SMS.ru $digits: " . $response);
            return false;
        }
        crm_sms_log("Отправлено $digits: " . mb_substr($text, 0, 60));
        return true;
    } catch (Throwable $e) {
        crm_sms_log("Исключение при отправке $digits: " . $e->getMessage());
        return false;
    }
}
