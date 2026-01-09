<?php

/**
 * Operations Module - Edit Candidate
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-candidates-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM candidates WHERE id = ?');
$stmt->execute([$id]);
$candidate = $stmt->fetch();
if (!$candidate) {
    header('Location: index.php');
    exit;
}

// Fetch categories
$cat_stmt = $db->query('SELECT * FROM categories ORDER BY name');
$categories = $cat_stmt->fetchAll();

// Fetch selected categories
$sel_cat_stmt = $db->prepare('SELECT category_id FROM candidate_categories WHERE candidate_id = ?');
$sel_cat_stmt->execute([$id]);
$selected_categories = $sel_cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch keywords
$kw_stmt = $db->prepare('SELECT keyword FROM candidate_keywords WHERE candidate_id = ?');
$kw_stmt->execute([$id]);
$keywords = implode(', ', $kw_stmt->fetchAll(PDO::FETCH_COLUMN));

$errors = [];
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
    $new_keywords = trim($_POST['keywords'] ?? '');
    $resume_file = $candidate['resume_file'];

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
        $stmt = $db->prepare('UPDATE candidates SET email=?, name=?, profile=?, referrer=?, notes=?, internal_status=?, external_status=?, reprofile=?, comments=?, redactor=?, done=?, resume_file=?, status=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$email, $name, $profile, $referrer, $notes, $internal_status, $external_status, $reprofile, $comments, $redactor, $done, $resume_file, $status, $id]);
        // Update categories
        $db->prepare('DELETE FROM candidate_categories WHERE candidate_id=?')->execute([$id]);
        foreach ($category_ids as $cat_id) {
            $db->prepare('INSERT INTO candidate_categories (candidate_id, category_id) VALUES (?, ?)')->execute([$id, $cat_id]);
        }
        // Update keywords
        $db->prepare('DELETE FROM candidate_keywords WHERE candidate_id=?')->execute([$id]);
        foreach (array_filter(array_map('trim', explode(',', $new_keywords))) as $kw) {
            $db->prepare('INSERT INTO candidate_keywords (candidate_id, keyword) VALUES (?, ?)')->execute([$id, $kw]);
        }
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    }
}

$page_title = 'Edit Candidate';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Edit Candidate</h1>
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
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($candidate['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($candidate['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profile</label>
                        <textarea name="profile" class="form-control"><?= htmlspecialchars($candidate['profile']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nick Name</label>
                        <input type="text" name="referrer" class="form-control" value="<?= htmlspecialchars($candidate['referrer']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Internal Status</label>
                        <select name="internal_status" class="form-select">
                            <option value="">Select Internal Status</option>
                            <option value="Active" <?= ($candidate['internal_status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Screened" <?= ($candidate['internal_status'] == 'Screened') ? 'selected' : '' ?>>Screened</option>
                            <option value="Shortlisted" <?= ($candidate['internal_status'] == 'Shortlisted') ? 'selected' : '' ?>>Shortlisted</option>
                            <option value="Interviewed" <?= ($candidate['internal_status'] == 'Interviewed') ? 'selected' : '' ?>>Interviewed</option>
                            <option value="Rejected" <?= ($candidate['internal_status'] == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">External Status</label>
                        <select name="external_status" class="form-select">
                            <option value="">Select External Status</option>
                            <option value="Available" <?= ($candidate['external_status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                            <option value="Not Available" <?= ($candidate['external_status'] == 'Not Available') ? 'selected' : '' ?>>Not Available</option>
                            <option value="Offer Pending" <?= ($candidate['external_status'] == 'Offer Pending') ? 'selected' : '' ?>>Offer Pending</option>
                            <option value="Hired" <?= ($candidate['external_status'] == 'Hired') ? 'selected' : '' ?>>Hired</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Comments</label>
                        <textarea name="comments" class="form-control"><?= htmlspecialchars($candidate['comments']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"><?= htmlspecialchars($candidate['notes']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Redactor</label>
                        <input type="text" name="redactor" class="form-control" value="<?= htmlspecialchars($candidate['redactor']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="New" <?= ($candidate['status'] == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Active" <?= ($candidate['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="In Review" <?= ($candidate['status'] == 'In Review') ? 'selected' : '' ?>>In Review</option>
                            <option value="Approved" <?= ($candidate['status'] == 'Approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= ($candidate['status'] == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                            <option value="Interview" <?= ($candidate['status'] == 'Interview') ? 'selected' : '' ?>>Interview</option>
                            <option value="Hired" <?= ($candidate['status'] == 'Hired') ? 'selected' : '' ?>>Hired</option>
                            <option value="Not Suitable" <?= ($candidate['status'] == 'Not Suitable') ? 'selected' : '' ?>>Not Suitable</option>
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
                    <div class="col-md-6">
                        <label class="form-label">Keywords</label>
                        <input type="text" name="keywords" class="form-control" value="<?= htmlspecialchars($keywords) ?>">
                        <div class="form-text">Comma separated (e.g. PHP, Java, Team Lead)</div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="reprofile" id="reprofile" <?= $candidate['reprofile'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="reprofile">Reprofile</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="done" id="done" <?= $candidate['done'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="done">Done</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Resume File</label>
                        <?php if ($candidate['resume_file']): ?>
                            <a href="../../../<?= htmlspecialchars($candidate['resume_file']) ?>" target="_blank">Download Current</a><br>
                        <?php endif; ?>
                        <input type="file" name="resume_file" class="form-control">
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Update Candidate</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>