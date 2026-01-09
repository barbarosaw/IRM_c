<?php
/**
 * N8N Management Module - Knowledge Base Management
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['n8n_management']);
$is_active = $stmt->fetchColumn();
if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_category':
                    $stmt = $db->prepare("INSERT INTO chat_kb_categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$_POST['name'], $_POST['description']]);
                    $success_message = 'Category added successfully!';
                    break;

                case 'edit_category':
                    $stmt = $db->prepare("UPDATE chat_kb_categories SET name = ?, description = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$_POST['name'], $_POST['description'], isset($_POST['is_active']) ? 1 : 0, $_POST['id']]);
                    $success_message = 'Category updated successfully!';
                    break;

                case 'delete_category':
                    $stmt = $db->prepare("DELETE FROM chat_kb_categories WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = 'Category deleted successfully!';
                    break;

                case 'add_item':
                    $stmt = $db->prepare("INSERT INTO chat_kb_items (category_id, question, answer, keywords) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_POST['category_id'], $_POST['question'], $_POST['answer'], $_POST['keywords']]);
                    $success_message = 'Knowledge item added successfully!';
                    break;

                case 'edit_item':
                    $stmt = $db->prepare("UPDATE chat_kb_items SET category_id = ?, question = ?, answer = ?, keywords = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$_POST['category_id'], $_POST['question'], $_POST['answer'], $_POST['keywords'], isset($_POST['is_active']) ? 1 : 0, $_POST['id']]);
                    $success_message = 'Knowledge item updated successfully!';
                    break;

                case 'delete_item':
                    $stmt = $db->prepare("DELETE FROM chat_kb_items WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = 'Knowledge item deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}

// Get categories
$categories = $db->query("SELECT * FROM chat_kb_categories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

// Get items with category names
$items = $db->query("
    SELECT i.*, c.name as category_name
    FROM chat_kb_items i
    LEFT JOIN chat_kb_categories c ON i.category_id = c.id
    ORDER BY c.name, i.question
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Knowledge Base";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

define('AW_SYSTEM', true);
include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-book me-2"></i>Knowledge Base
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Knowledge Base</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Categories -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Categories</h5>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($categories as $cat): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="<?= !$cat['is_active'] ? 'text-muted' : '' ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                        <?php if (!$cat['is_active']): ?>
                                            <small class="text-muted">(inactive)</small>
                                        <?php endif; ?>
                                    </span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary edit-category"
                                                data-id="<?= $cat['id'] ?>"
                                                data-name="<?= htmlspecialchars($cat['name']) ?>"
                                                data-description="<?= htmlspecialchars($cat['description']) ?>"
                                                data-active="<?= $cat['is_active'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                <li class="list-group-item text-muted text-center">No categories yet</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Knowledge Items -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Knowledge Items</h5>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Question</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr class="<?= !$item['is_active'] ? 'table-secondary' : '' ?>">
                                            <td><span class="badge bg-info"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span></td>
                                            <td>
                                                <strong><?= htmlspecialchars(substr($item['question'], 0, 60)) ?><?= strlen($item['question']) > 60 ? '...' : '' ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($item['answer'], 0, 80)) ?>...</small>
                                            </td>
                                            <td>
                                                <?php if ($item['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-item"
                                                        data-id="<?= $item['id'] ?>"
                                                        data-category="<?= $item['category_id'] ?>"
                                                        data-question="<?= htmlspecialchars($item['question']) ?>"
                                                        data-answer="<?= htmlspecialchars($item['answer']) ?>"
                                                        data-keywords="<?= htmlspecialchars($item['keywords']) ?>"
                                                        data-active="<?= $item['is_active'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No knowledge items yet</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-header">
                    <h5 class="modal-title">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="id" id="editCatId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editCatDesc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="editCatActive" class="form-check-input" value="1">
                        <label class="form-check-label" for="editCatActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" id="deleteCatId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_item">
                <div class="modal-header">
                    <h5 class="modal-title">Add Knowledge Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question / Topic</label>
                        <input type="text" name="question" class="form-control" required placeholder="e.g., What are your virtual assistant rates?">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Answer / Content</label>
                        <textarea name="answer" class="form-control" rows="4" required placeholder="The detailed answer or information..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keywords (comma-separated)</label>
                        <input type="text" name="keywords" class="form-control" placeholder="pricing, rates, cost, virtual assistant">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="id" id="editItemId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Knowledge Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="editItemCategory" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question / Topic</label>
                        <input type="text" name="question" id="editItemQuestion" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Answer / Content</label>
                        <textarea name="answer" id="editItemAnswer" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keywords</label>
                        <input type="text" name="keywords" id="editItemKeywords" class="form-control">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="editItemActive" class="form-check-input" value="1">
                        <label class="form-check-label" for="editItemActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-category').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editCatId').value = this.dataset.id;
        document.getElementById('deleteCatId').value = this.dataset.id;
        document.getElementById('editCatName').value = this.dataset.name;
        document.getElementById('editCatDesc').value = this.dataset.description;
        document.getElementById('editCatActive').checked = this.dataset.active == '1';
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    });
});

document.querySelectorAll('.edit-item').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editItemId').value = this.dataset.id;
        document.getElementById('editItemCategory').value = this.dataset.category;
        document.getElementById('editItemQuestion').value = this.dataset.question;
        document.getElementById('editItemAnswer').value = this.dataset.answer;
        document.getElementById('editItemKeywords').value = this.dataset.keywords;
        document.getElementById('editItemActive').checked = this.dataset.active == '1';
        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    });
});
</script>

<?php include '../../components/footer.php'; ?>
