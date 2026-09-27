<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();
$token = csrf_token();

$pdo = get_db_connection();
$cooldown = get_cooldown_status($pdo, get_client_ip_binary());
$questions = get_questions_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>.</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..500;1,9..144,300..500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main id="stage" aria-live="polite">

</main>

<button style="display:none;" type="button" id="privacy-toggle" class="privacy-toggle" aria-expanded="false" aria-controls="privacy-panel">Privacy</button>
<div id="privacy-panel" class="privacy-panel" hidden>
    <p>No account, login, username, or email is needed to send a message here.</p>
    <p>For security and to keep this from turning into a spam dump, some basic technical information is recorded with every message, like an IP address, browser information, and the time it was sent. Only the owner of this site can see that, and it is never shown publicly.</p>
    <p>Anonymous means no identifying information is required from you. It does not mean the server keeps nothing at all.</p>
</div>

<script>
    window.CSRF_TOKEN = <?= json_encode($token) ?>;
    window.CONFIG = {
        INTRO_DELAY_MS: <?= (int) INTRO_DELAY_MS ?>,
        MAX_MESSAGE_LENGTH: <?= (int) MAX_MESSAGE_LENGTH ?>,
        MAX_IMAGE_SIZE_BYTES: <?= (int) MAX_IMAGE_SIZE_BYTES ?>,
        MAX_VIDEO_SIZE_BYTES: <?= (int) MAX_VIDEO_SIZE_BYTES ?>,
        QUESTIONS: <?= json_encode($questions, JSON_UNESCAPED_SLASHES) ?>,
        COOLDOWN: <?= json_encode($cooldown) ?>
    };
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
