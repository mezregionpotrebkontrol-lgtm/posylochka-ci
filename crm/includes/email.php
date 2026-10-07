<?php
/**
 * Отправка простых писем (восстановление пароля по email). Использует
 * встроенную mail() PHP — на обычном хостинге (reg.ru и т.п.) этого
 * достаточно для разовых писем от своего домена. Никогда не бросает
 * исключение — при ошибке просто пишет в лог и возвращает false.
 */

function crm_email_log(string $line): void
{
    $path = __DIR__ . '/../email-notify.log';
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

function crm_send_email(string $to, string $subject, string $body): bool
{
    $cfg = crm_config();
    $fromEmail = $cfg['mail']['from_email'] ?? 'noreply@localhost';
    $fromName = $cfg['mail']['from_name'] ?? ($cfg['company_name'] ?? 'Посылочка');

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
        'Content-Type: text/plain; charset=utf-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    try {
        $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers));
        crm_email_log(($ok ? 'Отправлено ' : 'Не удалось отправить ') . $to . ': ' . $subject);
        return $ok;
    } catch (Throwable $e) {
        crm_email_log('Исключение при отправке ' . $to . ': ' . $e->getMessage());
        return false;
    }
}
