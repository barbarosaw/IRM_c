<?php

define('AW_SYSTEM', true);
$root_dir = dirname(__DIR__, 2);
require_once '../../config/database.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$usageLogModel = new InventoryUsageLog($db);

// Filter logs by search query
$logs = $usageLogModel->getAll([
    'search' => $q,
    'order' => 'DESC', // most recent first
    'limit' => 100
]);

foreach ($logs as $log): ?>
<tr>
    <td data-date="<?php echo strtotime($log['logged_at']); ?>">
        <span class="text-muted small">
            <?php echo date('M j, Y', strtotime($log['logged_at'])); ?>
        </span><br>
        <span class="text-sm">
            <?php echo date('H:i:s', strtotime($log['logged_at'])); ?>
        </span>
    </td>
    <td>
        <div class="d-flex align-items-center">
            <?php echo htmlspecialchars($log['user_name']); ?>
        </div>
    </td>
    <td><?php echo htmlspecialchars($log['item_name']); ?></td>
    <td><?php echo htmlspecialchars($log['action']); ?></td>
    <td><?php echo htmlspecialchars($log['description']); ?></td>
    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
    <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
</tr>
<?php endforeach; ?>
