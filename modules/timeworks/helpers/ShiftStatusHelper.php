<?php
/**
 * TimeWorks Module - Shift Status Helper
 *
 * Calculates shift statuses based on scheduled vs actual times
 * with configurable tolerance settings.
 *
 * Status Definitions:
 * - not_started: Shift not begun, no tracked time
 * - on_time: Clock-in within tolerance window (10 min early - 30 min late)
 * - late: Clock-in beyond tolerance, still working
 * - in_progress: Currently working (real-time status)
 * - completed: Full shift completed successfully
 * - abandoned: Left early, minimum hours not met
 * - absent: No clock-in for the entire shift
 * - pto: Paid time off
 * - upto: Unpaid time off
 *
 * @author ikinciadam@gmail.com
 */

class ShiftStatusHelper
{
    private $db;
    private $toleranceBefore = 10; // minutes before shift start
    private $toleranceAfter = 30;  // minutes after shift start
    private $minHoursPercentage = 80; // minimum % of scheduled hours
    private $timezone = 'America/New_York';

    /**
     * Status constants
     */
    const STATUS_NOT_STARTED = 'not_started';
    const STATUS_ON_TIME = 'on_time';
    const STATUS_LATE = 'late';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';
    const STATUS_ABSENT = 'absent';
    const STATUS_PTO = 'pto';
    const STATUS_UPTO = 'upto';

    /**
     * Status labels for display
     */
    private static $statusLabels = [
        'not_started' => 'Not Started',
        'on_time' => 'On Time',
        'late' => 'Late',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'abandoned' => 'Abandoned',
        'absent' => 'Absent',
        'pto' => 'PTO',
        'upto' => 'UPTO'
    ];

    /**
     * Status colors for badges
     */
    private static $statusColors = [
        'not_started' => 'secondary',
        'on_time' => 'success',
        'late' => 'warning',
        'in_progress' => 'info',
        'completed' => 'primary',
        'abandoned' => 'danger',
        'absent' => 'dark',
        'pto' => 'info',
        'upto' => 'secondary'
    ];

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     */
    public function __construct($db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            global $db;
            $this->db = $db;
        }

