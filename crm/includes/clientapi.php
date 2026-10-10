<?php
/**
 * &#x412;&#x441;&#x43f;&#x43e;&#x43c;&#x43e;&#x433;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x44b;&#x435; &#x444;&#x443;&#x43d;&#x43a;&#x446;&#x438;&#x438; &#x434;&#x43b;&#x44f; &#x43f;&#x443;&#x431;&#x43b;&#x438;&#x447;&#x43d;&#x43e;&#x433;&#x43e; API (crm/api/*.php), &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x44b;&#x43c;
 * &#x43f;&#x43e;&#x43b;&#x44c;&#x437;&#x443;&#x435;&#x442;&#x441;&#x44f; &#x441;&#x430;&#x439;&#x442; (&#x43c;&#x43e;&#x44f;-&#x43f;&#x43e;&#x441;&#x44b;&#x43b;&#x43e;&#x447;&#x43a;&#x430;.&#x440;&#x444;) &#x438; &#x43c;&#x43e;&#x431;&#x438;&#x43b;&#x44c;&#x43d;&#x43e;&#x435; &#x43f;&#x440;&#x438;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x438;&#x435; (&#x43e;&#x43d;&#x43e; &#x2014; &#x442;&#x43e;&#x43d;&#x43a;&#x430;&#x44f;
 * &#x43e;&#x431;&#x451;&#x440;&#x442;&#x43a;&#x430; &#x43d;&#x430;&#x434; &#x442;&#x435;&#x43c; &#x436;&#x435; &#x441;&#x430;&#x439;&#x442;&#x43e;&#x43c;, &#x441;&#x43c;. mobile-app/README.md). &#x412; &#x43e;&#x442;&#x43b;&#x438;&#x447;&#x438;&#x435; &#x43e;&#x442;
 * &#x43e;&#x441;&#x442;&#x430;&#x43b;&#x44c;&#x43d;&#x43e;&#x439; CRM, &#x44d;&#x442;&#x438; &#x444;&#x443;&#x43d;&#x43a;&#x446;&#x438;&#x438; &#x41d;&#x415; &#x442;&#x440;&#x435;&#x431;&#x443;&#x44e;&#x442; &#x432;&#x445;&#x43e;&#x434;&#x430; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x430; &#x2014; &#x43e;&#x43d;&#x438; &#x440;&#x430;&#x431;&#x43e;&#x442;&#x430;&#x44e;&#x442;
 * &#x43e;&#x442; &#x438;&#x43c;&#x435;&#x43d;&#x438; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; (&#x433;&#x43e;&#x441;&#x442;&#x44f; &#x438;&#x43b;&#x438; &#x430;&#x432;&#x442;&#x43e;&#x440;&#x438;&#x437;&#x43e;&#x432;&#x430;&#x43d;&#x43d;&#x43e;&#x433;&#x43e; &#x43f;&#x43e; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x443;+&#x43f;&#x430;&#x440;&#x43e;&#x43b;&#x44e;).
 *
 * &#x410;&#x432;&#x442;&#x43e;&#x440;&#x438;&#x437;&#x430;&#x446;&#x438;&#x44f; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x2014; &#x447;&#x435;&#x440;&#x435;&#x437; cookie-&#x441;&#x435;&#x441;&#x441;&#x438;&#x44e; (&#x43a;&#x430;&#x43a; &#x443; &#x441;&#x430;&#x439;&#x442;&#x430;: &#x432;&#x441;&#x435; &#x435;&#x433;&#x43e; fetch()
 * &#x438;&#x434;&#x443;&#x442; &#x441; credentials:'include'), &#x41f;&#x41e;&#x414; &#x41e;&#x422;&#x414;&#x415;&#x41b;&#x42c;&#x41d;&#x42b;&#x41c; &#x438;&#x43c;&#x435;&#x43d;&#x435;&#x43c; &#x441;&#x435;&#x441;&#x441;&#x438;&#x438;, &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x43d;&#x435;
 * &#x43f;&#x435;&#x440;&#x435;&#x441;&#x435;&#x43a;&#x430;&#x442;&#x44c;&#x441;&#x44f; &#x441; &#x441;&#x435;&#x441;&#x441;&#x438;&#x435;&#x439; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x430; CRM (&#x442;&#x430; &#x438;&#x441;&#x43f;&#x43e;&#x43b;&#x44c;&#x437;&#x443;&#x435;&#x442; &#x441;&#x435;&#x441;&#x441;&#x438;&#x44e; &#x43f;&#x43e; &#x443;&#x43c;&#x43e;&#x43b;&#x447;&#x430;&#x43d;&#x438;&#x44e;,
 * &#x441;&#x43c;. includes/auth.php::crm_start_session()).
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
 * &#x420;&#x430;&#x437;&#x440;&#x435;&#x448;&#x430;&#x435;&#x442; CORS &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x441; &#x441;&#x43e;&#x431;&#x441;&#x442;&#x432;&#x435;&#x43d;&#x43d;&#x43e;&#x433;&#x43e; &#x434;&#x43e;&#x43c;&#x435;&#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x430; (API &#x438; &#x441;&#x430;&#x439;&#x442; &#x43d;&#x430; &#x43e;&#x434;&#x43d;&#x43e;&#x43c;
 * &#x434;&#x43e;&#x43c;&#x435;&#x43d;&#x435;, &#x43d;&#x43e; &#x441;&#x430;&#x439;&#x442; &#x43c;&#x43e;&#x436;&#x435;&#x442; &#x441;&#x442;&#x443;&#x447;&#x430;&#x442;&#x44c;&#x441;&#x44f; &#x441; www/&#x431;&#x435;&#x437; www &#x438;&#x43b;&#x438; &#x438;&#x437; &#x43f;&#x440;&#x438;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x438;&#x44f;-&#x43e;&#x431;&#x451;&#x440;&#x442;&#x43a;&#x438;).
 * Access-Control-Allow-Credentials &#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x435;&#x43d;, &#x438;&#x43d;&#x430;&#x447;&#x435; &#x431;&#x440;&#x430;&#x443;&#x437;&#x435;&#x440; &#x43d;&#x435; &#x431;&#x443;&#x434;&#x435;&#x442;
 * &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x44f;&#x442;&#x44c;/&#x43f;&#x440;&#x438;&#x43d;&#x438;&#x43c;&#x430;&#x442;&#x44c; cookie &#x441;&#x435;&#x441;&#x441;&#x438;&#x438; &#x43f;&#x440;&#x438; credentials:'include'.
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
            $pdo->prepare("UPDATE clients SET name = ? WHERE id = ? AND (name = '' OR name IS NULL OR name = '\u{41a}\u{43b}\u{438}\u{435}\u{43d}\u{442} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}')")
                ->execute([$name, $row['id']]);
        }
        return (int) $row['id'];
    }
    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email) VALUES (?,?,?)');
    $stmt->execute([$name !== '' ? $name : "\u{41a}\u{43b}\u{438}\u{435}\u{43d}\u{442} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}", $phoneDigits, $email]);
    return (int) $pdo->lastInsertId();
}

function capi_generate_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* ======================================================================
 * &#x421;&#x435;&#x441;&#x441;&#x438;&#x44f; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; / &#x442;&#x435;&#x43a;&#x443;&#x449;&#x438;&#x439; &#x43f;&#x43e;&#x43b;&#x44c;&#x437;&#x43e;&#x432;&#x430;&#x442;&#x435;&#x43b;&#x44c;
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
 * &#x412;&#x43e;&#x437;&#x432;&#x440;&#x430;&#x449;&#x430;&#x435;&#x442; &#x442;&#x435;&#x43a;&#x443;&#x449;&#x435;&#x433;&#x43e; &#x430;&#x432;&#x442;&#x43e;&#x440;&#x438;&#x437;&#x43e;&#x432;&#x430;&#x43d;&#x43d;&#x43e;&#x433;&#x43e; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; (clients + client_accounts),
 * &#x43b;&#x438;&#x431;&#x43e; null, &#x435;&#x441;&#x43b;&#x438; &#x433;&#x43e;&#x441;&#x442;&#x44c;. &#x411;&#x435;&#x437;&#x43e;&#x43f;&#x430;&#x441;&#x43d;&#x43e; &#x434;&#x43b;&#x44f; &#x43f;&#x443;&#x431;&#x43b;&#x438;&#x447;&#x43d;&#x43e;&#x433;&#x43e; API: &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x447;&#x442;&#x435;&#x43d;&#x438;&#x435; &#x441;&#x435;&#x441;&#x441;&#x438;&#x438;.
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
        capi_error("\u{41d}\u{443}\u{436}\u{43d}\u{43e} \u{432}\u{43e}\u{439}\u{442}\u{438} \u{432} \u{43b}\u{438}\u{447}\u{43d}\u{44b}\u{439} \u{43a}\u{430}\u{431}\u{438}\u{43d}\u{435}\u{442}.", 401);
    }
    return $client;
}

/** &#x41f;&#x440;&#x435;&#x434;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x434;&#x43b;&#x44f; &#x43e;&#x442;&#x432;&#x435;&#x442;&#x430; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x443; (&#x431;&#x435;&#x437; &#x432;&#x43d;&#x443;&#x442;&#x440;&#x435;&#x43d;&#x43d;&#x438;&#x445; &#x43f;&#x43e;&#x43b;&#x435;&#x439;). */
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
 * &#x420;&#x435;&#x433;&#x438;&#x441;&#x442;&#x440;&#x430;&#x446;&#x438;&#x44f; / &#x432;&#x445;&#x43e;&#x434; &#x43f;&#x43e; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x443;+&#x43f;&#x430;&#x440;&#x43e;&#x43b;&#x44e;
 * ====================================================================== */

