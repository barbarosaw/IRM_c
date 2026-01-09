<?php
/**
 * Add Note to Workflow Log
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';
header('Content-Type: application/json');

if (!has_permission('operations-workflow-manage')) {
    echo json_encode(['success' => false, 'error' => 'No permission']);
    exit;
}

$log_id = (int)($_POST['log_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
if (!$log_id || $note === '') {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

$stmt = $db->prepare('UPDATE job_candidate_logs SET note = ?, note_added_by = ?, note_added_at = NOW() WHERE id = ?');
$stmt->execute([$note, $_SESSION['user_id'], $log_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Not updated']);
}
