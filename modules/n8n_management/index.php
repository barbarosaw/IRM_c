<?php
/**
 * N8N Management Module - Dashboard
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

// Load models
require_once __DIR__ . '/models/ChatSession.php';
require_once __DIR__ . '/models/ChatEmail.php';
require_once __DIR__ . '/models/N8nApi.php';

$sessionModel = new ChatSession($db);
$emailModel = new ChatEmail($db);
$n8nApi = new N8nApi($db);

// Get statistics
$sessionStats = $sessionModel->getStats();
$emailStats = $emailModel->getStats();
$recentSessions = $sessionModel->getRecent(10);

// Get n8n status
$n8nStatus = null;
if ($n8nApi->isConfigured()) {
    $n8nStatus = $n8nApi->getExecutionStats();
}

$page_title = "N8N Management - Dashboard";
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
                        <i class="fas fa-robot me-2"></i>Chatbot Dashboard
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item active">N8N Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="btn-group flex-wrap">
                        <a href="conversations.php" class="btn btn-outline-primary">
                            <i class="fas fa-comments me-1"></i> Conversations
                        </a>
                        <a href="emails.php" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-1"></i> Emails
                        </a>
                        <a href="knowledge-base.php" class="btn btn-outline-success">
                            <i class="fas fa-book me-1"></i> Knowledge Base
                        </a>
                        <a href="chat-prompts.php" class="btn btn-outline-info">
                            <i class="fas fa-comment-dots me-1"></i> Chat Prompts
                        </a>
                        <a href="rules.php" class="btn btn-outline-warning">
                            <i class="fas fa-brain me-1"></i> AI Rules
                        </a>
                        <a href="widget-settings.php" class="btn btn-outline-primary">
                            <i class="fas fa-cog me-1"></i> Widget Settings
                        </a>
                        <a href="<?= $root_path ?>modules/settings/?tab=n8n-settings" class="btn btn-outline-secondary">
                            <i class="fas fa-plug me-1"></i> n8n Connection
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($sessionStats['total_sessions']) ?></h3>
                            <p>Total Sessions</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <a href="conversations.php" class="small-box-footer">
                            View all <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($sessionStats['today_sessions']) ?></h3>
                            <p>Today's Sessions</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <a href="conversations.php?filter=today" class="small-box-footer">
                            View today <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($sessionStats['total_messages']) ?></h3>
                            <p>Total Messages</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <span class="small-box-footer">&nbsp;</span>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?= $emailStats['open_rate'] ?>%</h3>
                            <p>Email Open Rate</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <a href="emails.php" class="small-box-footer">
                            View emails <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Sessions -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock me-2"></i>Recent Conversations
                            </h3>
                            <div class="card-tools">
                                <a href="conversations.php" class="btn btn-tool">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Session</th>
                                        <th>Intent</th>
                                        <th>Messages</th>
                                        <th>Status</th>
                                        <th>Started</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentSessions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No conversations yet
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentSessions as $session): ?>
                                            <tr>
                                                <td>
                                                    <code class="small"><?= htmlspecialchars(substr($session['id'], 0, 8)) ?>...</code>
                                                    <?php if (!empty($session['first_message'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars(substr($session['first_message'], 0, 50)) ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($session['primary_intent']): ?>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($session['primary_intent']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $session['message_count'] ?></td>
                                                <td>
                                                    <?php if ($session['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Closed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, H:i', strtotime($session['started_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <a href="conversation-detail.php?id=<?= urlencode($session['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Side Stats -->
                <div class="col-lg-4">
                    <!-- n8n Status -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-server me-2"></i>n8n Status
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (!$n8nApi->isConfigured()): ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    n8n is not configured.
                                    <a href="<?= $root_path ?>modules/settings/?tab=n8n-settings">Configure now</a>
                                </div>
                            <?php elseif ($n8nStatus && $n8nStatus['success']): ?>
                                <div class="row text-center">
                                    <div class="col-4 border-end">
                                        <h4 class="mb-0 text-success"><?= $n8nStatus['stats']['success'] ?></h4>
                                        <small class="text-muted">Success</small>
                                    </div>
                                    <div class="col-4 border-end">
                                        <h4 class="mb-0 text-danger"><?= $n8nStatus['stats']['error'] ?></h4>
                                        <small class="text-muted">Error</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="mb-0 text-info"><?= $n8nStatus['stats']['running'] ?></h4>
                                        <small class="text-muted">Running</small>
                                    </div>
                                </div>
                                <p class="text-muted text-center mt-2 mb-0">
                                    <small>Last 100 executions</small>
                                </p>
                            <?php else: ?>
                                <div class="alert alert-danger mb-0">
                                    <i class="fas fa-times-circle me-2"></i>
                                    <?= htmlspecialchars($n8nStatus['error'] ?? 'Connection failed') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Intent Distribution -->
                    <?php if (!empty($sessionStats['by_intent'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie me-2"></i>Intent Distribution
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($sessionStats['by_intent'] as $intent): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-info"><?= htmlspecialchars($intent['primary_intent']) ?></span>
                                    <span class="text-muted"><?= $intent['count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Email Stats -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-envelope me-2"></i>Email Stats
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4 border-end">
                                    <h4 class="mb-0"><?= $emailStats['total'] ?></h4>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4 border-end">
                                    <h4 class="mb-0"><?= $emailStats['sent_today'] ?></h4>
                                    <small class="text-muted">Today</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="mb-0"><?= $emailStats['by_status']['pending'] ?? 0 ?></h4>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
