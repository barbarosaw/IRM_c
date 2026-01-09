<?php
/**
 * Operations Module - Clients List
 * Modern, user-friendly client management UI
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-clients-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$stmt = $db->query('SELECT * FROM clients ORDER BY created_at DESC');
$clients = $stmt->fetchAll();

$page_title = 'Clients';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Clients</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <?php if (has_permission('operations-clients-manage')): ?>
                        <a href="add.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Client</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="clients-table">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?= htmlspecialchars($client['name']) ?></td>
                                <td><?= htmlspecialchars($client['phone']) ?></td>
                                <td><?= htmlspecialchars($client['address']) ?></td>
                                <td><?= htmlspecialchars($client['status']) ?></td>
                                <td><?= date('Y-m-d', strtotime($client['created_at'])) ?></td>
                                <td>
                                    <a href="view.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                    <?php if (has_permission('operations-clients-manage')): ?>
                                        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                        <a href="delete.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
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
<script>
// Optionally, add DataTables or similar for better UX
</script>
