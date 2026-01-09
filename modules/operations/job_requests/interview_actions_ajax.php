<?php
/**
 * Handle interview actions: cancel or complete
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

if (!has_permission('operations-workflow-approve')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$interview_id = (int)($_POST['interview_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = trim($_POST['note'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;

if (!$interview_id || !in_array($action, ['cancel', 'complete']) || !$note) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    // Get interview and presentation info
    $stmt = $db->prepare('SELECT i.*, jc.job_request_id, c.name as candidate_name FROM job_candidate_interviews i JOIN job_candidate_presentations jc ON i.job_candidate_presentation_id = jc.id JOIN candidates c ON jc.candidate_id = c.id WHERE i.id = ?');
    $stmt->execute([$interview_id]);
    $interview = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$interview) throw new Exception('Interview not found.');

    if ($action === 'cancel') {
        $db->prepare('UPDATE job_candidate_interviews SET status = ? WHERE id = ?')->execute(['cancelled', $interview_id]);
        $log_action = "Interview for '{$interview['candidate_name']}' cancelled.";
    } else {
        $db->prepare('UPDATE job_candidate_interviews SET status = ? WHERE id = ?')->execute(['completed', $interview_id]);
        $log_action = "Interview for '{$interview['candidate_name']}' completed.";
    }
    // Log to job_request_logs
    $db->prepare('INSERT INTO job_request_logs (job_request_id, action, note, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$interview['job_request_id'], $log_action, $note, $user_id]);
    echo json_encode(['success' => true, 'message' => 'Interview action updated and logged.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
