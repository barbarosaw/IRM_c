<?php
/**
 * AJAX Handler for Fingerprint Module
 */

// Prevent direct access and add security check
if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No action specified']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1);

try {
    // Define AW_SYSTEM constant to allow access to Fingerprint model
    define('AW_SYSTEM', true);
    
    // Include required files
    require_once '../../config/database.php';
    require_once 'models/Fingerprint.php';
    
    // Check if database connection exists
    if (!isset($db) || !$db) {
        throw new Exception('Database connection not available');
    }
    
    $fingerprintModel = new Fingerprint($db);
    
    switch ($_GET['action']) {
        case 'get_ip_details':
            if (!isset($_GET['ip']) || empty($_GET['ip'])) {
                echo json_encode(['error' => 'IP parameter missing or empty']);
                exit;
            }
            
            $ip = trim($_GET['ip']);
            $details = $fingerprintModel->getIpDetails($ip);
            echo json_encode($details);
            break;
            
        case 'get_user_details':
            if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
                echo json_encode(['error' => 'User ID parameter missing or empty']);
                exit;
            }
            
            $userId = trim($_GET['user_id']);
            $details = $fingerprintModel->getUserFingerprints($userId);
            echo json_encode($details);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action: ' . $_GET['action']]);
            break;
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Fingerprint AJAX Error: " . $e->getMessage());
    
    // Return error as JSON
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'action' => $_GET['action'] ?? 'unknown'
        ]
    ]);
} catch (Error $e) {
    // Handle fatal errors
    error_log("Fingerprint AJAX Fatal Error: " . $e->getMessage());
    
    echo json_encode([
        'error' => 'A fatal error occurred: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

exit;
?>
