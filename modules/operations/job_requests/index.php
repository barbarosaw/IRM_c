<?php
/**
 * Operations Module - Job Requests List
 * Modern, user-friendly job request management UI
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-jobrequests-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$stmt = $db->query('SELECT jr.*, c.name as client_name FROM job_requests jr LEFT JOIN clients c ON jr.client_id = c.id ORDER BY jr.created_at DESC');
$job_requests = $stmt->fetchAll();

$page_title = 'Job Requests';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Job Requests</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <?php if (has_permission('operations-jobrequests-manage')): ?>
                        <a href="add.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Job Request</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="job-requests-table">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Job Title</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($job_requests as $job): ?>
                            <tr>
                                <td><?= htmlspecialchars($job['client_name']) ?></td>
                                <td><?= htmlspecialchars($job['job_title']) ?></td>
                                <td><?= htmlspecialchars($job['status']) ?></td>
                                <td><?= date('Y-m-d', strtotime($job['created_at'])) ?></td>
                                <td>
                                    <a href="view.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                    <?php if (has_permission('operations-jobrequests-manage')): ?>
                                        <a href="edit.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                        <a href="delete.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
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
</div>
<?php include $root_path . 'components/footer.php'; ?>
<script>
// Optionally, add DataTables or similar for better UX
</script>
