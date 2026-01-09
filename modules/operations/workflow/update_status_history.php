<?php
/**
 * Operations Module - Update Status History for Candidate and Client
 * Logs status changes to candidate_logs and client_logs
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-workflow-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$job_candidate_presentation_id = (int)($_GET['id'] ?? 0);
if (!$job_candidate_presentation_id) {
    header('Location: index.php');
    exit;
}

// Fetch workflow info
$stmt = $db->prepare('SELECT w.*, jr.client_id, w.candidate_id, w.status FROM job_candidate_presentations w LEFT JOIN job_requests jr ON w.job_request_id = jr.id WHERE w.id = ?');
$stmt->execute([$job_candidate_presentation_id]);
$wf = $stmt->fetch();
if (!$wf) {
    header('Location: index.php');
    exit;
}

$now = date('Y-m-d H:i:s');

// Log to candidate_logs
$db->prepare('INSERT INTO candidate_logs (candidate_id, action, details, status, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$wf['candidate_id'], 'Status Update', 'Status changed to ' . $wf['status'], $wf['status'], $_SESSION['user_id'], $now]);

// Log to client_logs
$db->prepare('INSERT INTO client_logs (client_id, action, details, performed_by, created_at) VALUES (?, ?, ?, ?, ?)')
    ->execute([$wf['client_id'], 'Status Update', 'Status changed to ' . $wf['status'], $_SESSION['user_id'], $now]);

header('Location: view.php?id=' . $job_candidate_presentation_id . '&status_logged=1');
exit;
