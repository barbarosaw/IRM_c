<?php
/**
 * AbroadWorks Management System - Execute Settings SQL
 * 
 * @author ikinciadam@gmail.com
 */

// Include database connection
require_once 'config/database.php';

// Read SQL file
$sql = file_get_contents('add_settings_module.sql');

// Split SQL file into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

// Execute each statement
$success = true;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    try {
        $result = $db->exec($statement);
        echo "Executed: " . substr($statement, 0, 50) . "...\n";
    } catch (PDOException $e) {
        $success = false;
        $errors[] = $e->getMessage();
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Output result
if ($success) {
    echo "\nSettings module SQL executed successfully!\n";
} else {
    echo "\nErrors occurred during execution:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}
