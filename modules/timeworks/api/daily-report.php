<?php
/**
 * TimeWorks Module - Daily Report API
 *
 * Chunk-based processing for daily attendance reports
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../../includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Permission check
if (!has_permission('timeworks_daily_report_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Set timezone to EST
date_default_timezone_set('America/New_York');

// Define system constant for TimeWorksAPI
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

require_once dirname(__DIR__) . '/models/TimeWorksAPI.php';
$api = new TimeWorksAPI($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_users_count':
        getUsersCount();
        break;
    case 'process_chunk':
        processChunk();
        break;
    case 'finalize_report':
        finalizeReport();
        break;
    case 'get_saved_report':
        getSavedReport();
        break;
    case 'get_notices':
        getNotices();
        break;
    case 'save_notice':
        saveNotice();
        break;
    case 'delete_notice':
        deleteNotice();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Get total count of users to process
 */
function getUsersCount() {
    global $db;

    $date = $_POST['date'] ?? date('Y-m-d');
    $dayOfWeek = date('l', strtotime($date));

    // Count active users who have a schedule for this day (not off)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.user_id) as total
        FROM twr_users u
        LEFT JOIN twr_user_shifts s ON u.user_id = s.user_id AND s.day_of_week = ?
        WHERE u.status = 'active'
    ");
    $stmt->execute([$dayOfWeek]);
    $result = $stmt->fetch();

    // Count users who are off
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.user_id) as off_count
        FROM twr_users u
        JOIN twr_user_shifts s ON u.user_id = s.user_id AND s.day_of_week = ? AND s.is_off = 1
        WHERE u.status = 'active'
    ");
    $stmt->execute([$dayOfWeek]);
    $offResult = $stmt->fetch();

    // Count users with no schedule
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.user_id) as no_schedule
        FROM twr_users u
        LEFT JOIN twr_user_shifts s ON u.user_id = s.user_id AND s.day_of_week = ?
        WHERE u.status = 'active' AND s.id IS NULL
    ");
    $stmt->execute([$dayOfWeek]);
    $noScheduleResult = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'total' => (int)$result['total'],
        'off_count' => (int)$offResult['off_count'],
        'no_schedule' => (int)$noScheduleResult['no_schedule'],
        'day_of_week' => $dayOfWeek
    ]);
}

/**
 * Process a chunk of users
 */
