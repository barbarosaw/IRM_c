<?php
require_once '../../includes/init.php';

// Check table structure
$stmt = $db->prepare('DESCRIBE inventory_teams');
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "inventory_teams table columns:\n";
foreach($columns as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n\nSample data:\n";
// Get sample data
$stmt = $db->prepare('SELECT * FROM inventory_teams LIMIT 1');
$stmt->execute();
$sample = $stmt->fetch(PDO::FETCH_ASSOC);

if ($sample) {
    foreach($sample as $key => $value) {
        echo "$key: $value\n";
    }
} else {
    echo "No data found\n";
}
?>
