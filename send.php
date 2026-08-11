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
    json_response(['success' => false, 'error' => $rate['reason']], 429);
}

$message = trim($_POST['message'] ?? '');

if (mb_strlen($message) < MIN_MESSAGE_LENGTH) {
    json_response(['success' => false, 'error' => 'Message cannot be empty.'], 400);
}
if (mb_strlen($message) > MAX_MESSAGE_LENGTH) {
    json_response(['success' => false, 'error' => 'Message is too long.'], 400);
}

$whyChoices = ['I have no idea', 'Someone sent it to me', 'I was bored', 'I wanted to see what this was', 'I was looking for something', 'Other'];
$whyClicked = $_POST['why_clicked'] ?? '';
$whyClickedOther = null;

if (!in_array($whyClicked, $whyChoices, true)) {
    json_response(['success' => false, 'error' => 'Invalid answer.'], 400);
}
if ($whyClicked === 'Other') {
    $whyClickedOther = mb_substr(trim($_POST['why_clicked_other'] ?? ''), 0, 255);
    if ($whyClickedOther === '') {
        json_response(['success' => false, 'error' => 'Please provide a short answer.'], 400);
    }
}

$whereChoices = ['Instagram', 'Facebook', 'LinkedIn', 'Discord', 'Messenger', 'Someone sent it to me', 'Somewhere else', 'Other'];
$whereFound = $_POST['where_found'] ?? '';
$whereFoundOther = null;

if (!in_array($whereFound, $whereChoices, true)) {
    json_response(['success' => false, 'error' => 'Invalid answer.'], 400);
}
if ($whereFound === 'Other') {
    $whereFoundOther = mb_substr(trim($_POST['where_found_other'] ?? ''), 0, 255);
    if ($whereFoundOther === '') {
        json_response(['success' => false, 'error' => 'Please provide a short answer.'], 400);
    }
}

$messageHash = hash('sha256', $message);
if (is_duplicate_message($pdo, $ipBinary, $messageHash)) {
    json_response(['success' => false, 'error' => 'This message was already sent recently.'], 429);
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
             attachment_path, attachment_type, submitted_link,
             ip_address, user_agent, requested_page)
         VALUES
            (:message, :why_clicked, :why_clicked_other, :where_found, :where_found_other,
             :attachment_path, :attachment_type, :submitted_link,
             :ip_address, :user_agent, :requested_page)'
    );

    $stmt->execute([
        'message'           => $message,
        'why_clicked'       => $whyClicked,
        'why_clicked_other' => $whyClickedOther,
        'where_found'       => $whereFound,
        'where_found_other' => $whereFoundOther,
        'attachment_path'   => $attachmentPath,
        'attachment_type'   => $attachmentType,
        'submitted_link'    => $submittedLink,
        'ip_address'        => $ipBinary,
        'user_agent'        => get_user_agent(),
        'requested_page'    => get_requested_page(),
    ]);

    log_submission_attempt($pdo, $ipBinary, $messageHash);
} catch (PDOException $e) {
    error_log('Message insert failed: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Something went wrong. Please try again.'], 500);
}

$confirmations = [
    'Message sent. Now fuck off!',
    'Into the void it goes.',
    'Your message has been delivered.',
    'Transmission complete.',
    'Noted, my nigga.',
];

json_response([
    'success' => true,
    'confirmation' => $confirmations[array_rand($confirmations)],
]);
