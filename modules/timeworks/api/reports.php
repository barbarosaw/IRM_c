<?php
/**
 * TimeWorks Module - Reports API
 *
 * API endpoint for generating various attendance reports.
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

// Check permission
if (!has_permission('timeworks_reports_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Handle GET exports
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'export' || $action === 'export_period') {
        handleExport($db, $_GET);
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
        case 'category_report':
            $categoryId = $input['category_id'] ?? null;
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-d');

            // Get users with optional category filter
            $userSql = "
                SELECT DISTINCT u.user_id, u.full_name,
                       GROUP_CONCAT(DISTINCT cd.name) as categories
                FROM twr_users u
                LEFT JOIN twr_user_categories uc ON u.user_id = uc.user_id
                LEFT JOIN twr_category_definitions cd ON uc.category_code = cd.code
                WHERE u.status = 'active'
            ";

            $params = [];
            if ($categoryId) {
                $userSql .= " AND cd.id = ?";
                $params[] = $categoryId;
            }

            $userSql .= " GROUP BY u.user_id ORDER BY u.full_name";

            $stmt = $db->prepare($userSql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            $result = [];
            $totalHours = 0;
            $totalLate = 0;
            $totalDays = 0;

            foreach ($users as $user) {
                // Get daily reports for this user in date range
                $stmt = $db->prepare("
                    SELECT
                        COUNT(*) as days_worked,
                        COALESCE(SUM(worked_hours), 0) as total_hours,
                        COALESCE(SUM(scheduled_hours), 0) as scheduled_hours,
                        SUM(CASE WHEN shift_status = 'on_time' OR shift_status = 'completed' THEN 1 ELSE 0 END) as on_time_count,
                        SUM(CASE WHEN shift_status = 'late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN shift_status = 'absent' THEN 1 ELSE 0 END) as absent_count
                    FROM twr_daily_report_details
                    WHERE user_id = ? AND report_date BETWEEN ? AND ?
                ");
                $stmt->execute([$user['user_id'], $startDate, $endDate]);
                $stats = $stmt->fetch();

                // If no daily report data, try to calculate from shifts
                if (!$stats || $stats['days_worked'] == 0) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as scheduled_days
                        FROM twr_user_shifts
                        WHERE user_id = ? AND is_off = 0
                    ");
                    $stmt->execute([$user['user_id']]);
                    $shiftData = $stmt->fetch();
                    $stats['scheduled_days'] = $shiftData['scheduled_days'] ?? 0;
                } else {
                    $stats['scheduled_days'] = $stats['on_time_count'] + $stats['late_count'] + $stats['absent_count'];
                }

                $result[] = array_merge($user, $stats);
                $totalHours += $stats['total_hours'] ?? 0;
                $totalLate += $stats['late_count'] ?? 0;
                $totalDays += $stats['days_worked'] ?? 0;
            }

            // Get chart data
            $chartData = getChartData($db, $categoryId, $startDate, $endDate);

            echo json_encode([
                'success' => true,
                'data' => $result,
                'summary' => [
                    'total_users' => count($users),
                    'total_hours' => $totalHours,
                    'late_count' => $totalLate,
                    'avg_hours_per_day' => $totalDays > 0 ? $totalHours / $totalDays : 0
                ],
                'chart_data' => $chartData
            ]);
            break;

        case 'period_report':
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-d');
            $userId = $input['user_id'] ?? null;

            // Generate date range
            $dates = [];
            $current = new DateTime($startDate);
            $end = new DateTime($endDate);
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }

            // Get users
            $userSql = "SELECT user_id, full_name FROM twr_users WHERE status = 'active'";
            $params = [];
            if ($userId) {
                $userSql .= " AND user_id = ?";
                $params[] = $userId;
            }
            $userSql .= " ORDER BY full_name";

            $stmt = $db->prepare($userSql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            // Get daily data
            $dailyData = [];
            $userStats = [];

            foreach ($users as &$user) {
                $user['days_worked'] = 0;
                $user['total_hours'] = 0;
                $user['on_time_count'] = 0;
                $user['late_count'] = 0;
                $user['absent_count'] = 0;
                $user['total_late_minutes'] = 0;

                $stmt = $db->prepare("
                    SELECT report_date as date, worked_hours as hours, shift_status as status, late_minutes
                    FROM twr_daily_report_details
                    WHERE user_id = ? AND report_date BETWEEN ? AND ?
                ");
                $stmt->execute([$user['user_id'], $startDate, $endDate]);
                $dayRecords = $stmt->fetchAll();

                foreach ($dayRecords as $record) {
                    $dailyData[] = [
                        'user_id' => $user['user_id'],
                        'date' => $record['date'],
                        'hours' => $record['hours'],
                        'status' => $record['status']
                    ];

                    if ($record['hours'] > 0) {
                        $user['days_worked']++;
                        $user['total_hours'] += $record['hours'];
                    }

                    if (in_array($record['status'], ['on_time', 'completed'])) {
                        $user['on_time_count']++;
                    } elseif ($record['status'] === 'late') {
                        $user['late_count']++;
                        $user['total_late_minutes'] += $record['late_minutes'] ?? 0;
                    } elseif ($record['status'] === 'absent') {
                        $user['absent_count']++;
                    }
                }
            }

            // Calculate summary
            $summary = [
                'total_users' => count($users),
                'total_hours' => array_sum(array_column($users, 'total_hours')),
                'working_days' => count($dates),
                'on_time_count' => array_sum(array_column($users, 'on_time_count')),
                'late_count' => array_sum(array_column($users, 'late_count')),
                'absent_count' => array_sum(array_column($users, 'absent_count'))
            ];

            echo json_encode([
                'success' => true,
                'dates' => $dates,
                'users' => $users,
                'daily' => $dailyData,
                'summary' => $summary
            ]);
            break;

        case 'client_report':
            $clientId = $input['client_id'] ?? null;
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-d');

            if (!$clientId) {
                throw new Exception('Client ID is required');
            }

            // Get users assigned to client
            $stmt = $db->prepare("
                SELECT u.user_id, u.full_name, u.email
                FROM twr_users u
                JOIN twr_user_clients uc ON u.user_id = uc.user_id
                WHERE uc.client_id = ? AND u.status = 'active'
                ORDER BY u.full_name
            ");
            $stmt->execute([$clientId]);
            $users = $stmt->fetchAll();

            $result = [];
            foreach ($users as $user) {
                $stmt = $db->prepare("
                    SELECT
                        COUNT(*) as days_worked,
                        COALESCE(SUM(worked_hours), 0) as total_hours,
                        SUM(CASE WHEN shift_status IN ('on_time', 'completed') THEN 1 ELSE 0 END) as on_time_count,
                        SUM(CASE WHEN shift_status = 'late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN shift_status = 'absent' THEN 1 ELSE 0 END) as absent_count
                    FROM twr_daily_report_details
                    WHERE user_id = ? AND report_date BETWEEN ? AND ?
                ");
                $stmt->execute([$user['user_id'], $startDate, $endDate]);
                $stats = $stmt->fetch();

                $result[] = array_merge($user, $stats);
            }

            // Get client info
            $stmt = $db->prepare("SELECT * FROM twr_clients WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'client' => $client,
                'data' => $result,
                'date_range' => ['start' => $startDate, 'end' => $endDate]
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

/**
 * Get chart data for category report
 */
