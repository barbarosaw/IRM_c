<?php
/**
 * ChatSession Model
 * Handles chat session CRUD operations
 */

class ChatSession
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all sessions with pagination and filters
     */
    public function getAll($page = 1, $limit = 20, $filters = [])
    {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['intent'])) {
            $where[] = 's.primary_intent = ?';
            $params[] = $filters['intent'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.started_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.started_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = '(s.id LIKE ? OR s.visitor_id LIKE ? OR s.initial_page_url LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM chat_sessions s WHERE {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        // Get sessions
        $sql = "
            SELECT s.*,
                   (SELECT COUNT(*) FROM chat_messages WHERE session_id = s.id) as message_count,
                   (SELECT content FROM chat_messages WHERE session_id = s.id AND role = 'user' ORDER BY created_at LIMIT 1) as first_message
            FROM chat_sessions s
            WHERE {$whereClause}
            ORDER BY s.started_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $sessions,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get single session with messages
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM chat_messages WHERE session_id = s.id) as message_count
            FROM chat_sessions s
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            // Get messages
            $stmt = $this->db->prepare("
                SELECT *
                FROM chat_messages
                WHERE session_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$id]);
            $session['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $session;
    }

    /**
     * Get session statistics
     */
    public function getStats($dateFrom = null, $dateTo = null)
    {
        $where = '';
        $params = [];

        if ($dateFrom) {
            $where .= ' AND started_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo) {
            $where .= ' AND started_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        // Total sessions
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE 1=1 {$where}");
        $stmt->execute($params);
        $totalSessions = $stmt->fetchColumn();

        // Active sessions
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM chat_sessions WHERE status = 'active' {$where}");
        $stmt->execute($params);
        $activeSessions = $stmt->fetchColumn();

        // Total messages
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM chat_messages m
            JOIN chat_sessions s ON m.session_id = s.id
            WHERE 1=1 {$where}
        ");
        $stmt->execute($params);
        $totalMessages = $stmt->fetchColumn();

        // Sessions by intent
        $stmt = $this->db->prepare("
            SELECT primary_intent, COUNT(*) as count
            FROM chat_sessions
            WHERE primary_intent IS NOT NULL {$where}
            GROUP BY primary_intent
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $intentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Today's sessions
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM chat_sessions
            WHERE DATE(started_at) = CURDATE()
        ");
        $stmt->execute();
        $todaySessions = $stmt->fetchColumn();

        return [
            'total_sessions' => (int)$totalSessions,
            'active_sessions' => (int)$activeSessions,
            'total_messages' => (int)$totalMessages,
            'today_sessions' => (int)$todaySessions,
            'by_intent' => $intentStats
        ];
    }

    /**
     * Get recent sessions
     */
    public function getRecent($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM chat_messages WHERE session_id = s.id) as message_count,
                   (SELECT content FROM chat_messages WHERE session_id = s.id AND role = 'user' ORDER BY created_at LIMIT 1) as first_message
            FROM chat_sessions s
            ORDER BY s.started_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unique intents
     */
    public function getIntents()
    {
        $stmt = $this->db->query("
            SELECT DISTINCT primary_intent
            FROM chat_sessions
            WHERE primary_intent IS NOT NULL AND primary_intent != ''
            ORDER BY primary_intent
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
