<?php

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin_login(bool $isJsonEndpoint = false): void
{
    if (is_admin_logged_in()) {
        return;
    }

    if ($isJsonEndpoint) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
        exit;
    }

    header('Location: login.php');
    exit;
}

function log_in_admin(int $adminId, string $username): void
{

    session_regenerate_id(true);
    $_SESSION['admin_id']       = $adminId;
    $_SESSION['admin_username'] = $username;
    $_SESSION['login_time']     = time();
}

function log_out_admin(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
