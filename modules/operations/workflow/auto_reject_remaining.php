<?php
/**
 * Operations Module - Auto Reject Remaining Candidates
 * After client approves enough candidates, auto-reject the rest and log
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-workflow-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$job_request_id = (int)($_GET['job_request_id'] ?? 0);
$approved_limit = (int)($_GET['approved_limit'] ?? 1); // kaç kişi onaylanınca kalanlar reddedilecek
if (!$job_request_id || $approved_limit < 1) {
    header('Location: ../job_requests/index.php');
    exit;
}

// Count approved candidates
$stmt = $db->prepare('SELECT COUNT(*) FROM job_candidate_presentations WHERE job_request_id = ? AND client_approved = 1');
$stmt->execute([$job_request_id]);
$approved_count = (int)$stmt->fetchColumn();

if ($approved_count >= $approved_limit) {
    // Reject all other candidates not approved
    $stmt = $db->prepare('SELECT id FROM job_candidate_presentations WHERE job_request_id = ? AND (client_approved IS NULL OR client_approved = 0)');
    $stmt->execute([$job_request_id]);
    $to_reject = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($to_reject as $presentation_id) {
        $now = date('Y-m-d H:i:s');
        $db->prepare('UPDATE job_candidate_presentations SET client_approved=0, client_approved_at=?, client_rejected_reason=?, status=?, updated_at=? WHERE id=?')
            ->execute([$now, 'Auto rejected after enough approvals', 'Rejected by Client', $now, $presentation_id]);
        $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
            ->execute([$presentation_id, 'Auto Rejected', 'Auto rejected after enough approvals', 'Auto rejection', $_SESSION['user_id'], $now]);
    }
}
header('Location: ../job_requests/view.php?id=' . $job_request_id . '&auto_rejected=1');
exit;
