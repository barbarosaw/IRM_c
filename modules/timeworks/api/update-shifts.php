<?php
/**
 * TimeWorks Module - Update User Shifts API
 *
 * Updates user shift schedules (individual or bulk)
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Permission check - require manage permission for editing
if (!has_permission('timeworks_shifts_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'update_single':
            // Update single user's shifts
            $userId = $input['user_id'] ?? null;
            $shifts = $input['shifts'] ?? [];

            if (!$userId) {
                throw new Exception('User ID is required');
            }

            updateUserShifts($db, $userId, $shifts);

            echo json_encode([
                'success' => true,
                'message' => 'Schedule updated successfully'
            ]);
            break;

        case 'update_bulk':
            // Update multiple users with same schedule
            $userIds = $input['user_ids'] ?? [];
            $shifts = $input['shifts'] ?? [];

            if (empty($userIds)) {
                throw new Exception('No users selected');
            }

            $updatedCount = 0;
            foreach ($userIds as $userId) {
                updateUserShifts($db, $userId, $shifts);
                $updatedCount++;
            }

            echo json_encode([
                'success' => true,
                'message' => "Schedule updated for {$updatedCount} user(s)"
            ]);
            break;

        case 'get_shifts':
            // Get shifts for a specific user
            $userId = $input['user_id'] ?? null;

            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $stmt = $db->prepare("
                SELECT day_of_week, start_time, end_time, is_off
                FROM twr_user_shifts
                WHERE user_id = ?
                ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
            ");
            $stmt->execute([$userId]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get user name
            $stmt = $db->prepare("SELECT full_name FROM twr_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'user_name' => $user['full_name'] ?? 'Unknown',
                'shifts' => $shifts
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
 * Update user shifts in database
 *
 * @param PDO $db Database connection
 * @param string $userId User ID
 * @param array $shifts Array of shift data by day
 * @throws Exception on failure
 */
function updateUserShifts($db, $userId, $shifts) {
    // Get user's full name
    $stmt = $db->prepare("SELECT full_name FROM twr_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found: {$userId}");
    }

    $fullName = $user['full_name'];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    $db->beginTransaction();

    try {
        foreach ($days as $day) {
            $dayData = $shifts[$day] ?? null;

            if ($dayData === null) {
                // Skip if no data for this day
                continue;
            }

            $isOff = isset($dayData['is_off']) && $dayData['is_off'] ? 1 : 0;
            $startTime = $dayData['start_time'] ?? '09:00';
            $endTime = $dayData['end_time'] ?? '18:00';

            // Check if record exists
            $stmt = $db->prepare("
                SELECT id FROM twr_user_shifts
                WHERE user_id = ? AND day_of_week = ?
            ");
            $stmt->execute([$userId, $day]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("
                    UPDATE twr_user_shifts
                    SET start_time = ?, end_time = ?, is_off = ?, updated_at = NOW()
                    WHERE user_id = ? AND day_of_week = ?
                ");
                $stmt->execute([$startTime, $endTime, $isOff, $userId, $day]);
            } else {
                // Insert new record
                $stmt = $db->prepare("
                    INSERT INTO twr_user_shifts
                    (user_id, full_name, day_of_week, start_time, end_time, is_off, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $fullName, $day, $startTime, $endTime, $isOff]);
            }
        }

        $db->commit();

        // Log activity
        log_activity(
            $_SESSION['user_id'],
            'shift_update',
            'timeworks_shift',
            "Updated schedule for {$fullName}"
        );

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
