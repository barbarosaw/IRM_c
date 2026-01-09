<?php
/**
 * N8N Management Module - Email Detail
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

require_once __DIR__ . '/models/ChatEmail.php';
$emailModel = new ChatEmail($db);

$emailId = (int)($_GET['id'] ?? 0);
if (!$emailId) {
    header('Location: emails.php');
    exit;
}

$email = $emailModel->getById($emailId);
if (!$email) {
    header('Location: emails.php');
    exit;
}

$page_title = "Email Detail";
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
                        <i class="fas fa-envelope-open me-2"></i>Email Detail
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item"><a href="emails.php">Emails</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Email Info -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle me-2"></i>Email Info</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th>ID:</th>
                                    <td><?= $email['id'] ?></td>
                                </tr>
                                <tr>
                                    <th>Type:</th>
                                    <td><span class="badge bg-secondary"><?= str_replace('_', ' ', $email['email_type']) ?></span></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'warning',
                                            'sent' => 'success',
                                            'opened' => 'primary',
                                            'failed' => 'danger'
                                        ][$email['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($email['status']) ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Recipient:</th>
                                    <td><?= htmlspecialchars($email['recipient_email']) ?></td>
                                </tr>
                                <?php if ($email['cc_emails']): ?>
                                <tr>
                                    <th>CC:</th>
                                    <td><small><?= htmlspecialchars($email['cc_emails']) ?></small></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($email['bcc_emails']): ?>
                                <tr>
                                    <th>BCC:</th>
                                    <td><small><?= htmlspecialchars($email['bcc_emails']) ?></small></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Created:</th>
                                    <td><?= date('M j, Y H:i:s', strtotime($email['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Sent:</th>
                                    <td>
                                        <?php if ($email['sent_at']): ?>
                                            <?= date('M j, Y H:i:s', strtotime($email['sent_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not sent yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tracking ID:</th>
                                    <td><code class="small"><?= htmlspecialchars($email['tracking_id']) ?></code></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Tracking Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line me-2"></i>Tracking</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($email['status'] === 'opened'): ?>
                                <div class="text-center">
                                    <div class="display-4 text-primary mb-2"><?= $email['open_count'] ?></div>
                                    <p class="text-muted mb-0">Opens</p>
                                </div>
                                <hr>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th>First Opened:</th>
                                        <td><?= date('M j, Y H:i:s', strtotime($email['opened_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Open Count:</th>
                                        <td><?= $email['open_count'] ?></td>
                                    </tr>
                                </table>
                            <?php elseif ($email['status'] === 'sent'): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-clock fa-3x mb-3"></i>
                                    <p>Email sent but not opened yet</p>
                                </div>
                            <?php elseif ($email['status'] === 'pending'): ?>
                                <div class="text-center text-warning">
                                    <i class="fas fa-hourglass-half fa-3x mb-3"></i>
                                    <p>Email pending to be sent</p>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-danger">
                                    <i class="fas fa-times-circle fa-3x mb-3"></i>
                                    <p>Email failed to send</p>
                                    <?php if ($email['error_message']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($email['error_message']) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($email['session_id']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-link me-2"></i>Related Session</h3>
                        </div>
                        <div class="card-body">
                            <a href="conversation-detail.php?id=<?= urlencode($email['session_id']) ?>" class="btn btn-outline-primary btn-block w-100">
                                <i class="fas fa-comments me-1"></i> View Conversation
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <a href="emails.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Emails
                    </a>
                </div>

                <!-- Email Content -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-envelope me-2"></i>Email Content</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Subject:</label>
                                <div class="border rounded p-2 bg-light">
                                    <?= htmlspecialchars($email['subject']) ?>
                                </div>
                            </div>
                            <div>
                                <label class="form-label fw-bold">Body:</label>
                                <div class="border rounded p-3 bg-light" style="white-space: pre-wrap; font-family: monospace; font-size: 13px; max-height: 500px; overflow-y: auto;">
<?= htmlspecialchars($email['body']) ?>
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
