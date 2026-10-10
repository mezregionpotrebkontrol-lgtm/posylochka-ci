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
        'collecting' => 'Сбор груза',
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
        'collecting' => 'badge-purple',
        'in_transit' => 'badge-orange',
        'delivered'  => 'badge-green',
        'cancelled'  => 'badge-red',
    ][$status] ?? 'badge-grey';
}

/**
 * Способ получения груза от отправителя: клиент привозит сам, или нужен
 * выездной сбор курьером по адресу отправителя (from_address).
 */
function crm_pickup_type_label(?string $type): string
{
    return [
        'self'    => 'Самостоятельно (клиент привозит сам)',
        'courier' => 'Выездной сбор (курьер забирает по адресу)',
    ][$type] ?? 'Самостоятельно (клиент привозит сам)';
}

function crm_pickup_type_class(?string $type): string
{
    return [
        'self'    => 'badge-grey',
        'courier' => 'badge-blue',
    ][$type] ?? 'badge-grey';
}

/**
 * Сборный рейс: группа заявок одного маршрута/даты, перевозимых вместе.
 */
function crm_shipment_run_status_label(string $status): string
{
    return [
        'forming'    => 'Формируется',
        'in_transit' => 'В пути',
        'completed'  => 'Завершён',
    ][$status] ?? $status;
}

function crm_shipment_run_status_class(string $status): string
{
    return [
        'forming'    => 'badge-grey',
        'in_transit' => 'badge-orange',
        'completed'  => 'badge-green',
    ][$status] ?? 'badge-grey';
}

function crm_payment_method_label(?string $method): string
{
    return [
        'online'      => 'Онлайн на сайте',
        'cash'        => 'Наличный расчёт',
        'terminal'    => 'Оплата через терминал',
        'invoice'     => 'Оплата по счёту',
        'installment' => 'Рассрочка',
    ][$method] ?? '—';
}

function crm_payment_status_label(string $status): string
{
    return [
        'unpaid'   => 'Не оплачен',
        'postpaid' => 'Постоплата — после доставки',
        'paid'     => 'Оплачен',
    ][$status] ?? $status;
}

function crm_payment_status_class(string $status): string
{
    return [
        'unpaid'   => 'badge-grey',
        'postpaid' => 'badge-orange',
        'paid'     => 'badge-green',
    ][$status] ?? 'badge-grey';
}

/**
 * Итоги по графику рассрочки для одной заявки: общая сумма графика, сколько
 * уже оплачено и сколько осталось.
 */
function crm_order_installments_totals(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT
        COALESCE(SUM(amount), 0) AS total,
        COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END), 0) AS paid,
        COUNT(*) AS cnt,
        COUNT(CASE WHEN status = "paid" THEN 1 END) AS paid_cnt
        FROM order_installments WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    $total = (float) $row['total'];
    $paid = (float) $row['paid'];
    return [
        'total'     => $total,
        'paid'      => $paid,
        'remaining' => $total - $paid,
        'cnt'       => (int) $row['cnt'],
        'paid_cnt'  => (int) $row['paid_cnt'],
    ];
}

function crm_client_type_label(?string $type): string
{
    return [
        'individual' => 'Физическое лицо',
        'company'    => 'Юридическое лицо',
    ][$type] ?? 'Физическое лицо';
}

function crm_claim_reason_label(string $reason): string
{
    return [
        'defect' => 'Брак / повреждение груза',
        'loss'   => 'Утеря груза',
        'return' => 'Возврат',
        'other'  => 'Другое',
    ][$reason] ?? $reason;
}

function crm_claim_status_label(string $status): string
{
    return [
        'open'        => 'Открыта',
        'in_progress' => 'В работе',
        'resolved'    => 'Решена',
        'rejected'    => 'Отказано',
    ][$status] ?? $status;
}

function crm_claim_status_class(string $status): string
{
    return [
        'open'        => 'badge-grey',
        'in_progress' => 'badge-orange',
        'resolved'    => 'badge-green',
        'rejected'    => 'badge-red',
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
