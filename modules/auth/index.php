<?php
/**
 * AbroadWorks Management System - Auth Module
 * 
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../includes/init.php';
require_once '../../vendor/autoload.php';

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'auth'");
$stmt->execute();
$is_active = $stmt->fetchColumn();

// If module is not active and user is not an owner, redirect to module-inactive page
if ($is_active === false || $is_active == 0) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        header('Location: ../../module-inactive.php?module=auth');
        exit;
    }
}

// Include model
require_once 'models/Auth.php';

// Initialize model
$authModel = new Auth();

// Set page title
$page_title = "Authentication";

// Set root path for components
$root_path = '../../';

// Handle login
if (isset($_GET['action']) && $_GET['action'] == 'login') {
    $error = '';
    $success = '';
    
    // When the form is submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = clean_input($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } else {
            // Authenticate user
            $user = $authModel->authenticate($email, $password);
            
            if ($user) {
                // User is valid, but check if account is active
                if ($user['is_active'] == 1) {

                    // 2FA enforce veya user aktifse, kodu kontrol et
                    $stmt_enforce = $db->prepare("SELECT value FROM settings WHERE `key` = 'two_factor_enforce' LIMIT 1");
                    $stmt_enforce->execute();
                    $enforce = $stmt_enforce->fetchColumn();

                    $require_2fa = false;
                    if ($enforce === '1') {
                        $require_2fa = true;
                    } elseif (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
                        $require_2fa = true;
                    }

                    if ($require_2fa) {
                        $code_input = isset($_POST['twofa_code']) ? trim($_POST['twofa_code']) : '';
                        $stmt_secret = $db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
                        $stmt_secret->execute([$user['id']]);
                        $secret = $stmt_secret->fetchColumn();

                        $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
                        if (!$g->checkCode($secret, $code_input)) {
                            $error = 'Invalid two-factor authentication code.';
                            // Include login view again
                            include 'views/login.php';
                            exit;
                        }
                    }
                    // Check if user is owner (bypass 2FA)
                    if (isset($user['is_owner']) && $user['is_owner'] == 1) {
                        // Owner users bypass 2FA
                        $authModel->loginUser($user, $remember);
                        
                        header('Location: ../../index.php');
                        exit;
                    }

                    // Check enforce setting
                    $stmt_enforce = $db->prepare("SELECT value FROM settings WHERE `key` = 'two_factor_enforce' LIMIT 1");
                    $stmt_enforce->execute();
                    $enforce = $stmt_enforce->fetchColumn();

                    if ($enforce === '1') {
                        // Enforce aktifse, 2FA zorunlu
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['needs_2fa_verification'] = true;

                        header('Location: /modules/auth/index.php?action=verify-2fa');
                        exit;
                    }
                    // Enforce değilse, kullanıcının 2FA aktif mi?
                    else if (isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['needs_2fa_verification'] = true;

                        header('Location: /modules/auth/index.php?action=verify-2fa');
                        exit;
                    } else {
                        // Login successful without 2FA
                        $authModel->loginUser($user, $remember);
                        
                        header('Location: ../../index.php');
                        exit;
                    }
                } else {
                    $error = 'Your account is not active. Please contact the administrator.';
                }
            } else {
                $error = 'Invalid email or password.';
                error_log("Login failed for user {$email}: Authentication failed");
            }
        }
    }
    
    // Include login view
    include 'views/login.php';
    exit;
}

// Handle 2FA verification
if (isset($_GET['action']) && $_GET['action'] == 'verify-2fa') {
    // Eğer zaten doğrulandıysa veya ihtiyaç yoksa ana sayfaya yönlendir
    if (!isset($_SESSION['user_id']) || empty($_SESSION['needs_2fa_verification']) || !empty($_SESSION['2fa_verified'])) {
        header('Location: ../../index.php');
        exit;
    }
    
    // Check if user is owner (owners bypass 2FA)
    if ($authModel->isUserOwner($_SESSION['user_id'])) {
        // Owner users bypass 2FA
        unset($_SESSION['needs_2fa_verification']);
        $_SESSION['2fa_verified'] = true;
        
        // Redirect to dashboard
        header('Location: ../../index.php');
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $error = '';
    $success = '';
    
    // Get user information
    $stmt = $db->prepare("SELECT name, email, two_factor_secret FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['two_factor_secret'])) {
        // User not found or 2FA not set up
        unset($_SESSION['needs_2fa_verification']);
        header('Location: ?action=login');
        exit;
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'verify_code') {
            $code = clean_input($_POST['code'] ?? '');
            
            if (empty($code)) {
                $error = 'Please enter the verification code.';
            } else {
                // Verify the code
                if ($authModel->verify2FACode($user_id, $code)) {
                    // Code is valid, complete login
                    unset($_SESSION['needs_2fa_verification']);
                    $_SESSION['2fa_verified'] = true;
                    
                    // Log the successful 2FA verification
                    $authModel->logActivity($user_id, 'auth', null, 'Two-factor authentication verified');
                    
                    // Redirect to dashboard
                    header('Location: ../../index.php');
                    exit;
                } else {
                    $error = 'Invalid verification code. Please try again.';
                }
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'use_recovery_code') {
            $recovery_code = clean_input($_POST['recovery_code'] ?? '');
            
            if (empty($recovery_code)) {
                $error = 'Please enter the recovery code.';
            } else {
                // Verify the recovery code
                if ($authModel->verifyRecoveryCode($user_id, $recovery_code)) {
                    // Recovery code is valid, complete login
                    unset($_SESSION['needs_2fa_verification']);
                    $_SESSION['2fa_verified'] = true;
                    
                    // Log the successful recovery code usage
                    $authModel->logActivity($user_id, 'auth', null, 'Recovery code used for authentication');
                    
                    // Redirect to dashboard
                    header('Location: ../../index.php');
                    exit;
                } else {
                    $error = 'Invalid recovery code. Please try again.';
                }
            }
        }
    }
    
    // Include 2FA verification view
    include 'views/verify_2fa.php';
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $authModel->logoutUser();
    header('Location: ?action=login');
    exit;
}

// Handle registration
if (isset($_GET['action']) && $_GET['action'] == 'register') {
    // Check if registration is enabled
    if (get_setting('enable_registration', '0') !== '1') {
        header('Location: ?action=login');
        exit;
    }
    
    $error = '';
    $success = '';
    
    // When the form is submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = clean_input($_POST['name']);
        $email = clean_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Register user
            $user_id = $authModel->registerUser([
                'name' => $name,
                'email' => $email,
                'password' => $password
            ]);
            
            if ($user_id) {
                // Check if approval is required
                if (get_setting('approval_required', '0') === '1') {
                    $success = 'Registration successful! Your account is pending approval by an administrator.';
                } else {
                    $success = 'Registration successful! You can now log in.';
                }
            } else {
                $error = 'Registration failed. Email may already be in use.';
            }
        }
    }
    
    // Include registration view
    include 'views/register.php';
    exit;
}

// Handle forgot password
if (isset($_GET['action']) && $_GET['action'] == 'forgot-password') {
    $error = '';
    $success = '';
    
    // When the form is submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = clean_input($_POST['email']);
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Request password reset
            $reset_data = $authModel->requestPasswordReset($email);
            
            if ($reset_data) {
                // In a real application, you would send an email with the reset link
                // For this example, we'll just show the token
                $reset_link = $root_path . 'modules/auth/?action=reset-password&token=' . $reset_data['token'];
                
                $success = 'Password reset instructions have been sent to your email address.';
                
                // For development purposes, show the reset link
                if (get_setting('display_errors', '0') === '1') {
                    $success .= '<br><br>Development mode: <a href="' . $reset_link . '">Reset Password Link</a>';
                }
            } else {
                $error = 'No account found with that email address.';
            }
        }
    }
    
    // Include forgot password view
    include 'views/forgot_password.php';
    exit;
}

// Handle reset password
if (isset($_GET['action']) && $_GET['action'] == 'reset-password') {
    $error = '';
    $success = '';
    
    // Check if token is provided
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        header('Location: ?action=forgot-password');
        exit;
    }
    
    $token = $_GET['token'];
    
    // Verify token
    $user = $authModel->verifyResetToken($token);
    
    if (!$user) {
        $error = 'Invalid or expired reset token.';
    } else {
        // When the form is submitted
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($password) || empty($confirm_password)) {
                $error = 'All fields are required.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } else {
                // Reset password
                if ($authModel->resetPassword($token, $password)) {
                    $success = 'Password has been reset successfully. You can now log in with your new password.';
                } else {
                    $error = 'Failed to reset password. Please try again.';
                }
            }
        }
    }
    
    // Include reset password view
    include 'views/reset_password.php';
    exit;
}

// Handle settings page
if (isset($_GET['action']) && $_GET['action'] == 'settings') {
    // Check if user has permission to manage auth settings
    if (!has_permission('auth-manage')) {
        header('Location: ../../access-denied.php');
        exit;
    }
    
    $error = '';
    $success = '';
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_auth_settings') {
        // Checkboxlar işaretli değilse 0 olarak set et
        if (!isset($_POST['settings']['two_factor_enabled'])) {
            $_POST['settings']['two_factor_enabled'] = '0';
        }
        if (!isset($_POST['settings']['two_factor_enforce'])) {
            $_POST['settings']['two_factor_enforce'] = '0';
        }

        try {
            // Update settings
            $updated = 0;
            
            foreach ($_POST['settings'] as $key => $value) {
                // Clean input
                $value = clean_input($value);
                
                // Update setting
                $stmt = $db->prepare("
                    INSERT INTO settings (`key`, `value`, `updated_at`) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()
                ");
                $stmt->execute([$key, $value, $value]);
                $updated++;
            }
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'update', 'settings', "Updated authentication settings");
            
            $success = "Authentication settings updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating settings: " . $e->getMessage();
        }
    }
    
    // Set page title
    $page_title = "Authentication Settings";
    
    // Include header
    include '../../components/header.php';
    
    // Include sidebar
    include '../../components/sidebar.php';
    
    // Include content wrapper start
    echo '<div class="content-wrapper">';
    echo '<div class="content-header">';
    echo '<div class="container-fluid">';
    echo '<div class="row mb-2">';
    echo '<div class="col-sm-6">';
    echo '<h1 class="m-0 text-primary">' . $page_title . '</h1>';
    echo '</div>';
    echo '<div class="col-sm-6">';
    echo '<ol class="breadcrumb float-sm-end">';
    echo '<li class="breadcrumb-item"><a href="' . $root_path . 'index.php">Home</a></li>';
    echo '<li class="breadcrumb-item active">' . $page_title . '</li>';
    echo '</ol>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="content">';
    echo '<div class="container-fluid">';
    
    // Display messages
    if ($success) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo $success;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    
    if ($error) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo $error;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    
    // Include settings view
    include 'views/settings.php';
    
    // Include content wrapper end
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Include footer
    include '../../components/footer.php';
    exit;
}

// Default action - redirect to login
header('Location: ?action=login');
exit;