function getChartData($db, $categoryId, $startDate, $endDate)
{
    // Category hours breakdown
    $stmt = $db->prepare("
        SELECT cd.name, cd.color_code,
               COALESCE(SUM(drd.worked_hours), 0) as hours
        FROM twr_category_definitions cd
        LEFT JOIN twr_user_categories uc ON cd.code = uc.category_code
        LEFT JOIN twr_daily_report_details drd ON uc.user_id = drd.user_id
            AND drd.report_date BETWEEN ? AND ?
        WHERE cd.is_active = 1
        GROUP BY cd.id, cd.name, cd.color_code
        ORDER BY cd.sort_order
    ");
    $stmt->execute([$startDate, $endDate]);
    $categoryData = $stmt->fetchAll();

    // Daily trend
    $stmt = $db->prepare("
        SELECT
            report_date,
            SUM(CASE WHEN shift_status IN ('on_time', 'completed') THEN 1 ELSE 0 END) as on_time,
            SUM(CASE WHEN shift_status = 'late' THEN 1 ELSE 0 END) as late
        FROM twr_daily_report_details
        WHERE report_date BETWEEN ? AND ?
        GROUP BY report_date
        ORDER BY report_date
    ");
    $stmt->execute([$startDate, $endDate]);
    $trendData = $stmt->fetchAll();

    return [
        'category_labels' => array_column($categoryData, 'name'),
        'category_hours' => array_column($categoryData, 'hours'),
        'category_colors' => array_column($categoryData, 'color_code'),
        'date_labels' => array_column($trendData, 'report_date'),
        'on_time_trend' => array_column($trendData, 'on_time'),
        'late_trend' => array_column($trendData, 'late')
    ];
}

/**
 * Handle export requests
 */
function handleExport($db, $params)
{
    $format = $params['format'] ?? 'excel';
    $startDate = $params['start_date'] ?? date('Y-m-01');
    $endDate = $params['end_date'] ?? date('Y-m-d');
    $categoryId = $params['category_id'] ?? null;
    $userId = $params['user_id'] ?? null;

    // Get data
    $userSql = "
        SELECT u.user_id, u.full_name, u.email,
               GROUP_CONCAT(DISTINCT cd.name) as categories
        FROM twr_users u
        LEFT JOIN twr_user_categories uc ON u.user_id = uc.user_id
        LEFT JOIN twr_category_definitions cd ON uc.category_code = cd.code
        WHERE u.status = 'active'
    ";

    $userParams = [];
    if ($categoryId) {
        $userSql .= " AND cd.id = ?";
        $userParams[] = $categoryId;
    }
    if ($userId) {
        $userSql .= " AND u.user_id = ?";
        $userParams[] = $userId;
    }
    $userSql .= " GROUP BY u.user_id ORDER BY u.full_name";

    $stmt = $db->prepare($userSql);
    $stmt->execute($userParams);
    $users = $stmt->fetchAll();

    $data = [];
    foreach ($users as $user) {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as days_worked,
                COALESCE(SUM(worked_hours), 0) as total_hours,
                COALESCE(SUM(scheduled_hours), 0) as scheduled_hours,
                SUM(CASE WHEN shift_status IN ('on_time', 'completed') THEN 1 ELSE 0 END) as on_time_count,
                SUM(CASE WHEN shift_status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN shift_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                COALESCE(SUM(late_minutes), 0) as total_late_minutes
            FROM twr_daily_report_details
            WHERE user_id = ? AND report_date BETWEEN ? AND ?
        ");
        $stmt->execute([$user['user_id'], $startDate, $endDate]);
        $stats = $stmt->fetch();

        $totalShifts = $stats['on_time_count'] + $stats['late_count'] + $stats['absent_count'];
        $attendance = $totalShifts > 0 ?
            round(($stats['on_time_count'] + $stats['late_count']) / $totalShifts * 100, 1) : 0;

        $data[] = [
            'Name' => $user['full_name'],
            'Email' => $user['email'],
            'Categories' => $user['categories'] ?: 'Uncategorized',
            'Days Worked' => $stats['days_worked'],
            'Total Hours' => round($stats['total_hours'], 1),
            'Scheduled Hours' => round($stats['scheduled_hours'], 1),
            'On Time' => $stats['on_time_count'],
            'Late' => $stats['late_count'],
            'Absent' => $stats['absent_count'],
            'Attendance %' => $attendance,
            'Late Minutes' => $stats['total_late_minutes']
        ];
    }

    // Export as CSV (Excel compatible)
    $filename = "attendance_report_{$startDate}_to_{$endDate}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }

    // Data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
}
