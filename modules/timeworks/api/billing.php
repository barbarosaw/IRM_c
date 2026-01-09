<?php
/**
 * TimeWorks Module - Billing API
 *
 * API endpoint for billing time entries and reports.
 *
 * @author ikinciadam@gmail.com
 */

// Increase limits for large data sets
ini_set('memory_limit', '1024M');
set_time_limit(300);

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('timeworks_billing_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Load models
require_once dirname(__DIR__) . '/models/BillingModel.php';

$billing = new BillingModel($db);

// Handle GET requests (exports)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'export_excel') {
        handleExcelExport($db, $billing, $_GET);
        exit;
    }
}

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'get_entries':
            handleGetEntries($billing, $input);
            break;

        case 'get_entries_grouped':
            handleGetEntriesGrouped($billing, $input);
            break;

        case 'get_user_entries':
            handleGetUserEntries($billing, $input);
            break;

        case 'get_totals':
            handleGetTotals($billing, $input);
            break;

        case 'get_clients':
            handleGetClients($billing);
            break;

        case 'get_employees':
            handleGetEmployees($billing);
            break;

        case 'sync_now':
            handleSyncNow($db, $billing);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Billing API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

// =========================================================================
// HANDLER FUNCTIONS
// =========================================================================

/**
 * Get filtered time entries
 */
function handleGetEntries($billing, $input) {
    $period = $input['period'] ?? 'this_month';
    $customStart = $input['start_date'] ?? null;
    $customEnd = $input['end_date'] ?? null;

    // Get date range
    $dateRange = $billing->getDateRangeForPeriod($period, $customStart, $customEnd);

    $filters = [
        'start_date' => $dateRange['start'],
        'end_date' => $dateRange['end']
    ];

    // Client filter
    if (!empty($input['client_id'])) {
        if (is_array($input['client_id'])) {
            $filters['client_id'] = array_map('intval', $input['client_id']);
        } else {
            $filters['client_id'] = (int)$input['client_id'];
        }

        // Get users associated with this client for aggregation
        if (!is_array($input['client_id'])) {
            $clientUsers = $billing->getUsersByClient((int)$input['client_id']);
            if (!empty($clientUsers)) {
                $filters['client_users'] = $clientUsers;
            }
        }
    }

    // Employee filter
    if (!empty($input['user_id'])) {
        $filters['user_id'] = $input['user_id'];
    }

    // Pagination
    if (!empty($input['limit'])) {
        $filters['limit'] = (int)$input['limit'];
    }
    if (!empty($input['offset'])) {
        $filters['offset'] = (int)$input['offset'];
    }

    $entries = $billing->getTimeEntries($filters);

    // Format entries for display
    $formatted = [];
    foreach ($entries as $entry) {
        $formatted[] = [
            'id' => $entry['id'],
            'entry_date' => date('m/d/Y', strtotime($entry['entry_date'])), // US format
            'entry_date_raw' => $entry['entry_date'],
            'employee_name' => $entry['employee_name'] ?? 'Unknown',
            'employee_email' => $entry['employee_email'] ?? '',
            'client_name' => $entry['client_name'] ?? '-',
            'project_name' => $entry['project_name'] ?? '-',
            'description' => $entry['description'] ?? '-',
            'hours' => round((float)$entry['hours'], 2),
            'hours_formatted' => formatHours($entry['duration_seconds']),
            'bill_rate' => (float)$entry['bill_rate'],
            'bill_rate_formatted' => '$' . number_format($entry['bill_rate'], 2),
            'pay_rate' => (float)$entry['pay_rate'],
            'pay_rate_formatted' => '$' . number_format($entry['pay_rate'], 2),
            'bill_amount' => (float)$entry['bill_amount'],
            'bill_amount_formatted' => '$' . number_format($entry['bill_amount'], 2),
            'pay_amount' => (float)$entry['pay_amount'],
            'pay_amount_formatted' => '$' . number_format($entry['pay_amount'], 2),
            'profit_amount' => (float)$entry['profit_amount'],
            'profit_amount_formatted' => '$' . number_format($entry['profit_amount'], 2)
        ];
    }

    // Get totals
    unset($filters['limit'], $filters['offset']);
    $totals = $billing->calculateTotals($filters);

    echo json_encode([
        'success' => true,
        'entries' => $formatted,
        'totals' => [
            'entry_count' => $totals['entry_count'],
            'total_hours' => round($totals['total_hours'], 2),
            'total_hours_formatted' => number_format($totals['total_hours'], 2),
            'total_bill_amount' => $totals['total_bill_amount'],
            'total_bill_amount_formatted' => '$' . number_format($totals['total_bill_amount'], 2),
            'total_pay_amount' => $totals['total_pay_amount'],
            'total_pay_amount_formatted' => '$' . number_format($totals['total_pay_amount'], 2),
            'total_profit' => $totals['total_profit'],
            'total_profit_formatted' => '$' . number_format($totals['total_profit'], 2)
        ],
        'date_range' => $dateRange
    ]);
}

