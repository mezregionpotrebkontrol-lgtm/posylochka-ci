<?php
/**
 * Ежедневные напоминания (запуск раз в день утром, например в 10:00):
 *   /opt/php/8.3/bin/php -f /var/www/u3661226/data/www/моя-посылочка.рф/crm/cron/reminders.php
 *
 * Клиентам (MAX / WhatsApp / Telegram — что настроено, + email):
 *  1. Неоплаченный выставленный счёт (не старше 30 дней) — через 3 дня после
 *     выставления, затем раз в 4 дня, не больше 3 раз.
 *  2. Посылка лежит в пункте выдачи и не забрана — на 2-й, 5-й и 10-й день.
 *  3. Платёж по рассрочке просрочен — на 1-й и 5-й день просрочки.
 *
 * Владельцу (MAX + почта) — одна сводка «на что обратить внимание»:
 *  - новые заявки без движения больше суток;
 *  - заявки в пути дольше 7 дней;
 *  - доставленные, но не оплаченные (постоплата) дольше 3 дней;
 *  - претензии, у которых срок ответа сегодня/завтра или уже прошёл;
 *  - посылки на складе дольше 10 дней.
 *
 * Запуск с параметром --dry-run печатает, что было бы отправлено, ничего не отправляя.
 */
require __DIR__ . '/_cli.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$pdo = crm_db();
$cfg = crm_config();
$company = $cfg['company_name'] ?? 'Посылочка';
$siteUrl = rtrim($cfg['site']['base_url'] ?? '', '/');
$contactPhone = $cfg['reminders']['contact_phone'] ?? '+7 (812) 711-18-19';
$sent = 0;

function remind_client(PDO $pdo, ?string $phone, ?string $email, string $subject, string $text, bool $dryRun): bool
{
    if ($dryRun) {
        cron_log("[тест] → {$phone} {$email}: {$text}");
        return true;
    }
    $ok = false;
    $chatId = crm_phone_to_chat_id($phone);
    if ($chatId) {
        $ok = crm_send_max_message($chatId, $text) || $ok;
        $ok = crm_send_whatsapp_message($chatId, $text) || $ok;
        $ok = crm_send_telegram_message($chatId, $text) || $ok;
    }
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $ok = crm_send_email($email, $subject, $text) || $ok;
    }
    if (!$ok && $phone && !empty(crm_config()['reminders']['sms_fallback'])) {
        $digits = crm_phone_digits($phone);
        if ($digits) {
            $ok = crm_send_sms('+' . $digits, $text);
        }
    }
    return $ok;
}