        $this->loadSettings();
    }

    /**
     * Load settings from database
     */
    private function loadSettings()
    {
        try {
            $stmt = $this->db->query("SELECT `key`, `value` FROM settings WHERE `group` = 'timeworks'");
            while ($row = $stmt->fetch()) {
                switch ($row['key']) {
                    case 'late_tolerance_before_minutes':
                        $this->toleranceBefore = (int) $row['value'];
                        break;
                    case 'late_tolerance_after_minutes':
                        $this->toleranceAfter = (int) $row['value'];
                        break;
                    case 'min_hours_percentage':
                        $this->minHoursPercentage = (int) $row['value'];
                        break;
                }
            }
        } catch (Exception $e) {
            error_log("ShiftStatusHelper: Error loading settings - " . $e->getMessage());
        }
    }

    /**
     * Calculate shift status based on scheduled and actual times
     *
     * @param string $scheduledStart Scheduled start time (HH:MM:SS)
     * @param string $scheduledEnd Scheduled end time (HH:MM:SS)
     * @param string|null $actualStart Actual clock-in time (HH:MM:SS) or null
     * @param string|null $actualEnd Actual clock-out time (HH:MM:SS) or null
     * @param string $date Date of the shift (Y-m-d)
     * @param bool $isRealtime Whether to calculate real-time status
     * @return array Status details including status code, label, color, and metrics
     */
    public function calculateStatus($scheduledStart, $scheduledEnd, $actualStart, $actualEnd, $date, $isRealtime = false)
    {
        $now = new DateTime('now', new DateTimeZone($this->timezone));
        $shiftDate = new DateTime($date, new DateTimeZone($this->timezone));

        // Parse scheduled times
        $schedStart = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $scheduledStart, new DateTimeZone($this->timezone));
        $schedEnd = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $scheduledEnd, new DateTimeZone($this->timezone));

        // Handle overnight shifts
        if ($schedEnd <= $schedStart) {
            $schedEnd->modify('+1 day');
        }

        // Calculate scheduled hours
        $scheduledHours = ($schedEnd->getTimestamp() - $schedStart->getTimestamp()) / 3600;

        // Calculate tolerance window
        $toleranceStart = clone $schedStart;
        $toleranceStart->modify("-{$this->toleranceBefore} minutes");

        $toleranceEnd = clone $schedStart;
        $toleranceEnd->modify("+{$this->toleranceAfter} minutes");

        // Calculate minimum required hours
        $minRequiredHours = $scheduledHours * ($this->minHoursPercentage / 100);

        // Result structure
        $result = [
            'status' => self::STATUS_NOT_STARTED,
            'label' => self::$statusLabels[self::STATUS_NOT_STARTED],
            'color' => self::$statusColors[self::STATUS_NOT_STARTED],
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'scheduled_hours' => round($scheduledHours, 2),
            'tolerance_start' => $toleranceStart->format('H:i:s'),
            'tolerance_end' => $toleranceEnd->format('H:i:s'),
            'min_required_hours' => round($minRequiredHours, 2),
            'actual_start' => $actualStart,
            'actual_end' => $actualEnd,
            'worked_hours' => 0,
            'late_minutes' => 0,
            'is_within_window' => false
        ];

        // No clock-in
        if (empty($actualStart)) {
            // Check if shift is in the past
            if ($isRealtime && $now < $schedStart) {
                // Shift hasn't started yet
                $result['status'] = self::STATUS_NOT_STARTED;
            } elseif ($now > $schedEnd || !$isRealtime) {
                // Shift is over with no clock-in
                $result['status'] = self::STATUS_ABSENT;
            } else {
                // Shift should have started but no clock-in (late detection zone)
                if ($now > $toleranceEnd) {
                    $result['status'] = self::STATUS_ABSENT;
                } else {
                    $result['status'] = self::STATUS_NOT_STARTED;
                }
            }

            $result['label'] = self::$statusLabels[$result['status']];
            $result['color'] = self::$statusColors[$result['status']];
            return $result;
        }

        // Parse actual times
        $actStart = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $actualStart, new DateTimeZone($this->timezone));

        // Handle actual end time
        $actEnd = null;
        if (!empty($actualEnd)) {
            $actEnd = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $actualEnd, new DateTimeZone($this->timezone));

            // Handle overnight actual end
            if ($actEnd < $actStart) {
                $actEnd->modify('+1 day');
            }
        }

        // Calculate worked hours
        $workedHours = 0;
        if ($actEnd) {
            $workedHours = ($actEnd->getTimestamp() - $actStart->getTimestamp()) / 3600;
        } elseif ($isRealtime) {
            $workedHours = ($now->getTimestamp() - $actStart->getTimestamp()) / 3600;
        }

        $result['worked_hours'] = round($workedHours, 2);

        // Check if clock-in was within tolerance window
        $isWithinWindow = ($actStart >= $toleranceStart && $actStart <= $toleranceEnd);
        $result['is_within_window'] = $isWithinWindow;

        // Calculate late minutes
        $lateMinutes = 0;
        if ($actStart > $schedStart) {
            $lateMinutes = ($actStart->getTimestamp() - $schedStart->getTimestamp()) / 60;
        }
        $result['late_minutes'] = round($lateMinutes);

        // Determine status
        if ($isRealtime && empty($actualEnd)) {
            // Currently working
            if ($isWithinWindow) {
                $result['status'] = self::STATUS_IN_PROGRESS;
            } else {
                $result['status'] = self::STATUS_LATE;
            }
        } elseif (!empty($actualEnd)) {
            // Shift completed - check if enough hours
            if ($workedHours >= $minRequiredHours) {
                if ($isWithinWindow) {
                    $result['status'] = self::STATUS_COMPLETED;
                } else {
                    $result['status'] = self::STATUS_LATE;
                }
            } else {
                $result['status'] = self::STATUS_ABANDONED;
            }
        } else {
            // Historical data without end time
            if ($isWithinWindow) {
                $result['status'] = self::STATUS_ON_TIME;
            } else {
                $result['status'] = self::STATUS_LATE;
            }
        }

        $result['label'] = self::$statusLabels[$result['status']];
        $result['color'] = self::$statusColors[$result['status']];

        return $result;
    }

    /**
     * Check if a user is late (for cron job detection)
     *
     * @param string $userId User ID
     * @param string $date Date to check (Y-m-d)
     * @return array|null Late record data or null if not late
     */
    public function checkUserLate($userId, $date)
    {
        // Get user's shift for the day
        $dayOfWeek = date('l', strtotime($date));

        $stmt = $this->db->prepare("
            SELECT start_time, end_time, is_off
            FROM twr_user_shifts
            WHERE user_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$userId, $dayOfWeek]);
        $shift = $stmt->fetch();

        // No shift defined or day off
        if (!$shift || $shift['is_off']) {
            return null;
        }

        $now = new DateTime('now', new DateTimeZone($this->timezone));
        $shiftStart = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $shift['start_time'], new DateTimeZone($this->timezone));

        // Calculate late threshold (shift start + tolerance after)
        $lateThreshold = clone $shiftStart;
        $lateThreshold->modify("+{$this->toleranceAfter} minutes");

        // Not past threshold yet
        if ($now < $lateThreshold) {
            return null;
        }

        // Calculate how late (in minutes)
        $lateMinutes = ($now->getTimestamp() - $shiftStart->getTimestamp()) / 60;

        return [
            'user_id' => $userId,
            'shift_date' => $date,
            'scheduled_start' => $shift['start_time'],
            'scheduled_end' => $shift['end_time'],
            'late_minutes' => round($lateMinutes),
            'detected_at' => $now->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Check if user has a leave request for the given date
     *
     * @param string $userId User ID
     * @param string $date Date to check
     * @return array|null Leave request or null
     */
    public function checkLeaveRequest($userId, $date)
    {
        $stmt = $this->db->prepare("
            SELECT lr.*, lt.code as leave_code, lt.name as leave_name, lt.is_paid
            FROM twr_leave_requests lr
            JOIN twr_leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.user_id = ?
              AND lr.status = 'approved'
              AND ? BETWEEN lr.start_date AND lr.end_date
        ");
        $stmt->execute([$userId, $date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get status badge HTML
     *
     * @param string $status Status code
     * @return string HTML badge
     */
    public static function getStatusBadge($status)
    {
        $label = self::$statusLabels[$status] ?? ucfirst($status);
        $color = self::$statusColors[$status] ?? 'secondary';

        return "<span class=\"badge bg-{$color}\">{$label}</span>";
    }

    /**
     * Get all status options for dropdowns
     *
     * @return array Status options
     */
    public static function getStatusOptions()
    {
        $options = [];
        foreach (self::$statusLabels as $code => $label) {
            $options[] = [
                'code' => $code,
                'label' => $label,
                'color' => self::$statusColors[$code]
            ];
        }
        return $options;
    }

    /**
     * Get tolerance settings
     *
     * @return array Tolerance settings
     */
    public function getToleranceSettings()
    {
        return [
            'before_minutes' => $this->toleranceBefore,
            'after_minutes' => $this->toleranceAfter,
            'min_hours_percentage' => $this->minHoursPercentage,
            'timezone' => $this->timezone
        ];
    }

    /**
     * Calculate worked hours within shift window only
     * (Ignores work done outside the scheduled shift)
     *
     * @param DateTime $schedStart Scheduled start
     * @param DateTime $schedEnd Scheduled end
     * @param DateTime $actStart Actual start
     * @param DateTime $actEnd Actual end
     * @return float Hours worked within shift window
     */
    public function calculateInWindowHours($schedStart, $schedEnd, $actStart, $actEnd)
    {
        // Determine the effective start (later of scheduled and actual)
        $effectiveStart = ($actStart > $schedStart) ? $actStart : $schedStart;

        // Determine the effective end (earlier of scheduled and actual)
        $effectiveEnd = ($actEnd < $schedEnd) ? $actEnd : $schedEnd;

        // If effective start is after effective end, no valid hours
        if ($effectiveStart >= $effectiveEnd) {
            return 0;
        }

        return ($effectiveEnd->getTimestamp() - $effectiveStart->getTimestamp()) / 3600;
    }

    /**
     * Determine abandoned reason
     *
     * @param array $statusData Status calculation result
     * @return string Reason description
     */
    public function getAbandonedReason($statusData)
    {
        if ($statusData['status'] !== self::STATUS_ABANDONED) {
            return '';
        }

        $scheduledHours = $statusData['scheduled_hours'];
        $workedHours = $statusData['worked_hours'];
        $lateMinutes = $statusData['late_minutes'];
        $minRequired = $statusData['min_required_hours'];

        $deficit = round($minRequired - $workedHours, 1);

        if ($workedHours == 0) {
            return "No work logged";
        }

        if ($lateMinutes > 60) {
            return "Late entry ({$lateMinutes} min late), worked {$workedHours}h of {$minRequired}h required";
        }

        if ($workedHours < $scheduledHours * 0.5) {
            return "Early exit - worked only {$workedHours}h ({$deficit}h short)";
        }

        return "Incomplete shift - {$deficit}h short of minimum required";
    }
}
