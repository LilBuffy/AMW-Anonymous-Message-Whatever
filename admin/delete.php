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

if (!$id) {
    json_response(['success' => false, 'error' => 'Invalid message ID.'], 400);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT attachment_path FROM messages WHERE id = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    json_response(['success' => false, 'error' => 'Message not found.'], 404);
}

$stmt = $pdo->prepare('DELETE FROM messages WHERE id = :id');
$stmt->execute(['id' => $id]);

if (!empty($row['attachment_path'])) {
    $filePath = UPLOAD_DIR . basename($row['attachment_path']);
    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

json_response(['success' => true]);
