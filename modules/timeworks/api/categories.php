<?php
/**
 * TimeWorks Module - Categories API
 *
 * API endpoint for managing user categories.
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle GET for export
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export') {
    if (!has_permission('timeworks_users_view')) {
        die('Permission denied');
    }

    $categoryId = $_GET['category_id'] ?? null;

    $where = '';
    $params = [];
    if ($categoryId) {
        $where = 'WHERE cd.id = ?';
        $params[] = $categoryId;
    }

    $stmt = $db->prepare("
        SELECT
            u.full_name,
            u.email,
            cd.name as category_name,
            uc.referred_by,
            uc.notes,
            uc.assigned_at
        FROM twr_user_categories uc
        JOIN twr_users u ON uc.user_id = u.user_id
        JOIN twr_category_definitions cd ON uc.category_code = cd.code
        {$where}
        ORDER BY u.full_name
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="category_assignments_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Category', 'Referred By', 'Notes', 'Assigned At']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['full_name'],
            $row['email'],
            $row['category_name'],
            $row['referred_by'],
            $row['notes'],
            $row['assigned_at']
        ]);
    }

    fclose($output);
    exit;
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'list_categories':
            $stmt = $db->query("
                SELECT cd.*, COUNT(uc.id) as user_count
                FROM twr_category_definitions cd
                LEFT JOIN twr_user_categories uc ON cd.code = uc.category_code
                WHERE cd.is_active = 1
                GROUP BY cd.id, cd.code, cd.name, cd.description, cd.color_code, cd.is_active, cd.sort_order, cd.created_at, cd.updated_at
                ORDER BY cd.sort_order
            ");
            $categories = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            break;

        case 'list_assignments':
            $categoryCode = $input['category_code'] ?? $input['category_id'] ?? null;
            $search = $input['search'] ?? '';

            // If category_id is passed, convert to code
            if ($categoryCode && is_numeric($categoryCode)) {
                $stmt = $db->prepare("SELECT code FROM twr_category_definitions WHERE id = ?");
                $stmt->execute([$categoryCode]);
                $categoryCode = $stmt->fetchColumn() ?: null;
            }

            // Get all users with their categories
            $sql = "
                SELECT
                    u.user_id,
                    u.full_name,
                    u.email,
                    GROUP_CONCAT(uc.referred_by SEPARATOR ', ') as referred_by,
                    GROUP_CONCAT(uc.notes SEPARATOR '; ') as notes
                FROM twr_users u
                LEFT JOIN twr_user_categories uc ON u.user_id = uc.user_id
                WHERE u.status = 'active'
            ";

            $params = [];

            if ($categoryCode) {
                $sql .= " AND uc.category_code = ?";
                $params[] = $categoryCode;
            }

            if ($search) {
                $sql .= " AND u.full_name LIKE ?";
                $params[] = "%{$search}%";
            }

            $sql .= " GROUP BY u.user_id ORDER BY u.full_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            // Get categories for each user
            foreach ($users as &$user) {
                $stmt = $db->prepare("
                    SELECT cd.id, cd.name, cd.code, cd.color_code
                    FROM twr_user_categories uc
                    JOIN twr_category_definitions cd ON uc.category_code = cd.code
                    WHERE uc.user_id = ?
                ");
                $stmt->execute([$user['user_id']]);
                $user['categories'] = $stmt->fetchAll();
            }

            echo json_encode([
                'success' => true,
                'data' => $users
            ]);
            break;

        case 'create_category':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $name = $input['name'] ?? null;
            $code = $input['code'] ?? null;
            $colorCode = $input['color_code'] ?? '#007bff';
            $description = $input['description'] ?? null;

            if (!$name || !$code) {
                throw new Exception('Name and code are required');
            }

            // Check if code exists
            $stmt = $db->prepare("SELECT id FROM twr_category_definitions WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                throw new Exception('Category code already exists');
            }

            // Get max sort order
            $stmt = $db->query("SELECT MAX(sort_order) FROM twr_category_definitions");
            $maxOrder = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("
                INSERT INTO twr_category_definitions (code, name, description, color_code, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$code, $name, $description, $colorCode, $maxOrder + 1]);

            log_activity(
                $_SESSION['user_id'],
                'category_create',
                'timeworks_category',
                "Created category: {$name} ({$code})"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Category created successfully'
            ]);
            break;

        case 'update_category':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $id = $input['id'] ?? null;
            $name = $input['name'] ?? null;
            $colorCode = $input['color_code'] ?? null;
            $description = $input['description'] ?? null;

            if (!$id || !$name) {
                throw new Exception('ID and name are required');
            }

            $stmt = $db->prepare("
                UPDATE twr_category_definitions
                SET name = ?, color_code = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $colorCode, $description, $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Category updated successfully'
            ]);
            break;

        case 'assign':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $userId = $input['user_id'] ?? null;
            $categoryId = $input['category_id'] ?? null;
            $referredBy = $input['referred_by'] ?? null;
            $clientId = $input['client_id'] ?? null;
            $notes = $input['notes'] ?? null;

            if (!$userId || !$categoryId) {
                throw new Exception('User and category are required');
            }

            // Get category code from id
            $stmt = $db->prepare("SELECT code, name FROM twr_category_definitions WHERE id = ?");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch();
            if (!$category) {
                throw new Exception('Category not found');
            }
            $categoryCode = $category['code'];
            $categoryName = $category['name'];

            // Check if already assigned
            $stmt = $db->prepare("SELECT id FROM twr_user_categories WHERE user_id = ? AND category_code = ?");
            $stmt->execute([$userId, $categoryCode]);
            if ($stmt->fetch()) {
                throw new Exception('User already has this category');
            }

            $stmt = $db->prepare("
                INSERT INTO twr_user_categories
                (user_id, category_code, referred_by, client_id, notes, assigned_by, assigned_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
            ");
            $stmt->execute([$userId, $categoryCode, $referredBy, $clientId ?: null, $notes, $_SESSION['user_id']]);

            // Get user name for logging
            $stmt = $db->prepare("SELECT full_name FROM twr_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userName = $stmt->fetchColumn();

            log_activity(
                $_SESSION['user_id'],
                'category_assign',
                'timeworks_category',
                "Assigned {$userName} to category: {$categoryName}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Category assigned successfully'
            ]);
            break;

        case 'remove':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $userId = $input['user_id'] ?? null;
            $categoryId = $input['category_id'] ?? null;
            $categoryCode = $input['category_code'] ?? null;

            if (!$userId || (!$categoryId && !$categoryCode)) {
                throw new Exception('User and category are required');
            }

            // Get category code from id if not provided
            if (!$categoryCode && $categoryId) {
                $stmt = $db->prepare("SELECT code FROM twr_category_definitions WHERE id = ?");
                $stmt->execute([$categoryId]);
                $categoryCode = $stmt->fetchColumn();
            }

            $stmt = $db->prepare("DELETE FROM twr_user_categories WHERE user_id = ? AND category_code = ?");
            $stmt->execute([$userId, $categoryCode]);

            echo json_encode([
                'success' => true,
                'message' => 'Category removed successfully'
            ]);
            break;

        case 'remove_all':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $userId = $input['user_id'] ?? null;

            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $stmt = $db->prepare("DELETE FROM twr_user_categories WHERE user_id = ?");
            $stmt->execute([$userId]);

            echo json_encode([
                'success' => true,
                'message' => 'All categories removed successfully'
            ]);
            break;

        case 'get_user_categories':
            $userId = $input['user_id'] ?? null;

            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $stmt = $db->prepare("
                SELECT cd.*, uc.referred_by, uc.notes, uc.assigned_at
                FROM twr_user_categories uc
                JOIN twr_category_definitions cd ON uc.category_code = cd.code
                WHERE uc.user_id = ?
                ORDER BY cd.sort_order
            ");
            $stmt->execute([$userId]);
            $categories = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
