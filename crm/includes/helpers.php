<?php
/**
 * &#x41e;&#x431;&#x449;&#x438;&#x435; &#x432;&#x441;&#x43f;&#x43e;&#x43c;&#x43e;&#x433;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x435; &#x444;&#x443;&#x43d;&#x43a;&#x446;&#x438;&#x438;.
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function crm_order_status_label(string $status): string
{
    return [
        'new'        => "\u{41d}\u{43e}\u{432}\u{430}\u{44f}",
        'accepted'   => "\u{41f}\u{440}\u{438}\u{43d}\u{44f}\u{442}\u{430}",
        'collecting' => "\u{421}\u{431}\u{43e}\u{440} \u{433}\u{440}\u{443}\u{437}\u{430}",
        'in_transit' => "\u{412} \u{43f}\u{443}\u{442}\u{438}",
        'delivered'  => "\u{414}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{430}",
        'cancelled'  => "\u{41e}\u{442}\u{43c}\u{435}\u{43d}\u{435}\u{43d}\u{430}",
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
 * &#x421;&#x43f;&#x43e;&#x441;&#x43e;&#x431; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43e;&#x442; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f;: &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442; &#x43f;&#x440;&#x438;&#x432;&#x43e;&#x437;&#x438;&#x442; &#x441;&#x430;&#x43c;, &#x438;&#x43b;&#x438; &#x43d;&#x443;&#x436;&#x435;&#x43d;
 * &#x432;&#x44b;&#x435;&#x437;&#x434;&#x43d;&#x43e;&#x439; &#x441;&#x431;&#x43e;&#x440; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x43e;&#x43c; &#x43f;&#x43e; &#x430;&#x434;&#x440;&#x435;&#x441;&#x443; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f; (from_address).
 */
function crm_pickup_type_label(?string $type): string
{
    return [
        'self'    => "\u{421}\u{430}\u{43c}\u{43e}\u{441}\u{442}\u{43e}\u{44f}\u{442}\u{435}\u{43b}\u{44c}\u{43d}\u{43e} (\u{43a}\u{43b}\u{438}\u{435}\u{43d}\u{442} \u{43f}\u{440}\u{438}\u{432}\u{43e}\u{437}\u{438}\u{442} \u{441}\u{430}\u{43c})",
        'courier' => "\u{412}\u{44b}\u{435}\u{437}\u{434}\u{43d}\u{43e}\u{439} \u{441}\u{431}\u{43e}\u{440} (\u{43a}\u{443}\u{440}\u{44c}\u{435}\u{440} \u{437}\u{430}\u{431}\u{438}\u{440}\u{430}\u{435}\u{442} \u{43f}\u{43e} \u{430}\u{434}\u{440}\u{435}\u{441}\u{443})",
    ][$type] ?? "\u{421}\u{430}\u{43c}\u{43e}\u{441}\u{442}\u{43e}\u{44f}\u{442}\u{435}\u{43b}\u{44c}\u{43d}\u{43e} (\u{43a}\u{43b}\u{438}\u{435}\u{43d}\u{442} \u{43f}\u{440}\u{438}\u{432}\u{43e}\u{437}\u{438}\u{442} \u{441}\u{430}\u{43c})";
}

function crm_pickup_type_class(?string $type): string
{
    return [
        'self'    => 'badge-grey',
        'courier' => 'badge-blue',
    ][$type] ?? 'badge-grey';
}

/**
 * &#x421;&#x431;&#x43e;&#x440;&#x43d;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441;: &#x433;&#x440;&#x443;&#x43f;&#x43f;&#x430; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a; &#x43e;&#x434;&#x43d;&#x43e;&#x433;&#x43e; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;&#x430;/&#x434;&#x430;&#x442;&#x44b;, &#x43f;&#x435;&#x440;&#x435;&#x432;&#x43e;&#x437;&#x438;&#x43c;&#x44b;&#x445; &#x432;&#x43c;&#x435;&#x441;&#x442;&#x435;.
 */
