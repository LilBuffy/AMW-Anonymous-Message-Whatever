<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_data.php';

start_secure_session();
require_admin_login();

$pdo = get_db_connection();
$questions = get_questions_config();

$filters = build_message_filters($_GET);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$result = fetch_messages($pdo, $filters, $page, ADMIN_PAGE_SIZE);
$analytics = fetch_analytics($pdo);

function qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
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
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
<header class="admin-header">
    <h1>Messages <span class="admin-count">(<?= (int) $analytics['total'] ?>)</span></h1>
    <a href="logout.php" class="admin-logout-link">Log out</a>
</header>

<main class="admin-dashboard">

    <section class="analytics-row">
        <div class="analytics-card">
            <span class="analytics-value"><?= (int) $analytics['total'] ?></span>
            <span class="analytics-label">Total</span>
        </div>
        <div class="analytics-card">
            <span class="analytics-value"><?= (int) $analytics['today'] ?></span>
            <span class="analytics-label">Today</span>
        </div>
        <div class="analytics-card">
            <span class="analytics-value"><?= (int) $analytics['this_week'] ?></span>
            <span class="analytics-label">This week</span>
        </div>
        <div class="analytics-card">
            <span class="analytics-value"><?= (int) $analytics['unread'] ?></span>
            <span class="analytics-label">Unread</span>
        </div>
        <div class="analytics-card">
            <span class="analytics-value"><?= (int) $analytics['with_attachments'] ?></span>
            <span class="analytics-label">With attachments</span>
        </div>
    </section>

    <?php if ($analytics['top_reason'] || $analytics['top_found'] || $analytics['top_familiarity']): ?>
    <section class="analytics-top">
        <?php if ($analytics['top_reason']): ?>
            <span class="analytics-top-item">Most common reason: <strong><?= e($analytics['top_reason']) ?></strong></span>
        <?php endif; ?>
        <?php if ($analytics['top_found']): ?>
            <span class="analytics-top-item">Most common discovery: <strong><?= e($analytics['top_found']) ?></strong></span>
        <?php endif; ?>
        <?php if ($analytics['top_familiarity']): ?>
            <span class="analytics-top-item">Most common familiarity: <strong><?= e($analytics['top_familiarity']) ?></strong></span>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <form method="get" class="filter-bar" id="filter-form">
        <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search messages..." class="filter-search">

        <details class="filter-more">
            <summary>Filters</summary>
            <div class="filter-grid">
                <label>From
                    <input type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>">
                </label>
                <label>To
                    <input type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>">
                </label>
                <label>Read state
                    <select name="read">
                        <option value="">Any</option>
                        <option value="unread" <?= ($_GET['read'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option>
                        <option value="read" <?= ($_GET['read'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option>
                    </select>
                </label>
                <label>Attachment
                    <select name="attachment">
                        <option value="">Any</option>
                        <option value="any" <?= ($_GET['attachment'] ?? '') === 'any' ? 'selected' : '' ?>>Has attachment</option>
                        <option value="image" <?= ($_GET['attachment'] ?? '') === 'image' ? 'selected' : '' ?>>Image</option>
                        <option value="video" <?= ($_GET['attachment'] ?? '') === 'video' ? 'selected' : '' ?>>Video</option>
                        <option value="none" <?= ($_GET['attachment'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </label>
                <label>Link
                    <select name="link">
                        <option value="">Any</option>
                        <option value="yes" <?= ($_GET['link'] ?? '') === 'yes' ? 'selected' : '' ?>>Has link</option>
                        <option value="no" <?= ($_GET['link'] ?? '') === 'no' ? 'selected' : '' ?>>No link</option>
                    </select>
                </label>
                <label>Reason
                    <select name="reason">
                        <option value="">Any</option>
                        <?php foreach ($questions['reason']['choices'] as $choice): ?>
                            <option value="<?= e($choice) ?>" <?= ($_GET['reason'] ?? '') === $choice ? 'selected' : '' ?>><?= e($choice) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Found via
                    <select name="found">
                        <option value="">Any</option>
                        <?php foreach ($questions['found']['choices'] as $choice): ?>
                            <option value="<?= e($choice) ?>" <?= ($_GET['found'] ?? '') === $choice ? 'selected' : '' ?>><?= e($choice) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Familiarity
                    <select name="familiarity">
                        <option value="">Any</option>
                        <?php foreach ($questions['familiarity']['choices'] as $choice): ?>
                            <option value="<?= e($choice) ?>" <?= ($_GET['familiarity'] ?? '') === $choice ? 'selected' : '' ?>><?= e($choice) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="filter-actions">
                <button type="submit" class="filter-apply-btn">Apply</button>
                <a href="dashboard.php" class="filter-clear-link">Clear all</a>
            </div>
        </details>
    </form>

    <?php if (empty($result['messages'])): ?>
        <p class="admin-empty">Nothing here that matches.</p>
    <?php endif; ?>

    <div class="message-list">
    <?php foreach ($result['messages'] as $msg): ?>
        <?php
            $link = $msg['submitted_link'] ? e($msg['submitted_link']) : null;
            $ipReadable = @inet_ntop($msg['ip_address']) ?: 'unknown';
        ?>
        <article class="message-card <?= $msg['is_read'] ? '' : 'is-unread' ?>" data-id="<?= (int) $msg['id'] ?>">
            <div class="message-card-top">
                <span class="message-timestamp"><?= e(date('F j, Y g:i A', strtotime($msg['created_at']))) ?></span>
                <?php if (!$msg['is_read']): ?><span class="unread-dot" aria-label="Unread"></span><?php endif; ?>
            </div>

            <section class="message-section">
                <h3 class="section-label">Message</h3>
                <p class="message-text"><?= nl2br(e($msg['message'])) ?></p>

                <?php if ($link): ?>
                    <p class="message-link">
                        <a href="<?= $link ?>" target="_blank" rel="noopener noreferrer nofollow"><?= $link ?></a>
                    </p>
                <?php endif; ?>
            </section>

            <?php if ($msg['attachment_path']): ?>
                <section class="message-section">
                    <h3 class="section-label">Attachment</h3>
                    <div class="message-attachment">
                        <?php if ($msg['attachment_type'] === 'image'): ?>
                            <img src="../uploads/<?= e($msg['attachment_path']) ?>" alt="Attachment" loading="lazy">
                        <?php elseif ($msg['attachment_type'] === 'video'): ?>
                            <video src="../uploads/<?= e($msg['attachment_path']) ?>" controls preload="metadata"></video>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="message-section">
                <h3 class="section-label">Context</h3>
                <dl class="message-meta">
                    <dt>Why they are here</dt>
                    <dd><?= e($msg['why_clicked']) ?><?php if ($msg['why_clicked_other']): ?><br><span class="meta-other"><?= e($msg['why_clicked_other']) ?></span><?php endif; ?></dd>

                    <dt>How they found the link</dt>
                    <dd><?= e($msg['where_found']) ?><?php if ($msg['where_found_other']): ?><br><span class="meta-other"><?= e($msg['where_found_other']) ?></span><?php endif; ?></dd>

                    <dt>How well they know me</dt>
                    <dd><?= $msg['how_well_known'] !== '' ? e($msg['how_well_known']) : 'Not asked yet' ?><?php if ($msg['how_well_known_other']): ?><br><span class="meta-other"><?= e($msg['how_well_known_other']) ?></span><?php endif; ?></dd>
                </dl>
            </section>

            <details class="message-tech">
                <summary>Technical information</summary>
                <dl class="message-meta">
                    <dt>IP address</dt>
                    <dd><?= e($ipReadable) ?></dd>
                    <dt>Browser / User agent</dt>
                    <dd><?= e($msg['user_agent']) ?></dd>
                    <dt>Requested page</dt>
                    <dd><?= e($msg['requested_page']) ?></dd>
                    <?php if ($msg['attachment_mime']): ?>
                        <dt>Attachment MIME type</dt>
                        <dd><?= e($msg['attachment_mime']) ?></dd>
                    <?php endif; ?>
                </dl>
            </details>

            <div class="card-footer">
                <button type="button" class="read-toggle-btn" data-id="<?= (int) $msg['id'] ?>" data-read="<?= $msg['is_read'] ? '1' : '0' ?>">
                    <?= $msg['is_read'] ? 'Mark as unread' : 'Mark as read' ?>
                </button>
                <button type="button" class="delete-btn" data-id="<?= (int) $msg['id'] ?>">Delete</button>
            </div>
        </article>
    <?php endforeach; ?>
    </div>

    <?php if ($result['total_pages'] > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($result['page'] > 1): ?>
                <a href="<?= e(qs(['page' => $result['page'] - 1])) ?>" class="page-link">Previous</a>
            <?php endif; ?>
            <span class="page-info">Page <?= (int) $result['page'] ?> of <?= (int) $result['total_pages'] ?></span>
            <?php if ($result['page'] < $result['total_pages']): ?>
                <a href="<?= e(qs(['page' => $result['page'] + 1])) ?>" class="page-link">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

</main>

<script>
    window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
</script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
