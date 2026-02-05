<?php
/**
 * Heaven Nails - Booking Handler
 * Processes appointment booking requests
 */

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

// Honeypot spam protection - if this field is filled, it's a bot
$honeypot = $_POST['website'] ?? '';
if (!empty($honeypot)) {
    // Silently reject bot submissions
    sleep(2); // Slow down bots
    jsonResponse(false, 'Booking failed. Please try again.');
}

// Get and sanitize form data
$clientName = sanitize($_POST['client_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$services = $_POST['services'] ?? [];
$preferredDate = sanitize($_POST['preferred_date'] ?? '');
$preferredTime = sanitize($_POST['preferred_time'] ?? '');
$notes = sanitize($_POST['notes'] ?? '');
$staffId = isset($_POST['staff_id']) && $_POST['staff_id'] !== '' ? intval($_POST['staff_id']) : null;

// Validate required fields
$errors = [];

if (empty($clientName)) {
    $errors[] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($phone) || strlen($phone) < 10) {
    $errors[] = 'Valid phone number is required';
}

if (empty($services) || !is_array($services)) {
    $errors[] = 'Please select at least one service';
}

if (empty($preferredDate)) {
    $errors[] = 'Preferred date is required';
}

if (empty($preferredTime)) {
    $errors[] = 'Preferred time is required';
}

// Validate date is in the future
if (!empty($preferredDate)) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
    $today = new DateTime();
    if (!$dateObj || $dateObj <= $today) {
        $errors[] = 'Please select a future date';
    }
}

if (!empty($errors)) {
    jsonResponse(false, implode(', ', $errors));
}

// Sanitize services array and convert to JSON
$sanitizedServices = array_map('sanitize', $services);
$servicesJson = json_encode($sanitizedServices);

// Calculate total duration based on selected services
$serviceDurations = [
    'Classic Manicure' => 45,
    'Gel Extensions' => 90,
    'Nail Art' => 60,
    'Spa Pedicure' => 60,
    'Acrylic Nails' => 90,
    'Nail Repair' => 30
];
$totalDuration = 0;
foreach ($sanitizedServices as $service) {
    $totalDuration += $serviceDurations[$service] ?? 60;
}
$totalDuration = max(60, $totalDuration); // Minimum 1 hour

try {
    $db = Database::getInstance()->getConnection();
    
    // Start transaction for atomic check-and-insert (fixes TOCTOU race condition)
    $db->beginTransaction();
    
    try {
        // Lock the relevant rows to prevent race condition
        // Using SELECT ... FOR UPDATE to lock matching rows during transaction
        $checkSql = "SELECT id FROM appointments 
                     WHERE preferred_date = :date 
                     AND status IN ('pending', 'confirmed')
                     FOR UPDATE";
        $checkParams = [':date' => $preferredDate];
        
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute($checkParams);
        $existingBookings = $checkStmt->fetchAll();
        
        // Check for time overlap with service duration
        $requestedStart = strtotime($preferredTime);
        $requestedEnd = $requestedStart + ($totalDuration * 60);
        
        // Fetch full booking details for overlap check
        $detailSql = "SELECT preferred_time, duration_minutes, staff_id 
                      FROM appointments 
                      WHERE preferred_date = :date 
                      AND status IN ('pending', 'confirmed')";
        $detailParams = [':date' => $preferredDate];
        
        if ($staffId) {
            $detailSql .= " AND (staff_id = :staff_id OR staff_id IS NULL)";
            $detailParams[':staff_id'] = $staffId;
        }
        
        $detailStmt = $db->prepare($detailSql);
        $detailStmt->execute($detailParams);
        $bookedSlots = $detailStmt->fetchAll();
        
        $hasConflict = false;
        foreach ($bookedSlots as $booking) {
            $bookingStart = strtotime($booking['preferred_time']);
            $bookingDuration = $booking['duration_minutes'] ?? 60;
            $bookingEnd = $bookingStart + ($bookingDuration * 60);
            
            // Check for overlap
            if ($requestedStart < $bookingEnd && $requestedEnd > $bookingStart) {
                $hasConflict = true;
                break;
            }
        }
        
        if ($hasConflict) {
            $db->rollBack();
            jsonResponse(false, 'Sorry, this time slot was just booked. Please select another time.');
        }
        
        // Insert booking
        $stmt = $db->prepare("
            INSERT INTO appointments (client_name, email, phone, services, preferred_date, preferred_time, notes, status, staff_id, duration_minutes, created_at)
            VALUES (:client_name, :email, :phone, :services, :preferred_date, :preferred_time, :notes, 'pending', :staff_id, :duration, NOW())
        ");
        
        $stmt->execute([
            ':client_name' => $clientName,
            ':email' => $email,
            ':phone' => $phone,
            ':services' => $servicesJson,
            ':preferred_date' => $preferredDate,
            ':preferred_time' => $preferredTime,
            ':notes' => $notes,
            ':staff_id' => $staffId,
            ':duration' => $totalDuration
        ]);
        
        $bookingId = $db->lastInsertId();
        
        // Commit the transaction
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
    // Format services for email
    $servicesText = implode(', ', $sanitizedServices);
    $formattedDate = date('F j, Y', strtotime($preferredDate));
    $formattedTime = date('g:i A', strtotime($preferredTime));
    
    // Send admin notification email
    $adminSubject = "New Booking Request - " . SALON_NAME;
    $adminMessage = "
New Appointment Request

Client: $clientName
Email: $email
Phone: $phone

Services: $servicesText
Date: $formattedDate
Time: $formattedTime

Notes: " . ($notes ?: 'None') . "

Booking ID: #$bookingId
Status: Pending

Please log in to the admin dashboard to confirm or manage this booking.
";
    
    @mail(ADMIN_EMAIL, $adminSubject, $adminMessage, "From: noreply@heavennails.com");
    
    // Send client confirmation email
    $clientSubject = "Your Appointment Request - " . SALON_NAME;
    $clientMessage = "
Dear $clientName,

Thank you for choosing " . SALON_NAME . "!

Your appointment request has been received:

Services: $servicesText
Date: $formattedDate
Time: $formattedTime

Your booking is currently PENDING confirmation. We will contact you shortly to confirm your appointment.

If you have any questions, please call us at " . SALON_PHONE . " or reply to this email.

We look forward to seeing you!

Best regards,
" . SALON_NAME . "
" . SALON_EMAIL . "
";
    
    @mail($email, $clientSubject, $clientMessage, "From: " . SALON_EMAIL);
    
    jsonResponse(true, 'Booking submitted successfully', ['booking_id' => $bookingId]);
    
} catch (PDOException $e) {
    error_log("Booking error: " . $e->getMessage());
    jsonResponse(false, 'Unable to process booking. Please try again.');
}
?>
