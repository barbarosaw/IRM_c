<?php
/**
 * AbroadWorks Management System - Database Backup & Restore Functions
 * 
 * @author ikinciadam@gmail.com
 */

/**
 * Create a database backup
 * 
 * @param array $exclude_tables Tables to exclude from backup
 * @return string|false Backup file path or false on failure
 */
function create_database_backup($exclude_tables = []) {
    global $db;
    
    // Get absolute path to backup directory
    $script_dir = dirname(__FILE__); // This is includes/ directory
    $root_dir = dirname($script_dir); // This is the application root
    $backup_dir = $root_dir . '/backups/';
    
    // Create backups directory if it doesn't exist
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Get excluded tables from settings if none provided
    if (empty($exclude_tables) && function_exists('get_setting')) {
        $backup_exclude_tables = get_setting('backup_exclude_tables', '');
        if (!empty($backup_exclude_tables)) {
            $exclude_tables = explode(',', $backup_exclude_tables);
        }
    }
    
    // Generate backup filename
    $backup_filename = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Open backup file for writing
    $file = fopen($backup_filename, 'w');
    
    if ($file) {
        try {
            // Write backup header
            fwrite($file, "-- AbroadWorks Management System Backup\n");
            fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($file, "-- ------------------------------------------------------\n\n");
            
            // Get all tables
            $all_tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            // Process each table
            foreach ($all_tables as $table) {
                // Skip excluded tables
                if (in_array($table, $exclude_tables)) {
                    fwrite($file, "-- Skipping table `$table` (excluded)\n\n");
                    continue;
                }
                
                fwrite($file, "-- Table structure for `$table`\n");
                fwrite($file, "DROP TABLE IF EXISTS `$table`;\n");
                
                // Get table creation SQL
                $create_table = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                fwrite($file, $create_table['Create Table'] . ";\n\n");
                
                // Get table data
                $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                
                if ($rows) {
                    fwrite($file, "-- Dumping data for table `$table`\n");
                    
                    // Get column count
                    $stmt = $db->query("SELECT * FROM `$table` LIMIT 0");
                    $column_count = $stmt->columnCount();
                    
                    // Start INSERT statement
                    fwrite($file, "INSERT INTO `$table` VALUES\n");
                    
                    $values = [];
                    foreach ($rows as $row) {
                        $rowValues = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $rowValues[] = 'NULL';
                            } else {
                                $rowValues[] = $db->quote($value);
                            }
                        }
                        $values[] = '(' . implode(', ', $rowValues) . ')';
                    }
                    
                    fwrite($file, implode(",\n", $values) . ";\n\n");
                } else {
                    fwrite($file, "-- Table `$table` is empty\n\n");
                }
            }
            
            fclose($file);
            return $backup_filename;
            
        } catch (Exception $e) {
            fclose($file);
            @unlink($backup_filename); // Delete the incomplete file
            error_log("Backup error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Restore a database backup
 * 
 * @param string $backup_file Backup file name
 * @return bool Success or failure
 */
function restore_database_backup($backup_file) {
    global $db;
    
    // Get absolute path to backup directory
    $script_dir = dirname(__FILE__); // This is includes/ directory
    $root_dir = dirname($script_dir); // This is the application root
    $backup_dir = $root_dir . '/backups/';
    $full_path = $backup_dir . $backup_file;
    
    // Validate that the file exists and is a SQL file
    if (file_exists($full_path) && pathinfo($full_path, PATHINFO_EXTENSION) === 'sql') {
        try {
            // Read the SQL file
            $sql = file_get_contents($full_path);
            
            // Disable foreign key checks temporarily
            $db->exec('SET foreign_key_checks = 0');
            
            // Begin transaction
            $db->beginTransaction();
            
            // Better SQL statement splitting - handle multiline statements
            $sql = str_replace(["\r\n", "\r"], "\n", $sql); // Normalize line endings
            $statements = [];
            $current_statement = '';
            $lines = explode("\n", $sql);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip empty lines and comments
                if (empty($line) || substr($line, 0, 2) == '--') {
                    continue;
                }
                
                $current_statement .= $line . ' ';
                
                // If line ends with semicolon, it's end of statement
                if (substr($line, -1) == ';') {
                    $statements[] = trim($current_statement);
                    $current_statement = '';
                }
            }
            
            // Execute each statement
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $db->exec($statement);
                    } catch (Exception $stmt_error) {
                        // Log individual statement errors but continue
                        error_log("Statement error: " . $stmt_error->getMessage() . " - SQL: " . substr($statement, 0, 100) . "...");
                        // Don't fail entire restore for minor issues
                    }
                }
            }
            
            // Re-enable foreign key checks
            $db->exec('SET foreign_key_checks = 1');
            
            // Commit the transaction
            $db->commit();
            
            return true;
        } catch (Exception $e) {
            // Roll back the transaction if something failed
            $db->rollBack();
            $db->exec('SET foreign_key_checks = 1'); // Make sure to re-enable this
            error_log("Restore error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Get available backup files
 * 
 * @return array List of backup files
 */
function get_backup_files() {
    $backups = [];
    
    // Get the root directory of the application
    $script_dir = dirname(__FILE__); // This is includes/ directory
    $root_dir = dirname($script_dir); // This is the application root
    $backup_dir = $root_dir . '/backups/';
    
    if (file_exists($backup_dir) && is_dir($backup_dir)) {
        $files = glob($backup_dir . '*.sql');
        if ($files) {
            $backups = array_map('basename', $files);
            rsort($backups); // Sort by newest first
        }
    }
    
    return $backups;
}

/**
 * Get the absolute path to the backup directory
 * 
 * @return string Absolute path to backup directory
 */
function get_backup_directory_path() {
    $script_dir = dirname(__FILE__); // This is includes/ directory
    $root_dir = dirname($script_dir); // This is the application root
    return $root_dir . '/backups/';
}
