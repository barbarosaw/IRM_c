<?php
/**
 * Inventory Module - Export Usage Logs
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('view_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->
prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

// Initialize models
$usageLogModel = new InventoryUsageLog($db);

// Get filter parameters (same as usage-logs.php)
$itemFilter = $_GET['item_id'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFilter = $_GET['date_range'] ?? '7';

// Get export format
$format = $_GET['format'] ?? 'csv';

// Get usage logs with filters (increased limit for export)
$logs = $usageLogModel->getAll([
    'item_id' => $itemFilter,
    'user_id' => $userFilter,
    'action' => $actionFilter,
    'days' => $dateFilter
], 10000); // Higher limit for export

if (empty($logs)) {
    echo "No usage logs found for the selected criteria.";
    exit;
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$filename = "inventory_usage_logs_$timestamp";

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Date/Time',
        'User Name',
        'User Email', 
        'Item Name',
        'Item Code',
        'Action',
        'Description',
        'IP Address',
        'User Agent',
        'Duration (minutes)',
        'Session ID'
    ]);
    
    // CSV Data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['logged_at'],
            $log['user_name'],
            $log['user_email'] ?? '',
            $log['item_name'],
            $log['item_code'] ?? '',
            $log['action'],
            $log['description'] ?? '',
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
            $log['usage_duration_minutes'] ?? '',
            $log['session_id'] ?? ''
        ]);
    }
    
    fclose($output);
    
} elseif ($format === 'excel') {
    // Excel Export (using PHPSpreadsheet if available, otherwise CSV)
    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        require_once $root_dir . '/vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Headers
        $headers = [
            'A1' => 'Date/Time',
            'B1' => 'User Name', 
            'C1' => 'User Email',
            'D1' => 'Item Name',
            'E1' => 'Item Code',
            'F1' => 'Action',
            'G1' => 'Description',
            'H1' => 'IP Address',
            'I1' => 'User Agent',
            'J1' => 'Duration (minutes)',
            'K1' => 'Session ID'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        // Style headers
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE2E2E2');
        
        // Data
        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue('A' . $row, $log['logged_at']);
            $sheet->setCellValue('B' . $row, $log['user_name']);
            $sheet->setCellValue('C' . $row, $log['user_email'] ?? '');
            $sheet->setCellValue('D' . $row, $log['item_name']);
            $sheet->setCellValue('E' . $row, $log['item_code'] ?? '');
            $sheet->setCellValue('F' . $row, $log['action']);
            $sheet->setCellValue('G' . $row, $log['description'] ?? '');
            $sheet->setCellValue('H' . $row, $log['ip_address'] ?? '');
            $sheet->setCellValue('I' . $row, $log['user_agent'] ?? '');
            $sheet->setCellValue('J' . $row, $log['usage_duration_minutes'] ?? '');
            $sheet->setCellValue('K' . $row, $log['session_id'] ?? '');
            $row++;
        }
        
        // Auto size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Output
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        
    } else {
        // Fallback to CSV if PHPSpreadsheet not available
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, [
            'Date/Time', 'User Name', 'User Email', 'Item Name', 'Item Code',
            'Action', 'Description', 'IP Address', 'User Agent', 
            'Duration (minutes)', 'Session ID'
        ]);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['logged_at'],
                $log['user_name'],
                $log['user_email'] ?? '',
                $log['item_name'], 
                $log['item_code'] ?? '',
                $log['action'],
                $log['description'] ?? '',
                $log['ip_address'] ?? '',
                $log['user_agent'] ?? '',
                $log['usage_duration_minutes'] ?? '',
                $log['session_id'] ?? ''
            ]);
        }
        
        fclose($output);
    }
    
} elseif ($format === 'json') {
    // JSON Export
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    $exportData = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['user_name'] ?? 'Unknown',
            'total_records' => count($logs),
            'filters' => [
                'item_id' => $itemFilter,
                'user_id' => $userFilter, 
                'action' => $actionFilter,
                'date_range_days' => $dateFilter
            ]
        ],
        'usage_logs' => $logs
    ];
    
    echo json_encode($exportData, JSON_PRETTY_PRINT);
    
} else {
    // Default to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Date/Time', 'User Name', 'User Email', 'Item Name', 'Item Code',
        'Action', 'Description', 'IP Address', 'User Agent',
        'Duration (minutes)', 'Session ID'
    ]);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['logged_at'],
            $log['user_name'],
            $log['user_email'] ?? '',
            $log['item_name'],
            $log['item_code'] ?? '',
            $log['action'],
            $log['description'] ?? '',
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
            $log['usage_duration_minutes'] ?? '',
            $log['session_id'] ?? ''
        ]);
    }
    
    fclose($output);
}

exit;
?>
