<?php
/**
 * Create missing tables for the Vendor Management System
 */

require_once 'config/database.php';

// Create communication_logs table if it doesn't exist
if (!table_exists($db, 'communication_logs')) {
    try {
        $query = "CREATE TABLE communication_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (entity_type, entity_id),
            INDEX (created_by)
        )";
        $db->exec($query);
        echo "Created communication_logs table.<br>";
    } catch (PDOException $e) {
        echo "Error creating communication_logs table: " . $e->getMessage() . "<br>";
    }
}

// Create documents table if it doesn't exist
if (!table_exists($db, 'documents')) {
    try {
        $query = "CREATE TABLE documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size INT,
            file_type VARCHAR(100),
            uploaded_by INT,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (entity_type, entity_id),
            INDEX (uploaded_by)
        )";
        $db->exec($query);
        echo "Created documents table.<br>";
    } catch (PDOException $e) {
        echo "Error creating documents table: " . $e->getMessage() . "<br>";
    }
}

// Function to check if a table exists
function table_exists($db, $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

echo "Database check complete.";
?>
