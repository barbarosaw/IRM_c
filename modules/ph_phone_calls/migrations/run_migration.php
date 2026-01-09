<?php
/**
 * Run Voice API Database Migration
 */

require_once '../../../../includes/init.php';

if (!isset($_SESSION['user_id']) || !has_permission('ph_communications-settings')) {
    die('Unauthorized');
}

try {
    // Read migration SQL
    $sql = file_get_contents(__DIR__ . '/002_create_voice_tables.sql');

    // Execute migration
    $db->exec($sql);

    echo "Migration completed successfully!\n";
    echo "✅ Table ph_voice_calls created\n";
    echo "✅ Settings inserted\n";
    echo "✅ Permissions added\n";
    echo "✅ Menu items added\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