/**
 * @param array $payload &#x41f;&#x43e;&#x43b;&#x44f; &#x444;&#x43e;&#x440;&#x43c;&#x44b; &#x440;&#x435;&#x433;&#x438;&#x441;&#x442;&#x440;&#x430;&#x446;&#x438;&#x438; (&#x441;&#x43c;. register.php)
 * @return array{0:?array,1:?string} [client-&#x441;&#x442;&#x440;&#x43e;&#x43a;&#x430; &#x438;&#x43b;&#x438; null, &#x442;&#x435;&#x43a;&#x441;&#x442; &#x43e;&#x448;&#x438;&#x431;&#x43a;&#x438; &#x438;&#x43b;&#x438; null]
 */
function capi_register_client(PDO $pdo, array $payload): array
{
    $name = trim((string) ($payload['name'] ?? ''));
    $phoneDigits = crm_phone_digits($payload['phone'] ?? null);
    $password = (string) ($payload['password'] ?? '');

    if ($name === '' || !$phoneDigits || strlen($password) < 6) {
        return [null, "\u{417}\u{430}\u{43f}\u{43e}\u{43b}\u{43d}\u{438}\u{442}\u{435} \u{438}\u{43c}\u{44f}, \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d} \u{438} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44c} (\u{43c}\u{438}\u{43d}\u{438}\u{43c}\u{443}\u{43c} 6 \u{441}\u{438}\u{43c}\u{432}\u{43e}\u{43b}\u{43e}\u{432})."];
    }
    if (empty($payload['consent'])) {
        return [null, "\u{41d}\u{443}\u{436}\u{43d}\u{43e} \u{43f}\u{43e}\u{434}\u{442}\u{432}\u{435}\u{440}\u{434}\u{438}\u{442}\u{44c} \u{441}\u{43e}\u{433}\u{43b}\u{430}\u{441}\u{438}\u{435} \u{43d}\u{430} \u{43e}\u{431}\u{440}\u{430}\u{431}\u{43e}\u{442}\u{43a}\u{443} \u{43f}\u{435}\u{440}\u{441}\u{43e}\u{43d}\u{430}\u{43b}\u{44c}\u{43d}\u{44b}\u{445} \u{434}\u{430}\u{43d}\u{43d}\u{44b}\u{445}."];
    }

    $required = ['birth_date', 'passport_series', 'passport_number', 'passport_issued_by',
        'passport_issued_code', 'passport_issue_date', 'reg_city', 'reg_street', 'reg_house', 'reg_postcode'];
    foreach ($required as $field) {
        if (trim((string) ($payload[$field] ?? '')) === '') {
            return [null, "\u{417}\u{430}\u{43f}\u{43e}\u{43b}\u{43d}\u{438}\u{442}\u{435} \u{432}\u{441}\u{435} \u{43f}\u{430}\u{441}\u{43f}\u{43e}\u{440}\u{442}\u{43d}\u{44b}\u{435} \u{434}\u{430}\u{43d}\u{43d}\u{44b}\u{435} \u{438} \u{430}\u{434}\u{440}\u{435}\u{441} \u{440}\u{435}\u{433}\u{438}\u{441}\u{442}\u{440}\u{430}\u{446}\u{438}\u{438}."];
        }
    }

    $stmt = $pdo->prepare('SELECT id FROM client_accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phoneDigits]);
    if ($stmt->fetch()) {
        return [null, "\u{41a}\u{43b}\u{438}\u{435}\u{43d}\u{442} \u{441} \u{44d}\u{442}\u{438}\u{43c} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}\u{43e}\u{43c} \u{443}\u{436}\u{435} \u{437}\u{430}\u{440}\u{435}\u{433}\u{438}\u{441}\u{442}\u{440}\u{438}\u{440}\u{43e}\u{432}\u{430}\u{43d}. \u{412}\u{43e}\u{439}\u{434}\u{438}\u{442}\u{435} \u{438}\u{43b}\u{438} \u{432}\u{43e}\u{441}\u{441}\u{442}\u{430}\u{43d}\u{43e}\u{432}\u{438}\u{442}\u{435} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44c}."];
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
        return [null, "\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d} \u{438} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44c}."];
    }
    $stmt = $pdo->prepare('SELECT * FROM client_accounts WHERE phone = ? LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $account = $stmt->fetch();
    if (!$account || !password_verify($password, $account['password_hash'])) {
        return [null, "\u{41d}\u{435}\u{432}\u{435}\u{440}\u{43d}\u{44b}\u{439} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d} \u{438}\u{43b}\u{438} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44c}."];
    }
    return [capi_current_client_by_id($pdo, (int) $account['client_id']), null];
}

/* ======================================================================
 * &#x412;&#x43e;&#x441;&#x441;&#x442;&#x430;&#x43d;&#x43e;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x43f;&#x430;&#x440;&#x43e;&#x43b;&#x44f; &#x43f;&#x43e; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x443; (&#x43a;&#x43e;&#x434; &#x432; MAX &#x438;/&#x438;&#x43b;&#x438; SMS.ru)
 * ====================================================================== */

/** &#x422;&#x440;&#x43e;&#x442;&#x442;&#x43b;&#x438;&#x43d;&#x433;: &#x43d;&#x435; &#x447;&#x430;&#x449;&#x435; &#x440;&#x430;&#x437;&#x430; &#x432; 60 &#x441;&#x435;&#x43a;&#x443;&#x43d;&#x434; &#x43d;&#x430; &#x43d;&#x43e;&#x43c;&#x435;&#x440;. &#x41c;&#x43e;&#x43b;&#x447;&#x430; &#x438;&#x433;&#x43d;&#x43e;&#x440;&#x438;&#x440;&#x443;&#x435;&#x442; &#x43f;&#x43e;&#x432;&#x442;&#x43e;&#x440;. */
function capi_request_phone_reset_code(PDO $pdo, string $phoneDigits): void
{
    $stmt = $pdo->prepare('SELECT created_at FROM client_auth_codes WHERE phone = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $last = $stmt->fetch();
    if ($last && (time() - strtotime($last['created_at'])) < 60) {
        return;
    }

    // &#x41d;&#x435; &#x440;&#x430;&#x441;&#x43a;&#x440;&#x44b;&#x432;&#x430;&#x435;&#x43c;, &#x437;&#x430;&#x440;&#x435;&#x433;&#x438;&#x441;&#x442;&#x440;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d; &#x43b;&#x438; &#x442;&#x430;&#x43a;&#x43e;&#x439; &#x43d;&#x43e;&#x43c;&#x435;&#x440; &#x2014; &#x43e;&#x442;&#x432;&#x435;&#x447;&#x430;&#x435;&#x43c; {ok:true} &#x432;&#x441;&#x435;&#x433;&#x434;&#x430;,
    // &#x43d;&#x43e; &#x43a;&#x43e;&#x434; &#x440;&#x435;&#x430;&#x43b;&#x44c;&#x43d;&#x43e; &#x448;&#x43b;&#x451;&#x43c; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x441;&#x443;&#x449;&#x435;&#x441;&#x442;&#x432;&#x443;&#x44e;&#x449;&#x438;&#x43c; &#x430;&#x43a;&#x43a;&#x430;&#x443;&#x43d;&#x442;&#x430;&#x43c;.
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

    $text = "\u{41a}\u{43e}\u{434} \u{434}\u{43b}\u{44f} \u{432}\u{43e}\u{441}\u{441}\u{442}\u{430}\u{43d}\u{43e}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{44f} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44f} \u{432} \u{43b}\u{438}\u{447}\u{43d}\u{43e}\u{43c} \u{43a}\u{430}\u{431}\u{438}\u{43d}\u{435}\u{442}\u{435} \u{ab}\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}\u{bb}: {$code}\n\u{41d}\u{438}\u{43a}\u{43e}\u{43c}\u{443} \u{43d}\u{435} \u{441}\u{43e}\u{43e}\u{431}\u{449}\u{430}\u{439}\u{442}\u{435} \u{44d}\u{442}\u{43e}\u{442} \u{43a}\u{43e}\u{434}. \u{41a}\u{43e}\u{434} \u{434}\u{435}\u{439}\u{441}\u{442}\u{432}\u{443}\u{435}\u{442} 10 \u{43c}\u{438}\u{43d}\u{443}\u{442}.";

    // &#x428;&#x43b;&#x451;&#x43c; &#x438; &#x432; MAX, &#x438; &#x43f;&#x43e; SMS.ru &#x2014; &#x43f;&#x43e;&#x43b;&#x44c;&#x437;&#x43e;&#x432;&#x430;&#x442;&#x435;&#x43b;&#x44c; &#x44f;&#x432;&#x43d;&#x43e; &#x43f;&#x43e;&#x43f;&#x440;&#x43e;&#x441;&#x438;&#x43b; &#x43e;&#x431;&#x430; &#x43a;&#x430;&#x43d;&#x430;&#x43b;&#x430;,
    // &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x43a;&#x43e;&#x434; &#x434;&#x43e;&#x448;&#x451;&#x43b;, &#x434;&#x430;&#x436;&#x435; &#x435;&#x441;&#x43b;&#x438; &#x43e;&#x434;&#x438;&#x43d; &#x438;&#x437; &#x43a;&#x430;&#x43d;&#x430;&#x43b;&#x43e;&#x432; &#x43d;&#x435;&#x434;&#x43e;&#x441;&#x442;&#x443;&#x43f;&#x435;&#x43d;/&#x43d;&#x435; &#x43d;&#x430;&#x441;&#x442;&#x440;&#x43e;&#x435;&#x43d;.
    $chatId = crm_phone_to_chat_id($phoneDigits);
    if ($chatId) {
        crm_send_max_message($chatId, $text);
    }
    crm_send_sms('+' . $phoneDigits, $text);
}

function capi_verify_phone_reset(PDO $pdo, string $phoneDigits, string $code, string $newPassword): ?string
{
    if (strlen($newPassword) < 6) {
        return "\u{41f}\u{430}\u{440}\u{43e}\u{43b}\u{44c} \u{434}\u{43e}\u{43b}\u{436}\u{435}\u{43d} \u{431}\u{44b}\u{442}\u{44c} \u{43d}\u{435} \u{43a}\u{43e}\u{440}\u{43e}\u{447}\u{435} 6 \u{441}\u{438}\u{43c}\u{432}\u{43e}\u{43b}\u{43e}\u{432}.";
    }
    $stmt = $pdo->prepare('SELECT * FROM client_auth_codes WHERE phone = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phoneDigits]);
    $row = $stmt->fetch();
    if (!$row) {
        return "\u{41a}\u{43e}\u{434} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}. \u{417}\u{430}\u{43f}\u{440}\u{43e}\u{441}\u{438}\u{442}\u{435} \u{43d}\u{43e}\u{432}\u{44b}\u{439}.";
    }
    if (strtotime($row['expires_at']) < time()) {
        return "\u{41a}\u{43e}\u{434} \u{438}\u{441}\u{442}\u{451}\u{43a}. \u{417}\u{430}\u{43f}\u{440}\u{43e}\u{441}\u{438}\u{442}\u{435} \u{43d}\u{43e}\u{432}\u{44b}\u{439}.";
    }
    if ((int) $row['attempts'] >= 5) {
        return "\u{421}\u{43b}\u{438}\u{448}\u{43a}\u{43e}\u{43c} \u{43c}\u{43d}\u{43e}\u{433}\u{43e} \u{43f}\u{43e}\u{43f}\u{44b}\u{442}\u{43e}\u{43a}. \u{417}\u{430}\u{43f}\u{440}\u{43e}\u{441}\u{438}\u{442}\u{435} \u{43d}\u{43e}\u{432}\u{44b}\u{439} \u{43a}\u{43e}\u{434}.";
    }
    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare('UPDATE client_auth_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        return "\u{41d}\u{435}\u{432}\u{435}\u{440}\u{43d}\u{44b}\u{439} \u{43a}\u{43e}\u{434}.";
    }

    $pdo->prepare('DELETE FROM client_auth_codes WHERE phone = ?')->execute([$phoneDigits]);

    $updated = $pdo->prepare('UPDATE client_accounts SET password_hash = ? WHERE phone = ?')
        ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $phoneDigits]);
    if (!$updated) {
        return "\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{438}\u{437}\u{43c}\u{435}\u{43d}\u{438}\u{442}\u{44c} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44c}.";
    }
    return null;
}

/* ======================================================================
 * &#x412;&#x43e;&#x441;&#x441;&#x442;&#x430;&#x43d;&#x43e;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x43f;&#x430;&#x440;&#x43e;&#x43b;&#x44f; &#x43f;&#x43e; email-&#x441;&#x441;&#x44b;&#x43b;&#x43a;&#x435;
 * ====================================================================== */

function capi_request_email_reset(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('SELECT ca.client_id FROM client_accounts ca WHERE ca.email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) {
        return; // &#x43d;&#x435; &#x440;&#x430;&#x441;&#x43a;&#x440;&#x44b;&#x432;&#x430;&#x435;&#x43c; &#x440;&#x435;&#x433;&#x438;&#x441;&#x442;&#x440;&#x430;&#x446;&#x438;&#x44e; &#x43f;&#x43e;&#x447;&#x442;&#x44b;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 60 * 60);
    $pdo->prepare('INSERT INTO password_reset_tokens (client_id, token_hash, expires_at) VALUES (?,?,?)')
        ->execute([(int) $row['client_id'], $tokenHash, $expires]);

    $cfg = crm_config();
    $baseUrl = rtrim($cfg['site']['base_url'] ?? '', '/');
    // &#x421;&#x441;&#x44b;&#x43b;&#x43a;&#x430; &#x432;&#x435;&#x434;&#x451;&#x442; &#x43d;&#x430; &#x443;&#x436;&#x435; &#x441;&#x443;&#x449;&#x435;&#x441;&#x442;&#x432;&#x443;&#x44e;&#x449;&#x443;&#x44e; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x443; reset-password.html
    // (&#x43e;&#x43d;&#x430; &#x441;&#x430;&#x43c;&#x430; &#x448;&#x43b;&#x451;&#x442; POST {token, password} &#x43d;&#x430; /api/reset_password.php).
    $link = $baseUrl . '/reset-password.html?token=' . $token;
    $company = $cfg['company_name'] ?? "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}";

    $subject = "\u{412}\u{43e}\u{441}\u{441}\u{442}\u{430}\u{43d}\u{43e}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{435} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44f} \u{2014} {$company}";
    $body = "\u{412}\u{44b} \u{437}\u{430}\u{43f}\u{440}\u{43e}\u{441}\u{438}\u{43b}\u{438} \u{432}\u{43e}\u{441}\u{441}\u{442}\u{430}\u{43d}\u{43e}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{435} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44f} \u{432} \u{43b}\u{438}\u{447}\u{43d}\u{43e}\u{43c} \u{43a}\u{430}\u{431}\u{438}\u{43d}\u{435}\u{442}\u{435} \u{ab}{$company}\u{bb}.\n\n"
        . "\u{41f}\u{435}\u{440}\u{435}\u{439}\u{434}\u{438}\u{442}\u{435} \u{43f}\u{43e} \u{441}\u{441}\u{44b}\u{43b}\u{43a}\u{435}, \u{447}\u{442}\u{43e}\u{431}\u{44b} \u{437}\u{430}\u{434}\u{430}\u{442}\u{44c} \u{43d}\u{43e}\u{432}\u{44b}\u{439} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44c} (\u{441}\u{441}\u{44b}\u{43b}\u{43a}\u{430} \u{434}\u{435}\u{439}\u{441}\u{442}\u{432}\u{443}\u{435}\u{442} 1 \u{447}\u{430}\u{441}):\n{$link}\n\n"
        . "\u{415}\u{441}\u{43b}\u{438} \u{432}\u{44b} \u{43d}\u{435} \u{437}\u{430}\u{43f}\u{440}\u{430}\u{448}\u{438}\u{432}\u{430}\u{43b}\u{438} \u{44d}\u{442}\u{43e} \u{43f}\u{438}\u{441}\u{44c}\u{43c}\u{43e} \u{2014} \u{43f}\u{440}\u{43e}\u{441}\u{442}\u{43e} \u{438}\u{433}\u{43d}\u{43e}\u{440}\u{438}\u{440}\u{443}\u{439}\u{442}\u{435} \u{435}\u{433}\u{43e}.";
    crm_send_email($email, $subject, $body);
}

/**
 * @return array{0:?int,1:?string} [client_id &#x438;&#x43b;&#x438; null, &#x43e;&#x448;&#x438;&#x431;&#x43a;&#x430; &#x438;&#x43b;&#x438; null]
 */
function capi_verify_email_reset_token(PDO $pdo, string $token): array
{
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
        return [null, "\u{421}\u{441}\u{44b}\u{43b}\u{43a}\u{430} \u{43d}\u{435}\u{434}\u{435}\u{439}\u{441}\u{442}\u{432}\u{438}\u{442}\u{435}\u{43b}\u{44c}\u{43d}\u{430} \u{438}\u{43b}\u{438} \u{443}\u{441}\u{442}\u{430}\u{440}\u{435}\u{43b}\u{430}. \u{417}\u{430}\u{43f}\u{440}\u{43e}\u{441}\u{438}\u{442}\u{435} \u{432}\u{43e}\u{441}\u{441}\u{442}\u{430}\u{43d}\u{43e}\u{432}\u{43b}\u{435}\u{43d}\u{438}\u{435} \u{43f}\u{430}\u{440}\u{43e}\u{43b}\u{44f} \u{435}\u{449}\u{451} \u{440}\u{430}\u{437}."];
    }
    return [(int) $row['client_id'], null];
}

function capi_consume_email_reset_token(PDO $pdo, string $token, string $newPassword): ?string
{
    if (strlen($newPassword) < 6) {
        return "\u{41f}\u{430}\u{440}\u{43e}\u{43b}\u{44c} \u{434}\u{43e}\u{43b}\u{436}\u{435}\u{43d} \u{431}\u{44b}\u{442}\u{44c} \u{43d}\u{435} \u{43a}\u{43e}\u{440}\u{43e}\u{447}\u{435} 6 \u{441}\u{438}\u{43c}\u{432}\u{43e}\u{43b}\u{43e}\u{432}.";
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
 * &#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; / &#x437;&#x430;&#x43a;&#x430;&#x437;&#x44b;
 * ====================================================================== */

/**
 * &#x41f;&#x440;&#x43e;&#x441;&#x442;&#x430;&#x44f; &#x437;&#x430;&#x449;&#x438;&#x442;&#x430; &#x43e;&#x442; &#x441;&#x43f;&#x430;&#x43c;&#x430; &#x43f;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430;&#x43c; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430;: &#x43d;&#x435; &#x431;&#x43e;&#x43b;&#x44c;&#x448;&#x435; 3 &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a; &#x441; &#x43e;&#x434;&#x43d;&#x43e;&#x433;&#x43e;
 * &#x43d;&#x43e;&#x43c;&#x435;&#x440;&#x430; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x430; &#x437;&#x430; &#x43f;&#x43e;&#x441;&#x43b;&#x435;&#x434;&#x43d;&#x438;&#x435; 10 &#x43c;&#x438;&#x43d;&#x443;&#x442;.
 *
 * &#x412;&#x430;&#x436;&#x43d;&#x43e;: &#x441;&#x440;&#x430;&#x432;&#x43d;&#x435;&#x43d;&#x438;&#x435; "&#x441;&#x435;&#x439;&#x447;&#x430;&#x441; - 10 &#x43c;&#x438;&#x43d;&#x443;&#x442;" &#x441;&#x447;&#x438;&#x442;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x441;&#x440;&#x435;&#x434;&#x441;&#x442;&#x432;&#x430;&#x43c;&#x438; &#x421;&#x410;&#x41c;&#x41e;&#x419; &#x411;&#x414;
 * (NOW() - INTERVAL), &#x430; &#x43d;&#x435; &#x432; PHP. orders.created_at &#x437;&#x430;&#x43f;&#x43e;&#x43b;&#x43d;&#x44f;&#x435;&#x442;&#x441;&#x44f; &#x43f;&#x43e;
 * &#x443;&#x43c;&#x43e;&#x43b;&#x447;&#x430;&#x43d;&#x438;&#x44e; DEFAULT CURRENT_TIMESTAMP &#x2014; &#x442;&#x43e; &#x435;&#x441;&#x442;&#x44c; &#x447;&#x430;&#x441;&#x430;&#x43c;&#x438; &#x441;&#x435;&#x440;&#x432;&#x435;&#x440;&#x430; &#x411;&#x414;. &#x415;&#x441;&#x43b;&#x438;
 * &#x441;&#x447;&#x438;&#x442;&#x430;&#x442;&#x44c; &#x43f;&#x43e;&#x440;&#x43e;&#x433; &#x432; PHP (date_default_timezone_set &#x43c;&#x43e;&#x436;&#x435;&#x442; &#x43e;&#x442;&#x43b;&#x438;&#x447;&#x430;&#x442;&#x44c;&#x441;&#x44f; &#x43e;&#x442;
 * &#x447;&#x430;&#x441;&#x43e;&#x432;&#x43e;&#x433;&#x43e; &#x43f;&#x43e;&#x44f;&#x441;&#x430; &#x411;&#x414;) &#x438; &#x441;&#x440;&#x430;&#x432;&#x43d;&#x438;&#x432;&#x430;&#x442;&#x44c; &#x441;&#x43e; &#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x435;&#x43c;, &#x437;&#x430;&#x43f;&#x438;&#x441;&#x430;&#x43d;&#x43d;&#x44b;&#x43c; &#x447;&#x430;&#x441;&#x430;&#x43c;&#x438; &#x411;&#x414;,
 * &#x43f;&#x440;&#x438; &#x43d;&#x435;&#x441;&#x43e;&#x432;&#x43f;&#x430;&#x434;&#x435;&#x43d;&#x438;&#x438; &#x437;&#x43e;&#x43d; &#x444;&#x438;&#x43b;&#x44c;&#x442;&#x440; &#x43c;&#x43e;&#x436;&#x435;&#x442; &#x43d;&#x438;&#x43a;&#x43e;&#x433;&#x434;&#x430; &#x43d;&#x435; &#x441;&#x440;&#x430;&#x431;&#x430;&#x442;&#x44b;&#x432;&#x430;&#x442;&#x44c; (&#x438;&#x43b;&#x438;
 * &#x441;&#x440;&#x430;&#x431;&#x430;&#x442;&#x44b;&#x432;&#x430;&#x442;&#x44c; &#x432;&#x441;&#x435;&#x433;&#x434;&#x430;). &#x421;&#x440;&#x430;&#x432;&#x43d;&#x435;&#x43d;&#x438;&#x435; &#x43d;&#x430; &#x441;&#x442;&#x43e;&#x440;&#x43e;&#x43d;&#x435; &#x411;&#x414; &#x44d;&#x442;&#x43e;&#x439; &#x43f;&#x440;&#x43e;&#x431;&#x43b;&#x435;&#x43c;&#x44b; &#x43d;&#x435; &#x438;&#x43c;&#x435;&#x435;&#x442;.
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
 * &#x420;&#x430;&#x437;&#x431;&#x438;&#x440;&#x430;&#x435;&#x442; &#x43f;&#x43e;&#x43b;&#x435; "city" &#x438;&#x437; app.html (&#x43a;&#x43e;&#x43c;&#x431;&#x438;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x43d;&#x430;&#x44f; &#x441;&#x442;&#x440;&#x43e;&#x43a;&#x430;
 * "&#x413;&#x43e;&#x440;&#x43e;&#x434;A &#x2192; &#x413;&#x43e;&#x440;&#x43e;&#x434;B, &#x430;&#x434;&#x440;&#x435;&#x441;") &#x43d;&#x430; &#x441;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43b;&#x44f;&#x44e;&#x449;&#x438;&#x435;. &#x415;&#x441;&#x43b;&#x438; &#x440;&#x430;&#x437;&#x434;&#x435;&#x43b;&#x438;&#x442;&#x435;&#x43b;&#x44f; &#x43d;&#x435;&#x442;,
 * &#x441;&#x447;&#x438;&#x442;&#x430;&#x435;&#x442; &#x432;&#x441;&#x44e; &#x441;&#x442;&#x440;&#x43e;&#x43a;&#x443; &#x433;&#x43e;&#x440;&#x43e;&#x434;&#x43e;&#x43c; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;.
 */
function capi_split_combined_city(string $value): array
{
    $parts = preg_split("/\\s*\u{2192}\\s*/u", $value, 2);
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
 * &#x421;&#x43e;&#x437;&#x434;&#x430;&#x451;&#x442; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; (CRM orders) &#x43e;&#x442; &#x438;&#x43c;&#x435;&#x43d;&#x438; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x438; &#x432;&#x43e;&#x437;&#x432;&#x440;&#x430;&#x449;&#x430;&#x435;&#x442; [id, track_code].
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
    ?string $service = null,
    bool $isInsured = false,
    ?float $insuredAmount = null,
    ?float $insuranceFee = null
): array {
    $trackCode = capi_generate_track_code($pdo);
    $stmt = $pdo->prepare('INSERT INTO orders
        (client_id, from_city, to_city, to_address, weight_kg, declared_value, price, comment, status, source, track_code, service,
         is_insured, insured_amount, insurance_fee)
        VALUES (?,?,?,?,?,?,?,?,\'new\',?,?,?,?,?,?)');
    $stmt->execute([
        $clientId, $fromCity ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}", $toCity ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}", $toAddress ?: null,
        $weightKg, $declaredValue, $price, $comment ?: null, $source, $trackCode, $service,
        $isInsured ? 1 : 0, $insuredAmount, $insuranceFee,
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'new', NULL, '\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}')")
        ->execute([$orderId]);
    return [$orderId, $trackCode];
}

/** &#x423;&#x432;&#x435;&#x434;&#x43e;&#x43c;&#x43b;&#x44f;&#x435;&#x442; &#x432;&#x43b;&#x430;&#x434;&#x435;&#x43b;&#x44c;&#x446;&#x430; &#x432; MAX &#x43e; &#x43d;&#x43e;&#x432;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435;/&#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430; (&#x437;&#x430;&#x43c;&#x435;&#x43d;&#x430; admin-chat.html). */
function crm_notify_owner(string $text): void
{
    $cfg = crm_config();
    $chatId = $cfg['green_api']['ownerChatId'] ?? '';
    if ($chatId === '' || stripos($chatId, "\u{417}\u{410}\u{41c}\u{415}\u{41d}\u{418}\u{422}\u{415}") !== false) {
        return;
    }
    crm_send_max_message($chatId, $text);
}
