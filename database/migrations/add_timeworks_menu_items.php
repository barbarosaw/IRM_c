<?php
/**
 * Migration: Add TimeWorks Menu Items
 *
 * Adds new menu items for the TimeWorks module features.
 *
 * Run via CLI: php add_timeworks_menu_items.php
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once dirname(__DIR__, 2) . '/includes/init.php';

echo "=== TimeWorks Menu Items Migration ===\n\n";

try {
    // First, get the TimeWorks parent menu ID
    $stmt = $db->query("SELECT id FROM menu_items WHERE url LIKE '%timeworks%' AND parent_id IS NULL LIMIT 1");
    $parentId = $stmt->fetchColumn();

    if (!$parentId) {
        echo "TimeWorks parent menu not found. Looking for alternative...\n";
        $stmt = $db->query("SELECT id FROM menu_items WHERE name LIKE '%TimeWorks%' LIMIT 1");
        $parentId = $stmt->fetchColumn();
    }

    if (!$parentId) {
        echo "Creating TimeWorks parent menu item...\n";

        // Get max display_order
        $stmt = $db->query("SELECT MAX(display_order) FROM menu_items WHERE parent_id IS NULL");
        $maxOrder = $stmt->fetchColumn() ?: 0;

        $stmt = $db->prepare("
            INSERT INTO menu_items (name, url, icon, permission, parent_id, display_order, is_active)
            VALUES ('TimeWorks', '/modules/timeworks/', 'fas fa-clock', 'timeworks_users_view', NULL, ?, 1)
        ");
        $stmt->execute([$maxOrder + 1]);
        $parentId = $db->lastInsertId();
        echo "Created TimeWorks parent menu (ID: {$parentId})\n";
    } else {
        echo "Found TimeWorks parent menu (ID: {$parentId})\n";
    }

    // Get current max display_order for children
    $stmt = $db->prepare("SELECT MAX(display_order) FROM menu_items WHERE parent_id = ?");
    $stmt->execute([$parentId]);
    $maxChildOrder = $stmt->fetchColumn() ?: 0;

    // Menu items to add
    $menuItems = [
        [
            'name' => 'Users',
            'url' => '/modules/timeworks/users.php',
            'icon' => 'fas fa-users',
            'permission' => 'timeworks_users_view'
        ],
        [
            'name' => 'Projects',
            'url' => '/modules/timeworks/projects.php',
            'icon' => 'fas fa-project-diagram',
            'permission' => 'timeworks_projects_view'
        ],
        [
            'name' => 'Shifts',
            'url' => '/modules/timeworks/shifts.php',
            'icon' => 'fas fa-calendar-alt',
            'permission' => 'timeworks_shifts_view'
        ],
        [
            'name' => 'Clients',
            'url' => '/modules/timeworks/clients.php',
            'icon' => 'fas fa-building',
            'permission' => 'timeworks_users_manage'
        ],
        [
            'name' => 'Late Management',
            'url' => '/modules/timeworks/late-management.php',
            'icon' => 'fas fa-user-clock',
            'permission' => 'timeworks_late_manage'
        ],
        [
            'name' => 'Leave Requests',
            'url' => '/modules/timeworks/leave-requests.php',
            'icon' => 'fas fa-calendar-check',
            'permission' => 'timeworks_leave_view'
        ],
        [
            'name' => 'Categories',
            'url' => '/modules/timeworks/category-management.php',
            'icon' => 'fas fa-tags',
            'permission' => 'timeworks_users_manage'
        ],
        [
            'name' => 'Daily Report',
            'url' => '/modules/timeworks/daily-report.php',
            'icon' => 'fas fa-calendar-day',
            'permission' => 'timeworks_daily_report_view'
        ],
        [
            'name' => 'Activity Reports',
            'url' => '/modules/timeworks/reports.php',
            'icon' => 'fas fa-chart-line',
            'permission' => 'timeworks_reports_view'
        ],
        [
            'name' => 'Category Reports',
            'url' => '/modules/timeworks/reports/by-category.php',
            'icon' => 'fas fa-chart-pie',
            'permission' => 'timeworks_reports_view'
        ],
        [
            'name' => 'Period Reports',
            'url' => '/modules/timeworks/reports/period-report.php',
            'icon' => 'fas fa-calendar-week',
            'permission' => 'timeworks_reports_view'
        ],
        [
            'name' => 'Sync Data',
            'url' => '/modules/timeworks/sync.php',
            'icon' => 'fas fa-sync-alt',
            'permission' => 'timeworks_sync_manage'
        ]
    ];

    $added = 0;
    $skipped = 0;

    foreach ($menuItems as $item) {
        // Check if menu item already exists
        $stmt = $db->prepare("SELECT id FROM menu_items WHERE url = ? AND parent_id = ?");
        $stmt->execute([$item['url'], $parentId]);

        if ($stmt->fetch()) {
            echo "  - Skipped (exists): {$item['name']}\n";
            $skipped++;
            continue;
        }

        $maxChildOrder++;

        $stmt = $db->prepare("
            INSERT INTO menu_items (name, url, icon, permission, parent_id, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $item['name'],
            $item['url'],
            $item['icon'],
            $item['permission'],
            $parentId,
            $maxChildOrder
        ]);

        echo "  + Added: {$item['name']}\n";
        $added++;
    }

    echo "\n=== Summary ===\n";
    echo "Added: {$added}\n";
    echo "Skipped: {$skipped}\n";
    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
