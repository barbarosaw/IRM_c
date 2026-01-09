<?php
/**
 * AbroadWorks Management System - TimeWorks JSON Importer
 *
 * Imports TimeWorks data from JSON files into database
 * - users.json -> twr_users
 * - projects.json -> twr_projects
 * - user_projects.json -> twr_user_projects
 * - user_shifts.json -> twr_user_shifts
 *
 * @author ikinciadam@gmail.com
 */

// Allow CLI and direct access for this import script
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

// Only require init if not already included
if (!isset($db)) {
    require_once '../../includes/init.php';
}

// Check permissions - only logged in users
if (!isset($_SESSION['user_id'])) {
    die('Access denied: Please login first');
}

// Optional: Check if user is owner or admin (comment out if needed)
// if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
//     die('Access denied: Only system owners can run imports');
// }

// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set execution time and memory limits for large imports
set_time_limit(300);
ini_set('memory_limit', '512M');

// Start output
echo "<!DOCTYPE html><html><head><title>TimeWorks Import</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#00ff00;}</style>";
echo "</head><body>";
echo "<h2>TimeWorks JSON Import</h2>";
echo "<pre>";
flush();

// Color output for CLI
function colorLog($message, $type = 'info') {
    $colors = [
        'success' => "\033[0;32m",
        'error' => "\033[0;31m",
        'warning' => "\033[0;33m",
        'info' => "\033[0;36m",
        'reset' => "\033[0m"
    ];

    $color = $colors[$type] ?? $colors['info'];
    echo $color . $message . $colors['reset'] . PHP_EOL;
}

// Start import process
colorLog("==============================================", 'info');
colorLog("  TimeWorks JSON Import Script", 'info');
colorLog("==============================================", 'info');
echo PHP_EOL;

$startTime = microtime(true);
$totalRecords = 0;
$errors = [];

// Base path for JSON files
$jsonPath = __DIR__ . '/../../mds/';

// =====================================================
// 1. Import Users
// =====================================================
colorLog("1. Importing Users...", 'info');

$usersFile = $jsonPath . 'users.json';
if (!file_exists($usersFile)) {
    colorLog("Error: users.json not found at {$usersFile}", 'error');
    exit(1);
}

$usersData = json_decode(file_get_contents($usersFile), true);
if (!$usersData) {
    colorLog("Error: Failed to parse users.json", 'error');
    exit(1);
}

$usersCount = 0;
$usersUpdated = 0;
$usersInserted = 0;