/**
 * Get filtered time entries grouped by user
 */
function handleGetEntriesGrouped($billing, $input) {
    $period = $input['period'] ?? 'this_month';
    $customStart = $input['start_date'] ?? null;
    $customEnd = $input['end_date'] ?? null;

    // Get date range
    $dateRange = $billing->getDateRangeForPeriod($period, $customStart, $customEnd);

    // Validate custom date range
    if ($period === 'custom') {
        $start = new DateTime($dateRange['start']);
        $end = new DateTime($dateRange['end']);
        if ($end < $start) {
            echo json_encode(['success' => false, 'message' => 'End date must be after start date.']);
            return;
        }
    }

    $filters = [
        'start_date' => $dateRange['start'],
        'end_date' => $dateRange['end']
    ];

    // Client filter
    if (!empty($input['client_id'])) {
        if (is_array($input['client_id'])) {
            $filters['client_id'] = array_map('intval', $input['client_id']);
        } else {
            $filters['client_id'] = (int)$input['client_id'];
        }

        // Get users associated with this client for aggregation
        if (!is_array($input['client_id'])) {
            $clientUsers = $billing->getUsersByClient((int)$input['client_id']);
            if (!empty($clientUsers)) {
                $filters['client_users'] = $clientUsers;
            }
        }
    }

    // Employee filter
    if (!empty($input['user_id'])) {
        $filters['user_id'] = $input['user_id'];
    }

    // Use lightweight getUserSummaries - entries loaded on demand
    $groupedData = $billing->getUserSummaries($filters);

    // Format the response (without entries - lazy loaded)
    $formatted = [];
    foreach ($groupedData as $user) {
        $formatted[] = [
            'user_id' => $user['user_id'],
            'employee_name' => $user['employee_name'] ?? 'Unknown',
            'employee_email' => $user['employee_email'] ?? '',
            'current_bill_rate' => (float)($user['current_bill_rate'] ?? 0),
            'current_bill_rate_formatted' => '$' . number_format($user['current_bill_rate'] ?? 0, 2),
            'current_pay_rate' => (float)($user['current_pay_rate'] ?? 0),
            'current_pay_rate_formatted' => '$' . number_format($user['current_pay_rate'] ?? 0, 2),
            'entry_count' => (int)$user['entry_count'],
            'total_hours' => round((float)$user['total_hours'], 2),
            'total_hours_formatted' => number_format($user['total_hours'], 2),
            'total_bill_amount' => round((float)$user['total_bill_amount'], 2),
            'total_bill_amount_formatted' => '$' . number_format($user['total_bill_amount'], 2),
            'total_pay_amount' => round((float)$user['total_pay_amount'], 2),
            'total_pay_amount_formatted' => '$' . number_format($user['total_pay_amount'], 2),
            'total_profit' => round((float)$user['total_profit'], 2),
            'total_profit_formatted' => '$' . number_format($user['total_profit'], 2),
            'entries' => [] // Lazy loaded via get_user_entries
        ];
    }

    // Get overall totals
    $totals = $billing->calculateTotals($filters);

    echo json_encode([
        'success' => true,
        'users' => $formatted,
        'user_count' => count($formatted),
        'totals' => [
            'entry_count' => $totals['entry_count'],
            'total_hours' => round($totals['total_hours'], 2),
            'total_hours_formatted' => number_format($totals['total_hours'], 2),
            'total_bill_amount' => $totals['total_bill_amount'],
            'total_bill_amount_formatted' => '$' . number_format($totals['total_bill_amount'], 2),
            'total_pay_amount' => $totals['total_pay_amount'],
            'total_pay_amount_formatted' => '$' . number_format($totals['total_pay_amount'], 2),
            'total_profit' => $totals['total_profit'],
            'total_profit_formatted' => '$' . number_format($totals['total_profit'], 2)
        ],
        'date_range' => $dateRange
    ]);
}

