<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'error' => 'Invalid request method.'], 405);
}

$pdo = get_db_connection();
$ipBinary = get_client_ip_binary();

$status = get_cooldown_status($pdo, $ipBinary);

json_response([
    'success'           => true,
    'active'            => $status['active'],
    'remaining_seconds' => $status['remaining_seconds'],
]);
