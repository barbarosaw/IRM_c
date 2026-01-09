<?php
/**
 * ChatMessage Model
 * Handles chat message operations
 */

class ChatMessage
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get messages by session ID
     */
    public function getBySessionId($sessionId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM chat_messages
            WHERE session_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get message by ID
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM chat_messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get message statistics
     */
    public function getStats($dateFrom = null, $dateTo = null)
    {
        $where = '';
        $params = [];

        if ($dateFrom) {
            $where .= ' AND m.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo) {
            $where .= ' AND m.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        // Messages by role
        $stmt = $this->db->prepare("
            SELECT role, COUNT(*) as count
            FROM chat_messages m
            WHERE 1=1 {$where}
            GROUP BY role
        ");
        $stmt->execute($params);
        $byRole = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Average response time
        $stmt = $this->db->prepare("
            SELECT AVG(response_time_ms) as avg_response_time
            FROM chat_messages m
            WHERE role = 'assistant' AND response_time_ms > 0 {$where}
        ");
        $stmt->execute($params);
        $avgResponseTime = $stmt->fetchColumn();

        // Total tokens used
        $stmt = $this->db->prepare("
            SELECT SUM(tokens_used) as total_tokens
            FROM chat_messages m
            WHERE 1=1 {$where}
        ");
        $stmt->execute($params);
        $totalTokens = $stmt->fetchColumn();

        return [
            'by_role' => $byRole,
            'avg_response_time_ms' => (int)$avgResponseTime,
            'total_tokens' => (int)$totalTokens
        ];
    }

    /**
     * Search messages
     */
    public function search($query, $limit = 50)
    {
        $stmt = $this->db->prepare("
            SELECT m.*, s.visitor_id, s.started_at as session_started
            FROM chat_messages m
            JOIN chat_sessions s ON m.session_id = s.id
            WHERE m.content LIKE ?
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute(['%' . $query . '%', $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
