<?php
/**
 * Email Tracking Endpoint
 *
 * Handles email open tracking (via pixel) and link click tracking.
 * This is a public endpoint - no authentication required.
 *
 * Usage:
 * - Open tracking: /api/email-track.php?t={token}&a=open
 * - Click tracking: /api/email-track.php?t={token}&a=click&url={encoded_url}
 *
 * @author ikinciadam@gmail.com
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include database config
require_once dirname(__DIR__) . '/config/database.php';

// Get parameters
$token = $_GET['t'] ?? '';
$action = $_GET['a'] ?? '';
$url = $_GET['url'] ?? '';

/**
 * Serve a 1x1 transparent GIF
 */
function serveTrackingPixel() {
    header('Content-Type: image/gif');
    // 1x1 transparent GIF (43 bytes)
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

/**
 * Get client IP address
 */
function getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Proxy
        'HTTP_X_REAL_IP',            // Nginx proxy
        'REMOTE_ADDR'                // Standard
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle comma-separated IPs (from X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// Validate required parameters
if (empty($token) || empty($action)) {
    // Still serve pixel for invalid requests to avoid email client errors
    if ($action === 'open' || empty($action)) {
        serveTrackingPixel();
    }
    http_response_code(400);
    exit;
}

// Validate token format (64 hex characters)
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    if ($action === 'open') {
        serveTrackingPixel();
    } elseif ($action === 'click' && !empty($url)) {
        header('Location: ' . urldecode($url));
    }
    exit;
}

try {
    // Get send record from token
    $stmt = $db->prepare("SELECT id, campaign_id, email FROM email_sends WHERE token = ?");
    $stmt->execute([$token]);
    $send = $stmt->fetch();

    if (!$send) {
        // Token not found - still serve pixel or redirect
        if ($action === 'open') {
            serveTrackingPixel();
        } elseif ($action === 'click' && !empty($url)) {
            header('Location: ' . urldecode($url));
        }
        exit;
    }

    $sendId = $send['id'];
    $ipAddress = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if ($action === 'open') {
        // Check if this is the first open
        $stmt = $db->prepare("SELECT COUNT(*) FROM email_opens WHERE send_id = ?");
        $stmt->execute([$sendId]);
        $isFirstOpen = $stmt->fetchColumn() == 0 ? 1 : 0;

        // Record the open
        $stmt = $db->prepare("
            INSERT INTO email_opens (send_id, ip_address, user_agent, is_first_open, opened_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$sendId, $ipAddress, substr($userAgent, 0, 1000), $isFirstOpen]);

        // Serve tracking pixel
        serveTrackingPixel();

    } elseif ($action === 'click') {
        if (empty($url)) {
            http_response_code(400);
            exit;
        }

        $originalUrl = urldecode($url);

        // Validate URL to prevent open redirect vulnerability
        // Only allow http and https protocols
        $parsedUrl = parse_url($originalUrl);
        if (!isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), ['http', 'https'])) {
            http_response_code(400);
            exit;
        }

        // Record the click
        $stmt = $db->prepare("
            INSERT INTO email_clicks (send_id, url, ip_address, user_agent, clicked_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$sendId, substr($originalUrl, 0, 2000), $ipAddress, substr($userAgent, 0, 1000)]);

        // Redirect to original URL
        header('Location: ' . $originalUrl, true, 302);
        exit;

    } else {
        // Unknown action
        http_response_code(400);
        exit;
    }

} catch (PDOException $e) {
    // Log error but don't expose details
    error_log("Email tracking error: " . $e->getMessage());

    // Still serve pixel or redirect on error
    if ($action === 'open') {
        serveTrackingPixel();
    } elseif ($action === 'click' && !empty($url)) {
        header('Location: ' . urldecode($url));
    }
    exit;
}
