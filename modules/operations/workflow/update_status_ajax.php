<?php
/**
 * Operations Module - Update Candidate Workflow Status via AJAX
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check permissions
if (!has_permission('operations-workflow-approve')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$presentation_id = (int)($_POST['presentation_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = trim($_POST['note'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$presentation_id || !in_array($action, ['approve', 'reject', 'job_offer', 'hire', 'candidate_approve', 'candidate_reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Fetch presentation and related data
    $stmt = $db->prepare(
        'SELECT jc.job_request_id, jc.candidate_id, c.name as candidate_name, jr.job_title
         FROM job_candidate_presentations jc
         JOIN candidates c ON jc.candidate_id = c.id
         JOIN job_requests jr ON jc.job_request_id = jr.id
         WHERE jc.id = ?'
    );
    $stmt->execute([$presentation_id]);
    $presentation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$presentation) {
        throw new Exception("Presentation not found.");
    }

    $job_request_id = $presentation['job_request_id'];
    $candidate_name = $presentation['candidate_name'];

    // 2. Determine new status and log action message
    $current_status_stmt = $db->prepare('SELECT status FROM job_candidate_presentations WHERE id = ?');
    $current_status_stmt->execute([$presentation_id]);
    $current_status = $current_status_stmt->fetchColumn();

    $new_status = '';
    $log_action = '';
    if ($action === 'approve') {
        if ($current_status === 'manager_review') {
            $new_status = 'client_review';
            $log_action = "Candidate '{$candidate_name}' approved by Manager and sent to Client Review.";
        } else if ($current_status === 'client_review') {
            $new_status = 'client_approved';
            $log_action = "Candidate '{$candidate_name}' approved by Client. Awaiting Manager Job Offer/Hire.";
        } else if ($current_status === 'job_offered') {
            $new_status = 'hired';
            $log_action = "Candidate '{$candidate_name}' approved by Candidate and Hired.";
        } else {
            $new_status = 'manager_review';
            $log_action = "Candidate '{$candidate_name}' approved for Manager Review.";
        }
    } else if ($action === 'job_offer') {
        $new_status = 'job_offered';
        $log_action = "Job offer sent to '{$candidate_name}'.";
    } else if ($action === 'hire') {
        $new_status = 'hired';
        $log_action = "Candidate '{$candidate_name}' directly hired by Manager.";
    } else if ($action === 'candidate_approve') {
        $new_status = 'hired';
        $log_action = "Candidate '{$candidate_name}' accepted the job offer and hired.";
    } else if ($action === 'candidate_reject') {
        $new_status = 'rejected';
        $log_action = "Candidate '{$candidate_name}' rejected the job offer.";
    } else { // reject
        $new_status = 'rejected';
        $log_action = "Candidate '{$candidate_name}' was rejected.";
    }

    // 3. Update presentation status
    $update_stmt = $db->prepare(
        'UPDATE job_candidate_presentations SET status = ?, updated_at = NOW() WHERE id = ?'
    );
    $update_stmt->execute([$new_status, $presentation_id]);

    // 4. Log in job_request_logs
    $log_stmt = $db->prepare(
        'INSERT INTO job_request_logs (job_request_id, action, note, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $log_stmt->execute([$job_request_id, $log_action, $note, $user_id]);
    
    // 5. Log in job_candidate_logs (tüm mevcut alanlar)
    $cand_log_stmt = $db->prepare(
        'INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $details = $log_action;
    $cand_log_stmt->execute([$presentation_id, $log_action, $details, $note, $user_id]);


    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Workflow updated successfully!']);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Workflow update failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
