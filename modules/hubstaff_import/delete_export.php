<?php
/**
 * Delete Export File Handler
 */

require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$file = $_GET['file'] ?? '';
$exportsDir = __DIR__ . '/exports';

// Validate filename (prevent directory traversal)
if (empty($file) || preg_match('/[\/\\\\]/', $file) || strpos($file, '..') !== false) {
    echo json_encode(['success' => false, 'error' => 'Invalid file']);
    exit;
}

// Only allow CSV files
if (!preg_match('/^[a-zA-Z0-9_-]+_december_2025\.csv$/', $file)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file format']);
    exit;
}

$filePath = $exportsDir . '/' . $file;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not delete file']);
}
