<?php
/**
 * TimeWorks Module - Daily Report Cron Job
 *
 * Generates daily attendance report automatically.
 * Run via cron: 30 12 * * * /opt/plesk/php/8.3/bin/php /path/to/generate-daily-report.php
 *
 * @author ikinciadam@gmail.com
 */

// Disable web output if called from web (security)
if (php_sapi_name() !== 'cli') {
    // Allow Plesk scheduled tasks
    if (!isset($_SERVER['PLESK_SCHEDULED_TASK'])) {
        @header('Content-Type: text/plain');
    }
}

define('AW_SYSTEM', true);
chdir(dirname(__DIR__, 3));
require_once 'includes/init.php';

// Set timezone to EST
date_default_timezone_set('America/New_York');

// Load TimeWorks API
require_once 'modules/timeworks/models/TimeWorksAPI.php';

// Configuration
$date = $argv[1] ?? $_GET['date'] ?? date('Y-m-d'); // Allow passing date as argument or query param
$dayOfWeek = date('l', strtotime($date));
$chunkSize = 50;

echo "=== TimeWorks Daily Report Generator ===\n";
echo "Date: {$date} ({$dayOfWeek})\n";
echo "Started: " . date('Y-m-d H:i:s') . " EST\n\n";

try {
    $api = new TimeWorksAPI($db);

    // Get total user count
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.user_id) as total
        FROM twr_users u
        WHERE u.status = 'active'
    ");
    $stmt->execute();
    $totalUsers = (int)$stmt->fetchColumn();

    echo "Total active users: {$totalUsers}\n";

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
    echo "Notices found: " . count($notices) . "\n\n";

    // Initialize stats
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

    $allDetails = [];
    $offset = 0;
    $processedCount = 0;

    // Process users in chunks
    while ($offset < $totalUsers) {
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
        $stmt->execute([$dayOfWeek, $chunkSize, $offset]);
        $users = $stmt->fetchAll();

        if (empty($users)) break;

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
                $allDetails[] = $userResult;
                $processedCount++;
                continue;
            }

            // Check if user has a defined schedule
            $hasSchedule = $user['start_time'] && $user['end_time'];

            // Calculate scheduled hours (only if has schedule)
            if ($hasSchedule) {
                $startTime = strtotime($user['start_time']);
                $endTime = strtotime($user['end_time']);
                if ($endTime <= $startTime) {
                    $endTime += 86400;
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
                        $allDetails[] = $userResult;
                        $processedCount++;
                        continue 2;
                    case 'unpaid_to':
                        $userResult['status'] = 'unpaid_to';
                        $stats['unpaid_to']++;
                        $allDetails[] = $userResult;
                        $processedCount++;
                        continue 2;
                    case 'sick_leave':
                        $userResult['status'] = 'absent_with_notice';
                        $stats['absent_with_notice']++;
                        $allDetails[] = $userResult;
                        $processedCount++;
                        continue 2;
                }
            }

            // Fetch time entries from API (for ALL users including flexible schedule)
            $apiResult = $api->getUserDailyReport($user['user_id'], $date, 'America/New_York');
            $hasActivity = false;

            if ($apiResult && isset($apiResult['report'][$date]) && !empty($apiResult['report'][$date])) {
                $report = $apiResult['report'][$date];
                $entries = $report['entries'] ?? [];

                if (!empty($entries)) {
                    $hasActivity = true;

                    // Calculate worked hours
                    $totalWorkedSeconds = 0;
                    foreach ($entries as $entry) {
                        if (isset($entry['started_at']) && isset($entry['stopped_at'])) {
                            $entryStart = strtotime($entry['started_at']);
                            $entryEnd = strtotime($entry['stopped_at']);
                            if ($entryEnd < $entryStart) {
                                $entryEnd += 86400;
                            }
                            $totalWorkedSeconds += ($entryEnd - $entryStart);
                        } elseif (isset($entry['started_at']) && !isset($entry['stopped_at'])) {
                            $entryStart = strtotime($entry['started_at']);
                            $now = time();
                            $totalWorkedSeconds += ($now - $entryStart);
                        }
                    }

                    $workedSeconds = $totalWorkedSeconds > 0 ? $totalWorkedSeconds : ($report['total_user_duration_seconds'] ?? 0);
                    $userResult['worked_hours'] = round($workedSeconds / 3600, 2);
                    $stats['worked_hours'] += $userResult['worked_hours'];

                    // First check-in
                    $firstEntry = reset($entries);
                    $lastEntry = end($entries);

                    if (isset($firstEntry['started_at'])) {
                        $firstCheckIn = $firstEntry['started_at'];
                        $userResult['actual_start'] = date('H:i:s', strtotime($firstCheckIn));

                        // Determine status based on schedule
                        if ($hasSchedule) {
                            // Has schedule - check if late
                            $scheduledStart = strtotime($date . ' ' . $user['start_time']);
                            $actualStart = strtotime($firstCheckIn);
                            $lateMinutes = ($actualStart - $scheduledStart) / 60;

                            if ($lateMinutes > 5) {
                                $userResult['late_minutes'] = (int)$lateMinutes;
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

            $allDetails[] = $userResult;
            $processedCount++;
        }

        $offset += $chunkSize;
        $percent = round(($offset / $totalUsers) * 100);
        echo "Processed: {$processedCount}/{$totalUsers} ({$percent}%)\n";
    }

    echo "\nProcessing complete. Saving report...\n";

    // Calculate rates
    $totalScheduled = $stats['on_time'] + $stats['late_with_notice'] + $stats['late_without_notice'] +
                      $stats['absent_with_notice'] + $stats['absent_without_notice'];

    $totalAbsent = $stats['absent_with_notice'] + $stats['absent_without_notice'];
    $totalLate = $stats['late_with_notice'] + $stats['late_without_notice'];

    $absenteeismRate = $totalScheduled > 0 ? ($totalAbsent / $totalScheduled) * 100 : 0;
    $tardinessRate = $totalScheduled > 0 ? ($totalLate / $totalScheduled) * 100 : 0;

    $scheduledHours = $stats['scheduled_hours'];
    $workedHours = $stats['worked_hours'];
    $lostHours = max(0, $scheduledHours - $workedHours);
    $shrinkageRate = $scheduledHours > 0 ? ($lostHours / $scheduledHours) * 100 : 0;

    // Save to database
    $db->beginTransaction();

    // Delete existing report
    $stmt = $db->prepare("DELETE FROM twr_daily_reports WHERE report_date = ?");
    $stmt->execute([$date]);

    $stmt = $db->prepare("DELETE FROM twr_daily_report_details WHERE report_date = ?");
    $stmt->execute([$date]);

    // Insert report
    $totalOff = $stats['off'] + $stats['no_schedule'];

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

    $stmt->execute([
        $date,
        $dayOfWeek,
        count($allDetails),
        $totalScheduled,
        $totalOff,
        $stats['on_time'],
        $stats['late_with_notice'],
        $stats['late_without_notice'],
        $stats['absent_with_notice'],
        $stats['absent_without_notice'],
        $stats['pto'],
        $stats['unpaid_to'],
        $stats['flexible'],
        $scheduledHours,
        $workedHours,
        $lostHours,
        $absenteeismRate,
        $tardinessRate,
        $shrinkageRate,
        0 // System generated (no user)
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

    foreach ($allDetails as $detail) {
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

    echo "\n=== Report Saved Successfully ===\n";
    echo "Report ID: {$reportId}\n";
    echo "Total Users: " . count($allDetails) . "\n";
    echo "On Time: {$stats['on_time']}\n";
    echo "Late (with notice): {$stats['late_with_notice']}\n";
    echo "Late (without notice): {$stats['late_without_notice']}\n";
    echo "Absent (with notice): {$stats['absent_with_notice']}\n";
    echo "Absent (without notice): {$stats['absent_without_notice']}\n";
    echo "PTO: {$stats['pto']}\n";
    echo "Unpaid TO: {$stats['unpaid_to']}\n";
    echo "Flexible Schedule: {$stats['flexible']}\n";
    echo "Off/No Schedule: {$totalOff}\n";
    echo "Scheduled Hours: " . round($scheduledHours, 2) . "\n";
    echo "Worked Hours: " . round($workedHours, 2) . "\n";
    echo "Lost Hours: " . round($lostHours, 2) . "\n";
    echo "Absenteeism Rate: " . round($absenteeismRate, 2) . "%\n";
    echo "Tardiness Rate: " . round($tardinessRate, 2) . "%\n";
    echo "Shrinkage Rate: " . round($shrinkageRate, 2) . "%\n";
    echo "\nCompleted: " . date('Y-m-d H:i:s') . " EST\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