function processChunk() {
    global $db, $api;

    $date = $_POST['date'] ?? date('Y-m-d');
    $offset = (int)($_POST['offset'] ?? 0);
    $limit = (int)($_POST['limit'] ?? 50);
    $dayOfWeek = date('l', strtotime($date));

    // Get users for this chunk
    $stmt = $db->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.email,
            s.start_time,
            s.end_time,
            s.is_off
        FROM twr_users u
        LEFT JOIN twr_user_shifts s ON u.user_id = s.user_id AND s.day_of_week = ?
        WHERE u.status = 'active'
        ORDER BY u.full_name ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$dayOfWeek, $limit, $offset]);
    $users = $stmt->fetchAll();

    // Get notices for this date
    $stmt = $db->prepare("
        SELECT user_id, notice_type, reason
        FROM twr_user_notices
        WHERE notice_date = ?
    ");
    $stmt->execute([$date]);
    $noticesRaw = $stmt->fetchAll();
    $notices = [];
    foreach ($noticesRaw as $n) {
        $notices[$n['user_id']] = $n;
    }

    $results = [];
    $stats = [
        'on_time' => 0,
        'late_with_notice' => 0,
        'late_without_notice' => 0,
        'absent_with_notice' => 0,
        'absent_without_notice' => 0,
        'pto' => 0,
        'unpaid_to' => 0,
        'flexible' => 0,
        'off' => 0,
        'no_schedule' => 0,
        'scheduled_hours' => 0,
        'worked_hours' => 0
    ];

    foreach ($users as $user) {
        $userResult = [
            'user_id' => $user['user_id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'scheduled_start' => $user['start_time'],
            'scheduled_end' => $user['end_time'],
            'scheduled_hours' => 0,
            'actual_start' => null,
            'actual_end' => null,
            'worked_hours' => 0,
            'status' => 'absent_without_notice',
            'late_minutes' => 0,
            'notice_type' => null,
            'notes' => null
        ];

        // Check if user is off for this day
        if ($user['is_off']) {
            $userResult['status'] = 'off';
            $stats['off']++;
            $results[] = $userResult;
            continue;
        }

        // Check if user has a defined schedule
        $hasSchedule = $user['start_time'] && $user['end_time'];

        // Calculate scheduled hours (only if has schedule)
        if ($hasSchedule) {
            $startTime = strtotime($user['start_time']);
            $endTime = strtotime($user['end_time']);

            // If end time is before start time, shift crosses midnight - add 24 hours
            if ($endTime <= $startTime) {
                $endTime += 86400; // Add 24 hours in seconds
            }

            $scheduledHours = ($endTime - $startTime) / 3600;
            $userResult['scheduled_hours'] = $scheduledHours;
            $stats['scheduled_hours'] += $scheduledHours;
        }

        // Check for notice (PTO, Unpaid TO, etc.) - only for scheduled users
        if ($hasSchedule && isset($notices[$user['user_id']])) {
            $notice = $notices[$user['user_id']];
            $userResult['notice_type'] = $notice['notice_type'];
            $userResult['notes'] = $notice['reason'];

            switch ($notice['notice_type']) {
                case 'pto':
                    $userResult['status'] = 'pto';
                    $stats['pto']++;
                    $results[] = $userResult;
                    continue 2;
                case 'unpaid_to':
                    $userResult['status'] = 'unpaid_to';
                    $stats['unpaid_to']++;
                    $results[] = $userResult;
                    continue 2;
                case 'sick_leave':
                    $userResult['status'] = 'absent_with_notice';
                    $stats['absent_with_notice']++;
                    $results[] = $userResult;
                    continue 2;
                case 'absent_notice':
                    // Will be processed below if user has time entries
                    break;
                case 'late_notice':
                    // Will be processed below
                    break;
            }
        }

        // Fetch time entries from API
        $apiResult = $api->getUserDailyReport($user['user_id'], $date, 'America/New_York');

        // API returns report keyed by date, e.g. $apiResult['report']['2025-12-17']
        $hasActivity = false;

        if ($apiResult && isset($apiResult['report'][$date]) && !empty($apiResult['report'][$date])) {
            $report = $apiResult['report'][$date];
            $entries = $report['entries'] ?? [];

            // Check if user actually has time entries (not just an empty report)
            if (!empty($entries)) {
                $hasActivity = true;

                // Calculate worked hours from entries (sum of each entry's duration)
                $totalWorkedSeconds = 0;
                foreach ($entries as $entry) {
                    if (isset($entry['started_at']) && isset($entry['stopped_at'])) {
                        $entryStart = strtotime($entry['started_at']);
                        $entryEnd = strtotime($entry['stopped_at']);

                        // Handle entries that span midnight
                        if ($entryEnd < $entryStart) {
                            $entryEnd += 86400; // Add 24 hours
                        }

                        $totalWorkedSeconds += ($entryEnd - $entryStart);
                    } elseif (isset($entry['started_at']) && !isset($entry['stopped_at'])) {
                        // Entry still running - calculate from start to now
                        $entryStart = strtotime($entry['started_at']);
                        $now = time();
                        $totalWorkedSeconds += ($now - $entryStart);
                    }
                }

                // Use our calculated value (more accurate for overnight entries)
                // Fallback to API value if our calculation is 0
                $workedSeconds = $totalWorkedSeconds > 0 ? $totalWorkedSeconds : ($report['total_user_duration_seconds'] ?? 0);
                $userResult['worked_hours'] = round($workedSeconds / 3600, 2);
                $stats['worked_hours'] += $userResult['worked_hours'];

                // First entry's started_at is the first check-in
                $firstEntry = reset($entries);
                $lastEntry = end($entries);

                if (isset($firstEntry['started_at'])) {
                    $firstCheckIn = $firstEntry['started_at'];
                    $userResult['actual_start'] = date('H:i:s', strtotime($firstCheckIn));

                    // Determine status based on schedule
                    if ($hasSchedule) {
                        // Has schedule - check if late (more than 5 minutes after scheduled start)
                        $scheduledStart = strtotime($date . ' ' . $user['start_time']);
                        $actualStart = strtotime($firstCheckIn);
                        $lateMinutes = ($actualStart - $scheduledStart) / 60;

                        if ($lateMinutes > 5) {
                            $userResult['late_minutes'] = (int)$lateMinutes;

                            // Check if they have late notice
                            if (isset($notices[$user['user_id']]) && $notices[$user['user_id']]['notice_type'] === 'late_notice') {
                                $userResult['status'] = 'late_with_notice';
                                $stats['late_with_notice']++;
                            } else {
                                $userResult['status'] = 'late_without_notice';
                                $stats['late_without_notice']++;
                            }
                        } else {
                            $userResult['status'] = 'on_time';
                            $stats['on_time']++;
                        }
                    } else {
                        // No schedule but has activity = flexible schedule
                        $userResult['status'] = 'flexible';
                        $stats['flexible']++;
                    }
                }

                // Last entry's stopped_at is the last check-out
                if (isset($lastEntry['stopped_at'])) {
                    $userResult['actual_end'] = date('H:i:s', strtotime($lastEntry['stopped_at']));
                }
            }
        }

        // No activity - determine status
        if (!$hasActivity) {
            if ($hasSchedule) {
                // Has schedule but no activity = absent
                if (isset($notices[$user['user_id']]) && $notices[$user['user_id']]['notice_type'] === 'absent_notice') {
                    $userResult['status'] = 'absent_with_notice';
                    $stats['absent_with_notice']++;
                } else {
                    $userResult['status'] = 'absent_without_notice';
                    $stats['absent_without_notice']++;
                }
            } else {
                // No schedule and no activity = no_schedule
                $userResult['status'] = 'no_schedule';
                $stats['no_schedule']++;
            }
        }

        $results[] = $userResult;
    }

    echo json_encode([
        'success' => true,
        'processed' => count($users),
        'results' => $results,
        'stats' => $stats
    ]);
}

/**
 * Finalize and save the report
 */
function finalizeReport() {
    global $db;

    $date = $_POST['date'] ?? date('Y-m-d');
    $dayOfWeek = date('l', strtotime($date));
    $stats = json_decode($_POST['stats'] ?? '{}', true);
    $details = json_decode($_POST['details'] ?? '[]', true);

    if (!$stats || !$details) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }

    try {
        $db->beginTransaction();

        // Calculate rates
        $totalScheduled = ($stats['on_time'] ?? 0) +
                         ($stats['late_with_notice'] ?? 0) +
                         ($stats['late_without_notice'] ?? 0) +
                         ($stats['absent_with_notice'] ?? 0) +
                         ($stats['absent_without_notice'] ?? 0);

        $totalAbsent = ($stats['absent_with_notice'] ?? 0) + ($stats['absent_without_notice'] ?? 0);
        $totalLate = ($stats['late_with_notice'] ?? 0) + ($stats['late_without_notice'] ?? 0);

        $absenteeismRate = $totalScheduled > 0 ? ($totalAbsent / $totalScheduled) * 100 : 0;
        $tardinessRate = $totalScheduled > 0 ? ($totalLate / $totalScheduled) * 100 : 0;

        $scheduledHours = $stats['scheduled_hours'] ?? 0;
        $workedHours = $stats['worked_hours'] ?? 0;
        $lostHours = max(0, $scheduledHours - $workedHours);
        $shrinkageRate = $scheduledHours > 0 ? ($lostHours / $scheduledHours) * 100 : 0;

        // Delete existing report for this date
        $stmt = $db->prepare("DELETE FROM twr_daily_reports WHERE report_date = ?");
        $stmt->execute([$date]);

        $stmt = $db->prepare("DELETE FROM twr_daily_report_details WHERE report_date = ?");
        $stmt->execute([$date]);

        // Insert new report
        $stmt = $db->prepare("
            INSERT INTO twr_daily_reports (
                report_date, day_of_week, total_users, total_scheduled, total_off,
                on_time, late_with_notice, late_without_notice,
                absent_with_notice, absent_without_notice, pto, unpaid_to, flexible,
                scheduled_hours, worked_hours, lost_hours,
                absenteeism_rate, tardiness_rate, shrinkage_rate,
                generated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $totalUsers = count($details);
        $totalOff = ($stats['off'] ?? 0) + ($stats['no_schedule'] ?? 0);

        $stmt->execute([
            $date,
            $dayOfWeek,
            $totalUsers,
            $totalScheduled,
            $totalOff,
            $stats['on_time'] ?? 0,
            $stats['late_with_notice'] ?? 0,
            $stats['late_without_notice'] ?? 0,
            $stats['absent_with_notice'] ?? 0,
            $stats['absent_without_notice'] ?? 0,
            $stats['pto'] ?? 0,
            $stats['unpaid_to'] ?? 0,
            $stats['flexible'] ?? 0,
            $scheduledHours,
            $workedHours,
            $lostHours,
            $absenteeismRate,
            $tardinessRate,
            $shrinkageRate,
            $_SESSION['user_id']
        ]);

        $reportId = $db->lastInsertId();

        // Insert details
        $stmt = $db->prepare("
            INSERT INTO twr_daily_report_details (
                report_id, report_date, user_id, full_name, email,
                scheduled_start, scheduled_end, scheduled_hours,
                actual_start, actual_end, worked_hours,
                status, late_minutes, notice_type, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($details as $detail) {
            $stmt->execute([
                $reportId,
                $date,
                $detail['user_id'],
                $detail['full_name'],
                $detail['email'],
                $detail['scheduled_start'],
                $detail['scheduled_end'],
                $detail['scheduled_hours'],
                $detail['actual_start'],
                $detail['actual_end'],
                $detail['worked_hours'],
                $detail['status'],
                $detail['late_minutes'] ?? 0,
                $detail['notice_type'],
                $detail['notes']
            ]);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'report_id' => $reportId,
            'summary' => [
                'total_users' => $totalUsers,
                'total_scheduled' => $totalScheduled,
                'total_off' => $totalOff,
                'on_time' => $stats['on_time'] ?? 0,
                'late_with_notice' => $stats['late_with_notice'] ?? 0,
                'late_without_notice' => $stats['late_without_notice'] ?? 0,
                'absent_with_notice' => $stats['absent_with_notice'] ?? 0,
                'absent_without_notice' => $stats['absent_without_notice'] ?? 0,
                'pto' => $stats['pto'] ?? 0,
                'unpaid_to' => $stats['unpaid_to'] ?? 0,
                'flexible' => $stats['flexible'] ?? 0,
                'scheduled_hours' => round($scheduledHours, 2),
                'worked_hours' => round($workedHours, 2),
                'lost_hours' => round($lostHours, 2),
                'absenteeism_rate' => round($absenteeismRate, 2),
                'tardiness_rate' => round($tardinessRate, 2),
                'shrinkage_rate' => round($shrinkageRate, 2)
            ]
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get saved report for a date
 */
function getSavedReport() {
    global $db;

    $date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');

    // Get summary
    $stmt = $db->prepare("SELECT * FROM twr_daily_reports WHERE report_date = ?");
    $stmt->execute([$date]);
    $report = $stmt->fetch();

    if (!$report) {
        echo json_encode(['success' => false, 'error' => 'No report found for this date']);
        return;
    }

    // Get details
    $stmt = $db->prepare("
        SELECT * FROM twr_daily_report_details
        WHERE report_date = ?
        ORDER BY full_name ASC
    ");
    $stmt->execute([$date]);
    $details = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'report' => $report,
        'details' => $details
    ]);
}

/**
 * Get notices for a date
 */
function getNotices() {
    global $db;

    $date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT n.*, u.full_name
        FROM twr_user_notices n
        JOIN twr_users u ON n.user_id = u.user_id
        WHERE n.notice_date = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$date]);
    $notices = $stmt->fetchAll();

    echo json_encode(['success' => true, 'notices' => $notices]);
}

/**
 * Save a notice
 */
function saveNotice() {
    global $db;

    $userId = $_POST['user_id'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $noticeType = $_POST['notice_type'] ?? '';
    $reason = $_POST['reason'] ?? '';

    if (!$userId || !$noticeType) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    try {
        // Check if notice already exists
        $stmt = $db->prepare("
            SELECT id FROM twr_user_notices
            WHERE user_id = ? AND notice_date = ?
        ");
        $stmt->execute([$userId, $date]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE twr_user_notices
                SET notice_type = ?, reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$noticeType, $reason, $existing['id']]);
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO twr_user_notices (user_id, notice_date, notice_type, reason, approved_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $date, $noticeType, $reason, $_SESSION['user_id']]);
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete a notice
 */
function deleteNotice() {
    global $db;

    $noticeId = $_POST['notice_id'] ?? 0;

    if (!$noticeId) {
        echo json_encode(['success' => false, 'error' => 'Missing notice ID']);
        return;
    }

    try {
        $stmt = $db->prepare("DELETE FROM twr_user_notices WHERE id = ?");
        $stmt->execute([$noticeId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
