<?php
/**
 * TimeWorks Module - Leave Requests API
 *
 * API endpoint for managing leave/PTO requests.
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    // Try form data for list action
    $input = $_POST;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $where = [];
            $params = [];

            if (!empty($input['status'])) {
                $where[] = "lr.status = ?";
                $params[] = $input['status'];
            }

            if (!empty($input['leave_type'])) {
                $where[] = "lr.leave_type_id = ?";
                $params[] = $input['leave_type'];
            }

            if (!empty($input['user_id'])) {
                $where[] = "lr.user_id = ?";
                $params[] = $input['user_id'];
            }

            if (!empty($input['start_date'])) {
                $where[] = "lr.start_date >= ?";
                $params[] = $input['start_date'];
            }

            if (!empty($input['end_date'])) {
                $where[] = "lr.end_date <= ?";
                $params[] = $input['end_date'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT
                    lr.*,
                    u.full_name,
                    u.email,
                    lt.name as leave_type_name,
                    lt.code as leave_type_code,
                    lt.is_paid,
                    lt.color_code as leave_color,
                    DATEDIFF(lr.end_date, lr.start_date) + 1 as days_count,
                    approver.name as approved_by_name
                FROM twr_leave_requests lr
                LEFT JOIN twr_users u ON lr.user_id = u.user_id
                LEFT JOIN twr_leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users approver ON lr.approved_by = approver.id
                {$whereClause}
                ORDER BY lr.requested_at DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $requests = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $requests
            ]);
            break;

        case 'get':
            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('ID is required');
            }

            $stmt = $db->prepare("
                SELECT
                    lr.*,
                    u.full_name,
                    u.email,
                    lt.name as leave_type_name,
                    lt.code as leave_type_code,
                    lt.is_paid,
                    lt.color_code as leave_color,
                    DATEDIFF(lr.end_date, lr.start_date) + 1 as days_count,
                    approver.name as approved_by_name
                FROM twr_leave_requests lr
                LEFT JOIN twr_users u ON lr.user_id = u.user_id
                LEFT JOIN twr_leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users approver ON lr.approved_by = approver.id
                WHERE lr.id = ?
            ");
            $stmt->execute([$id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Request not found');
            }

            echo json_encode([
                'success' => true,
                'request' => $request
            ]);
            break;

        case 'create':
            // Check permission
            if (!has_permission('timeworks_leave_manage')) {
                throw new Exception('Permission denied');
            }

            $userId = $input['user_id'] ?? null;
            $leaveTypeId = $input['leave_type_id'] ?? null;
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;
            $reason = $input['reason'] ?? null;
            $isClientPaid = $input['is_client_paid'] ?? 0;
            $autoApprove = $input['auto_approve'] ?? 0;

            if (!$userId || !$leaveTypeId || !$startDate || !$endDate) {
                throw new Exception('User, leave type, start date, and end date are required');
            }

            // Validate dates
            if ($endDate < $startDate) {
                throw new Exception('End date cannot be before start date');
            }

            // Check for overlapping requests
            $stmt = $db->prepare("
                SELECT id FROM twr_leave_requests
                WHERE user_id = ?
                AND status IN ('pending', 'approved')
                AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?)
                     OR (start_date <= ? AND end_date >= ?))
            ");
            $stmt->execute([$userId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
            if ($stmt->fetch()) {
                throw new Exception('User already has a leave request for this period');
            }

            // Calculate hours
            $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
            $hoursRequested = $days * 8; // Assuming 8 hours per day

            // Get status
            $status = ($autoApprove && has_permission('timeworks_leave_manage')) ? 'approved' : 'pending';
            $approvedBy = $status === 'approved' ? $_SESSION['user_id'] : null;
            $approvedAt = $status === 'approved' ? date('Y-m-d H:i:s') : null;

            $stmt = $db->prepare("
                INSERT INTO twr_leave_requests
                (user_id, leave_type_id, start_date, end_date, hours_requested, reason, status, is_client_paid, requested_at, approved_by, approved_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $userId,
                $leaveTypeId,
                $startDate,
                $endDate,
                $hoursRequested,
                $reason,
                $status,
                $isClientPaid,
                $approvedBy,
                $approvedAt
            ]);

            $requestId = $db->lastInsertId();

            // Get user and leave type for logging
            $stmt = $db->prepare("SELECT full_name FROM twr_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userName = $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT name FROM twr_leave_types WHERE id = ?");
            $stmt->execute([$leaveTypeId]);
            $leaveTypeName = $stmt->fetchColumn();

            // Log activity
            log_activity(
                $_SESSION['user_id'],
                'leave_request_create',
                'timeworks_leave',
                "Created {$leaveTypeName} request for {$userName} ({$startDate} to {$endDate})"
            );

            // Send notification email if approved
            if ($status === 'approved') {
                $stmt = $db->prepare("SELECT email FROM twr_users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $email = $stmt->fetchColumn();

                if ($email) {
                    require_once dirname(__DIR__, 2) . '/includes/EmailHelper.php';
                    $emailHelper = new EmailHelper($db);

                    if ($emailHelper->isEnabled()) {
                        $subject = "Leave Request Approved - {$startDate} to {$endDate}";
                        $body = $emailHelper->wrapInTemplate("
                            <p>Dear {$userName},</p>

                            <p>Your leave request has been <strong style='color: #28a745;'>approved</strong>.</p>

                            <table style='width: 100%; margin: 20px 0; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Leave Type:</strong></td>
                                    <td style='padding: 10px; border: 1px solid #ddd;'>{$leaveTypeName}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Period:</strong></td>
                                    <td style='padding: 10px; border: 1px solid #ddd;'>{$startDate} to {$endDate}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Days:</strong></td>
                                    <td style='padding: 10px; border: 1px solid #ddd;'>{$days} day(s)</td>
                                </tr>
                            </table>

                            <p>Please make sure to complete any pending tasks before your leave.</p>
                        ", "Leave Request Approved");

                        $emailHelper->send($email, $subject, $body);
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Leave request created successfully',
                'id' => $requestId
            ]);
            break;

        case 'review':
            // Check permission
            if (!has_permission('timeworks_leave_manage')) {
                throw new Exception('Permission denied');
            }

            $id = $input['id'] ?? null;
            $status = $input['status'] ?? null;
            $notes = $input['notes'] ?? null;

            if (!$id || !$status) {
                throw new Exception('ID and status are required');
            }

            if (!in_array($status, ['approved', 'rejected'])) {
                throw new Exception('Invalid status');
            }

            // Get current request
            $stmt = $db->prepare("
                SELECT lr.*, u.full_name, u.email, lt.name as leave_type_name
                FROM twr_leave_requests lr
                LEFT JOIN twr_users u ON lr.user_id = u.user_id
                LEFT JOIN twr_leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.id = ?
            ");
            $stmt->execute([$id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Request not found');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('Only pending requests can be reviewed');
            }

            // Update request
            $stmt = $db->prepare("
                UPDATE twr_leave_requests
                SET status = ?, notes = ?, approved_by = ?, approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $_SESSION['user_id'], $id]);

            // Log activity
            log_activity(
                $_SESSION['user_id'],
                'leave_request_review',
                'timeworks_leave',
                ucfirst($status) . " {$request['leave_type_name']} request for {$request['full_name']}"
            );

            // Send email notification
            if (!empty($request['email'])) {
                require_once dirname(__DIR__, 2) . '/includes/EmailHelper.php';
                $emailHelper = new EmailHelper($db);

                if ($emailHelper->isEnabled()) {
                    $statusColor = $status === 'approved' ? '#28a745' : '#dc3545';
                    $statusLabel = ucfirst($status);

                    $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / 86400 + 1;

                    $subject = "Leave Request {$statusLabel}";
                    $body = $emailHelper->wrapInTemplate("
                        <p>Dear {$request['full_name']},</p>

                        <p>Your leave request has been reviewed.</p>

                        <div style='padding: 20px; background: {$statusColor}; color: white; text-align: center; border-radius: 5px; margin: 20px 0;'>
                            <h2 style='margin: 0;'>{$statusLabel}</h2>
                        </div>

                        <table style='width: 100%; margin: 20px 0; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Leave Type:</strong></td>
                                <td style='padding: 10px; border: 1px solid #ddd;'>{$request['leave_type_name']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Period:</strong></td>
                                <td style='padding: 10px; border: 1px solid #ddd;'>{$request['start_date']} to {$request['end_date']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'><strong>Days:</strong></td>
                                <td style='padding: 10px; border: 1px solid #ddd;'>{$days} day(s)</td>
                            </tr>
                        </table>

                        " . ($notes ? "<p><strong>Notes:</strong><br>{$notes}</p>" : "") . "

                        <p>If you have any questions, please contact HR.</p>
                    ", "Leave Request {$statusLabel}");

                    $emailHelper->send($request['email'], $subject, $body);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Request ' . $status . ' successfully'
            ]);
            break;

        case 'cancel':
            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('ID is required');
            }

            // Get request
            $stmt = $db->prepare("SELECT * FROM twr_leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Request not found');
            }

            // Only pending requests can be cancelled
            if ($request['status'] !== 'pending') {
                throw new Exception('Only pending requests can be cancelled');
            }

            $stmt = $db->prepare("UPDATE twr_leave_requests SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Request cancelled successfully'
            ]);
            break;

        case 'get_balance':
            $userId = $input['user_id'] ?? null;
            $year = $input['year'] ?? date('Y');

            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $stmt = $db->prepare("
                SELECT
                    lb.*,
                    lt.name as leave_type_name,
                    lt.code as leave_type_code
                FROM twr_leave_balances lb
                JOIN twr_leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.user_id = ? AND lb.year = ?
            ");
            $stmt->execute([$userId, $year]);
            $balances = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'balances' => $balances
            ]);
            break;

        case 'get_calendar':
            $startDate = $input['start'] ?? date('Y-m-01');
            $endDate = $input['end'] ?? date('Y-m-t');

            $stmt = $db->prepare("
                SELECT
                    lr.id,
                    lr.user_id,
                    u.full_name as title,
                    lr.start_date as start,
                    DATE_ADD(lr.end_date, INTERVAL 1 DAY) as end,
                    lt.color_code as color,
                    lt.name as leave_type,
                    lr.status
                FROM twr_leave_requests lr
                JOIN twr_users u ON lr.user_id = u.user_id
                JOIN twr_leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.status = 'approved'
                AND ((lr.start_date BETWEEN ? AND ?) OR (lr.end_date BETWEEN ? AND ?))
            ");
            $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
            $events = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'events' => $events
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
