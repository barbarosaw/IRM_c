<?php
/**
 * Operations Module - Workflow Step (Manager/Client Approval or Rejection)
 * Allows manager/client to approve/reject and add log note
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

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

// Role check (manager or client)
$is_manager = in_array('Manager', $_SESSION['user_roles'] ?? []);
$is_client = in_array('Client', $_SESSION['user_roles'] ?? []);
if (!$is_manager && !$is_client && !has_permission('operations-workflow-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $now = date('Y-m-d H:i:s');
    if ($is_manager && $wf['status'] === 'Manager Review') {
        if ($action === 'approve') {
            // Manager approves, move to client review
            $db->prepare('UPDATE job_candidate_presentations SET manager_approved=1, manager_approved_by=?, manager_approved_at=?, status=?, updated_at=? WHERE id=?')
                ->execute([$_SESSION['user_id'], $now, 'Client Review', $now, $id]);
            $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
                ->execute([$id, 'Manager Approved', 'Candidate approved by manager', $note, $_SESSION['user_id'], $now]);
        } elseif ($action === 'reject') {
            $db->prepare('UPDATE job_candidate_presentations SET manager_approved=0, manager_approved_by=?, manager_approved_at=?, manager_rejected_reason=?, status=?, updated_at=? WHERE id=?')
                ->execute([$_SESSION['user_id'], $now, $note, 'Rejected by Manager', $now, $id]);
            $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
                ->execute([$id, 'Manager Rejected', 'Candidate rejected by manager', $note, $_SESSION['user_id'], $now]);
        }
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    }
    if ($is_client && $wf['status'] === 'Client Review') {
        if ($action === 'approve') {
            $db->prepare('UPDATE job_candidate_presentations SET client_approved=1, client_approved_by=?, client_approved_at=?, status=?, updated_at=? WHERE id=?')
                ->execute([$_SESSION['user_id'], $now, 'Interview', $now, $id]);
            $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
                ->execute([$id, 'Client Approved', 'Candidate approved by client', $note, $_SESSION['user_id'], $now]);
        } elseif ($action === 'reject') {
            $db->prepare('UPDATE job_candidate_presentations SET client_approved=0, client_approved_by=?, client_approved_at=?, client_rejected_reason=?, status=?, updated_at=? WHERE id=?')
                ->execute([$_SESSION['user_id'], $now, $note, 'Rejected by Client', $now, $id]);
            $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
                ->execute([$id, 'Client Rejected', 'Candidate rejected by client', $note, $_SESSION['user_id'], $now]);
        }
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    }
}

$page_title = 'Workflow Step';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Workflow Step</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Back to Details</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Add Note (optional)</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <?php if ($is_manager && $wf['status'] === 'Manager Review'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        <?php elseif ($is_client && $wf['status'] === 'Client Review'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        <?php endif; ?>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
