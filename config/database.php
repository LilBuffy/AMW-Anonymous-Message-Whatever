<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'nigatron');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

define('INTRO_DELAY_MS', 3000);

define('MAX_MESSAGE_LENGTH', 1000);
define('MIN_MESSAGE_LENGTH', 1);

define('MAX_IMAGE_SIZE_BYTES', 5 * 1024 * 1024);
define('MAX_VIDEO_SIZE_BYTES', 20 * 1024 * 1024);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_IMAGE_MIME', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_MIME', ['video/mp4', 'video/webm', 'video/quicktime']);
define('ALLOWED_VIDEO_EXT', ['mp4', 'webm', 'mov']);

define('SUBMISSION_COOLDOWN_SECONDS', 30);
define('MAX_SUBMISSIONS_PER_HOUR', 20);
define('DUPLICATE_WINDOW_SECONDS', 300);

define('SESSION_NAME', 'anonmsg_admin_session');
define('SESSION_LIFETIME_SECONDS', 3600 * 4);

define('SETUP_KEY', 'change-this-to-your-own-random-text-123456');

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    } catch (PDOException $e) {

        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Something went wrong. Please try again later.');
    }

    return $pdo;
}
