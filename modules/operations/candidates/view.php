<?php
/**
 * Operations Module - View Candidate Details
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-candidates-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT c.*, GROUP_CONCAT(cat.name) as categories FROM candidates c
    LEFT JOIN candidate_categories cc ON c.id = cc.candidate_id
    LEFT JOIN categories cat ON cc.category_id = cat.id
    WHERE c.id = ? GROUP BY c.id');
$stmt->execute([$id]);
$candidate = $stmt->fetch();
if (!$candidate) {
    header('Location: index.php');
    exit;
}

// Fetch keywords
$kw_stmt = $db->prepare('SELECT keyword FROM candidate_keywords WHERE candidate_id = ?');
$kw_stmt->execute([$id]);
$keywords = $kw_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch logs
$log_stmt = $db->prepare('SELECT l.*, u.name as user_name FROM candidate_logs l LEFT JOIN users u ON l.performed_by = u.id WHERE l.candidate_id = ? ORDER BY l.created_at DESC');
$log_stmt->execute([$id]);
$logs = $log_stmt->fetchAll();

// Fetch job application history
$job_history_stmt = $db->prepare('
    SELECT
        jcp.id as presentation_id,
        jcp.status,
        jcp.presented_at,
        jr.id as job_request_id,
        jr.job_title,
        c.name as client_name
    FROM job_candidate_presentations jcp
    JOIN job_requests jr ON jcp.job_request_id = jr.id
    JOIN clients c ON jr.client_id = c.id
    WHERE jcp.candidate_id = ?
    ORDER BY jcp.presented_at DESC
');
$job_history_stmt->execute([$id]);
$job_history = $job_history_stmt->fetchAll();


$page_title = 'Candidate Details';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1 class="m-0 text-primary d-flex align-items-center">
                        <i class="fas fa-user-tie me-3"></i>
                        <?= htmlspecialchars($candidate['name']) ?>
                    </h1>
                    <p class="text-muted ms-5"><?= htmlspecialchars($candidate['email']) ?></p>
                </div>
                <div class="col-sm-4 text-end">
                    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit me-1"></i> Edit Candidate</a>
                    <a href="index.php" class="btn btn-secondary">Back to List</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-7">
                <!-- Main Details Card -->
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Candidate Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-user-tag me-1"></i> Internal Status</strong>
                                <p class="text-muted"><?= htmlspecialchars($candidate['internal_status']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-user-tag me-1"></i> External Status</strong>
                                <p class="text-muted"><?= htmlspecialchars($candidate['external_status']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-user-friends me-1"></i> Nick Name</strong>
                                <p class="text-muted"><?= htmlspecialchars($candidate['referrer']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-user-edit me-1"></i> Redactor</strong>
                                <p class="text-muted"><?= htmlspecialchars($candidate['redactor']) ?></p>
                            </div>
                        </div>
                        <hr>
                        <strong><i class="fas fa-id-card-alt me-1"></i> Profile</strong>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($candidate['profile'])) ?></p>
                        <hr>
                        <strong><i class="fas fa-sticky-note me-1"></i> Notes</strong>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($candidate['notes'])) ?></p>
                        <hr>
                        <strong><i class="fas fa-comments me-1"></i> Comments</strong>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($candidate['comments'])) ?></p>
                        <hr>
                        <div class="row">
                            <div class="col-md-4">
                                <strong><i class="fas fa-check-circle me-1"></i> Reprofile</strong>
                                <p><?= $candidate['reprofile'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong><i class="fas fa-check-double me-1"></i> Done</strong>
                                <p><?= $candidate['done'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></p>
                            </div>
                            <div class="col-md-4">
                                <?php if ($candidate['resume_file']): ?>
                                    <a href="../../../<?= htmlspecialchars($candidate['resume_file']) ?>" target="_blank" class="btn btn-success"><i class="fas fa-download me-1"></i> Download Resume</a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled><i class="fas fa-download me-1"></i> No Resume</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Categories & Keywords -->
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Tags</h3></div>
                    <div class="card-body">
                        <strong><i class="fas fa-tags me-1"></i> Categories</strong>
                        <p class="mt-2">
                            <?php if (!empty($candidate['categories'])): ?>
                                <?php foreach (explode(',', $candidate['categories']) as $cat): ?>
                                    <span class="badge bg-info me-1"><?= htmlspecialchars($cat) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No categories assigned.</span>
                            <?php endif; ?>
                        </p>
                        <hr>
                        <strong><i class="fas fa-key me-1"></i> Keywords</strong>
                        <p class="mt-2">
                            <?php if (!empty($keywords)): ?>
                                <?php foreach ($keywords as $kw): ?>
                                    <span class="badge bg-secondary me-1"><?= htmlspecialchars($kw) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No keywords assigned.</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <!-- Right Column -->
            <div class="col-lg-5">
                <!-- Job History -->
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Job Application History</h3></div>
                    <div class="card-body p-0">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($job_history): ?>
                                    <?php foreach ($job_history as $job): ?>
                                        <tr>
                                            <td><a href="../job_requests/view.php?id=<?= $job['job_request_id'] ?>"><?= htmlspecialchars($job['job_title']) ?></a></td>
                                            <td><?= htmlspecialchars($job['client_name']) ?></td>
                                            <td><span class="badge bg-warning"><?= htmlspecialchars($job['status']) ?></span></td>
                                            <td><?= date('m/d/Y', strtotime($job['presented_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">No job application history.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Activity Log -->
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Activity Log</h3></div>
                    <div class="card-body">
                        <?php if ($logs): ?>
                            <ul class="timeline">
                                <?php foreach ($logs as $log): ?>
                                    <li>
                                        <div class="timeline-item">
                                            <span class="time"><i class="fas fa-clock"></i> <?= date('m/d/Y H:i', strtotime($log['created_at'])) ?></span>
                                            <h3 class="timeline-header">
                                                <a href="#"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></a>
                                                <?= htmlspecialchars($log['action']) ?>
                                            </h3>
                                            <?php if (!empty($log['details'])): ?>
                                                <div class="timeline-body">
                                                    <?= nl2br(htmlspecialchars($log['details'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center text-muted">No activity logs found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
