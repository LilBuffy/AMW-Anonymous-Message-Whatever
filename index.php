<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>.</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..500;1,9..144,300..500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main id="stage" aria-live="polite">

</main>

<p class="privacy-notice">
    
</p>

<script>
    window.CSRF_TOKEN = <?= json_encode($token) ?>;
    window.CONFIG = {
        INTRO_DELAY_MS: <?= (int) INTRO_DELAY_MS ?>,
        MAX_MESSAGE_LENGTH: <?= (int) MAX_MESSAGE_LENGTH ?>,
        MAX_IMAGE_SIZE_BYTES: <?= (int) MAX_IMAGE_SIZE_BYTES ?>,
        MAX_VIDEO_SIZE_BYTES: <?= (int) MAX_VIDEO_SIZE_BYTES ?>
    };
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
