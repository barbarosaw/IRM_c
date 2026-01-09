<?php
/**
 * Phone Calls Module - PhoneCall Model
 */

if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class PhoneCall {
    private $db;

    public function __construct($db = null) {
        if ($db === null) {
            global $db;
            $this->db = $db;
        } else {
            $this->db = $db;
        }
    }

    /**
     * Create a new call record
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO phone_calls
                (call_sid, user_id, direction, from_number, to_number, status, started_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $data['call_sid'] ?? null,
                $data['user_id'],
                $data['direction'] ?? 'outbound',
                $data['from_number'] ?? null,
                $data['to_number'] ?? null,
                $data['status'] ?? 'initiated'
            ]);

            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating phone call: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update call by SID
     */
    public function updateBySid($callSid, $data) {
        try {
            $fields = [];
            $values = [];

            $allowedFields = ['status', 'duration', 'cost', 'recording_url', 'recording_duration', 'ended_at'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $values[] = $callSid;

            $stmt = $this->db->prepare("
                UPDATE phone_calls
                SET " . implode(', ', $fields) . "
                WHERE call_sid = ?
            ");

            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Error updating phone call: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get call by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT pc.*, u.name as user_name
            FROM phone_calls pc
            LEFT JOIN users u ON pc.user_id = u.id
            WHERE pc.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get call by SID
     */
    public function getBySid($callSid) {
        $stmt = $this->db->prepare("
            SELECT pc.*, u.name as user_name
            FROM phone_calls pc
            LEFT JOIN users u ON pc.user_id = u.id
            WHERE pc.call_sid = ?
        ");
        $stmt->execute([$callSid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get calls for a user
     */
    public function getByUserId($userId, $limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM phone_calls
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all calls with filters
     */
    public function getAll($filters = [], $limit = 50, $offset = 0) {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "pc.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "pc.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['direction'])) {
            $where[] = "pc.direction = ?";
            $params[] = $filters['direction'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(pc.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(pc.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(pc.to_number LIKE ? OR pc.from_number LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT pc.*, u.name as user_name
            FROM phone_calls pc
            LEFT JOIN users u ON pc.user_id = u.id
            $whereClause
            ORDER BY pc.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total calls with filters
     */
    public function countAll($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['direction'])) {
            $where[] = "direction = ?";
            $params[] = $filters['direction'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM phone_calls $whereClause");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Get recent calls for a user
     */
    public function getRecent($userId, $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT * FROM phone_calls
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get call statistics for a user
     */
    public function getUserStats($userId, $period = 'month') {
        $dateCondition = match($period) {
            'today' => "DATE(created_at) = CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "1=1"
        };

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_calls,
                SUM(duration) as total_duration,
                SUM(cost) as total_cost,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_calls,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_calls
            FROM phone_calls
            WHERE user_id = ? AND $dateCondition
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get overall statistics (admin)
     */
    public function getOverallStats($period = 'month') {
        $dateCondition = match($period) {
            'today' => "DATE(created_at) = CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "1=1"
        };

        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_calls,
                SUM(duration) as total_duration,
                SUM(cost) as total_cost,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_calls
            FROM phone_calls
            WHERE $dateCondition
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get stats grouped by user (admin)
     */
    public function getStatsByUser($period = 'month') {
        $dateCondition = match($period) {
            'today' => "DATE(pc.created_at) = CURDATE()",
            'week' => "pc.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "pc.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "pc.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "1=1"
        };

        $stmt = $this->db->query("
            SELECT
                u.id as user_id,
                u.name,
                COUNT(*) as total_calls,
                SUM(pc.duration) as total_duration,
                SUM(pc.cost) as total_cost
            FROM phone_calls pc
            JOIN users u ON pc.user_id = u.id
            WHERE $dateCondition
            GROUP BY u.id, u.name
            ORDER BY total_calls DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get stats grouped by day
     */
    public function getStatsByDay($days = 30) {
        $stmt = $this->db->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as total_calls,
                SUM(duration) as total_duration,
                SUM(cost) as total_cost
            FROM phone_calls
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Format duration for display (seconds to MM:SS)
     */
    public static function formatDuration($seconds) {
        if (!$seconds) return '0:00';
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Format cost for display
     */
    public static function formatCost($cost) {
        return '$' . number_format($cost, 4);
    }

    /**
     * Get status badge HTML
     */
    public static function getStatusBadge($status) {
        $badges = [
            'initiated' => '<span class="badge bg-secondary">Initiated</span>',
            'ringing' => '<span class="badge bg-info">Ringing</span>',
            'in-progress' => '<span class="badge bg-primary">In Progress</span>',
            'completed' => '<span class="badge bg-success">Completed</span>',
            'busy' => '<span class="badge bg-warning">Busy</span>',
            'no-answer' => '<span class="badge bg-warning">No Answer</span>',
            'canceled' => '<span class="badge bg-secondary">Canceled</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>'
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}
