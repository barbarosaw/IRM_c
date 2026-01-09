<?php
/**
 * View CSV File Handler - November
 * Returns CSV data as JSON for modal display
 */

require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$file = $_GET['file'] ?? '';
$exportsDir = __DIR__ . '/exports';

// Validate filename (prevent directory traversal)
if (empty($file) || preg_match('/[\/\\\\]/', $file) || strpos($file, '..') !== false) {
    echo json_encode(['error' => 'Invalid file']);
    exit;
}

$filePath = $exportsDir . '/' . $file;

if (!file_exists($filePath) || !is_readable($filePath)) {
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Parse CSV
$headers = [];
$rows = [];

if (($handle = fopen($filePath, 'r')) !== false) {
    $lineNum = 0;
    while (($data = fgetcsv($handle)) !== false) {
        if ($lineNum === 0) {
            $headers = $data;
        } else {
            // Limit to first 100 rows for preview
            if ($lineNum <= 100) {
                $rows[] = $data;
            }
        }
        $lineNum++;
    }
    fclose($handle);
}

echo json_encode([
    'headers' => $headers,
    'rows' => $rows,
    'total_rows' => $lineNum - 1,
    'truncated' => ($lineNum - 1) > 100
]);
