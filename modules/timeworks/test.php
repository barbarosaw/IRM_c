<?php
echo "TEST START<br>";
flush();

require_once '../../includes/init.php';
echo "INIT LOADED<br>";
flush();

echo "Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "DB Connected: " . (isset($db) ? 'YES' : 'NO') . "<br>";

if (isset($db)) {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM twr_users");
    $result = $stmt->fetch();
    echo "TW Users Count: " . $result['cnt'] . "<br>";
}

echo "TEST END";
?>
