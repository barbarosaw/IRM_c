<?php
/**
 * Operations Module - Categories Management
 * Modern, user-friendly categories UI
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-categories-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$stmt = $db->query('SELECT * FROM categories ORDER BY name');
$categories = $stmt->fetchAll();

$page_title = 'Categories';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Categories</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="add.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Category</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="categories-table">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['name']) ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                    <a href="delete.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
