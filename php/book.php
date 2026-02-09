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
$totalDuration = 0;
try {
    $db = Database::getInstance()->getConnection();
    
    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($sanitizedServices), '?'));
    $stmt = $db->prepare("SELECT name, duration_minutes FROM services WHERE name IN ($placeholders)");
    $stmt->execute($sanitizedServices);
    $dbServices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [name => duration]
    
    foreach ($sanitizedServices as $service) {
        // Use DB duration or default to 60 if not found (fallback)
        $totalDuration += $dbServices[$service] ?? 60;
    }
} catch (PDOException $e) {
    error_log("Error fetching service durations: " . $e->getMessage());
    $totalDuration = count($sanitizedServices) * 60; // Fallback
}

$totalDuration = max(30, $totalDuration); // Minimum 30 mins

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
New Appointment Request<br><br>
Client: $clientName<br>
Email: $email<br>
Phone: $phone<br><br>
Services: $servicesText<br>
Date: $formattedDate<br>
Time: $formattedTime<br><br>
Notes: " . ($notes ?: 'None') . "<br><br>
Booking ID: #$bookingId<br>
Status: Pending<br><br>
Please log in to the admin dashboard to confirm or manage this booking.
";
    
    sendMail(ADMIN_EMAIL, $adminSubject, $adminMessage);
    
    // Send client confirmation email
    $clientSubject = "We’ve received your request! ✨ (Appointment #$bookingId)";
    $clientMessage = "
<html>
<body style='font-family: sans-serif; color: #333;'>
<p>Dear $clientName,</p>

<p>Thank you for choosing " . SALON_NAME . "! We have received your booking request and are checking our availability.</p>

<p><strong>Requested Details:</strong></p>

<p>
Service: $servicesText<br>
Date: $formattedDate<br>
Time: $formattedTime
</p>

<p><strong>Status:</strong> 🟡 PENDING APPROVAL</p>

<p><strong>What happens next?</strong> Our team will review your request and send a separate confirmation email shortly. Please do not arrive at the studio until you receive this final confirmation.</p>

<p><strong>Need to make changes?</strong> You can reply to this email or call us at " . SALON_PHONE . ".</p>

<p>While you wait, check out our latest designs on Instagram: <a href='https://www.instagram.com/the_heaven_nail_/'>@the_heaven_nail_</a></p>

<p>Chat on WhatsApp: <a href='https://wa.me/919316458160'>+91 93164 58160</a></p>

<p>Warmly,<br>
The Heaven Nails Team</p>
</body>
</html>
";
    
    sendMail($email, $clientSubject, $clientMessage);
    
    jsonResponse(true, 'Booking submitted successfully', ['booking_id' => $bookingId]);
    
} catch (PDOException $e) {
    error_log("Booking error: " . $e->getMessage());
    jsonResponse(false, 'Unable to process booking. Please try again.');
}
?>
