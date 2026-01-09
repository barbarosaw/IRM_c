<?php
/**
 * PH Communications Module - Settings Page
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('ph_communications-settings')) {
    header('Location: ../../access-denied.php');
    exit;
}

require_once 'models/PHSettings.php';
$settingsModel = new PHSettings($db);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // m360 SMS Settings
    $appKey = trim($_POST['app_key'] ?? '');
    $appSecret = trim($_POST['app_secret'] ?? '');
    $shortcode = trim($_POST['shortcode'] ?? '');

    if ($appKey && $appSecret) {
        $settingsModel->updateM360Credentials($appKey, $appSecret, $shortcode);
        $success_message = 'm360 credentials updated successfully';
    } else {
        $error_message = 'App Key and App Secret are required';
    }
}

// Get current settings
$credentials = $settingsModel->getM360Credentials();

$page_title = "PH Communications Settings";
$root_path = "../../";

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<link rel="stylesheet" href="assets/css/ph-communications.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-cog me-2"></i>PH Communications Settings
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">PH Communications</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <strong>Success:</strong> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
            <?php endif; ?>

            <!-- m360 SMS Settings -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sms me-2"></i>m360 SMS API Settings
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="app_key" class="form-label">App Key <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="app_key" name="app_key"
                                           value="<?php echo htmlspecialchars($credentials['app_key'] ?? ''); ?>" required>
                                    <small class="form-text text-muted">Your m360 application key</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="app_secret" class="form-label">App Secret <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="app_secret" name="app_secret"
                                           value="<?php echo htmlspecialchars($credentials['app_secret'] ?? ''); ?>" required>
                                    <small class="form-text text-muted">Your m360 application secret</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="shortcode" class="form-label">Shortcode / Sender ID</label>
                                    <input type="text" class="form-control" id="shortcode" name="shortcode"
                                           value="<?php echo htmlspecialchars($credentials['shortcode'] ?? ''); ?>"
                                           maxlength="11">
                                    <small class="form-text text-muted">Registered sender ID (must be approved by m360)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="api_url" class="form-label">API URL</label>
                                    <input type="text" class="form-control" id="api_url" name="api_url"
                                           value="<?php echo htmlspecialchars($credentials['api_url'] ?? 'https://api.m360.com.ph/v3/api/broadcast'); ?>"
                                           readonly>
                                    <small class="form-text text-muted">m360 API endpoint (read-only)</small>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Webhook URLs -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-link me-2"></i>Webhook URLs
                    </h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Configure these webhook URLs in your m360 dashboard to receive delivery reports and inbound messages.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">DLR Webhook (Delivery Reports)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly
                                   value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/modules/ph_phone_calls/api/m360-sms/dlr.php"
                                   id="dlrWebhook">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('dlrWebhook')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">MO Webhook (Inbound SMS)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly
                                   value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/modules/ph_phone_calls/api/m360-sms/receive.php"
                                   id="moWebhook">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('moWebhook')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Setup Instructions:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Login to <a href="https://dashboard.m360.com.ph" target="_blank">m360 Dashboard</a></li>
                            <li>Go to API → Webhook settings</li>
                            <li>Paste the above URLs for DLR and MO webhooks</li>
                            <li>Save the configuration</li>
                        </ol>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const input = document.getElementById(elementId);
    input.select();
    document.execCommand('copy');

    // Show feedback
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-success');

    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-secondary');
    }, 2000);
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });
});
</script>

<?php include '../../components/footer.php'; ?>
