<?php
/**
 * Save Interview AJAX Endpoint
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

if (!has_permission('operations-workflow-approve')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$presentation_id = (int)($_POST['presentation_id'] ?? 0);
$scheduled_at = $_POST['scheduled_at'] ?? '';
$platform = trim($_POST['platform'] ?? '');
$meeting_link = trim($_POST['meeting_link'] ?? '');
$timezone = trim($_POST['timezone'] ?? '');
$duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
$topics = trim($_POST['topics'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;

if (!$presentation_id || !$scheduled_at || !$platform || !$timezone || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    $stmt = $db->prepare('INSERT INTO job_candidate_interviews (job_candidate_presentation_id, scheduled_at, platform, meeting_link, timezone, duration_minutes, topics, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $presentation_id,
        $scheduled_at,
        $platform,
        $meeting_link,
        $timezone,
        $duration_minutes,
        $topics,
        $user_id
    ]);
    echo json_encode(['success' => true, 'message' => 'Interview scheduled successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
