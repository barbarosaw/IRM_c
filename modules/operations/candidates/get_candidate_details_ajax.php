<?php
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

if (!has_permission('operations-candidates-access')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid candidate ID.']);
    exit;
}

try {
    // Fetch candidate data
    $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ?");
    $stmt->execute([$id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        echo json_encode(['success' => false, 'message' => 'Candidate not found.']);
        exit;
    }

    // Fetch categories
    $cat_stmt = $db->prepare('SELECT c.id, c.name FROM candidate_categories cc JOIN categories c ON cc.category_id = c.id WHERE cc.candidate_id = ?');
    $cat_stmt->execute([$id]);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    $candidate['categories'] = $categories;

    echo json_encode(['success' => true, 'data' => $candidate]);

} catch (Exception $e) {
    error_log("AJAX candidate fetch failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching data.']);
}
