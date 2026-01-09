<?php
/**
 * TimeWorks Module - Email Campaign Reports
 *
 * View all email campaigns and their statistics
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

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();

if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Get campaigns
$campaigns = [];
$totalStats = [
    'total_campaigns' => 0,
    'total_sent' => 0,
    'total_opens' => 0,
    'total_clicks' => 0
];

try {
    $stmt = $db->query("
        SELECT c.*,
               u.name as created_by_name,
               (SELECT COUNT(*) FROM email_sends WHERE campaign_id = c.id) as total_recipients,
               (SELECT COUNT(*) FROM email_sends WHERE campaign_id = c.id AND status = 'sent') as sent_count,
               (SELECT COUNT(*) FROM email_sends WHERE campaign_id = c.id AND status = 'failed') as failed_count,
               (SELECT COUNT(DISTINCT es.id) FROM email_sends es
                INNER JOIN email_opens eo ON es.id = eo.send_id
                WHERE es.campaign_id = c.id) as opened_count,
               (SELECT COUNT(DISTINCT es.id) FROM email_sends es
                INNER JOIN email_clicks ec ON es.id = ec.send_id
                WHERE es.campaign_id = c.id) as clicked_count
        FROM email_campaigns c
        LEFT JOIN users u ON c.created_by = u.id
        ORDER BY c.created_at DESC
    ");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalStats['total_campaigns'] = count($campaigns);
    foreach ($campaigns as $campaign) {
        $totalStats['total_sent'] += $campaign['sent_count'];
        $totalStats['total_opens'] += $campaign['opened_count'];
        $totalStats['total_clicks'] += $campaign['clicked_count'];
    }
} catch (PDOException $e) {
    error_log("Error loading campaigns: " . $e->getMessage());
}

$page_title = "TimeWorks - Email Reports";
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
                        <i class="fas fa-chart-bar text-primary"></i> Email Reports
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Email Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?= $totalStats['total_campaigns'] ?></h3>
                            <p>Total Campaigns</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-mail-bulk"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($totalStats['total_sent']) ?></h3>
                            <p>Emails Sent</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($totalStats['total_opens']) ?></h3>
                            <p>Emails Opened</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($totalStats['total_clicks']) ?></h3>
                            <p>Link Clicks</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-mouse-pointer"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaigns Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> All Campaigns</h3>
                    <div class="card-tools">
                        <?php if (has_permission('timeworks_email_manage')): ?>
                        <a href="bulk-email.php" class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> New Campaign
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <table id="campaignsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Status</th>
                                <th>Sent</th>
                                <th>Open Rate</th>
                                <th>Click Rate</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign):
                                $openRate = $campaign['sent_count'] > 0 ? round(($campaign['opened_count'] / $campaign['sent_count']) * 100, 1) : 0;
                                $clickRate = $campaign['sent_count'] > 0 ? round(($campaign['clicked_count'] / $campaign['sent_count']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($campaign['name']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars(substr($campaign['subject'], 0, 50)) ?>...</small>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = [
                                        'draft' => 'secondary',
                                        'sending' => 'primary',
                                        'paused' => 'warning',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $statusBadge[$campaign['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($campaign['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $campaign['sent_count'] ?>/<?= $campaign['total_recipients'] ?>
                                    <?php if ($campaign['failed_count'] > 0): ?>
                                    <br><small class="text-danger"><?= $campaign['failed_count'] ?> failed</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-info" style="width: <?= $openRate ?>%;">
                                            <?= $openRate ?>%
                                        </div>
                                    </div>
                                    <small><?= $campaign['opened_count'] ?> opened</small>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-warning" style="width: <?= $clickRate ?>%;">
                                            <?= $clickRate ?>%
                                        </div>
                                    </div>
                                    <small><?= $campaign['clicked_count'] ?> clicked</small>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($campaign['created_at'])) ?>
                                    <br>
                                    <small class="text-muted"><?= $campaign['created_by_name'] ?? 'System' ?></small>
                                </td>
                                <td>
                                    <a href="email-campaign-detail.php?id=<?= $campaign['id'] ?>" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#campaignsTable').DataTable({
        "order": [[5, "desc"]],
        "pageLength": 25
    });
});
</script>

<?php include '../../components/footer.php'; ?>
