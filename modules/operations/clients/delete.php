<?php
/**
 * Operations Module - Delete Client
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-clients-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $db->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: index.php');
exit;
