<?php
/**
 * TimeWorks Billing Model
 *
 * Handles all database operations for the billing module including:
 * - Time entries management
 * - Rate calculations
 * - Client/Project matching
 * - Filtering and aggregation
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class BillingModel {
    private $db;
    private $api;

    /**
     * Constructor
     *
     * @param PDO $connection Database connection
     * @param TimeWorksAPI|null $api TimeWorks API instance
     */
    public function __construct($connection = null, $api = null) {
        if ($connection) {
            $this->db = $connection;
        } else {
            global $db;
            $this->db = $db;
        }

        $this->api = $api;
    }

    /**
     * Set API instance
     *
     * @param TimeWorksAPI $api
     */
    public function setApi($api) {
        $this->api = $api;
    }

    // =========================================================================
    // USER METHODS
    // =========================================================================

    /**
     * Get all users (regardless of bill rate status)
     *
     * @return array List of users
     */
    public function getAllUsers(): array {
        $stmt = $this->db->query("
            SELECT
                u.id,
                u.user_id,
                u.full_name,
                u.email,
                u.status,
                u.current_bill_rate,
                u.current_pay_rate,
                u.rates_synced_at
            FROM twr_users u
            WHERE u.status = 'active'
            ORDER BY u.full_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get users by client (for client-based aggregation)
     *
     * @param int $clientId Client ID
     * @return array List of user_ids
     */
    public function getUsersByClient(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT uc.user_id
            FROM twr_user_clients uc
            WHERE uc.client_id = ?
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get employees for filter dropdown
     *
     * @return array List of employees
     */
    public function getEmployees(): array {
        $stmt = $this->db->query("
            SELECT
                u.user_id,
                u.full_name,
                u.email,
                u.current_bill_rate,
                u.current_pay_rate
            FROM twr_users u
            WHERE u.status = 'active'
            ORDER BY u.full_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // CLIENT/PROJECT METHODS
    // =========================================================================

    /**
     * Get distinct clients for filter dropdown
     *
     * @return array List of clients
     */
    public function getClients(): array {
        $stmt = $this->db->query("
            SELECT
                c.id,
                c.name,
                c.status
            FROM twr_clients c
            WHERE c.status = 'active'
            ORDER BY c.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Match API client name with system client
     *
     * @param string $apiClientName Client name from API
     * @return int|null Client ID or null if not found
     */
    public function matchClient(string $apiClientName): ?int {
        $stmt = $this->db->prepare("
            SELECT id FROM twr_clients
            WHERE name = ? OR name LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$apiClientName, $apiClientName . '%']);
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : null;
    }

    /**
     * Match API project ID with system project
     *
     * @param string $apiProjectId Project UUID from API
     * @return bool True if project exists
     */
    public function matchProject(string $apiProjectId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM twr_projects
            WHERE project_id = ?
            LIMIT 1
        ");
        $stmt->execute([$apiProjectId]);
        return (bool)$stmt->fetchColumn();
    }

    // =========================================================================
    // RATE METHODS
    // =========================================================================

    /**
     * Get rate for specific date and type
     *
     * @param string $userId User UUID
     * @param string $date Date (Y-m-d format)
     * @param string $type Rate type ('bill' or 'pay')
     * @return float Rate value
     */
    public function getRateForDate(string $userId, string $date, string $type = 'bill'): float {
        $stmt = $this->db->prepare("
            SELECT rate FROM twr_billing_rates
            WHERE user_id = ?
              AND rate_type = ?
              AND rate_from <= ?
              AND (rate_to IS NULL OR rate_to >= ?)
            ORDER BY rate_from DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $type, $date, $date]);
        $result = $stmt->fetchColumn();
        return $result ? (float)$result : 0.0;
    }

    /**
     * Get rate from API response array for a specific date
     *
     * @param array $rates Array of rates from API
     * @param string $date Date to find rate for
     * @param string $rateField Field name for rate value
     * @param string $fromField Field name for from date
     * @param string $toField Field name for to date
     * @return float Rate value
     */
    public function getRateFromApiArray(array $rates, string $date, string $rateField = 'bill_rate', string $fromField = 'bill_rate_from', string $toField = 'bill_rate_to'): float {
        // Sort by from date desc to get most recent first
        usort($rates, function($a, $b) use ($fromField) {
            return strcmp($b[$fromField] ?? '', $a[$fromField] ?? '');
        });

        foreach ($rates as $rate) {
            $from = $rate[$fromField] ?? null;
            $to = $rate[$toField] ?? null;

            if ($from && $date >= $from && ($to === null || $date <= $to)) {
                return (float)($rate[$rateField] ?? 0);
            }
        }
        return 0.0;
    }

    /**
     * Sync user rates from API response
     *
     * @param string $userId User UUID
     * @param array $billRates Bill rates array from API
     * @param array $payRates Pay rates array from API
     * @return array Statistics [bill_added, pay_added]
     */
    public function syncUserRates(string $userId, array $billRates, array $payRates): array {
        $stats = ['bill_added' => 0, 'pay_added' => 0];
        $now = date('Y-m-d H:i:s');

        // Sync bill rates
        foreach ($billRates as $rate) {
            $apiRateId = $rate['id'] ?? null;
            if (!$apiRateId) continue;

            $stmt = $this->db->prepare("
                INSERT INTO twr_billing_rates
                    (user_id, rate_type, rate, rate_from, rate_to, api_rate_id, notes, synced_at, created_at)
                VALUES
                    (?, 'bill', ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    rate = VALUES(rate),
                    rate_from = VALUES(rate_from),
                    rate_to = VALUES(rate_to),
                    notes = VALUES(notes),
                    synced_at = VALUES(synced_at)
            ");
            $stmt->execute([
                $userId,
                $rate['bill_rate'] ?? 0,
                $rate['bill_rate_from'] ?? null,
                $rate['bill_rate_to'] ?? null,
                $apiRateId,
                $rate['notes'] ?? null,
                $now
            ]);

            if ($stmt->rowCount() > 0) {
                $stats['bill_added']++;
            }
        }

        // Sync pay rates
        foreach ($payRates as $rate) {
            $apiRateId = $rate['id'] ?? null;
            if (!$apiRateId) continue;

            $stmt = $this->db->prepare("
                INSERT INTO twr_billing_rates
                    (user_id, rate_type, rate, rate_from, rate_to, api_rate_id, notes, synced_at, created_at)
                VALUES
                    (?, 'pay', ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    rate = VALUES(rate),
                    rate_from = VALUES(rate_from),
                    rate_to = VALUES(rate_to),
                    notes = VALUES(notes),
                    synced_at = VALUES(synced_at)
            ");
            $stmt->execute([
                $userId,
                $rate['pay_rate'] ?? 0,
                $rate['pay_rate_from'] ?? null,
                $rate['pay_rate_to'] ?? null,
                $apiRateId,
                $rate['notes'] ?? null,
                $now
            ]);

            if ($stmt->rowCount() > 0) {
                $stats['pay_added']++;
            }
        }

        // Update current rates in twr_users
        $currentBillRate = null;
        $currentPayRate = null;

        if (!empty($billRates)) {
            $currentBillRate = $this->getRateFromApiArray($billRates, date('Y-m-d'), 'bill_rate', 'bill_rate_from', 'bill_rate_to');
        }
        if (!empty($payRates)) {
            $currentPayRate = $this->getRateFromApiArray($payRates, date('Y-m-d'), 'pay_rate', 'pay_rate_from', 'pay_rate_to');
        }

        $stmt = $this->db->prepare("
            UPDATE twr_users SET
                current_bill_rate = ?,
                current_pay_rate = ?,
                rates_synced_at = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$currentBillRate, $currentPayRate, $now, $userId]);

        return $stats;
    }

    // =========================================================================
    // TIME ENTRY METHODS
    // =========================================================================

    /**
     * Sync time entries from API response
     *
     * @param string $userId User UUID
     * @param array $report Report data from getUserTimeSheet API
     * @param array $billRates User's bill rates for date lookup
     * @param array $payRates User's pay rates for date lookup
     * @return int Number of entries synced
     */
    public function syncTimeEntries(string $userId, array $report, array $billRates = [], array $payRates = []): int {
        $count = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($report as $date => $dayData) {
            // Skip if no entries
            if (empty($dayData['entries'])) {
                continue;
            }

            // Get rates for this date
            $billRate = $this->getRateFromApiArray($billRates, $date, 'bill_rate', 'bill_rate_from', 'bill_rate_to');
            $payRate = $this->getRateFromApiArray($payRates, $date, 'pay_rate', 'pay_rate_from', 'pay_rate_to');

            // If no rates from API array, try from database
            if ($billRate == 0) {
                $billRate = $this->getRateForDate($userId, $date, 'bill');
            }
            if ($payRate == 0) {
                $payRate = $this->getRateForDate($userId, $date, 'pay');
            }

            foreach ($dayData['entries'] as $entry) {
                $projectId = $entry['project_id'] ?? null;
                $apiClientName = $entry['client_name'] ?? null;
                $apiProjectName = $entry['project_name'] ?? null;
                $taskName = $entry['task_name'] ?? null;
                $durationSeconds = $entry['duration_seconds'] ?? 0;

                // Calculate hours (4 decimal places)
                $hours = round($durationSeconds / 3600, 4);

                // Calculate amounts
                $billAmount = round($hours * $billRate, 2);
                $payAmount = round($hours * $payRate, 2);
                $profitAmount = round($billAmount - $payAmount, 2);

                // Match client
                $clientId = $apiClientName ? $this->matchClient($apiClientName) : null;

                // Description: task_name if available, otherwise project_name
                $description = $taskName ?: $apiProjectName;

                // Insert or update entry
                $stmt = $this->db->prepare("
                    INSERT INTO twr_billing_entries
                        (user_id, entry_date, client_id, project_id, api_client_name, api_project_name,
                         task_name, description, duration_seconds, hours, bill_rate, pay_rate,
                         bill_amount, pay_amount, profit_amount, synced_at, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        client_id = VALUES(client_id),
                        api_client_name = VALUES(api_client_name),
                        api_project_name = VALUES(api_project_name),
                        task_name = VALUES(task_name),
                        description = VALUES(description),
                        duration_seconds = VALUES(duration_seconds),
                        hours = VALUES(hours),
                        bill_rate = VALUES(bill_rate),
                        pay_rate = VALUES(pay_rate),
                        bill_amount = VALUES(bill_amount),
                        pay_amount = VALUES(pay_amount),
                        profit_amount = VALUES(profit_amount),
                        synced_at = VALUES(synced_at),
                        updated_at = NOW()
                ");
                $stmt->execute([
                    $userId, $date, $clientId, $projectId, $apiClientName, $apiProjectName,
                    $taskName, $description, $durationSeconds, $hours, $billRate, $payRate,
                    $billAmount, $payAmount, $profitAmount, $now
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Get time entries with filters
     *
     * @param array $filters Filter options
     * @return array List of entries
     */
    public function getTimeEntries(array $filters): array {
        $where = ['1=1'];
        $params = [];

        // Date range filter
        if (!empty($filters['start_date'])) {
            $where[] = 'e.entry_date >= ?';
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'e.entry_date <= ?';
            $params[] = $filters['end_date'];
        }

        // Client filter
        if (!empty($filters['client_id'])) {
            if (is_array($filters['client_id'])) {
                $placeholders = implode(',', array_fill(0, count($filters['client_id']), '?'));
                $where[] = "e.client_id IN ({$placeholders})";
                $params = array_merge($params, $filters['client_id']);
            } else {
                $where[] = 'e.client_id = ?';
                $params[] = $filters['client_id'];
            }
        }

        // Employee filter
        if (!empty($filters['user_id'])) {
            $where[] = 'e.user_id = ?';
            $params[] = $filters['user_id'];
        }

        // Users by client filter (for client-based aggregation)
        if (!empty($filters['client_users'])) {
            $placeholders = implode(',', array_fill(0, count($filters['client_users']), '?'));
            $where[] = "e.user_id IN ({$placeholders})";
            $params = array_merge($params, $filters['client_users']);
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT
                e.id,
                e.entry_date,
                e.user_id,
                u.full_name as employee_name,
                u.email as employee_email,
                e.client_id,
                COALESCE(c.name, e.api_client_name) as client_name,
                e.project_id,
                e.api_project_name as project_name,
                e.description,
                e.duration_seconds,
                e.hours,
                e.bill_rate,
                e.pay_rate,
                e.bill_amount,
                e.pay_amount,
                e.profit_amount,
                e.synced_at
            FROM twr_billing_entries e
            LEFT JOIN twr_users u ON e.user_id = u.user_id
            LEFT JOIN twr_clients c ON e.client_id = c.id
            WHERE {$whereClause}
            ORDER BY e.entry_date DESC, u.full_name ASC
        ";

        // Add pagination if requested
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int)$filters['limit'];

            if (!empty($filters['offset'])) {
                $sql .= ' OFFSET ?';
                $params[] = (int)$filters['offset'];
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate totals for filtered data
     *
     * @param array $filters Filter options
     * @return array Totals
     */
    public function calculateTotals(array $filters): array {
        $where = ['1=1'];
        $params = [];

        // Date range filter
        if (!empty($filters['start_date'])) {
            $where[] = 'e.entry_date >= ?';
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'e.entry_date <= ?';
            $params[] = $filters['end_date'];
        }

        // Client filter
        if (!empty($filters['client_id'])) {
            if (is_array($filters['client_id'])) {
                $placeholders = implode(',', array_fill(0, count($filters['client_id']), '?'));
                $where[] = "e.client_id IN ({$placeholders})";
                $params = array_merge($params, $filters['client_id']);
            } else {
                $where[] = 'e.client_id = ?';
                $params[] = $filters['client_id'];
            }
        }

        // Employee filter
        if (!empty($filters['user_id'])) {
            $where[] = 'e.user_id = ?';
            $params[] = $filters['user_id'];
        }

        // Users by client filter
        if (!empty($filters['client_users'])) {
            $placeholders = implode(',', array_fill(0, count($filters['client_users']), '?'));
            $where[] = "e.user_id IN ({$placeholders})";
            $params = array_merge($params, $filters['client_users']);
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as entry_count,
                COALESCE(SUM(e.hours), 0) as total_hours,
                COALESCE(SUM(e.bill_amount), 0) as total_bill_amount,
                COALESCE(SUM(e.pay_amount), 0) as total_pay_amount,
                COALESCE(SUM(e.profit_amount), 0) as total_profit
            FROM twr_billing_entries e
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'entry_count' => (int)($result['entry_count'] ?? 0),
            'total_hours' => round((float)($result['total_hours'] ?? 0), 2),
            'total_bill_amount' => round((float)($result['total_bill_amount'] ?? 0), 2),
            'total_pay_amount' => round((float)($result['total_pay_amount'] ?? 0), 2),
            'total_profit' => round((float)($result['total_profit'] ?? 0), 2)
        ];
    }

    /**
     * Get entry count for filters
     *
     * @param array $filters Filter options
     * @return int Count
     */
    public function getEntryCount(array $filters): int {
        $totals = $this->calculateTotals($filters);
        return $totals['entry_count'];
    }

    /**
     * Get time entries grouped by user with totals
     *
     * @param array $filters Filter options
     * @return array List of users with their entries and totals
     */
    public function getEntriesGroupedByUser(array $filters): array {
        $where = ['1=1'];
        $params = [];

        // Date range filter
        if (!empty($filters['start_date'])) {
            $where[] = 'e.entry_date >= ?';
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'e.entry_date <= ?';
            $params[] = $filters['end_date'];
        }

        // Client filter
        if (!empty($filters['client_id'])) {
            if (is_array($filters['client_id'])) {
                $placeholders = implode(',', array_fill(0, count($filters['client_id']), '?'));
                $where[] = "e.client_id IN ({$placeholders})";
                $params = array_merge($params, $filters['client_id']);
            } else {
                $where[] = 'e.client_id = ?';
                $params[] = $filters['client_id'];
            }
        }

        // Employee filter
        if (!empty($filters['user_id'])) {
            $where[] = 'e.user_id = ?';
            $params[] = $filters['user_id'];
        }

        // Users by client filter
        if (!empty($filters['client_users'])) {
            $placeholders = implode(',', array_fill(0, count($filters['client_users']), '?'));
            $where[] = "e.user_id IN ({$placeholders})";
            $params = array_merge($params, $filters['client_users']);
        }

        $whereClause = implode(' AND ', $where);

        // Get user summaries
        $stmt = $this->db->prepare("
            SELECT
                e.user_id,
                u.full_name as employee_name,
                u.email as employee_email,
                u.current_bill_rate,
                u.current_pay_rate,
                COUNT(*) as entry_count,
                SUM(e.hours) as total_hours,
                SUM(e.bill_amount) as total_bill_amount,
                SUM(e.pay_amount) as total_pay_amount,
                SUM(e.profit_amount) as total_profit
            FROM twr_billing_entries e
            LEFT JOIN twr_users u ON e.user_id = u.user_id
            WHERE {$whereClause}
            GROUP BY e.user_id, u.full_name, u.email, u.current_bill_rate, u.current_pay_rate
            ORDER BY u.full_name ASC
        ");
        $stmt->execute($params);
        $userSummaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get detailed entries for each user
        $result = [];
        foreach ($userSummaries as $user) {
            $userId = $user['user_id'];

            // Build user-specific where clause
            $userParams = $params;
            $userWhere = $where;
            $userWhere[] = 'e.user_id = ?';
            $userParams[] = $userId;
            $userWhereClause = implode(' AND ', $userWhere);

            $entryStmt = $this->db->prepare("
                SELECT
                    e.id,
                    e.entry_date,
                    e.client_id,
                    COALESCE(c.name, e.api_client_name) as client_name,
                    e.project_id,
                    e.api_project_name as project_name,
                    e.description,
                    e.duration_seconds,
                    e.hours,
                    e.bill_rate,
                    e.pay_rate,
                    e.bill_amount,
                    e.pay_amount,
                    e.profit_amount
                FROM twr_billing_entries e
                LEFT JOIN twr_clients c ON e.client_id = c.id
                WHERE {$userWhereClause}
                ORDER BY e.entry_date DESC
            ");
            $entryStmt->execute($userParams);
            $entries = $entryStmt->fetchAll(PDO::FETCH_ASSOC);

            $result[] = [
                'user_id' => $userId,
                'employee_name' => $user['employee_name'] ?? 'Unknown',
                'employee_email' => $user['employee_email'] ?? '',
                'current_bill_rate' => (float)($user['current_bill_rate'] ?? 0),
                'current_pay_rate' => (float)($user['current_pay_rate'] ?? 0),
                'entry_count' => (int)$user['entry_count'],
                'total_hours' => round((float)$user['total_hours'], 2),
                'total_bill_amount' => round((float)$user['total_bill_amount'], 2),
                'total_pay_amount' => round((float)$user['total_pay_amount'], 2),
                'total_profit' => round((float)$user['total_profit'], 2),
                'entries' => $entries
            ];
        }

        return $result;
    }

    /**
     * Get user summaries only (without entries) - lightweight version for listing
     */
    public function getUserSummaries(array $filters): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['start_date'])) {
            $where[] = 'e.entry_date >= ?';
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $where[] = 'e.entry_date <= ?';
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['client_id'])) {
            if (is_array($filters['client_id'])) {
                $placeholders = implode(',', array_fill(0, count($filters['client_id']), '?'));
                $where[] = "e.client_id IN ({$placeholders})";
                $params = array_merge($params, $filters['client_id']);
            } else {
                $where[] = 'e.client_id = ?';
                $params[] = $filters['client_id'];
            }
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'e.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['client_users'])) {
            $placeholders = implode(',', array_fill(0, count($filters['client_users']), '?'));
            $where[] = "e.user_id IN ({$placeholders})";
            $params = array_merge($params, $filters['client_users']);
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                e.user_id,
                u.full_name as employee_name,
                u.email as employee_email,
                u.current_bill_rate,
                u.current_pay_rate,
                COUNT(*) as entry_count,
                SUM(e.hours) as total_hours,
                SUM(e.bill_amount) as total_bill_amount,
                SUM(e.pay_amount) as total_pay_amount,
                SUM(e.profit_amount) as total_profit
            FROM twr_billing_entries e
            LEFT JOIN twr_users u ON e.user_id = u.user_id
            WHERE {$whereClause}
            GROUP BY e.user_id, u.full_name, u.email, u.current_bill_rate, u.current_pay_rate
            ORDER BY u.full_name ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // DATE RANGE HELPERS
    // =========================================================================

    /**
     * Get date range for period preset
     *
     * @param string $period Period preset name
     * @param string|null $customStart Custom start date
     * @param string|null $customEnd Custom end date
     * @return array ['start' => date, 'end' => date]
     */
    public function getDateRangeForPeriod(string $period, ?string $customStart = null, ?string $customEnd = null): array {
        $today = new DateTime();

        switch ($period) {
            case 'this_month':
                return [
                    'start' => $today->format('Y-m-01'),
                    'end' => $today->format('Y-m-d')
                ];

            case 'last_month':
                $lastMonth = (clone $today)->modify('first day of last month');
                return [
                    'start' => $lastMonth->format('Y-m-01'),
                    'end' => $lastMonth->format('Y-m-t')
                ];

            case 'last_15':
                return [
                    'start' => (clone $today)->modify('-14 days')->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];

            case 'previous_15':
                return [
                    'start' => (clone $today)->modify('-29 days')->format('Y-m-d'),
                    'end' => (clone $today)->modify('-15 days')->format('Y-m-d')
                ];

            case 'earlier_15':
                return [
                    'start' => (clone $today)->modify('-44 days')->format('Y-m-d'),
                    'end' => (clone $today)->modify('-30 days')->format('Y-m-d')
                ];

            case 'custom':
                return [
                    'start' => $customStart ?? $today->format('Y-m-01'),
                    'end' => $customEnd ?? $today->format('Y-m-d')
                ];

            default:
                // Default to this month
                return [
                    'start' => $today->format('Y-m-01'),
                    'end' => $today->format('Y-m-d')
                ];
        }
    }

    // =========================================================================
    // SYNC LOG
    // =========================================================================

    /**
     * Log sync operation
     *
     * @param string $syncType Type of sync
     * @param int $recordsProcessed Number of records
     * @param string $status Status (success/error)
     * @param string|null $message Additional message
     */
    public function logSync(string $syncType, int $recordsProcessed, string $status = 'success', ?string $message = null): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO twr_sync_log (sync_type, records_processed, status, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$syncType, $recordsProcessed, $status, $message]);
        } catch (Exception $e) {
            error_log("BillingModel::logSync error: " . $e->getMessage());
        }
    }
}
