<?php

/**
 * Operations Module - Add Candidate
 * Modern, user-friendly candidate add form
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-candidates-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

// Fetch categories
$cat_stmt = $db->query('SELECT * FROM categories ORDER BY name');
$categories = $cat_stmt->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $profile = trim($_POST['profile'] ?? '');
    $referrer = trim($_POST['referrer'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $internal_status = trim($_POST['internal_status'] ?? '');
    $external_status = trim($_POST['external_status'] ?? '');
    $reprofile = isset($_POST['reprofile']) ? 1 : 0;
    $comments = trim($_POST['comments'] ?? '');
    $redactor = trim($_POST['redactor'] ?? '');
    $done = isset($_POST['done']) ? 1 : 0;
    $status = trim($_POST['status'] ?? '');
    $category_ids = $_POST['categories'] ?? [];
    $keywords = trim($_POST['keywords'] ?? '');
    $resume_file = '';

    // File upload
    if (!empty($_FILES['resume_file']['name'])) {
        $target_dir = '../../../assets/uploads/resumes/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = uniqid('resume_') . '_' . basename($_FILES['resume_file']['name']);
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['resume_file']['tmp_name'], $target_file)) {
            $resume_file = 'assets/uploads/resumes/' . $filename;
        } else {
            $errors[] = 'Resume upload failed.';
        }
    }

    if (!$name) $errors[] = 'Name is required.';
    if (!$email) $errors[] = 'Email is required.';

    if (empty($errors)) {
        $stmt = $db->prepare('INSERT INTO candidates (email, name, profile, referrer, notes, internal_status, external_status, reprofile, comments, redactor, done, resume_file, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$email, $name, $profile, $referrer, $notes, $internal_status, $external_status, $reprofile, $comments, $redactor, $done, $resume_file, $status]);
        $candidate_id = $db->lastInsertId();

        // Categories
        foreach ($category_ids as $cat_id) {
            $db->prepare('INSERT INTO candidate_categories (candidate_id, category_id) VALUES (?, ?)')->execute([$candidate_id, $cat_id]);
        }
        // Keywords
        foreach (array_filter(array_map('trim', explode(',', $keywords))) as $kw) {
            $db->prepare('INSERT INTO candidate_keywords (candidate_id, keyword) VALUES (?, ?)')->execute([$candidate_id, $kw]);
        }
        $success = true;
        header('Location: index.php?success=1');
        exit;
    }
}

$page_title = 'Add Candidate';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Add Candidate</h1>
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
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profile</label>
                        <textarea name="profile" class="form-control"><?= htmlspecialchars($_POST['profile'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nick Name</label>
                        <input type="text" name="referrer" class="form-control" value="<?= htmlspecialchars($_POST['referrer'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Internal Status</label>
                        <select name="internal_status" class="form-select">
                            <option value="">Select Internal Status</option>
                            <option value="Active" <?= (($_POST['internal_status'] ?? '') == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Screened" <?= (($_POST['internal_status'] ?? '') == 'Screened') ? 'selected' : '' ?>>Screened</option>
                            <option value="Shortlisted" <?= (($_POST['internal_status'] ?? '') == 'Shortlisted') ? 'selected' : '' ?>>Shortlisted</option>
                            <option value="Interviewed" <?= (($_POST['internal_status'] ?? '') == 'Interviewed') ? 'selected' : '' ?>>Interviewed</option>
                            <option value="Rejected" <?= (($_POST['internal_status'] ?? '') == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">External Status</label>
                        <select name="external_status" class="form-select">
                            <option value="">Select External Status</option>
                            <option value="Available" <?= (($_POST['external_status'] ?? '') == 'Available') ? 'selected' : '' ?>>Available</option>
                            <option value="Not Available" <?= (($_POST['external_status'] ?? '') == 'Not Available') ? 'selected' : '' ?>>Not Available</option>
                            <option value="Offer Pending" <?= (($_POST['external_status'] ?? '') == 'Offer Pending') ? 'selected' : '' ?>>Offer Pending</option>
                            <option value="Hired" <?= (($_POST['external_status'] ?? '') == 'Hired') ? 'selected' : '' ?>>Hired</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Comments</label>
                        <textarea name="comments" class="form-control"><?= htmlspecialchars($_POST['comments'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Redactor</label>
                        <input type="text" name="redactor" class="form-control" value="<?= htmlspecialchars($_POST['redactor'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="New" <?= (($_POST['status'] ?? '') == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="In Review" <?= (($_POST['status'] ?? '') == 'In Review') ? 'selected' : '' ?>>In Review</option>
                            <option value="Approved" <?= (($_POST['status'] ?? '') == 'Approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= (($_POST['status'] ?? '') == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                            <option value="Interview" <?= (($_POST['status'] ?? '') == 'Interview') ? 'selected' : '' ?>>Interview</option>
                            <option value="Hired" <?= (($_POST['status'] ?? '') == 'Hired') ? 'selected' : '' ?>>Hired</option>
                            <option value="Not Suitable" <?= (($_POST['status'] ?? '') == 'Not Suitable') ? 'selected' : '' ?>>Not Suitable</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categories</label>
                        <select name="categories[]" class="form-select" multiple>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($_POST['categories']) && in_array($cat['id'], $_POST['categories'])) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Keywords</label>
                        <input type="text" name="keywords" class="form-control" value="<?= htmlspecialchars($_POST['keywords'] ?? '') ?>">
                        <div class="form-text">Comma separated (e.g. PHP, Java, Team Lead)</div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="reprofile" id="reprofile" <?= isset($_POST['reprofile']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reprofile">Reprofile</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="done" id="done" <?= isset($_POST['done']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="done">Done</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Resume File</label>
                        <input type="file" name="resume_file" class="form-control">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Save Candidate</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>