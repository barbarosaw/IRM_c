<?php
/**
 * AbroadWorks Management System - Two-Factor Authentication Verification View
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
    <title>Two-Factor Authentication | <?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/style.css">
</head>
<body class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-logo">
            <img src="<?php echo $root_path; ?>assets/images/logo.png" alt="<?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?> Logo" class="img-fluid" style="max-height: 80px;">
            <h1><?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></h1>
            <p class="text-muted">Two-Factor Authentication</p>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Verification Code</h5>
                <p>Hello, <strong><?php echo htmlspecialchars($user['name']); ?></strong>!</p>
                <p>Please enter the verification code from your Google Authenticator app.</p>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="verify_code">
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Verification Code</label>
                        <input type="text" class="form-control" id="code" name="code" placeholder="6-digit code" required autofocus>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Verify</button>
                    </div>
                </form>
                
                <hr>
                
                <div class="mt-3">
                    <p class="text-center mb-3">Don't have access to your app?</p>
                    
                    <div class="accordion" id="recoveryAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    Use Recovery Code
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#recoveryAccordion">
                                <div class="accordion-body">
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="use_recovery_code">
                                        
                                        <div class="mb-3">
                                            <label for="recovery_code" class="form-label">Recovery Code</label>
                                            <input type="text" class="form-control" id="recovery_code" name="recovery_code" placeholder="XXXXX-XXXXX" required>
                                            <div class="form-text">
                                                Enter your recovery code. Each code can only be used once.
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-outline-secondary">Use Recovery Code</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3 text-center">
            <a href="?action=logout" class="text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> Sign Out
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-focus the code input
        document.getElementById('code').focus();
        
        // Format the recovery code input
        const recoveryCodeInput = document.getElementById('recovery_code');
        if (recoveryCodeInput) {
            recoveryCodeInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
                
                if (value.length > 5) {
                    value = value.substring(0, 5) + '-' + value.substring(5);
                }
                
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                
                e.target.value = value;
            });
        }
    });
    </script>
</body>
</html>
