<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
log_out_admin();

header('Location: login.php');
exit;
