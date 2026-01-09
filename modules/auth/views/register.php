<?php
/**
 * AbroadWorks Management System - Registration View
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if registration is enabled
if (get_setting('enable_registration', '0') !== '1') {
    header('Location: ?action=login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></title>
    
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
                <p class="text-muted">Create a New Account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="post" id="registerForm" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                    <div class="invalid-feedback">Please enter your full name.</div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    </div>
                    <div class="form-text">Password must be at least 8 characters long.</div>
                    <div class="invalid-feedback">Please enter a password with at least 8 characters.</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="invalid-feedback">Passwords do not match.</div>
                </div>
                
                <?php if (get_setting('approval_required', '0') === '1'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Note: New accounts require administrator approval before they can be used.
                    </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Register</button>
                </div>
            </form>
            
            <div class="mt-3 text-center">
                <p>Already have an account? <a href="?action=login" class="text-decoration-none">Login</a></p>
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
            const form = document.getElementById('registerForm');
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
        });
    </script>
</body>
</html>
