<?php
/**
 * AbroadWorks Management System - Notification Model
 * 
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

class Notification {
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
     * Create a new notification
     * 
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $entity_type Entity type (optional)
     * @param int $entity_id Entity ID (optional)
     * @param array $user_ids Array of user IDs to notify (empty for all users)
     * @return int|false New notification ID or false on failure
     */
    public function createNotification($type, $title, $message, $entity_type = null, $entity_id = null, $user_ids = []) {
        try {
            $this->db->beginTransaction();
            
            // Create notification
            $stmt = $this->db->prepare("
                INSERT INTO system_notifications (type, title, message, entity_type, entity_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $type,
                $title,
                $message,
                $entity_type,
                $entity_id
            ]);
            
            if (!$result) {
                $this->db->rollBack();
                return false;
            }
            
            $notification_id = $this->db->lastInsertId();
            
            // Assign to users
            if (empty($user_ids)) {
                // Assign to all active users
                $user_stmt = $this->db->prepare("
                    INSERT INTO user_notifications (user_id, notification_id, is_read, read_at)
                    SELECT id, ?, 0, NULL FROM users WHERE is_active = 1 AND is_system = 0
                ");
                
                $result = $user_stmt->execute([$notification_id]);
            } else {
                // Assign to specific users
                $values = [];
                $params = [];
                
                foreach ($user_ids as $user_id) {
                    $values[] = "(?, ?, 0, NULL)";
                    $params[] = $user_id;
                    $params[] = $notification_id;
                }
                
                $user_stmt = $this->db->prepare("
                    INSERT INTO user_notifications (user_id, notification_id, is_read, read_at)
                    VALUES " . implode(', ', $values)
                );
                
                $result = $user_stmt->execute($params);
            }
            
            if (!$result) {
                $this->db->rollBack();
                return false;
            }
            
            // Also create a system message for each user
            $system_user_id = $this->getSystemUserId();
            
            if ($system_user_id) {
                if (empty($user_ids)) {
                    // Send to all active users
                    $message_stmt = $this->db->prepare("
                        INSERT INTO messages (sender_id, receiver_id, subject, message, is_system, created_at)
                        SELECT ?, id, ?, ?, 1, NOW() FROM users WHERE is_active = 1 AND is_system = 0
                    ");
                    
                    $message_stmt->execute([
                        $system_user_id,
                        $title,
                        $message
                    ]);
                } else {
                    // Send to specific users
                    $message_values = [];
                    $message_params = [];
                    
                    foreach ($user_ids as $user_id) {
                        $message_values[] = "(?, ?, ?, ?, 1, NOW())";
                        $message_params[] = $system_user_id;
                        $message_params[] = $user_id;
                        $message_params[] = $title;
                        $message_params[] = $message;
                    }
                    
                    $message_stmt = $this->db->prepare("
                        INSERT INTO messages (sender_id, receiver_id, subject, message, is_system, created_at)
                        VALUES " . implode(', ', $message_values)
                    );
                    
                    $message_stmt->execute($message_params);
                }
            }
            
            $this->db->commit();
            return $notification_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's notifications
     * 
     * @param int $user_id User ID
     * @param int $limit Maximum number of notifications to return
     * @param int $offset Offset for pagination
     * @param bool $unread_only Whether to return only unread notifications
     * @return array Notifications
     */
    public function getUserNotifications($user_id, $limit = 20, $offset = 0, $unread_only = false) {
        try {
            $query = "
                SELECT n.*, un.is_read, un.read_at
                FROM system_notifications n
                JOIN user_notifications un ON n.id = un.notification_id
                WHERE un.user_id = ?
            ";
            
            if ($unread_only) {
                $query .= " AND un.is_read = 0";
            }
            
            $query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id, $limit, $offset]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            log_error("Error getting user notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notification by ID
     * 
     * @param int $notification_id Notification ID
     * @param int $user_id User ID (for security check)
     * @return array|false Notification data or false if not found
     */
    public function getNotification($notification_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT n.*, un.is_read, un.read_at
                FROM system_notifications n
                JOIN user_notifications un ON n.id = un.notification_id
                WHERE n.id = ? AND un.user_id = ?
            ");
            
            $stmt->execute([$notification_id, $user_id]);
            $notification = $stmt->fetch();
            
            if ($notification && $notification['is_read'] == 0) {
                $this->markAsRead($notification_id, $user_id);
                $notification['is_read'] = 1;
                $notification['read_at'] = date('Y-m-d H:i:s');
            }
            
            return $notification;
        } catch (Exception $e) {
            log_error("Error getting notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification read status for all users
     * 
     * @param int $notification_id Notification ID
     * @return array Array of user read status
     */
    public function getNotificationReadStatus($notification_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.email, un.is_read, un.read_at
                FROM users u
                JOIN user_notifications un ON u.id = un.user_id
                WHERE un.notification_id = ? AND u.is_system = 0
                ORDER BY u.name
            ");
            
            $stmt->execute([$notification_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            log_error("Error getting notification read status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $notification_id Notification ID
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_notifications
                SET is_read = 1, read_at = NOW()
                WHERE notification_id = ? AND user_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            log_error("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read
     * 
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ");
            
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            log_error("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete notification
     * 
     * @param int $notification_id Notification ID
     * @param int $user_id User ID (for security check)
     * @return bool Success status
     */
    public function deleteNotification($notification_id, $user_id) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM user_notifications
                WHERE notification_id = ? AND user_id = ?
            ");
            
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            log_error("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count
     * 
     * @param int $user_id User ID
     * @return int Unread notification count
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_notifications
                WHERE user_id = ? AND is_read = 0
            ");
            
            $stmt->execute([$user_id]);
            
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            log_error("Error getting unread notification count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get system user ID
     * 
     * @return int|false System user ID or false if not found
     */
    private function getSystemUserId() {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM users
                WHERE is_system = 1
                LIMIT 1
            ");
            
            $stmt->execute();
            
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            log_error("Error getting system user ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create activity notification
     * 
     * This is a helper method to create notifications from activity logs
     * 
     * @param int $user_id User ID who performed the action
     * @param string $action Action performed
     * @param string $entity_type Entity type
     * @param string $description Description
     * @param array $notify_user_ids Array of user IDs to notify (empty for all users)
     * @return int|false New notification ID or false on failure
     */
    public function createActivityNotification($user_id, $action, $entity_type, $description, $notify_user_ids = []) {
        try {
            // Get user name
            $stmt = $this->db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_name = $stmt->fetchColumn();
            
            if (!$user_name) {
                $user_name = "User #" . $user_id;
            }
            
            $title = ucfirst($action) . " " . $entity_type;
            $message = $user_name . " " . $action . " " . $entity_type . ": " . $description;
            
            return $this->createNotification(
                'activity',
                $title,
                $message,
                $entity_type,
                null,
                $notify_user_ids
            );
        } catch (Exception $e) {
            log_error("Error creating activity notification: " . $e->getMessage());
            return false;
        }
    }
}
