<?php
/**
 * Add missing menu items for N8N Management module
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

echo "=== Adding N8N Menu Items ===\n\n";

// First find the N8N Management parent menu
$stmt = $db->query("SELECT id, name FROM menu_items WHERE name LIKE '%N8N%' OR name LIKE '%Chatbot%' OR url LIKE '%n8n%'");
$n8nMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found N8N related menus:\n";
foreach ($n8nMenus as $menu) {
    echo "  - ID: {$menu['id']}, Name: {$menu['name']}\n";
}

// Find the parent menu ID
$stmt = $db->query("SELECT id FROM menu_items WHERE url LIKE '%n8n_management%' AND parent_id IS NULL LIMIT 1");
$parentId = $stmt->fetchColumn();

if (!$parentId) {
    // Try to find by name
    $stmt = $db->query("SELECT id FROM menu_items WHERE (name LIKE '%N8N%' OR name LIKE '%Chatbot%') AND parent_id IS NULL LIMIT 1");
    $parentId = $stmt->fetchColumn();
}

echo "\nParent menu ID: " . ($parentId ?: 'Not found') . "\n";

if (!$parentId) {
    echo "\nCannot find N8N parent menu. Listing all parent menus:\n";
    $stmt = $db->query("SELECT id, name, url FROM menu_items WHERE parent_id IS NULL ORDER BY display_order");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $menu) {
        echo "  - ID: {$menu['id']}, Name: {$menu['name']}, URL: {$menu['url']}\n";
    }
    exit(1);
}

// Get existing child menus
echo "\nExisting child menus under N8N:\n";
$stmt = $db->prepare("SELECT id, name, url, display_order FROM menu_items WHERE parent_id = ? ORDER BY display_order");
$stmt->execute([$parentId]);
$existingChildren = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($existingChildren as $child) {
    echo "  - {$child['name']} ({$child['url']}) - Order: {$child['display_order']}\n";
}

// Define new menu items to add
$newMenuItems = [
    [
        'name' => 'Knowledge Base',
        'url' => 'modules/n8n_management/knowledge-base.php',
        'icon' => 'fa-book',
        'display_order' => 30
    ],
    [
        'name' => 'Chat Prompts',
        'url' => 'modules/n8n_management/chat-prompts.php',
        'icon' => 'fa-comment-dots',
        'display_order' => 40
    ]
];

echo "\n--- Adding new menu items ---\n";

foreach ($newMenuItems as $item) {
    // Check if already exists
    $stmt = $db->prepare("SELECT id FROM menu_items WHERE url = ?");
    $stmt->execute([$item['url']]);
    if ($stmt->fetchColumn()) {
        echo "✓ Already exists: {$item['name']}\n";
        continue;
    }

    // Add the menu item
    $stmt = $db->prepare("INSERT INTO menu_items (parent_id, name, url, icon, display_order, is_active, permission) VALUES (?, ?, ?, ?, ?, 1, NULL)");
    $stmt->execute([
        $parentId,
        $item['name'],
        $item['url'],
        $item['icon'],
        $item['display_order']
    ]);
    echo "✓ Added: {$item['name']}\n";
}

echo "\n=== Done ===\n";
