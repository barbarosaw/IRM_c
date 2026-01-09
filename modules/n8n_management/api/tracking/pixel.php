<?php
/**
 * Email Tracking Pixel
 * GET: Returns 1x1 transparent GIF and records email open
 */

require_once dirname(__DIR__, 4) . '/config/database.php';

// Get tracking ID from query string
$trackingId = $_GET['id'] ?? null;

if ($trackingId) {
    try {
        // Update email record
        $stmt = $db->prepare("
            UPDATE n8n_chat_emails
            SET status = CASE WHEN status != 'opened' THEN 'opened' ELSE status END,
                opened_at = CASE WHEN opened_at IS NULL THEN NOW() ELSE opened_at END,
                open_count = open_count + 1
            WHERE tracking_id = ?
        ");
        $stmt->execute([$trackingId]);
    } catch (Exception $e) {
        // Silently fail - don't break email display
        error_log("Tracking pixel error: " . $e->getMessage());
    }
}

// Return 1x1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 1x1 transparent GIF (43 bytes)
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
