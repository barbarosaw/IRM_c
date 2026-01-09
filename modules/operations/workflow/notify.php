<?php
/**
 * Operations Module - Manual Notification for Workflow
 * Allows sending manual notification to client or candidate and logs the action
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

if (!has_permission('operations-workflow-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = $_POST['recipient'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $now = date('Y-m-d H:i:s');
    if ($recipient === 'client') {
        // Log manual notification to client
        $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
            ->execute([$id, 'Notified Client', 'Manual notification sent to client', $note, $_SESSION['user_id'], $now]);
        $success = true;
    } elseif ($recipient === 'candidate') {
        // Log manual notification to candidate
        $db->prepare('INSERT INTO job_candidate_logs (job_candidate_presentation_id, action, details, note, performed_by, created_at) VALUES (?, ?, ?, ?, ?, ? )')
            ->execute([$id, 'Notified Candidate', 'Manual notification sent to candidate', $note, $_SESSION['user_id'], $now]);
        $success = true;
    } else {
        $errors[] = 'Please select a recipient.';
    }
}

$page_title = 'Send Notification';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Send Notification</h1>
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
                <?php if ($success): ?>
                    <div class="alert alert-success">Notification logged successfully.</div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Recipient</label>
                        <select name="recipient" class="form-select" required>
                            <option value="">Select Recipient</option>
                            <option value="client">Client</option>
                            <option value="candidate">Candidate</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Note (optional)</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Log Notification</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
