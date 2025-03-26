<?php
session_start();
require_once 'db_connect.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['email']) || !isset($_POST['process_payment'])) {
    header('Location: signin.php');
    exit;
}

try {
    $conn->beginTransaction();

    // Get user details
    $stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($_POST['booking_type'] === 'recurring') {
        // Insert into recurring_bookings
        $stmt = $conn->prepare("INSERT INTO recurring_bookings (user_id, sub_activity_id, start_date, end_date, 
                               booking_time, selected_days) VALUES (?, ?, ?, ?, ?, ?)");
        
        $start_date = $_POST['start_date'];
        $weeks = (int)$_POST['weeks'];
        $end_date = date('Y-m-d', strtotime($start_date . " + {$weeks} weeks - 1 day"));
        $booking_time = explode('|', $_POST['booking_time'])[0];
        
        $stmt->execute([
            $user['user_id'],
            $_POST['activity_id'],
            $start_date,
            $end_date,
            $booking_time,
            $_POST['selected_days']
        ]);

        $recurring_id = $conn->lastInsertId();

        // Process individual slots
        $selected_days = json_decode($_POST['selected_days']);
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);
        $booking_ids = [];

        while ($current_date <= $end_date_obj) {
            $day_of_week = $current_date->format('w'); // 0 (Sunday) to 6 (Saturday)
            
            if (in_array($day_of_week, $selected_days)) {
                // Find matching timeslot
                $stmt = $conn->prepare("SELECT slot_id FROM timeslots 
                                      WHERE sub_activity_id = ? 
                                      AND slot_date = ? 
                                      AND slot_start_time = ?");
                $stmt->execute([
                    $_POST['activity_id'],
                    $current_date->format('Y-m-d'),
                    $booking_time
                ]);
                $timeslot_id = $stmt->fetchColumn();

                if ($timeslot_id) {
                    // Insert recurring slot
                    $stmt = $conn->prepare("INSERT INTO recurring_slots (recurring_id, booking_date, timeslot_id) 
                                          VALUES (?, ?, ?)");
                    $stmt->execute([
                        $recurring_id,
                        $current_date->format('Y-m-d'),
                        $timeslot_id
                    ]);

                    // Update timeslot participants
                    $stmt = $conn->prepare("UPDATE timeslots 
                                          SET current_participants = current_participants + 1,
                                              slot_full = CASE 
                                                  WHEN current_participants + 1 >= max_participants THEN 1 
                                                  ELSE 0 
                                              END 
                                          WHERE slot_id = ?");
                    $stmt->execute([$timeslot_id]);
                }
            }
            $current_date->modify('+1 day');
        }

        // Insert payment record using your existing payment table structure
        $stmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time) 
                               VALUES (?, ?, CURDATE(), CURTIME())");
        $stmt->execute([
            $user['user_id'],
            $_POST['price']
        ]);

        // Create notification
        $stmt = $conn->prepare("INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                               VALUES (?, ?, ?, CURDATE(), CURTIME())");
        $notification_message = "Your recurring booking for {$_POST['activity']} has been confirmed. Booking ID: $recurring_id";
        $stmt->execute([
            $user['user_id'],
            "Recurring Booking Confirmation",
            $notification_message
        ]);

    } else {
        // Handle single booking
        // ... existing single booking code ...
    }

    $conn->commit();
    header('Location: user_bookings.php');
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Payment processing error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred during payment processing. Please try again.";
    header('Location: user_outdoor.php');
    exit;
}
?> 