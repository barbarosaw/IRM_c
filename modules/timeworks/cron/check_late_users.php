<?php
/**
 * TimeWorks Module - Late Detection Cron Job
 *
 * This script checks for users who are late for their shifts and:
 * 1. Creates late records in twr_late_records
 * 2. Sends email notifications with evidence upload link
 * 3. Logs the detection run for audit purposes
 *
 * Run every 30 minutes via cron:
 * 0,30 * * * * /opt/plesk/php/8.3/bin/php /path/to/check_late_users.php
 *
 * @author ikinciadam@gmail.com
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define constants
define('AW_SYSTEM', true);
define('CRON_MODE', true);

// Change to project root
chdir(dirname(__DIR__, 3));

// Load dependencies
require_once 'includes/init.php';
require_once 'modules/timeworks/models/TimeWorksAPI.php';
require_once 'modules/timeworks/helpers/ShiftStatusHelper.php';
require_once 'includes/EmailHelper.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Log start
$startTime = microtime(true);
$logPrefix = "[Late Detection " . date('Y-m-d H:i:s') . "]";
error_log("{$logPrefix} Starting late detection cron job");

// Statistics
$stats = [
    'users_checked' => 0,
    'late_detected' => 0,
    'notifications_sent' => 0,
    'errors' => []
];

try {
    // Get settings
    $toleranceMinutes = 30; // Default
    $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'late_detection_threshold_minutes' AND `group` = 'timeworks'");
    $setting = $stmt->fetchColumn();
    if ($setting) {
        $toleranceMinutes = (int) $setting;
    }

    $evidenceHours = 24; // Default
    $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'evidence_upload_hours' AND `group` = 'timeworks'");
    $setting = $stmt->fetchColumn();
    if ($setting) {
        $evidenceHours = (int) $setting;
    }

    // Get current date and day of week
    $today = date('Y-m-d');
    $dayOfWeek = date('l'); // Monday, Tuesday, etc.
    $currentTime = new DateTime('now', new DateTimeZone('America/New_York'));

    error_log("{$logPrefix} Checking for late users on {$today} ({$dayOfWeek}) with {$toleranceMinutes} min tolerance");

    // Get all active users with shifts for today that are NOT marked as off
    $stmt = $db->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.email,
            s.start_time,
            s.end_time
        FROM twr_users u
        INNER JOIN twr_user_shifts s ON u.user_id = s.user_id
        WHERE u.status = 'active'
          AND s.day_of_week = ?
          AND s.is_off = 0
          AND s.start_time IS NOT NULL
    ");
    $stmt->execute([$dayOfWeek]);
    $usersWithShifts = $stmt->fetchAll();

    error_log("{$logPrefix} Found " . count($usersWithShifts) . " users with shifts today");

    // Initialize TimeWorks API
    $api = new TimeWorksAPI($db);

    // Email helper
    $emailHelper = new EmailHelper($db);

    // Get site settings for email
    $siteUrl = '';
    $siteName = 'AbroadWorks IRM';
    $stmt = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ('site_url', 'site_name')");
    while ($row = $stmt->fetch()) {
        if ($row['key'] === 'site_url') $siteUrl = $row['value'];
        if ($row['key'] === 'site_name') $siteName = $row['value'];
    }

    foreach ($usersWithShifts as $user) {
        $stats['users_checked']++;

        try {
            // Parse shift start time
            $shiftStart = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $user['start_time'], new DateTimeZone('America/New_York'));

            // Calculate late threshold
            $lateThreshold = clone $shiftStart;
            $lateThreshold->modify("+{$toleranceMinutes} minutes");

            // Skip if we haven't reached the late threshold yet
            if ($currentTime < $lateThreshold) {
                continue;
            }

            // Check if we already have a late record for this user today
            $stmt = $db->prepare("SELECT id FROM twr_late_records WHERE user_id = ? AND shift_date = ?");
            $stmt->execute([$user['user_id'], $today]);
            if ($stmt->fetch()) {
                // Already recorded
                continue;
            }

            // Check TimeWorks API for clock-in
            $timesheet = $api->getUserDailyReport($user['user_id'], $today, 'America/New_York');

            // Check if user has clocked in
            $hasClockedIn = false;
            $actualStart = null;

            if ($timesheet && isset($timesheet['data'])) {
                // Check for time entries
                $entries = $timesheet['data']['time_entries'] ?? $timesheet['data']['entries'] ?? [];
                if (!empty($entries)) {
                    $hasClockedIn = true;
                    // Get earliest entry as actual start
                    $earliestEntry = null;
                    foreach ($entries as $entry) {
                        $entryStart = $entry['start_time'] ?? $entry['started_at'] ?? null;
                        if ($entryStart) {
                            if (!$earliestEntry || $entryStart < $earliestEntry) {
                                $earliestEntry = $entryStart;
                            }
                        }
                    }
                    if ($earliestEntry) {
                        // Extract time part
                        if (strlen($earliestEntry) > 10) {
                            $actualStart = date('H:i:s', strtotime($earliestEntry));
                        } else {
                            $actualStart = $earliestEntry;
                        }
                    }
                }
            }

            // If clocked in, check if they were late
            if ($hasClockedIn && $actualStart) {
                $actStart = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $actualStart, new DateTimeZone('America/New_York'));

                // If they clocked in within tolerance, skip
                $toleranceEnd = clone $shiftStart;
                $toleranceEnd->modify("+{$toleranceMinutes} minutes");

                if ($actStart <= $toleranceEnd) {
                    continue; // Not late enough to record
                }

                // Calculate late minutes
                $lateMinutes = ($actStart->getTimestamp() - $shiftStart->getTimestamp()) / 60;
            } else {
                // No clock-in at all
                $lateMinutes = ($currentTime->getTimestamp() - $shiftStart->getTimestamp()) / 60;
            }

            // Generate evidence token
            $evidenceToken = bin2hex(random_bytes(32));

            // Calculate evidence deadline (24 hours from now)
            $evidenceDeadline = (clone $currentTime)->modify("+{$evidenceHours} hours");

            // Create late record
            $stmt = $db->prepare("
                INSERT INTO twr_late_records
                (user_id, shift_date, scheduled_start, scheduled_end, actual_start, late_minutes,
                 status, evidence_token, evidence_deadline, notification_sent_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $user['user_id'],
                $today,
                $user['start_time'],
                $user['end_time'],
                $actualStart,
                round($lateMinutes),
                $evidenceToken,
                $evidenceDeadline->format('Y-m-d H:i:s')
            ]);

            $stats['late_detected']++;
            error_log("{$logPrefix} Late detected: {$user['full_name']} - {$lateMinutes} minutes late");

            // Send email notification
            if (!empty($user['email']) && $emailHelper->isEnabled()) {
                $evidenceUrl = rtrim($siteUrl, '/') . "/modules/timeworks/submit-late-evidence.php?token={$evidenceToken}";

                $subject = "Late Arrival Notice - {$today}";

                $body = $emailHelper->wrapInTemplate("
                    <p>Dear {$user['full_name']},</p>

                    <p>Our system has detected that you were <strong>" . round($lateMinutes) . " minutes late</strong>
                    for your scheduled shift today ({$today}).</p>

                    <table style='width: 100%; margin: 20px 0; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Scheduled Start:</strong></td>
                            <td style='padding: 10px; border: 1px solid #ddd;'>" . date('h:i A', strtotime($user['start_time'])) . " (EST)</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Actual Start:</strong></td>
                            <td style='padding: 10px; border: 1px solid #ddd;'>" . ($actualStart ? date('h:i A', strtotime($actualStart)) . " (EST)" : "No clock-in recorded") . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Late By:</strong></td>
                            <td style='padding: 10px; border: 1px solid #ddd;'>" . round($lateMinutes) . " minutes</td>
                        </tr>
                    </table>

                    <p><strong>Did you notify someone beforehand?</strong></p>

                    <p>If you provided prior notice (via email, chat, SMS, or phone), please submit your evidence
                    using the link below. You have <strong>{$evidenceHours} hours</strong> to submit evidence.</p>

                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$evidenceUrl}' style='display: inline-block; padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            Submit Evidence
                        </a>
                    </p>

                    <p><strong>Evidence Deadline:</strong> " . $evidenceDeadline->format('F j, Y g:i A') . " (EST)</p>

                    <p style='color: #666; font-size: 12px;'>
                        If you did not provide prior notice, no action is required. Your status will remain as 'Late without Notice'.
                    </p>
                ", "Late Arrival Notice");

                if ($emailHelper->send($user['email'], $subject, $body)) {
                    $stats['notifications_sent']++;
                    error_log("{$logPrefix} Email sent to: {$user['email']}");
                } else {
                    $stats['errors'][] = "Failed to send email to {$user['email']}: " . $emailHelper->getLastError();
                }
            }

        } catch (Exception $e) {
            $stats['errors'][] = "Error processing user {$user['user_id']}: " . $e->getMessage();
            error_log("{$logPrefix} Error: " . $e->getMessage());
        }
    }

    // Log the detection run
    $duration = round(microtime(true) - $startTime, 2);
    $errorText = !empty($stats['errors']) ? implode("; ", $stats['errors']) : null;

    $stmt = $db->prepare("
        INSERT INTO twr_late_detection_log
        (run_at, users_checked, late_detected, notifications_sent, errors, duration_seconds)
        VALUES (NOW(), ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $stats['users_checked'],
        $stats['late_detected'],
        $stats['notifications_sent'],
        $errorText,
        $duration
    ]);

    error_log("{$logPrefix} Completed - Checked: {$stats['users_checked']}, Late: {$stats['late_detected']}, Emails: {$stats['notifications_sent']}, Duration: {$duration}s");

} catch (Exception $e) {
    error_log("{$logPrefix} Fatal error: " . $e->getMessage());
    $stats['errors'][] = $e->getMessage();
}

// Output for CLI
if (php_sapi_name() === 'cli') {
    echo "=== Late Detection Cron Job ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo "Users Checked: {$stats['users_checked']}\n";
    echo "Late Detected: {$stats['late_detected']}\n";
    echo "Notifications Sent: {$stats['notifications_sent']}\n";
    echo "Errors: " . count($stats['errors']) . "\n";
    if (!empty($stats['errors'])) {
        foreach ($stats['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
}
