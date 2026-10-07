<?php
/**
 * Авторизация, сессии, проверка ролей, CSRF-защита.
 */

require_once __DIR__ . '/db.php';

function crm_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function crm_current_user(): ?array
{
    crm_start_session();
    return $_SESSION['user'] ?? null;
}

function crm_require_login(): array
{
    $user = crm_current_user();
    if (!$user) {
        header('Location: /crm/login.php');
        exit;
    }
    return $user;
}

/**
 * @param string[] $roles Разрешённые роли, например ['admin','operator']
 */
function crm_require_role(array $roles): array
{
    $user = crm_require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('Доступ запрещён: недостаточно прав для этого раздела.');
    }
    return $user;
}

function crm_login(string $login, string $password): bool
{
    $pdo = crm_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE login = ? AND active = 1 LIMIT 1');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    crm_start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'login' => $user['login'],
        'role'  => $user['role'],
    ];
    return true;
}

function crm_logout(): void
{
    crm_start_session();
    $_SESSION = [];
    session_destroy();
}

function crm_csrf_token(): string
{
    crm_start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function crm_csrf_field(): string
{
    $token = htmlspecialchars(crm_csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf\" value=\"{$token}\">";
}

function crm_csrf_check(): void
{
    crm_start_session();
    $sent = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        die('Сессия устарела, обновите страницу и попробуйте снова.');
    }
}

/** Человекочитаемые названия ролей */
function crm_role_label(string $role): string
{
    return [
        'admin'    => 'Администратор',
        'operator' => 'Оператор',
        'courier'  => 'Курьер',
    ][$role] ?? $role;
}
