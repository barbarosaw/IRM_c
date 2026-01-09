<?php
/**
 * N8N Management Module - Chat Prompts & Messages Settings
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
        $settings = [
            'chat_greeting_message' => $_POST['greeting_message'] ?? '',
            'chat_system_prompt' => $_POST['system_prompt'] ?? '',
            'chat_fallback_message' => $_POST['fallback_message'] ?? ''
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("UPDATE n8n_chatbot_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $result = $stmt->execute([$value, $key]);
            if ($stmt->rowCount() === 0) {
                // Insert if not exists
                $stmt = $db->prepare("INSERT INTO n8n_chatbot_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, 'string', '')");
                $stmt->execute([$key, $value]);
            }
        }
        $success_message = 'Settings saved successfully!';
    } catch (Exception $e) {
        $error_message = 'Error saving settings: ' . $e->getMessage();
    }
}

// Get current settings
$stmt = $db->query("SELECT setting_key, setting_value FROM n8n_chatbot_settings WHERE setting_key LIKE 'chat_%'");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$page_title = "Chat Prompts & Messages";
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
                        <i class="fas fa-comment-dots me-2"></i>Chat Prompts & Messages
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Chat Prompts</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <!-- Greeting Message -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-hand-wave me-2"></i>Greeting Message</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">This message is shown when the chat widget opens.</p>
                                <textarea name="greeting_message" class="form-control" rows="3" placeholder="Hello! How can I help you today?"><?= htmlspecialchars($settings['chat_greeting_message'] ?? 'Hello! How can I help you today?') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Fallback Message -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Error/Fallback Message</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Shown when an error occurs during chat.</p>
                                <textarea name="fallback_message" class="form-control" rows="3" placeholder="I apologize, but I encountered an issue..."><?= htmlspecialchars($settings['chat_fallback_message'] ?? 'I apologize, but I encountered an issue. Please try refreshing the page or starting a new chat.') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Prompt -->
                <div class="card mt-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-robot me-2"></i>AI System Prompt</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            This prompt defines how the AI assistant behaves. It's sent with every conversation.
                            <strong>Be specific about your company, services, and how the AI should respond.</strong>
                        </p>

                        <div class="alert alert-info">
                            <strong>Tips:</strong>
                            <ul class="mb-0">
                                <li>Define the AI's name and personality</li>
                                <li>List your company's services clearly</li>
                                <li>Specify what information to collect (name, email, phone)</li>
                                <li>Set boundaries (what NOT to do)</li>
                            </ul>
                        </div>

                        <textarea name="system_prompt" class="form-control font-monospace" rows="15" placeholder="You are Ava, a helpful assistant for..."><?= htmlspecialchars($settings['chat_system_prompt'] ?? '') ?></textarea>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadTemplate('default')">
                                <i class="fas fa-file-alt me-1"></i>Load Default Template
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadTemplate('sales')">
                                <i class="fas fa-chart-line me-1"></i>Sales-Focused Template
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadTemplate('support')">
                                <i class="fas fa-headset me-1"></i>Support Template
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body text-end">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>Save All Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const templates = {
    default: `You are Ava, a helpful assistant for AbroadWorks.

AbroadWorks offers:
- Virtual Assistant services ($8-15/hr) - admin support, scheduling, email management
- Graphic Design services - logos, marketing materials
- Staffing Solutions - temporary and permanent placement
- Recruitment Services - end-to-end hiring

YOUR GOALS:
1. Greet users warmly and understand their needs
2. Collect their name, email, and phone (one at a time)
3. Explain relevant services based on their interest
4. Guide them toward booking a consultation

RULES:
- Keep responses SHORT (2-3 sentences max)
- Ask for ONLY ONE piece of information at a time
- NEVER ask for information you already have
- Match the user's language (Turkish gets Turkish response)
- Be friendly but professional`,

    sales: `You are Ava, a sales assistant for AbroadWorks.

Your primary goal is to qualify leads and book consultations.

SERVICES:
- Virtual Assistants: $8-15/hr for admin, scheduling, customer service
- Graphic Design: Logo packages from $299, marketing materials
- Staffing: Temporary and permanent placement solutions
- Recruitment: Full-cycle hiring support

QUALIFICATION PROCESS:
1. Identify their biggest pain point
2. Get their name and email
3. Understand timeline and budget
4. Get phone number for callback
5. Push for booking a discovery call

OBJECTION HANDLING:
- "Too expensive" -> Emphasize ROI and time saved
- "Need to think" -> Offer limited-time consultation
- "Just browsing" -> Share success stories

Always create urgency while being helpful.`,

    support: `You are Ava, a support assistant for AbroadWorks.

Your goal is to help existing clients and answer questions.

COMMON QUESTIONS:
- Hours of operation: 9 AM - 6 PM EST, Monday-Friday
- Support email: support@abroadworks.com
- Billing questions: billing@abroadworks.com

FOR NEW INQUIRIES:
- Collect name and email
- Understand their question
- Route to appropriate team

ESCALATION:
If the user is frustrated or you can't help, offer to connect them with a human representative.

Always be empathetic and solution-focused.`
};

function loadTemplate(name) {
    if (confirm('This will replace the current prompt. Continue?')) {
        document.querySelector('textarea[name="system_prompt"]').value = templates[name];
    }
}
</script>

<?php include '../../components/footer.php'; ?>
