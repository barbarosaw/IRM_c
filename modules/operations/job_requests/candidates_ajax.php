<?php
/**
 * Candidates AJAX Filter Endpoint
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';
header('Content-Type: application/json');

// Filtreler
$category = trim($_POST['category'] ?? '');
$status = trim($_POST['status'] ?? '');
$internal_status = trim($_POST['internal_status'] ?? '');
$external_status = trim($_POST['external_status'] ?? '');
$done = trim($_POST['done'] ?? '');
$keyword = trim($_POST['keyword'] ?? '');
$order = trim($_POST['order'] ?? 'created_at_desc');

$sql = "SELECT c.*, GROUP_CONCAT(cat.name) as categories FROM candidates c
    LEFT JOIN candidate_categories cc ON c.id = cc.candidate_id
    LEFT JOIN categories cat ON cc.category_id = cat.id
    WHERE 1=1";
$params = [];
if ($category) {
    $sql .= " AND FIND_IN_SET(?, GROUP_CONCAT(cat.name))";
    $params[] = $category;
}
if ($status) {
    $sql .= " AND c.status = ?";
    $params[] = $status;
}
if ($internal_status) {
    $sql .= " AND c.internal_status = ?";
    $params[] = $internal_status;
}
if ($external_status) {
    $sql .= " AND c.external_status = ?";
    $params[] = $external_status;
}
if ($done !== '') {
    $sql .= " AND c.done = ?";
    $params[] = $done;
}
if ($keyword) {
    $sql .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.profile LIKE ? OR c.notes LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
$sql .= " GROUP BY c.id";
// Sıralama
switch ($order) {
    case 'name_asc': $sql .= " ORDER BY c.name ASC"; break;
    case 'name_desc': $sql .= " ORDER BY c.name DESC"; break;
    case 'created_at_asc': $sql .= " ORDER BY c.created_at ASC"; break;
    case 'created_at_desc': default: $sql .= " ORDER BY c.created_at DESC"; break;
    case 'status_asc': $sql .= " ORDER BY c.status ASC"; break;
    case 'status_desc': $sql .= " ORDER BY c.status DESC"; break;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();
echo json_encode(['success' => true, 'candidates' => $candidates]);
