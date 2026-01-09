<?php
/**
 * Hubstaff Import Handler - November 2025
 * Modes: test (Absalon only), automate (all users), continue (resume processing)
 */

// Extend timeout for API calls
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/models/HubstaffAPI.php';

header('Content-Type: application/json');

$mode = $_GET['mode'] ?? 'test';
$exportsDir = __DIR__ . '/exports';
$stateFile = $exportsDir . '/state_november.json';
$projectsFile = $exportsDir . '/projects.json';
$tasksFile = $exportsDir . '/tasks.json';
$usersFile = $exportsDir . '/users_november.json';

// Ensure exports directory exists
if (!is_dir($exportsDir)) {
    mkdir($exportsDir, 0755, true);
}

// Load state
function loadState($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return [];
}

function saveState($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

try {
    $api = new HubstaffAPI();
    $orgId = 231892;
    $orgName = "AbroadWorks";

    // Test mode - only Absalon
    if ($mode === 'test') {
        $absalonId = 3461727;

        // Get user details
        $userResp = $api->getUser($absalonId);
        $userName = $userResp['user']['name'] ?? 'Unknown';
        $userTimezone = $userResp['user']['time_zone'] ?? 'UTC';

        // Load or fetch projects
        $projects = loadState($projectsFile);
        if (empty($projects)) {
            $projects = $api->getProjects($orgId);
            saveState($projectsFile, $projects);
        }

        // Load or fetch tasks
        $tasks = loadState($tasksFile);
        if (empty($tasks)) {
            $tasks = fetchAllTasks($api, $orgId);
            saveState($tasksFile, $tasks);
        }

        // Export user
        $result = exportUserData($api, $orgId, $orgName, $absalonId, $userName, $userTimezone, $projects, $tasks, $exportsDir);

        echo json_encode([
            'success' => true,
            'message' => "Test export completed for $userName",
            'user_name' => $userName,
            'entries' => $result['count'],
            'continue' => false
        ]);
        exit;
    }

    // Automate mode - start processing all users
    if ($mode === 'automate') {
        // Reset state
        $state = [
            'processing' => true,
            'current_index' => 0,
            'current_user' => null,
            'total_users' => 0,
            'processed' => [],
            'started_at' => date('Y-m-d H:i:s')
        ];

        // Load or fetch projects
        $projects = loadState($projectsFile);
        if (empty($projects)) {
            $projects = $api->getProjects($orgId);
            saveState($projectsFile, $projects);
        }

        // Load or fetch tasks
        $tasks = loadState($tasksFile);
        if (empty($tasks)) {
            $tasks = fetchAllTasks($api, $orgId);
            saveState($tasksFile, $tasks);
        }

        // Get all members with activities
        $users = loadState($usersFile);
        if (empty($users)) {
            $members = $api->getMembers($orgId);
            $users = [];
            foreach ($members as $m) {
                $users[] = [
                    'user_id' => $m['user_id'],
                    'processed' => false
                ];
            }
            saveState($usersFile, $users);
        }

        $state['total_users'] = count($users);

        // Find first unprocessed user
        foreach ($users as $i => $u) {
            if (!$u['processed']) {
                $state['current_index'] = $i;
                break;
            }
        }

        saveState($stateFile, $state);

        // Process first user
        echo json_encode([
            'success' => true,
            'message' => 'Starting automated export...',
            'total_users' => count($users),
            'continue' => true
        ]);
        exit;
    }

    // Continue mode - process next user
    if ($mode === 'continue') {
        $state = loadState($stateFile);
        $users = loadState($usersFile);
        $projects = loadState($projectsFile);
        $tasks = loadState($tasksFile);

        if (empty($state) || !$state['processing']) {
            echo json_encode(['success' => true, 'continue' => false, 'message' => 'No active process']);
            exit;
        }

        $currentIndex = $state['current_index'] ?? 0;

        // Find next unprocessed user
        $userToProcess = null;
        $userIndex = null;
        for ($i = $currentIndex; $i < count($users); $i++) {
            if (!$users[$i]['processed']) {
                $userToProcess = $users[$i];
                $userIndex = $i;
                break;
            }
        }

        if (!$userToProcess) {
            // All done
            $state['processing'] = false;
            $state['completed_at'] = date('Y-m-d H:i:s');
            saveState($stateFile, $state);

            echo json_encode([
                'success' => true,
                'continue' => false,
                'message' => 'All users processed!'
            ]);
            exit;
        }

        // Get user details
        $userId = $userToProcess['user_id'];
        try {
            $userResp = $api->getUser($userId);
            $userName = $userResp['user']['name'] ?? 'User ' . $userId;
            $userTimezone = $userResp['user']['time_zone'] ?? 'UTC';
        } catch (Exception $e) {
            $userName = 'User ' . $userId;
            $userTimezone = 'UTC';
        }

        $state['current_user'] = $userName;
        $state['current_index'] = $userIndex;
        saveState($stateFile, $state);

        // Export user data
        $result = exportUserData($api, $orgId, $orgName, $userId, $userName, $userTimezone, $projects, $tasks, $exportsDir);

        // Mark as processed
        $users[$userIndex]['processed'] = true;
        $users[$userIndex]['name'] = $userName;
        $users[$userIndex]['entries'] = $result['count'];
        saveState($usersFile, $users);

        // Update state
        $state['processed'][] = $userName;
        $state['current_index'] = $userIndex + 1;
        saveState($stateFile, $state);

        // Check if more users
        $hasMore = false;
        for ($i = $userIndex + 1; $i < count($users); $i++) {
            if (!$users[$i]['processed']) {
                $hasMore = true;
                break;
            }
        }

        if (!$hasMore) {
            $state['processing'] = false;
            $state['completed_at'] = date('Y-m-d H:i:s');
            saveState($stateFile, $state);
        }

        echo json_encode([
            'success' => true,
            'user_name' => $userName,
            'entries' => $result['count'],
            'continue' => $hasMore,
            'message' => $hasMore ? "Processed $userName" : 'All users processed!'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid mode']);

} catch (Exception $e) {
    // Clear processing state on error
    $state = loadState($stateFile);
    $state['processing'] = false;
    $state['error'] = $e->getMessage();
    saveState($stateFile, $state);

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Helper function to fetch all tasks
function fetchAllTasks($api, $orgId) {
    $tasks = [];
    $page = 1;

    do {
        $params = http_build_query([
            'page_start_id' => $page,
            'page_limit' => 500
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.hubstaff.com/v2/organizations/$orgId/tasks?" . $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api->getAccessToken(),
                'Accept: application/json'
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['tasks'])) {
                foreach ($data['tasks'] as $task) {
                    $tasks[$task['id']] = $task['name'] ?? '';
                }
            }
            $hasMore = isset($data['pagination']['next_page_start_id']);
            if ($hasMore) {
                $page = $data['pagination']['next_page_start_id'];
                sleep(4); // Rate limit
            }
        } else {
            $hasMore = false;
        }
    } while ($hasMore);

    return $tasks;
}

// Helper function to export user data
function exportUserData($api, $orgId, $orgName, $userId, $userName, $userTimezone, $projects, $tasks, $exportsDir) {
    // Fetch activities (in weekly chunks due to API limit)
    $allActivities = [];
    $weeks = [
        ['2025-11-01T00:00:00Z', '2025-11-07T23:59:59Z'],
        ['2025-11-08T00:00:00Z', '2025-11-14T23:59:59Z'],
        ['2025-11-15T00:00:00Z', '2025-11-21T23:59:59Z'],
        ['2025-11-22T00:00:00Z', '2025-11-28T23:59:59Z'],
        ['2025-11-29T00:00:00Z', '2025-11-30T23:59:59Z']
    ];

    foreach ($weeks as $week) {
        $page = 1;
        do {
            $params = http_build_query([
                'time_slot[start]' => $week[0],
                'time_slot[stop]' => $week[1],
                'user_ids[]' => $userId,
                'page_limit' => 500,
                'page_start_id' => $page
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.hubstaff.com/v2/organizations/$orgId/activities?" . $params,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $api->getAccessToken(),
                    'Accept: application/json'
                ]
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['activities'])) {
                    $allActivities = array_merge($allActivities, $data['activities']);
                }
                $hasMore = isset($data['pagination']['next_page_start_id']);
                if ($hasMore) {
                    $page = $data['pagination']['next_page_start_id'];
                }
            } else {
                $hasMore = false;
            }
        } while ($hasMore);

        sleep(4); // Rate limit between weeks
    }

    // If no activities, return early
    if (empty($allActivities)) {
        return ['count' => 0, 'file' => null];
    }

    // Sort by starts_at
    usort($allActivities, function($a, $b) {
        return strcmp($a['starts_at'] ?? '', $b['starts_at'] ?? '');
    });

    // Aggregate consecutive entries with same project
    $aggregatedEntries = [];
    $currentGroup = null;

    foreach ($allActivities as $act) {
        $projectId = $act['project_id'] ?? null;
        $taskId = $act['task_id'] ?? null;
        $startsAt = $act['starts_at'] ?? '';
        $tracked = $act['tracked'] ?? 0;
        $overall = $act['overall'] ?? 0;
        $billable = $act['billable'] ?? false;
        $isManual = isset($act['time_type']) && $act['time_type'] === 'manual';

        // Calculate stop time for this entry
        $stopAt = '';
        if ($startsAt && $tracked > 0) {
            $stopDt = new DateTime($startsAt, new DateTimeZone('UTC'));
            $stopDt->modify("+$tracked seconds");
            $stopAt = $stopDt->format('Y-m-d\TH:i:s\Z');
        }

        // Check if this entry continues the current group
        $isContinuation = false;
        if ($currentGroup !== null) {
            // Same project and task, and starts exactly when previous ends
            if ($currentGroup['project_id'] === $projectId &&
                $currentGroup['task_id'] === $taskId &&
                $currentGroup['stop_at'] === $startsAt) {
                $isContinuation = true;
            }
        }

        if ($isContinuation) {
            // Extend the current group
            $currentGroup['stop_at'] = $stopAt;
            $currentGroup['tracked'] += $tracked;
            $currentGroup['overall'] += $overall;
            $currentGroup['has_manual'] = $currentGroup['has_manual'] || $isManual;
        } else {
            // Save the previous group if exists
            if ($currentGroup !== null) {
                $aggregatedEntries[] = $currentGroup;
            }
            // Start new group
            $currentGroup = [
                'project_id' => $projectId,
                'task_id' => $taskId,
                'starts_at' => $startsAt,
                'stop_at' => $stopAt,
                'tracked' => $tracked,
                'overall' => $overall,
                'billable' => $billable,
                'has_manual' => $isManual
            ];
        }
    }

    // Don't forget the last group
    if ($currentGroup !== null) {
        $aggregatedEntries[] = $currentGroup;
    }

    // Create CSV
    $safeUserName = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $userName));
    $safeUserName = preg_replace('/_+/', '_', $safeUserName);
    $csvFile = "$exportsDir/{$safeUserName}_november_2025.csv";

    $fp = fopen($csvFile, 'w');

    // Header
    fputcsv($fp, [
        'Member', 'Organization', 'Time Zone', 'Projects', 'Task Summary',
        'Start', 'Stop', 'Duration', 'Activity', 'Idle', 'Manual',
        'Notes', 'Reasons', 'Type', 'Payment type'
    ]);

    // Timezone conversion helper
    $tz = new DateTimeZone($userTimezone);

    foreach ($aggregatedEntries as $entry) {
        $projectId = $entry['project_id'];
        $projectName = '';
        if ($projectId && isset($projects[$projectId])) {
            $projectName = $projects[$projectId]['name'] ?? '';
        }

        $taskId = $entry['task_id'];
        $taskName = '';
        if ($taskId && isset($tasks[$taskId])) {
            $taskName = $tasks[$taskId];
        }

        $startsAt = $entry['starts_at'];
        $stopAt = $entry['stop_at'];
        $tracked = $entry['tracked'];

        // Convert to local timezone
        $startLocal = '';
        $stopLocal = '';
        if ($startsAt) {
            $startDt = new DateTime($startsAt, new DateTimeZone('UTC'));
            $startDt->setTimezone($tz);
            $startLocal = $startDt->format('Y-m-d\TH:i:sP');
        }
        if ($stopAt) {
            $stopDt = new DateTime($stopAt, new DateTimeZone('UTC'));
            $stopDt->setTimezone($tz);
            $stopLocal = $stopDt->format('Y-m-d\TH:i:sP');
        }

        // Duration
        $hours = floor($tracked / 3600);
        $mins = floor(($tracked % 3600) / 60);
        $secs = $tracked % 60;
        $duration = sprintf('%d:%02d:%02d', $hours, $mins, $secs);

        // Activity percentage (weighted average)
        $overall = $entry['overall'];
        $activityPct = $tracked > 0 ? round(($overall / $tracked) * 100) : 0;

        // Manual
        $manual = $entry['has_manual'] ? '100%' : '0%';

        // Payment type
        $paymentType = $entry['billable'] ? 'Billable' : 'Non-billable';

        fputcsv($fp, [
            $userName,
            $orgName,
            $userTimezone,
            $projectName,
            $taskName,
            $startLocal,
            $stopLocal,
            $duration,
            $activityPct . '%',
            '0%',
            $manual,
            '',
            '',
            'Time entry',
            $paymentType
        ]);
    }

    fclose($fp);

    return ['count' => count($aggregatedEntries), 'file' => $csvFile];
}
