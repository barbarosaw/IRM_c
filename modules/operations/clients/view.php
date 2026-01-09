<?php
/**
 * Operations Module - View Client Details
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-clients-access')) {
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

$page_title = 'Client Details';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Client Details</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="index.php" class="btn btn-secondary">Back to List</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <h5>Name</h5>
                    <p><?= htmlspecialchars($client['name']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Phone</h5>
                    <p><?= htmlspecialchars($client['phone']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Address</h5>
                    <p><?= htmlspecialchars($client['address']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Status</h5>
                    <p><?= htmlspecialchars($client['status']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Created At</h5>
                    <p><?= htmlspecialchars($client['created_at']) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
