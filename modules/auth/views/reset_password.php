<?php
/**
 * AbroadWorks Management System - Reset Password View
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-logo">
                <img src="<?php echo $root_path; ?>assets/images/logo.png" alt="<?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?> Logo" height="70" class="mb-3">
                <h1><?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></h1>
                <p class="text-muted">Create New Password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div class="text-center mt-3">
                    <a href="?action=login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-1"></i> Go to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <?php if (isset($user)): ?>
                            <p>Hello, <strong><?php echo htmlspecialchars($user['name'] ?? ''); ?></strong>!</p>
                            <p>Please enter your new password below.</p>
                            
                            <form method="post" id="resetPasswordForm" class="needs-validation" novalidate>
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                    </div>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                    <div class="invalid-feedback">Please enter a password with at least 8 characters.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Reset Password</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Invalid or expired reset token. Please request a new password reset link.
                            </div>
                            <div class="d-grid gap-2">
                                <a href="?action=forgot-password" class="btn btn-primary">
                                    <i class="fas fa-redo me-1"></i> Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <a href="?action=login" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
            
            <div class="mt-3 text-center text-muted">
                <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></small>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo $root_path; ?>assets/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.getElementById('resetPasswordForm');
            
            if (form) {
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                
                // Check if passwords match
                function validatePassword() {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity("Passwords do not match");
                    } else {
                        confirmPassword.setCustomValidity("");
                    }
                }
                
                password.addEventListener('change', validatePassword);
                confirmPassword.addEventListener('keyup', validatePassword);
                
                form.addEventListener('submit', function(event) {
                    validatePassword();
                    
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                });
            }
        });
    </script>
</body>
</html>
