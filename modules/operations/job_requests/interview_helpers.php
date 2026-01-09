<?php
// Helper to fetch latest interview for a candidate presentation
function get_latest_interview($db, $presentation_id) {
    $stmt = $db->prepare('SELECT * FROM job_candidate_interviews WHERE job_candidate_presentation_id = ? ORDER BY scheduled_at DESC LIMIT 1');
    $stmt->execute([$presentation_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper to fetch all interviews for a candidate presentation
function get_all_interviews($db, $presentation_id) {
    $stmt = $db->prepare('SELECT * FROM job_candidate_interviews WHERE job_candidate_presentation_id = ? ORDER BY scheduled_at DESC');
    $stmt->execute([$presentation_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
