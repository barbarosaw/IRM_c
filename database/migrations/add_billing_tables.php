<?php
/**
 * Migration: Add Billing Tables
 *
 * Creates tables and permissions for the TimeWorks Billing module.
 *
 * Run via CLI: php add_billing_tables.php
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once dirname(__DIR__, 2) . '/includes/init.php';

echo "=== TimeWorks Billing Tables Migration ===\n\n";

try {
    // =========================================================================
    // STEP 1: Create twr_billing_rates table
    // =========================================================================
    echo "Step 1: Creating twr_billing_rates table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS `twr_billing_rates` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` VARCHAR(100) NOT NULL COMMENT 'TimeWorks UUID',
            `rate_type` ENUM('bill', 'pay') NOT NULL COMMENT 'Rate type: bill or pay',
            `rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `rate_from` DATE NOT NULL COMMENT 'Rate effective from date',
            `rate_to` DATE DEFAULT NULL COMMENT 'Rate effective to date (NULL = current)',
            `api_rate_id` INT DEFAULT NULL COMMENT 'Original rate ID from API',
            `notes` TEXT DEFAULT NULL,
            `synced_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_user_type_rate` (`user_id`, `rate_type`, `api_rate_id`),
            KEY `idx_user_type_dates` (`user_id`, `rate_type`, `rate_from`, `rate_to`),
            CONSTRAINT `fk_billing_rates_user` FOREIGN KEY (`user_id`)
                REFERENCES `twr_users` (`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='User billing and pay rates history from TimeWorks API'
    ");
    echo "  + twr_billing_rates table created\n";

    // =========================================================================
    // STEP 2: Create twr_billing_entries table
    // =========================================================================
    echo "\nStep 2: Creating twr_billing_entries table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS `twr_billing_entries` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` VARCHAR(100) NOT NULL COMMENT 'TimeWorks UUID',
            `entry_date` DATE NOT NULL COMMENT 'Service date',
            -- Client/Project foreign key relationships
            `client_id` INT DEFAULT NULL COMMENT 'FK to twr_clients',
            `project_id` VARCHAR(100) DEFAULT NULL COMMENT 'TimeWorks project UUID',
            -- Original API values (for reference)
            `api_client_name` VARCHAR(255) DEFAULT NULL COMMENT 'Original client name from API',
            `api_project_name` VARCHAR(255) DEFAULT NULL COMMENT 'Original project name from API',
            `task_name` VARCHAR(255) DEFAULT NULL COMMENT 'Task name from API',
            `description` VARCHAR(255) DEFAULT NULL COMMENT 'Computed: task_name or project_name',
            -- Duration info
            `duration_seconds` INT DEFAULT 0 COMMENT 'Total duration in seconds',
            `hours` DECIMAL(10,4) DEFAULT 0.0000 COMMENT 'Duration in hours (4 decimal)',
            -- Rate info (effective rate at entry_date)
            `bill_rate` DECIMAL(10,2) DEFAULT 0.00,
            `pay_rate` DECIMAL(10,2) DEFAULT 0.00,
            -- Calculated amounts
            `bill_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'hours * bill_rate',
            `pay_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'hours * pay_rate',
            `profit_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'bill_amount - pay_amount',
            -- Meta
            `synced_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_entry_date` (`entry_date`),
            KEY `idx_user_date` (`user_id`, `entry_date`),
            KEY `idx_client` (`client_id`),
            KEY `idx_project` (`project_id`),
            KEY `idx_synced_at` (`synced_at`),
            CONSTRAINT `fk_billing_entries_user` FOREIGN KEY (`user_id`)
                REFERENCES `twr_users` (`user_id`) ON DELETE CASCADE,
            CONSTRAINT `fk_billing_entries_client` FOREIGN KEY (`client_id`)
                REFERENCES `twr_clients` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Daily time entries with billing calculations - DATA NEVER DELETED'
    ");
    echo "  + twr_billing_entries table created\n";

    // =========================================================================
    // STEP 3: Alter twr_users table - add rate columns
    // =========================================================================
    echo "\nStep 3: Altering twr_users table...\n";

    // Check if columns already exist
    $stmt = $db->query("SHOW COLUMNS FROM twr_users LIKE 'current_bill_rate'");
    if (!$stmt->fetch()) {
        $db->exec("
            ALTER TABLE `twr_users`
            ADD COLUMN `current_bill_rate` DECIMAL(10,2) DEFAULT NULL COMMENT 'Current bill rate' AFTER `activity_checked_at`,
            ADD COLUMN `current_pay_rate` DECIMAL(10,2) DEFAULT NULL COMMENT 'Current pay rate' AFTER `current_bill_rate`,
            ADD COLUMN `rates_synced_at` DATETIME DEFAULT NULL COMMENT 'Last rates sync time' AFTER `current_pay_rate`
        ");
        echo "  + Added current_bill_rate, current_pay_rate, rates_synced_at columns\n";
    } else {
        echo "  - Columns already exist, skipping\n";
    }

    // =========================================================================
    // STEP 4: Add permissions
    // =========================================================================
    echo "\nStep 4: Adding permissions...\n";

    $permissions = [
        [
            'name' => 'View Billing',
            'code' => 'timeworks_billing_view',
            'module' => 'timeworks',
            'module_order' => 60,
            'is_module' => 0,
            'description' => 'View billing time logs and reports'
        ],
        [
            'name' => 'Manage Billing',
            'code' => 'timeworks_billing_manage',
            'module' => 'timeworks',
            'module_order' => 61,
            'is_module' => 0,
            'description' => 'Manage billing sync and data operations'
        ]
    ];

    $addedPerms = 0;
    $skippedPerms = 0;

    foreach ($permissions as $perm) {
        $stmt = $db->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$perm['code']]);

        if ($stmt->fetch()) {
            echo "  - Skipped (exists): {$perm['code']}\n";
            $skippedPerms++;
            continue;
        }

        $stmt = $db->prepare("
            INSERT INTO permissions (name, code, module, module_order, is_module, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $perm['name'],
            $perm['code'],
            $perm['module'],
            $perm['module_order'],
            $perm['is_module'],
            $perm['description']
        ]);

        echo "  + Added: {$perm['code']}\n";
        $addedPerms++;
    }

    // =========================================================================
    // STEP 5: Add menu item
    // =========================================================================
    echo "\nStep 5: Adding menu item...\n";

    // Check if menu item already exists
    $stmt = $db->query("SELECT id FROM menu_items WHERE url = '/modules/timeworks/billing.php'");
    if ($stmt->fetch()) {
        echo "  - Menu item already exists, skipping\n";
    } else {
        // Get TimeWorks parent menu ID
        $stmt = $db->query("SELECT id FROM menu_items WHERE url LIKE '%timeworks%' AND parent_id IS NULL LIMIT 1");
        $parentId = $stmt->fetchColumn();

        if (!$parentId) {
            $stmt = $db->query("SELECT id FROM menu_items WHERE name LIKE '%TimeWorks%' LIMIT 1");
            $parentId = $stmt->fetchColumn();
        }

        if ($parentId) {
            // Get max display_order for children
            $stmt = $db->prepare("SELECT MAX(display_order) FROM menu_items WHERE parent_id = ?");
            $stmt->execute([$parentId]);
            $maxOrder = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("
                INSERT INTO menu_items (name, url, icon, permission, parent_id, display_order, is_active)
                VALUES ('Billing', '/modules/timeworks/billing.php', 'fas fa-file-invoice-dollar', 'timeworks_billing_view', ?, ?, 1)
            ");
            $stmt->execute([$parentId, $maxOrder + 1]);

            echo "  + Added Billing menu item under TimeWorks\n";
        } else {
            echo "  ! Warning: TimeWorks parent menu not found. Menu item not added.\n";
        }
    }

    // =========================================================================
    // STEP 6: Update twr_sync_log enum (if needed)
    // =========================================================================
    echo "\nStep 6: Checking twr_sync_log table...\n";

    $stmt = $db->query("SHOW COLUMNS FROM twr_sync_log LIKE 'sync_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column && strpos($column['Type'], 'billing') === false) {
        // Need to update enum
        $db->exec("
            ALTER TABLE `twr_sync_log`
            MODIFY COLUMN `sync_type` ENUM(
                'users', 'projects', 'user_projects', 'user_shifts',
                'time_entries', 'full', 'billing_rates', 'billing_entries'
            ) NOT NULL
        ");
        echo "  + Updated sync_type enum to include billing types\n";
    } else {
        echo "  - sync_type already includes billing types or table doesn't exist\n";
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================
    echo "\n=== Migration Summary ===\n";
    echo "Tables created: twr_billing_rates, twr_billing_entries\n";
    echo "Columns added to twr_users: current_bill_rate, current_pay_rate, rates_synced_at\n";
    echo "Permissions added: {$addedPerms}, skipped: {$skippedPerms}\n";
    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
