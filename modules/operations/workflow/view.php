<?php
/**
 * Operations Module - View Workflow Details
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-workflow-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT w.*, jr.job_title, c.name as client_name, cand.name as candidate_name, cand.email as candidate_email
    FROM job_candidate_presentations w
    LEFT JOIN job_requests jr ON w.job_request_id = jr.id
    LEFT JOIN clients c ON jr.client_id = c.id
    LEFT JOIN candidates cand ON w.candidate_id = cand.id
    WHERE w.id = ?');
$stmt->execute([$id]);
$wf = $stmt->fetch();
if (!$wf) {
    header('Location: index.php');
    exit;
}
// Fetch logs
$log_stmt = $db->prepare('SELECT l.*, u.name as user_name FROM job_candidate_logs l LEFT JOIN users u ON l.performed_by = u.id WHERE l.job_candidate_presentation_id = ? ORDER BY l.created_at ASC');
$log_stmt->execute([$id]);
$logs = $log_stmt->fetchAll();

$page_title = 'Workflow Details';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Workflow Details</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="index.php" class="btn btn-secondary">Back to List</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <h5>Client</h5>
                    <p><?= htmlspecialchars($wf['client_name']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Job Title</h5>
                    <p><?= htmlspecialchars($wf['job_title']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Candidate</h5>
                    <p><?= htmlspecialchars($wf['candidate_name']) ?> <br><small><?= htmlspecialchars($wf['candidate_email']) ?></small></p>
                </div>
                <div class="col-md-6">
                    <h5>Status</h5>
                    <p><?= htmlspecialchars($wf['status']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Presented At</h5>
                    <p><?= htmlspecialchars($wf['presented_at']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Updated At</h5>
                    <p><?= htmlspecialchars($wf['updated_at']) ?></p>
                </div>
                <div class="col-md-12">
                    <h5>Manager Approval</h5>
                    <p>
                        <?php if ($wf['manager_approved'] === null): ?>Pending<?php elseif ($wf['manager_approved']): ?>Approved<?php else: ?>Rejected<?php endif; ?>
                        <?php if ($wf['manager_rejected_reason']): ?><br><small class="text-danger">Reason: <?= htmlspecialchars($wf['manager_rejected_reason']) ?></small><?php endif; ?>
                    </p>
                </div>
                <div class="col-md-12">
                    <h5>Client Approval</h5>
                    <p>
                        <?php if ($wf['client_approved'] === null): ?>Pending<?php elseif ($wf['client_approved']): ?>Approved<?php else: ?>Rejected<?php endif; ?>
                        <?php if ($wf['client_rejected_reason']): ?><br><small class="text-danger">Reason: <?= htmlspecialchars($wf['client_rejected_reason']) ?></small><?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <!-- Dynamic Action Buttons by Role & Status -->
        <div class="mb-3">
        <?php
        // Example statuses: 'proposed', 'manager_review', 'client_review', 'approved', 'rejected'
        $user_roles = $_SESSION['user_roles'] ?? [];
        $status = $wf['status'];
        // Researcher: Suggest Candidates (only if status is 'proposed')
        if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || has_permission('operations-suggest-candidates')) {
            if ($status === 'proposed') {
                echo '<a href="../../job_requests/suggest_candidates.php?job_id=' . $wf['job_request_id'] . '" class="btn btn-success me-2">Suggest Candidates</a>';
            }
        }
        // Manager: Approve/Reject (only if status is 'manager_review')
        if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || has_permission('operations-workflow-approve')) {
            if ($status === 'manager_review') {
                echo '<a href="edit.php?id=' . $wf['id'] . '&action=approve" class="btn btn-primary me-2">Approve</a>';
                echo '<a href="edit.php?id=' . $wf['id'] . '&action=reject" class="btn btn-danger">Reject</a>';
            }
        }
        // Client: Approve/Reject (only if status is 'client_review')
        if ((isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || has_permission('operations-client-approve')) {
            if ($status === 'client_review') {
                echo '<a href="edit.php?id=' . $wf['id'] . '&action=approve" class="btn btn-primary me-2">Approve</a>';
                echo '<a href="edit.php?id=' . $wf['id'] . '&action=reject" class="btn btn-danger">Reject</a>';
            }
        }
        ?>
        </div>
        <div class="card">
            <div class="card-header"><strong>Workflow History</strong></div>
            <div class="card-body">
                <ul class="timeline list-unstyled">
                    <?php foreach ($logs as $log): ?>
                        <li class="mb-3 p-2 border rounded bg-light position-relative">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-primary me-2">#<?= htmlspecialchars($log['id']) ?></span>
                                <strong><?= htmlspecialchars($log['action']) ?></strong>
                                <span class="ms-auto small text-muted"><i class="fas fa-user me-1"></i><?= htmlspecialchars($log['user_name']) ?> | <i class="fas fa-clock me-1"></i><?= htmlspecialchars($log['created_at']) ?></span>
                            </div>
                            <div class="mb-1"><i class="fas fa-info-circle text-secondary me-1"></i><?= htmlspecialchars($log['details']) ?></div>
                            <?php if (!empty($log['note'])): ?>
                                <div class="mb-1"><i class="fas fa-sticky-note text-info me-1"></i><strong>Note:</strong> <?= nl2br(htmlspecialchars($log['note'])) ?></div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary add-note-btn" data-log-id="<?= $log['id'] ?>"><i class="fas fa-plus"></i> Add Note</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <!-- Not ekleme modalı -->
        <div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">Add Note to Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <form id="add-note-form">
                  <input type="hidden" name="log_id" id="note-log-id">
                  <div class="mb-3">
                    <label for="note-text" class="form-label">Note</label>
                    <textarea class="form-control" id="note-text" name="note" rows="3" required></textarea>
                  </div>
                  <button type="submit" class="btn btn-primary">Save Note</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <script>
        document.querySelectorAll('.add-note-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('note-log-id').value = this.dataset.logId;
                document.getElementById('note-text').value = '';
                var modal = new bootstrap.Modal(document.getElementById('addNoteModal'));
                modal.show();
            });
        });
        document.getElementById('add-note-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var logId = document.getElementById('note-log-id').value;
            var note = document.getElementById('note-text').value;
            fetch('add_note.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({log_id: logId, note: note})
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    location.reload();
                } else {
                    alert('Note could not be saved.');
                }
            });
        });
        </script>
    </div>
</div>
<?php
// DEBUG: Show user roles for troubleshooting
if (isset($_SESSION['user_roles'])) {
    echo '<div style="background:#ffe;border:1px solid #cc0;padding:8px;margin:8px 0;color:#333;font-size:14px;">';
    echo '<strong>DEBUG: User Roles:</strong> ' . implode(', ', $_SESSION['user_roles']);
    echo '</div>';
}
include $root_path . 'components/footer.php';
