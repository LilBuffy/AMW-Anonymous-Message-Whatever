<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

if (is_admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter both fields.';
        } else {
            $pdo = get_db_connection();

            usleep(300000);

            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :u LIMIT 1');
            $stmt->execute(['u' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                log_in_admin((int) $admin['id'], $admin['username']);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Incorrect username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..500;1,9..144,300..500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
<main class="admin-login-wrap">
    <p class="admin-eyebrow">Private</p>
    <h1 class="admin-title">Admin</h1>

    <?php if ($error): ?>
        <p class="admin-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="admin-login-form" novalidate>
        <?= csrf_field() ?>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <button type="submit">Log in</button>
    </form>
</main>
</body>
</html>
