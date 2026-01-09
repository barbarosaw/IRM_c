<?php
/**
 * TimeWorks Module - Billing Data Sync Cron Job
 *
 * Synchronizes billing data from TimeWorks API:
 * - User bill rates and pay rates
 * - Time entries with calculated amounts
 *
 * Run via cron: 0 3 * * * php /var/www/html/modules/timeworks/cron/sync-billing-data.php
 *
 * @author ikinciadam@gmail.com
 */

// Disable web output if called from web (security)
if (php_sapi_name() !== 'cli') {
    if (!isset($_SERVER['PLESK_SCHEDULED_TASK'])) {
        @header('Content-Type: text/plain');
    }
}

define('AW_SYSTEM', true);
chdir(dirname(__DIR__, 3));
require_once 'includes/init.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Load models
require_once 'modules/timeworks/models/TimeWorksAPI.php';
require_once 'modules/timeworks/models/BillingModel.php';

// Configuration
$syncDays = 60; // Sync last 60 days of time entries
$chunkSize = 50; // Process users in chunks
$startDate = date('Y-m-d', strtotime("-{$syncDays} days"));
$endDate = date('Y-m-d');

echo "=== TimeWorks Billing Data Sync ===\n";
echo "Started: " . date('Y-m-d H:i:s') . " EST\n";
echo "Date Range: {$startDate} to {$endDate} ({$syncDays} days)\n\n";

try {
    $api = new TimeWorksAPI($db);
    $billing = new BillingModel($db, $api);

    // Get total user count
    $stmt = $db->query("
        SELECT COUNT(*) as total FROM twr_users WHERE status = 'active'
    ");
    $totalUsers = (int)$stmt->fetchColumn();
    echo "Total active users: {$totalUsers}\n\n";

    // Initialize stats
    $stats = [
        'users_processed' => 0,
        'users_with_rates' => 0,
        'bill_rates_synced' => 0,
        'pay_rates_synced' => 0,
        'entries_synced' => 0,
        'errors' => 0
    ];

    $offset = 0;

    // Process users in chunks
    while ($offset < $totalUsers) {
        echo "Processing users {$offset} to " . min($offset + $chunkSize, $totalUsers) . "...\n";

        $stmt = $db->prepare("
            SELECT user_id, full_name, email
            FROM twr_users
            WHERE status = 'active'
            ORDER BY full_name ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$chunkSize, $offset]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            break;
        }

        foreach ($users as $user) {
            $userId = $user['user_id'];
            $userName = $user['full_name'];

            try {
                // Step 1: Get user details with rates from API
                $userDetails = $api->getUser($userId);

                if (!$userDetails) {
                    echo "  - {$userName}: API error (getUser failed)\n";
                    $stats['errors']++;
                    continue;
                }

                // Extract rates
                $billRates = $userDetails['billrates'] ?? [];
                $payRates = $userDetails['payrates'] ?? [];

                // Step 2: Sync rates
                $rateStats = $billing->syncUserRates($userId, $billRates, $payRates);
                $stats['bill_rates_synced'] += $rateStats['bill_added'];
                $stats['pay_rates_synced'] += $rateStats['pay_added'];

                if (!empty($billRates) || !empty($payRates)) {
                    $stats['users_with_rates']++;
                }

                // Step 3: Get time entries from API
                $timeSheet = $api->getUserTimeSheet($userId, $startDate, $endDate, 'America/New_York', 100, 0);

                if ($timeSheet && isset($timeSheet['report'])) {
                    // Step 4: Sync time entries
                    $entriesCount = $billing->syncTimeEntries(
                        $userId,
                        $timeSheet['report'],
                        $billRates,
                        $payRates
                    );
                    $stats['entries_synced'] += $entriesCount;
                }

                $stats['users_processed']++;

                // Progress indicator
                if ($stats['users_processed'] % 10 === 0) {
                    echo "  Progress: {$stats['users_processed']}/{$totalUsers} users processed\n";
                }

            } catch (Exception $e) {
                echo "  - {$userName}: Error - " . $e->getMessage() . "\n";
                $stats['errors']++;
            }

            // Small delay to avoid API rate limiting
            usleep(100000); // 100ms
        }

        $offset += $chunkSize;
    }

    // Log sync operation
    $billing->logSync(
        'billing_rates',
        $stats['bill_rates_synced'] + $stats['pay_rates_synced'],
        'success',
        "Users: {$stats['users_processed']}, Rates: " . ($stats['bill_rates_synced'] + $stats['pay_rates_synced'])
    );

    $billing->logSync(
        'billing_entries',
        $stats['entries_synced'],
        'success',
        "Users: {$stats['users_processed']}, Entries: {$stats['entries_synced']}"
    );

    // Summary
    echo "\n=== Sync Summary ===\n";
    echo "Users Processed: {$stats['users_processed']}\n";
    echo "Users with Rates: {$stats['users_with_rates']}\n";
    echo "Bill Rates Synced: {$stats['bill_rates_synced']}\n";
    echo "Pay Rates Synced: {$stats['pay_rates_synced']}\n";
    echo "Time Entries Synced: {$stats['entries_synced']}\n";
    echo "Errors: {$stats['errors']}\n";
    echo "\nCompleted: " . date('Y-m-d H:i:s') . " EST\n";

} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";

    // Log error
    try {
        $billing->logSync('billing_entries', 0, 'error', $e->getMessage());
    } catch (Exception $logError) {
        // Ignore log errors
    }

    exit(1);
}

echo "\n=== Billing Sync Complete ===\n";
