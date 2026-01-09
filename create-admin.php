<?php
/**
 * AbroadWorks Management System - Create Admin User
 * 
 * @author ikinciadam@gmail.com
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$success = false;
$error = '';

// Check if admin user already exists
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_owner = 1");
$admin_exists = $stmt->fetchColumn() > 0;

// Only proceed if no admin user exists
if (!$admin_exists) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = clean_input($_POST['name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($password != $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Create admin user
                $hashed_password = password_hash_safe($password);
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password, is_active, is_owner, created_at, updated_at) 
                    VALUES (?, ?, ?, 1, 1, NOW(), NOW())
                ");
                $stmt->execute([$name, $email, $hashed_password]);
                
                // Get inserted user ID
                $admin_id = $db->lastInsertId();
                
                // Create default roles if they don't exist
                // Administrator role
                $stmt = $db->prepare("
                    INSERT IGNORE INTO roles (name, description, created_at, updated_at) 
                    VALUES ('Administrator', 'Full access to all system features', NOW(), NOW())
                ");
                $stmt->execute();
                
                // Manager role
                $stmt = $db->prepare("
                    INSERT IGNORE INTO roles (name, description, created_at, updated_at) 
                    VALUES ('Manager', 'Access to most features except system settings', NOW(), NOW())
                ");
                $stmt->execute();
                
                // Staff role
                $stmt = $db->prepare("
                    INSERT IGNORE INTO roles (name, description, created_at, updated_at) 
                    VALUES ('Staff', 'Limited access to basic features', NOW(), NOW())
                ");
                $stmt->execute();
                
                // Get Administrator role ID
                $stmt = $db->query("SELECT id FROM roles WHERE name = 'Administrator'");
                $admin_role_id = $stmt->fetchColumn();
                
                // Assign Administrator role to the admin user
                if ($admin_role_id) {
                    $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$admin_id, $admin_role_id]);
                }
                
                // Create default modules if they don't exist
                $stmt = $db->query("SELECT COUNT(*) FROM modules");
                $modules_exist = $stmt->fetchColumn() > 0;
                
                if (!$modules_exist) {
                    // Create default modules
                    $stmt = $db->prepare("
                        INSERT INTO `modules` (`name`, `code`, `description`, `icon`, `order`, `is_active`, `is_core`, `created_at`, `updated_at`) VALUES
                        ('Dashboard', 'dashboard', 'Main dashboard module', 'fa-tachometer-alt', 1, 1, 1, NOW(), NOW()),
                        ('Users', 'users', 'User management module', 'fa-users', 2, 1, 1, NOW(), NOW()),
                        ('Roles', 'roles', 'Role and permission management', 'fa-user-tag', 3, 1, 1, NOW(), NOW()),
                        ('Vendors', 'vendors', 'Vendor management module', 'fa-building', 4, 1, 0, NOW(), NOW()),
                        ('Reports', 'reports', 'Reports and analytics module', 'fa-chart-bar', 5, 1, 0, NOW(), NOW()),
                        ('Settings', 'settings', 'System settings module', 'fa-cog', 6, 1, 1, NOW(), NOW()),
                        ('Logs', 'logs', 'System logs and audit trails', 'fa-history', 7, 1, 0, NOW(), NOW())
                    ");
                    $stmt->execute();
                }
                
                // Create default permissions if they don't exist
                // Insert module-level permissions
                $stmt = $db->prepare("
                    INSERT INTO `permissions` (`name`, `code`, `module`, `module_order`, `is_module`, `description`, `created_at`, `updated_at`)
                    VALUES 
                    ('Access Dashboard', 'dashboard-access', 'dashboard', 1, 1, 'Can access the dashboard module', NOW(), NOW()),
                    ('Access Users', 'users-access', 'users', 2, 1, 'Can access the users module', NOW(), NOW()),
                    ('Access Roles', 'roles-access', 'roles', 3, 1, 'Can access the roles module', NOW(), NOW()),
                    ('Access Vendors', 'vendors-access', 'vendors', 4, 1, 'Can access the vendors module', NOW(), NOW()),
                    ('Access Reports', 'reports-access', 'reports', 5, 1, 'Can access the reports module', NOW(), NOW()),
                    ('Access Settings', 'settings-access', 'settings', 6, 1, 'Can access the settings module', NOW(), NOW()),
                    ('Access Logs', 'logs-access', 'logs', 7, 1, 'Can access the logs module', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE `updated_at` = NOW()
                ");
                $stmt->execute();
                
                // Insert action-level permissions
                $stmt = $db->prepare("
                    INSERT INTO `permissions` (`name`, `code`, `module`, `module_order`, `description`, `created_at`, `updated_at`)
                    VALUES 
                    ('View Dashboard', 'dashboard-view', 'dashboard', 1, 'Can view the main dashboard', NOW(), NOW()),
                    ('Manage Users', 'users-manage', 'users', 2, 'Can manage user accounts', NOW(), NOW()),
                    ('View Users', 'users-view', 'users', 2, 'Can view user accounts', NOW(), NOW()),
                    ('Manage Roles', 'roles-manage', 'roles', 3, 'Can manage roles and permissions', NOW(), NOW()),
                    ('View Roles', 'roles-view', 'roles', 3, 'Can view roles and permissions', NOW(), NOW()),
                    ('Manage Vendors', 'vendors-manage', 'vendors', 4, 'Can manage vendors', NOW(), NOW()),
                    ('View Vendors', 'vendors-view', 'vendors', 4, 'Can view vendors', NOW(), NOW()),
                    ('Manage Reports', 'reports-manage', 'reports', 5, 'Can manage reports', NOW(), NOW()),
                    ('View Reports', 'reports-view', 'reports', 5, 'Can view reports', NOW(), NOW()),
                    ('Manage Settings', 'settings-manage', 'settings', 6, 'Can manage system settings', NOW(), NOW()),
                    ('View Logs', 'logs-view', 'logs', 7, 'Can view activity logs', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE `updated_at` = NOW()
                ");
                $stmt->execute();
                
                // Assign all permissions to Administrator role
                $stmt = $db->query("SELECT id FROM permissions");
                $permission_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if ($admin_role_id && !empty($permission_ids)) {
                    foreach ($permission_ids as $permission_id) {
                        $stmt = $db->prepare("
                            INSERT INTO role_permissions (role_id, permission_id) 
                            VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE role_id = role_id
                        ");
                        $stmt->execute([$admin_role_id, $permission_id]);
                    }
                }
                
                // Make all modules visible for Administrator role
                if ($admin_role_id) {
                    $stmt = $db->query("SELECT code FROM modules");
                    $module_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($module_codes as $code) {
                        $stmt = $db->prepare("
                            INSERT INTO module_visibility (role_id, module_code, is_visible, created_at, updated_at)
                            VALUES (?, ?, 1, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE is_visible = 1, updated_at = NOW()
                        ");
                        $stmt->execute([$admin_role_id, $code]);
                    }
                }
                
                // Initialize settings table
                require_once 'includes/system_settings.php';
                
                // Commit transaction
                $db->commit();
                
                $success = true;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error = "Error creating admin user: " . $e->getMessage();
            }
        }
    }
} else {
    // Admin user already exists
    $error = "An administrator account already exists. Please use the login page.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Administrator | AbroadWorks Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-logo">
                <img src="assets/images/logo.png" alt="AbroadWorks Management Logo" height="70" class="mb-3">
                <h1>System Setup</h1>
                <p class="text-muted">Create Administrator Account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                    <?php if (strpos($error, 'already exists') !== false): ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4><i class="fas fa-check-circle me-2"></i> Success!</h4>
                    <p>Administrator account has been created successfully.</p>
                    <p>You can now log in to the system with your new account.</p>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            <?php elseif (!$admin_exists): ?>
                <form method="post" id="adminForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="invalid-feedback">Please enter your full name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                        </div>
                        <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="invalid-feedback">Passwords must match.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Create Administrator</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="mt-3 text-center text-muted">
                <small>&copy; <?php echo date('Y'); ?> AbroadWorks Management</small>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const form = document.getElementById('adminForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords do not match");
                    event.preventDefault();
                } else {
                    confirmPassword.setCustomValidity("");
                }
                
                form.classList.add('was-validated');
            }, false);
            
            // Clear custom validity on input
            document.getElementById('confirm_password').addEventListener('input', function() {
                if (this.value === document.getElementById('password').value) {
                    this.setCustomValidity('');
                } else {
                    this.setCustomValidity('Passwords do not match');
                }
            });
        }
    });
    </script>
</body>
</html>
