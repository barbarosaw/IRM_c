<?php
/**
 * Email Tracking Helper
 *
 * Provides functionality for email tracking:
 * - Generate unique tracking tokens
 * - Inject tracking pixel into email body
 * - Wrap links for click tracking
 * - Get campaign statistics
 *
 * @author ikinciadam@gmail.com
 */

class EmailTrackingHelper
{
    private PDO $db;
    private string $baseUrl;

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     * @param string|null $baseUrl Base URL for tracking (defaults to current host)
     */
    public function __construct(PDO $db, ?string $baseUrl = null)
    {
        $this->db = $db;

        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'irm.abroadworks.com';
            $this->baseUrl = $protocol . '://' . $host;
        }
    }

    /**
     * Generate a unique tracking token
     *
     * @return string 64-character hex token
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Prepare email HTML for tracking
     *
     * - Injects tracking pixel before </body> or at end
     * - Wraps all links with click tracking URL
     *
     * @param string $html Email HTML content
     * @param string $token Unique tracking token
     * @return string Modified HTML with tracking
     */
    public function prepareEmailForTracking(string $html, string $token): string
    {
        // Inject tracking pixel
        $html = $this->injectTrackingPixel($html, $token);

        // Wrap links for click tracking
        $html = $this->wrapLinks($html, $token);

        return $html;
    }

    /**
     * Inject tracking pixel into email HTML
     *
     * @param string $html Email HTML content
     * @param string $token Tracking token
     * @return string HTML with tracking pixel
     */
    public function injectTrackingPixel(string $html, string $token): string
    {
        $trackingUrl = $this->baseUrl . '/api/email-track.php?t=' . $token . '&a=open';
        $trackingPixel = '<img src="' . htmlspecialchars($trackingUrl) . '" width="1" height="1" alt="" style="display:none;width:1px;height:1px;border:0;" />';

        // Try to insert before </body>
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $trackingPixel . '</body>', $html, 1);
        } else {
            // No body tag, append to end
            $html .= $trackingPixel;
        }

        return $html;
    }

    /**
     * Wrap all links in email HTML for click tracking
     *
     * @param string $html Email HTML content
     * @param string $token Tracking token
     * @return string HTML with wrapped links
     */
    public function wrapLinks(string $html, string $token): string
    {
        $baseUrl = $this->baseUrl;

        // Match href attributes in anchor tags
        $html = preg_replace_callback(
            '/<a\s+([^>]*href\s*=\s*["\'])([^"\']+)(["\'][^>]*)>/i',
            function ($matches) use ($token, $baseUrl) {
                $beforeHref = $matches[1];
                $originalUrl = $matches[2];
                $afterHref = $matches[3];

                // Skip certain URLs
                if ($this->shouldSkipUrl($originalUrl)) {
                    return $matches[0];
                }

                // Create tracked URL
                $trackedUrl = $baseUrl . '/api/email-track.php?t=' . $token . '&a=click&url=' . urlencode($originalUrl);

                return '<a ' . $beforeHref . htmlspecialchars($trackedUrl) . $afterHref . '>';
            },
            $html
        );

        return $html;
    }

    /**
     * Check if URL should be skipped from tracking
     *
     * @param string $url URL to check
     * @return bool True if URL should not be tracked
     */
    private function shouldSkipUrl(string $url): bool
    {
        // Skip mailto: links
        if (stripos($url, 'mailto:') === 0) {
            return true;
        }

        // Skip tel: links
        if (stripos($url, 'tel:') === 0) {
            return true;
        }

        // Skip anchor links
        if (strpos($url, '#') === 0) {
            return true;
        }

        // Skip javascript: links
        if (stripos($url, 'javascript:') === 0) {
            return true;
        }

        // Skip data: URLs
        if (stripos($url, 'data:') === 0) {
            return true;
        }

        // Skip unsubscribe tracking URLs (already tracked)
        if (strpos($url, '/api/email-track.php') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get comprehensive campaign statistics
     *
     * @param int $campaignId Campaign ID
     * @return array Statistics array
     */
    public function getCampaignStats(int $campaignId): array
    {
        // Total recipients
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM email_sends WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $totalRecipients = (int) $stmt->fetchColumn();

        // Sent count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM email_sends WHERE campaign_id = ? AND status = 'sent'");
        $stmt->execute([$campaignId]);
        $sent = (int) $stmt->fetchColumn();

        // Failed count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM email_sends WHERE campaign_id = ? AND status = 'failed'");
        $stmt->execute([$campaignId]);
        $failed = (int) $stmt->fetchColumn();

        // Pending count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM email_sends WHERE campaign_id = ? AND status = 'pending'");
        $stmt->execute([$campaignId]);
        $pending = (int) $stmt->fetchColumn();

        // Unique opens (count sends with at least one open)
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT es.id)
            FROM email_sends es
            INNER JOIN email_opens eo ON es.id = eo.send_id
            WHERE es.campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $uniqueOpens = (int) $stmt->fetchColumn();

        // Total opens
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM email_opens eo
            INNER JOIN email_sends es ON eo.send_id = es.id
            WHERE es.campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $totalOpens = (int) $stmt->fetchColumn();

        // Unique clicks (count sends with at least one click)
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT es.id)
            FROM email_sends es
            INNER JOIN email_clicks ec ON es.id = ec.send_id
            WHERE es.campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $uniqueClicks = (int) $stmt->fetchColumn();

        // Total clicks
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM email_clicks ec
            INNER JOIN email_sends es ON ec.send_id = es.id
            WHERE es.campaign_id = ?
        ");
        $stmt->execute([$campaignId]);
        $totalClicks = (int) $stmt->fetchColumn();

        // Calculate rates
        $openRate = $sent > 0 ? round(($uniqueOpens / $sent) * 100, 2) : 0;
        $clickRate = $sent > 0 ? round(($uniqueClicks / $sent) * 100, 2) : 0;
        $clickToOpenRate = $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 2) : 0;

        return [
            'total_recipients' => $totalRecipients,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'unique_opens' => $uniqueOpens,
            'total_opens' => $totalOpens,
            'unique_clicks' => $uniqueClicks,
            'total_clicks' => $totalClicks,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'click_to_open_rate' => $clickToOpenRate
        ];
    }

    /**
     * Get individual send statistics
     *
     * @param int $sendId Send ID
     * @return array Send statistics
     */
    public function getSendStats(int $sendId): array
    {
        // Get send record
        $stmt = $this->db->prepare("SELECT * FROM email_sends WHERE id = ?");
        $stmt->execute([$sendId]);
        $send = $stmt->fetch();

        if (!$send) {
            return [];
        }

        // Get opens
        $stmt = $this->db->prepare("
            SELECT opened_at, ip_address, is_first_open
            FROM email_opens
            WHERE send_id = ?
            ORDER BY opened_at ASC
        ");
        $stmt->execute([$sendId]);
        $opens = $stmt->fetchAll();

        // Get clicks
        $stmt = $this->db->prepare("
            SELECT url, clicked_at, ip_address
            FROM email_clicks
            WHERE send_id = ?
            ORDER BY clicked_at ASC
        ");
        $stmt->execute([$sendId]);
        $clicks = $stmt->fetchAll();

        return [
            'send' => $send,
            'opens' => $opens,
            'clicks' => $clicks,
            'open_count' => count($opens),
            'click_count' => count($clicks),
            'first_open' => !empty($opens) ? $opens[0]['opened_at'] : null,
            'last_open' => !empty($opens) ? end($opens)['opened_at'] : null,
            'first_click' => !empty($clicks) ? $clicks[0]['clicked_at'] : null
        ];
    }

    /**
     * Get recipient list with tracking status for a campaign
     *
     * @param int $campaignId Campaign ID
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Recipients with tracking data
     */
    public function getCampaignRecipients(int $campaignId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                es.id,
                es.email,
                es.recipient_name,
                es.status,
                es.sent_at,
                es.error_message,
                (SELECT COUNT(*) FROM email_opens WHERE send_id = es.id) as open_count,
                (SELECT MIN(opened_at) FROM email_opens WHERE send_id = es.id) as first_opened_at,
                (SELECT COUNT(*) FROM email_clicks WHERE send_id = es.id) as click_count,
                (SELECT MIN(clicked_at) FROM email_clicks WHERE send_id = es.id) as first_clicked_at
            FROM email_sends es
            WHERE es.campaign_id = ?
            ORDER BY es.id ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$campaignId, $limit, $offset]);

        return $stmt->fetchAll();
    }

    /**
     * Get click statistics by URL for a campaign
     *
     * @param int $campaignId Campaign ID
     * @return array Click statistics grouped by URL
     */
    public function getCampaignLinkStats(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ec.url,
                COUNT(*) as click_count,
                COUNT(DISTINCT ec.send_id) as unique_clicks,
                MIN(ec.clicked_at) as first_click,
                MAX(ec.clicked_at) as last_click
            FROM email_clicks ec
            INNER JOIN email_sends es ON ec.send_id = es.id
            WHERE es.campaign_id = ?
            GROUP BY ec.url
            ORDER BY click_count DESC
        ");
        $stmt->execute([$campaignId]);

        return $stmt->fetchAll();
    }
}
