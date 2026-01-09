<?php
/**
 * Module Management Module
 * 
 * @author System Generated
 */

require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['module_management']);
$is_active = $stmt->fetchColumn();

if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Define messages
$success_message = '';
$error_message = '';

// Handle module creation
if (isset($_POST['create_module'])) {
    $module_name = trim($_POST['module_name']);
    $module_code = trim($_POST['module_code']);
    $module_description = trim($_POST['module_description']);
    $module_icon = trim($_POST['module_icon']);
    
    // Clean icon value - remove fas/far/fab prefixes if they exist
    $module_icon = preg_replace('/^(fas|far|fab|fal|fat|fad|fass|fasr|fasl|fast)\s+/', '', $module_icon);
    // Ensure it starts with fa- if it doesn't already
    if (!empty($module_icon) && !str_starts_with($module_icon, 'fa-')) {
        $module_icon = 'fa-' . ltrim($module_icon, '-');
    }
    
    if (empty($module_name) || empty($module_code)) {
        $error_message = "Module name and code are required.";
    } else {
        // Check if module code already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM modules WHERE code = ?");
        $stmt->execute([$module_code]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            $error_message = "A module with this code already exists.";
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // 1. Create module record in database
                $stmt = $db->prepare("INSERT INTO modules (name, code, description, icon, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $result = $stmt->execute([$module_name, $module_code, $module_description, $module_icon]);
                
                if (!$result) {
                    throw new Exception("Failed to create module record.");
                }
                
                $module_id = $db->lastInsertId();
                
                // 2. Create module directory structure
                $module_dir = "../{$module_code}";
                if (!file_exists($module_dir)) {
                    mkdir($module_dir, 0755, true);
                    mkdir("{$module_dir}/models", 0755, true);
                    mkdir("{$module_dir}/views", 0755, true);
                    mkdir("{$module_dir}/widgets", 0755, true);
                } else {
                    throw new Exception("Module directory already exists.");
                }
                
                // 3. Create basic files
                
                // Main index.php with fixed paths for assets
                $index_content = "<?php\n/**\n * {$module_name} Module\n * \n * @author System Generated\n */\n\nrequire_once '../../includes/init.php';\nif (!isset(\$_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }\n\$stmt = \$db->prepare(\"SELECT is_active FROM modules WHERE code = ?\");\n\$stmt->execute(['{$module_code}']);\n\$is_active = \$stmt->fetchColumn();\nif (!\$is_active) { header('Location: ../../module-inactive.php'); exit; }\n\$page_title = \"{$module_name}\";\n\$root_path = \"../../\";\n\$root_dir = dirname(__DIR__, 2);\ninclude '../../components/header.php';\ninclude '../../components/sidebar.php';\ninclude 'views/index.php';\ninclude '../../components/footer.php';\n";
                file_put_contents("{$module_dir}/index.php", $index_content);
                
                $models_index = "<?php\n// This file is intentionally left empty to prevent directory listing\n";
                file_put_contents("{$module_dir}/models/index.php", $models_index);
                
                $views_index = "<?php\n/**\n * {$module_name} Module - Main View\n */\n?>\n<div class=\"content-wrapper\">\n    <div class=\"content-header\">\n        <div class=\"container-fluid\">\n            <div class=\"row mb-2\">\n                <div class=\"col-sm-6\">\n                    <h1 class=\"m-0 text-primary\">{$module_name}</h1>\n                </div>\n                <div class=\"col-sm-6\">\n                    <ol class=\"breadcrumb float-sm-end\">\n                        <li class=\"breadcrumb-item\"><a href=\"../../index.php\">Home</a></li>\n                        <li class=\"breadcrumb-item active\">{$module_name}</li>\n                    </ol>\n                </div>\n            </div>\n        </div>\n    </div>\n    <div class=\"content\">\n        <div class=\"container-fluid\">\n            <div class=\"card\">\n                <div class=\"card-header\">\n                    <h3 class=\"card-title\">{$module_name} Module</h3>\n                </div>\n                <div class=\"card-body\">\n                    <p>Welcome to the {$module_name} module. This is a system-generated template.</p>\n                </div>\n            </div>\n        </div>\n    </div>\n</div>";
                file_put_contents("{$module_dir}/views/index.php", $views_index);
                
                $widgets_index = "<?php\n// This file is intentionally left empty to prevent directory listing\n";
                file_put_contents("{$module_dir}/widgets/index.php", $widgets_index);
                
                // 4. Create permissions
                $view_permission_name = "View {$module_name}";
                $view_permission_code = "view_{$module_code}";
                $view_permission_desc = "Permission to view the {$module_name} module";
                
                // Check if permission code already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM permissions WHERE code = ?");
                $stmt->execute([$view_permission_code]);
                $permission_exists = $stmt->fetchColumn();
                
                if ($permission_exists) {
                    // If permission code exists, add a unique suffix
                    $counter = 1;
                    $original_code = $view_permission_code;
                    do {
                        $view_permission_code = $original_code . '_' . $counter;
                        $stmt->execute([$view_permission_code]);
                        $permission_exists = $stmt->fetchColumn();
                        $counter++;
                    } while ($permission_exists);
                }
                
                $stmt = $db->prepare("INSERT INTO permissions (name, code, description, module, is_module, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $result = $stmt->execute([$view_permission_name, $view_permission_code, $view_permission_desc, $module_code]);
                
                if (!$result) {
                    throw new Exception("Failed to create permission records.");
                }
                
                // 5. Create menu item
                $stmt = $db->prepare("INSERT INTO menu_items (name, url, icon, parent_id, permission, display_order, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, NULL, ?, ?, 1, NOW(), NOW())");
                $menu_url = "modules/{$module_code}/";
                $result = $stmt->execute([$module_name, $menu_url, $module_icon, $view_permission_code, 999]);
                
                if (!$result) {
                    throw new Exception("Failed to create menu item.");
                }
                
                // Commit transaction
                $db->commit();
                
                $success_message = "Module '{$module_name}' has been successfully created.";
                
                // Log the action
                log_activity($_SESSION['user_id'], 'create', 'modules', "Created module: {$module_name}");
                
                // Redirect to refresh with success message
                header("Location: index.php?result=created&name=".urlencode($module_name));
                exit;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error_message = "Error creating module: " . $e->getMessage();
            }
        }
    }
}

// Handle module deletion
if (isset($_POST['delete_module_id']) && !empty($_POST['delete_module_id'])) {
    $delete_id = (int)$_POST['delete_module_id'];
    $input_name = trim($_POST['delete_module_name_input'] ?? '');
    
    // Fetch module info
    $stmt = $db->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$delete_id]);
    $mod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mod && $input_name === $mod['name']) {
        try {
            $db->beginTransaction();
            
            // Delete module folder
            $mod_dir = dirname(__DIR__) . "/" . $mod['code'];
            if (file_exists($mod_dir)) {
                function rrmdir($dir) {
                    if (!is_dir($dir)) return;
                    $files = array_diff(scandir($dir), array('.', '..'));
                    foreach ($files as $file) {
                        (is_dir("$dir/$file")) ? rrmdir("$dir/$file") : unlink("$dir/$file");
                    }
                    return rmdir($dir);
                }
                rrmdir($mod_dir);
            }
            
            // Delete related permissions (by module code and code pattern)
            $stmt = $db->prepare("DELETE FROM permissions WHERE module = ? OR code LIKE ?");
            $stmt->execute([$mod['code'], $mod['code'] . '%']);
            
            // Delete menu item
            $stmt = $db->prepare("DELETE FROM menu_items WHERE url = ?");
            $stmt->execute(["modules/{$mod['code']}/"]); 
            
            // Delete module record
            $stmt = $db->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            // Log the action
            log_activity($_SESSION['user_id'], 'delete', 'modules', "Deleted module: {$mod['name']} ({$mod['code']})");
            
            $db->commit();
            $success_message = "Module successfully deleted.";
            
            // Redirect to refresh with success message
            header("Location: index.php?result=deleted&name=".urlencode($mod['name']));
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error deleting module: " . $e->getMessage();
        }
    } else {
        $error_message = "Module name verification failed or module not found.";
    }
}

// Handle module activation/deactivation
if (isset($_POST['toggle_module'])) {
    $module_id = (int)$_POST['module_id'];
    $new_status = (int)$_POST['new_status'];
    
    try {
        $stmt = $db->prepare("UPDATE modules SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$new_status, $module_id]);
        
        if ($result) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            $success_message = "Module successfully $status_text.";
            
            // Log the action
            $action = $new_status ? 'activate' : 'deactivate';
            log_activity($_SESSION['user_id'], $action, 'modules', "Module ID: $module_id");
            
            // Redirect to refresh with success message
            header("Location: index.php?result=".($new_status ? 'activated' : 'deactivated')."&id=".$module_id);
            exit;
        } else {
            $error_message = "Failed to update module status.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Set page title and root path for assets
$page_title = "Module Management";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Include header and sidebar
include '../../components/header.php';
include '../../components/sidebar.php';

// Modül listesini çek
$stmt = $db->prepare("SELECT * FROM modules ORDER BY name");
$stmt->execute();
$modules = $stmt->fetchAll();

// Include the view
include 'views/index.php';

// Include footer
include '../../components/footer.php';
?>
