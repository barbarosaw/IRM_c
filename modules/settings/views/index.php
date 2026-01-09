<?php
/**
 * AbroadWorks Management System - Settings View
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary"><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>index.php">Home</a></li>
                        <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Maintenance Mode Card -->
            <div class="card mb-4">
                <div class="card-header bg-<?php echo $settings['maintenance_mode'] == '1' ? 'warning' : 'success'; ?>">
                    <h3 class="card-title">
                        <i class="fas fa-tools me-2"></i>
                        Maintenance Mode
                    </h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4>
                                <?php if ($settings['maintenance_mode'] == '1'): ?>
                                    <span class="badge bg-warning text-dark">Maintenance Mode is ON</span>
                                <?php else: ?>
                                    <span class="badge bg-success">System is LIVE</span>
                                <?php endif; ?>
                            </h4>
                            <p class="mb-0">
                                <?php if ($settings['maintenance_mode'] == '1'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    Only administrators can access the system when maintenance mode is enabled.
                                <?php else: ?>
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    The system is operating normally and accessible to all users.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <form method="post">
                                <button type="submit" name="toggle_maintenance" class="btn btn-<?php echo $settings['maintenance_mode'] == '1' ? 'success' : 'warning'; ?> btn-lg">
                                    <?php if ($settings['maintenance_mode'] == '1'): ?>
                                        <i class="fas fa-play-circle me-2"></i> Go Live
                                    <?php else: ?>
                                        <i class="fas fa-pause-circle me-2"></i> Enable Maintenance Mode
                                    <?php endif; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post">
                <div class="card">
                    <div class="card-header p-2">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#general-settings" role="tab">General</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#company-settings" role="tab">Company</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#system-settings" role="tab">System</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#security-settings" role="tab">Security</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#email-settings" role="tab">
                                    <i class="fas fa-envelope"></i> Email
                                </a>
                            </li>
                            <?php if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']): ?>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#debug-settings" role="tab">Debug</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#backup-restore" role="tab">Backup & Restore</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#n8n-settings" role="tab">
                                    <i class="fas fa-robot"></i> n8n / Chatbot
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- General Settings -->
                            <div class="tab-pane active" id="general-settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="settings[site_name]" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                            <small class="form-text text-muted">The name that appears in the browser title and header.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_description" class="form-label">Site Description</label>
                                            <input type="text" class="form-control" id="site_description" name="settings[site_description]" value="<?php echo htmlspecialchars($settings['site_description']); ?>">
                                            <small class="form-text text-muted">A brief description of the system.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_format" class="form-label">Date Format</label>
                                            <select class="form-control" id="date_format" name="settings[date_format]">
                                                <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (<?php echo date('Y-m-d'); ?>)</option>
                                                <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (<?php echo date('m/d/Y'); ?>)</option>
                                                <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (<?php echo date('d/m/Y'); ?>)</option>
                                                <option value="d.m.Y" <?php echo $settings['date_format'] == 'd.m.Y' ? 'selected' : ''; ?>>DD.MM.YYYY (<?php echo date('d.m.Y'); ?>)</option>
                                                <option value="F j, Y" <?php echo $settings['date_format'] == 'F j, Y' ? 'selected' : ''; ?>>Month Day, Year (<?php echo date('F j, Y'); ?>)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="time_format" class="form-label">Time Format</label>
                                            <select class="form-control" id="time_format" name="settings[time_format]">
                                                <option value="H:i" <?php echo $settings['time_format'] == 'H:i' ? 'selected' : ''; ?>>24-hour (<?php echo date('H:i'); ?>)</option>
                                                <option value="h:i A" <?php echo $settings['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12-hour (<?php echo date('h:i A'); ?>)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-control select2" id="timezone" name="settings[timezone]">
                                                <?php
                                                $timezones = DateTimeZone::listIdentifiers();
                                                foreach ($timezones as $tz) {
                                                    $selected = $settings['timezone'] == $tz ? 'selected' : '';
                                                    echo '<option value="' . $tz . '" ' . $selected . '>' . $tz . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="system_theme" class="form-label">System Theme</label>
                                            <select class="form-control" id="system_theme" name="settings[system_theme]">
                                                <option value="default" <?php echo $settings['system_theme'] == 'default' ? 'selected' : ''; ?>>Default</option>
                                                <option value="dark" <?php echo $settings['system_theme'] == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                                <option value="light" <?php echo $settings['system_theme'] == 'light' ? 'selected' : ''; ?>>Light</option>
                                            </select>
                                            <small class="form-text text-muted">This feature will be available in future updates.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="maintenance_message" class="form-label">Maintenance Message</label>
                                            <textarea class="form-control" id="maintenance_message" name="settings[maintenance_message]" rows="3"><?php echo htmlspecialchars($settings['maintenance_message']); ?></textarea>
                                            <small class="form-text text-muted">This message will be displayed when maintenance mode is active.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Company Settings -->
                            <div class="tab-pane" id="company-settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="company_name" name="settings[company_name]" value="<?php echo htmlspecialchars($settings['company_name']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_email" class="form-label">Company Email</label>
                                            <input type="email" class="form-control" id="company_email" name="settings[company_email]" value="<?php echo htmlspecialchars($settings['company_email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_phone" class="form-label">Company Phone</label>
                                            <input type="text" class="form-control" id="company_phone" name="settings[company_phone]" value="<?php echo htmlspecialchars($settings['company_phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_address" class="form-label">Company Address</label>
                                            <textarea class="form-control" id="company_address" name="settings[company_address]" rows="3"><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Settings -->
                            <div class="tab-pane" id="system-settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="pagination_limit" class="form-label">Items Per Page</label>
                                            <input type="number" class="form-control" id="pagination_limit" name="settings[pagination_limit]" value="<?php echo htmlspecialchars($settings['pagination_limit']); ?>" min="10" max="100">
                                            <small class="form-text text-muted">Number of items to display per page in tables.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="log_retention_days" class="form-label">Log Retention Period (Days)</label>
                                            <input type="number" class="form-control" id="log_retention_days" name="settings[log_retention_days]" value="<?php echo htmlspecialchars($settings['log_retention_days']); ?>" min="1">
                                            <small class="form-text text-muted">Number of days to keep activity logs.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5><i class="fas fa-code"></i> APIs</h5>
                                <div class="row">
                                    <?php
                                    // Get API settings (group = 'api')
                                    $api_settings = [];
                                    foreach ($settings as $key => $value) {
                                        if (isset($settings_groups[$key]) && $settings_groups[$key] === 'api') {
                                            $api_settings[$key] = $value;
                                        }
                                    }
                                    
                                    if (empty($api_settings)): ?>
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i> No API settings found. API settings will appear here automatically when modules create them.
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($api_settings as $key => $value): ?>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="<?php echo $key; ?>" class="form-label">
                                                        <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="<?php echo $key; ?>" name="settings[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($value); ?>">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('<?php echo $key; ?>')">
                                                            <i class="fas fa-eye" id="<?php echo $key; ?>_icon"></i>
                                                        </button>
                                                    </div>
                                                    <small class="form-text text-muted">API key for <?php echo str_replace('_', ' ', $key); ?> integration.</small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Security Settings -->
                            <div class="tab-pane" id="security-settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="session_lifetime" class="form-label">Session Lifetime (Minutes)</label>
                                            <input type="number" class="form-control" id="session_lifetime" name="settings[session_lifetime]" value="<?php echo htmlspecialchars($settings['session_lifetime']); ?>" min="10">
                                            <small class="form-text text-muted">How long a user session remains active without activity.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 form-check mt-4">
                                            <input type="checkbox" class="form-check-input" id="enable_registration" name="settings[enable_registration]" <?php echo $settings['enable_registration'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_registration">Enable User Registration</label>
                                            <small class="form-text text-muted d-block">Allow new users to register on the login page.</small>
                                        </div>
                                        
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="approval_required" name="settings[approval_required]" <?php echo $settings['approval_required'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="approval_required">Require Admin Approval for New Users</label>
                                            <small class="form-text text-muted d-block">If enabled, newly registered users must be approved by an admin.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5>Two-Factor Authentication (2FA)</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="two_factor_enabled" name="settings[two_factor_enabled]" value="1" <?php echo isset($settings['two_factor_enabled']) && $settings['two_factor_enabled'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="two_factor_enabled">Enable Two-Factor Authentication</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="two_factor_enforce" name="settings[two_factor_enforce]" value="1" <?php echo isset($settings['two_factor_enforce']) && $settings['two_factor_enforce'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="two_factor_enforce">Enforce Two-Factor Authentication for All Users</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="two_factor_issuer" class="form-label">2FA Issuer Name</label>
                                            <input type="text" class="form-control" id="two_factor_issuer" name="settings[two_factor_issuer]" value="<?php echo htmlspecialchars($settings['two_factor_issuer'] ?? 'AbroadWorks Management'); ?>">
                                            <small class="form-text text-muted">The name that appears in authenticator apps.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="default_role" class="form-label">Default Role for New Users</label>
                                            <select class="form-control" id="default_role" name="settings[default_role]">
                                                <option value="">-- No Role --</option>
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?php echo $role['id']; ?>" <?php echo $settings['default_role'] == $role['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($role['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Role automatically assigned to new users.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Settings -->
                            <div class="tab-pane" id="email-settings">
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Gmail SMTP Setup:</strong> To use Gmail, you need to generate an App Password. Go to
                                    <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a>,
                                    select "Mail" and generate a password. Use that password (not your regular Gmail password).
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="email_enabled" name="settings[email_enabled]" value="1" <?php echo isset($settings['email_enabled']) && $settings['email_enabled'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_enabled"><strong>Enable Email Sending</strong></label>
                                            <small class="form-text text-muted d-block">Turn on/off system email capabilities.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5><i class="fas fa-server"></i> SMTP Server Configuration</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" id="smtp_host" name="settings[smtp_host]" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>" placeholder="smtp.gmail.com">
                                            <small class="form-text text-muted">Gmail: smtp.gmail.com</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port</label>
                                            <select class="form-control" id="smtp_port" name="settings[smtp_port]">
                                                <option value="587" <?php echo ($settings['smtp_port'] ?? '587') == '587' ? 'selected' : ''; ?>>587 (TLS - Recommended)</option>
                                                <option value="465" <?php echo ($settings['smtp_port'] ?? '') == '465' ? 'selected' : ''; ?>>465 (SSL)</option>
                                                <option value="25" <?php echo ($settings['smtp_port'] ?? '') == '25' ? 'selected' : ''; ?>>25 (Non-secure)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="smtp_encryption" class="form-label">Encryption</label>
                                            <select class="form-control" id="smtp_encryption" name="settings[smtp_encryption]">
                                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="" <?php echo ($settings['smtp_encryption'] ?? '') == '' ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_username" name="settings[smtp_username]" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="your-email@gmail.com">
                                            <small class="form-text text-muted">Your Gmail address (e.g., barbaros@abroadworks.com)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">SMTP Password (App Password)</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="smtp_password" name="settings[smtp_password]" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="xxxx xxxx xxxx xxxx">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('smtp_password')">
                                                    <i class="fas fa-eye" id="smtp_password_icon"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Use Google App Password, not your regular password</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5><i class="fas fa-at"></i> Sender Information</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="smtp_from_email" class="form-label">From Email</label>
                                            <input type="email" class="form-control" id="smtp_from_email" name="settings[smtp_from_email]" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" placeholder="noreply@abroadworks.com">
                                            <small class="form-text text-muted">Email address that will appear as sender</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="smtp_from_name" class="form-label">From Name</label>
                                            <input type="text" class="form-control" id="smtp_from_name" name="settings[smtp_from_name]" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'AbroadWorks IRM'); ?>">
                                            <small class="form-text text-muted">Name that will appear as sender</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="smtp_reply_to" class="form-label">Reply-To Email</label>
                                            <input type="email" class="form-control" id="smtp_reply_to" name="settings[smtp_reply_to]" value="<?php echo htmlspecialchars($settings['smtp_reply_to'] ?? ''); ?>" placeholder="it@abroadworks.com">
                                            <small class="form-text text-muted">Email where replies will be sent</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_cc" class="form-label">CC Email (Carbon Copy)</label>
                                            <input type="email" class="form-control" id="smtp_cc" name="settings[smtp_cc]" value="<?php echo htmlspecialchars($settings['smtp_cc'] ?? ''); ?>" placeholder="it@abroadworks.com">
                                            <small class="form-text text-muted">All system emails will be CC'd to this address. Leave empty to disable.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5><i class="fas fa-tachometer-alt"></i> Rate Limiting</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email_rate_limit" class="form-label">Seconds Between Emails</label>
                                            <input type="number" class="form-control" id="email_rate_limit" name="settings[email_rate_limit]" value="<?php echo htmlspecialchars($settings['email_rate_limit'] ?? '5'); ?>" min="1" max="60">
                                            <small class="form-text text-muted">Wait time between sending each email (for bulk operations). Gmail allows ~20 emails per minute.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5><i class="fas fa-flask"></i> Test Email</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="test_email_address" class="form-label">Test Email Address</label>
                                            <input type="email" class="form-control" id="test_email_address" value="" placeholder="Enter email to receive test">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" id="btnSendTestEmail" class="btn btn-success btn-block d-block w-100">
                                                <i class="fas fa-paper-plane"></i> Send Test Email
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="testEmailResult" style="display:none;"></div>
                            </div>

                            <?php if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']): ?>
                            <!-- Debug Settings -->
                            <div class="tab-pane" id="debug-settings">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> These settings are for debugging and development purposes only. Changing them may affect system performance and security.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="display_errors" name="settings[display_errors]" <?php echo isset($settings['display_errors']) && $settings['display_errors'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="display_errors">Display PHP Errors</label>
                                            <small class="form-text text-muted d-block">Shows PHP errors in the browser. Should be disabled in production.</small>
                                        </div>
                                        
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="log_errors" name="settings[log_errors]" <?php echo isset($settings['log_errors']) && $settings['log_errors'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="log_errors">Log PHP Errors</label>
                                            <small class="form-text text-muted d-block">Record PHP errors to log files.</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="error_reporting" class="form-label">Error Reporting Level</label>
                                            <select class="form-control" id="error_reporting" name="settings[error_reporting]">
                                                <option value="E_ALL" <?php echo isset($settings['error_reporting']) && $settings['error_reporting'] == 'E_ALL' ? 'selected' : ''; ?>>E_ALL (All errors)</option>
                                                <option value="E_ALL & ~E_NOTICE" <?php echo isset($settings['error_reporting']) && $settings['error_reporting'] == 'E_ALL & ~E_NOTICE' ? 'selected' : ''; ?>>E_ALL & ~E_NOTICE (All except notices)</option>
                                                <option value="E_ERROR | E_WARNING | E_PARSE" <?php echo isset($settings['error_reporting']) && $settings['error_reporting'] == 'E_ERROR | E_WARNING | E_PARSE' ? 'selected' : ''; ?>>Only errors, warnings and parse errors</option>
                                            </select>
                                            <small class="form-text text-muted">Controls which types of errors are reported.</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_execution_time" class="form-label">Max Execution Time (seconds)</label>
                                            <input type="number" class="form-control" id="max_execution_time" name="settings[max_execution_time]" value="<?php echo htmlspecialchars($settings['max_execution_time'] ?? '60'); ?>" min="10">
                                            <small class="form-text text-muted">Maximum time a script is allowed to run.</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="memory_limit" class="form-label">Memory Limit</label>
                                            <input type="text" class="form-control" id="memory_limit" name="settings[memory_limit]" value="<?php echo htmlspecialchars($settings['memory_limit'] ?? '128M'); ?>">
                                            <small class="form-text text-muted">Maximum amount of memory a script may consume (e.g., 128M, 256M).</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="upload_max_filesize" class="form-label">Max Upload File Size</label>
                                            <input type="text" class="form-control" id="upload_max_filesize" name="settings[upload_max_filesize]" value="<?php echo htmlspecialchars($settings['upload_max_filesize'] ?? '10M'); ?>">
                                            <small class="form-text text-muted">Maximum allowed size for uploaded files (e.g., 10M).</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="post_max_size" class="form-label">Max POST Size</label>
                                            <input type="text" class="form-control" id="post_max_size" name="settings[post_max_size]" value="<?php echo htmlspecialchars($settings['post_max_size'] ?? '12M'); ?>">
                                            <small class="form-text text-muted">Maximum size of POST data that PHP will accept (e.g., 12M).</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Backup & Restore Tab -->
                            <div class="tab-pane" id="backup-restore">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4>Create Backup</h4>
                                        <form method="post" class="mb-4">
                                            <div class="mb-3">
                                                <label class="form-label">Tables to Exclude</label>
                                                <div class="card bg-light">
                                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                                        <?php 
                                                        $default_excludes = explode(',', get_setting('backup_exclude_tables', 'sessions,activity_logs'));
                                                        foreach ($all_tables as $table): 
                                                        ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="exclude_tables[]" value="<?php echo $table; ?>" id="table_<?php echo $table; ?>" <?php echo in_array($table, $default_excludes) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="table_<?php echo $table; ?>">
                                                                    <?php echo $table; ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted">These tables will not be included in the backup. Select tables with temporary data or large datasets.</small>
                                            </div>
                                            <button type="submit" name="create_backup" class="btn btn-primary">
                                                <i class="fas fa-download me-1"></i> Create Database Backup
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h4>Available Backups</h4>
                                        
                                        <?php if (empty($backups)): ?>
                                            <div class="alert alert-info">
                                                No backup files found. Please create a backup first.
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Backup File</th>
                                                            <th>Size</th>
                                                            <th>Created</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($backups as $backup): 
                                                            // Use absolute path from backup.php function
                                                            $backup_dir = get_backup_directory_path();
                                                            $file_path = $backup_dir . $backup;
                                                            
                                                            $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                                                            // Better size formatting
                                                            if ($file_size > 1024 * 1024) {
                                                                $file_size_display = round($file_size / (1024 * 1024), 2) . ' MB';
                                                            } elseif ($file_size > 0) {
                                                                $file_size_display = round($file_size / 1024, 2) . ' KB';
                                                            } else {
                                                                $file_size_display = '0 KB';
                                                            }
                                                            $file_time = file_exists($file_path) ? filemtime($file_path) : 0;
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($backup); ?></td>
                                                            <td><?php echo $file_size_display; ?></td>
                                                            <td><?php echo $file_time ? date('Y-m-d H:i:s', $file_time) : 'Unknown'; ?></td>
                                                            <td>
                                                                <form method="post" class="d-inline me-1" onsubmit="return confirm('Are you sure you want to restore the database from this backup? This will overwrite current data.')">
                                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup); ?>">
                                                                    <button type="submit" name="restore_backup" class="btn btn-sm btn-warning">
                                                                        <i class="fas fa-upload"></i> Restore
                                                                    </button>
                                                                </form>
                                                                
                                                                <a href="<?php echo $root_path; ?>download-backup.php?file=<?php echo urlencode($backup); ?>" class="btn btn-sm btn-info me-1">
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                                
                                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this backup file? This action cannot be undone.')">
                                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup); ?>">
                                                                    <button type="submit" name="delete_backup" class="btn btn-sm btn-danger">
                                                                        <i class="fas fa-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Important:</strong> Regular backups help protect your data from loss. It's recommended to download backups and store them in a safe location.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- n8n / Chatbot Settings -->
                            <div class="tab-pane" id="n8n-settings">
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>n8n Integration:</strong> These settings are used for n8n workflow automation platform and chatbot integration.
                                    <a href="<?php echo $root_path; ?>modules/n8n_management/" class="alert-link">Go to n8n Management module →</a>
                                </div>

                                <h5><i class="fas fa-plug"></i> API Connection</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="n8n_host" class="form-label">n8n Host URL</label>
                                            <input type="url" class="form-control" id="n8n_host" name="settings[n8n_host]" value="<?php echo htmlspecialchars($settings['n8n_host'] ?? ''); ?>" placeholder="https://n8n.example.com">
                                            <small class="form-text text-muted">URL of your n8n instance (with https://)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="n8n_api_key" class="form-label">n8n API Key</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="n8n_api_key" name="settings[n8n_api_key]" value="<?php echo htmlspecialchars($settings['n8n_api_key'] ?? ''); ?>" placeholder="eyJhbGciOiJIUzI1NiIs...">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('n8n_api_key')">
                                                    <i class="fas fa-eye" id="n8n_api_key_icon"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">n8n Settings → API → Create API Key</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <button type="button" id="btnTestN8nConnection" class="btn btn-outline-primary">
                                            <i class="fas fa-plug"></i> Test Connection
                                        </button>
                                    </div>
                                </div>
                                <div id="n8nTestResult" class="mt-3" style="display:none;"></div>

                                <hr>
                                <h5><i class="fas fa-comments"></i> Chatbot Widget</h5>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Widget Embed Code</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="widget_embed_code" value='<script src="https://irm.abroadworks.com/modules/n8n_management/widget/abroadworks-chat.js"></script>' readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyWidgetCode()">
                                                    <i class="fas fa-copy"></i> Copy
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Add this code before the &lt;/body&gt; tag on your website</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h5><i class="fas fa-chart-line"></i> Status</h5>
                                <div id="n8nStatusPanel">
                                    <div class="text-center py-3">
                                        <i class="fas fa-spinner fa-spin"></i> Loading...
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize select2 for timezone
    $('.select2').select2();
    
    // Check/uncheck all tables
    $('#toggle_all_tables').on('change', function() {
        $('.table-checkbox').prop('checked', $(this).is(':checked'));
    });
});

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Test Email functionality
$('#btnSendTestEmail').on('click', function() {
    var testEmail = $('#test_email_address').val();

    if (!testEmail) {
        Swal.fire('Error', 'Please enter a test email address', 'error');
        return;
    }

    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    $('#testEmailResult').hide();

    $.ajax({
        url: '<?php echo $root_path; ?>api/send-test-email.php',
        method: 'POST',
        data: {
            to_email: testEmail
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#testEmailResult')
                    .removeClass('alert-danger')
                    .addClass('alert alert-success')
                    .html('<i class="fas fa-check-circle"></i> ' + response.message)
                    .show();
                Swal.fire('Success!', response.message, 'success');
            } else {
                $('#testEmailResult')
                    .removeClass('alert-success')
                    .addClass('alert alert-danger')
                    .html('<i class="fas fa-times-circle"></i> ' + response.message + (response.debug ? '<br><small>' + response.debug + '</small>' : ''))
                    .show();
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            var errorDetail = 'Status: ' + status + ', Error: ' + error;
            if (xhr.responseText) {
                errorDetail += '<br><br><strong>Response:</strong><br><pre style="text-align:left;font-size:11px;max-height:200px;overflow:auto;">' + xhr.responseText.substring(0, 1000) + '</pre>';
            }
            $('#testEmailResult')
                .removeClass('alert-success')
                .addClass('alert alert-danger')
                .html('<i class="fas fa-times-circle"></i> Request failed<br><br>' + errorDetail)
                .show();
            Swal.fire('Error', 'Request failed: ' + error + (xhr.status ? ' (HTTP ' + xhr.status + ')' : ''), 'error');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Test Email');
        }
    });
});

// n8n Connection Test
$('#btnTestN8nConnection').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
    $('#n8nTestResult').hide();

    $.ajax({
        url: '<?php echo $root_path; ?>modules/n8n_management/api/test-connection.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#n8nTestResult')
                    .removeClass('alert-danger')
                    .addClass('alert alert-success')
                    .html('<i class="fas fa-check-circle"></i> ' + response.message +
                          (response.workflows ? '<br><small>Found ' + response.workflows + ' workflows total</small>' : ''))
                    .show();
            } else {
                $('#n8nTestResult')
                    .removeClass('alert-success')
                    .addClass('alert alert-danger')
                    .html('<i class="fas fa-times-circle"></i> ' + response.message)
                    .show();
            }
        },
        error: function(xhr, status, error) {
            $('#n8nTestResult')
                .removeClass('alert-success')
                .addClass('alert alert-danger')
                .html('<i class="fas fa-times-circle"></i> Connection error: ' + error)
                .show();
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
        }
    });
});

// Load n8n status on tab show
$('a[href="#n8n-settings"]').on('shown.bs.tab', function() {
    loadN8nStatus();
});

function loadN8nStatus() {
    $.ajax({
        url: '<?php echo $root_path; ?>modules/n8n_management/api/status.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var html = '<div class="row">' +
                    '<div class="col-md-3"><div class="info-box bg-info"><span class="info-box-icon"><i class="fas fa-project-diagram"></i></span><div class="info-box-content"><span class="info-box-text">Workflows</span><span class="info-box-number">' + response.data.workflows.total + '</span><span class="progress-description">' + response.data.workflows.active + ' active</span></div></div></div>' +
                    '<div class="col-md-3"><div class="info-box bg-success"><span class="info-box-icon"><i class="fas fa-check"></i></span><div class="info-box-content"><span class="info-box-text">Success</span><span class="info-box-number">' + response.data.executions.success + '</span><span class="progress-description">Last 100 executions</span></div></div></div>' +
                    '<div class="col-md-3"><div class="info-box bg-danger"><span class="info-box-icon"><i class="fas fa-times"></i></span><div class="info-box-content"><span class="info-box-text">Errors</span><span class="info-box-number">' + response.data.executions.error + '</span><span class="progress-description">Last 100 executions</span></div></div></div>' +
                    '<div class="col-md-3"><div class="info-box bg-warning"><span class="info-box-icon"><i class="fas fa-comments"></i></span><div class="info-box-content"><span class="info-box-text">Chat Sessions</span><span class="info-box-number">' + (response.data.chat_sessions || 0) + '</span><span class="progress-description">Today</span></div></div></div>' +
                    '</div>';
                $('#n8nStatusPanel').html(html);
            } else {
                $('#n8nStatusPanel').html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function() {
            $('#n8nStatusPanel').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Could not retrieve status</div>');
        }
    });
}

function copyWidgetCode() {
    var copyText = document.getElementById("widget_embed_code");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    Swal.fire({
        icon: 'success',
        title: 'Copied!',
        text: 'Widget code copied to clipboard',
        timer: 1500,
        showConfirmButton: false
    });
}

</script>
