<?php
/**
 * AbroadWorks Management System - Fingerprint Data Receiver
 * 
 * Receives fingerprint data from Nextcloud and stores in database
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('ALLOW_RECEIVER_ACCESS')) {
    define('ALLOW_RECEIVER_ACCESS', true);
}

require_once __DIR__ . '/../../includes/init.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-KEY');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Validate API key from settings
try {
    // First try to get from settings table
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'fingerprint_api_key' LIMIT 1");
    $stmt->execute();
    $setting = $stmt->fetch();
    
    if ($setting && !empty($setting['value'])) {
        $expected_api_key = $setting['value'];
    } else {
        // Fallback to hardcoded API key if not found in settings
        $expected_api_key = 'ed1e7ddd8885d718244278e1a3f9c7b6eec7b1a31cb6feef3a6fcd640f63559d';
        
        // Optionally insert into settings table for future use
        try {
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `created_at`, `updated_at`) VALUES ('fingerprint_api_key', ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");
            $stmt->execute([$expected_api_key, $expected_api_key]);
        } catch (Exception $e) {
            // Ignore insert error, use hardcoded key
        }
    }
} catch (Exception $e) {
    // Use hardcoded API key as fallback
    $expected_api_key = 'ed1e7ddd8885d718244278e1a3f9c7b6eec7b1a31cb6feef3a6fcd640f63559d';
}

$provided_api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X-API-KEY'] ?? '';

// Try getallheaders() safely
$headers = getallheaders();
if ($headers && is_array($headers)) {
    // Check all possible case variations of X-API-Key
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-api-key') {
            $provided_api_key = $value;
            break;
        }
    }
}

if (empty($provided_api_key) || $provided_api_key !== $expected_api_key) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    
    // Log unauthorized access attempt using correct table structure
    try {
        $unauthorizedStmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $unauthorizedStmt->execute([
            54, // System user ID
            'unauthorized_access',
            'fingerprint_api',
            'Unauthorized API access attempt with key: ' . substr($provided_api_key, 0, 10) . '...',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (Exception $logError) {
        error_log("Failed to log unauthorized access: " . $logError->getMessage());
    }
    
    exit;
}

// Read and decode JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
$required_fields = ['user_id', 'user_agent', 'timestamp', 'ip'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

try {
    // Validate and sanitize data
    $user_id = clean_input($data['user_id']);
    $user_agent = clean_input($data['user_agent']);
    $timestamp = $data['timestamp'];
    $ip = clean_input($data['ip']);
    $request_method = clean_input($data['request_method'] ?? 'GET');
    $referer = isset($data['referer']) ? clean_input($data['referer']) : null;
    $session_id = isset($data['session_id']) ? clean_input($data['session_id']) : null;
    $url = isset($data['url']) ? clean_input($data['url']) : null;
    
    // Validate timestamp format
    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
    if (!$datetime) {
        throw new Exception('Invalid timestamp format. Expected: Y-m-d H:i:s');
    }
    
    // Validate IP address
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new Exception('Invalid IP address format');
    }
    
    // Insert into database
    $stmt = $db->prepare("
        INSERT INTO fingerprints 
        (user_id, user_agent, timestamp, ip, request_method, referer, session_id, url, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $stmt->execute([
        $user_id,
        $user_agent,
        $timestamp,
        $ip,
        $request_method,
        $referer,
        $session_id,
        $url
    ]);
    
    if ($result) {
        $fingerprint_id = $db->lastInsertId();
        
        // Log activity using correct table structure
        try {
            $activityStmt = $db->prepare("
                INSERT INTO activity_logs 
                (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $activityStmt->execute([
                54, // Fixed user_id = 54 for all fingerprint activities
                'create',
                'fingerprint',
                $fingerprint_id,
                json_encode([
                    'external_user_id' => $user_id,
                    'ip' => $ip,
                    'session_id' => $session_id,
                    'url' => $url,
                    'request_method' => $request_method,
                    'timestamp' => $timestamp
                ]),
                $ip,
                substr($user_agent, 0, 500), // Limit user_agent length
            ]);
        } catch (PDOException $e) {
            // Non-critical error - log but continue
            error_log("Activity logging failed (non-critical): " . $e->getMessage());
        }
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Fingerprint data received successfully',
            'fingerprint_id' => $fingerprint_id
        ]);
    } else {
        throw new Exception('Failed to insert fingerprint data');
    }
    
} catch (PDOException $e) {
    // Log database error to activity_logs
    try {
        $errorStmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $errorStmt->execute([
            54, // System user ID
            'error',
            'fingerprint',
            'Database error: ' . $e->getMessage(),
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (Exception $logError) {
        error_log("Failed to log error to activity_logs: " . $logError->getMessage());
    }
    
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    error_log("Fingerprint receiver database error: " . $e->getMessage());
    
} catch (Exception $e) {
    // Log general error to activity_logs
    try {
        $errorStmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $errorStmt->execute([
            54, // System user ID
            'error',
            'fingerprint',
            'General error: ' . $e->getMessage(),
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (Exception $logError) {
        error_log("Failed to log error to activity_logs: " . $logError->getMessage());
    }
    
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("Fingerprint receiver error: " . $e->getMessage());
}
?>
