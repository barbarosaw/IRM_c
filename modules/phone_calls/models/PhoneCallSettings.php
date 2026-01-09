<?php
/**
 * Phone Calls Module - Settings Model
 */

if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class PhoneCallSettings {
    private $db;
    private $cache = [];

    public function __construct($db = null) {
        if ($db === null) {
            global $db;
            $this->db = $db;
        } else {
            $this->db = $db;
        }
        $this->loadAll();
    }

    /**
     * Load all settings into cache
     */
    private function loadAll() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM phone_call_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        return $this->cache[$key] ?? $default;
    }

    /**
     * Get all settings
     */
    public function getAll() {
        return $this->cache;
    }

    /**
     * Set a setting value
     */
    public function set($key, $value) {
        $stmt = $this->db->prepare("
            INSERT INTO phone_call_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $result = $stmt->execute([$key, $value]);

        if ($result) {
            $this->cache[$key] = $value;
        }

        return $result;
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple($settings) {
        $this->db->beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating phone call settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Twilio Account SID
     */
    public function getAccountSid() {
        return $this->get('twilio_account_sid');
    }

    /**
     * Get Twilio Auth Token
     */
    public function getAuthToken() {
        return $this->get('twilio_auth_token');
    }

    /**
     * Get Twilio API Key SID
     */
    public function getApiKeySid() {
        return $this->get('twilio_api_key_sid');
    }

    /**
     * Get Twilio API Key Secret
     */
    public function getApiKeySecret() {
        return $this->get('twilio_api_key_secret');
    }

    /**
     * Get Twilio Phone Number
     */
    public function getPhoneNumber() {
        return $this->get('twilio_phone_number');
    }

    /**
     * Get TwiML App SID
     */
    public function getTwimlAppSid() {
        return $this->get('twilio_twiml_app_sid');
    }

    /**
     * Check if recording is enabled
     */
    public function isRecordingEnabled() {
        return $this->get('call_recording_enabled', '0') === '1';
    }

    /**
     * Get max call duration in minutes
     */
    public function getMaxCallDuration() {
        return (int) $this->get('max_call_duration_minutes', 60);
    }

    /**
     * Validate Twilio credentials are configured
     */
    public function isConfigured() {
        return !empty($this->getAccountSid())
            && !empty($this->getAuthToken())
            && !empty($this->getApiKeySid())
            && !empty($this->getApiKeySecret())
            && !empty($this->getPhoneNumber())
            && !empty($this->getTwimlAppSid());
    }

    /**
     * Mask sensitive value for display
     */
    public function maskValue($value, $showChars = 4) {
        if (strlen($value) <= $showChars * 2) {
            return str_repeat('*', strlen($value));
        }
        return substr($value, 0, $showChars) . str_repeat('*', strlen($value) - $showChars * 2) . substr($value, -$showChars);
    }
}
