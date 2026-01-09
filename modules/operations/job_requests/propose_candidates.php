<?php
/**
 * Operations Module - Propose Candidates AJAX Endpoint
 * Handles proposing selected candidates and logs the action with a note.
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check permissions
if (!has_permission('operations-workflow-manage')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// Get POST data from AJAX request
$job_request_id = (int)($_POST['job_request_id'] ?? 0);
$candidate_ids = $_POST['candidate_ids'] ?? [];
$note = trim($_POST['note'] ?? '');

// --- ROBUSTNESS FIX ---
// If candidate_ids are sent as a comma-separated string, convert to array.
if (is_string($candidate_ids)) {
    $candidate_ids = array_filter(array_map('intval', explode(',', $candidate_ids)));
}

// Validate input
if (empty($job_request_id) || empty($candidate_ids) || !is_array($candidate_ids) || empty($note)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required data. Please select at least one candidate and provide a note.']);
    exit;
}

$db->beginTransaction();

try {
    $proposed_count = 0;
    $already_proposed_count = 0;

    // Prepare statements for reuse
    $stmt_check = $db->prepare(
        'SELECT COUNT(*) FROM job_candidate_presentations WHERE job_request_id = ? AND candidate_id = ?'
    );
    $stmt_insert = $db->prepare(
        'INSERT INTO job_candidate_presentations (job_request_id, candidate_id, presented_by, presented_at, status) VALUES (?, ?, ?, NOW(), ?)'
    );

    foreach ($candidate_ids as $candidate_id) {
        $cid = (int)$candidate_id;
        if ($cid > 0) {
            // Check if the candidate has already been proposed for this job
            $stmt_check->execute([$job_request_id, $cid]);
            if ($stmt_check->fetchColumn() == 0) {
                // If not, insert them
                $stmt_insert->execute([$job_request_id, $cid, $_SESSION['user_id'], 'Proposed']);
                $proposed_count++;
            } else {
                $already_proposed_count++;
            }
        }
    }

    // Only log and update status if new candidates were actually proposed
    if ($proposed_count > 0) {
        // Log this action in the main job request history
        $log_action = "Proposed " . $proposed_count . " candidate(s)";
        $stmt_log = $db->prepare(
            'INSERT INTO job_request_logs (job_request_id, action, note, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt_log->execute([$job_request_id, $log_action, $note, $_SESSION['user_id']]);

        // Update the job request status to 'Candidates Proposed'
        $stmt_update = $db->prepare(
            'UPDATE job_requests SET status = ? WHERE id = ?'
        );
        $stmt_update->execute(['Candidates Proposed', $job_request_id]);
    }

    $db->commit();

    // Construct a meaningful response message
    $message = '';
    if ($proposed_count > 0) {
        $message .= $proposed_count . ' candidate(s) were successfully proposed.';
    }
    if ($already_proposed_count > 0) {
        $message .= ' ' . $already_proposed_count . ' candidate(s) had already been proposed and were skipped.';
    }
    if (empty($message)) {
        $message = 'All selected candidates had already been proposed.';
    }

    echo json_encode(['status' => 'success', 'message' => trim($message)]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    error_log("Proposal Error: " . $e->getMessage()); // Log error for debugging
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred while proposing candidates. Please try again.']);
}
?>