function crm_shipment_run_status_label(string $status): string
{
    return [
        'forming'    => "\u{424}\u{43e}\u{440}\u{43c}\u{438}\u{440}\u{443}\u{435}\u{442}\u{441}\u{44f}",
        'in_transit' => "\u{412} \u{43f}\u{443}\u{442}\u{438}",
        'completed'  => "\u{417}\u{430}\u{432}\u{435}\u{440}\u{448}\u{451}\u{43d}",
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
        'online'      => "\u{41e}\u{43d}\u{43b}\u{430}\u{439}\u{43d} \u{43d}\u{430} \u{441}\u{430}\u{439}\u{442}\u{435}",
        'cash'        => "\u{41d}\u{430}\u{43b}\u{438}\u{447}\u{43d}\u{44b}\u{439} \u{440}\u{430}\u{441}\u{447}\u{451}\u{442}",
        'terminal'    => "\u{41e}\u{43f}\u{43b}\u{430}\u{442}\u{430} \u{447}\u{435}\u{440}\u{435}\u{437} \u{442}\u{435}\u{440}\u{43c}\u{438}\u{43d}\u{430}\u{43b}",
        'invoice'     => "\u{41e}\u{43f}\u{43b}\u{430}\u{442}\u{430} \u{43f}\u{43e} \u{441}\u{447}\u{451}\u{442}\u{443}",
        'installment' => "\u{420}\u{430}\u{441}\u{441}\u{440}\u{43e}\u{447}\u{43a}\u{430}",
    ][$method] ?? "\u{2014}";
}

function crm_payment_status_label(string $status): string
{
    return [
        'unpaid'   => "\u{41d}\u{435} \u{43e}\u{43f}\u{43b}\u{430}\u{447}\u{435}\u{43d}",
        'postpaid' => "\u{41f}\u{43e}\u{441}\u{442}\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{430} \u{2014} \u{43f}\u{43e}\u{441}\u{43b}\u{435} \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43a}\u{438}",
        'paid'     => "\u{41e}\u{43f}\u{43b}\u{430}\u{447}\u{435}\u{43d}",
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
 * &#x418;&#x442;&#x43e;&#x433;&#x438; &#x43f;&#x43e; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a;&#x443; &#x440;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x438; &#x434;&#x43b;&#x44f; &#x43e;&#x434;&#x43d;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;: &#x43e;&#x431;&#x449;&#x430;&#x44f; &#x441;&#x443;&#x43c;&#x43c;&#x430; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a;&#x430;, &#x441;&#x43a;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e;
 * &#x443;&#x436;&#x435; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x43e; &#x438; &#x441;&#x43a;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x43e;&#x441;&#x442;&#x430;&#x43b;&#x43e;&#x441;&#x44c;.
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
        'individual' => "\u{424}\u{438}\u{437}\u{438}\u{447}\u{435}\u{441}\u{43a}\u{43e}\u{435} \u{43b}\u{438}\u{446}\u{43e}",
        'company'    => "\u{42e}\u{440}\u{438}\u{434}\u{438}\u{447}\u{435}\u{441}\u{43a}\u{43e}\u{435} \u{43b}\u{438}\u{446}\u{43e}",
    ][$type] ?? "\u{424}\u{438}\u{437}\u{438}\u{447}\u{435}\u{441}\u{43a}\u{43e}\u{435} \u{43b}\u{438}\u{446}\u{43e}";
}

function crm_claim_reason_label(string $reason): string
{
    return [
        'defect' => "\u{411}\u{440}\u{430}\u{43a} / \u{43f}\u{43e}\u{432}\u{440}\u{435}\u{436}\u{434}\u{435}\u{43d}\u{438}\u{435} \u{433}\u{440}\u{443}\u{437}\u{430}",
        'loss'   => "\u{423}\u{442}\u{435}\u{440}\u{44f} \u{433}\u{440}\u{443}\u{437}\u{430}",
        'return' => "\u{412}\u{43e}\u{437}\u{432}\u{440}\u{430}\u{442}",
        'other'  => "\u{414}\u{440}\u{443}\u{433}\u{43e}\u{435}",
    ][$reason] ?? $reason;
}

