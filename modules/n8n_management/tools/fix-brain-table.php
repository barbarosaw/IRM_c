<?php
/**
 * Fix conversation_brain table - expand state column
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    // Check current column definition
    $result = $db->query("SHOW COLUMNS FROM conversation_brain LIKE 'state'");
    $col = $result->fetch(PDO::FETCH_ASSOC);
    echo "Current state column: " . json_encode($col) . "\n";

    // Alter to VARCHAR(30) to accommodate all state names
    $db->exec("ALTER TABLE conversation_brain MODIFY COLUMN state VARCHAR(30) DEFAULT 'greeting'");
    echo "Updated state column to VARCHAR(30)\n";

    // Also add state_turn_count if missing
    try {
        $db->exec("ALTER TABLE conversation_brain ADD COLUMN state_turn_count INT DEFAULT 0 AFTER turn_count");
        echo "Added state_turn_count column\n";
    } catch (Exception $e) {
        echo "state_turn_count column already exists or error: " . $e->getMessage() . "\n";
    }

    // Add questions_answered if missing
    try {
        $db->exec("ALTER TABLE conversation_brain ADD COLUMN questions_answered INT DEFAULT 0");
        echo "Added questions_answered column\n";
    } catch (Exception $e) {
        echo "questions_answered column already exists or error: " . $e->getMessage() . "\n";
    }

    echo "\nDone!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
