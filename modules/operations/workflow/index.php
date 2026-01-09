<?php
/**
 * Operations Module - Workflow Management
 * Modern, user-friendly workflow UI for candidate-job process
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-workflow-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

// Fetch all job-candidate presentations with status and related info
$stmt = $db->query('SELECT w.*, jr.job_title, c.name as client_name, cand.name as candidate_name, cand.email as candidate_email
    FROM job_candidate_presentations w
    LEFT JOIN job_requests jr ON w.job_request_id = jr.id
    LEFT JOIN clients c ON jr.client_id = c.id
    LEFT JOIN candidates cand ON w.candidate_id = cand.id
    ORDER BY jr.job_title, w.presented_at DESC');
$workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group workflows by job request
$grouped_workflows = [];
foreach ($workflows as $wf) {
    $job_id = $wf['job_request_id'];
    if (!isset($grouped_workflows[$job_id])) {
        $grouped_workflows[$job_id] = [
            'job_title' => $wf['job_title'],
            'client_name' => $wf['client_name'],
            'candidates' => []
        ];
    }
    $grouped_workflows[$job_id]['candidates'][] = $wf;
}

// Helper function for status badges
if (!function_exists('get_status_badge_class')) {
    function get_status_badge_class($status) {
        $status = strtolower($status);
        switch ($status) {
            case 'approved':
            case 'hired':
                return 'badge-success';
            case 'rejected':
            case 'cancelled':
                return 'badge-danger';
            case 'manager_review':
            case 'client_review':
                return 'badge-warning';
            case 'proposed':
                return 'badge-info';
            case 'on_hold':
                return 'badge-secondary';
            default:
                return 'badge-primary';
        }
    }
}


$page_title = 'Workflow Management';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Workflow Management</h1>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <?php if (empty($grouped_workflows)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info mb-0">No active workflows found.</div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_workflows as $job_id => $job_data): ?>
                <div class="card card-primary card-outline mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-briefcase mr-2"></i>
                            <strong><a href="../job_requests/view.php?id=<?= $job_id ?>"><?= htmlspecialchars($job_data['job_title']) ?></a></strong>
                            <small class="text-muted ml-2">for <?= htmlspecialchars($job_data['client_name']) ?></small>
                        </h3>
                        <div class="card-tools">
                            <?php if (has_permission('operations-suggest-candidates')): ?>
                                <a href="../job_requests/suggest_candidates.php?job_id=<?= $job_id ?>" class="btn btn-success btn-sm" title="Suggest Candidates">
                                    <i class="fas fa-user-plus"></i>
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 30%;">Candidate</th>
                                        <th style="width: 15%;">Status</th>
                                        <th style="width: 15%;">Presented</th>
                                        <th style="width: 15%;">Last Update</th>
                                        <th style="width: 25%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($job_data['candidates'] as $wf): ?>
                                        <tr>
                                            <td>
                                                <a href="../candidates/view.php?id=<?= $wf['candidate_id'] ?>"><strong><?= htmlspecialchars($wf['candidate_name']) ?></strong></a>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($wf['candidate_email']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?= get_status_badge_class($wf['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $wf['status']))) ?></span>
                                            </td>
                                            <td><?= date('m/d/Y', strtotime($wf['presented_at'])) ?></td>
                                            <td><?= $wf['updated_at'] ? date('m/d/Y', strtotime($wf['updated_at'])) : 'N/A' ?></td>
                                            <td>
                                                <a href="view.php?id=<?= $wf['id'] ?>" class="btn btn-sm btn-info" title="View Workflow Details"><i class="fas fa-eye"></i></a>
                                                <?php
                                                $user_roles = $_SESSION['user_roles'] ?? [];
                                                $status = $wf['status'];
                                                
                                                // Manager: Approve/Reject (only if status is 'manager_review')
                                                if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || has_permission('operations-workflow-approve')) {
                                                    if ($status === 'manager_review') {
                                                        echo '<a href="edit.php?id=' . $wf['id'] . '&action=approve" class="btn btn-primary btn-sm ms-1" title="Approve"><i class="fas fa-check"></i></a>';
                                                        echo '<a href="edit.php?id=' . $wf['id'] . '&action=reject" class="btn btn-danger btn-sm ms-1" title="Reject"><i class="fas fa-times"></i></a>';
                                                    }
                                                }
                                                
                                                // Client: Approve/Reject (only if status is 'client_review')
                                                if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || has_permission('operations-client-approve')) {
                                                    if ($status === 'client_review') {
                                                        echo '<a href="edit.php?id=' . $wf['id'] . '&action=approve" class="btn btn-primary btn-sm ms-1" title="Client Approve"><i class="fas fa-user-check"></i></a>';
                                                        echo '<a href="edit.php?id=' . $wf['id'] . '&action=reject" class="btn btn-danger btn-sm ms-1" title="Client Reject"><i class="fas fa-user-times"></i></a>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
<script>
// No specific script needed for this view, but keeping for consistency.
$(function () {
    // Make cards collapsible
    $('[data-card-widget="collapse"]').on('click', function () {
        var card = $(this).closest('.card');
        if (card.hasClass('collapsed-card')) {
            card.removeClass('collapsed-card');
            card.find('.card-body').slideDown();
            $(this).find('.fa-plus').removeClass('fa-plus').addClass('fa-minus');
        } else {
            card.addClass('collapsed-card');
            card.find('.card-body').slideUp();
            $(this).find('.fa-minus').removeClass('fa-minus').addClass('fa-plus');
        }
    });
});
</script>
