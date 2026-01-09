<?php
/**
 * Phone Calls Module - Settings Page
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('phone_calls-settings')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Load settings model
require_once 'models/PhoneCallSettings.php';
$settings = new PhoneCallSettings($db);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $newSettings = [
        'twilio_account_sid' => trim($_POST['twilio_account_sid'] ?? ''),
        'twilio_auth_token' => trim($_POST['twilio_auth_token'] ?? ''),
        'twilio_api_key_sid' => trim($_POST['twilio_api_key_sid'] ?? ''),
        'twilio_api_key_secret' => trim($_POST['twilio_api_key_secret'] ?? ''),
        'twilio_phone_number' => trim($_POST['twilio_phone_number'] ?? ''),
        'twilio_twiml_app_sid' => trim($_POST['twilio_twiml_app_sid'] ?? ''),
        'call_recording_enabled' => isset($_POST['call_recording_enabled']) ? '1' : '0',
        'max_call_duration_minutes' => (int) ($_POST['max_call_duration_minutes'] ?? 60)
    ];

    if ($settings->updateMultiple($newSettings)) {
        $message = 'Settings saved successfully.';
        $messageType = 'success';
        // Reload settings
        $settings = new PhoneCallSettings($db);
    } else {
        $message = 'Failed to save settings.';
        $messageType = 'danger';
    }
}

// Test connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    if (!$settings->isConfigured()) {
        $message = 'Please configure all Twilio settings first.';
        $messageType = 'warning';
    } else {
        // Test Twilio API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.twilio.com/2010-04-01/Accounts/' . $settings->getAccountSid() . '.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $settings->getAccountSid() . ':' . $settings->getAuthToken());
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $message = 'Connection successful! Account: ' . ($data['friendly_name'] ?? 'Unknown') . ' | Status: ' . ($data['status'] ?? 'Unknown');
            $messageType = 'success';
        } else {
            $message = 'Connection failed. Please check your credentials. HTTP Code: ' . $httpCode;
            $messageType = 'danger';
        }
    }
}

$page_title = "Phone Calls Settings";
$root_path = "../../";

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-cog me-2"></i>Phone Calls Settings
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Phone Calls</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Twilio Credentials -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-key me-2"></i>Twilio Credentials
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twilio_account_sid" class="form-label">Account SID</label>
                                            <input type="text" class="form-control" id="twilio_account_sid"
                                                   name="twilio_account_sid"
                                                   value="<?php echo htmlspecialchars($settings->getAccountSid()); ?>"
                                                   placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                            <small class="text-muted">Found in Twilio Console dashboard</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twilio_auth_token" class="form-label">Auth Token</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="twilio_auth_token"
                                                       name="twilio_auth_token"
                                                       value="<?php echo htmlspecialchars($settings->getAuthToken()); ?>"
                                                       placeholder="Your auth token">
                                                <button class="btn btn-outline-secondary" type="button"
                                                        onclick="togglePassword('twilio_auth_token')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twilio_api_key_sid" class="form-label">API Key SID</label>
                                            <input type="text" class="form-control" id="twilio_api_key_sid"
                                                   name="twilio_api_key_sid"
                                                   value="<?php echo htmlspecialchars($settings->getApiKeySid()); ?>"
                                                   placeholder="SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                            <small class="text-muted">For generating access tokens</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twilio_api_key_secret" class="form-label">API Key Secret</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="twilio_api_key_secret"
                                                       name="twilio_api_key_secret"
                                                       value="<?php echo htmlspecialchars($settings->getApiKeySecret()); ?>"
                                                       placeholder="Your API key secret">
                                                <button class="btn btn-outline-secondary" type="button"
                                                        onclick="togglePassword('twilio_api_key_secret')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twilio_phone_number" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" id="twilio_phone_number"
                                                   name="twilio_phone_number"
                                                   value="<?php echo htmlspecialchars($settings->getPhoneNumber()); ?>"
                                                   placeholder="+1XXXXXXXXXX">
                                            <small class="text-muted">Your Twilio phone number (E.164 format)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twilio_twiml_app_sid" class="form-label">TwiML App SID</label>
                                            <input type="text" class="form-control" id="twilio_twiml_app_sid"
                                                   name="twilio_twiml_app_sid"
                                                   value="<?php echo htmlspecialchars($settings->getTwimlAppSid()); ?>"
                                                   placeholder="APxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                            <small class="text-muted">TwiML Application for Voice SDK</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Call Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-phone-alt me-2"></i>Call Settings
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                       id="call_recording_enabled" name="call_recording_enabled"
                                                       <?php echo $settings->isRecordingEnabled() ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="call_recording_enabled">
                                                    Enable Call Recording
                                                </label>
                                            </div>
                                            <small class="text-muted">Record all outbound calls</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_call_duration_minutes" class="form-label">
                                                Max Call Duration (minutes)
                                            </label>
                                            <input type="number" class="form-control" id="max_call_duration_minutes"
                                                   name="max_call_duration_minutes"
                                                   value="<?php echo $settings->getMaxCallDuration(); ?>"
                                                   min="1" max="240">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Status Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-info-circle me-2"></i>Status
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if ($settings->isConfigured()): ?>
                                    <div class="alert alert-success mb-3">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Twilio is configured
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Configuration incomplete
                                    </div>
                                <?php endif; ?>

                                <button type="submit" name="test_connection" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-plug me-2"></i>Test Connection
                                </button>

                                <button type="submit" name="save_settings" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                            </div>
                        </div>

                        <!-- Webhook URLs -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-link me-2"></i>Webhook URLs
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Configure these URLs in your Twilio TwiML App:</p>

                                <div class="mb-3">
                                    <label class="form-label small">Voice Request URL</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" readonly
                                               value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/modules/phone_calls/api/voice.php'; ?>"
                                               id="voice_url">
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="copyToClipboard('voice_url')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small">Status Callback URL</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" readonly
                                               value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/modules/phone_calls/api/status-callback.php'; ?>"
                                               id="status_url">
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="copyToClipboard('status_url')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function copyToClipboard(inputId) {
    const input = document.getElementById(inputId);
    input.select();
    document.execCommand('copy');

    // Show feedback
    const btn = input.nextElementSibling;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(() => btn.innerHTML = originalHtml, 1500);
}
</script>

<?php include '../../components/footer.php'; ?>
