<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Invalid request method.'], 405);
}

csrf_require();

$pdo = get_db_connection();
$ipBinary = get_client_ip_binary();

$rate = check_rate_limit($pdo, $ipBinary);
if (!$rate['allowed']) {
    $response = ['success' => false, 'error' => $rate['reason']];
    if (isset($rate['remaining_seconds'])) {
        $response['cooldown_active'] = true;
        $response['remaining_seconds'] = $rate['remaining_seconds'];
    }
    json_response($response, 429);
}

$message = trim($_POST['message'] ?? '');

if (mb_strlen($message) < MIN_MESSAGE_LENGTH) {
    json_response(['success' => false, 'error' => 'You have to actually say something.'], 400);
}
if (mb_strlen($message) > MAX_MESSAGE_LENGTH) {
    json_response(['success' => false, 'error' => 'That is a bit long. Trim it down.'], 400);
}

$questions = get_questions_config();

$reason = validate_question_answer($questions['reason'], $_POST['reason'] ?? null, $_POST['reason_other'] ?? null);
if (!$reason['valid']) {
    json_response(['success' => false, 'error' => $reason['error']], 400);
}

$found = validate_question_answer($questions['found'], $_POST['found'] ?? null, $_POST['found_other'] ?? null);
if (!$found['valid']) {
    json_response(['success' => false, 'error' => $found['error']], 400);
}

$familiarity = validate_question_answer($questions['familiarity'], $_POST['familiarity'] ?? null, $_POST['familiarity_other'] ?? null);
if (!$familiarity['valid']) {
    json_response(['success' => false, 'error' => $familiarity['error']], 400);
}

$messageHash = hash('sha256', $message);
if (is_duplicate_message($pdo, $ipBinary, $messageHash)) {
    json_response(['success' => false, 'error' => 'That one already went through. It is already in the void.'], 429);
}

$attachmentPath = null;
$attachmentType = null;
$attachmentMime = null;

if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $result = handle_attachment_upload($_FILES['attachment']);
    if (!$result['success']) {
        json_response(['success' => false, 'error' => $result['error']], 400);
    }
    $attachmentPath = $result['filename'];
    $attachmentType = $result['type'];
    $attachmentMime = $result['mime'];
}

$submittedLink = extract_first_url($message);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO messages
            (message, why_clicked, why_clicked_other, where_found, where_found_other,
             how_well_known, how_well_known_other,
             attachment_path, attachment_type, attachment_mime, submitted_link,
             ip_address, user_agent, requested_page)
         VALUES
            (:message, :why_clicked, :why_clicked_other, :where_found, :where_found_other,
             :how_well_known, :how_well_known_other,
             :attachment_path, :attachment_type, :attachment_mime, :submitted_link,
             :ip_address, :user_agent, :requested_page)'
    );

    $stmt->execute([
        'message'              => $message,
        'why_clicked'          => $_POST['reason'],
        'why_clicked_other'    => $reason['other'],
        'where_found'          => $_POST['found'],
        'where_found_other'    => $found['other'],
        'how_well_known'       => $_POST['familiarity'],
        'how_well_known_other' => $familiarity['other'],
        'attachment_path'      => $attachmentPath,
        'attachment_type'      => $attachmentType,
        'attachment_mime'      => $attachmentMime,
        'submitted_link'       => $submittedLink,
        'ip_address'           => $ipBinary,
        'user_agent'           => get_user_agent(),
        'requested_page'       => get_requested_page(),
    ]);

    log_submission_attempt($pdo, $ipBinary, $messageHash);
} catch (PDOException $e) {
    error_log('Message insert failed: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Something went wrong. Please try again.'], 500);
}

$confirmations = [
    'Message sent.',
    'It is gone. Delivered.',
    'Transmission complete.',
    'Received, loud and clear.',
    'That one is mine now.',
];

json_response([
    'success'           => true,
    'confirmation'      => $confirmations[array_rand($confirmations)],
    'remaining_seconds' => SUBMISSION_COOLDOWN_SECONDS,
]);
