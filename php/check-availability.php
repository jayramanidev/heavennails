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

$selectedServices = isset($_GET['services']) ? explode(',', $_GET['services']) : [];

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

// Define business hours (8:00 AM - 10:00 PM, Mon-Sun)
// Last booking slot at 9:00 PM to allow 1-hour minimum service before closing
$businessHours = [
    '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'
];

// Closing time in minutes from midnight (10:00 PM = 22:00 = 1320 minutes)
$closingTime = 22 * 60;

// Service durations in minutes
// Service durations in minutes (fetch from DB)
$serviceDurations = [];
try {
    $db = Database::getInstance()->getConnection();
    $svcStmt = $db->query("SELECT name, duration_minutes FROM services");
    $serviceDurations = $svcStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Error fetching service durations: " . $e->getMessage());
    // Fallback defaults
    $serviceDurations = [
        'Classic Manicure' => 45,
        'Gel Extensions' => 90,
        'Nail Art' => 60,
        'Spa Pedicure' => 60,
        'Acrylic Nails' => 90,
        'Nail Repair' => 30
    ];
}

// Calculate total duration for selected services
$requestedDuration = 0;
foreach ($selectedServices as $service) {
    $service = trim($service);
    $requestedDuration += $serviceDurations[$service] ?? 60;
}
$requestedDuration = max(60, $requestedDuration); // Minimum 1 hour

try {
    $db = Database::getInstance()->getConnection();
    
    // Get booked slots for the date WITH their durations
    $sql = "SELECT preferred_time, duration_minutes 
            FROM appointments 
            WHERE preferred_date = :date 
            AND status IN ('pending', 'confirmed')";
    
    $params = [':date' => $date];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookedSlots = $stmt->fetchAll();
    
    // Get blocked dates/times
    $blockSql = "SELECT block_time, end_time FROM blocked_dates 
                 WHERE block_date = :date";
    $blockParams = [':date' => $date];
    
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
        
        // Convert slot time to minutes from midnight
        $slotMinutes = intval(substr($time, 0, 2)) * 60 + intval(substr($time, 3, 2));
        
        // Check if service would end after closing time
        if ($slotMinutes + $requestedDuration > $closingTime) {
            $available = false;
            $reason = 'Service would end after closing';
        }
        
        // Check if day is blocked
        if ($available && $dayBlocked) {
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
                    $slotEndTime = $slotTime + ($requestedDuration * 60);
                    
                    // Check if requested slot overlaps with blocked time
                    if ($slotTime < $blockEnd && $slotEndTime > $blockStart) {
                        $available = false;
                        $reason = 'Blocked';
                        break;
                    }
                }
            }
        }
        
        // Check if slot overlaps with existing bookings (considering service duration)
        if ($available) {
            $slotTime = strtotime($time);
            $slotEndTime = $slotTime + ($requestedDuration * 60);
            
            foreach ($bookedSlots as $booking) {
                $bookingStart = strtotime($booking['preferred_time']);
                $bookingDuration = $booking['duration_minutes'] ?? 60;
                $bookingEnd = $bookingStart + ($bookingDuration * 60);
                
                // Check for overlap: new booking would overlap if it starts before existing ends
                // AND ends after existing starts
                if ($slotTime < $bookingEnd && $slotEndTime > $bookingStart) {
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
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'slots' => $slots,
        'service_durations' => $serviceDurations,
        'requested_duration' => $requestedDuration
    ]);
    
} catch (PDOException $e) {
    error_log("Availability check error: " . $e->getMessage());
    jsonResponse(false, 'Unable to check availability');
}
?>
