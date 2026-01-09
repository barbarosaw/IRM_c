<?php
/**
 * AbroadWorks Management System - Auth Helper Functions
 * 
 * @author ikinciadam@gmail.com
 */

// Bu fonksiyonu kaldırıyorum veya yorum satırına alıyorum
/* 
function debug_auth_function($name, $params = []) {
    echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ddd;'>";
    echo "Function: <strong>$name</strong>\n";
    echo "Parameters: " . print_r($params, true);
    echo "</pre>";
}
*/

/**
 * Check if the user is logged in
 *
 * @return boolean
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Log the user in and set session variables
 *
 * @param array $user User information
 * @param boolean $remember Whether to set a remember token
 * @return void
 */
function login_user($user, $remember = false) {
    global $db;
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_owner'] = $user['is_owner'] ?? 0;
    
    // Update last login time
    $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Create a new session record
    $token = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $db->prepare("INSERT INTO sessions (user_id, token, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user['id'], $token, $ip, $agent]);
    
    // If remember me is selected, set a cookie
    if ($remember) {
        $remember_token = bin2hex(random_bytes(32));
        
        // Store the remember token in the users table
        $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$remember_token, $user['id']]);
        
        // Set a cookie that expires in 30 days
        setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }
    
    // Load user permissions
    load_user_permissions($user['id']);
    
    // Log the login activity (but not for owner)
    if (!$user['is_owner']) {
        log_activity($user['id'], 'auth', null, 'User logged in', $ip, $agent);
    }
}

/**
 * Load user permissions into session
 *
 * @param int $user_id User ID
 * @return void
 */
function load_user_permissions($user_id) {
    global $db;
    
    // Initialize arrays
    $_SESSION['user_roles'] = [];
    $_SESSION['user_permissions'] = [];
    
    // Get user roles
    $stmt = $db->prepare("
        SELECT r.* 
        FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $roles = $stmt->fetchAll();
    
    foreach ($roles as $role) {
        $_SESSION['user_roles'][] = $role['name'];
        
        // Get permissions for this role
        $stmt = $db->prepare("
            SELECT p.code 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$role['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add permissions to session
        $_SESSION['user_permissions'] = array_merge($_SESSION['user_permissions'], $permissions);
    }
    
    // Get direct user permissions
    $stmt = $db->prepare("
        SELECT p.code 
        FROM permissions p
        JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ? AND up.granted = 1
    ");
    $stmt->execute([$user_id]);
    $direct_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add direct permissions to session
    $_SESSION['user_permissions'] = array_merge($_SESSION['user_permissions'], $direct_permissions);
    
    // Remove duplicates
    $_SESSION['user_permissions'] = array_unique($_SESSION['user_permissions']);
}

/**
 * Check if the user has a specific permission
 *
 * @param string $permission Permission code
 * @return boolean
 */
function has_permission($permission) {
    global $db;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Owner her zaman tüm izinlere sahip
    if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
        return true;
    }

    $user_id = $_SESSION['user_id'];

    // Get user roles
    $stmt = $db->prepare("
        SELECT r.name 
        FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Admins have all permissions
    if (in_array('Administrator', $roles)) {
        return true;
    }

    // Check if the user is an owner directly from the database
    if (is_user_owner($user_id)) {
        return true;
    }

    // Get permissions from roles
    $role_permissions = [];
    foreach ($roles as $role_name) {
        $stmt2 = $db->prepare("
            SELECT p.code 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN roles r ON rp.role_id = r.id
            WHERE r.name = ?
        ");
        $stmt2->execute([$role_name]);
        $role_permissions = array_merge($role_permissions, $stmt2->fetchAll(PDO::FETCH_COLUMN));
    }

    // Get direct user permissions
    $stmt = $db->prepare("
        SELECT p.code 
        FROM permissions p
        JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ? AND up.granted = 1
    ");
    $stmt->execute([$user_id]);
    $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $all_permissions = array_unique(array_merge($role_permissions, $user_permissions));

    return in_array($permission, $all_permissions);
}

/**
 * Check if the user has a specific role
 *
 * @param string $role Role name
 * @return boolean
 */
function has_role($role) {
    if (!isset($_SESSION['user_roles'])) {
        return false;
    }
    
    return in_array($role, $_SESSION['user_roles']);
}

/**
 * Log user out and destroy session
 *
 * @return void
 */
function logout_user() {
    global $db;
    
    if (isset($_SESSION['user_id'])) {
        // Mark current session as expired
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $db->prepare("
            UPDATE sessions 
            SET expired = 1 
            WHERE user_id = ? AND ip_address = ? AND user_agent = ? 
            ORDER BY last_activity DESC 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id'], $ip, $agent]);
        
        // Log the logout activity
        log_activity($_SESSION['user_id'], 'auth', null, 'User logged out', $ip, $agent);
    }
    
    // Clear the remember token
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Clear all session variables
    session_unset();
    
    // Destroy the session
    session_destroy();
}

/**
 * Check for remember me cookie and log user in if valid
 *
 * @return boolean Whether auto-login was successful
 */
function check_remember_me() {
    // Debug fonksiyon çağrısını kaldırıyorum
    // debug_auth_function('check_remember_me');
    
    global $db;
    
    if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // Find user with this token
        $stmt = $db->prepare("SELECT * FROM users WHERE remember_token = ? AND is_active = 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Auto login the user
            login_user($user, true);
            return true;
        }
    }
    
    return false;
}

/**
 * Check if access to the current page is allowed
 *
 * @param string $required_permission Permission required to view the page
 * @return boolean
 */
function check_page_access($required_permission = null) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    
    if ($required_permission && !has_permission($required_permission)) {
        header('Location: access-denied.php');
        exit;
    }
    
    return true;
}

/**
 * Log an activity
 *
 * @param int $user_id User ID (or null for system actions)
 * @param string $action Action type (auth, create, update, delete, etc.)
 * @param string $entity_type Entity type (user, role, vendor, etc.)
 * @param string $description Description of the activity
 * @param string $ip IP address
 * @param string $agent User agent
 * @return void
 */
function log_activity($user_id, $action, $entity_type, $description, $ip = null, $agent = null) {
    global $db;
    
    // Don't log activities for owner account
    if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) ||
        ($user_id && is_user_owner($user_id))) {
        return;
    }
    
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $agent ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $entity_type, $description, $ip, $agent]);
    } catch (Exception $e) {
        // In case the activity_logs table doesn't exist yet
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Check if a user is an owner
 *
 * @param int $user_id User ID to check
 * @return boolean
 */
function is_user_owner($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT is_owner FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() == 1;
    } catch (Exception $e) {
        return false;
    }
}
?>
