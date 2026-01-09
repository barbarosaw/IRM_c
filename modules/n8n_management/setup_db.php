<?php
/**
 * Database Setup Script for n8n Management Module
 * Run once to setup required tables and settings
 */

require_once dirname(__DIR__, 2) . '/config/database.php';

$results = [];

// 1. Create n8n_chat_emails table (if not exists)
try {
    $sql = "CREATE TABLE IF NOT EXISTS n8n_chat_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(36),
        email_type ENUM('session_summary', 'lead_capture', 'daily_report') DEFAULT 'session_summary',
        recipient_email VARCHAR(255),
        cc_emails TEXT NULL,
        bcc_emails TEXT NULL,
        subject VARCHAR(255),
        body TEXT,
        tracking_id VARCHAR(36) UNIQUE,
        status ENUM('pending', 'sent', 'failed', 'opened') DEFAULT 'pending',
        sent_at DATETIME NULL,
        opened_at DATETIME NULL,
        open_count INT DEFAULT 0,
        error_message TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_id),
        INDEX idx_status (status),
        INDEX idx_tracking (tracking_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sql);
    $results[] = "✓ n8n_chat_emails table ready";
} catch (Exception $e) {
    $results[] = "✗ n8n_chat_emails: " . $e->getMessage();
}

// 2. Add n8n_chat_api_key to settings
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
    $stmt->execute(['n8n_chat_api_key']);
    $exists = $stmt->fetchColumn() > 0;

    if (!$exists) {
        $apiKey = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `group`, is_public, created_at, updated_at) VALUES (?, ?, 'n8n', 0, NOW(), NOW())");
        $stmt->execute(['n8n_chat_api_key', $apiKey]);
        $results[] = "✓ n8n_chat_api_key created: " . $apiKey;
    } else {
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute(['n8n_chat_api_key']);
        $results[] = "✓ n8n_chat_api_key exists: " . $stmt->fetchColumn();
    }
} catch (Exception $e) {
    $results[] = "✗ n8n_chat_api_key: " . $e->getMessage();
}

// 3. Add email TO/CC/BCC settings to n8n_chatbot_settings
$emailSettings = [
    ['email_to', '', 'string', 'Primary email recipient for chat notifications'],
    ['email_cc', '', 'string', 'CC email addresses (comma separated)'],
    ['email_bcc', '', 'string', 'BCC email addresses (comma separated)'],
    ['widget_title', 'Hi there! 👋', 'string', 'Chat widget title text'],
    ['widget_subtitle', 'We typically reply instantly', 'string', 'Chat widget subtitle text'],
    ['widget_primary_color', '#e74266', 'string', 'Chat widget primary color'],
    ['widget_enabled', 'true', 'boolean', 'Enable/disable chat widget'],
];

foreach ($emailSettings as $setting) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM n8n_chatbot_settings WHERE setting_key = ?");
        $stmt->execute([$setting[0]]);
        $exists = $stmt->fetchColumn() > 0;

        if (!$exists) {
            $stmt = $db->prepare("INSERT INTO n8n_chatbot_settings (setting_key, setting_value, setting_type, description, updated_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$setting[0], $setting[1], $setting[2], $setting[3]]);
            $results[] = "✓ Added setting: " . $setting[0];
        } else {
            $results[] = "○ Setting exists: " . $setting[0];
        }
    } catch (Exception $e) {
        $results[] = "✗ " . $setting[0] . ": " . $e->getMessage();
    }
}

// Output results
foreach ($results as $result) {
    echo $result . "\n";
}

echo "\nDatabase setup completed.\n";
