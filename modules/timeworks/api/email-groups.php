<?php
/**
 * TimeWorks Module - Email Groups API
 *
 * CRUD operations for email recipient groups
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('timeworks_email_manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_groups':
            getGroups();
            break;

        case 'get_group':
            getGroup();
            break;

        case 'create_group':
            createGroup();
            break;

        case 'update_group':
            updateGroup();
            break;

        case 'delete_group':
            deleteGroup();
            break;

        case 'get_members':
            getMembers();
            break;

        case 'add_member':
            addMember();
            break;

        case 'add_members_bulk':
            addMembersBulk();
            break;

        case 'remove_member':
            removeMember();
            break;

        case 'get_available_users':
            getAvailableUsers();
            break;

        case 'import_from_filter':
            importFromFilter();
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Email Groups API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Get all email groups
 */
function getGroups()
{
    global $db;

    $stmt = $db->query("
        SELECT g.*,
               u.name as created_by_name,
               (SELECT COUNT(*) FROM email_group_members WHERE group_id = g.id) as member_count
        FROM email_groups g
        LEFT JOIN users u ON g.created_by = u.id
        ORDER BY g.name ASC
    ");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'groups' => $groups]);
}

/**
 * Get single group with details
 */
function getGroup()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? 0);

    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    $stmt = $db->prepare("
        SELECT g.*, u.name as created_by_name
        FROM email_groups g
        LEFT JOIN users u ON g.created_by = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        return;
    }

    // Get member count
    $stmt = $db->prepare("SELECT COUNT(*) FROM email_group_members WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $group['member_count'] = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'group' => $group]);
}

/**
 * Create new email group
 */
function createGroup()
{
    global $db;

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Group name is required']);
        return;
    }

    // Check if name already exists
    $stmt = $db->prepare("SELECT id FROM email_groups WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'A group with this name already exists']);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO email_groups (name, description, created_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $description, $_SESSION['user_id']]);
    $groupId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Group created successfully',
        'group_id' => $groupId
    ]);
}

/**
 * Update email group
 */
function updateGroup()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (!$groupId || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Group ID and name are required']);
        return;
    }

    // Check if name already exists for other groups
    $stmt = $db->prepare("SELECT id FROM email_groups WHERE name = ? AND id != ?");
    $stmt->execute([$name, $groupId]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'A group with this name already exists']);
        return;
    }

    $stmt = $db->prepare("
        UPDATE email_groups
        SET name = ?, description = ?, is_active = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$name, $description, $isActive, $groupId]);

    echo json_encode(['success' => true, 'message' => 'Group updated successfully']);
}

/**
 * Delete email group
 */
function deleteGroup()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? 0);

    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    // Delete group (members will be deleted by CASCADE)
    $stmt = $db->prepare("DELETE FROM email_groups WHERE id = ?");
    $stmt->execute([$groupId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
    }
}

/**
 * Get members of a group
 */
function getMembers()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? 0);

    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    $stmt = $db->prepare("
        SELECT m.*, u.full_name as user_name, u.status as user_status
        FROM email_group_members m
        LEFT JOIN twr_users u ON m.user_id = u.user_id
        WHERE m.group_id = ?
        ORDER BY m.name ASC, m.email ASC
    ");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'members' => $members]);
}

/**
 * Add single member to group
 */
function addMember()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;

    if (!$groupId || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Group ID and email are required']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }

    // Check if already exists
    $stmt = $db->prepare("SELECT id FROM email_group_members WHERE group_id = ? AND email = ?");
    $stmt->execute([$groupId, $email]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'This email is already in the group']);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO email_group_members (group_id, user_id, email, name, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$groupId, $userId, $email, $name]);

    echo json_encode(['success' => true, 'message' => 'Member added successfully']);
}

/**
 * Add multiple members to group (from user selection)
 */