/**
 * Get totals only
 */
function handleGetTotals($billing, $input) {
    $period = $input['period'] ?? 'this_month';
    $customStart = $input['start_date'] ?? null;
    $customEnd = $input['end_date'] ?? null;

    $dateRange = $billing->getDateRangeForPeriod($period, $customStart, $customEnd);

    $filters = [
        'start_date' => $dateRange['start'],
        'end_date' => $dateRange['end']
    ];

    if (!empty($input['client_id'])) {
        if (is_array($input['client_id'])) {
            $filters['client_id'] = array_map('intval', $input['client_id']);
        } else {
            $filters['client_id'] = (int)$input['client_id'];
            $clientUsers = $billing->getUsersByClient((int)$input['client_id']);
            if (!empty($clientUsers)) {
                $filters['client_users'] = $clientUsers;
            }
        }
    }

    if (!empty($input['user_id'])) {
        $filters['user_id'] = $input['user_id'];
    }

    $totals = $billing->calculateTotals($filters);

    echo json_encode([
        'success' => true,
        'totals' => [
            'entry_count' => $totals['entry_count'],
            'total_hours' => round($totals['total_hours'], 2),
            'total_hours_formatted' => number_format($totals['total_hours'], 2),
            'total_bill_amount' => $totals['total_bill_amount'],
            'total_bill_amount_formatted' => '$' . number_format($totals['total_bill_amount'], 2),
            'total_pay_amount' => $totals['total_pay_amount'],
            'total_pay_amount_formatted' => '$' . number_format($totals['total_pay_amount'], 2),
            'total_profit' => $totals['total_profit'],
            'total_profit_formatted' => '$' . number_format($totals['total_profit'], 2)
        ],
        'date_range' => $dateRange
    ]);
}

/**
 * Get clients for dropdown
 */
function handleGetClients($billing) {
    $clients = $billing->getClients();

    echo json_encode([
        'success' => true,
        'clients' => $clients
    ]);
}

/**
 * Get employees for dropdown
 */
function handleGetEmployees($billing) {
    $employees = $billing->getEmployees();

    $formatted = [];
    foreach ($employees as $emp) {
        $formatted[] = [
            'user_id' => $emp['user_id'],
            'full_name' => $emp['full_name'],
            'email' => $emp['email'],
            'bill_rate' => $emp['current_bill_rate'],
            'pay_rate' => $emp['current_pay_rate']
        ];
    }

    echo json_encode([
        'success' => true,
        'employees' => $formatted
    ]);
}

/**
 * Manual sync trigger
 */
