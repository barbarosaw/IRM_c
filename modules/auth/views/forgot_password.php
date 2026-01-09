<?php
/**
 * AbroadWorks Management System - Forgot Password View
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
    <title>Forgot Password | <?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></title>
    
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
                <p class="text-muted">Reset Your Password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p>Enter your email address below and we'll send you instructions to reset your password.</p>
                        
                        <form method="post" id="forgotPasswordForm" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send Reset Link</button>
                            </div>
                        </form>
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
            const form = document.getElementById('forgotPasswordForm');
            
            form.addEventListener('submit', function(event) {
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
