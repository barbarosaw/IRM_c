<?php
/**
 * Operations Module - Add Client
 * Modern, user-friendly client add form
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-clients-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if (!$name) $errors[] = 'Name is required.';

    if (empty($errors)) {
        $stmt = $db->prepare('INSERT INTO clients (name, phone, address, status, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$name, $phone, $address, $status]);
        header('Location: index.php?success=1');
        exit;
    }
}

$page_title = 'Add Client';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Add Client</h1>
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
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="Active" <?= (($_POST['status'] ?? '') == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= (($_POST['status'] ?? '') == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="Prospect" <?= (($_POST['status'] ?? '') == 'Prospect') ? 'selected' : '' ?>>Prospect</option>
                            <option value="Blocked" <?= (($_POST['status'] ?? '') == 'Blocked') ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Save Client</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
