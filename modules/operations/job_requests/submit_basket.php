<?php
/**
 * Operations Module - Submit Candidate Basket (AJAX)
 * Receives candidate basket from frontend and processes for manager review
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

if (!has_permission('operations-jobrequests-manage')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$job_request_id = (int)($_POST['job_request_id'] ?? 0);
$candidate_ids = $_POST['candidate_ids'] ?? [];

if (!$job_request_id || !is_array($candidate_ids) || count($candidate_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

// Insert each candidate into job_candidate_presentations
foreach ($candidate_ids as $candidate_id) {
    $stmt = $db->prepare('INSERT INTO job_candidate_presentations (job_request_id, candidate_id, presented_by, presented_at, status, updated_at) VALUES (?, ?, ?, NOW(), ?, NOW())');
    $stmt->execute([$job_request_id, $candidate_id, $_SESSION['user_id'], 'Manager Review']);
    $presentation_id = $db->lastInsertId();
    // Log
    $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$presentation_id, 'Proposed', 'Candidate proposed to manager (basket)', $_SESSION['user_id']]);
}
// Update job request status
$db->prepare('UPDATE job_requests SET status = ? WHERE id = ?')->execute(['Manager Review', $job_request_id]);

// Notification örneği (geliştirilebilir)
// $db->prepare('INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())')->execute([$manager_id, 'New candidate basket submitted for review.']);
// Tüm işlemi history'ye kaydet
$db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())')
    ->execute([null, 'Basket Submitted', 'Candidate basket submitted for manager review (job_request_id: ' . $job_request_id . ')', $_SESSION['user_id']]);

echo json_encode(['success' => true, 'message' => 'Basket sent to manager!']);
exit;
