<?php
require_once 'config.php';

// Enable verbose debug output
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "Checking OpenSSL extension...\n";
if (!extension_loaded('openssl')) {
    echo "ERROR: OpenSSL extension is NOT loaded!\n";
    echo "Please enable 'extension=openssl' in your php.ini file.\n";
    exit(1);
}
echo "OpenSSL is loaded.\n";

echo "Testing Email sending...\n";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USER: " . SMTP_USER . "\n";
// Mask password for security in output
echo "SMTP_PASS: " . substr(SMTP_PASS, 0, 4) . "********\n";

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = 4; // Enable verbose debug output (level 4)
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = str_replace(' ', '', SMTP_PASS);
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    echo "Testing Port " . SMTP_PORT . " (TLS)...\n";

    // Recipients
    $mail->setFrom(SMTP_USER, 'Debug Test');
    $mail->addAddress(SMTP_USER); // Send to self

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Debug Email Test';
    $mail->Body    = 'This is a test email working <b>correctly</b>';

    $mail->send();
    echo "Message has been sent successfully\n";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}
?>
