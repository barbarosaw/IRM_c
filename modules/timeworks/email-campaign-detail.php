<?php
/**
 * TimeWorks Module - Email Campaign Detail
 *
 * View detailed statistics and recipient tracking for a campaign
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';
require_once 'helpers/EmailTrackingHelper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_email_view') && !has_permission('timeworks_email_manage')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Get campaign ID
$campaignId = (int)($_GET['id'] ?? 0);

if (!$campaignId) {
    header('Location: email-reports.php');
    exit;
}

// Get campaign data
$campaign = null;
$stats = [];
$recipients = [];
$linkStats = [];

try {
    $stmt = $db->prepare("
        SELECT c.*, u.name as created_by_name, t.name as template_name
        FROM email_campaigns c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN email_templates t ON c.template_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        header('Location: email-reports.php');
        exit;
    }

    // Get stats using helper
    $trackingHelper = new EmailTrackingHelper($db);
    $stats = $trackingHelper->getCampaignStats($campaignId);
    $linkStats = $trackingHelper->getCampaignLinkStats($campaignId);

    // Get recipients
    $stmt = $db->prepare("
        SELECT es.*,
               (SELECT COUNT(*) FROM email_opens WHERE send_id = es.id) as open_count,
               (SELECT MIN(opened_at) FROM email_opens WHERE send_id = es.id) as first_opened_at,
               (SELECT COUNT(*) FROM email_clicks WHERE send_id = es.id) as click_count,
               (SELECT MIN(clicked_at) FROM email_clicks WHERE send_id = es.id) as first_clicked_at
        FROM email_sends es
        WHERE es.campaign_id = ?
        ORDER BY es.id ASC
    ");
    $stmt->execute([$campaignId]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error loading campaign: " . $e->getMessage());
    header('Location: email-reports.php');
    exit;
}

$page_title = "Campaign: " . htmlspecialchars($campaign['name']);
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-envelope text-primary"></i> Campaign Details
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item"><a href="email-reports.php">Email Reports</a></li>
                        <li class="breadcrumb-item active">Campaign Details</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Campaign Info Card -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><?= htmlspecialchars($campaign['name']) ?></h3>
                    <div class="card-tools">
                        <?php
                        $statusBadge = [
                            'draft' => 'secondary',
                            'sending' => 'primary',
                            'paused' => 'warning',
                            'completed' => 'success',
                            'cancelled' => 'danger'
                        ];
                        ?>
                        <span class="badge bg-<?= $statusBadge[$campaign['status']] ?? 'secondary' ?> me-2">
                            <?= ucfirst($campaign['status']) ?>
                        </span>
                        <a href="email-reports.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Subject:</strong> <?= htmlspecialchars($campaign['subject']) ?></p>
                            <p><strong>Template:</strong> <?= htmlspecialchars($campaign['template_name'] ?? 'Custom') ?></p>
                            <p><strong>Created by:</strong> <?= htmlspecialchars($campaign['created_by_name'] ?? 'System') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Created:</strong> <?= date('M j, Y H:i', strtotime($campaign['created_at'])) ?></p>
                            <?php if ($campaign['started_at']): ?>
                            <p><strong>Started:</strong> <?= date('M j, Y H:i', strtotime($campaign['started_at'])) ?></p>
                            <?php endif; ?>
                            <?php if ($campaign['completed_at']): ?>
                            <p><strong>Completed:</strong> <?= date('M j, Y H:i', strtotime($campaign['completed_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $stats['sent'] ?></h3>
                            <p>Sent</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $stats['unique_opens'] ?> <small style="font-size: 0.5em;">(<?= $stats['open_rate'] ?>%)</small></h3>
                            <p>Opened</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $stats['unique_clicks'] ?> <small style="font-size: 0.5em;">(<?= $stats['click_rate'] ?>%)</small></h3>
                            <p>Clicked</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-mouse-pointer"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $stats['failed'] ?></h3>
                            <p>Failed</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Link Statistics -->
                <div class="col-lg-4">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-link"></i> Link Clicks</h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($linkStats)): ?>
                            <p class="text-muted text-center py-3">No link clicks recorded</p>
                            <?php else: ?>
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>URL</th>
                                        <th>Clicks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($linkStats as $link): ?>
                                    <tr>
                                        <td>
                                            <small title="<?= htmlspecialchars($link['url']) ?>">
                                                <?= htmlspecialchars(substr($link['url'], 0, 40)) ?>...
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?= $link['click_count'] ?></span>
                                            <small class="text-muted">(<?= $link['unique_clicks'] ?> unique)</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recipients Table -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Recipients</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="filterRecipients('all')">All</button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="filterRecipients('opened')">Opened</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterRecipients('not_opened')">Not Opened</button>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="filterRecipients('clicked')">Clicked</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="recipientsTable" class="table table-bordered table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Recipient</th>
                                        <th>Status</th>
                                        <th>Opens</th>
                                        <th>Clicks</th>
                                        <th>Sent At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recipients as $recipient): ?>
                                    <tr data-opened="<?= $recipient['open_count'] > 0 ? '1' : '0' ?>"
                                        data-clicked="<?= $recipient['click_count'] > 0 ? '1' : '0' ?>"
                                        data-status="<?= $recipient['status'] ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($recipient['recipient_name'] ?? '-') ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($recipient['email']) ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'pending' => 'secondary',
                                                'sent' => 'success',
                                                'failed' => 'danger',
                                                'bounced' => 'warning'
                                            ];
                                            ?>
                                            <span class="badge bg-<?= $statusColors[$recipient['status']] ?? 'secondary' ?>">
                                                <?= ucfirst($recipient['status']) ?>
                                            </span>
                                            <?php if ($recipient['error_message']): ?>
                                            <br><small class="text-danger" title="<?= htmlspecialchars($recipient['error_message']) ?>">
                                                <?= htmlspecialchars(substr($recipient['error_message'], 0, 30)) ?>...
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($recipient['open_count'] > 0): ?>
                                            <span class="badge bg-info"><?= $recipient['open_count'] ?></span>
                                            <br><small><?= date('M j H:i', strtotime($recipient['first_opened_at'])) ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($recipient['click_count'] > 0): ?>
                                            <span class="badge bg-warning"><?= $recipient['click_count'] ?></span>
                                            <br><small><?= date('M j H:i', strtotime($recipient['first_clicked_at'])) ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($recipient['sent_at']): ?>
                                            <?= date('M j H:i', strtotime($recipient['sent_at'])) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Body Preview -->
            <div class="card card-secondary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-alt"></i> Email Body</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="border p-3 bg-white" style="max-height: 400px; overflow-y: auto;">
                        <?= $campaign['body'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var recipientsTable;

$(document).ready(function() {
    recipientsTable = $('#recipientsTable').DataTable({
        "order": [[4, "desc"]],
        "pageLength": 25,
        "language": {
            "emptyTable": "No recipients found"
        }
    });
});

function filterRecipients(filter) {
    recipientsTable.search('').columns().search('').draw();

    $('#recipientsTable tbody tr').show();

    if (filter === 'opened') {
        $('#recipientsTable tbody tr').each(function() {
            if ($(this).data('opened') != '1') {
                $(this).hide();
            }
        });
    } else if (filter === 'not_opened') {
        $('#recipientsTable tbody tr').each(function() {
            if ($(this).data('opened') == '1' || $(this).data('status') != 'sent') {
                $(this).hide();
            }
        });
    } else if (filter === 'clicked') {
        $('#recipientsTable tbody tr').each(function() {
            if ($(this).data('clicked') != '1') {
                $(this).hide();
            }
        });
    }
}
</script>

<?php include '../../components/footer.php'; ?>
