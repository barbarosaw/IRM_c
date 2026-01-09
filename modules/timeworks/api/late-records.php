<?php
/**
 * TimeWorks Module - Late Records API
 *
 * API endpoint for managing late attendance records.
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
        case 'update_status':
            // Check permission
            if (!has_permission('timeworks_late_manage')) {
                throw new Exception('Permission denied');
            }

            $id = $input['id'] ?? null;
            $status = $input['status'] ?? null;
            $hrNotes = $input['hr_notes'] ?? null;

            if (!$id || !$status) {
                throw new Exception('ID and status are required');
            }

            // Validate status
            $validStatuses = ['pending', 'late_without_notice', 'late_with_notice', 'excused', 'rejected', 'absent'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status');
            }

            // Update record
            $stmt = $db->prepare("
                UPDATE twr_late_records
                SET status = ?,
                    hr_notes = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $hrNotes, $_SESSION['user_id'], $id]);

            // Get record details for logging
            $stmt = $db->prepare("
                SELECT lr.*, u.full_name, u.email
                FROM twr_late_records lr
                LEFT JOIN twr_users u ON lr.user_id = u.user_id
                WHERE lr.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();

            // Log activity
            log_activity(
                $_SESSION['user_id'],
                'late_record_update',
                'timeworks_late',
                "Updated late record for {$record['full_name']} ({$record['shift_date']}) to {$status}"
            );

            // Send notification email to user if approved or rejected
            if (in_array($status, ['late_with_notice', 'rejected']) && !empty($record['email'])) {
                require_once dirname(__DIR__, 2) . '/includes/EmailHelper.php';
                $emailHelper = new EmailHelper($db);

                if ($emailHelper->isEnabled()) {
                    $statusLabel = $status === 'late_with_notice' ? 'Approved' : 'Rejected';
                    $statusColor = $status === 'late_with_notice' ? '#28a745' : '#dc3545';

                    $subject = "Late Notice Review - {$statusLabel}";
                    $body = $emailHelper->wrapInTemplate("
                        <p>Dear {$record['full_name']},</p>

                        <p>Your late notice evidence for <strong>{$record['shift_date']}</strong> has been reviewed.</p>

                        <div style='padding: 20px; background: {$statusColor}; color: white; text-align: center; border-radius: 5px; margin: 20px 0;'>
                            <h2 style='margin: 0;'>{$statusLabel}</h2>
                        </div>

                        " . ($hrNotes ? "<p><strong>HR Notes:</strong><br>{$hrNotes}</p>" : "") . "

                        <p>If you have any questions, please contact HR.</p>
                    ", "Late Notice Review");

                    $emailHelper->send($record['email'], $subject, $body);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Record updated successfully'
            ]);
            break;

        case 'get_record':
            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('ID is required');
            }

            $stmt = $db->prepare("
                SELECT lr.*, u.full_name, u.email
                FROM twr_late_records lr
                LEFT JOIN twr_users u ON lr.user_id = u.user_id
                WHERE lr.id = ?
            ");
            $stmt->execute([$id]);
            $record = $stmt->fetch();

            if (!$record) {
                throw new Exception('Record not found');
            }

            echo json_encode([
                'success' => true,
                'record' => $record
            ]);
            break;

        case 'get_statistics':
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-d');

            $stmt = $db->prepare("
                SELECT
                    status,
                    COUNT(*) as count,
                    AVG(late_minutes) as avg_late_minutes
                FROM twr_late_records
                WHERE shift_date BETWEEN ? AND ?
                GROUP BY status
            ");
            $stmt->execute([$startDate, $endDate]);
            $stats = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'statistics' => $stats,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);
            break;

        case 'bulk_update':
            // Check permission
            if (!has_permission('timeworks_late_manage')) {
                throw new Exception('Permission denied');
            }

            $ids = $input['ids'] ?? [];
            $status = $input['status'] ?? null;

            if (empty($ids) || !$status) {
                throw new Exception('IDs and status are required');
            }

            $validStatuses = ['pending', 'late_without_notice', 'late_with_notice', 'excused', 'rejected'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status');
            }

            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $db->prepare("
                UPDATE twr_late_records
                SET status = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id IN ({$placeholders})
            ");
            $stmt->execute(array_merge([$status, $_SESSION['user_id']], $ids));

            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' records updated'
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
