<?php
/**
 * AbroadWorks Management System - Backup File Download Handler
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Only owners and users with specific permissions can download backups
if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
    header('Location: access-denied.php');
    exit;
}

// Check if file parameter exists
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: modules/settings/');
    exit;
}

// Clean and validate the filename to prevent directory traversal
$filename = basename(clean_input($_GET['file']));
$file_path = 'backups/' . $filename;

// Check if file exists and is within the backups directory
if (!file_exists($file_path) || !is_file($file_path) || dirname(realpath($file_path)) !== realpath('backups')) {
    header('Location: modules/settings/');
    exit;
}

// Log the download activity
log_activity($_SESSION['user_id'], 'download', 'backup', "Downloaded backup file: $filename");

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Output file and exit
readfile($file_path);
exit;