$stmt = $db->prepare("
    INSERT INTO twr_users
    (user_id, full_name, email, timezone, status, user_status, last_login_local, roles, created_at, synced_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        email = VALUES(email),
        timezone = VALUES(timezone),
        status = VALUES(status),
        user_status = VALUES(user_status),
        last_login_local = VALUES(last_login_local),
        roles = VALUES(roles),
        synced_at = VALUES(synced_at),
        updated_at = NOW()
");

foreach ($usersData as $user) {
    try {
        // Check if exists
        $checkStmt = $db->prepare("SELECT id FROM twr_users WHERE user_id = ?");
        $checkStmt->execute([$user['user_id']]);
        $exists = $checkStmt->fetch();

        $stmt->execute([
            $user['user_id'],
            $user['full_name'],
            $user['email'],
            $user['timezone'] ?? 'UTC',
            $user['status'] ?? 'active',
            $user['user_status'] ?? 'normal',
            $user['last_login_local'],
            $user['roles'] ?? 'User',
            $user['created_at'] ?? date('Y-m-d H:i:s'),
            $user['synced_at'] ?? date('Y-m-d H:i:s')
        ]);

        if ($exists) {
            $usersUpdated++;
        } else {
            $usersInserted++;
        }

        $usersCount++;

        if ($usersCount % 50 == 0) {
            echo ".";
        }
    } catch (Exception $e) {
        $errors[] = "User Import Error ({$user['email']}): " . $e->getMessage();
    }
}

echo PHP_EOL;
colorLog("✓ Users: {$usersCount} processed ({$usersInserted} new, {$usersUpdated} updated)", 'success');
$totalRecords += $usersCount;

// =====================================================
// 2. Import Projects
// =====================================================
colorLog("2. Importing Projects...", 'info');

$projectsFile = $jsonPath . 'projects.json';
if (!file_exists($projectsFile)) {
    colorLog("Error: projects.json not found", 'error');
    exit(1);
}

$projectsData = json_decode(file_get_contents($projectsFile), true);
if (!$projectsData) {
    colorLog("Error: Failed to parse projects.json", 'error');
    exit(1);
}

$projectsCount = 0;
$projectsUpdated = 0;
$projectsInserted = 0;

$stmt = $db->prepare("
    INSERT INTO twr_projects
    (project_id, name, description, status, progress, is_billable, task_count, member_count, created_at, updated_at, synced_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        status = VALUES(status),
        progress = VALUES(progress),
        is_billable = VALUES(is_billable),
        task_count = VALUES(task_count),
        member_count = VALUES(member_count),
        updated_at = VALUES(updated_at),
        synced_at = VALUES(synced_at)
");

foreach ($projectsData as $project) {
    try {
        // Check if exists
        $checkStmt = $db->prepare("SELECT id FROM twr_projects WHERE project_id = ?");
        $checkStmt->execute([$project['project_id']]);
        $exists = $checkStmt->fetch();

        $stmt->execute([
            $project['project_id'],
            $project['name'],
            $project['description'],
            $project['status'] ?? 'active',
            $project['progress'] ?? 'Pending',
            $project['is_billable'] ?? 0,
            $project['task_count'] ?? 0,
            $project['member_count'] ?? 0,
            $project['created_at'] ?? date('Y-m-d H:i:s'),
            $project['updated_at'] ?? date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        if ($exists) {
            $projectsUpdated++;
        } else {
            $projectsInserted++;
        }

        $projectsCount++;

        if ($projectsCount % 50 == 0) {
            echo ".";
        }
    } catch (Exception $e) {
        $errors[] = "Project Import Error ({$project['name']}): " . $e->getMessage();
    }
}

echo PHP_EOL;
colorLog("✓ Projects: {$projectsCount} processed ({$projectsInserted} new, {$projectsUpdated} updated)", 'success');
$totalRecords += $projectsCount;

// =====================================================
// 3. Import User-Project Relationships
// =====================================================
colorLog("3. Importing User-Project Relationships...", 'info');

$userProjectsFile = $jsonPath . 'user_projects.json';
if (!file_exists($userProjectsFile)) {
    colorLog("Error: user_projects.json not found", 'error');
    exit(1);
}

$userProjectsData = json_decode(file_get_contents($userProjectsFile), true);
if (!$userProjectsData) {
    colorLog("Error: Failed to parse user_projects.json", 'error');
    exit(1);
}

// First, clear existing relationships
$db->exec("TRUNCATE TABLE twr_user_projects");

$relationshipsCount = 0;
$stmt = $db->prepare("
    INSERT IGNORE INTO twr_user_projects
    (user_id, project_id, assigned_at, synced_at)
    VALUES (?, ?, NOW(), NOW())
");

foreach ($userProjectsData as $userProject) {
    try {
        if (!isset($userProject['projects']) || !is_array($userProject['projects'])) {
            continue;
        }

        foreach ($userProject['projects'] as $project) {
            $stmt->execute([
                $userProject['user_id'],
                $project['project_id']
            ]);

            $relationshipsCount++;

            if ($relationshipsCount % 100 == 0) {
                echo ".";
            }
        }
    } catch (Exception $e) {
        $errors[] = "User-Project Relationship Error ({$userProject['user_id']}): " . $e->getMessage();
    }
}

echo PHP_EOL;
colorLog("✓ User-Project Relationships: {$relationshipsCount} processed", 'success');
$totalRecords += $relationshipsCount;

// =====================================================
// 4. Import User Shifts
// =====================================================
colorLog("4. Importing User Shifts...", 'info');

$shiftsFile = $jsonPath . 'user_shifts.json';
if (!file_exists($shiftsFile)) {
    colorLog("Error: user_shifts.json not found", 'error');
    exit(1);
}

$shiftsData = json_decode(file_get_contents($shiftsFile), true);
if (!$shiftsData) {
    colorLog("Error: Failed to parse user_shifts.json", 'error');
    exit(1);
}

// Clear existing shifts
$db->exec("TRUNCATE TABLE twr_user_shifts");

$shiftsCount = 0;
$stmt = $db->prepare("
    INSERT INTO twr_user_shifts
    (user_id, full_name, day_of_week, start_time, end_time, is_off, synced_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        start_time = VALUES(start_time),
        end_time = VALUES(end_time),
        is_off = VALUES(is_off),
        synced_at = VALUES(synced_at)
");

foreach ($shiftsData as $userShift) {
    try {
        // Get user_id from full_name
        $userStmt = $db->prepare("SELECT user_id FROM twr_users WHERE full_name = ? LIMIT 1");
        $userStmt->execute([$userShift['full_name']]);
        $user = $userStmt->fetch();

        if (!$user) {
            colorLog("Warning: User not found: {$userShift['full_name']}", 'warning');
            continue;
        }

        if (!isset($userShift['schedule']) || !is_array($userShift['schedule'])) {
            continue;
        }

        foreach ($userShift['schedule'] as $day => $schedule) {
            $stmt->execute([
                $user['user_id'],
                $userShift['full_name'],
                $day,
                $schedule['start'] ?? '09:00',
                $schedule['end'] ?? '17:00',
                $schedule['is_off'] ?? 0
            ]);

            $shiftsCount++;
        }

        if ($shiftsCount % 100 == 0) {
            echo ".";
        }
    } catch (Exception $e) {
        $errors[] = "Shift Import Error ({$userShift['full_name']}): " . $e->getMessage();
    }
}

echo PHP_EOL;
colorLog("✓ User Shifts: {$shiftsCount} processed", 'success');
$totalRecords += $shiftsCount;

// =====================================================
// 5. Log to sync_log
// =====================================================
$duration = round(microtime(true) - $startTime, 2);

$logStmt = $db->prepare("
    INSERT INTO twr_sync_log
    (sync_type, status, records_processed, records_added, records_updated, records_failed, started_at, completed_at, duration_seconds)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
");

$logStmt->execute([
    'full',
    empty($errors) ? 'success' : 'partial',
    $totalRecords,
    $usersInserted + $projectsInserted,
    $usersUpdated + $projectsUpdated,
    count($errors),
    date('Y-m-d H:i:s', $startTime),
    $duration
]);

// =====================================================
// Summary
// =====================================================
echo PHP_EOL;
colorLog("==============================================", 'info');
colorLog("  Import Summary", 'info');
colorLog("==============================================", 'info');
colorLog("Total Records: {$totalRecords}", 'success');
colorLog("Duration: {$duration} seconds", 'info');
echo PHP_EOL;

if (!empty($errors)) {
    colorLog("Errors encountered: " . count($errors), 'error');
    foreach (array_slice($errors, 0, 10) as $error) {
        colorLog("  - {$error}", 'error');
    }
    if (count($errors) > 10) {
        colorLog("  ... and " . (count($errors) - 10) . " more errors", 'error');
    }
} else {
    colorLog("✓ Import completed successfully with no errors!", 'success');
}

echo PHP_EOL;
colorLog("==============================================", 'info');

echo "</pre></body></html>";
?>
