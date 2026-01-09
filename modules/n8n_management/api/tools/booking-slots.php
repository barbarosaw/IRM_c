<?php
/**
 * Booking Slots API
 * Returns available booking slots based on settings and existing bookings
 * 
 * GET: Get available slots
 * POST: Book a slot
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

// Get booking settings
function getBookingSettings($db) {
    $stmt = $db->query("SELECT setting_key, setting_value FROM chat_booking_settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Convert timezone
function convertTimezone($datetime, $fromTz, $toTz) {
    $dt = new DateTime($datetime, new DateTimeZone($fromTz));
    $dt->setTimezone(new DateTimeZone($toTz));
    return $dt;
}

// Get available slots
function getAvailableSlots($db, $settings, $userTimezone = null, $limit = 3) {
    $workDays = explode(',', $settings['work_days']);
    $workStart = (int)$settings['work_start_hour'];
    $workEnd = (int)$settings['work_end_hour'];
    $businessTz = $settings['timezone'];
    $slotDuration = (int)$settings['slot_duration'];
    $minHoursAhead = (int)$settings['min_hours_ahead'];
    $maxDaysAhead = (int)$settings['max_days_ahead'];

    // Get current time in business timezone
    $now = new DateTime('now', new DateTimeZone($businessTz));
    $minTime = clone $now;
    $minTime->modify("+{$minHoursAhead} hours");

    // Get existing bookings
    $stmt = $db->prepare("
        SELECT slot_datetime FROM chat_booked_slots 
        WHERE status != 'cancelled' 
        AND slot_datetime >= ?
        ORDER BY slot_datetime
    ");
    $stmt->execute([$minTime->format('Y-m-d H:i:s')]);
    $bookedSlots = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'slot_datetime');

    // Generate available slots
    $availableSlots = [];
    $checkDate = clone $minTime;
    $endDate = clone $now;
    $endDate->modify("+{$maxDaysAhead} days");

    while ($checkDate < $endDate && count($availableSlots) < $limit) {
        $dayOfWeek = $checkDate->format('N'); // 1=Monday, 7=Sunday
        
        if (in_array($dayOfWeek, $workDays)) {
            $hour = (int)$checkDate->format('H');
            $minute = (int)$checkDate->format('i');
            
            // Round to next slot
            $slotMinute = ceil($minute / $slotDuration) * $slotDuration;
            if ($slotMinute >= 60) {
                $hour++;
                $slotMinute = 0;
            }
            
            // Check each slot in the day
            while ($hour < $workEnd) {
                if ($hour >= $workStart) {
                    $slotTime = clone $checkDate;
                    $slotTime->setTime($hour, $slotMinute);
                    
                    // Check if slot is available
                    $slotStr = $slotTime->format('Y-m-d H:i:s');
                    if (!in_array($slotStr, $bookedSlots) && $slotTime > $minTime) {
                        $slot = [
                            'datetime_utc' => $slotTime->setTimezone(new DateTimeZone('UTC'))->format('c'),
                            'datetime_est' => convertTimezone($slotStr, $businessTz, 'America/New_York')->format('Y-m-d H:i'),
                            'datetime_display' => convertTimezone($slotStr, $businessTz, 'America/New_York')->format('l, F j \a\t g:i A') . ' EST',
                            'duration_minutes' => $slotDuration
                        ];
                        
                        // Add user timezone if provided
                        if ($userTimezone) {
                            $slot['datetime_user'] = convertTimezone($slotStr, $businessTz, $userTimezone)->format('Y-m-d H:i');
                            $slot['datetime_user_display'] = convertTimezone($slotStr, $businessTz, $userTimezone)->format('l, F j \a\t g:i A') . ' ' . $userTimezone;
                        }
                        
                        $availableSlots[] = $slot;
                        if (count($availableSlots) >= $limit) break;
                    }
                }
                
                $slotMinute += $slotDuration;
                if ($slotMinute >= 60) {
                    $hour++;
                    $slotMinute = 0;
                }
            }
        }
        
        // Move to next day at work start
        $checkDate->modify('+1 day');
        $checkDate->setTime($workStart, 0);
    }

    return $availableSlots;
}

// Book a slot
function bookSlot($db, $settings, $data) {
    $sessionId = $data['session_id'] ?? null;
    $slotDatetime = $data['slot_datetime'] ?? null; // Expected in EST
    $attendeeName = $data['attendee_name'] ?? null;
    $attendeeEmail = $data['attendee_email'] ?? null;
    $attendeeTimezone = $data['attendee_timezone'] ?? 'America/New_York';

    if (!$slotDatetime || !$attendeeEmail) {
        return ['success' => false, 'error' => 'slot_datetime and attendee_email required'];
    }

    $businessTz = $settings['timezone'];
    
    // Convert to business timezone for storage
    $dt = new DateTime($slotDatetime, new DateTimeZone('America/New_York'));
    $dt->setTimezone(new DateTimeZone($businessTz));
    $slotStr = $dt->format('Y-m-d H:i:s');

    // Check if slot is still available
    $stmt = $db->prepare("
        SELECT id FROM chat_booked_slots 
        WHERE slot_datetime = ? AND status != 'cancelled'
    ");
    $stmt->execute([$slotStr]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'This slot is no longer available'];
    }

    // Book the slot
    $stmt = $db->prepare("
        INSERT INTO chat_booked_slots 
        (session_id, slot_datetime, attendee_name, attendee_email, attendee_timezone, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$sessionId, $slotStr, $attendeeName, $attendeeEmail, $attendeeTimezone]);
    $bookingId = $db->lastInsertId();

    return [
        'success' => true,
        'booking_id' => $bookingId,
        'slot_datetime_est' => $dt->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d H:i'),
        'slot_display' => $dt->format('l, F j \a\t g:i A') . ' EST',
        'message' => 'Booking created successfully. Pending confirmation.'
    ];
}

// Handle request
$settings = getBookingSettings($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userTimezone = $_GET['timezone'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 3), 10);
    
    $slots = getAvailableSlots($db, $settings, $userTimezone, $limit);
    
    jsonResponse([
        'success' => true,
        'slots' => $slots,
        'business_hours' => [
            'days' => 'Monday - Friday',
            'hours' => $settings['work_start_hour'] . ':00 - ' . $settings['work_end_hour'] . ':00 EST',
            'timezone' => $settings['timezone']
        ],
        'note' => 'All times shown in EST. If you prefer a different time, please let us know your timezone and preferred time.'
    ]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = bookSlot($db, $settings, $input);
    jsonResponse($result, $result['success'] ? 200 : 400);
} else {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
