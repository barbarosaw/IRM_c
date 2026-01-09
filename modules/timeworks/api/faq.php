<?php
/**
 * TimeWorks Module - FAQ API
 *
 * CRUD operations for FAQ management
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Permission check
if (!has_permission('timeworks_users_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM twr_faq WHERE id = ?");
        $stmt->execute([$id]);
        $faq = $stmt->fetch();

        if ($faq) {
            echo json_encode(['success' => true, 'faq' => $faq]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'FAQ not found']);
        }
        exit;
    }

    if ($action === 'list') {
        $stmt = $db->query("SELECT * FROM twr_faq ORDER BY sort_order ASC, id ASC");
        $faqs = $stmt->fetchAll();
        echo json_encode(['success' => true, 'faqs' => $faqs]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create':
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');
            $sortOrder = (int)($input['sort_order'] ?? 0);
            $isActive = (int)($input['is_active'] ?? 1);

            if (empty($question) || empty($answer)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Question and answer are required']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO twr_faq (question, answer, sort_order, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$question, $answer, $sortOrder, $isActive]);

            echo json_encode([
                'success' => true,
                'message' => 'FAQ created successfully',
                'id' => $db->lastInsertId()
            ]);
            break;

        case 'update':
            $id = (int)($input['id'] ?? 0);
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');
            $sortOrder = (int)($input['sort_order'] ?? 0);
            $isActive = (int)($input['is_active'] ?? 1);

            if (empty($id) || empty($question) || empty($answer)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID, question and answer are required']);
                exit;
            }

            $stmt = $db->prepare("
                UPDATE twr_faq
                SET question = ?, answer = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$question, $answer, $sortOrder, $isActive, $id]);

            echo json_encode(['success' => true, 'message' => 'FAQ updated successfully']);
            break;

        case 'delete':
            $id = (int)($input['id'] ?? 0);

            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID is required']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM twr_faq WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'FAQ deleted successfully']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
