<?php
/**
 * Heaven Nails - Appointment Reminder Script
 * Sends email reminders 24 hours before appointment
 * Intended to be run via cron job daily
 */

require_once 'config.php';

// Check if running from CLI or if authenticated admin
if (php_sapi_name() !== 'cli') {
    // If accessed via browser, require admin login
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die('Access denied');
    }
}

$db = Database::getInstance()->getConnection();

// Calculate "Tomorrow" date
$tomorrow = new DateTime('tomorrow');
$tomorrowDate = $tomorrow->format('Y-m-d');

echo "Checking for appointments on: $tomorrowDate\n";

try {
    // Fetch confirmed appointments for tomorrow
    $stmt = $db->prepare("
        SELECT * FROM appointments 
        WHERE preferred_date = :date 
        AND status = 'confirmed'
    ");
    $stmt->execute([':date' => $tomorrowDate]);
    $appointments = $stmt->fetchAll();

    $count = 0;
    $googleMapsLink = "https://maps.app.goo.gl/sL6c3vtcnqBhm7os8"; // From index.html

    foreach ($appointments as $appt) {
        $clientName = $appt['client_name'];
        $email = $appt['email'];
        $time = date('g:i A', strtotime($appt['preferred_time']));
        $formattedDate = date('F j, Y', strtotime($appt['preferred_date']));
        
        $servicesArr = json_decode($appt['services'], true);
        $serviceList = is_array($servicesArr) ? implode(', ', $servicesArr) : $appt['services'];

        $subject = "Can’t wait to see you tomorrow! ✨ (Appointment Reminder)";
        $message = "
<html>
<body style='font-family: sans-serif; color: #333;'>
<p>Dear $clientName,</p>

<p>This is a friendly reminder that your appointment at The Heaven Nails is coming up tomorrow! We are getting everything ready to give your nails the perfect look.</p>

<p><strong>Your Appointment Details:</strong></p>

<p>
Service: $serviceList<br>
📅 Date: $formattedDate<br>
🕒 Time: $time<br>
📍 Location: The Heaven Nails, Shree Ram Society 5-A, Rajkot. (<a href='$googleMapsLink'>View on Map</a>)
</p>

<p><strong>⚠️ A Quick Note:</strong></p>

<ul>
<li><strong>Running Late?</strong> Please let us know! If you are more than 15 minutes late, we may need to reschedule to keep our day running smoothly for everyone.</li>
<li><strong>Gel Removal:</strong> If you have existing gel polish or extensions that need removal, please reply to this email so we can allocate extra time (if not already booked).</li>
</ul>

<p>Need to reschedule? Please call or WhatsApp us immediately at " . SALON_PHONE . ".</p>

<p>See you soon,</p>

<p>The Heaven Nails Team</p>
</body>
</html>
";

        if (sendMail($email, $subject, $message)) {
            echo "Reminder sent to: $clientName ($email)\n";
            $count++;
        } else {
            echo "Failed to send reminder to: $clientName ($email)\n";
        }
    }

    echo "Total reminders sent: $count\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
