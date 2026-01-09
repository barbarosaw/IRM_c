<?php
/**
 * Download All Exports as ZIP - November
 */

require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$exportsDir = __DIR__ . '/exports';
$files = glob($exportsDir . '/*_november_2025.csv');

if (empty($files)) {
    header('Location: november.php');
    exit;
}

// Create ZIP file
$zipFile = tempnam(sys_get_temp_dir(), 'hubstaff_export_') . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
    die('Could not create ZIP file');
}

foreach ($files as $file) {
    $zip->addFile($file, basename($file));
}

$zip->close();

// Send ZIP file
$downloadName = 'hubstaff_november_2025_' . date('Y-m-d_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($zipFile));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipFile);

// Clean up
unlink($zipFile);
exit;