function handleSyncNow($db, $billing) {
    global $db;

    // Check manage permission
    if (!has_permission('timeworks_billing_manage')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    // Load API
    require_once dirname(__DIR__) . '/models/TimeWorksAPI.php';
    $api = new TimeWorksAPI($db);
    $billing->setApi($api);

    $syncDays = 30;
    $startDate = date('Y-m-d', strtotime("-{$syncDays} days"));
    $endDate = date('Y-m-d');

    $stats = [
        'users_processed' => 0,
        'rates_synced' => 0,
        'entries_synced' => 0
    ];

    // Get active users
    $stmt = $db->query("SELECT user_id FROM twr_users WHERE status = 'active' LIMIT 100");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($users as $userId) {
        try {
            // Get user details
            $userDetails = $api->getUser($userId);
            if (!$userDetails) continue;

            // Sync rates
            $billRates = $userDetails['billrates'] ?? [];
            $payRates = $userDetails['payrates'] ?? [];
            $rateStats = $billing->syncUserRates($userId, $billRates, $payRates);
            $stats['rates_synced'] += $rateStats['bill_added'] + $rateStats['pay_added'];

            // Get time sheet
            $timeSheet = $api->getUserTimeSheet($userId, $startDate, $endDate, 'America/New_York', 100, 0);
            if ($timeSheet && isset($timeSheet['report'])) {
                $entriesCount = $billing->syncTimeEntries($userId, $timeSheet['report'], $billRates, $payRates);
                $stats['entries_synced'] += $entriesCount;
            }

            $stats['users_processed']++;

        } catch (Exception $e) {
            error_log("Sync error for user {$userId}: " . $e->getMessage());
        }
    }

    $billing->logSync('billing_entries', $stats['entries_synced'], 'success', 'Manual sync');

    echo json_encode([
        'success' => true,
        'message' => 'Sync completed',
        'stats' => $stats
    ]);
}

/**
 * Export to Excel
 */
function handleExcelExport($db, $billing, $params) {
    $period = $params['period'] ?? 'this_month';
    $customStart = $params['start_date'] ?? null;
    $customEnd = $params['end_date'] ?? null;

    $dateRange = $billing->getDateRangeForPeriod($period, $customStart, $customEnd);

    $filters = [
        'start_date' => $dateRange['start'],
        'end_date' => $dateRange['end']
    ];

    if (!empty($params['client_id'])) {
        $filters['client_id'] = (int)$params['client_id'];
    }
    if (!empty($params['user_id'])) {
        $filters['user_id'] = $params['user_id'];
    }

    $entries = $billing->getTimeEntries($filters);
    $totals = $billing->calculateTotals($filters);

    // Generate CSV (Excel compatible)
    $filename = 'billing_report_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, [
        'Service Date',
        'Employee',
        'Email',
        'Client',
        'Description',
        'Hours',
        'Bill Rate',
        'Pay Rate',
        'Bill Amount',
        'Pay Amount',
        'Profit'
    ]);

    // Data rows
    foreach ($entries as $entry) {
        fputcsv($output, [
            date('m/d/Y', strtotime($entry['entry_date'])),
            $entry['employee_name'] ?? '',
            $entry['employee_email'] ?? '',
            $entry['client_name'] ?? '',
            $entry['description'] ?? '',
            round($entry['hours'], 2),
            '$' . number_format($entry['bill_rate'], 2),
            '$' . number_format($entry['pay_rate'], 2),
            '$' . number_format($entry['bill_amount'], 2),
            '$' . number_format($entry['pay_amount'], 2),
            '$' . number_format($entry['profit_amount'], 2)
        ]);
    }

    // Totals row
    fputcsv($output, []);
    fputcsv($output, [
        'TOTALS',
        '',
        '',
        '',
        '',
        number_format($totals['total_hours'], 2),
        '',
        '',
        '$' . number_format($totals['total_bill_amount'], 2),
        '$' . number_format($totals['total_pay_amount'], 2),
        '$' . number_format($totals['total_profit'], 2)
    ]);

    fclose($output);
}

/**
 * Get entries for a specific user (lazy loading)
 */
function handleGetUserEntries($billing, $input) {
    $userId = $input['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'user_id is required']);
        return;
    }

    $period = $input['period'] ?? 'this_month';
    $customStart = $input['start_date'] ?? null;
    $customEnd = $input['end_date'] ?? null;

    $dateRange = $billing->getDateRangeForPeriod($period, $customStart, $customEnd);

    $filters = [
        'start_date' => $dateRange['start'],
        'end_date' => $dateRange['end'],
        'user_id' => $userId
    ];

    $entries = $billing->getTimeEntries($filters);

    $formattedEntries = [];
    foreach ($entries as $entry) {
        $formattedEntries[] = [
            'id' => $entry['id'],
            'entry_date' => date('m/d/Y', strtotime($entry['entry_date'])),
            'entry_date_raw' => $entry['entry_date'],
            'client_name' => $entry['client_name'] ?? '-',
            'project_name' => $entry['project_name'] ?? '-',
            'description' => $entry['description'] ?? '-',
            'hours' => round((float)$entry['hours'], 2),
            'bill_rate' => (float)$entry['bill_rate'],
            'bill_rate_formatted' => '$' . number_format($entry['bill_rate'], 2),
            'pay_rate' => (float)$entry['pay_rate'],
            'pay_rate_formatted' => '$' . number_format($entry['pay_rate'], 2),
            'bill_amount' => (float)$entry['bill_amount'],
            'bill_amount_formatted' => '$' . number_format($entry['bill_amount'], 2),
            'pay_amount' => (float)$entry['pay_amount'],
            'pay_amount_formatted' => '$' . number_format($entry['pay_amount'], 2),
            'profit_amount' => (float)$entry['profit_amount'],
            'profit_amount_formatted' => '$' . number_format($entry['profit_amount'], 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'entries' => $formattedEntries,
        'entry_count' => count($formattedEntries)
    ]);
}

/**
 * Format seconds to HH:MM:SS
 */
function formatHours($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
}
