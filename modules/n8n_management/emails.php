<?php
/**
 * N8N Management Module - Emails List
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

// Get filters
$page = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status' => $_GET['status'] ?? '',
    'email_type' => $_GET['email_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$result = $emailModel->getAll($page, 20, $filters);
$emails = $result['data'];
$stats = $emailModel->getStats();

$page_title = "Email Tracking";
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
                        <i class="fas fa-envelope me-2"></i>Email Tracking
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Emails</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-lg-3 col-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-envelope"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total</span>
                            <span class="info-box-number"><?= $stats['total'] ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Sent</span>
                            <span class="info-box-number"><?= $stats['by_status']['sent'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="info-box bg-primary">
                        <span class="info-box-icon"><i class="fas fa-envelope-open"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Opened</span>
                            <span class="info-box-number"><?= $stats['by_status']['opened'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Open Rate</span>
                            <span class="info-box-number"><?= $stats['open_rate'] ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter me-2"></i>Filters</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="sent" <?= $filters['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                                <option value="opened" <?= $filters['status'] === 'opened' ? 'selected' : '' ?>>Opened</option>
                                <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select name="email_type" class="form-select">
                                <option value="">All</option>
                                <option value="session_summary" <?= $filters['email_type'] === 'session_summary' ? 'selected' : '' ?>>Session Summary</option>
                                <option value="lead_capture" <?= $filters['email_type'] === 'lead_capture' ? 'selected' : '' ?>>Lead Capture</option>
                                <option value="daily_report" <?= $filters['email_type'] === 'daily_report' ? 'selected' : '' ?>>Daily Report</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Emails List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= number_format($result['total']) ?> Emails</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Opens</th>
                                <th>Created</th>
                                <th>Sent</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($emails)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No emails found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($emails as $email): ?>
                                    <tr>
                                        <td>
                                            <span title="<?= htmlspecialchars($email['subject']) ?>">
                                                <?= htmlspecialchars(substr($email['subject'], 0, 40)) ?>...
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($email['recipient_email']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= str_replace('_', ' ', $email['email_type']) ?></span>
                                        </td>
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
                                        <td>
                                            <?php if ($email['open_count'] > 0): ?>
                                                <span class="badge bg-info"><?= $email['open_count'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?= date('M j, H:i', strtotime($email['created_at'])) ?></small></td>
                                        <td>
                                            <?php if ($email['sent_at']): ?>
                                                <small><?= date('M j, H:i', strtotime($email['sent_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="email-detail.php?id=<?= $email['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($result['pages'] > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
