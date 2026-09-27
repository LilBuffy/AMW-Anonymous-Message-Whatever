<?php

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function csrf_verify(?string $submittedToken): bool
{
    if (empty($_SESSION['csrf_token']) || empty($submittedToken)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function csrf_require(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!csrf_verify($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid or expired request. Please refresh and try again.']);
        exit;
    }
}
