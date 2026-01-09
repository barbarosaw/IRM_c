<?php
/**
 * N8N Management Module - Widget Settings
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['n8n_management']);
$is_active = $stmt->fetchColumn();
if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $db->prepare("UPDATE n8n_chatbot_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        $success_message = 'Settings saved successfully!';
    } catch (Exception $e) {
        $error_message = 'Error saving settings: ' . $e->getMessage();
    }
}

// Handle API key regeneration
if (isset($_POST['regenerate_api_key'])) {
    try {
        $newKey = bin2hex(random_bytes(32));
        $stmt = $db->prepare("UPDATE settings SET `value` = ?, updated_at = NOW() WHERE `key` = 'n8n_chat_api_key'");
        $stmt->execute([$newKey]);
        $success_message = 'API Key regenerated successfully!';
    } catch (Exception $e) {
        $error_message = 'Error regenerating API key: ' . $e->getMessage();
    }
}

// Get current settings
$stmt = $db->query("SELECT setting_key, setting_value, setting_type, description FROM n8n_chatbot_settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row;
}

// Get API key
$stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = 'n8n_chat_api_key'");
$stmt->execute();
$apiKey = $stmt->fetchColumn();

$page_title = "Widget Settings";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

define('AW_SYSTEM', true);
include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-cog me-2"></i>Widget & Email Settings
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Widget Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-times-circle me-2"></i><?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <form method="POST">
                        <!-- Widget Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-comment-dots me-2"></i>Widget Configuration</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Widget Enabled</label>
                                            <select name="settings[widget_enabled]" class="form-select">
                                                <option value="true" <?= ($settings['widget_enabled']['setting_value'] ?? '') === 'true' ? 'selected' : '' ?>>Enabled</option>
                                                <option value="false" <?= ($settings['widget_enabled']['setting_value'] ?? '') === 'false' ? 'selected' : '' ?>>Disabled</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Widget Position</label>
                                            <select name="settings[widget_position]" class="form-select">
                                                <option value="bottom-right" <?= ($settings['widget_position']['setting_value'] ?? '') === 'bottom-right' ? 'selected' : '' ?>>Bottom Right</option>
                                                <option value="bottom-left" <?= ($settings['widget_position']['setting_value'] ?? '') === 'bottom-left' ? 'selected' : '' ?>>Bottom Left</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Widget Title</label>
                                            <input type="text" name="settings[widget_title]" class="form-control" value="<?= htmlspecialchars($settings['widget_title']['setting_value'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Widget Subtitle</label>
                                            <input type="text" name="settings[widget_subtitle]" class="form-control" value="<?= htmlspecialchars($settings['widget_subtitle']['setting_value'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Primary Color</label>
                                            <input type="color" name="settings[widget_primary_color]" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['widget_primary_color']['setting_value'] ?? '#e74266') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Trigger Time (ms)</label>
                                            <input type="number" name="settings[trigger_time]" class="form-control" value="<?= htmlspecialchars($settings['trigger_time']['setting_value'] ?? '30000') ?>">
                                            <small class="text-muted">Auto-open after X milliseconds (0 = disabled)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Trigger Scroll (%)</label>
                                            <input type="number" name="settings[trigger_scroll]" class="form-control" value="<?= htmlspecialchars($settings['trigger_scroll']['setting_value'] ?? '50') ?>" min="0" max="100">
                                            <small class="text-muted">Auto-open after X% scroll (0 = disabled)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Webhook URL</label>
                                    <input type="url" name="settings[webhook_url]" class="form-control" value="<?= htmlspecialchars($settings['webhook_url']['setting_value'] ?? '') ?>" placeholder="https://n8n.abroadworks.com/webhook/chatbot">
                                    <small class="text-muted">n8n webhook URL for the chatbot workflow</small>
                                </div>
                            </div>
                        </div>

                        <!-- Email Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-envelope me-2"></i>Email Notification Settings</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="hidden" name="settings[email_on_session_end]" value="false">
                                        <input type="checkbox" class="form-check-input" id="email_on_session_end" name="settings[email_on_session_end]" value="true" <?= ($settings['email_on_session_end']['setting_value'] ?? '') === 'true' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_on_session_end">
                                            Send email notification when chat session ends
                                        </label>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">TO Email <span class="text-danger">*</span></label>
                                            <input type="email" name="settings[email_to]" class="form-control" value="<?= htmlspecialchars($settings['email_to']['setting_value'] ?? '') ?>" placeholder="sales@abroadworks.com">
                                            <small class="text-muted">Primary recipient for chat notifications</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">CC Emails</label>
                                            <input type="text" name="settings[email_cc]" class="form-control" value="<?= htmlspecialchars($settings['email_cc']['setting_value'] ?? '') ?>" placeholder="marketing@abroadworks.com, support@abroadworks.com">
                                            <small class="text-muted">Comma separated. Recipients will see each other.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">BCC Emails</label>
                                            <input type="text" name="settings[email_bcc]" class="form-control" value="<?= htmlspecialchars($settings['email_bcc']['setting_value'] ?? '') ?>" placeholder="archive@abroadworks.com">
                                            <small class="text-muted">Comma separated. Hidden from other recipients.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                    </form>
                </div>

                <div class="col-lg-4">
                    <!-- API Key -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-key me-2"></i>Chat API Key</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">This API key is used by n8n to authenticate when saving chat data to IRM.</p>

                            <div class="mb-3">
                                <label class="form-label">Current API Key</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="apiKeyField" value="<?= htmlspecialchars($apiKey) ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey()">
                                        <i class="fas fa-eye" id="apiKeyIcon"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyApiKey()">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>

                            <form method="POST" onsubmit="return confirm('Are you sure? This will invalidate the current API key and n8n will need to be updated.')">
                                <button type="submit" name="regenerate_api_key" class="btn btn-warning w-100">
                                    <i class="fas fa-sync me-1"></i> Regenerate API Key
                                </button>
                            </form>

                            <hr>

                            <h6>n8n Configuration</h6>
                            <p class="small text-muted">Add this header to your HTTP Request nodes in n8n:</p>
                            <code class="d-block bg-light p-2 rounded small">
                                X-Chat-API-Key: [your-api-key]
                            </code>
                        </div>
                    </div>

                    <!-- Widget Embed Code -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-code me-2"></i>Embed Code</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Add this code to your website before the closing &lt;/body&gt; tag:</p>

                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="embedCode" value='<script src="https://irm.abroadworks.com/modules/n8n_management/widget/abroadworks-chat.js"></script>' readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyEmbedCode()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>

                            <a href="test-widget.php" class="btn btn-info w-100" target="_blank">
                                <i class="fas fa-eye me-2"></i>Preview & Test Widget
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-link me-2"></i>Quick Links</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                            <a href="conversations.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-comments me-2"></i> Conversations
                            </a>
                            <a href="emails.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-envelope me-2"></i> Email Tracking
                            </a>
                            <a href="<?= $root_path ?>modules/settings/?tab=n8n-settings" class="list-group-item list-group-item-action">
                                <i class="fas fa-plug me-2"></i> n8n Connection Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleApiKey() {
    const field = document.getElementById('apiKeyField');
    const icon = document.getElementById('apiKeyIcon');
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

function copyApiKey() {
    const field = document.getElementById('apiKeyField');
    navigator.clipboard.writeText(field.value);
    Swal.fire({
        icon: 'success',
        title: 'Copied!',
        text: 'API Key copied to clipboard',
        timer: 1500,
        showConfirmButton: false
    });
}

function copyEmbedCode() {
    const field = document.getElementById('embedCode');
    navigator.clipboard.writeText(field.value);
    Swal.fire({
        icon: 'success',
        title: 'Copied!',
        text: 'Embed code copied to clipboard',
        timer: 1500,
        showConfirmButton: false
    });
}
</script>

<?php include '../../components/footer.php'; ?>
