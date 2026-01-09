<?php
/**
 * Operations Module - Add Job Request
 * Modern, user-friendly job request add form
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-jobrequests-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

// Fetch clients
$client_stmt = $db->query('SELECT id, name FROM clients ORDER BY name');
$clients = $client_stmt->fetchAll();
// Fetch categories
$cat_stmt = $db->query('SELECT * FROM categories ORDER BY name');
$categories = $cat_stmt->fetchAll();

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
        $stmt = $db->prepare('INSERT INTO job_requests (client_id, job_title, job_description, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$client_id, $job_title, $job_description, $status, $_SESSION['user_id']]);
        $job_request_id = $db->lastInsertId();
        // Categories
        foreach ($category_ids as $cat_id) {
            $db->prepare('INSERT INTO job_request_categories (job_request_id, category_id) VALUES (?, ?)')->execute([$job_request_id, $cat_id]);
        }
        // Log creation with note
        $db->prepare('INSERT INTO job_request_logs (job_request_id, action, note, performed_by, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$job_request_id, 'Created', $note, $_SESSION['user_id']]);
        header('Location: index.php?success=1');
        exit;
    }
}

$page_title = 'Add Job Request';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Add Job Request</h1>
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
                                <option value="<?= $client['id'] ?>" <?= (isset($_POST['client_id']) && $_POST['client_id'] == $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars($client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Job Title *</label>
                        <input type="text" name="job_title" class="form-control" required value="<?= htmlspecialchars($_POST['job_title'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Job Description</label>
                        <textarea name="job_description" class="form-control"><?= htmlspecialchars($_POST['job_description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="New" <?= (($_POST['status'] ?? '') == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Research" <?= (($_POST['status'] ?? '') == 'Research') ? 'selected' : '' ?>>Research</option>
                            <option value="Candidates Proposed" <?= (($_POST['status'] ?? '') == 'Candidates Proposed') ? 'selected' : '' ?>>Candidates Proposed</option>
                            <option value="Manager Review" <?= (($_POST['status'] ?? '') == 'Manager Review') ? 'selected' : '' ?>>Manager Review</option>
                            <option value="Client Review" <?= (($_POST['status'] ?? '') == 'Client Review') ? 'selected' : '' ?>>Client Review</option>
                            <option value="Interview" <?= (($_POST['status'] ?? '') == 'Interview') ? 'selected' : '' ?>>Interview</option>
                            <option value="Closed" <?= (($_POST['status'] ?? '') == 'Closed') ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categories</label>
                        <select id="categories-select" name="categories[]" class="form-select" multiple>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($_POST['categories']) && in_array($cat['id'], $_POST['categories'])) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Type to search and select multiple categories.</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Note *</label>
                        <textarea name="note" class="form-control" required><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                        <div class="form-text">Please enter a note for this operation. This will be logged as the first entry in the job request history.</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Save Job Request</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#categories-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select categories',
        allowClear: true
    });
});
</script>
