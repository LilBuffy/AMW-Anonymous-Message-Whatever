<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function get_client_ip_binary(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $packed = @inet_pton($ip);
    return $packed !== false ? $packed : inet_pton('0.0.0.0');
}

function get_client_ip_string(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function get_user_agent(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return mb_substr($ua, 0, 500);
}

function get_requested_page(): string
{
    $page = $_SERVER['HTTP_REFERER'] ?? ($_SERVER['REQUEST_URI'] ?? '');
    return mb_substr($page, 0, 255);
}

function get_cooldown_status(PDO $pdo, string $ipBinary): array
{
    $stmt = $pdo->prepare(
        'SELECT created_at FROM submission_log
         WHERE ip_address = :ip
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->execute(['ip' => $ipBinary]);
    $last = $stmt->fetch();

    if (!$last) {
        return ['active' => false, 'remaining_seconds' => 0];
    }

    $secondsSinceLast = time() - strtotime($last['created_at']);
    $remaining = SUBMISSION_COOLDOWN_SECONDS - $secondsSinceLast;

    if ($remaining <= 0) {
        return ['active' => false, 'remaining_seconds' => 0];
    }

    return ['active' => true, 'remaining_seconds' => $remaining];
}

function check_rate_limit(PDO $pdo, string $ipBinary): array
{
    $cooldown = get_cooldown_status($pdo, $ipBinary);

    if ($cooldown['active']) {
        return [
            'allowed'           => false,
            'reason'            => 'The cooldown is still active. Give it a moment.',
            'remaining_seconds' => $cooldown['remaining_seconds'],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt FROM submission_log
         WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $stmt->execute(['ip' => $ipBinary]);
    $count = (int) $stmt->fetch()['cnt'];

    if ($count >= MAX_SUBMISSIONS_PER_HOUR) {
        return [
            'allowed' => false,
            'reason'  => 'Too many messages from you recently. Please try again later.',
        ];
    }

    return ['allowed' => true];
}

function is_duplicate_message(PDO $pdo, string $ipBinary, string $messageHash): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM submission_log
         WHERE ip_address = :ip
           AND message_hash = :hash
           AND created_at > (NOW() - INTERVAL :window SECOND)
         LIMIT 1'
    );
    $stmt->bindValue(':ip', $ipBinary, PDO::PARAM_STR);
    $stmt->bindValue(':hash', $messageHash, PDO::PARAM_STR);
    $stmt->bindValue(':window', DUPLICATE_WINDOW_SECONDS, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function log_submission_attempt(PDO $pdo, string $ipBinary, string $messageHash): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO submission_log (ip_address, message_hash) VALUES (:ip, :hash)'
    );
    $stmt->execute(['ip' => $ipBinary, 'hash' => $messageHash]);
}

function extract_first_url(string $text): ?string
{
    if (preg_match('/\bhttps?:\/\/[^\s<>"\']+/i', $text, $matches)) {
        return rtrim($matches[0], '.,!?)');
    }
    return null;
}

function validate_question_answer(array $questionConfig, ?string $choice, ?string $otherText): array
{
    $choice = $choice ?? '';

    if (!in_array($choice, $questionConfig['choices'], true)) {
        return ['valid' => false, 'error' => 'Invalid answer.', 'other' => null];
    }

    if ($choice === 'Other') {
        $otherText = mb_substr(trim($otherText ?? ''), 0, MAX_OTHER_ANSWER_LENGTH);
        $otherText = preg_replace('/[\x00-\x1F\x7F]/u', '', $otherText);

        if ($otherText === '' || $otherText === null) {
            return ['valid' => false, 'error' => 'Please provide a short answer.', 'other' => null];
        }

        return ['valid' => true, 'error' => null, 'other' => $otherText];
    }

    return ['valid' => true, 'error' => null, 'other' => null];
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return round($bytes / 1024 / 1024, 1) . 'MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    }
    return $bytes . 'B';
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