function addMembersBulk()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? 0);
    $userIds = $_POST['user_ids'] ?? [];

    if (!$groupId || empty($userIds)) {
        echo json_encode(['success' => false, 'message' => 'Group ID and user IDs are required']);
        return;
    }

    if (!is_array($userIds)) {
        $userIds = json_decode($userIds, true);
    }

    $added = 0;
    $skipped = 0;

    $db->beginTransaction();

    try {
        foreach ($userIds as $userId) {
            $odooUserId = trim($userId);

            // Get user info from twr_users
            $stmt = $db->prepare("SELECT user_id, full_name, email FROM twr_users WHERE user_id = ? AND email IS NOT NULL AND email != ''");
            $stmt->execute([$odooUserId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $skipped++;
                continue;
            }

            // Check if already exists
            $stmt = $db->prepare("SELECT id FROM email_group_members WHERE group_id = ? AND email = ?");
            $stmt->execute([$groupId, $user['email']]);
            if ($stmt->fetchColumn()) {
                $skipped++;
                continue;
            }

            // Insert
            $stmt = $db->prepare("
                INSERT INTO email_group_members (group_id, user_id, email, name, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$groupId, $user['user_id'], $user['email'], $user['full_name']]);
            $added++;
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "$added members added" . ($skipped > 0 ? " ($skipped skipped)" : ""),
            'added' => $added,
            'skipped' => $skipped
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Remove member from group
 */
function removeMember()
{
    global $db;

    $memberId = (int)($_POST['member_id'] ?? 0);

    if (!$memberId) {
        echo json_encode(['success' => false, 'message' => 'Member ID required']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM email_group_members WHERE id = ?");
    $stmt->execute([$memberId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Member removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
}

/**
 * Get users available to add to group (not already in group)
 */
function getAvailableUsers()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
    $search = trim($_POST['search'] ?? $_GET['search'] ?? '');

    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    $query = "
        SELECT u.user_id as id, u.full_name as name, u.email,
               CASE WHEN u.status = 'active' THEN 1 ELSE 0 END as is_active
        FROM twr_users u
        WHERE u.email IS NOT NULL AND u.email != ''
        AND u.email NOT IN (SELECT email FROM email_group_members WHERE group_id = ?)
    ";
    $params = [$groupId];

    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY u.full_name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Import users from a filter (like bulk-email filters)
 */
function importFromFilter()
{
    global $db;

    $groupId = (int)($_POST['group_id'] ?? 0);
    $filter = $_POST['filter'] ?? 'all';

    if (!$groupId) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    // Build query based on filter - using twr_users table
    $query = "SELECT user_id, full_name, email FROM twr_users WHERE email IS NOT NULL AND email != ''";

    switch ($filter) {
        case 'active':
            $query .= " AND status = 'active'";
            break;
        case 'inactive':
            $query .= " AND status = 'inactive'";
            break;
        case 'without_activity':
            $query .= " AND status = 'active' AND (activity_checked_at IS NOT NULL AND activity_days = 0 AND activity_hours = 0)";
            break;
        case 'with_activity':
            $query .= " AND status = 'active' AND (activity_days > 0 OR activity_hours > 0)";
            break;
    }

    $stmt = $db->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $added = 0;
    $skipped = 0;

    $db->beginTransaction();

    try {
        foreach ($users as $user) {
            // Check if already exists
            $stmt = $db->prepare("SELECT id FROM email_group_members WHERE group_id = ? AND email = ?");
            $stmt->execute([$groupId, $user['email']]);
            if ($stmt->fetchColumn()) {
                $skipped++;
                continue;
            }

            // Insert
            $stmt = $db->prepare("
                INSERT INTO email_group_members (group_id, user_id, email, name, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$groupId, $user['user_id'], $user['email'], $user['full_name']]);
            $added++;
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "$added members imported" . ($skipped > 0 ? " ($skipped already existed)" : ""),
            'added' => $added,
            'skipped' => $skipped
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
