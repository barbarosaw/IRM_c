<?php
/**
 * AbroadWorks Management System - Two-Factor Authentication Helper
 * 
 * @author ikinciadam@gmail.com
 */

// Require the Google Authenticator library
require_once __DIR__ . '/../vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

/**
 * Generate a new secret key for 2FA
 *
 * @return string The generated secret key
 */
function generate_2fa_secret() {
    $g = new GoogleAuthenticator();
    return $g->generateSecret();
}

/**
 * Verify a 2FA code against a secret
 *
 * @param string $secret The secret key
 * @param string $code The code to verify
 * @return boolean Whether the code is valid
 */
function verify_2fa_code($secret, $code) {
    $g = new GoogleAuthenticator();
    return $g->checkCode($secret, $code);
}

/**
 * Generate a QR code URL for Google Authenticator
 *
 * @param string $user_email User's email
 * @param string $secret The secret key
 * @return string The QR code URL
 */
function generate_2fa_qr_url($user_email, $secret) {
    $issuer = get_setting('two_factor_issuer', 'AbroadWorks Management');
    return GoogleQrUrl::generate($user_email, $secret, $issuer);
}

/**
 * Generate recovery codes for a user
 *
 * @param int $user_id User ID
 * @param int $count Number of codes to generate (default: 8)
 * @return array Array of recovery codes
 */
function generate_recovery_codes($user_id, $count = 8) {
    global $db;
    
    // Delete any existing unused recovery codes
    $stmt = $db->prepare("DELETE FROM two_factor_recovery_codes WHERE user_id = ? AND is_used = 0");
    $stmt->execute([$user_id]);
    
    $codes = [];
    
    // Generate new recovery codes
    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
        $code = chunk_split($code, 5, '-');
        $code = rtrim($code, '-');
        $codes[] = $code;
        
        $stmt = $db->prepare("INSERT INTO two_factor_recovery_codes (user_id, code, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $code]);
    }
    
    return $codes;
}

/**
 * Verify a recovery code for a user
 *
 * @param int $user_id User ID
 * @param string $code Recovery code to verify
 * @return boolean Whether the code is valid
 */
function verify_recovery_code($user_id, $code) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT id FROM two_factor_recovery_codes 
        WHERE user_id = ? AND code = ? AND is_used = 0
        LIMIT 1
    ");
    $stmt->execute([$user_id, $code]);
    $recovery_code_id = $stmt->fetchColumn();
    
    if ($recovery_code_id) {
        // Mark the code as used
        $stmt = $db->prepare("
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
 * Enable 2FA for a user
 *
 * @param int $user_id User ID
 * @param string $secret Secret key
 * @return boolean Success status
 */
function enable_2fa_for_user($user_id, $secret) {
    global $db;
    
    // Check if user is owner (owners cannot enable 2FA)
    $stmt = $db->prepare("SELECT is_owner FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $is_owner = (bool) $stmt->fetchColumn();
    
    if ($is_owner) {
        return false; // Owner users cannot enable 2FA
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET two_factor_enabled = 1, two_factor_secret = ? 
            WHERE id = ?
        ");
        $stmt->execute([$secret, $user_id]);
        
        // Generate recovery codes
        $codes = generate_recovery_codes($user_id);
        
        // Log the activity
        log_activity($user_id, 'security', 'user', 'Two-factor authentication enabled');
        
        return true;
    } catch (Exception $e) {
        log_error('Error enabling 2FA: ' . $e->getMessage());
        return false;
    }
}

/**
 * Disable 2FA for a user
 *
 * @param int $user_id User ID
 * @return boolean Success status
 */
function disable_2fa_for_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET two_factor_enabled = 0, two_factor_secret = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        
        // Delete recovery codes
        $stmt = $db->prepare("DELETE FROM two_factor_recovery_codes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Log the activity
        log_activity($user_id, 'security', 'user', 'Two-factor authentication disabled');
        
        return true;
    } catch (Exception $e) {
        log_error('Error disabling 2FA: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if 2FA is enabled for a user
 *
 * @param int $user_id User ID
 * @return boolean Whether 2FA is enabled
 */
function is_2fa_enabled_for_user($user_id) {
    global $db;
    
    // Check if user is owner (owners bypass 2FA)
    $stmt = $db->prepare("SELECT is_owner FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $is_owner = (bool) $stmt->fetchColumn();
    
    if ($is_owner) {
        return false; // Owner users are treated as if 2FA is disabled
    }
    
    $stmt = $db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Get a user's 2FA secret
 *
 * @param int $user_id User ID
 * @return string|null The secret key or null if not set
 */
function get_user_2fa_secret($user_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * Check if 2FA is globally enabled in system settings
 *
 * @return boolean Whether 2FA is enabled
 */
function is_2fa_enabled() {
    return get_setting('two_factor_enabled', '0') === '1';
}

/**
 * Check if 2FA is enforced for all users
 *
 * @return boolean Whether 2FA is enforced
 */
function is_2fa_enforced() {
    // Owner users are always exempt from 2FA enforcement
    if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
        return false;
    }
    
    return get_setting('two_factor_enforce', '0') === '1';
}

/**
 * Enforce 2FA verification if required
 * 
 * Eğer sistemde enforce aktifse tüm kullanıcılar, değilse sadece kendi hesabında 2FA aktif olanlar doğrulama yapmalı
 * 
 * @return void
 */
function enforce_2fa_if_required() {
    if (!isset($_SESSION['user_id'])) {
        return; // login değilse
    }

    if (!empty($_SESSION['2fa_verified'])) {
        return; // zaten doğrulandıysa
    }

    // Eğer zaten 2FA doğrulama sayfasındaysa, yeniden yönlendirme yapma
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($current_url, 'verify-2fa') !== false || strpos($current_url, 'two-factor-verify.php') !== false) {
        return;
    }

    // Süper adminler muaf
    if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
        return;
    }

    global $db;

    $user_id = $_SESSION['user_id'];

    // Enforce aktif mi?
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'two_factor_enforce' LIMIT 1");
    $stmt->execute();
    $enforce = $stmt->fetchColumn();

    if ($enforce === '1') {
        header('Location: two-factor-verify.php');
        exit;
    }

    // Enforce değilse, kullanıcının 2FA aktif mi?
    $stmt = $db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_2fa = $stmt->fetchColumn();

    if ($user_2fa === '1') {
        header('Location: two-factor-verify.php');
        exit;
    }
}
