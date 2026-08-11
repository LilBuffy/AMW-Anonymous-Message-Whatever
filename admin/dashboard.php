<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$pdo = get_db_connection();
$messages = $pdo->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();
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
<header class="admin-header">
    <h1>Messages <span class="admin-count">(<?= count($messages) ?>)</span></h1>
    <a href="logout.php" class="admin-logout-link">Log out</a>
</header>

<main class="admin-dashboard">
    <?php if (empty($messages)): ?>
        <p class="admin-empty">No messages yet.</p>
    <?php endif; ?>

    <?php foreach ($messages as $msg): ?>
        <?php
            $link = $msg['submitted_link'] ? e($msg['submitted_link']) : null;
            $ipReadable = @inet_ntop($msg['ip_address']) ?: 'unknown';
        ?>
        <article class="message-card" data-id="<?= (int) $msg['id'] ?>">
            <p class="message-text"><?= nl2br(e($msg['message'])) ?></p>

            <?php if ($link): ?>
                <p class="message-link">
                    <a href="<?= $link ?>" target="_blank" rel="noopener noreferrer nofollow"><?= $link ?></a>
                </p>
            <?php endif; ?>

            <?php if ($msg['attachment_path']): ?>
                <div class="message-attachment">
                    <?php if ($msg['attachment_type'] === 'image'): ?>
                        <img src="../uploads/<?= e($msg['attachment_path']) ?>" alt="Attachment" loading="lazy">
                    <?php elseif ($msg['attachment_type'] === 'video'): ?>
                        <video src="../uploads/<?= e($msg['attachment_path']) ?>" controls preload="metadata"></video>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <dl class="message-meta">
                <dt>Why did you click my link?</dt>
                <dd><?= e($msg['why_clicked']) ?><?= $msg['why_clicked_other'] ? ' — ' . e($msg['why_clicked_other']) : '' ?></dd>

                <dt>Where did you find my link?</dt>
                <dd><?= e($msg['where_found']) ?><?= $msg['where_found_other'] ? ' — ' . e($msg['where_found_other']) : '' ?></dd>

                <dt>Received</dt>
                <dd><?= e(date('F j, Y g:i A', strtotime($msg['created_at']))) ?></dd>
            </dl>

            <details class="message-tech">
                <summary>Technical information</summary>
                <dl class="message-meta">
                    <dt>IP address</dt>
                    <dd><?= e($ipReadable) ?></dd>
                    <dt>Browser / User agent</dt>
                    <dd><?= e($msg['user_agent']) ?></dd>
                    <dt>Requested page</dt>
                    <dd><?= e($msg['requested_page']) ?></dd>
                </dl>
            </details>

            <div class="card-footer">
                <button type="button" class="delete-btn" data-id="<?= (int) $msg['id'] ?>">Delete</button>
            </div>
        </article>
    <?php endforeach; ?>
</main>

<script>
    window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
</script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
