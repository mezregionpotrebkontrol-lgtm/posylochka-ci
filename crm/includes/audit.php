<?php
/**
 * Журнал действий сотрудников (таблица audit_log, миграция 013).
 *
 * Пишется автоматически:
 *  - каждое сохранение формы в CRM (любой POST, прошедший crm_csrf_check());
 *  - вход/выход и неудачные попытки входа;
 *  - выгрузки в Excel.
 * Пароли, подписи, токены и файлы в журнал не попадают.
 * Если миграция 013 ещё не применена — функции молча ничего не делают,
 * CRM продолжает работать.
 */

function crm_audit(string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null, ?array $user = null): void
{
    try {
        if ($user === null && function_exists('crm_current_user')) {
            $user = crm_current_user();
        }
        crm_db()->prepare('INSERT INTO audit_log (user_id, user_name, action, entity, entity_id, details, ip) VALUES (?,?,?,?,?,?,?)')
            ->execute([
                $user['id'] ?? null,
                $user ? mb_substr((string) $user['name'], 0, 150) : null,
                mb_substr($action, 0, 60),
                $entity !== null ? mb_substr($entity, 0, 60) : null,
                $entityId,
                $details !== null ? mb_substr($details, 0, 4000) : null,
                mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45),
            ]);
    } catch (Throwable $e) {
        // Нет таблицы / нет базы — журнал не должен ломать работу.
    }
}

/** Поля формы, которые никогда не пишем в журнал. */
function crm_audit_is_secret_field(string $key): bool
{
    return (bool) preg_match('/pass|csrf|token|secret|signature|photo|file|code$/i', $key);
}

/** Пишет в журнал текущую отправку формы (вызывается из crm_csrf_check). */
function crm_audit_post(): void
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    if ($script === 'login') {
        return; // вход пишется отдельно в crm_login()
    }
    $action = (string) ($_POST['action'] ?? 'save');
    $entityId = null;
    foreach (['id', 'order_id', 'client_id', 'claim_id', 'invoice_id', 'lead_id', 'run_id', 'user_id', 'promo_id'] as $k) {
        $v = $_POST[$k] ?? $_GET[$k] ?? null;
        if ($v !== null && ctype_digit((string) $v)) {
            $entityId = (int) $v;
            break;
        }
    }
    $fields = [];
    foreach ($_POST as $k => $v) {
        if ($k === 'action' || crm_audit_is_secret_field((string) $k)) {
            continue;
        }
        if (is_array($v)) {
            $v = implode(',', array_map(fn($x) => is_scalar($x) ? (string) $x : '…', array_slice($v, 0, 20)));
        }
        $v = trim((string) $v);
        if ($v === '') {
            continue;
        }
        if (mb_strlen($v) > 120) {
            $v = mb_substr($v, 0, 120) . '…';
        }
        $fields[] = $k . '=' . $v;
    }
    crm_audit($script . ':' . $action, $script, $entityId, $fields ? implode('; ', $fields) : null);
}

/** Понятное название действия для страницы журнала. */
function crm_audit_action_label(string $action): string
{
    $map = [
        'login'        => 'Вход в CRM',
        'login_failed' => 'Неудачная попытка входа',
        'logout'       => 'Выход',
        'export'       => 'Выгрузка в Excel',
        'backup'       => 'Резервная копия',
    ];
    if (isset($map[$action])) {
        return $map[$action];
    }
    $pages = [
        'order' => 'Заявка', 'orders' => 'Заявки', 'client' => 'Клиент', 'clients' => 'Клиенты',
        'invoice' => 'Счёт', 'finance' => 'Финансы', 'claim' => 'Претензия', 'claims' => 'Претензии',
        'shipments' => 'Рейсы', 'warehouse' => 'Склад', 'reception' => 'Приём/выдача',
        'packages-scan' => 'Сканирование мест', 'courier-pickup' => 'Приём груза курьером',
        'my-orders' => 'Мои доставки', 'leads' => 'Заявки с сайта', 'reviews' => 'Отзывы',
        'pricing' => 'Цены', 'users' => 'Сотрудники', 'promo' => 'Промокоды', 'backups' => 'Резервные копии',
        'route-sheet' => 'Маршрутный лист',
    ];
    [$page, $act] = array_pad(explode(':', $action, 2), 2, '');
    return ($pages[$page] ?? $page) . ($act !== '' && $act !== 'save' ? ' → ' . $act : '');
}

/**
 * Защита от подбора пароля: сколько неудачных входов было с этого IP
 * за последние $minutes минут. Без таблицы audit_log возвращает 0.
 */
function crm_failed_logins_recent(int $minutes = 15): int
{
    try {
        $stmt = crm_db()->prepare("SELECT COUNT(*) FROM audit_log WHERE action = 'login_failed' AND ip = ? AND created_at > (NOW() - INTERVAL ? MINUTE)");
        $stmt->execute([(string) ($_SERVER['REMOTE_ADDR'] ?? ''), $minutes]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
