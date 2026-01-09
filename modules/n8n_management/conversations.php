<?php
/**
 * N8N Management Module - Conversations List
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

require_once __DIR__ . '/models/ChatSession.php';
$sessionModel = new ChatSession($db);

// Get filters
$page = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status' => $_GET['status'] ?? '',
    'intent' => $_GET['intent'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Today filter shortcut
if (isset($_GET['filter']) && $_GET['filter'] === 'today') {
    $filters['date_from'] = date('Y-m-d');
    $filters['date_to'] = date('Y-m-d');
}

$result = $sessionModel->getAll($page, 20, $filters);
$sessions = $result['data'];
$intents = $sessionModel->getIntents();

$page_title = "Conversations";
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
                        <i class="fas fa-comments me-2"></i>Conversations
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Conversations</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter me-2"></i>Filters</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="closed" <?= $filters['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Intent</label>
                            <select name="intent" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($intents as $intent): ?>
                                    <option value="<?= htmlspecialchars($intent) ?>" <?= $filters['intent'] === $intent ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($intent) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Session ID, URL..." value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sessions List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?= number_format($result['total']) ?> Conversations
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Session ID</th>
                                <th>First Message</th>
                                <th>Intent</th>
                                <th>Messages</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sessions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No conversations found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sessions as $session): ?>
                                    <?php
                                    $duration = '';
                                    if ($session['started_at'] && $session['last_activity']) {
                                        $start = new DateTime($session['started_at']);
                                        $end = new DateTime($session['last_activity']);
                                        $diff = $start->diff($end);
                                        if ($diff->h > 0) {
                                            $duration = $diff->format('%hh %im');
                                        } else {
                                            $duration = $diff->format('%im %ss');
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <code><?= htmlspecialchars(substr($session['id'], 0, 8)) ?>...</code>
                                        </td>
                                        <td>
                                            <?php if (!empty($session['first_message'])): ?>
                                                <span title="<?= htmlspecialchars($session['first_message']) ?>">
                                                    <?= htmlspecialchars(substr($session['first_message'], 0, 40)) ?>...
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($session['primary_intent']): ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($session['primary_intent']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $session['message_count'] ?></td>
                                        <td>
                                            <?php if ($session['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M j, H:i', strtotime($session['started_at'])) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= $duration ?></small>
                                        </td>
                                        <td>
                                            <a href="conversation-detail.php?id=<?= urlencode($session['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($result['pages'] > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
