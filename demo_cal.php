<?php
session_start();
require_once 'db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function logDebug($message) {
    error_log("[Recurring Booking Debug] " . print_r($message, true));
}

logDebug("Script started");

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    logDebug("User not logged in, redirecting to signin.php");
    header("Location: signin.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logDebug("POST request received");
    
    // Log all POST data
    logDebug("POST data: " . print_r($_POST, true));
    
    // Get user ID
    $user_email = $_SESSION['email'];
    logDebug("User email: " . $user_email);
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$user_email]);
    $user_id = $stmt->fetchColumn();
    
    if (!$user_id) {
        logDebug("User not found in database");
        $_SESSION['error'] = "User not found.";
        header("Location: user_outdoor.php");
        exit();
    }
    
    logDebug("User ID: " . $user_id);
    
    // Get booking details from POST
    $activity_id = $_POST['activity_id'];
    $activity_name = $_POST['activity_name'];
    $booking_type = $_POST['booking_type'] ?? 'single';
    
    logDebug("Activity ID: $activity_id, Name: $activity_name, Booking Type: $booking_type");
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        logDebug("Transaction started");
        
        if ($booking_type === 'single') {
            // Handle single booking
            $booking_date = $_POST['booking_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            
            logDebug("Booking Date: $booking_date, Start Time: $start_time, End Time: $end_time");
            
            // Insert into bookings table
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, sub_activity_id, booking_date, start_time, end_time, booking_status) VALUES (?, ?, ?, ?, ?, 'confirmed')");
            $stmt->execute([$user_id, $activity_id, $booking_date, $start_time, $end_time]);
            
            // Update timeslots table
            $stmt = $conn->prepare("UPDATE timeslots SET current_participants = current_participants + 1 WHERE sub_activity_id = ? AND slot_date = ? AND slot_start_time = ? AND slot_end_time = ?");
            $stmt->execute([$activity_id, $booking_date, $start_time, $end_time]);
            
        } else {
            // Handle recurring booking
            $start_date = $_POST['booking_date'];
            $end_date = $_POST['end_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $booking_time = $_POST['booking_time'];
            $selected_days = $_POST['selected_days'];
            $weeks = intval($_POST['weeks']);

            logDebug([
                'start_date' => $start_date,
                'end_date' => $end_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'booking_time' => $booking_time,
                'selected_days' => $selected_days,
                'weeks' => $weeks
            ]);

            // First, create the recurring booking record
            $stmt = $conn->prepare("INSERT INTO recurring_bookings (
                user_id, 
                sub_activity_id, 
                start_date, 
                end_date, 
                booking_time, 
                selected_days, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            
            $stmt->execute([
                $user_id,
                $activity_id,
                $start_date,
                $end_date,
                $booking_time,
                $selected_days
            ]);

            $recurring_id = $conn->lastInsertId();
            logDebug("Created recurring_booking with ID: $recurring_id");

            // Generate all booking dates and create individual bookings
            $selected_days_array = json_decode($selected_days);
            $start = new DateTime($start_date);
            
            for ($week = 0; $week < $weeks; $week++) {
                foreach ($selected_days_array as $day) {
                    $current_date = clone $start;
                    $current_date->modify("+$week week");
                    
                    // Adjust to the correct day of week
                    $days_to_add = (7 + (intval($day) - $current_date->format('w'))) % 7;
                    $current_date->modify("+$days_to_add day");

                    // Skip if date is in the past
                    if ($current_date < new DateTime()) {
                        logDebug("Skipping past date: " . $current_date->format('Y-m-d'));
                        continue;
                    }

                    // Check if timeslot exists and has availability
                    $stmt = $conn->prepare("SELECT slot_id, current_participants, max_participants 
                        FROM timeslots 
                        WHERE sub_activity_id = ? 
                        AND slot_date = ? 
                        AND slot_start_time = ? 
                        AND slot_end_time = ?");
                    
                    $stmt->execute([
                        $activity_id,
                        $current_date->format('Y-m-d'),
                        $start_time,
                        $end_time
                    ]);
                    
                    $timeslot = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($timeslot && $timeslot['current_participants'] < $timeslot['max_participants']) {
                        $timeslot_id = $timeslot['slot_id'];
                        logDebug("Processing timeslot ID: $timeslot_id for date: " . $current_date->format('Y-m-d'));

                        // Insert into bookings table
                        $stmt = $conn->prepare("INSERT INTO bookings (
                            user_id, 
                            sub_activity_id, 
                            booking_date, 
                            start_time, 
                            end_time, 
                            booking_status, 
                            is_recurring, 
                            recurring_group_id
                        ) VALUES (?, ?, ?, ?, ?, 'confirmed', 1, ?)");
                        
                        $stmt->execute([
                            $user_id,
                            $activity_id,
                            $current_date->format('Y-m-d'),
                            $start_time,
                            $end_time,
                            $recurring_id
                        ]);

                        $booking_id = $conn->lastInsertId();
                        logDebug("Created booking with ID: $booking_id");

                        // Insert into recurring_slots
                        $stmt = $conn->prepare("INSERT INTO recurring_slots (
                            recurring_id, 
                            booking_date, 
                            timeslot_id, 
                            status, 
                            booking_id
                        ) VALUES (?, ?, ?, 'booked', ?)");
                        
                        $stmt->execute([
                            $recurring_id,
                            $current_date->format('Y-m-d'),
                            $timeslot_id,
                            $booking_id
                        ]);

                        // Update timeslots table
                        $stmt = $conn->prepare("UPDATE timeslots 
                            SET current_participants = current_participants + 1 
                            WHERE slot_id = ?");
                        $stmt->execute([$timeslot_id]);

                        logDebug("Successfully processed booking for date: " . $current_date->format('Y-m-d'));
            } else {
                        logDebug("No available timeslot for date: " . $current_date->format('Y-m-d'));
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        logDebug("Transaction committed successfully");
        
        // Set success message and redirect
        $_SESSION['success'] = "Recurring booking confirmed successfully!";
        logDebug("Redirecting to user_bookings.php");
        header("Location: user_bookings.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        logDebug("Error: " . $e->getMessage());
        error_log("Error in process_direct_booking.php: " . $e->getMessage());
        $_SESSION['error'] = "Error processing booking. Please try again.";
        header("Location: user_outdoor.php");
        exit();
    }
} else {
    // If not POST request, redirect back
    logDebug("Not a POST request, redirecting back");
    $_SESSION['error'] = "Invalid request method.";
    header("Location: user_outdoor.php");
    exit();
}
?> 