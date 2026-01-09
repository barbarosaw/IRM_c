<?php
/**
 * Conversation Rules Management
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['n8n_management']);
if (!$stmt->fetchColumn()) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'toggle_rule') {
        $stmt = $db->prepare("UPDATE conversation_rules SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'update_rule') {
        $stmt = $db->prepare("
            UPDATE conversation_rules
            SET rule_name = ?, priority = ?, ai_instructions = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['rule_name'],
            (int)$_POST['priority'],
            $_POST['ai_instructions'],
            $_POST['description'],
            $_POST['id']
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'get_rule') {
        $stmt = $db->prepare("SELECT * FROM conversation_rules WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        exit;
    }
}

// Get all rules grouped by category
$stmt = $db->query("
    SELECT * FROM conversation_rules
    ORDER BY category, priority DESC
");
$allRules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rulesByCategory = [];
foreach ($allRules as $rule) {
    $rulesByCategory[$rule['category']][] = $rule;
}

$categoryLabels = [
    'lead_collection' => ['Lead Collection', 'primary'],
    'service_inquiry' => ['Service Inquiry', 'success'],
    'booking' => ['Booking', 'info'],
    'engagement' => ['Engagement', 'warning'],
    'state_transition' => ['State Transition', 'secondary'],
    'safety' => ['Safety', 'danger'],
    'fallback' => ['Fallback', 'dark']
];

$page_title = "Conversation Rules";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

define('AW_SYSTEM', true);
include '../../components/header.php';
include '../../components/sidebar.php';
?>

<style>
.rule-card {
    border-left: 4px solid #ddd;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.rule-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.rule-card.inactive {
    opacity: 0.5;
}
.rule-code {
    font-family: monospace;
    font-size: 11px;
    color: #666;
}
.priority-badge {
    font-size: 10px;
    font-weight: bold;
}
.conditions-preview, .ai-instructions-preview {
    font-size: 12px;
    color: #666;
    max-height: 60px;
    overflow: hidden;
}
.category-header {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f4f6f9;
    padding: 10px 0;
}
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-brain me-2"></i>Conversation Rules
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Rules</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-list"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Rules</span>
                            <span class="info-box-number"><?= count($allRules) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Active Rules</span>
                            <span class="info-box-number"><?= count(array_filter($allRules, fn($r) => $r['is_active'])) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-layer-group"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Categories</span>
                            <span class="info-box-number"><?= count($rulesByCategory) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-bolt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">High Priority</span>
                            <span class="info-box-number"><?= count(array_filter($allRules, fn($r) => $r['priority'] >= 80)) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rules by Category -->
            <div class="row">
                <div class="col-12">
                    <?php foreach ($rulesByCategory as $category => $rules): ?>
                    <?php $catInfo = $categoryLabels[$category] ?? [$category, 'secondary']; ?>
                    <div class="card">
                        <div class="card-header bg-<?= $catInfo[1] ?> text-white">
                            <h3 class="card-title">
                                <i class="fas fa-folder me-2"></i>
                                <?= htmlspecialchars($catInfo[0]) ?>
                                <span class="badge bg-light text-dark ms-2"><?= count($rules) ?> rules</span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($rules as $rule): ?>
                            <div class="card rule-card <?= $rule['is_active'] ? '' : 'inactive' ?>" data-id="<?= $rule['id'] ?>">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="badge bg-secondary priority-badge me-2">P<?= $rule['priority'] ?></span>
                                                <strong><?= htmlspecialchars($rule['rule_name']) ?></strong>
                                                <span class="rule-code ms-2"><?= htmlspecialchars($rule['rule_code']) ?></span>
                                            </div>
                                            <?php if ($rule['description']): ?>
                                            <div class="text-muted small mb-1"><?= htmlspecialchars($rule['description']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($rule['ai_instructions']): ?>
                                            <div class="ai-instructions-preview">
                                                <strong>AI:</strong> <?= htmlspecialchars(substr($rule['ai_instructions'], 0, 150)) ?>...
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary btn-edit" data-id="<?= $rule['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-<?= $rule['is_active'] ? 'warning' : 'success' ?> btn-toggle" data-id="<?= $rule['id'] ?>">
                                                <i class="fas fa-<?= $rule['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="id" id="edit-id">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Rule Name</label>
                                <input type="text" class="form-control" name="rule_name" id="edit-name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Priority (1-100)</label>
                                <input type="number" class="form-control" name="priority" id="edit-priority" min="1" max="100">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="edit-description">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Conditions (JSON - Read Only)</label>
                        <textarea class="form-control font-monospace" id="edit-conditions" rows="4" readonly></textarea>
                        <small class="text-muted">Edit conditions in database directly for safety</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">AI Instructions</label>
                        <textarea class="form-control" name="ai_instructions" id="edit-ai-instructions" rows="5"></textarea>
                        <small class="text-muted">These instructions are sent to the AI when this rule triggers</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRule">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-toggle').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=toggle_rule&id=${id}`
        });
        if ((await response.json()).success) {
            location.reload();
        }
    });
});

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_rule&id=${id}`
        });
        const rule = await response.json();

        document.getElementById('edit-id').value = rule.id;
        document.getElementById('edit-name').value = rule.rule_name;
        document.getElementById('edit-priority').value = rule.priority;
        document.getElementById('edit-description').value = rule.description || '';
        document.getElementById('edit-conditions').value = rule.conditions;
        document.getElementById('edit-ai-instructions').value = rule.ai_instructions || '';

        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

document.getElementById('saveRule').addEventListener('click', async function() {
    const form = document.getElementById('editForm');
    const data = new FormData(form);
    data.append('action', 'update_rule');

    const response = await fetch('', {
        method: 'POST',
        body: new URLSearchParams(data)
    });

    if ((await response.json()).success) {
        location.reload();
    }
});
</script>

<?php include '../../components/footer.php'; ?>