function crm_claim_status_label(string $status): string
{
    return [
        'open'        => "\u{41e}\u{442}\u{43a}\u{440}\u{44b}\u{442}\u{430}",
        'in_progress' => "\u{412} \u{440}\u{430}\u{431}\u{43e}\u{442}\u{435}",
        'resolved'    => "\u{420}\u{435}\u{448}\u{435}\u{43d}\u{430}",
        'rejected'    => "\u{41e}\u{442}\u{43a}\u{430}\u{437}\u{430}\u{43d}\u{43e}",
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
        'draft'     => "\u{427}\u{435}\u{440}\u{43d}\u{43e}\u{432}\u{438}\u{43a}",
        'sent'      => "\u{412}\u{44b}\u{441}\u{442}\u{430}\u{432}\u{43b}\u{435}\u{43d}",
        'paid'      => "\u{41e}\u{43f}\u{43b}\u{430}\u{447}\u{435}\u{43d}",
        'cancelled' => "\u{41e}\u{442}\u{43c}\u{435}\u{43d}\u{451}\u{43d}",
    ][$status] ?? $status;
}

function crm_money(?float $amount): string
{
    if ($amount === null) {
        return "\u{2014}";
    }
    return number_format($amount, 2, ',', ' ') . " \u{20bd}";
}

function crm_date(?string $datetime, string $format = 'd.m.Y H:i'): string
{
    if (!$datetime) {
        return "\u{2014}";
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : "\u{2014}";
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
 * &#x41f;&#x440;&#x438;&#x432;&#x43e;&#x434;&#x438;&#x442; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d; &#x43a; &#x43a;&#x430;&#x43d;&#x43e;&#x43d;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x43e;&#x43c;&#x443; &#x432;&#x438;&#x434;&#x443; "79001234567" (11 &#x446;&#x438;&#x444;&#x440;, &#x43d;&#x430;&#x447;&#x438;&#x43d;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x441; 7).
 * &#x41f;&#x43e;&#x434;&#x434;&#x435;&#x440;&#x436;&#x438;&#x432;&#x430;&#x435;&#x442; &#x440;&#x43e;&#x441;&#x441;&#x438;&#x439;&#x441;&#x43a;&#x438;&#x435;/&#x431;&#x435;&#x43b;&#x43e;&#x440;&#x443;&#x441;&#x441;&#x43a;&#x438;&#x435; &#x43d;&#x43e;&#x43c;&#x435;&#x440;&#x430;. &#x412;&#x43e;&#x437;&#x432;&#x440;&#x430;&#x449;&#x430;&#x435;&#x442; null, &#x435;&#x441;&#x43b;&#x438; &#x43d;&#x43e;&#x43c;&#x435;&#x440;
 * &#x43d;&#x435;&#x43b;&#x44c;&#x437;&#x44f; &#x43e;&#x434;&#x43d;&#x43e;&#x437;&#x43d;&#x430;&#x447;&#x43d;&#x43e; &#x43f;&#x440;&#x438;&#x432;&#x435;&#x441;&#x442;&#x438; (&#x441;&#x43b;&#x438;&#x448;&#x43a;&#x43e;&#x43c; &#x43a;&#x43e;&#x440;&#x43e;&#x442;&#x43a;&#x438;&#x439;, &#x438;&#x43d;&#x43e;&#x441;&#x442;&#x440;&#x430;&#x43d;&#x43d;&#x44b;&#x439; &#x444;&#x43e;&#x440;&#x43c;&#x430;&#x442; &#x438; &#x442;.&#x43f;.).
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
