<?php
/**
 * AbroadWorks Management System - Email Templates API
 *
 * @author ikinciadam@gmail.com
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once '../includes/init.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Init error: ' . $e->getMessage()]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listTemplates();
        break;
    case 'get':
        getTemplate();
        break;
    case 'save':
        saveTemplate();
        break;
    case 'delete':
        deleteTemplate();
        break;
    case 'toggle':
        toggleTemplate();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * List all templates
 */
function listTemplates() {
    global $db;

    try {
        $stmt = $db->query("SELECT * FROM email_templates ORDER BY name ASC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'templates' => $templates]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get single template
 */
function getTemplate() {
    global $db;

    $id = $_REQUEST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            echo json_encode(['success' => true, 'template' => $template]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Template not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save template (create or update)
 */
function saveTemplate() {
    global $db;

    $id = $_POST['id'] ?? 0;
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = $_POST['body'] ?? '';
    $placeholders = trim($_POST['placeholders'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $header_id = !empty($_POST['header_id']) ? (int)$_POST['header_id'] : null;
    $footer_id = !empty($_POST['footer_id']) ? (int)$_POST['footer_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($code) || empty($name) || empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Code, name, subject and body are required']);
        return;
    }

    // Validate code format (alphanumeric and underscores only)
    if (!preg_match('/^[a-z0-9_]+$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Code must contain only lowercase letters, numbers and underscores']);
        return;
    }

    try {
        if ($id) {
            // Check if code exists for another template
            $stmt = $db->prepare("SELECT id FROM email_templates WHERE code = ? AND id != ?");
            $stmt->execute([$code, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Template code already exists']);
                return;
            }

            // Update
            $stmt = $db->prepare("UPDATE email_templates SET code = ?, name = ?, subject = ?, body = ?, placeholders = ?, description = ?, header_id = ?, footer_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$code, $name, $subject, $body, $placeholders, $description, $header_id, $footer_id, $is_active, $id]);

            // Log activity
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'], 'update', 'email_template', "Updated email template: {$name}");
            }

            echo json_encode(['success' => true, 'message' => 'Template updated successfully']);
        } else {
            // Check if code exists
            $stmt = $db->prepare("SELECT id FROM email_templates WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Template code already exists']);
                return;
            }

            // Insert
            $stmt = $db->prepare("INSERT INTO email_templates (code, name, subject, body, placeholders, description, header_id, footer_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$code, $name, $subject, $body, $placeholders, $description, $header_id, $footer_id, $is_active]);

            // Log activity
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'], 'create', 'email_template', "Created email template: {$name}");
            }

            echo json_encode(['success' => true, 'message' => 'Template created successfully', 'id' => $db->lastInsertId()]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete template
 */
function deleteTemplate() {
    global $db;

    $id = $_POST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }

    try {
        // Get template name for logging
        $stmt = $db->prepare("SELECT name FROM email_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch();

        if (!$template) {
            echo json_encode(['success' => false, 'message' => 'Template not found']);
            return;
        }

        $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
        $stmt->execute([$id]);

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'delete', 'email_template', "Deleted email template: {$template['name']}");
        }

        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Toggle template active status
 */
function toggleTemplate() {
    global $db;

    $id = $_POST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("UPDATE email_templates SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        // Get new status
        $stmt = $db->prepare("SELECT name, is_active FROM email_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch();

        $status = $template['is_active'] ? 'activated' : 'deactivated';

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'update', 'email_template', "Template {$status}: {$template['name']}");
        }

        echo json_encode(['success' => true, 'message' => "Template {$status}", 'is_active' => $template['is_active']]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
