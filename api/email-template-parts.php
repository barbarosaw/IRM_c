<?php
/**
 * AbroadWorks Management System - Email Template Parts API (Headers/Footers)
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
        listParts();
        break;
    case 'get':
        getPart();
        break;
    case 'save':
        savePart();
        break;
    case 'delete':
        deletePart();
        break;
    case 'toggle':
        togglePart();
        break;
    case 'preview':
        previewComposed();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * List all parts (optionally filtered by type)
 */
function listParts() {
    global $db;

    $type = $_REQUEST['type'] ?? '';

    try {
        if ($type && in_array($type, ['header', 'footer'])) {
            $stmt = $db->prepare("SELECT * FROM email_template_parts WHERE type = ? ORDER BY name ASC");
            $stmt->execute([$type]);
        } else {
            $stmt = $db->query("SELECT * FROM email_template_parts ORDER BY type, name ASC");
        }
        $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'parts' => $parts]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get single part
 */
function getPart() {
    global $db;

    $id = $_REQUEST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Part ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM email_template_parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($part) {
            echo json_encode(['success' => true, 'part' => $part]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Part not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save part (create or update)
 */
function savePart() {
    global $db;

    $id = $_POST['id'] ?? 0;
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $content = $_POST['content'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($code) || empty($name) || empty($type) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Code, name, type and content are required']);
        return;
    }

    // Validate type
    if (!in_array($type, ['header', 'footer'])) {
        echo json_encode(['success' => false, 'message' => 'Type must be header or footer']);
        return;
    }

    // Validate code format (alphanumeric and underscores only)
    if (!preg_match('/^[a-z0-9_]+$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Code must contain only lowercase letters, numbers and underscores']);
        return;
    }

    try {
        if ($id) {
            // Check if code exists for another part of same type
            $stmt = $db->prepare("SELECT id FROM email_template_parts WHERE code = ? AND type = ? AND id != ?");
            $stmt->execute([$code, $type, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Code already exists for this type']);
                return;
            }

            // Update
            $stmt = $db->prepare("UPDATE email_template_parts SET code = ?, name = ?, type = ?, content = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$code, $name, $type, $content, $description, $is_active, $id]);

            // Log activity
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'], 'update', 'email_template_part', "Updated email {$type}: {$name}");
            }

            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' updated successfully']);
        } else {
            // Check if code exists for same type
            $stmt = $db->prepare("SELECT id FROM email_template_parts WHERE code = ? AND type = ?");
            $stmt->execute([$code, $type]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Code already exists for this type']);
                return;
            }

            // Insert
            $stmt = $db->prepare("INSERT INTO email_template_parts (code, name, type, content, description, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$code, $name, $type, $content, $description, $is_active, $_SESSION['user_id']]);

            // Log activity
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'], 'create', 'email_template_part', "Created email {$type}: {$name}");
            }

            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' created successfully', 'id' => $db->lastInsertId()]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete part
 */
function deletePart() {
    global $db;

    $id = $_POST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Part ID required']);
        return;
    }

    try {
        // Get part info for logging and validation
        $stmt = $db->prepare("SELECT name, type FROM email_template_parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch();

        if (!$part) {
            echo json_encode(['success' => false, 'message' => 'Part not found']);
            return;
        }

        // Check if part is used by any template
        $column = $part['type'] === 'header' ? 'header_id' : 'footer_id';
        $stmt = $db->prepare("SELECT COUNT(*) FROM email_templates WHERE {$column} = ?");
        $stmt->execute([$id]);
        $usageCount = $stmt->fetchColumn();

        if ($usageCount > 0) {
            echo json_encode(['success' => false, 'message' => "Cannot delete: This {$part['type']} is used by {$usageCount} template(s)"]);
            return;
        }

        $stmt = $db->prepare("DELETE FROM email_template_parts WHERE id = ?");
        $stmt->execute([$id]);

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'delete', 'email_template_part', "Deleted email {$part['type']}: {$part['name']}");
        }

        echo json_encode(['success' => true, 'message' => ucfirst($part['type']) . ' deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Toggle part active status
 */
function togglePart() {
    global $db;

    $id = $_POST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Part ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("UPDATE email_template_parts SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        // Get new status
        $stmt = $db->prepare("SELECT name, type, is_active FROM email_template_parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch();

        $status = $part['is_active'] ? 'activated' : 'deactivated';

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'update', 'email_template_part', ucfirst($part['type']) . " {$status}: {$part['name']}");
        }

        echo json_encode(['success' => true, 'message' => ucfirst($part['type']) . " {$status}", 'is_active' => $part['is_active']]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Preview composed email (header + body + footer)
 */
function previewComposed() {
    global $db;

    $headerId = $_REQUEST['header_id'] ?? null;
    $footerId = $_REQUEST['footer_id'] ?? null;
    $body = $_REQUEST['body'] ?? '<p>Sample email content goes here.</p>';

    $headerContent = '';
    $footerContent = '';

    try {
        // Get header if specified
        if (!empty($headerId)) {
            $stmt = $db->prepare("SELECT content FROM email_template_parts WHERE id = ? AND type = 'header' AND is_active = 1");
            $stmt->execute([$headerId]);
            $headerContent = $stmt->fetchColumn() ?: '';
        }

        // Get footer if specified
        if (!empty($footerId)) {
            $stmt = $db->prepare("SELECT content FROM email_template_parts WHERE id = ? AND type = 'footer' AND is_active = 1");
            $stmt->execute([$footerId]);
            $footerContent = $stmt->fetchColumn() ?: '';
        }

        // Get settings for placeholders
        $siteName = 'AbroadWorks IRM';
        $siteUrl = '';

        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'site_url')");
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] === 'site_name') $siteName = $row['setting_value'];
            if ($row['setting_key'] === 'site_url') $siteUrl = $row['setting_value'];
        }

        // Replace placeholders in header and footer
        $replacements = [
            '{{site_name}}' => $siteName,
            '{{site_url}}' => $siteUrl,
            '{{year}}' => date('Y'),
            '{{date}}' => date('d.m.Y'),
            '{{time}}' => date('H:i'),
        ];

        foreach ($replacements as $placeholder => $value) {
            $headerContent = str_replace($placeholder, $value, $headerContent);
            $footerContent = str_replace($placeholder, $value, $footerContent);
            $body = str_replace($placeholder, $value, $body);
        }

        // Compose final HTML
        $composedHtml = $headerContent . $body . $footerContent;

        // Wrap in 80% width container (same as actual email sending)
        $wrappedHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" width="80%" cellspacing="0" cellpadding="0" border="0" style="max-width: 800px; background-color: #ffffff;">
                    <tr>
                        <td style="padding: 0;">
                            ' . $composedHtml . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        echo json_encode([
            'success' => true,
            'html' => $wrappedHtml,
            'header' => $headerContent,
            'body' => $body,
            'footer' => $footerContent
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
