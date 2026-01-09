<?php
/**
 * N8N Management Module - Conversation Detail
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

require_once __DIR__ . '/models/ChatSession.php';
$sessionModel = new ChatSession($db);

$sessionId = $_GET['id'] ?? null;
if (!$sessionId) {
    header('Location: conversations.php');
    exit;
}

$session = $sessionModel->getById($sessionId);
if (!$session) {
    header('Location: conversations.php');
    exit;
}

// Get lead/customer info
$stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
$stmt->execute([$sessionId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

// Get brain state for additional collected data
$stmt = $db->prepare("SELECT collected_data, current_goal, goals_completed, engagement_level FROM conversation_brain WHERE session_id = ?");
$stmt->execute([$sessionId]);
$brain = $stmt->fetch(PDO::FETCH_ASSOC);

$collectedData = [];
if ($brain && !empty($brain['collected_data'])) {
    $collectedData = json_decode($brain['collected_data'], true) ?: [];
}
$goalsCompleted = [];
if ($brain && !empty($brain['goals_completed'])) {
    $goalsCompleted = json_decode($brain['goals_completed'], true) ?: [];
}

// Calculate duration
$duration = '';
if ($session['started_at'] && $session['last_activity']) {
    $start = new DateTime($session['started_at']);
    $end = new DateTime($session['last_activity']);
    $diff = $start->diff($end);
    $duration = $diff->format('%hh %im %ss');
}

$page_title = "Conversation Detail";
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
                        <i class="fas fa-comment-dots me-2"></i>Conversation Detail
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item"><a href="conversations.php">Conversations</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Session Info -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle me-2"></i>Session Info</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th>Session ID:</th>
                                    <td><code class="small"><?= htmlspecialchars($session['id']) ?></code></td>
                                </tr>
                                <tr>
                                    <th>Visitor ID:</th>
                                    <td><code class="small"><?= htmlspecialchars($session['visitor_id']) ?></code></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <?php if ($session['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Started:</th>
                                    <td><?= date('M j, Y H:i:s', strtotime($session['started_at'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Last Activity:</th>
                                    <td><?= date('M j, Y H:i:s', strtotime($session['last_activity'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Duration:</th>
                                    <td><?= $duration ?></td>
                                </tr>
                                <tr>
                                    <th>Messages:</th>
                                    <td><?= $session['message_count'] ?></td>
                                </tr>
                                <tr>
                                    <th>Total Tokens:</th>
                                    <td><?= number_format($session['total_tokens']) ?></td>
                                </tr>
                                <tr>
                                    <th>Primary Intent:</th>
                                    <td>
                                        <?php if ($session['primary_intent']): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($session['primary_intent']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Initial Page:</th>
                                    <td>
                                        <?php if ($session['initial_page_url']): ?>
                                            <a href="<?= htmlspecialchars($session['initial_page_url']) ?>" target="_blank" class="small">
                                                <?= htmlspecialchars(substr($session['initial_page_url'], 0, 40)) ?>...
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>IP Address:</th>
                                    <td><code><?= htmlspecialchars($session['ip_address']) ?></code></td>
                                </tr>
                            </table>
                        </div>
                        <div class="card-footer">
                            <a href="conversations.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>

                    <!-- Customer Info Card -->
                    <?php if ($lead || !empty($collectedData)): ?>
                    <div class="card mt-3">
                        <div class="card-header bg-success text-white">
                            <h3 class="card-title mb-0"><i class="fas fa-user me-2"></i>Customer Info</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <?php if (!empty($lead['full_name']) || !empty($collectedData['name'])): ?>
                                <tr>
                                    <th width="100"><i class="fas fa-user text-muted me-2"></i>Name:</th>
                                    <td><strong><?= htmlspecialchars($lead['full_name'] ?? $collectedData['name'] ?? '-') ?></strong></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($lead['email']) || !empty($collectedData['email'])): ?>
                                <tr>
                                    <th><i class="fas fa-envelope text-muted me-2"></i>Email:</th>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($lead['email'] ?? $collectedData['email']) ?>">
                                            <?= htmlspecialchars($lead['email'] ?? $collectedData['email'] ?? '-') ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($lead['phone']) || !empty($collectedData['phone'])): ?>
                                <tr>
                                    <th><i class="fas fa-phone text-muted me-2"></i>Phone:</th>
                                    <td>
                                        <a href="tel:<?= htmlspecialchars($lead['phone'] ?? $collectedData['phone']) ?>">
                                            <?= htmlspecialchars($lead['phone'] ?? $collectedData['phone'] ?? '-') ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($lead['business_name']) || !empty($collectedData['company'])): ?>
                                <tr>
                                    <th><i class="fas fa-building text-muted me-2"></i>Company:</th>
                                    <td><?= htmlspecialchars($lead['business_name'] ?? $collectedData['company'] ?? '-') ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($lead['needs_summary']) || !empty($collectedData['need'])): ?>
                                <tr>
                                    <th><i class="fas fa-clipboard-list text-muted me-2"></i>Needs:</th>
                                    <td><?= htmlspecialchars($lead['needs_summary'] ?? $collectedData['need'] ?? '-') ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($lead['lead_score'])): ?>
                                <tr>
                                    <th><i class="fas fa-star text-muted me-2"></i>Lead Score:</th>
                                    <td>
                                        <div class="progress" style="height: 20px; max-width: 150px;">
                                            <div class="progress-bar <?= $lead['lead_score'] >= 70 ? 'bg-success' : ($lead['lead_score'] >= 40 ? 'bg-warning' : 'bg-danger') ?>"
                                                 style="width: <?= $lead['lead_score'] ?>%">
                                                <?= $lead['lead_score'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($lead['qualified_at'])): ?>
                                <tr>
                                    <th><i class="fas fa-check-circle text-muted me-2"></i>Qualified:</th>
                                    <td><span class="badge bg-success"><?= date('M j, Y H:i', strtotime($lead['qualified_at'])) ?></span></td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <?php if (!empty($goalsCompleted)): ?>
                            <hr>
                            <h6 class="text-muted mb-2"><i class="fas fa-tasks me-2"></i>Goals Completed</h6>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($goalsCompleted as $goal): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($goal) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($brain && !empty($brain['current_goal'])): ?>
                            <hr>
                            <h6 class="text-muted mb-2"><i class="fas fa-bullseye me-2"></i>Current Goal</h6>
                            <span class="badge bg-primary"><?= htmlspecialchars($brain['current_goal']) ?></span>
                            <?php if (!empty($brain['engagement_level'])): ?>
                                <span class="badge bg-secondary ms-2">Engagement: <?= htmlspecialchars($brain['engagement_level']) ?></span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card mt-3">
                        <div class="card-header bg-secondary text-white">
                            <h3 class="card-title mb-0"><i class="fas fa-user me-2"></i>Customer Info</h3>
                        </div>
                        <div class="card-body text-center text-muted py-4">
                            <i class="fas fa-user-slash fa-2x mb-2"></i>
                            <p class="mb-0">No customer information collected yet</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Messages -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-comments me-2"></i>Conversation</h3>
                        </div>
                        <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                            <?php if (empty($session['messages'])): ?>
                                <div class="text-center text-muted py-4">
                                    No messages in this conversation
                                </div>
                            <?php else: ?>
                                <?php foreach ($session['messages'] as $message): ?>
                                    <div class="direct-chat-msg <?= $message['role'] === 'user' ? 'right' : '' ?> mb-3">
                                        <div class="direct-chat-infos clearfix">
                                            <span class="direct-chat-name <?= $message['role'] === 'user' ? 'float-end' : 'float-start' ?>">
                                                <?= $message['role'] === 'user' ? 'Visitor' : 'Bot' ?>
                                                <?php if ($message['intent']): ?>
                                                    <small class="badge bg-secondary"><?= htmlspecialchars($message['intent']) ?></small>
                                                <?php endif; ?>
                                            </span>
                                            <span class="direct-chat-timestamp <?= $message['role'] === 'user' ? 'float-start' : 'float-end' ?>">
                                                <?= date('H:i:s', strtotime($message['created_at'])) ?>
                                                <?php if ($message['response_time_ms'] > 0): ?>
                                                    <small class="text-muted">(<?= $message['response_time_ms'] ?>ms)</small>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="direct-chat-text <?= $message['role'] === 'user' ? 'bg-primary' : 'bg-light text-dark' ?>">
                                            <?= nl2br(htmlspecialchars($message['content'])) ?>
                                        </div>
                                        <?php if ($message['tokens_used'] > 0): ?>
                                            <small class="text-muted">Tokens: <?= $message['tokens_used'] ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.direct-chat-msg {
    margin-bottom: 15px;
}
.direct-chat-msg.right .direct-chat-text {
    margin-left: auto;
    margin-right: 0;
}
.direct-chat-text {
    border-radius: 8px;
    padding: 12px 15px;
    max-width: 80%;
    display: inline-block;
}
.direct-chat-infos {
    margin-bottom: 5px;
}
</style>

<?php include '../../components/footer.php'; ?>
