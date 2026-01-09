<?php
/**
 * Operations Module - Edit Client
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-clients-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    header('Location: index.php');
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
        $stmt = $db->prepare('UPDATE clients SET name=?, phone=?, address=?, status=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$name, $phone, $address, $status, $id]);
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    }
}

$page_title = 'Edit Client';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Edit Client</h1>
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
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($client['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($client['phone']) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($client['address']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="Active" <?= ($client['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($client['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="Prospect" <?= ($client['status'] == 'Prospect') ? 'selected' : '' ?>>Prospect</option>
                            <option value="Blocked" <?= ($client['status'] == 'Blocked') ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Update Client</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
