<?php
/**
 * Migration: Add Bulk Email System Tables
 *
 * Creates tables for:
 * - email_campaigns
 * - email_sends
 * - email_opens
 * - email_clicks
 *
 * Also inserts:
 * - 2 new email templates
 * - Menu items for TimeWorks
 * - Permissions
 *
 * @author ikinciadam@gmail.com
 */

require_once dirname(__DIR__, 2) . '/config/database.php';

echo "Starting Bulk Email System Migration...\n\n";

try {
    $db->beginTransaction();

    // =====================================================
    // 1. CREATE TABLES
    // =====================================================

    echo "Creating email_campaigns table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `email_campaigns` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `template_id` INT UNSIGNED DEFAULT NULL COMMENT 'Reference to email_templates.id',
            `name` VARCHAR(255) NOT NULL COMMENT 'Campaign name for identification',
            `subject` VARCHAR(500) NOT NULL COMMENT 'Email subject',
            `body` LONGTEXT NOT NULL COMMENT 'Email body HTML',
            `recipient_filter` VARCHAR(100) DEFAULT 'all' COMMENT 'Filter: all, active, inactive, custom',
            `delay_seconds` INT UNSIGNED DEFAULT 5 COMMENT 'Delay between emails in seconds',
            `total_recipients` INT UNSIGNED DEFAULT 0,
            `status` ENUM('draft', 'sending', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
            `started_at` DATETIME DEFAULT NULL,
            `completed_at` DATETIME DEFAULT NULL,
            `created_by` INT UNSIGNED NOT NULL COMMENT 'Reference to users.id',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_template_id` (`template_id`),
            KEY `idx_status` (`status`),
            KEY `idx_created_by` (`created_by`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  - email_campaigns table created.\n";

    echo "Creating email_sends table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `email_sends` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `campaign_id` INT UNSIGNED NOT NULL COMMENT 'Reference to email_campaigns.id',
            `user_id` VARCHAR(100) DEFAULT NULL COMMENT 'TimeWorks user_id (UUID)',
            `email` VARCHAR(255) NOT NULL,
            `recipient_name` VARCHAR(255) DEFAULT NULL,
            `token` VARCHAR(64) NOT NULL COMMENT 'Unique tracking token',
            `status` ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
            `error_message` TEXT DEFAULT NULL,
            `sent_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_token` (`token`),
            KEY `idx_campaign_id` (`campaign_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_email` (`email`),
            KEY `idx_status` (`status`),
            KEY `idx_sent_at` (`sent_at`),
            CONSTRAINT `fk_sends_campaign` FOREIGN KEY (`campaign_id`)
                REFERENCES `email_campaigns` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  - email_sends table created.\n";

    echo "Creating email_opens table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `email_opens` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `send_id` INT UNSIGNED NOT NULL COMMENT 'Reference to email_sends.id',
            `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6',
            `user_agent` TEXT DEFAULT NULL,
            `is_first_open` TINYINT(1) DEFAULT 0 COMMENT 'Flag for first open',
            PRIMARY KEY (`id`),
            KEY `idx_send_id` (`send_id`),
            KEY `idx_opened_at` (`opened_at`),
            CONSTRAINT `fk_opens_send` FOREIGN KEY (`send_id`)
                REFERENCES `email_sends` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  - email_opens table created.\n";

    echo "Creating email_clicks table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `email_clicks` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `send_id` INT UNSIGNED NOT NULL COMMENT 'Reference to email_sends.id',
            `url` TEXT NOT NULL COMMENT 'Original URL that was clicked',
            `clicked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_send_id` (`send_id`),
            KEY `idx_clicked_at` (`clicked_at`),
            CONSTRAINT `fk_clicks_send` FOREIGN KEY (`send_id`)
                REFERENCES `email_sends` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  - email_clicks table created.\n";

    // =====================================================
    // 2. INSERT EMAIL TEMPLATES
    // =====================================================

    echo "\nInserting email templates...\n";

    // Check if templates already exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM email_templates WHERE code = ?");

    // Template 1: Update Announcement & Reset Pass
    $stmt->execute(['update_announcement_reset_pass']);
    if ($stmt->fetchColumn() == 0) {
        $db->exec("
            INSERT INTO `email_templates` (`code`, `name`, `subject`, `body`, `placeholders`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
            ('update_announcement_reset_pass', 'Update Announcement & Reset Pass', 'Important Update: Your TimeWorks Password Has Been Reset',
            '<div style=\"font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;\">
    <h2 style=\"color: #333;\">Hello {{name}},</h2>

    <p>We are reaching out to inform you about an important update regarding your TimeWorks account.</p>

    <div style=\"background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0;\">
        <h3 style=\"margin-top: 0; color: #007bff;\">System Updates</h3>
        <p>We have recently made improvements to the TimeWorks platform to enhance security and user experience. As part of this process, your password has been reset.</p>
    </div>

    <h3 style=\"color: #333;\">Your New Password</h3>
    <p>Please click the link below to view your new password:</p>
    <p style=\"text-align: center; margin: 25px 0;\">
        <a href=\"{{pwpush_url}}\" style=\"display: inline-block; padding: 14px 28px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;\">View Your New Password</a>
    </p>

    <div style=\"background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 20px 0;\">
        <p style=\"margin: 0;\"><strong>Important Security Notes:</strong></p>
        <ul style=\"margin: 10px 0 0 0; padding-left: 20px;\">
            <li>This password link will expire after <strong>{{expire_views}}</strong> views or <strong>{{expire_days}}</strong> days</li>
            <li>Please save your new password in a secure location</li>
            <li>We recommend changing your password after first login</li>
        </ul>
    </div>

    <h3 style=\"color: #333;\">What''s New?</h3>
    <ul>
        <li>Enhanced security features</li>
        <li>Improved performance and reliability</li>
        <li>Better user interface</li>
    </ul>

    <p>If you have any questions or need assistance, please contact our support team.</p>

    <hr style=\"border: none; border-top: 1px solid #eee; margin: 30px 0;\">

    <p style=\"color: #666;\">Best regards,<br>
    <strong>{{company_name}} IT Team</strong></p>

    <p style=\"color: #999; font-size: 12px;\">This is an automated message from {{site_name}}. Please do not reply directly to this email.</p>
</div>',
            'name, email, pwpush_url, expire_days, expire_views, company_name, site_name, site_url, date, time',
            'Template for announcing updates and providing reset password link', 1, NOW(), NOW())
        ");
        echo "  - update_announcement_reset_pass template inserted.\n";
    } else {
        echo "  - update_announcement_reset_pass template already exists, skipping.\n";
    }

    // Template 2: IT Announcement
    $stmt->execute(['it_announcement']);
    if ($stmt->fetchColumn() == 0) {
        $db->exec("
            INSERT INTO `email_templates` (`code`, `name`, `subject`, `body`, `placeholders`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
            ('it_announcement', 'IT Announcement', '{{announcement_title}}',
            '<div style=\"font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;\">
    <h2 style=\"color: #333;\">Hello {{name}},</h2>

    <p>This is an important announcement from the {{company_name}} IT Department.</p>

    <div style=\"background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0;\">
        <h3 style=\"margin-top: 0; color: #007bff;\">{{announcement_title}}</h3>
        <p style=\"margin-bottom: 0;\">{{announcement_message}}</p>
    </div>

    <h3 style=\"color: #333;\">Key Points:</h3>
    <ul>
        {{announcement_points}}
    </ul>

    <div style=\"background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 20px 0;\">
        <h4 style=\"margin-top: 0; color: #856404;\">Action Required</h4>
        <p style=\"margin-bottom: 0;\">{{action_required}}</p>
    </div>

    <h3 style=\"color: #333;\">Timeline</h3>
    <table style=\"width: 100%; border-collapse: collapse;\">
        <tr>
            <td style=\"padding: 8px; border-bottom: 1px solid #eee;\"><strong>Effective Date:</strong></td>
            <td style=\"padding: 8px; border-bottom: 1px solid #eee;\">{{effective_date}}</td>
        </tr>
        <tr>
            <td style=\"padding: 8px; border-bottom: 1px solid #eee;\"><strong>Deadline:</strong></td>
            <td style=\"padding: 8px; border-bottom: 1px solid #eee;\">{{deadline}}</td>
        </tr>
    </table>

    <div style=\"background: #f8f9fa; border-radius: 5px; padding: 15px; margin: 20px 0;\">
        <p style=\"margin: 0;\"><strong>Need Help?</strong></p>
        <ul style=\"margin: 10px 0 0 0; padding-left: 20px;\">
            <li>Email: <a href=\"mailto:{{support_email}}\">{{support_email}}</a></li>
            <li>IT Support Portal: <a href=\"{{site_url}}\">{{site_url}}</a></li>
        </ul>
    </div>

    <p>Thank you for your attention to this matter.</p>

    <hr style=\"border: none; border-top: 1px solid #eee; margin: 30px 0;\">

    <p style=\"color: #666;\">Best regards,<br>
    <strong>{{company_name}} IT Department</strong></p>

    <p style=\"color: #999; font-size: 12px;\">This is an automated message from {{site_name}}. Please do not reply directly to this email.</p>
</div>',
            'name, email, company_name, site_name, site_url, support_email, announcement_title, announcement_message, announcement_points, action_required, effective_date, deadline, date, time',
            'Template for general IT announcements and notifications', 1, NOW(), NOW())
        ");
        echo "  - it_announcement template inserted.\n";
    } else {
        echo "  - it_announcement template already exists, skipping.\n";
    }

    // =====================================================
    // 3. ADD MENU ITEMS
    // =====================================================

    echo "\nAdding menu items...\n";

    // Find TimeWorks parent menu item
    $stmt = $db->query("SELECT id FROM menu_items WHERE name = 'TimeWorks' AND parent_id IS NULL LIMIT 1");
    $timeworksParent = $stmt->fetch();

    if ($timeworksParent) {
        $parentId = $timeworksParent['id'];

        // Get max display order for TimeWorks children
        $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 FROM menu_items WHERE parent_id = ?");
        $stmt->execute([$parentId]);
        $nextOrder = $stmt->fetchColumn();

        // Check if Bulk Email menu item exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE name = 'Bulk Email' AND parent_id = ?");
        $stmt->execute([$parentId]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO menu_items (name, url, icon, permission, parent_id, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute(['Bulk Email', 'modules/timeworks/bulk-email.php', 'fas fa-mail-bulk', 'timeworks_email_manage', $parentId, $nextOrder]);
            echo "  - Bulk Email menu item added.\n";
            $nextOrder++;
        } else {
            echo "  - Bulk Email menu item already exists, skipping.\n";
        }

        // Check if Email Reports menu item exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE name = 'Email Reports' AND parent_id = ?");
        $stmt->execute([$parentId]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO menu_items (name, url, icon, permission, parent_id, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute(['Email Reports', 'modules/timeworks/email-reports.php', 'fas fa-chart-bar', 'timeworks_email_view', $parentId, $nextOrder]);
            echo "  - Email Reports menu item added.\n";
        } else {
            echo "  - Email Reports menu item already exists, skipping.\n";
        }
    } else {
        echo "  - WARNING: TimeWorks parent menu not found. Skipping menu items.\n";
    }

    // =====================================================
    // 4. ADD PERMISSIONS
    // =====================================================

    echo "\nAdding permissions...\n";

    // Check if permissions table has the required columns
    $stmt = $db->query("SHOW TABLES LIKE 'permissions'");
    if ($stmt->rowCount() > 0) {
        // Check columns
        $stmt = $db->query("DESCRIBE permissions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('code', $columns)) {
            // Check if permissions exist
            $stmt = $db->prepare("SELECT COUNT(*) FROM permissions WHERE code = ?");

            $stmt->execute(['timeworks_email_manage']);
            if ($stmt->fetchColumn() == 0) {
                $db->exec("INSERT INTO permissions (code, name, description, module) VALUES ('timeworks_email_manage', 'Manage Bulk Emails', 'Create and send bulk email campaigns', 'timeworks')");
                echo "  - timeworks_email_manage permission added.\n";
            } else {
                echo "  - timeworks_email_manage permission already exists, skipping.\n";
            }

            $stmt->execute(['timeworks_email_view']);
            if ($stmt->fetchColumn() == 0) {
                $db->exec("INSERT INTO permissions (code, name, description, module) VALUES ('timeworks_email_view', 'View Email Reports', 'View email campaign reports and statistics', 'timeworks')");
                echo "  - timeworks_email_view permission added.\n";
            } else {
                echo "  - timeworks_email_view permission already exists, skipping.\n";
            }
        } else {
            echo "  - Permissions table structure not compatible, skipping permissions.\n";
        }
    } else {
        echo "  - Permissions table not found, skipping permissions.\n";
    }

    $db->commit();
    echo "\n========================================\n";
    echo "Migration completed successfully!\n";
    echo "========================================\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Migration rolled back.\n";
    exit(1);
}
