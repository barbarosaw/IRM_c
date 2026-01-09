<?php
/**
 * TimeWorks Module - Client Sync API
 *
 * API endpoint for syncing clients from TimeWorks and managing user-client assignments.
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';
require_once '../models/TimeWorksAPI.php';

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
    $input = $_POST;
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'sync':
            // Check permission
            if (!has_permission('timeworks_sync_manage')) {
                throw new Exception('Permission denied');
            }

            $api = new TimeWorksAPI($db);
            $synced = 0;
            $offset = 0;
            $limit = 100;
            $totalFetched = 0;

            // Paginate through all clients
            do {
                $response = $api->getClients($limit, $offset);

                if (!$response) {
                    error_log("TimeWorks Client Sync: API returned null response at offset $offset");
                    if ($offset === 0) {
                        throw new Exception('Failed to fetch clients from TimeWorks API');
                    }
                    break; // Stop pagination if we already have some clients
                }

                // Handle different API response formats
                $clients = [];
                if (isset($response['data'])) {
                    $clients = $response['data'];
                } elseif (isset($response['clients'])) {
                    $clients = $response['clients'];
                } elseif (isset($response['items'])) {
                    $clients = $response['items'];
                }

                if (empty($clients)) {
                    if ($offset === 0) {
                        error_log("TimeWorks Client Sync: Response structure: " . json_encode(array_keys($response)));
                        throw new Exception('No clients found in API response. Check error log for details.');
                    }
                    break; // No more clients
                }

                $totalFromAPI = $response['total'] ?? 0;
                $batchCount = count($clients);
                $totalFetched += $batchCount;

                foreach ($clients as $client) {
                    $clientId = $client['id'] ?? null;
                    if (!$clientId) continue;

                    $name = $client['name'] ?? 'Unknown';
                    // API uses contact_email
                    $email = $client['contact_email'] ?? $client['email'] ?? null;
                    // API uses status directly as 'active'/'inactive' string
                    $status = $client['status'] ?? 'inactive';

                    // Check if client exists
                    $stmt = $db->prepare("SELECT id FROM twr_clients WHERE client_id = ?");
                    $stmt->execute([$clientId]);

                    if ($stmt->fetch()) {
                        // Update
                        $stmt = $db->prepare("
                            UPDATE twr_clients
                            SET name = ?, email = ?, status = ?, synced_at = NOW(), updated_at = NOW()
                            WHERE client_id = ?
                        ");
                        $stmt->execute([$name, $email, $status, $clientId]);
                    } else {
                        // Insert
                        $stmt = $db->prepare("
                            INSERT INTO twr_clients (client_id, name, email, status, synced_at, created_at, updated_at)
                            VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())
                        ");
                        $stmt->execute([$clientId, $name, $email, $status]);
                    }

                    $synced++;
                }

                // Move to next page
                $offset += $limit;

            } while ($batchCount >= $limit && $totalFetched < $totalFromAPI);

            // Log sync
            log_activity(
                $_SESSION['user_id'],
                'client_sync',
                'timeworks_clients',
                "Synced {$synced} clients from TimeWorks API"
            );

            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'message' => "Successfully synced {$synced} clients"
            ]);
            break;

        case 'assign':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $clientId = $input['client_id'] ?? null;
            $userIds = $input['user_ids'] ?? [];
            $assignmentType = $input['assignment_type'] ?? 'direct';
            $notes = $input['notes'] ?? null;

            if (!$clientId || empty($userIds)) {
                throw new Exception('Client and users are required');
            }

            $assigned = 0;
            foreach ($userIds as $userId) {
                // Check if already assigned
                $stmt = $db->prepare("SELECT id FROM twr_user_clients WHERE user_id = ? AND client_id = ?");
                $stmt->execute([$userId, $clientId]);

                if (!$stmt->fetch()) {
                    $stmt = $db->prepare("
                        INSERT INTO twr_user_clients
                        (user_id, client_id, assignment_type, notes, assigned_by, assigned_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$userId, $clientId, $assignmentType, $notes, $_SESSION['user_id']]);
                    $assigned++;
                }
            }

            echo json_encode([
                'success' => true,
                'assigned' => $assigned,
                'message' => "Assigned {$assigned} users to client"
            ]);
            break;

        case 'remove_assignment':
            if (!has_permission('timeworks_users_manage')) {
                throw new Exception('Permission denied');
            }

            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('Assignment ID is required');
            }

            $stmt = $db->prepare("DELETE FROM twr_user_clients WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Assignment removed successfully'
            ]);
            break;

        case 'list_assignments':
            $stmt = $db->query("
                SELECT
                    uc.id,
                    uc.user_id,
                    u.full_name,
                    uc.client_id,
                    c.name as client_name,
                    uc.assignment_type,
                    uc.notes,
                    uc.assigned_at
                FROM twr_user_clients uc
                JOIN twr_users u ON uc.user_id = u.user_id
                JOIN twr_clients c ON uc.client_id = c.client_id
                ORDER BY u.full_name, c.name
            ");
            $assignments = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $assignments
            ]);
            break;

        case 'get_client':
            $clientId = $input['client_id'] ?? null;

            if (!$clientId) {
                throw new Exception('Client ID is required');
            }

            $stmt = $db->prepare("SELECT * FROM twr_clients WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();

            if (!$client) {
                throw new Exception('Client not found');
            }

            // Get assigned users
            $stmt = $db->prepare("
                SELECT uc.*, u.full_name, u.email
                FROM twr_user_clients uc
                JOIN twr_users u ON uc.user_id = u.user_id
                WHERE uc.client_id = ?
                ORDER BY u.full_name
            ");
            $stmt->execute([$clientId]);
            $users = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'client' => $client,
                'users' => $users
            ]);
            break;

        case 'get_clients':
            $stmt = $db->query("
                SELECT c.*, COUNT(uc.id) as user_count
                FROM twr_clients c
                LEFT JOIN twr_user_clients uc ON c.client_id = uc.client_id
                WHERE c.status = 'active'
                GROUP BY c.id
                ORDER BY c.name
            ");
            $clients = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $clients
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
