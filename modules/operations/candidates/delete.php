<?php
/**
 * Operations Module - Delete Candidate
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-candidates-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $db->prepare('DELETE FROM candidates WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: index.php');
exit;
