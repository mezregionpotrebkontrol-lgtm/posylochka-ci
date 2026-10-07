<?php
/**
 * Вспомогательные функции для публичного API (crm/api/*.php), которым
 * пользуется сайт (моя-посылочка.рф) и мобильное приложение (оно — тонкая
 * обёртка над тем же сайтом, см. mobile-app/README.md). В отличие от
 * остальной CRM, эти функции НЕ требуют входа сотрудника — они работают
 * от имени клиента (гостя или авторизованного по телефону+паролю).
 *
 * Авторизация клиента — через cookie-сессию (как у сайта: все его fetch()
 * идут с credentials:'include'), ПОД ОТДЕЛЬНЫМ именем сессии, чтобы не
 * пересекаться с сессией сотрудника CRM (та использует сессию по умолчанию,
 * см. includes/auth.php::crm_start_session()).
 */

require_once __DIR__ . '/pricing-formula.php';

const CAPI_SESSION_NAME = 'posylochka_client';

function capi_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(CAPI_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 30 * 24 * 60 * 60,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function capi_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}

function capi_respond($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function capi_error(string $message, int $status = 400): void
{
    capi_respond(['ok' => false, 'error' => $message], $status);
}

/**
 * Разрешает CORS только с собственного домена сайта (API и сайт на одном
 * домене, но сайт может стучаться с www/без www или из приложения-обёртки).
 * Access-Control-Allow-Credentials обязателен, иначе браузер не будет
 * отправлять/принимать cookie сессии при credentials:'include'.
 */
function capi_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function capi_find_or_create_client(PDO $pdo, string $name, string $phoneDigits, ?string $email = null): int
{
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE phone = ? ORDER BY id LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $row = $stmt->fetch();
    if ($row) {
        if ($name !== '') {
            $pdo->prepare('UPDATE clients SET name = ? WHERE id = ? AND (name = \'\' OR name IS NULL OR name = \'Клиент с сайта\')')
                ->execute([$name, $row['id']]);
        }
        return (int) $row['id'];
    }
    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email) VALUES (?,?,?)');
    $stmt->execute([$name !== '' ? $name : 'Клиент с сайта', $phoneDigits, $email]);
    return (int) $pdo->lastInsertId();
}

function capi_generate_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* ======================================================================
 * Сессия клиента / текущий пользователь
 * ====================================================================== */

function capi_login_client(PDO $pdo, int $clientId): void
{
    capi_start_session();
    session_regenerate_id(true);
    $_SESSION['client_id'] = $clientId;
}

function capi_logout_client(): void
{
    capi_start_session();
    $_SESSION = [];
    session_destroy();
}

/**
 * Возвращает текущего авторизованного клиента (clients + client_accounts),
 * либо null, если гость. Безопасно для публичного API: только чтение сессии.
 */
