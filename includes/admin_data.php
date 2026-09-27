<?php

function build_message_filters(array $input): array
{
    $where = [];
    $params = [];

    $search = trim($input['q'] ?? '');
    if ($search !== '') {
        $where[] = 'message LIKE :search';
        $params['search'] = '%' . $search . '%';
    }

    $dateFrom = trim($input['from'] ?? '');
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim($input['to'] ?? '');
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'created_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $attachment = $input['attachment'] ?? '';
    if ($attachment === 'image' || $attachment === 'video') {
        $where[] = 'attachment_type = :attachment_type';
        $params['attachment_type'] = $attachment;
    } elseif ($attachment === 'any') {
        $where[] = 'attachment_path IS NOT NULL';
    } elseif ($attachment === 'none') {
        $where[] = 'attachment_path IS NULL';
    }

    $hasLink = $input['link'] ?? '';
    if ($hasLink === 'yes') {
        $where[] = 'submitted_link IS NOT NULL';
    } elseif ($hasLink === 'no') {
        $where[] = 'submitted_link IS NULL';
    }

    $readState = $input['read'] ?? '';
    if ($readState === 'read') {
        $where[] = 'is_read = 1';
    } elseif ($readState === 'unread') {
        $where[] = 'is_read = 0';
    }

    $questions = get_questions_config();
    $questionColumnMap = [
        'reason'      => 'why_clicked',
        'found'       => 'where_found',
        'familiarity' => 'how_well_known',
    ];

    foreach ($questionColumnMap as $key => $column) {
        $value = $input[$key] ?? '';
        if ($value !== '' && in_array($value, $questions[$key]['choices'], true)) {
            $where[] = $column . ' = :filter_' . $key;
            $params['filter_' . $key] = $value;
        }
    }

    return ['where' => $where, 'params' => $params];
}

function fetch_messages(PDO $pdo, array $filters, int $page, int $pageSize): array
{
    $whereSql = empty($filters['where']) ? '' : ('WHERE ' . implode(' AND ', $filters['where']));

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM messages $whereSql");
    $countStmt->execute($filters['params']);
    $total = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $pageSize));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $pageSize;

    $sql = "SELECT * FROM messages $whereSql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($filters['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'messages'    => $stmt->fetchAll(),
        'total'       => $total,
        'page'        => $page,
        'total_pages' => $totalPages,
    ];
}

function fetch_analytics(PDO $pdo): array
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();

    $today = (int) $pdo->query(
        "SELECT COUNT(*) FROM messages WHERE created_at >= CURDATE()"
    )->fetchColumn();

    $thisWeek = (int) $pdo->query(
        "SELECT COUNT(*) FROM messages WHERE created_at >= (NOW() - INTERVAL 7 DAY)"
    )->fetchColumn();

    $withAttachments = (int) $pdo->query(
        'SELECT COUNT(*) FROM messages WHERE attachment_path IS NOT NULL'
    )->fetchColumn();

    $unread = (int) $pdo->query(
        'SELECT COUNT(*) FROM messages WHERE is_read = 0'
    )->fetchColumn();

    $topReason = $pdo->query(
        'SELECT why_clicked, COUNT(*) AS cnt FROM messages GROUP BY why_clicked ORDER BY cnt DESC LIMIT 1'
    )->fetch();

    $topFound = $pdo->query(
        'SELECT where_found, COUNT(*) AS cnt FROM messages GROUP BY where_found ORDER BY cnt DESC LIMIT 1'
    )->fetch();

    $topFamiliarity = $pdo->query(
        "SELECT how_well_known, COUNT(*) AS cnt FROM messages WHERE how_well_known != '' GROUP BY how_well_known ORDER BY cnt DESC LIMIT 1"
    )->fetch();

    return [
        'total'            => $total,
        'today'            => $today,
        'this_week'        => $thisWeek,
        'with_attachments' => $withAttachments,
        'unread'           => $unread,
        'top_reason'       => $topReason ? $topReason['why_clicked'] : null,
        'top_found'        => $topFound ? $topFound['where_found'] : null,
        'top_familiarity'  => $topFamiliarity ? $topFamiliarity['how_well_known'] : null,
    ];
}
