<?php
/**
 * AbroadWorks Management System - Email Images Module
 * Image management for email templates
 *
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Check if user has permission
if (!has_permission('timeworks_email_templates')) {
    header('Location: ../../../access-denied.php');
    exit;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['email_images_csrf'])) {
    $_SESSION['email_images_csrf'] = bin2hex(random_bytes(32));
}

// Handle delete request
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['image_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['email_images_csrf']) {
        $_SESSION['error'] = 'Invalid security token';
    } else {
        try {
            // Get image info
            $stmt = $db->prepare("SELECT filename FROM email_images WHERE id = ?");
            $stmt->execute([$_POST['image_id']]);
            $image = $stmt->fetch();
            
            if ($image) {
                // Delete file
                $file_path = dirname(__FILE__) . '/../../../uploads/email_images/' . $image['filename'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete database record
                $stmt = $db->prepare("DELETE FROM email_images WHERE id = ?");
                $stmt->execute([$_POST['image_id']]);
                
                $_SESSION['success'] = 'Image deleted successfully';
            } else {
                $_SESSION['error'] = 'Image not found';
            }
        } catch (PDOException $e) {
            error_log("Error deleting image: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to delete image';
        }
    }
    
    header('Location: index.php');
    exit;
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get images from database
$images = [];
try {
    $sql = "SELECT i.*, u.name as uploader_name 
            FROM email_images i 
            LEFT JOIN users u ON i.uploaded_by = u.id";
    
    if (!empty($search)) {
        $sql .= " WHERE i.original_name LIKE ? OR i.filename LIKE ?";
        $sql .= " ORDER BY i.created_at DESC";
        $stmt = $db->prepare($sql);
        $search_param = '%' . $search . '%';
        $stmt->execute([$search_param, $search_param]);
    } else {
        $sql .= " ORDER BY i.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading images: " . $e->getMessage());
}

// Get base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host;

// CSRF token for views
$csrf_token = $_SESSION['email_images_csrf'];

// Set page title
$page_title = "Email Images";

// Set root path for components
$root_path = '../../../';

// Include header
include '../../../components/header.php';

// Include sidebar
include '../../../components/sidebar.php';

// Include view
include 'views/index.php';

// Include footer
include '../../../components/footer.php';
