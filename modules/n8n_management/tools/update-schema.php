<?php
/**
 * Update database schema for Advanced Chatbot
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

echo "=== Updating Database Schema ===\n\n";

// Function to check if column exists
function columnExists($db, $table, $column) {
    $stmt = $db->query("SHOW COLUMNS FROM `$table` WHERE Field = '$column'");
    return $stmt->rowCount() > 0;
}

// Columns to add to chat_sessions
$columns = [
    ['is_job_seeker', 'TINYINT(1) DEFAULT 0'],
    ['hubspot_contact_id', 'VARCHAR(50) NULL'],
    ['collected_info', 'JSON NULL'],
    ['off_topic_attempts', 'INT DEFAULT 0']
];

echo "--- chat_sessions table ---\n";
foreach ($columns as $col) {
    if (!columnExists($db, 'chat_sessions', $col[0])) {
        try {
            $db->exec("ALTER TABLE chat_sessions ADD COLUMN `{$col[0]}` {$col[1]}");
            echo "✓ Added: {$col[0]}\n";
        } catch (PDOException $e) {
            echo "✗ Error adding {$col[0]}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "○ Exists: {$col[0]}\n";
    }
}

// Create n8n_booking_slots table
echo "\n--- n8n_booking_slots table ---\n";
$createTable = "
CREATE TABLE IF NOT EXISTS n8n_booking_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(36),
    person_calendar VARCHAR(100),
    person_name VARCHAR(100),
    slot_datetime DATETIME,
    slot_duration INT DEFAULT 30,
    status ENUM('offered', 'selected', 'confirmed', 'cancelled') DEFAULT 'offered',
    google_event_id VARCHAR(100) NULL,
    meeting_link VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_datetime (slot_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

try {
    $db->exec($createTable);
    echo "✓ n8n_booking_slots table ready\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Verify changes
echo "\n--- Verification ---\n";
$stmt = $db->query("SHOW COLUMNS FROM chat_sessions");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "chat_sessions columns: " . count($cols) . "\n";

$stmt = $db->query("SHOW TABLES LIKE 'n8n_booking_slots'");
echo "n8n_booking_slots exists: " . ($stmt->rowCount() > 0 ? 'Yes' : 'No') . "\n";

echo "\n=== Schema Update Complete ===\n";
