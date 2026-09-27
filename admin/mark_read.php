<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Invalid request method.'], 405);
}

csrf_require();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$isRead = filter_input(INPUT_POST, 'is_read', FILTER_VALIDATE_INT);

if (!$id || $isRead === null) {
    json_response(['success' => false, 'error' => 'Invalid request.'], 400);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('UPDATE messages SET is_read = :is_read WHERE id = :id');
$stmt->execute(['is_read' => $isRead ? 1 : 0, 'id' => $id]);

json_response(['success' => true]);
