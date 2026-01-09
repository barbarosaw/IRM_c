<?php
/**
 * AbroadWorks Management System - Session Termination Handler
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
check_page_access();

$user_id = $_SESSION['user_id'];
$success = false;
$message = '';

// Terminate a specific session
if (isset($_POST['terminate']) && isset($_POST['session_id'])) {
    $session_id = (int)$_POST['session_id'];
    
    // Make sure the session belongs to the current user
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND user_id = ?");
    $stmt->execute([$session_id, $user_id]);
    $session = $stmt->fetch();
    
    if ($session) {
        $stmt = $db->prepare("UPDATE sessions SET expired = 1 WHERE id = ?");
        
        try {
            $stmt->execute([$session_id]);
            
            // Log the activity
            log_activity($user_id, 'security', 'session', "Terminated session ID: $session_id");
            
            $success = true;
            $message = "Session terminated successfully.";
        } catch (PDOException $e) {
            $message = "Failed to terminate session: " . $e->getMessage();
        }
    } else {
        $message = "Invalid session or permission denied.";
    }
}

// Terminate all other sessions
if (isset($_POST['terminate_all']) && isset($_POST['all_sessions'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Terminate all sessions except the current one
    $stmt = $db->prepare("
        UPDATE sessions 
        SET expired = 1 
        WHERE user_id = ? 
        AND NOT (ip_address = ? AND user_agent = ?) 
        AND expired = 0
    ");

    try {
        $stmt->execute([$user_id, $ip, $agent]);

        // Log the activity
        log_activity($user_id, 'security', 'session', "Terminated all other sessions");

        $success = true;
        $message = "All other sessions terminated successfully.";
    } catch (PDOException $e) {
        $message = "Failed to terminate sessions: " . $e->getMessage();
    }
}


// Terminate all sessions for a user
if (isset($_POST['terminate_all_forId']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];

    // Terminate all sessions except the current one
    $stmt = $db->prepare("
        UPDATE sessions 
        SET expired = 1 
        WHERE user_id = ? 
    ");

    try {
        $stmt->execute([$user_id]);

        // Log the activity
        log_activity($user_id, 'security', 'session', "Terminated user -". $user_id ."- sessions");

        $success = true;
        $message = "All sessions terminated successfully.";
    } catch (PDOException $e) {
        $message = "Failed to terminate sessions: " . $e->getMessage();
    }
}

// Store result in session and redirect back to profile
if ($success) {
    show_message($message, 'success');
} else {
    show_message($message, 'danger');
}

header('Location: profile.php');
exit;
