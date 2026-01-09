<?php
/**
 * AbroadWorks Management System - Auth Settings View
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to manage auth settings
if (!has_permission('auth-manage')) {
    echo '<div class="alert alert-danger">You do not have permission to manage authentication settings.</div>';
    return;
}
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-shield-alt me-2"></i> Authentication Settings
        </h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="action" value="save_auth_settings">
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <h6 class="border-bottom pb-2 mb-3">Two-Factor Authentication (2FA)</h6>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="two_factor_enabled" name="settings[two_factor_enabled]" value="1" <?php echo get_setting('two_factor_enabled', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="two_factor_enabled">Enable Two-Factor Authentication</label>
                        <div class="form-text">Allow users to set up two-factor authentication for their accounts.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="two_factor_enforce" name="settings[two_factor_enforce]" value="1" <?php echo get_setting('two_factor_enforce', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="two_factor_enforce">Enforce Two-Factor Authentication</label>
                        <div class="form-text">Require all users to set up 2FA for their accounts. Owner accounts are exempt.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="two_factor_issuer" class="form-label">2FA Issuer Name</label>
                        <input type="text" class="form-control" id="two_factor_issuer" name="settings[two_factor_issuer]" value="<?php echo htmlspecialchars(get_setting('two_factor_issuer', 'AbroadWorks Management')); ?>">
                        <div class="form-text">The name that appears in authenticator apps.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="two_factor_recovery_codes" class="form-label">Number of Recovery Codes</label>
                        <input type="number" class="form-control" id="two_factor_recovery_codes" name="settings[two_factor_recovery_codes]" value="<?php echo htmlspecialchars(get_setting('two_factor_recovery_codes', '8')); ?>" min="4" max="16">
                        <div class="form-text">Number of recovery codes generated for each user.</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <h6 class="border-bottom pb-2 mb-3">User Registration</h6>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="enable_registration" name="settings[enable_registration]" value="1" <?php echo get_setting('enable_registration', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enable_registration">Enable User Registration</label>
                        <div class="form-text">Allow new users to register on the login page.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="approval_required" name="settings[approval_required]" value="1" <?php echo get_setting('approval_required', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="approval_required">Require Admin Approval</label>
                        <div class="form-text">New user accounts require administrator approval before they can log in.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="default_role" class="form-label">Default Role for New Users</label>
                        <select class="form-control" id="default_role" name="settings[default_role]">
                            <option value="">-- No Role --</option>
                            <?php 
                            $stmt = $db->query("SELECT id, name FROM roles ORDER BY name");
                            $roles = $stmt->fetchAll();
                            foreach ($roles as $role): 
                            ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo get_setting('default_role', '') == $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Role automatically assigned to new users upon registration.</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <h6 class="border-bottom pb-2 mb-3">Password Policy</h6>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                        <input type="number" class="form-control" id="password_min_length" name="settings[password_min_length]" value="<?php echo htmlspecialchars(get_setting('password_min_length', '8')); ?>" min="6" max="32">
                        <div class="form-text">Minimum number of characters required for passwords.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="password_require_special" name="settings[password_require_special]" value="1" <?php echo get_setting('password_require_special', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="password_require_special">Require Special Characters</label>
                        <div class="form-text">Passwords must contain at least one special character.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="password_require_number" name="settings[password_require_number]" value="1" <?php echo get_setting('password_require_number', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="password_require_number">Require Numbers</label>
                        <div class="form-text">Passwords must contain at least one number.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="password_require_uppercase" name="settings[password_require_uppercase]" value="1" <?php echo get_setting('password_require_uppercase', '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="password_require_uppercase">Require Uppercase Letters</label>
                        <div class="form-text">Passwords must contain at least one uppercase letter.</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <h6 class="border-bottom pb-2 mb-3">Session Settings</h6>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="session_lifetime" class="form-label">Session Lifetime (Minutes)</label>
                        <input type="number" class="form-control" id="session_lifetime" name="settings[session_lifetime]" value="<?php echo htmlspecialchars(get_setting('session_lifetime', '120')); ?>" min="10" max="1440">
                        <div class="form-text">How long a user session remains active without activity.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="remember_me_days" class="form-label">Remember Me Duration (Days)</label>
                        <input type="number" class="form-control" id="remember_me_days" name="settings[remember_me_days]" value="<?php echo htmlspecialchars(get_setting('remember_me_days', '30')); ?>" min="1" max="365">
                        <div class="form-text">How long to keep users logged in when "Remember Me" is checked.</div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3 form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="session_regenerate_id" name="settings[session_regenerate_id]" value="1" <?php echo get_setting('session_regenerate_id', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="session_regenerate_id">Regenerate Session ID</label>
                        <div class="form-text">Regenerate session ID on login to prevent session fixation attacks.</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle dependent settings
    const twoFactorEnabled = document.getElementById('two_factor_enabled');
    const twoFactorEnforce = document.getElementById('two_factor_enforce');
    const twoFactorIssuer = document.getElementById('two_factor_issuer');
    const twoFactorRecoveryCodes = document.getElementById('two_factor_recovery_codes');
    
    function toggleTwoFactorSettings() {
        const isEnabled = twoFactorEnabled.checked;
        twoFactorEnforce.disabled = !isEnabled;
        twoFactorIssuer.disabled = !isEnabled;
        twoFactorRecoveryCodes.disabled = !isEnabled;
        
        if (!isEnabled) {
            twoFactorEnforce.checked = false;
        }
    }
    
    twoFactorEnabled.addEventListener('change', toggleTwoFactorSettings);
    toggleTwoFactorSettings();
    
    // Toggle registration settings
    const enableRegistration = document.getElementById('enable_registration');
    const approvalRequired = document.getElementById('approval_required');
    const defaultRole = document.getElementById('default_role');
    
    function toggleRegistrationSettings() {
        const isEnabled = enableRegistration.checked;
        approvalRequired.disabled = !isEnabled;
        defaultRole.disabled = !isEnabled;
    }
    
    enableRegistration.addEventListener('change', toggleRegistrationSettings);
    toggleRegistrationSettings();
});
</script>