/* ---------- 1. Неоплаченные счета ---------- */
$hasRemindedAt = true;
try {
    $pdo->query('SELECT reminded_at FROM invoices LIMIT 1');
} catch (Throwable $e) {
    $hasRemindedAt = false;
}
if ($hasRemindedAt) {
    $invoices = $pdo->query("SELECT i.*, c.name AS client_name, c.phone, c.email,
            (SELECT COUNT(*) FROM audit_log a WHERE a.action = 'reminder_invoice' AND a.entity_id = i.id) AS times
        FROM invoices i JOIN clients c ON c.id = i.client_id
        WHERE i.status = 'sent'
          AND i.created_at < NOW() - INTERVAL 3 DAY
          AND i.created_at > NOW() - INTERVAL 30 DAY
          AND (i.reminded_at IS NULL OR i.reminded_at < NOW() - INTERVAL 4 DAY)")->fetchAll();
    foreach ($invoices as $inv) {
        if ((int) $inv['times'] >= 3 || (!$inv['phone'] && !$inv['email'])) {
            continue;
        }
        $text = "Здравствуйте! Напоминаем об оплате счёта №{$inv['number']} от " . crm_date($inv['created_at'], 'd.m.Y')
            . ' на сумму ' . crm_money((float) $inv['amount']) . ". Если уже оплатили — спасибо, просто не обращайте внимания. {$company}";
        if (remind_client($pdo, $inv['phone'], $inv['email'], "Напоминание об оплате счёта №{$inv['number']} — {$company}", $text, $dryRun)) {
            $sent++;
            if (!$dryRun) {
                $pdo->prepare('UPDATE invoices SET reminded_at = NOW() WHERE id = ?')->execute([$inv['id']]);
                crm_audit('reminder_invoice', 'invoice', (int) $inv['id'], $inv['client_name'], null);
            }
        }
    }
}

/* ---------- 2. Посылки ждут в пункте выдачи ---------- */
$waiting = $pdo->query("SELECT o.id, o.track_code, o.to_city, o.recipient_name, o.recipient_phone,
        c.name AS client_name, c.phone, c.email, wp.name AS point_name, wp.address AS point_address,
        DATEDIFF(CURDATE(), DATE(we.created_at)) AS days
    FROM warehouse_events we
    JOIN orders o ON o.id = we.order_id
    JOIN clients c ON c.id = o.client_id
    LEFT JOIN warehouse_points wp ON wp.id = we.point_id
    WHERE we.event_type = 'received'
      AND we.id = (SELECT MAX(w2.id) FROM warehouse_events w2 WHERE w2.order_id = we.order_id)
      AND o.status NOT IN ('delivered','cancelled')")->fetchAll();
$longOnStock = [];
foreach ($waiting as $w) {
    $days = (int) $w['days'];
    if ($days >= 10) {
        $longOnStock[] = $w;
    }
    if (!in_array($days, [2, 5, 10], true)) {
        continue;
    }
    // Напоминаем получателю, если он указан, иначе отправителю.
    $phone = $w['recipient_phone'] ?: $w['phone'];
    $email = $w['recipient_phone'] ? null : $w['email'];
    $where = trim(($w['point_name'] ?? '') . ($w['point_address'] ? ', ' . $w['point_address'] : ''));
    $text = "Здравствуйте! Посылка №{$w['id']}" . ($w['track_code'] ? " (трек {$w['track_code']})" : '')
        . " ждёт вас" . ($where !== '' ? " в пункте выдачи: {$where}" : ' в пункте выдачи')
        . ". Лежит уже {$days} дн. Можно заказать бесплатную доставку до адреса — позвоните нам: {$contactPhone}. {$company}";
    if ($phone && remind_client($pdo, $phone, $email, "Посылка ждёт вас — {$company}", $text, $dryRun)) {
        $sent++;
        if (!$dryRun) {
            crm_audit('reminder_pickup', 'order', (int) $w['id'], "день {$days}", null);
        }
    }
}

/* ---------- 3. Просроченные платежи по рассрочке ---------- */
$overdue = $pdo->query("SELECT oi.*, o.id AS order_id, c.name AS client_name, c.phone, c.email,
        DATEDIFF(CURDATE(), oi.due_date) AS days
    FROM order_installments oi
    JOIN orders o ON o.id = oi.order_id
    JOIN clients c ON c.id = o.client_id
    WHERE oi.status = 'pending' AND oi.due_date IS NOT NULL AND oi.due_date < CURDATE()")->fetchAll();
foreach ($overdue as $inst) {
    if (!in_array((int) $inst['days'], [1, 5], true)) {
        continue;
    }
    $text = "Здравствуйте! По заявке №{$inst['order_id']} " . crm_date($inst['due_date'], 'd.m.Y')
        . ' был срок платежа по рассрочке ' . crm_money((float) $inst['amount']) . ". Пожалуйста, оплатите или свяжитесь с нами. {$company}";
    if (remind_client($pdo, $inst['phone'], $inst['email'], "Платёж по рассрочке — {$company}", $text, $dryRun)) {
        $sent++;
        if (!$dryRun) {
            crm_audit('reminder_installment', 'order', (int) $inst['order_id'], crm_money((float) $inst['amount']), null);
        }
    }
}

/* ---------- Сводка владельцу ---------- */
$lines = [];
$q = function (string $sql) use ($pdo): array {
    return $pdo->query($sql)->fetchAll();
};
$stuckNew = $q("SELECT id FROM orders WHERE status = 'new' AND created_at < NOW() - INTERVAL 1 DAY ORDER BY id");
if ($stuckNew) {
    $lines[] = '• Новые заявки без движения больше суток: ' . count($stuckNew) . ' (№' . implode(', №', array_slice(array_column($stuckNew, 'id'), 0, 15)) . ')';
}
$longTransit = $q("SELECT id FROM orders WHERE status = 'in_transit' AND updated_at < NOW() - INTERVAL 7 DAY ORDER BY id");
if ($longTransit) {
    $lines[] = '• В пути дольше 7 дней: ' . count($longTransit) . ' (№' . implode(', №', array_slice(array_column($longTransit, 'id'), 0, 15)) . ')';
}
$unpaid = $q("SELECT id, price FROM orders WHERE status = 'delivered' AND payment_status = 'postpaid' AND updated_at < NOW() - INTERVAL 3 DAY");
if ($unpaid) {
    $sum = array_sum(array_map(fn($r) => (float) $r['price'], $unpaid));
    $lines[] = '• Доставлены, но не оплачены (постоплата) дольше 3 дней: ' . count($unpaid) . ' на ' . crm_money($sum);
}
$claimsDue = $q("SELECT id, order_id, response_due_date FROM claims WHERE status IN ('open','in_progress') AND response_due_date IS NOT NULL AND response_due_date <= CURDATE() + INTERVAL 1 DAY");
foreach ($claimsDue as $cl) {
    $late = $cl['response_due_date'] < date('Y-m-d');
    $lines[] = '• Претензия №' . $cl['id'] . ' (заявка №' . $cl['order_id'] . '): ' . ($late ? 'срок ответа ПРОШЁЛ ' : 'срок ответа ') . crm_date($cl['response_due_date'], 'd.m.Y');
}
if ($longOnStock) {
    $lines[] = '• На складе дольше 10 дней: ' . count($longOnStock) . ' (№' . implode(', №', array_slice(array_column($longOnStock, 'id'), 0, 15)) . ')';
}

if ($lines) {
    $digest = "Сводка CRM на " . date('d.m.Y') . ":\n" . implode("\n", $lines) . "\n\nОткрыть CRM: {$siteUrl}/crm/";
    if ($dryRun) {
        cron_log("[тест] Сводка владельцу:\n" . $digest);
    } else {
        crm_notify_owner($digest);
    }
}

cron_log("Готово. Напоминаний клиентам: {$sent}. Пунктов в сводке: " . count($lines) . '.');
