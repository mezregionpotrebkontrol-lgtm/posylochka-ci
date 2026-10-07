<?php
/**
 * Общие вспомогательные функции.
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_order_status_label(string $status): string
{
    return [
        'new'        => 'Новая',
        'accepted'   => 'Принята',
        'in_transit' => 'В пути',
        'delivered'  => 'Доставлена',
        'cancelled'  => 'Отменена',
    ][$status] ?? $status;
}

function crm_order_status_class(string $status): string
{
    return [
        'new'        => 'badge-grey',
        'accepted'   => 'badge-blue',
        'in_transit' => 'badge-orange',
        'delivered'  => 'badge-green',
        'cancelled'  => 'badge-red',
    ][$status] ?? 'badge-grey';
}

function crm_invoice_status_label(string $status): string
{
    return [
        'draft'     => 'Черновик',
        'sent'      => 'Выставлен',
        'paid'      => 'Оплачен',
        'cancelled' => 'Отменён',
    ][$status] ?? $status;
}

function crm_money(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }
    return number_format($amount, 2, ',', ' ') . ' ₽';
}

function crm_date(?string $datetime, string $format = 'd.m.Y H:i'): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

function crm_next_invoice_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM invoices WHERE number LIKE ?");
    $stmt->execute([$year . '-%']);
    $count = (int) $stmt->fetch()['cnt'] + 1;
    return sprintf('%s-%04d', $year, $count);
}

function crm_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function crm_flash_set(string $message, string $type = 'ok'): void
{
    crm_start_session();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function crm_flash_get(): ?array
{
    crm_start_session();
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Приводит телефон к каноническому виду "79001234567" (11 цифр, начинается с 7).
 * Поддерживает российские/белорусские номера. Возвращает null, если номер
 * нельзя однозначно привести (слишком короткий, иностранный формат и т.п.).
 */
function crm_phone_digits(?string $phone): ?string
{
    if (!$phone) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    } elseif (strlen($digits) === 10) {
        $digits = '7' . $digits;
    }
    if (strlen($digits) !== 11 || $digits[0] !== '7') {
        return null;
    }
    return $digits;
}
