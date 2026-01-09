<?php
/**
 * AbroadWorks Management System - Setting Model
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Setting {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Get all settings
     * 
     * @param string $group Optional group filter
     * @return array
     */
    public function getAllSettings($group = null) {
        $settings = [];
        
        try {
            if ($group) {
                $stmt = $this->db->prepare("SELECT * FROM settings WHERE `group` = ? ORDER BY `key`");
                $stmt->execute([$group]);
            } else {
                $stmt = $this->db->query("SELECT * FROM settings ORDER BY `group`, `key`");
            }
            
            while ($row = $stmt->fetch()) {
                $settings[$row['key']] = $row['value'];
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Error getting settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all settings grouped by their group
     * 
     * @return array
     */
    public function getSettingsByGroup() {
        $settings = [];
        
        try {
            $stmt = $this->db->query("SELECT * FROM settings ORDER BY `group`, `key`");
            
            while ($row = $stmt->fetch()) {
                if (!isset($settings[$row['group']])) {
                    $settings[$row['group']] = [];
                }
                
                $settings[$row['group']][$row['key']] = [
                    'id' => $row['id'],
                    'key' => $row['key'],
                    'value' => $row['value'],
                    'description' => $row['description'],
                    'type' => $row['type'],
                    'options' => $row['options'],
                    'is_public' => $row['is_public'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Error getting settings by group: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a single setting by key
     * 
     * @param string $key
     * @param mixed $default Default value if setting not found
     * @return mixed
     */
    public function getSetting($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            
            $value = $stmt->fetchColumn();
            
            return $value !== false ? $value : $default;
        } catch (PDOException $e) {
            error_log("Error getting setting $key: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Update a setting
     * 
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function updateSetting($key, $value) {
        try {
            $stmt = $this->db->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
            $stmt->execute([$value, $key]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating setting $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update multiple settings at once
     * 
     * @param array $settings Key-value pairs of settings
     * @return int Number of settings updated
     */
    public function updateSettings($settings) {
        $updated = 0;
        
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $this->db->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
                $result = $stmt->execute([$value, $key]);

                if ($result && $stmt->rowCount() > 0) {
                    $updated++;
                } else {
                    // Eğer update etmedi, insert et
                    // Key'e göre group belirle
                    $group = 'general';
                    if (strpos($key, 'smtp_') === 0 || strpos($key, 'email_') === 0) {
                        $group = 'email';
                    } elseif (strpos($key, 'company_') === 0) {
                        $group = 'company';
                    } elseif (strpos($key, 'two_factor_') === 0 || strpos($key, 'session_') === 0) {
                        $group = 'security';
                    }

                    $stmt_insert = $this->db->prepare("INSERT INTO settings (`key`, `value`, `group`, `created_at`, `updated_at`) VALUES (?, ?, ?, NOW(), NOW())");
                    $stmt_insert->execute([$key, $value, $group]);
                    if ($stmt_insert->rowCount() > 0) {
                        $updated++;
                    }
                }
            }
            
            $this->db->commit();
            
            return $updated;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating settings: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Add a new setting
     * 
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @param string $description
     * @param string $type
     * @param string $options
     * @param bool $is_public
     * @return bool
     */
    public function addSetting($key, $value, $group = 'general', $description = '', $type = 'text', $options = '', $is_public = false) {
        try {
            // Check if setting already exists
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetchColumn() > 0) {
                // Setting already exists, update it
                return $this->updateSetting($key, $value);
            }
            
            // Insert new setting
            $stmt = $this->db->prepare("
                INSERT INTO settings (`key`, `value`, `group`, `description`, `type`, `options`, `is_public`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([$key, $value, $group, $description, $type, $options, $is_public ? 1 : 0]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error adding setting $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a setting
     * 
     * @param string $key
     * @return bool
     */
    public function deleteSetting($key) {
        try {
            $stmt = $this->db->prepare("DELETE FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error deleting setting $key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all available setting groups
     * 
     * @return array
     */
    public function getSettingGroups() {
        try {
            $stmt = $this->db->query("SELECT DISTINCT `group` FROM settings ORDER BY `group`");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting setting groups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all roles for dropdown
     * 
     * @return array
     */
    public function getAllRoles() {
        try {
            $stmt = $this->db->query("SELECT * FROM roles ORDER BY name");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting roles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all database tables
     * 
     * @return array
     */
    public function getAllTables() {
        try {
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            return $tables;
        } catch (PDOException $e) {
            error_log("Error getting tables: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Toggle maintenance mode
     * 
     * @param bool $enable
     * @return bool
     */
    public function toggleMaintenanceMode($enable = true) {
        $value = $enable ? '1' : '0';
        return $this->updateSetting('maintenance_mode', $value);
    }
}