function capi_current_client(PDO $pdo): ?array
{
    capi_start_session();
    $clientId = $_SESSION['client_id'] ?? null;
    if (!$clientId) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT c.*, ca.email AS account_email, ca.birth_date, ca.passport_series, ca.passport_number,
            ca.passport_issued_by, ca.passport_issued_code, ca.passport_issue_date,
            ca.reg_city, ca.reg_street, ca.reg_house, ca.reg_apartment, ca.reg_postcode
        FROM clients c
        JOIN client_accounts ca ON ca.client_id = c.id
        WHERE c.id = ? LIMIT 1');
    $stmt->execute([(int) $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        capi_logout_client();
        return null;
    }
    return $row;
}

function capi_require_client(PDO $pdo): array
{
    $client = capi_current_client($pdo);
    if (!$client) {
        capi_error('Нужно войти в личный кабинет.', 401);
    }
    return $client;
}

/** Представление клиента для ответа клиенту (без внутренних полей). */
function capi_client_public(array $client): array
{
    return [
        'id'    => (int) $client['id'],
        'name'  => $client['name'],
        'phone' => $client['phone'],
        'email' => $client['account_email'] ?? $client['email'] ?? null,
    ];
}

/* ======================================================================
 * Регистрация / вход по телефону+паролю
 * ====================================================================== */

/**
 * @param array $payload Поля формы регистрации (см. register.php)
 * @return array{0:?array,1:?string} [client-строка или null, текст ошибки или null]
 */
function capi_register_client(PDO $pdo, array $payload): array
{
    $name = trim((string) ($payload['name'] ?? ''));
    $phoneDigits = crm_phone_digits($payload['phone'] ?? null);
    $password = (string) ($payload['password'] ?? '');

    if ($name === '' || !$phoneDigits || strlen($password) < 6) {
        return [null, 'Заполните имя, телефон и пароль (минимум 6 символов).'];
    }
    if (empty($payload['consent'])) {
        return [null, 'Нужно подтвердить согласие на обработку персональных данных.'];
    }

    $required = ['birth_date', 'passport_series', 'passport_number', 'passport_issued_by',
        'passport_issued_code', 'passport_issue_date', 'reg_city', 'reg_street', 'reg_house', 'reg_postcode'];
    foreach ($required as $field) {
        if (trim((string) ($payload[$field] ?? '')) === '') {
            return [null, 'Заполните все паспортные данные и адрес регистрации.'];
        }
    }

    $stmt = $pdo->prepare('SELECT id FROM client_accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phoneDigits]);
    if ($stmt->fetch()) {
        return [null, 'Клиент с этим телефоном уже зарегистрирован. Войдите или восстановите пароль.'];
    }

    $email = trim((string) ($payload['email'] ?? '')) ?: null;
    $clientId = capi_find_or_create_client($pdo, $name, $phoneDigits, $email);

    $stmt = $pdo->prepare('INSERT INTO client_accounts
        (client_id, phone, password_hash, email, birth_date, passport_series, passport_number,
         passport_issued_by, passport_issued_code, passport_issue_date,
         reg_city, reg_street, reg_house, reg_apartment, reg_postcode, consent_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        $clientId, $phoneDigits, password_hash($password, PASSWORD_BCRYPT), $email,
        capi_to_date($payload['birth_date'] ?? null),
        trim($payload['passport_series']), trim($payload['passport_number']),
        trim($payload['passport_issued_by']), trim($payload['passport_issued_code']),
        capi_to_date($payload['passport_issue_date'] ?? null),
        trim($payload['reg_city']), trim($payload['reg_street']), trim($payload['reg_house']),
        trim((string) ($payload['reg_apartment'] ?? '')) ?: null, trim($payload['reg_postcode']),
    ]);

    return [capi_current_client_by_id($pdo, $clientId), null];
}

function capi_to_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function capi_current_client_by_id(PDO $pdo, int $clientId): ?array
{
    $stmt = $pdo->prepare('SELECT c.*, ca.email AS account_email, ca.birth_date, ca.passport_series, ca.passport_number,
            ca.passport_issued_by, ca.passport_issued_code, ca.passport_issue_date,
            ca.reg_city, ca.reg_street, ca.reg_house, ca.reg_apartment, ca.reg_postcode
        FROM clients c JOIN client_accounts ca ON ca.client_id = c.id WHERE c.id = ? LIMIT 1');
    $stmt->execute([$clientId]);
    return $stmt->fetch() ?: null;
}

/**
 * @return array{0:?array,1:?string}
 */
function capi_authenticate(PDO $pdo, ?string $phone, ?string $password): array
{
    $phoneDigits = crm_phone_digits($phone);
    if (!$phoneDigits || !$password) {
        return [null, 'Укажите телефон и пароль.'];
    }
    $stmt = $pdo->prepare('SELECT * FROM client_accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $account = $stmt->fetch();
    if (!$account || !password_verify($password, $account['password_hash'])) {
        return [null, 'Неверный телефон или пароль.'];
    }
    return [capi_current_client_by_id($pdo, (int) $account['client_id']), null];
}

/* ======================================================================
 * Восстановление пароля по телефону (код в MAX и/или SMS.ru)
 * ====================================================================== */

/** Троттлинг: не чаще раза в 60 секунд на номер. Молча игнорирует повтор. */
function capi_request_phone_reset_code(PDO $pdo, string $phoneDigits): void
{
    $stmt = $pdo->prepare('SELECT created_at FROM client_auth_codes WHERE phone = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $last = $stmt->fetch();
    if ($last && (time() - strtotime($last['created_at'])) < 60) {
        return;
    }

    // Не раскрываем, зарегистрирован ли такой номер — отвечаем {ok:true} всегда,
    // но код реально шлём только существующим аккаунтам.
    $stmt = $pdo->prepare('SELECT id FROM client_accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phoneDigits]);
    if (!$stmt->fetch()) {
        return;
    }

    $code = capi_generate_code();
    $hash = password_hash($code, PASSWORD_BCRYPT);
    $expires = date('Y-m-d H:i:s', time() + 10 * 60);

    $pdo->prepare('INSERT INTO client_auth_codes (phone, code_hash, expires_at) VALUES (?,?,?)')
        ->execute([$phoneDigits, $hash, $expires]);

    $text = "Код для восстановления пароля в личном кабинете «Посылочка»: {$code}\nНикому не сообщайте этот код. Код действует 10 минут.";

    // Шлём и в MAX, и по SMS.ru — пользователь явно попросил оба канала,
    // чтобы код дошёл, даже если один из каналов недоступен/не настроен.
    $chatId = crm_phone_to_chat_id($phoneDigits);
    if ($chatId) {
        crm_send_max_message($chatId, $text);
    }
    crm_send_sms('+' . $phoneDigits, $text);
}

function capi_verify_phone_reset(PDO $pdo, string $phoneDigits, string $code, string $newPassword): ?string
{
    if (strlen($newPassword) < 6) {
        return 'Пароль должен быть не короче 6 символов.';
    }
    $stmt = $pdo->prepare('SELECT * FROM client_auth_codes WHERE phone = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $row = $stmt->fetch();
    if (!$row) {
        return 'Код не найден. Запросите новый.';
    }
    if (strtotime($row['expires_at']) < time()) {
        return 'Код истёк. Запросите новый.';
    }
    if ((int) $row['attempts'] >= 5) {
        return 'Слишком много попыток. Запросите новый код.';
    }
    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare('UPDATE client_auth_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        return 'Неверный код.';
    }

    $pdo->prepare('DELETE FROM client_auth_codes WHERE phone = ?')->execute([$phoneDigits]);

    $updated = $pdo->prepare('UPDATE client_accounts SET password_hash = ? WHERE phone = ?')
        ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $phoneDigits]);
    if (!$updated) {
        return 'Не удалось изменить пароль.';
    }
    return null;
}

/* ======================================================================
 * Восстановление пароля по email-ссылке
 * ====================================================================== */

function capi_request_email_reset(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('SELECT ca.client_id FROM client_accounts ca WHERE ca.email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) {
        return; // не раскрываем регистрацию почты
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 60 * 60);
    $pdo->prepare('INSERT INTO password_reset_tokens (client_id, token_hash, expires_at) VALUES (?,?,?)')
        ->execute([(int) $row['client_id'], $tokenHash, $expires]);

    $cfg = crm_config();
    $baseUrl = rtrim($cfg['site']['base_url'] ?? '', '/');
    $link = $baseUrl . '/api/reset_password.php?token=' . $token;
    $company = $cfg['company_name'] ?? 'Посылочка';

    $subject = "Восстановление пароля — {$company}";
    $body = "Вы запросили восстановление пароля в личном кабинете «{$company}».\n\n"
        . "Перейдите по ссылке, чтобы задать новый пароль (ссылка действует 1 час):\n{$link}\n\n"
        . "Если вы не запрашивали это письмо — просто игнорируйте его.";
    crm_send_email($email, $subject, $body);
}

/**
 * @return array{0:?int,1:?string} [client_id или null, ошибка или null]
 */
function capi_verify_email_reset_token(PDO $pdo, string $token): array
{
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
        return [null, 'Ссылка недействительна или устарела. Запросите восстановление пароля ещё раз.'];
    }
    return [(int) $row['client_id'], null];
}

function capi_consume_email_reset_token(PDO $pdo, string $token, string $newPassword): ?string
{
    if (strlen($newPassword) < 6) {
        return 'Пароль должен быть не короче 6 символов.';
    }
    [$clientId, $err] = capi_verify_email_reset_token($pdo, $token);
    if ($err !== null) {
        return $err;
    }
    $tokenHash = hash('sha256', $token);
    $pdo->prepare('UPDATE client_accounts SET password_hash = ? WHERE client_id = ?')
        ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $clientId]);
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?')->execute([$tokenHash]);
    return null;
}

/* ======================================================================
 * Заявки / заказы
 * ====================================================================== */

/**
 * Простая защита от спама по заявкам с сайта: не больше 3 заявок с одного
 * номера телефона за последние 10 минут.
 *
 * Важно: сравнение "сейчас - 10 минут" считается средствами САМОЙ БД
 * (NOW() - INTERVAL), а не в PHP. orders.created_at заполняется по
 * умолчанию DEFAULT CURRENT_TIMESTAMP — то есть часами сервера БД. Если
 * считать порог в PHP (date_default_timezone_set может отличаться от
 * часового пояса БД) и сравнивать со значением, записанным часами БД,
 * при несовпадении зон фильтр может никогда не срабатывать (или
 * срабатывать всегда). Сравнение на стороне БД этой проблемы не имеет.
 */
function capi_order_rate_limited(PDO $pdo, string $phoneDigits): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM orders o
        JOIN clients c ON c.id = o.client_id
        WHERE c.phone = ? AND o.created_at > (NOW() - INTERVAL 10 MINUTE)');
    $stmt->execute([$phoneDigits]);
    return (int) $stmt->fetch()['cnt'] >= 3;
}

function capi_generate_track_code(PDO $pdo): string
{
    do {
        $code = 'PSL' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE track_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

/**
 * Разбирает поле "city" из app.html (комбинированная строка
 * "ГородA → ГородB, адрес") на составляющие. Если разделителя нет,
 * считает всю строку городом отправления.
 */
function capi_split_combined_city(string $value): array
{
    $parts = preg_split('/\s*→\s*/u', $value, 2);
    if (count($parts) < 2) {
        return [trim($value), '', ''];
    }
    $origin = trim($parts[0]);
    $rest = trim($parts[1]);
    $destParts = explode(', ', $rest, 2);
    $dest = trim($destParts[0]);
    $address = isset($destParts[1]) ? trim($destParts[1]) : '';
    return [$origin, $dest, $address];
}

/**
 * Создаёт заявку (CRM orders) от имени клиента и возвращает [id, track_code].
 */
function capi_create_order_row(
    PDO $pdo,
    int $clientId,
    string $fromCity,
    string $toCity,
    ?string $toAddress,
    ?float $weightKg,
    ?float $declaredValue,
    ?float $price,
    ?string $comment,
    string $source,
    ?string $service = null
): array {
    $trackCode = capi_generate_track_code($pdo);
    $stmt = $pdo->prepare('INSERT INTO orders
        (client_id, from_city, to_city, to_address, weight_kg, declared_value, price, comment, status, source, track_code, service)
        VALUES (?,?,?,?,?,?,?,?,\'new\',?,?,?)');
    $stmt->execute([
        $clientId, $fromCity ?: 'Дербент', $toCity ?: 'Санкт-Петербург', $toAddress ?: null,
        $weightKg, $declaredValue, $price, $comment ?: null, $source, $trackCode, $service,
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, \'new\', NULL, \'Заявка создана с сайта\')')
        ->execute([$orderId]);
    return [$orderId, $trackCode];
}

/** Уведомляет владельца в MAX о новой заявке/заявке с сайта (замена admin-chat.html). */
function crm_notify_owner(string $text): void
{
    $cfg = crm_config();
    $chatId = $cfg['green_api']['ownerChatId'] ?? '';
    if ($chatId === '' || stripos($chatId, 'ЗАМЕНИТЕ') !== false) {
        return;
    }
    crm_send_max_message($chatId, $text);
}
