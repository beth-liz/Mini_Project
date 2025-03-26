<?php
session_start();
require_once 'db_connect.php';
require 'vendor/autoload.php';  // Using composer autoload instead of individual requires

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['email']) || !isset($_POST['razorpay_payment_id']) || !isset($_POST['event_id'])) {
    header("Location: user_events.php");
    exit();
}

$payment_id = $_POST['razorpay_payment_id'];
$event_id = $_POST['event_id'];
$amount = $_POST['amount'];
$user_email = $_SESSION['email'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Get user details
    $stmt = $conn->prepare("SELECT user_id, name, mobile FROM users WHERE email = ?");
    $stmt->execute([$user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['user_id'];

    // Get event details
    $stmt = $conn->prepare("SELECT event_title, event_date, event_time, event_location FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    // Generate a unique bill number
    $bill_number = 'BILL' . date('YmdHis') . rand(1000, 9999);
    $bill_date = date('Y-m-d');
    $bill_time = date('H:i:s');

    // Register for the event with bill number
    $stmt = $conn->prepare("INSERT INTO event_registration (event_id, user_id, bill) 
                           VALUES (?, ?, ?)");
    $stmt->execute([$event_id, $user_id, $bill_number]);
    $event_reg_id = $conn->lastInsertId();

    // Insert into payment table
    $stmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time, event_reg_id) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $amount, $bill_date, $bill_time, $event_reg_id]);

    // Create notification
    $stmt = $conn->prepare("INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                           VALUES (?, ?, ?, CURDATE(), CURTIME())");
    $notification_message = "Your registration for {$event['event_title']} has been confirmed. Bill Number: {$bill_number}";
    $stmt->execute([$user_id, "Event Registration Confirmation", $notification_message]);

    // Send Email
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elizabethmaryabraham09@gmail.com';
        $mail->Password = 'xvec mfoh vkhp fabg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX');
        $mail->addAddress($user_email, $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Event Registration Confirmation - ArenaX';

        // Email body with styling
        $emailBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { padding: 20px; }
                    .header { background-color: #00bcd4; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { text-align: center; padding: 20px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Event Registration Confirmation</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$user['name']},</p>
                        <p>Your event registration has been confirmed. Here are the details:</p>
                        <ul>
                            <li>Event: {$event['event_title']}</li>
                            <li>Date: {$event['event_date']}</li>
                            <li>Time: {$event['event_time']}</li>
                            <li>Location: {$event['event_location']}</li>
                            <li>Bill Number: {$bill_number}</li>
                            <li>Amount Paid: ₹{$amount}</li>
                            <li>Contact Number: {$user['mobile']}</li>
                        </ul>
                        <p>Thank you for choosing ArenaX!</p>
                    </div>
                    <div class='footer'>
                        <p>For any queries, please contact: arenax@gmail.com</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody);

        $mail->send();

        // Commit transaction
        $conn->commit();

        // Store success message and redirect
        $_SESSION['success_message'] = "Payment successful! You have been registered for " . $event['event_title'] . ". A confirmation email has been sent to your email address.";
        header("Location: user_bookings.php");
        exit();

    } catch (Exception $e) {
        // Email sending failed
        $conn->rollBack();
        $_SESSION['error_message'] = "Registration successful but email could not be sent. Please contact support.";
        header("Location: user_events.php");
        exit();
    }

} catch (Exception $e) {
    // Transaction failed
    $conn->rollBack();
    $_SESSION['error_message'] = "Registration failed. Please try again.";
    header("Location: user_events.php");
    exit();
}
?> 