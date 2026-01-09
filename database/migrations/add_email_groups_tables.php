<?php
/**
 * Migration: Add Email Groups Tables
 *
 * Creates tables for managing email recipient groups
 *
 * @author ikinciadam@gmail.com
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';

echo "Starting Email Groups Tables Migration...\n\n";

try {
    // 1. Create email_groups table
    echo "Creating email_groups table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_groups (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_is_active (is_active),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  - email_groups table created.\n";

    // 2. Create email_group_members table
    echo "Creating email_group_members table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_group_members (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES email_groups(id) ON DELETE CASCADE,
            INDEX idx_group_id (group_id),
            INDEX idx_user_id (user_id),
            INDEX idx_email (email),
            UNIQUE KEY unique_group_email (group_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  - email_group_members table created.\n";

    // 3. Add menu item for Email Groups
    echo "\nAdding menu item...\n";

    // Find TimeWorks parent menu
    $stmt = $db->prepare("SELECT id FROM menu_items WHERE url LIKE '%/modules/timeworks/index.php%' OR name = 'TimeWorks' LIMIT 1");
    $stmt->execute();
    $parentId = $stmt->fetchColumn();

    if ($parentId) {
        // Get max display order under TimeWorks
        $stmt = $db->prepare("SELECT MAX(display_order) FROM menu_items WHERE parent_id = ?");
        $stmt->execute([$parentId]);
        $maxOrder = (int)$stmt->fetchColumn();

        // Check if menu item already exists
        $stmt = $db->prepare("SELECT id FROM menu_items WHERE url = ?");
        $stmt->execute(['/modules/timeworks/email-groups.php']);

        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("
                INSERT INTO menu_items (name, url, icon, permission, parent_id, display_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                'Email Groups',
                '/modules/timeworks/email-groups.php',
                'fas fa-users-cog',
                'timeworks_email_manage',
                $parentId,
                $maxOrder + 1
            ]);
            echo "  - Email Groups menu item added.\n";
        } else {
            echo "  - Email Groups menu item already exists.\n";
        }
    } else {
        echo "  - WARNING: TimeWorks parent menu not found. Menu item not added.\n";
    }

    echo "\n";
    echo "==========================================\n";
    echo "Migration completed successfully!\n";
    echo "==========================================\n";

} catch (PDOException $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
