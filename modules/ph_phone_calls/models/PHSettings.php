<?php
/**
 * PH Communications Module - Settings Model
 */

if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class PHSettings {
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
     * Get setting value by key
     */
    public function get($key) {
        $stmt = $this->db->prepare("SELECT value FROM ph_communications_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Set setting value
     */
    public function set($key, $value) {
        $stmt = $this->db->prepare("
            INSERT INTO ph_communications_settings (setting_key, value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }

    /**
     * Get all settings
     */
    public function getAll() {
        $stmt = $this->db->query("SELECT setting_key, value FROM ph_communications_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['value'];
        }
        return $settings;
    }

    /**
     * Get m360 credentials
     */
    public function getM360Credentials() {
        return [
            'app_key' => $this->get('m360_app_key'),
            'app_secret' => $this->get('m360_app_secret'),
            'shortcode' => $this->get('m360_shortcode'),
            'api_url' => $this->get('m360_api_url') ?: 'https://api.m360.com.ph/v3/api/broadcast'
        ];
    }

    /**
     * Update m360 credentials
     */
    public function updateM360Credentials($appKey, $appSecret, $shortcode) {
        $this->set('m360_app_key', $appKey);
        $this->set('m360_app_secret', $appSecret);
        $this->set('m360_shortcode', $shortcode);
        return true;
    }
}
