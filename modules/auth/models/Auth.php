<?php
/**
 * AbroadWorks Management System - Auth Model
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Auth {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Authenticate a user with email and password
     *
     * @param string $email User email
     * @param string $password User password
     * @return array|false User data or false if authentication fails
     */
    public function authenticate($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['is_active'] == 1) {
                    return $user;
                }
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log the user in and set session variables
     *
     * @param array $user User information
     * @param boolean $remember Whether to set a remember token
     * @return void
     */
    public function loginUser($user, $remember = false) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_owner'] = $user['is_owner'] ?? 0;
        
        // Update last login time
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Create a new session record
        $token = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $this->db->prepare("INSERT INTO sessions (user_id, token, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user['id'], $token, $ip, $agent]);
        
        // If remember me is selected, set a cookie
        if ($remember) {
            $remember_token = bin2hex(random_bytes(32));
            
            // Store the remember token in the users table
            $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$remember_token, $user['id']]);
            
            // Set a cookie that expires in 30 days
            setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        }
        
        // Load user permissions
        $this->loadUserPermissions($user['id']);
        
        // Log the login activity (but not for owner)
        if (!$user['is_owner']) {
            $this->logActivity($user['id'], 'auth', null, 'User logged in', $ip, $agent);
        }
    }
    
    /**
     * Load user permissions into session
     *
     * @param int $user_id User ID
     * @return void
     */
    public function loadUserPermissions($user_id) {
        // Initialize arrays
        $_SESSION['user_roles'] = [];
        $_SESSION['user_permissions'] = [];
        
        // Get user roles
        $stmt = $this->db->prepare("
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
            $stmt = $this->db->prepare("
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
        $stmt = $this->db->prepare("
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
     * Log user out and destroy session
     *
     * @return void
     */
    public function logoutUser() {
        if (isset($_SESSION['user_id'])) {
            // Mark current session as expired
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt = $this->db->prepare("
                UPDATE sessions 
                SET expired = 1 
                WHERE user_id = ? AND ip_address = ? AND user_agent = ? 
                ORDER BY last_activity DESC 
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id'], $ip, $agent]);
            
            // Log the logout activity
            $this->logActivity($_SESSION['user_id'], 'auth', null, 'User logged out', $ip, $agent);
        }
        
        // Clear the remember token
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $this->db->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
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
    public function checkRememberMe() {
        if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            
            // Find user with this token
            $stmt = $this->db->prepare("SELECT * FROM users WHERE remember_token = ? AND is_active = 1");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Auto login the user
                $this->loginUser($user, true);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a user has a specific permission
     *
     * @param string $permission Permission code
     * @return boolean
     */
    public function hasPermission($permission) {
        if (!isset($_SESSION['user_permissions'])) {
            return false;
        }
        
        // Owner users have all permissions
        if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
            return true;
        }
        
        // Admins have all permissions
        if (in_array('Administrator', $_SESSION['user_roles'] ?? [])) {
            return true;
        }
        
        return in_array($permission, $_SESSION['user_permissions']);
    }
    
    /**
     * Check if a user has a specific role
     *
     * @param string $role Role name
     * @return boolean
     */
    public function hasRole($role) {
        if (!isset($_SESSION['user_roles'])) {
            return false;
        }
        
        return in_array($role, $_SESSION['user_roles']);
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
    public function logActivity($user_id, $action, $entity_type, $description, $ip = null, $agent = null) {
        // Don't log activities for owner account
        if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) ||
            ($user_id && $this->isUserOwner($user_id))) {
            return;
        }
        
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $agent ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        try {
            $stmt = $this->db->prepare("
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
    public function isUserOwner($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT is_owner FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() == 1;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if a user has 2FA enabled
     *
     * @param int $user_id User ID
     * @return boolean
     */
    public function is2FAEnabled($user_id) {
        // Check if user is owner (owners bypass 2FA)
        if ($this->isUserOwner($user_id)) {
            return false;
        }
        
        $stmt = $this->db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Verify a 2FA code for a user
     *
     * @param int $user_id User ID
     * @param string $code 2FA code
     * @return boolean
     */
    public function verify2FACode($user_id, $code) {
        $stmt = $this->db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $secret = $stmt->fetchColumn();
        
        if (!$secret) {
            return false;
        }
        
        // Use the Google Authenticator library to verify the code
        $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        return $g->checkCode($secret, $code);
    }
    
    /**
     * Verify a recovery code for a user
     *
     * @param int $user_id User ID
     * @param string $code Recovery code
     * @return boolean
     */
    public function verifyRecoveryCode($user_id, $code) {
        $stmt = $this->db->prepare("
            SELECT id FROM two_factor_recovery_codes 
            WHERE user_id = ? AND code = ? AND is_used = 0
            LIMIT 1
        ");
        $stmt->execute([$user_id, $code]);
        $recovery_code_id = $stmt->fetchColumn();
        
        if ($recovery_code_id) {
            // Mark the code as used
            $stmt = $this->db->prepare("
                UPDATE two_factor_recovery_codes 
                SET is_used = 1, used_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$recovery_code_id]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate recovery codes for a user
     *
     * @param int $user_id User ID
     * @param int $count Number of codes to generate
     * @return array Array of recovery codes
     */
    public function generateRecoveryCodes($user_id, $count = 8) {
        // Delete any existing unused recovery codes
        $stmt = $this->db->prepare("DELETE FROM two_factor_recovery_codes WHERE user_id = ? AND is_used = 0");
        $stmt->execute([$user_id]);
        
        $codes = [];
        
        // Generate new recovery codes
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
            $code = chunk_split($code, 5, '-');
            $code = rtrim($code, '-');
            $codes[] = $code;
            
            $stmt = $this->db->prepare("INSERT INTO two_factor_recovery_codes (user_id, code, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $code]);
        }
        
        return $codes;
    }
    
    /**
     * Enable 2FA for a user
     *
     * @param int $user_id User ID
     * @param string $secret Secret key
     * @return boolean Success status
     */
    public function enable2FA($user_id, $secret) {
        // Check if user is owner (owners cannot enable 2FA)
        if ($this->isUserOwner($user_id)) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET two_factor_enabled = 1, two_factor_secret = ? 
                WHERE id = ?
            ");
            $stmt->execute([$secret, $user_id]);
            
            // Generate recovery codes
            $this->generateRecoveryCodes($user_id);
            
            // Log the activity
            $this->logActivity($user_id, 'security', 'user', 'Two-factor authentication enabled');
            
            return true;
        } catch (Exception $e) {
            error_log('Error enabling 2FA: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA for a user
     *
     * @param int $user_id User ID
     * @return boolean Success status
     */
    public function disable2FA($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET two_factor_enabled = 0, two_factor_secret = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            
            // Delete recovery codes
            $stmt = $this->db->prepare("DELETE FROM two_factor_recovery_codes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Log the activity
            $this->logActivity($user_id, 'security', 'user', 'Two-factor authentication disabled');
            
            return true;
        } catch (Exception $e) {
            error_log('Error disabling 2FA: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a new 2FA secret
     *
     * @return string
     */
    public function generate2FASecret() {
        $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        return $g->generateSecret();
    }
    
    /**
     * Generate a QR code URL for Google Authenticator
     *
     * @param string $user_email User's email
     * @param string $secret The secret key
     * @return string The QR code URL
     */
    public function generate2FAQrUrl($user_email, $secret) {
        $issuer = get_setting('two_factor_issuer', 'AbroadWorks Management');
        return \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($user_email, $secret, $issuer);
    }
    
    /**
     * Check if 2FA is globally enabled in system settings
     *
     * @return boolean Whether 2FA is enabled
     */
    public function is2FAGloballyEnabled() {
        return get_setting('two_factor_enabled', '0') === '1';
    }
    
    /**
     * Check if 2FA is enforced for all users
     *
     * @return boolean Whether 2FA is enforced
     */
    public function is2FAEnforced() {
        // Owner users are always exempt from 2FA enforcement
        if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
            return false;
        }
        
        return get_setting('two_factor_enforce', '0') === '1';
    }
    
    /**
     * Register a new user
     *
     * @param array $data User data
     * @return int|false User ID or false on failure
     */
    public function registerUser($data) {
        try {
            // Check if registration is enabled
            if (get_setting('enable_registration', '0') !== '1') {
                return false;
            }
            
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetchColumn() > 0) {
                return false;
            }
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Determine if approval is required
            $is_active = get_setting('approval_required', '0') === '1' ? 0 : 1;
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$data['name'], $data['email'], $hashed_password, $is_active]);
            
            $user_id = $this->db->lastInsertId();
            
            // Assign default role if set
            $default_role = get_setting('default_role', '');
            if (!empty($default_role)) {
                $stmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $default_role]);
            }
            
            // Log the activity
            $this->logActivity(null, 'auth', 'user', "New user registered: {$data['email']}");
            
            return $user_id;
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Request a password reset
     *
     * @param string $email User email
     * @return boolean Success status
     */
    public function requestPasswordReset($email) {
        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token
            $stmt = $this->db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $token, $expires_at]);
            
            // Log the activity
            $this->logActivity($user['id'], 'auth', 'user', 'Password reset requested');
            
            return [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'token' => $token,
                'expires_at' => $expires_at
            ];
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify a password reset token
     *
     * @param string $token Reset token
     * @return array|false User data or false if token is invalid
     */
    public function verifyResetToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT pr.user_id, u.name, u.email
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
                ORDER BY pr.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$token]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset a user's password
     *
     * @param string $token Reset token
     * @param string $password New password
     * @return boolean Success status
     */
    public function resetPassword($token, $password) {
        try {
            // Verify token
            $user = $this->verifyResetToken($token);
            if (!$user) {
                return false;
            }
            
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user's password
            $stmt = $this->db->prepare("
                UPDATE users 
                SET password = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$hashed_password, $user['user_id']]);
            
            // Mark token as used
            $stmt = $this->db->prepare("
                UPDATE password_resets 
                SET used = 1, used_at = NOW() 
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            // Log the activity
            $this->logActivity($user['user_id'], 'auth', 'user', 'Password reset completed');
            
            return true;
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return false;
        }
    }
}
