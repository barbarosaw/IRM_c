<?php
/**
 * PH Communications Module - SMS Message Model
 */

if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class SMSMessage {
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
     * Create a new SMS message record
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sms_messages
                (user_id, message_id, direction, from_number, to_number, message,
                 status, status_code, telco_id, msgcount, shortcode_mask, rcvd_transid, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $data['user_id'],
                $data['message_id'] ?? null,
                $data['direction'] ?? 'outbound',
                $data['from_number'] ?? null,
                $data['to_number'],
                $data['message'],
                $data['status'] ?? 'pending',
                $data['status_code'] ?? null,
                $data['telco_id'] ?? null,
                $data['msgcount'] ?? 1,
                $data['shortcode_mask'] ?? null,
                $data['rcvd_transid'] ?? null
            ]);

            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating SMS message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update SMS message by message_id
     */
    public function updateByMessageId($messageId, $data) {
        try {
            $fields = [];
            $values = [];

            $allowedFields = ['status', 'status_code', 'delivered_at'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $values[] = $messageId;

            $stmt = $this->db->prepare("
                UPDATE sms_messages
                SET " . implode(', ', $fields) . "
                WHERE message_id = ?
            ");

            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Error updating SMS message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get message by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT sms.*, u.name as user_name
            FROM sms_messages sms
            LEFT JOIN users u ON sms.user_id = u.id
            WHERE sms.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get message by message_id
     */
    public function getByMessageId($messageId) {
        $stmt = $this->db->prepare("
            SELECT sms.*, u.name as user_name
            FROM sms_messages sms
            LEFT JOIN users u ON sms.user_id = u.id
            WHERE sms.message_id = ?
        ");
        $stmt->execute([$messageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get messages by user ID
     */
    public function getByUserId($userId, $direction = null, $limit = 50, $offset = 0) {
        $where = "user_id = ?";
        $params = [$userId];

        if ($direction) {
            $where .= " AND direction = ?";
            $params[] = $direction;
        }

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT * FROM sms_messages
            WHERE $where
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all messages with filters
     */
    public function getAll($filters = [], $limit = 50, $offset = 0) {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "sms.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['direction'])) {
            $where[] = "sms.direction = ?";
            $params[] = $filters['direction'];
        }

        if (!empty($filters['status'])) {
            $where[] = "sms.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(sms.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(sms.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(sms.to_number LIKE ? OR sms.from_number LIKE ? OR sms.message LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT sms.*, u.name as user_name
            FROM sms_messages sms
            LEFT JOIN users u ON sms.user_id = u.id
            $whereClause
            ORDER BY sms.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count messages with filters
     */
    public function countAll($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['direction'])) {
            $where[] = "direction = ?";
            $params[] = $filters['direction'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(to_number LIKE ? OR from_number LIKE ? OR message LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sms_messages $whereClause");
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Get status badge HTML
     */
    public static function getStatusBadge($status) {
        $badges = [
            'pending' => '<span class="badge bg-secondary">Pending</span>',
            'sent' => '<span class="badge bg-info">Sent</span>',
            'acknowledged' => '<span class="badge bg-success">Acknowledged</span>',
            'delivered' => '<span class="badge bg-success">Delivered</span>',
            'rejected' => '<span class="badge bg-danger">Rejected</span>',
            'expired' => '<span class="badge bg-warning">Expired</span>',
            'undelivered' => '<span class="badge bg-danger">Undelivered</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>'
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }

    /**
     * Get telco name by ID
     */
    public static function getTelcoName($telcoId) {
        $telcos = [
            1 => 'Globe',
            2 => 'Smart',
            3 => 'Sun',
            4 => 'DITO'
        ];
        return $telcos[$telcoId] ?? 'Unknown';
    }
}
