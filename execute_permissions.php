<?php
// Include database connection
require_once 'config/database.php';

// Read SQL file
$sql = file_get_contents('add_dashboard_permissions.sql');

// Execute SQL
try {
    $db->exec($sql);
    echo "SQL executed successfully.\n";
} catch (PDOException $e) {
    echo "Error executing SQL: " . $e->getMessage() . "\n";
}
