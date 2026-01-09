<?php
/**
 * Operations Module - Edit Job Request
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-jobrequests-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM job_requests WHERE id = ?');
$stmt->execute([$id]);
$job = $stmt->fetch();
if (!$job) {
    header('Location: index.php');
    exit;
}
// Fetch clients
$client_stmt = $db->query('SELECT id, name FROM clients ORDER BY name');
$clients = $client_stmt->fetchAll();
// Fetch categories
$cat_stmt = $db->query('SELECT * FROM categories ORDER BY name');
$categories = $cat_stmt->fetchAll();
// Fetch selected categories
$sel_cat_stmt = $db->prepare('SELECT category_id FROM job_request_categories WHERE job_request_id = ?');
$sel_cat_stmt->execute([$id]);
$selected_categories = $sel_cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $job_title = trim($_POST['job_title'] ?? '');
    $job_description = trim($_POST['job_description'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $category_ids = $_POST['categories'] ?? [];
    $note = trim($_POST['note'] ?? '');

    if (!$client_id) $errors[] = 'Client is required.';
    if (!$job_title) $errors[] = 'Job title is required.';
    if (!$note) $errors[] = 'Note is required.';

    if (empty($errors)) {
        $stmt = $db->prepare('UPDATE job_requests SET client_id=?, job_title=?, job_description=?, status=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$client_id, $job_title, $job_description, $status, $id]);
        // Update categories
        $db->prepare('DELETE FROM job_request_categories WHERE job_request_id=?')->execute([$id]);
        foreach ($category_ids as $cat_id) {
            $db->prepare('INSERT INTO job_request_categories (job_request_id, category_id) VALUES (?, ?)')->execute([$id, $cat_id]);
        }
        // Log update with note
        $db->prepare('INSERT INTO job_request_logs (job_request_id, action, note, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$id, 'Updated', $note, $_SESSION['user_id']]);
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    }
}

$page_title = 'Edit Job Request';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Edit Job Request</h1>
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
                    <div class="col-md-6">
                        <label class="form-label">Client *</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($job['client_id'] == $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Job Title *</label>
                        <input type="text" name="job_title" class="form-control" required value="<?= htmlspecialchars($job['job_title']) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Job Description</label>
                        <textarea name="job_description" class="form-control"><?= htmlspecialchars($job['job_description']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="New" <?= ($job['status'] == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Research" <?= ($job['status'] == 'Research') ? 'selected' : '' ?>>Research</option>
                            <option value="Candidates Proposed" <?= ($job['status'] == 'Candidates Proposed') ? 'selected' : '' ?>>Candidates Proposed</option>
                            <option value="Manager Review" <?= ($job['status'] == 'Manager Review') ? 'selected' : '' ?>>Manager Review</option>
                            <option value="Client Review" <?= ($job['status'] == 'Client Review') ? 'selected' : '' ?>>Client Review</option>
                            <option value="Interview" <?= ($job['status'] == 'Interview') ? 'selected' : '' ?>>Interview</option>
                            <option value="Closed" <?= ($job['status'] == 'Closed') ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categories</label>
                        <select name="categories[]" class="form-select" multiple>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $selected_categories) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Note *</label>
                        <textarea name="note" class="form-control" required></textarea>
                        <div class="form-text">Please enter a note for this operation. This will be logged in the job request history.</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Update Job Request</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
