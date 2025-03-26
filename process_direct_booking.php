<?php
session_start();
require_once 'db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function logDebug($message) {
    error_log("[Direct Booking Debug] " . $message);
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
    $email = $_SESSION['email'];
    logDebug("User email: " . $email);
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bindParam(1, $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        logDebug("User not found in database");
        $_SESSION['error'] = "User not found.";
        header("Location: user_outdoor.php");
        exit();
    }
    
    $user_id = $user['user_id'];
    logDebug("User ID: " . $user_id);
    
    // Get booking details from POST
    $sub_activity_id = $_POST['activity_id']; // This is actually sub_activity_id
    $activity_name = $_POST['activity_name'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    logDebug("Sub Activity ID: $sub_activity_id, Name: $activity_name, Date: $booking_date");
    logDebug("Start time: $start_time, End time: $end_time");
    
    // Convert time format if needed (12-hour to 24-hour)
    function convertTo24Hour($time) {
        logDebug("Converting time: $time");
        if (strpos($time, 'AM') !== false || strpos($time, 'PM') !== false) {
            $converted = date("H:i:s", strtotime($time));
            logDebug("Converted to: $converted");
            return $converted;
        }
        logDebug("No conversion needed");
        return $time;
    }
    
    $start_time_24h = convertTo24Hour($start_time);
    $booking_time = $start_time_24h; // Use start time as booking time
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        logDebug("Transaction started");
        
        // First, let's check what timeslots are available for this activity and date
        $check_stmt = $conn->prepare("SELECT slot_id, slot_start_time, slot_end_time FROM timeslots 
                                     WHERE sub_activity_id = ? AND slot_date = ?");
        $check_stmt->bindParam(1, $sub_activity_id);
        $check_stmt->bindParam(2, $booking_date);
        $check_stmt->execute();
        $available_slots = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Available slots for this activity and date: " . print_r($available_slots, true));
        
        // Find the matching timeslot
        $slot_id = null;
        foreach ($available_slots as $slot) {
            $db_start = substr($slot['slot_start_time'], 0, 5); // HH:MM format
            $db_end = substr($slot['slot_end_time'], 0, 5); // HH:MM format
            
            $input_start = substr($start_time_24h, 0, 5);
            
            logDebug("Comparing DB start ($db_start) with Input start ($input_start)");
            
            if ($db_start == $input_start) {
                $slot_id = $slot['slot_id'];
                logDebug("Match found! Slot ID: $slot_id");
                break;
            }
        }
        
        if (!$slot_id) {
            // Try a more flexible approach - just use the first available slot
            if (count($available_slots) > 0) {
                $slot_id = $available_slots[0]['slot_id'];
                logDebug("No exact match found, using first available slot: $slot_id");
            } else {
                throw new Exception("No timeslots available for this activity and date.");
            }
        }
        
        // Generate a bill number (you can customize this as needed)
        $bill = "BILL-" . time() . "-" . $user_id;
        logDebug("Generated bill number: $bill");
        
        // Insert booking record - using the correct table name and column names
        logDebug("Inserting booking record with slot_id: $slot_id");
        $stmt = $conn->prepare("INSERT INTO booking (user_id, sub_activity_id, slot_id, booking_date, booking_time, bill) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $sub_activity_id);
        $stmt->bindParam(3, $slot_id);
        $stmt->bindParam(4, $booking_date);
        $stmt->bindParam(5, $booking_time);
        $stmt->bindParam(6, $bill);
        $stmt->execute();
        $booking_id = $conn->lastInsertId();
        logDebug("Booking inserted with ID: $booking_id");
        
        // Update timeslot current participants
        logDebug("Updating timeslot participants");
        $stmt = $conn->prepare("UPDATE timeslots SET current_participants = current_participants + 1 
                               WHERE slot_id = ?");
        $stmt->bindParam(1, $slot_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        logDebug("Transaction committed successfully");
        
        // Set success message and redirect
        $_SESSION['success'] = "Booking confirmed successfully!";
        logDebug("Redirecting to user_bookings.php");
        header("Location: user_bookings.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        logDebug("Error: " . $e->getMessage());
        $_SESSION['error'] = "Error processing booking: " . $e->getMessage();
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