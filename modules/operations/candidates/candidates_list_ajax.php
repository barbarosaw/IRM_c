<?php
/**
 * Operations Module - Candidates List AJAX Endpoint
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-candidates-access')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

// Get params
$page = (int)($_POST['page'] ?? 1);
$limit = 25; // Records per page
$offset = ($page - 1) * $limit;

$keyword = trim($_POST['keyword'] ?? '');
$internal_status = $_POST['internal_status'] ?? '';
$external_status = $_POST['external_status'] ?? '';
$category_id = (int)($_POST['category_id'] ?? 0);
$sort_by = $_POST['sort_by'] ?? 'created_at';
$sort_dir = $_POST['sort_dir'] ?? 'DESC';
$candidate_id = (int)($_POST['id'] ?? 0);

// Base query
$sql = "SELECT SQL_CALC_FOUND_ROWS c.*, GROUP_CONCAT(DISTINCT cat.name) as categories 
        FROM candidates c
        LEFT JOIN candidate_categories cc ON c.id = cc.candidate_id
        LEFT JOIN categories cat ON cc.category_id = cat.id";

// Filters
$where = [];
$params = [];

if ($candidate_id > 0) {
    $where[] = "c.id = ?";
    $params[] = $candidate_id;
} else {
    if ($keyword !== '') {
        $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.profile LIKE ? OR c.notes LIKE ?)";
        $keyword_param = "%$keyword%";
        array_push($params, $keyword_param, $keyword_param, $keyword_param, $keyword_param);
    }

    if ($internal_status !== '') {
        $where[] = "c.internal_status = ?";
        $params[] = $internal_status;
    }

    if ($external_status !== '') {
        $where[] = "c.external_status = ?";
        $params[] = $external_status;
    }

    if ($category_id > 0) {
        // We need to check if the candidate belongs to the selected category.
        // The GROUP_CONCAT is for display, so we need a proper JOIN or SUBQUERY for filtering.
        $where[] = "c.id IN (SELECT candidate_id FROM candidate_categories WHERE category_id = ?)";
        $params[] = $category_id;
    }
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

// Group by
$sql .= " GROUP BY c.id";

// Sorting
if ($candidate_id === 0) { // Only apply sorting to the list view
    $allowed_sort_columns = ['name', 'email', 'internal_status', 'external_status', 'created_at', 'done'];
    if (in_array($sort_by, $allowed_sort_columns)) {
        $sql .= " ORDER BY " . $sort_by . " " . (strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC');
    } else {
        $sql .= " ORDER BY created_at DESC"; // Default sort
    }
}

// Pagination
if ($candidate_id === 0) { // Only apply pagination to the list view
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
}

// Execute query
$stmt = $db->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Get total count for pagination
$total_records = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Output JSON
header('Content-Type: application/json');
echo json_encode([
    'candidates' => $candidates,
    'pagination' => [
        'page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total_records
    ]
]);
