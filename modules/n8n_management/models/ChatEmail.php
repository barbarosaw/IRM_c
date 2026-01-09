<?php
/**
 * ChatEmail Model
 * Handles email tracking and sending
 */

class ChatEmail
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all emails with pagination
     */
    public function getAll($page = 1, $limit = 20, $filters = [])
    {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['email_type'])) {
            $where[] = 'e.email_type = ?';
            $params[] = $filters['email_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'e.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'e.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM n8n_chat_emails e WHERE {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        // Get emails
        $sql = "
            SELECT e.*, s.visitor_id, s.primary_intent
            FROM n8n_chat_emails e
            LEFT JOIN chat_sessions s ON e.session_id = s.id
            WHERE {$whereClause}
            ORDER BY e.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $emails,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get single email
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("
            SELECT e.*, s.visitor_id, s.primary_intent, s.started_at as session_started
            FROM n8n_chat_emails e
            LEFT JOIN chat_sessions s ON e.session_id = s.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get email by tracking ID
     */
    public function getByTrackingId($trackingId)
    {
        $stmt = $this->db->prepare("SELECT * FROM n8n_chat_emails WHERE tracking_id = ?");
        $stmt->execute([$trackingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending emails for processing
     */
    public function getPending($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM n8n_chat_emails
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark email as sent
     */
    public function markSent($id)
    {
        $stmt = $this->db->prepare("
            UPDATE n8n_chat_emails
            SET status = 'sent', sent_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Mark email as failed
     */
    public function markFailed($id, $errorMessage)
    {
        $stmt = $this->db->prepare("
            UPDATE n8n_chat_emails
            SET status = 'failed', error_message = ?
            WHERE id = ?
        ");
        return $stmt->execute([$errorMessage, $id]);
    }

    /**
     * Get email statistics
     */
    public function getStats()
    {
        $stats = [];

        // By status
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count
            FROM n8n_chat_emails
            GROUP BY status
        ");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Total emails
        $stmt = $this->db->query("SELECT COUNT(*) FROM n8n_chat_emails");
        $stats['total'] = (int)$stmt->fetchColumn();

        // Sent today
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM n8n_chat_emails
            WHERE DATE(sent_at) = CURDATE()
        ");
        $stats['sent_today'] = (int)$stmt->fetchColumn();

        // Open rate
        $stmt = $this->db->query("
            SELECT
                COUNT(CASE WHEN status = 'opened' THEN 1 END) as opened,
                COUNT(CASE WHEN status IN ('sent', 'opened') THEN 1 END) as delivered
            FROM n8n_chat_emails
        ");
        $openStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['open_rate'] = $openStats['delivered'] > 0
            ? round(($openStats['opened'] / $openStats['delivered']) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Queue a new email
     */
    public function queue($sessionId, $emailType, $recipient, $cc, $bcc, $subject, $body)
    {
        $trackingId = bin2hex(random_bytes(18));

        $stmt = $this->db->prepare("
            INSERT INTO n8n_chat_emails
            (session_id, email_type, recipient_email, cc_emails, bcc_emails, subject, body, tracking_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$sessionId, $emailType, $recipient, $cc, $bcc, $subject, $body, $trackingId]);

        return [
            'id' => $this->db->lastInsertId(),
            'tracking_id' => $trackingId
        ];
    }
}
