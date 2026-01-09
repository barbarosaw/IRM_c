<?php
/**
 * AbroadWorks Management System - Message Model
 * 
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

class Message {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct($db = null) {
        if ($db === null) {
            global $db;
            $this->db = $db;
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Send a message
     * 
     * @param int $sender_id Sender user ID
     * @param int $receiver_id Receiver user ID
     * @param string $subject Message subject
     * @param string $message Message content
     * @param bool $is_system Whether this is a system message
     * @param int $forward_id Optional ID of message being forwarded
     * @return int|false New message ID or false on failure
     */
    public function sendMessage($sender_id, $receiver_id, $subject, $message, $is_system = false, $forward_id = null) {
        try {
            // If this is a forwarded message, append the original message content
            if ($forward_id) {
                $forward_message = $this->getMessage($forward_id, $sender_id);
                if ($forward_message) {
                    $message .= "\n\n---------- Forwarded Message ----------\n";
                    $message .= "From: " . $forward_message['sender_name'] . "\n";
                    $message .= "Date: " . date('Y-m-d H:i', strtotime($forward_message['created_at'])) . "\n";
                    $message .= "Subject: " . $forward_message['subject'] . "\n";
                    $message .= "To: " . $forward_message['receiver_name'] . "\n\n";
                    $message .= $forward_message['message'];
                    $message .= "\n--------------------------------------";
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO messages (sender_id, receiver_id, subject, message, is_system, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $sender_id,
                $receiver_id,
                $subject,
                $message,
                $is_system ? 1 : 0
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            log_error("Error sending message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's inbox messages
     * 
     * @param int $user_id User ID
     * @param int $limit Maximum number of messages to return
     * @param int $offset Offset for pagination
     * @param bool $unread_only Whether to return only unread messages
     * @return array Messages
     */
    public function getInbox($user_id, $limit = 20, $offset = 0, $unread_only = false) {
        try {
            $query = "
                SELECT m.*, 
                       u.name as sender_name, 
                       u.profile_image as sender_image,
                       CASE WHEN u.is_system = 1 THEN 1 ELSE 0 END as is_system_user
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.receiver_id = ?
            ";
            
            if ($unread_only) {
                $query .= " AND m.is_read = 0";
            }
            
            $query .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id, $limit, $offset]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            log_error("Error getting inbox: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user's sent messages
     * 
     * @param int $user_id User ID
     * @param int $limit Maximum number of messages to return
     * @param int $offset Offset for pagination
     * @return array Messages
     */
    public function getSent($user_id, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       u.name as receiver_name, 
                       u.profile_image as receiver_image
                FROM messages m
                LEFT JOIN users u ON m.receiver_id = u.id
                WHERE m.sender_id = ?
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$user_id, $limit, $offset]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            log_error("Error getting sent messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get message by ID
     * 
     * @param int $message_id Message ID
     * @param int $user_id User ID (for security check)
     * @return array|false Message data or false if not found
     */
    public function getMessage($message_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       s.name as sender_name, 
                       s.profile_image as sender_image,
                       r.name as receiver_name, 
                       r.profile_image as receiver_image,
                       CASE WHEN s.is_system = 1 THEN 1 ELSE 0 END as is_system_user
                FROM messages m
                LEFT JOIN users s ON m.sender_id = s.id
                LEFT JOIN users r ON m.receiver_id = r.id
                WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
            ");
            
            $stmt->execute([$message_id, $user_id, $user_id]);
            $message = $stmt->fetch();
            
            if ($message && $message['receiver_id'] == $user_id && $message['is_read'] == 0) {
                $this->markAsRead($message_id, $user_id);
                $message['is_read'] = 1;
                $message['read_at'] = date('Y-m-d H:i:s');
            }
            
            return $message;
        } catch (Exception $e) {
            log_error("Error getting message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark message as read
     * 
     * @param int $message_id Message ID
     * @param int $user_id User ID (for security check)
     * @return bool Success status
     */
    public function markAsRead($message_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE messages
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND receiver_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$message_id, $user_id]);
        } catch (Exception $e) {
            log_error("Error marking message as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all messages as read
     * 
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE messages
                SET is_read = 1, read_at = NOW()
                WHERE receiver_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            log_error("Error marking all messages as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete message
     * 
     * @param int $message_id Message ID
     * @param int $user_id User ID (for security check)
     * @return bool Success status
     */
    public function deleteMessage($message_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM messages
                WHERE id = ? AND (sender_id = ? OR receiver_id = ?)
            ");
            
            return $stmt->execute([$message_id, $user_id, $user_id]);
        } catch (Exception $e) {
            log_error("Error deleting message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread message count
     * 
     * @param int $user_id User ID
     * @return int Unread message count
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM messages
                WHERE receiver_id = ? AND is_read = 0
            ");
            
            $stmt->execute([$user_id]);
            
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            log_error("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all users for messaging
     * 
     * @param int $current_user_id Current user ID (to exclude from list)
     * @return array Users
     */
    public function getUsers($current_user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, email, profile_image
                FROM users
                WHERE id != ? AND is_active = 1 AND is_system = 0
                ORDER BY name
            ");
            
            $stmt->execute([$current_user_id]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            log_error("Error getting users: " . $e->getMessage());
            return [];
        }
    }
}
