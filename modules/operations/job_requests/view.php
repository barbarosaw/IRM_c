<?php

/**
 * Operations Module - View Job Request Details
 * Modern, user-friendly job request view page
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';
require_once 'interview_helpers.php';

if (!has_permission('operations-jobrequests-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch main job request data
$stmt = $db->prepare('SELECT jr.*, c.name as client_name, u.name as creator_name 
                     FROM job_requests jr 
                     LEFT JOIN clients c ON jr.client_id = c.id 
                     LEFT JOIN users u ON jr.created_by = u.id
                     WHERE jr.id = ?');
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: index.php');
    exit;
}

// Fetch categories
$cat_stmt = $db->prepare('SELECT cat.id, cat.name FROM job_request_categories jrc JOIN categories cat ON jrc.category_id = cat.id WHERE jrc.job_request_id = ? ORDER BY cat.name');
$cat_stmt->execute([$id]);
$categories = $cat_stmt->fetchAll();

// Fetch related candidates
$cand_stmt = $db->prepare('SELECT c.id, c.name, c.internal_status, c.external_status, jc.id as presentation_id, jc.status as workflow_status
                         FROM job_candidate_presentations jc 
                         JOIN candidates c ON jc.candidate_id = c.id 
                         WHERE jc.job_request_id = ? 
                         ORDER BY c.name');
$cand_stmt->execute([$id]);
$related_candidates = $cand_stmt->fetchAll();

// Define workflow tabs as requested (include legacy and new status values)
$status_groups = [
    'suggested' => [
        'label' => 'Suggested',
        'icon' => 'fas fa-lightbulb',
        'bg' => 'bg-primary',
        'statuses' => ['proposed', 'suggested']
    ],
    'approved' => [
        'label' => 'Approved',
        'icon' => 'fas fa-user-check',
        'bg' => 'bg-success',
        'statuses' => [
            'approved',
            'approved_by_manager',
            'approved_by_client',
            'approved_by_candidate',
            'manager_review',
            'client_review'
        ]
    ],
    'rejected' => [
        'label' => 'Rejected',
        'icon' => 'fas fa-user-times',
        'bg' => 'bg-danger',
        'statuses' => ['rejected', 'rejected_by_manager', 'rejected_by_client', 'rejected_by_candidate']
    ],
    'interviewed' => [
        'label' => 'Interviewed',
        'icon' => 'fas fa-comments',
        'bg' => 'bg-info',
        'statuses' => ['interviewed']
    ],
    'job_offered' => [
        'label' => 'Job Offered',
        'icon' => 'fas fa-briefcase',
        'bg' => 'bg-warning',
        'statuses' => ['job_offered']
    ],
    'hired' => [
        'label' => 'Hired',
        'icon' => 'fas fa-star',
        'bg' => 'bg-success',
        'statuses' => ['hired']
    ],
];

// Normalize status for grouping
$tab_candidates = [];
foreach ($status_groups as $key => $group) {
    $tab_candidates[$key] = [];
}
foreach ($related_candidates as $candidate) {
    $status = strtolower(trim(str_replace([' ', '-'], ['_', '_'], $candidate['workflow_status'])));
    foreach ($status_groups as $key => $group) {
        foreach ($group['statuses'] as $group_status) {
            if ($status === strtolower($group_status)) {
                $tab_candidates[$key][] = $candidate;
                break 2;
            }
        }
    }
}

// Special handling for Interviewed tab - Get candidates with interview records
$interview_stmt = $db->prepare('
    SELECT c.id, c.name, c.internal_status, c.external_status, jcp.id as presentation_id, jcp.status as workflow_status,
           jci.id as interview_id, jci.scheduled_at, jci.platform, jci.meeting_link, jci.status as interview_status,
           jci.note as interview_note, jci.duration_minutes
    FROM job_candidate_presentations jcp
    JOIN candidates c ON jcp.candidate_id = c.id
    JOIN job_candidate_interviews jci ON jci.job_candidate_presentation_id = jcp.id
    WHERE jcp.job_request_id = ?
    ORDER BY jci.scheduled_at DESC');
$interview_stmt->execute([$id]);
$interviewed_candidates = $interview_stmt->fetchAll();

// Add interviewed candidates to the tab_candidates
foreach ($interviewed_candidates as $candidate) {
    // Flag to avoid duplicates
    $found = false;
    foreach ($tab_candidates['interviewed'] as $existing) {
        if ($existing['id'] == $candidate['id']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $tab_candidates['interviewed'][] = $candidate;
    }
}

// Fetch history logs
$log_stmt = $db->prepare('SELECT l.*, u.name as user_name 
                       FROM job_request_logs l 
                       LEFT JOIN users u ON l.performed_by = u.id 
                       WHERE l.job_request_id = ? 
                       ORDER BY l.created_at DESC');
$log_stmt->execute([$id]);
$logs = $log_stmt->fetchAll();

// Helper function for status badges
function getStatusBadgeClass($status)
{
    if (!$status) return 'bg-secondary';
    switch ($status) {
        case 'Active':
            return 'bg-primary';
        case 'Screened':
            return 'bg-info';
        case 'Shortlisted':
            return 'bg-purple';
        case 'Interviewed':
            return 'bg-warning text-dark';
        case 'Rejected':
            return 'bg-danger';
        case 'Available':
            return 'bg-success';
        case 'Not Available':
            return 'bg-secondary';
        case 'Offer Pending':
            return 'bg-orange';
        case 'Hired':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

// Group candidates for Approved/Rejected by who approved/rejected
function group_by_approver($candidates, $type = 'approved')
{
    $groups = [
        'manager' => [],
        'client' => [],
        'candidate' => [],
        'other' => []
    ];
    foreach ($candidates as $c) {
        $status = strtolower($c['workflow_status']);
        if (str_contains($status, 'manager')) $groups['manager'][] = $c;
        elseif (str_contains($status, 'client')) $groups['client'][] = $c;
        elseif (str_contains($status, 'candidate')) $groups['candidate'][] = $c;
        else $groups['other'][] = $c;
    }
    return $groups;
}

$page_title = 'Job Request: ' . htmlspecialchars($job['job_title']);
$root_path = '../../../';
include $root_path . 'components/header.php';
?>
<?php
include $root_path . 'components/sidebar.php';
?>
<style>
    .badge.bg-purple {
        color: #fff;
        background-color: #6f42c1;
    }

    .badge.bg-orange {
        color: #fff;
        background-color: #fd7e14;
    }

    .timeline {
        position: relative;
        padding-left: 2rem;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .timeline-icon {
        position: absolute;
        left: -10px;
        top: 5px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        text-align: center;
        line-height: 20px;
        font-size: 10px;
        color: #fff;
    }

    .compact-history .badge {
        font-size: 0.85em;
        font-weight: 400;
        background: #f8f9fa;
        color: #333;
        border: 1px solid #ddd;
    }

    .compact-history .list-group-item {
        font-size: 0.97em;
        padding-top: 0.35rem;
        padding-bottom: 0.35rem;
        border: none;
        border-bottom: 1px solid #f0f0f0;
    }

    .compact-history .list-group-item:last-child {
        border-bottom: none;
    }

    .stat-box {
        min-width: 70px;
        max-width: 70px;
        min-height: 100px;
        max-height: 100px;
        padding: 8px 4px 6px 4px;
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 0 0 auto;
        justify-content: flex-end;
        position: relative;
    }

    .stat-box .stat-label {
        font-size: 0.93em;
        font-weight: 500;
        margin-bottom: auto;
        margin-top: 0;
        letter-spacing: 0.01em;
        min-height: 2.2em;
        width: 100%;
        text-align: center;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        line-height: 1.1;
        word-break: break-word;
    }

    .stat-box i,
    .stat-box .stat-value {
        margin-top: 2px;
    }

    .stat-box i {
        font-size: 1.2em;
        margin-bottom: 2px;
    }

    .stat-box .stat-value {
        font-size: 1.15em;
        font-weight: bold;
        margin-bottom: 0px;
        margin-top: 2px;
    }

    .btn-interview-sm {
        font-size: 0.85em;
        padding: 2px 10px;
        line-height: 1.1;
        height: 26px;
        min-width: 70px;
    }
</style>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1 class="m-0 text-primary"><i class="fas fa-briefcase me-2"></i><?= htmlspecialchars($job['job_title']) ?></h1>
                    <small class="text-muted">for <?= htmlspecialchars($job['client_name']) ?></small>
                </div>
                <div class="col-sm-4 text-end">
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <!-- Left Column: Main Details -->
            <div class="col-lg-8">
                <!-- Job Details Card -->
                <div class="card card-outline card-primary mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle me-2"></i>Job Details</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">Status</dt>
                            <dd class="col-sm-9"><span class="badge bg-info"><?= htmlspecialchars($job['status']) ?></span></dd>

                            <dt class="col-sm-3">Categories</dt>
                            <dd class="col-sm-9">
                                <?php if ($categories): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($cat['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-3">Description</dt>
                            <dd class="col-sm-9">
                                <div class="p-2 bg-light rounded">
                                    <?= !empty($job['job_description']) ? nl2br(htmlspecialchars($job['job_description'])) : '<span class="text-muted">No description provided.</span>' ?>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>

                <!-- Candidate Workflow Tabs -->
                <div class="card mb-4">
                    <div class="card-header pb-0 pt-2">
                        <h3 class="card-title mb-0"><i class="fas fa-users me-2"></i>Candidate Workflow</h3>
                    </div>
                    <div class="card-body pt-3 pb-0">
                        <?php
                        // Count candidates per tab
                        $tab_counts = [];
                        foreach ($status_groups as $key => $group) {
                            $tab_counts[$key] = count($tab_candidates[$key]);
                        }
                        // Find first tab with candidates, else first tab
                        $first_tab = array_key_first($status_groups);
                        foreach ($status_groups as $key => $group) {
                            if ($tab_counts[$key] > 0) {
                                $first_tab = $key;
                                break;
                            }
                        }
                        ?>
                        <ul class="nav nav-tabs mb-3" id="candidateWorkflowTabs" role="tablist">
                            <?php $tab_idx = 0;
                            foreach ($status_groups as $key => $group): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link<?= $key === $first_tab ? ' active' : '' ?> d-flex align-items-center gap-2" id="tab-<?= $key ?>" data-bs-toggle="tab" data-bs-target="#tab-pane-<?= $key ?>" type="button" role="tab" aria-controls="tab-pane-<?= $key ?>" aria-selected="<?= $key === $first_tab ? 'true' : 'false' ?>">
                                        <span class="badge <?= $group['bg'] ?> me-1"><i class="<?= $group['icon'] ?>"></i></span>
                                        <span><?= htmlspecialchars($group['label']) ?></span>
                                        <span class="badge bg-secondary ms-1"><?= $tab_counts[$key] ?></span>
                                    </button>
                                </li>
                            <?php $tab_idx++;
                            endforeach; ?>
                        </ul>
                        <div class="tab-content" id="candidateWorkflowTabContent">
                            <?php foreach ($status_groups as $key => $group): ?>                                <div class="tab-pane fade<?= $key === $first_tab ? ' show active' : '' ?>" id="tab-pane-<?= $key ?>" role="tabpanel" aria-labelledby="tab-<?= $key ?>">
                                    <?php if ($key === 'interviewed'): ?>
                                        <!-- Special handling for Interviewed tab -->
                                        <?php if (count($tab_candidates[$key])): ?>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Workflow Status</th>
                                                            <th>Details</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tab_candidates[$key] as $candidate): ?>
                                                            <?php 
                                                                // Check if this candidate has interview data directly in the record
                                                                $interview = null;
                                                                if (isset($candidate['interview_id'])) {
                                                                    $interview = $candidate;
                                                                } else {
                                                                    // Fetch interview details if not present in the record
                                                                    $interview = get_latest_interview($db, $candidate['presentation_id']);
                                                                }
                                                            ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($candidate['name']) ?></td>
                                                                <td>
                                                                    <span class="badge <?= $group['bg'] ?> text-white">
                                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $candidate['workflow_status']))) ?>
                                                                    </span>
                                                                </td>

                                                                <td>
                                                                    <?php if ($interview): ?>
                                                                        <div class="interview-summary-box p-2 mb-1 bg-light border rounded">
                                                                            <div class="d-flex flex-column">
                                                                                <div>
                                                                                    <strong><?= date('M d, Y h:i A', strtotime($interview['scheduled_at'])) ?></strong>
                                                                                </div>
                                                                                <div>
                                                                                    <?= htmlspecialchars($interview['platform'] ?? 'N/A') ?> 
                                                                                    <?php if (isset($interview['timezone']) && $interview['timezone']): ?> | <?= htmlspecialchars($interview['timezone']) ?><?php endif; ?>
                                                                                    <?php if (isset($interview['meeting_link']) && $interview['meeting_link']): ?> | <a href="<?= htmlspecialchars($interview['meeting_link']) ?>" target="_blank">Link</a><?php endif; ?>
                                                                                    <?php if (isset($interview['duration_minutes']) && $interview['duration_minutes']): ?> | <?= (int)$interview['duration_minutes'] ?> min<?php endif; ?>
                                                                                </div>                                                                                <?php if (isset($interview['note']) && $interview['note']): ?>
                                                                                    <div class="mt-1 text-muted small">
                                                                                        <strong>Note:</strong> <?= htmlspecialchars($interview['note']) ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <em class="text-muted">No interview information available</em>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-xs btn-info view-candidate-btn" data-id="<?= $candidate['id'] ?>" title="View Candidate Details"><i class="fas fa-eye"></i></button>
                                                                    <?php if (has_permission('operations-workflow-manage')): ?>
                                                                        <?php if (!$interview): ?>
                                                                            <button class="btn btn-xs btn-primary schedule-interview-btn" data-presentation-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>" title="Schedule Interview"><i class="fas fa-calendar-plus"></i></button>
                                                                        <?php elseif ($interview['status'] === 'scheduled'): ?>
                                                                            <button class="interview-action-btn btn btn-xs btn-success" data-action="complete" data-id="<?= $interview['id'] ?>" title="Mark as Completed"><i class="fas fa-check"></i></button>
                                                                            <button class="interview-action-btn btn btn-xs btn-danger" data-action="cancel" data-id="<?= $interview['id'] ?>" title="Cancel Interview"><i class="fas fa-times"></i></button>
                                                                            <button class="btn btn-xs btn-warning reschedule-interview-btn" data-interview-id="<?= $interview['id'] ?>" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Reschedule"><i class="fas fa-edit"></i></button>
                                                                        <?php elseif ($interview['status'] === 'completed'): ?>
                                                                            <button class="btn btn-xs btn-primary schedule-interview-btn" data-presentation-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>" title="Schedule Another"><i class="fas fa-calendar-plus"></i></button>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-light border text-muted">No candidates have been interviewed yet.</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Standard tabs handling -->
                                        <?php if (count($tab_candidates[$key])): ?>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Workflow Status</th>
                                                            <?php if ($key === 'approved' || $key === 'rejected'): ?><th>By</th><?php endif; ?>
                                                            <th>Internal Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($key === 'approved' || $key === 'rejected') {
                                                            $approver_groups = group_by_approver($tab_candidates[$key], $key);
                                                            $approver_labels = [
                                                                'manager' => ['Manager', 'bg-primary'],
                                                                'client' => ['Client', 'bg-info'],
                                                                'candidate' => ['Candidate', 'bg-success'],
                                                                'other' => ['Other', 'bg-secondary']
                                                            ];
                                                            foreach (['manager', 'client', 'candidate', 'other'] as $who) {
                                                                if (count($approver_groups[$who])) {
                                                                    echo '<tr class="table-group-row"><td colspan="5"><span class="badge ' . $approver_labels[$who][1] . '">Approved/Rejected by ' . $approver_labels[$who][0] . '</span></td></tr>';
                                                                    foreach ($approver_groups[$who] as $candidate) {
                                                                        $showManagerActions = ($key === 'approved' && strtolower(trim(str_replace([' ', '-'], ['_', '_'], $candidate['workflow_status']))) === 'manager_review');
                                                        ?>
                                                                        <tr>
                                                                            <td><?= htmlspecialchars($candidate['name']) ?></td>
                                                                            <td><span class="badge <?= $group['bg'] ?> text-white"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $candidate['workflow_status']))) ?></span></td>
                                                                            <td><?= $approver_labels[$who][0] ?></td>
                                                                            <td><span class="badge <?= getStatusBadgeClass($candidate['internal_status']) ?>"><?= htmlspecialchars($candidate['internal_status'] ?: 'N/A') ?></span></td>
                                                                            <td>
                                                                                <button class="btn btn-xs btn-info view-candidate-btn" data-id="<?= $candidate['id'] ?>" title="View Candidate Details"><i class="fas fa-eye"></i></button>
                                                                                <?php if ($showManagerActions && has_permission('operations-workflow-approve')): ?>
                                                                                    <button class="btn btn-xs btn-success action-btn" data-action="approve" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Approve"><i class="fas fa-check"></i></button>
                                                                                    <button class="btn btn-xs btn-danger action-btn" data-action="reject" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Reject"><i class="fas fa-times"></i></button>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                        </tr>
                                                                <?php
                                                                    }
                                                                }
                                                            }
                                                        } else {
                                                            foreach ($tab_candidates[$key] as $candidate) {
                                                                ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($candidate['name']) ?></td>
                                                                    <td><span class="badge <?= $group['bg'] ?> text-white"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $candidate['workflow_status']))) ?></span></td>
                                                                    <td><span class="badge <?= getStatusBadgeClass($candidate['internal_status']) ?>"><?= htmlspecialchars($candidate['internal_status'] ?: 'N/A') ?></span></td>
                                                                    <td>
                                                                        <button class="btn btn-xs btn-info view-candidate-btn" data-id="<?= $candidate['id'] ?>" title="View Candidate Details"><i class="fas fa-eye"></i></button>
                                                                        <?php if (has_permission('operations-workflow-approve') && $key === 'suggested'): ?>
                                                                            <button class="btn btn-xs btn-success action-btn" data-action="approve" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Approve"><i class="fas fa-check"></i></button>
                                                                            <button class="btn btn-xs btn-danger action-btn" data-action="reject" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Reject"><i class="fas fa-times"></i></button>
                                                                        <?php endif; ?>
                                                                    </td>                                                                </tr>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-light border text-muted">No candidates in this status.</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- End Candidate Workflow Tabs -->
            </div>

            <!-- Right Column: Actions & Meta -->
            <div class="col-lg-4">
                <!-- Actions Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-cogs me-2"></i>Actions</h3>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <?php if (has_permission('operations-workflow-manage')): ?>
                            <a href="suggest_candidates.php?id=<?= $id ?>" class="btn btn-success"><i class="fas fa-user-plus me-1"></i> Suggest Candidates</a>
                        <?php endif; ?>
                        <?php if (has_permission('operations-jobrequests-manage')): ?>
                            <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit me-1"></i> Edit Request</a>
                            <a href="delete.php?id=<?= $id ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this job request?');"><i class="fas fa-trash me-1"></i> Delete Request</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- General Statistics Card -->
                <?php
                // Statistics
                $created_at = strtotime($job['created_at']);
                $now = time();
                $days_open = max(1, floor(($now - $created_at) / 86400));
                $total_candidates = count($related_candidates);
                $proposed = 0;
                $rejected = 0;
                $approved = 0;
                foreach ($related_candidates as $c) {
                    $status = strtolower($c['workflow_status']);
                    if ($status === 'proposed') $proposed++;
                    elseif ($status === 'rejected') $rejected++;
                    elseif ($status === 'manager_review' || $status === 'client_review' || $status === 'approved' || $status === 'hired') $approved++;
                }
                ?>
                <div class="card mb-3 shadow-sm border-0 bg-gradient-light">
                    <div class="card-header pb-2 pt-2">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Job Statistics</h5>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div class="d-flex flex-row flex-nowrap gap-2 justify-content-between align-items-end overflow-auto">
                            <div class="stat-box text-center bg-primary text-white">
                                <div class="stat-label">Day(s) Open</div>
                                <i class="fas fa-calendar-day"></i>
                                <div class="stat-value"><?= $days_open ?></div>
                            </div>
                            <div class="stat-box text-center bg-info text-white">
                                <div class="stat-label">Total Proposed</div>
                                <i class="fas fa-users"></i>
                                <div class="stat-value"><?= $total_candidates ?></div>
                            </div>
                            <div class="stat-box text-center bg-success text-white">
                                <div class="stat-label">Approved</div>
                                <i class="fas fa-check-circle"></i>
                                <div class="stat-value"><?= $approved ?></div>
                            </div>
                            <div class="stat-box text-center bg-warning text-dark">
                                <div class="stat-label">Awaiting Review</div>
                                <i class="fas fa-hourglass-half"></i>
                                <div class="stat-value"><?= $proposed ?></div>
                            </div>
                            <div class="stat-box text-center bg-danger text-white">
                                <div class="stat-label">Rejected</div>
                                <i class="fas fa-times-circle"></i>
                                <div class="stat-value"><?= $rejected ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <style>
                    .stat-box .stat-label {
                        font-size: 0.93em;
                        font-weight: 500;
                        margin-bottom: auto;
                        margin-top: 0;
                        letter-spacing: 0.01em;
                        min-height: 2.2em;
                        width: 100%;
                        text-align: center;
                        display: flex;
                        align-items: flex-start;
                        justify-content: center;
                        line-height: 1.1;
                        word-break: break-word;
                    }

                    .stat-box i {
                        font-size: 1.2em;
                        margin-bottom: 2px;
                        margin-top: 2px;
                    }

                    .stat-box .stat-value {
                        font-size: 1.15em;
                        font-weight: bold;
                        margin-bottom: 0px;
                        margin-top: 2px;
                    }
                </style>

                <!-- Meta Info Card -->
                <div class="card mb-4">
                    <div class="card-header pb-2 pt-2">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Job Meta Info</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-user-tie me-2 text-muted"></i><strong>Client:</strong> <?= htmlspecialchars($job['client_name']) ?></li>
                            <li class="mb-2"><i class="fas fa-user-plus me-2 text-muted"></i><strong>Created By:</strong> <?= htmlspecialchars($job['creator_name']) ?></li>
                            <li><i class="fas fa-clock me-2 text-muted"></i><strong>Created At:</strong> <?= date('M d, Y h:i A', strtotime($job['created_at'])) ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Process Card (with interview button and modal) -->
        <?php
        // Client süreci için: client_review aşamasındaki adaylar
        $client_review_candidates = array_filter($related_candidates, function ($c) {
            return strtolower(trim(str_replace([' ', '-'], ['_', '_'], $c['workflow_status']))) === 'client_review';
        });
        ?>
        <?php if (count($client_review_candidates)): ?>
            <div class="card mb-4">
                <div class="card-header pb-0 pt-2">
                    <h3 class="card-title mb-0"><i class="fas fa-user-tie me-2"></i>Client Process</h3>
                </div>
                <div class="card-body pt-3 pb-0">
                    <?php if (count($client_review_candidates)): ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Interview</th>
                                        <th>Feedback</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($client_review_candidates as $candidate): ?>
                                        <?php $interview = get_latest_interview($db, $candidate['presentation_id']); ?>
                                        <tr>
                                            <td><?= htmlspecialchars($candidate['name']) ?></td>
                                            <td>
                                                <?php if ($interview): ?>
                                                    <div class="interview-summary-box p-2 mb-1 bg-light border rounded">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?= htmlspecialchars($interview['scheduled_at']) ?></strong> | <?= htmlspecialchars($interview['platform']) ?> | <?= htmlspecialchars($interview['timezone']) ?>
                                                                <?php if ($interview['meeting_link']): ?> | <a href="<?= htmlspecialchars($interview['meeting_link']) ?>" target="_blank">Link</a><?php endif; ?>
                                                                <?php if ($interview['duration_minutes']): ?> | <?= (int)$interview['duration_minutes'] ?> min<?php endif; ?>
                                                                    <?php if ($interview['topics']): ?> <br><small><?= htmlspecialchars($interview['topics']) ?></small><?php endif; ?>
                                                            </div>
                                                            <div class="ms-2 flex-shrink-0">
                                                                <?php if ($interview['status'] === 'scheduled'): ?>
                                                                    <button class="interview-action-btn btn btn-danger btn-interview-sm" data-action="cancel" data-id="<?= $interview['id'] ?>" title="Cancel Interview"><i class="fas fa-times me-1"></i>Cancel</button>
                                                                    <button class="interview-action-btn btn btn-success btn-interview-sm" data-action="complete" data-id="<?= $interview['id'] ?>" title="Mark as Completed"><i class="fas fa-check me-1"></i>Complete</button>
                                                                <?php elseif ($interview['status'] === 'completed'): ?>
                                                                    <span class="badge bg-success">Completed</span>
                                                                <?php elseif ($interview['status'] === 'cancelled'): ?>
                                                                    <span class="badge bg-danger">Cancelled</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <button class="btn btn-xs btn-outline-primary schedule-interview-btn" data-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>">Schedule Interview</button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($interview && $interview['status'] === 'completed' && !empty($interview['note'])): ?>
                                                    <span class="badge bg-info">Feedback: <?= htmlspecialchars($interview['note']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-xs btn-info view-candidate-btn" data-id="<?= $candidate['id'] ?>" title="View Candidate Details"><i class="fas fa-eye"></i></button>
                                                <button class="btn btn-xs btn-success client-action-btn" data-action="approve" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Client Approve"><i class="fas fa-check"></i></button>
                                                <button class="btn btn-xs btn-danger client-action-btn" data-action="reject" data-presentation-id="<?= $candidate['presentation_id'] ?>" title="Client Reject"><i class="fas fa-times"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border text-muted">No candidates in client review process.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <!-- End Client Process Card -->

        <!-- Candidate Process Card (Client Approved/Job Offered/Hired) -->
        <?php
        $candidate_process_candidates = array_filter($related_candidates, function ($c) {
            $status = strtolower(trim(str_replace([' ', '-'], ['_', '_'], $c['workflow_status'])));
            return in_array($status, ['client_approved', 'job_offered', 'hired']);
        });
        ?>
        <?php if (count($candidate_process_candidates)): ?>
            <div class="card mb-4">
                <div class="card-header pb-0 pt-2">
                    <h3 class="card-title mb-0"><i class="fas fa-user-check me-2"></i>Candidate Process</h3>
                </div>
                <div class="card-body pt-3 pb-0">
                    <div class="table-responsive mb-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Workflow Status</th>
                                    <th>Internal Status</th>
                                    <th>Step</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidate_process_candidates as $candidate):
                                    $status = strtolower(trim(str_replace([' ', '-'], ['_', '_'], $candidate['workflow_status'])));
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($candidate['name']) ?></td>
                                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $candidate['workflow_status']))) ?></span></td>
                                        <td><span class="badge <?= getStatusBadgeClass($candidate['internal_status']) ?>"><?= htmlspecialchars($candidate['internal_status'] ?: 'N/A') ?></span></td>
                                        <td>
                                            <?php if ($status === 'client_approved'): ?>
                                                <span class="badge bg-info">Client Approved</span>
                                            <?php elseif ($status === 'job_offered'): ?>
                                                <span class="badge bg-warning text-dark">Job Offered</span>
                                            <?php elseif ($status === 'hired'): ?>
                                                <span class="badge bg-success">Hired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-xs btn-info view-candidate-btn" data-id="<?= $candidate['id'] ?>" title="View Candidate Details"><i class="fas fa-eye"></i></button>
                                            <?php if ($status === 'client_approved' && has_permission('operations-workflow-approve')): ?>
                                                <button class="btn btn-xs btn-primary offer-job-btn" data-presentation-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>">Offer Job</button>
                                                <button class="btn btn-xs btn-success hire-btn" data-presentation-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>">Hire</button>
                                            <?php elseif ($status === 'job_offered'): ?>
                                                <button class="btn btn-xs btn-success candidate-action-btn" data-action="candidate_approve" data-presentation-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>">Approve</button>
                                                <button class="btn btn-xs btn-danger candidate-action-btn" data-action="candidate_reject" data-presentation-id="<?= $candidate['presentation_id'] ?>" data-name="<?= htmlspecialchars($candidate['name']) ?>">Reject</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- History/Logs Timeline -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history me-2"></i>Request History</h3>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush compact-history">
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log):
                            $icon_class = 'fas fa-info-circle text-primary'; // Default
                            $action_lower = strtolower($log['action']);
                            if (str_contains($action_lower, 'created')) $icon_class = 'fas fa-plus-circle text-success';
                            if (str_contains($action_lower, 'updated')) $icon_class = 'fas fa-edit text-warning';
                            if (str_contains($action_lower, 'status')) $icon_class = 'fas fa-exchange-alt text-info';
                            if (str_contains($action_lower, 'proposed')) $icon_class = 'fas fa-users text-purple';
                            if (str_contains($action_lower, 'approved')) $icon_class = 'fas fa-check-circle text-success';
                            if (str_contains($action_lower, 'rejected')) $icon_class = 'fas fa-times-circle text-danger';
                            if (str_contains($action_lower, 'note')) $icon_class = 'fas fa-sticky-note text-secondary';
                        ?>
                            <li class="list-group-item py-1 px-2 d-flex align-items-center justify-content-between">
                                <span>
                                    <i class="<?= $icon_class ?> me-2"></i>
                                    <strong><?= htmlspecialchars($log['action']) ?></strong>
                                    <?php if (!empty($log['note'])): ?>
                                        <span class="badge bg-light text-dark border ms-2">Note: <?= htmlspecialchars(mb_strimwidth($log['note'], 0, 60, '...')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-muted small">
                                    <i class="fas fa-user me-1"></i><?= htmlspecialchars($log['user_name']) ?>
                                    &bull; <i class="fas fa-clock me-1"></i><?= date('m/d/Y H:i', strtotime($log['created_at'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-muted">No history logs found for this job request.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Candidate Detail Modal -->
<div class="modal fade" id="candidateDetailModal" tabindex="-1" aria-labelledby="candidateDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="candidateDetailModalLabel">Candidate Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center" id="modal-loader">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="candidate-details-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="actionForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="presentationId" name="presentation_id">
                    <input type="hidden" id="actionType" name="action">
                    <p id="action-confirmation-text"></p>
                    <div class="mb-3">
                        <label for="note" class="form-label">Note (Required)</label>
                        <textarea class="form-control" id="note" name="note" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmActionButton">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Interview Modal -->
<div class="modal fade" id="interviewModal" tabindex="-1" aria-labelledby="interviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="interviewForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="interviewModalLabel">Schedule Interview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="presentation_id" id="interviewPresentationId">
                    <div class="mb-2">
                        <label for="scheduled_at" class="form-label">Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label for="platform" class="form-label">Platform</label>
                        <select name="platform" id="platform" class="form-select" required>
                            <option value="">Select Platform</option>
                            <option value="Zoom">Zoom</option>
                            <option value="Google Meet">Google Meet</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="meeting_link" class="form-label">Meeting Link</label>
                        <input type="text" name="meeting_link" id="meeting_link" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label for="timezone" class="form-label">Timezone</label>
                        <select name="timezone" id="timezone" class="form-select" required>
                            <option value="UTC+0">UTC (UTC+0)</option>
                            <option value="UTC-12">UTC-12</option>
                            <option value="UTC-11">UTC-11</option>
                            <option value="UTC-10">UTC-10 (HST)</option>
                            <option value="UTC-9">UTC-9 (AKST)</option>
                            <option value="UTC-8">UTC-8 (PST)</option>
                            <option value="UTC-7">UTC-7 (MST)</option>
                            <option value="UTC-6">UTC-6 (CST)</option>
                            <option value="UTC-5">UTC-5 (EST)</option>
                            <option value="UTC-4">UTC-4 (AST/EDT)</option>
                            <option value="UTC-3">UTC-3 (ART/BRT)</option>
                            <option value="UTC-2">UTC-2</option>
                            <option value="UTC-1">UTC-1 (AZOT)</option>
                            <option value="UTC+1">UTC+1 (CET/WAT)</option>
                            <option value="UTC+2">UTC+2 (EET/SAST)</option>
                            <option value="UTC+3">UTC+3 (EEST/MSK/Europe/Istanbul)</option>
                            <option value="UTC+3:30">UTC+3:30 (IRST)</option>
                            <option value="UTC+4">UTC+4 (GST/AZT)</option>
                            <option value="UTC+4:30">UTC+4:30 (AFT)</option>
                            <option value="UTC+5">UTC+5 (PKT/YEKST)</option>
                            <option value="UTC+5:30">UTC+5:30 (IST)</option>
                            <option value="UTC+5:45">UTC+5:45 (NPT)</option>
                            <option value="UTC+6">UTC+6 (BST/OMST)</option>
                            <option value="UTC+6:30">UTC+6:30 (CCT/MMT)</option>
                            <option value="UTC+7">UTC+7 (ICT/WIB)</option>
                            <option value="UTC+8">UTC+8 (CST/SGT/AWST)</option>
                            <option value="UTC+9">UTC+9 (JST/KST)</option>
                            <option value="UTC+9:30">UTC+9:30 (ACST)</option>
                            <option value="UTC+10">UTC+10 (AEST)</option>
                            <option value="UTC+10:30">UTC+10:30 (LHST)</option>
                            <option value="UTC+11">UTC+11 (SBT/VUT)</option>
                            <option value="UTC+12">UTC+12 (NZST/FJT)</option>
                            <option value="UTC+13">UTC+13 (NZDT/TKT)</option>
                            <option value="UTC+14">UTC+14 (LINT)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                        <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="1">
                    </div>
                    <div class="mb-2">
                        <label for="topics" class="form-label">Interview Topics/Notes</label>
                        <textarea name="topics" id="topics" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Interview</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Offer Job Modal -->
<div class="modal fade" id="offerJobModal" tabindex="-1" aria-labelledby="offerJobModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="offerJobForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="offerJobModalLabel">Offer Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="presentation_id" id="offerJobPresentationId">
                    <div class="mb-2">
                        <label for="offer_note" class="form-label">Offer Details/Note</label>
                        <textarea name="note" id="offer_note" class="form-control" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Offer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $root_path . 'components/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function() {
        // View Candidate Details Modal
        $('.view-candidate-btn').on('click', function() {
            const candidateId = $(this).data('id');
            const modal = new bootstrap.Modal(document.getElementById('candidateDetailModal'));

            $('#candidate-details-content').html('');
            $('#modal-loader').show();
            modal.show();

            $.ajax({
                url: '../candidates/get_candidate_details_ajax.php',
                type: 'GET',
                data: {
                    id: candidateId
                },
                success: function(response) {
                    if (response.success) {
                        const candidate = response.data;
                        let categoriesHtml = candidate.categories.length > 0 ?
                            candidate.categories.map(cat => `<span class="badge bg-secondary me-1">${cat.name}</span>`).join('') :
                            '<span class="text-muted">N/A</span>';

                        let detailsHtml = `
                        <dl class="row">
                            <dt class="col-sm-3">Name</dt><dd class="col-sm-9">${candidate.name}</dd>
                            <dt class="col-sm-3">Email</dt><dd class="col-sm-9">${candidate.email || 'N/A'}</dd>
                            <dt class="col-sm-3">Phone</dt><dd class="col-sm-9">${candidate.phone || 'N/A'}</dd>
                            <dt class="col-sm-3">Internal Status</dt><dd class="col-sm-9"><span class="badge bg-info">${candidate.internal_status || 'N/A'}</span></dd>
                            <dt class="col-sm-3">External Status</dt><dd class="col-sm-9"><span class="badge bg-primary">${candidate.external_status || 'N/A'}</span></dd>
                            <dt class="col-sm-3">Categories</dt><dd class="col-sm-9">${categoriesHtml}</dd>
                            <dt class="col-sm-3">CV</dt><dd class="col-sm-9">${candidate.cv_path ? `<a href="../../../${candidate.cv_path}" target="_blank">View CV</a>` : 'N/A'}</dd>
                            <dt class="col-sm-3">Created At</dt><dd class="col-sm-9">${new Date(candidate.created_at).toLocaleDateString()}</dd>
                        </dl>
                        <h5>Notes</h5>
                        <div class="p-2 bg-light rounded">${candidate.notes ? candidate.notes.replace(/\n/g, '<br>') : 'No notes.'}</div>
                    `;
                        $('#candidate-details-content').html(detailsHtml);
                    } else {
                        $('#candidate-details-content').html(`<div class="alert alert-danger">${response.message}</div>`);
                    }
                },
                error: function() {
                    $('#candidate-details-content').html('<div class="alert alert-danger">Error fetching candidate details.</div>');
                },
                complete: function() {
                    $('#modal-loader').hide();
                }
            });
        });

        // Action Modal (Approve/Reject)
        const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
        $('.action-btn').on('click', function() {
            const presentationId = $(this).data('presentation-id');
            const action = $(this).data('action');

            $('#presentationId').val(presentationId);
            $('#actionType').val(action);

            if (action === 'approve') {
                $('#actionModalLabel').text('Approve Candidate');
                $('#action-confirmation-text').text('Are you sure you want to approve this candidate for the next step?');
                $('#confirmActionButton').removeClass('btn-danger').addClass('btn-success');
            } else {
                $('#actionModalLabel').text('Reject Candidate');
                $('#action-confirmation-text').text('Are you sure you want to reject this candidate?');
                $('#confirmActionButton').removeClass('btn-success').addClass('btn-danger');
            }

            actionModal.show();
        });

        // Handle Action Form Submission
        $('#actionForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitButton = $('#confirmActionButton');
            submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...');

            $.ajax({
                url: '../workflow/update_status_ajax.php',
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        showSuccessAlert('Success', response.message);
                        actionModal.hide();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showErrorAlert('Error', response.message || 'An unknown error occurred.');
                    }
                },
                error: function() {
                    showErrorAlert('Error', 'An error occurred while communicating with the server.');
                },
                complete: function() {
                    submitButton.prop('disabled', false).text('Submit');
                }
            });
        });

        // Client Action Buttons (Approve/Reject)
        $('.client-action-btn').on('click', function() {
            const presentationId = $(this).data('presentation-id');
            const action = $(this).data('action');
            const candidateName = $(this).closest('tr').find('td:first').text();

            $('#presentationId').val(presentationId);
            $('#actionType').val(action);

            if (action === 'approve') {
                $('#actionModalLabel').text('Client Approve Candidate');
                $('#action-confirmation-text').html(`Are you sure you want to approve <strong>${candidateName}</strong> for the next step?`);
                $('#confirmActionButton').removeClass('btn-danger').addClass('btn-success');
            } else {
                $('#actionModalLabel').text('Client Reject Candidate');
                $('#action-confirmation-text').html(`Are you sure you want to reject <strong>${candidateName}</strong>?`);
                $('#confirmActionButton').removeClass('btn-success').addClass('btn-danger');
            }

            actionModal.show();
        });

        // Open interview modal on button click
        $('.schedule-interview-btn').on('click', function() {
            const presentationId = $(this).data('id');
            const candidateName = $(this).data('name');
            $('#interviewPresentationId').val(presentationId);
            $('#interviewModalLabel').text('Schedule Interview for ' + candidateName);
            $('#interviewForm')[0].reset();
            var modal = new bootstrap.Modal(document.getElementById('interviewModal'));
            modal.show();
        });
        // Interview Cancel/Complete
        $(document).on('click', '.interview-action-btn', function() {
            var btn = $(this);
            var action = btn.data('action');
            var interviewId = btn.data('id');
            Swal.fire({
                title: (action === 'cancel' ? 'Cancel Interview' : 'Complete Interview'),
                input: 'textarea',
                inputLabel: 'Note (required)',
                inputValidator: value => !value && 'Note is required!',
                showCancelButton: true,
                confirmButtonText: 'Submit',
                preConfirm: (note) => {
                    return $.post('interview_actions_ajax.php', {
                            interview_id: interviewId,
                            action: action,
                            note: note
                        })
                        .then(response => {
                            if (!response.success) throw new Error(response.message);
                            return response;
                        })
                        .catch(e => {
                            Swal.showValidationMessage(e.message);
                        });
                }
            }).then(result => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire('Success', result.value.message, 'success').then(() => location.reload());
                }
            });
        });
        // AJAX submit for interview form
        $('#interviewForm').off('submit').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var data = form.serialize();
            var presentationId = $('#interviewPresentationId').val();
            $.post('save_interview_ajax.php', data, function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message || 'Error saving interview.', 'error');
                }
            }, 'json');
        });

        // Timezone selection handling
        $('#timezone').on('change', function() {
            if ($(this).val() === 'Other') {
                $('#other-timezone-container').show();
                $('#other_timezone').prop('required', true);
            } else {
                $('#other-timezone-container').hide();
                $('#other_timezone').prop('required', false);
            }
        });

        // Offer Job Modal
        $('.offer-job-btn').on('click', function() {
            const presentationId = $(this).data('presentation-id');
            const candidateName = $(this).data('name');
            $('#offerJobPresentationId').val(presentationId);
            $('#offerJobModalLabel').text('Offer Job to ' + candidateName);
            $('#offerJobForm')[0].reset();
            var modal = new bootstrap.Modal(document.getElementById('offerJobModal'));
            modal.show();
        });
        // Offer Job Form Submit
        $('#offerJobForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var data = form.serialize() + '&action=job_offer';
            $.post('../workflow/update_status_ajax.php', data, function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message || 'Error sending job offer.', 'error');
                }
            }, 'json');
        });
        // Hire Button
        $('.hire-btn').on('click', function() {
            const presentationId = $(this).data('presentation-id');
            Swal.fire({
                title: 'Direct Hire',
                input: 'textarea',
                inputLabel: 'Note (required)',
                inputValidator: value => !value && 'Note is required!',
                showCancelButton: true,
                confirmButtonText: 'Hire',
                preConfirm: (note) => {
                    return $.post('../workflow/update_status_ajax.php', {
                            presentation_id: presentationId,
                            action: 'hire',
                            note: note
                        })
                        .then(response => {
                            if (!response.success) throw new Error(response.message);
                            return response;
                        })
                        .catch(e => {
                            Swal.showValidationMessage(e.message);
                        });
                }
            }).then(result => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire('Success', result.value.message, 'success').then(() => location.reload());
                }
            });
        });
        // Candidate Approve/Reject
        $('.candidate-action-btn').on('click', function() {
            const presentationId = $(this).data('presentation-id');
            const action = $(this).data('action');
            const candidateName = $(this).data('name');
            let actionLabel = action === 'candidate_approve' ? 'Approve Offer' : 'Reject Offer';
            let confirmBtn = action === 'candidate_approve' ? 'Approve' : 'Reject';
            Swal.fire({
                title: actionLabel,
                input: 'textarea',
                inputLabel: 'Note (required)',
                inputValidator: value => !value && 'Note is required!',
                showCancelButton: true,
                confirmButtonText: confirmBtn,
                preConfirm: (note) => {
                    return $.post('../workflow/update_status_ajax.php', {
                            presentation_id: presentationId,
                            action: action,
                            note: note
                        })
                        .then(response => {
                            if (!response.success) throw new Error(response.message);
                            return response;
                        })
                        .catch(e => {
                            Swal.showValidationMessage(e.message);
                        });
                }
            }).then(result => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire('Success', result.value.message, 'success').then(() => location.reload());
                }
            });
        });
    });
</script>