<?php
/**
 * Heaven Nails - Check Slot Availability API
 * Returns available time slots for a given date
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Invalid request method');
}

// Get and validate date parameter
$date = sanitize($_GET['date'] ?? '');
$staffId = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(false, 'Invalid date format. Use YYYY-MM-DD');
}

// Validate date is not in the past
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$today = new DateTime();
$today->setTime(0, 0, 0);

if ($dateObj < $today) {
    jsonResponse(false, 'Cannot check availability for past dates');
}

// Define business hours (10:00 AM - 6:00 PM, 1-hour slots)
$businessHours = [
    '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'
];

// Service durations in minutes
$serviceDurations = [
    'Classic Manicure' => 45,
    'Gel Extensions' => 90,
    'Nail Art' => 60,
    'Spa Pedicure' => 60,
    'Acrylic Nails' => 90,
    'Nail Repair' => 30
];

try {
    $db = Database::getInstance()->getConnection();
    
    // Get booked slots for the date
    $sql = "SELECT preferred_time, duration_minutes, staff_id 
            FROM appointments 
            WHERE preferred_date = :date 
            AND status IN ('pending', 'confirmed')";
    
    $params = [':date' => $date];
    
    if ($staffId) {
        $sql .= " AND (staff_id = :staff_id OR staff_id IS NULL)";
        $params[':staff_id'] = $staffId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookedSlots = $stmt->fetchAll();
    
    // Get blocked dates/times
    $blockSql = "SELECT block_time, end_time FROM blocked_dates 
                 WHERE block_date = :date";
    $blockParams = [':date' => $date];
    
    if ($staffId) {
        $blockSql .= " AND (staff_id = :staff_id OR staff_id IS NULL)";
        $blockParams[':staff_id'] = $staffId;
    }
    
    $blockStmt = $db->prepare($blockSql);
    $blockStmt->execute($blockParams);
    $blockedTimes = $blockStmt->fetchAll();
    
    // Check if entire day is blocked
    $dayBlocked = false;
    foreach ($blockedTimes as $block) {
        if ($block['block_time'] === null) {
            $dayBlocked = true;
            break;
        }
    }
    
    // Build availability response
    $slots = [];
    
    foreach ($businessHours as $time) {
        $available = true;
        $reason = null;
        
        // Check if day is blocked
        if ($dayBlocked) {
            $available = false;
            $reason = 'Salon closed';
        }
        
        // Check if specific time is blocked
        if ($available) {
            foreach ($blockedTimes as $block) {
                if ($block['block_time'] !== null) {
                    $blockStart = strtotime($block['block_time']);
                    $blockEnd = $block['end_time'] ? strtotime($block['end_time']) : $blockStart + 3600;
                    $slotTime = strtotime($time);
                    
                    if ($slotTime >= $blockStart && $slotTime < $blockEnd) {
                        $available = false;
                        $reason = 'Blocked';
                        break;
                    }
                }
            }
        }
        
        // Check if slot is already booked
        if ($available) {
            foreach ($bookedSlots as $booking) {
                $bookingTime = date('H:i', strtotime($booking['preferred_time']));
                if ($bookingTime === $time) {
                    $available = false;
                    $reason = 'Booked';
                    break;
                }
            }
        }
        
        // Format time for display
        $displayTime = date('g:i A', strtotime($time));
        
        $slots[] = [
            'time' => $time,
            'display' => $displayTime,
            'available' => $available,
            'reason' => $reason
        ];
    }
    
    // Get staff list
    $staffStmt = $db->query("SELECT id, name, specialty, avatar_emoji FROM staff WHERE is_active = TRUE ORDER BY name");
    $staffList = $staffStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'slots' => $slots,
        'staff' => $staffList,
        'service_durations' => $serviceDurations
    ]);
    
} catch (PDOException $e) {
    error_log("Availability check error: " . $e->getMessage());
    jsonResponse(false, 'Unable to check availability');
}
?>
