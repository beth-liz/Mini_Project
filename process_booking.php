<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to make a booking']);
    exit;
}

// Get the user ID from session
$conn = mysqli_connect("localhost", "root", "", "arenax");
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$email = $_SESSION['email'];
$user_query = "SELECT user_id FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Get POST data
$activity_id = $_POST['activity_id'] ?? null;
$slot_id = $_POST['slot_id'] ?? null;
$booking_date = $_POST['date'] ?? null;

if (!$activity_id || !$slot_id || !$booking_date) {
    echo json_encode(['success' => false, 'message' => 'Missing required booking information']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Check if slot is still available
    $check_slot = "SELECT current_participants, max_participants, slot_full 
                   FROM timeslots 
                   WHERE slot_id = ? AND slot_date = ? AND NOT slot_full";
    $stmt = mysqli_prepare($conn, $check_slot);
    mysqli_stmt_bind_param($stmt, "is", $slot_id, $booking_date);
    mysqli_stmt_execute($stmt);
    $slot_result = mysqli_stmt_get_result($stmt);
    $slot = mysqli_fetch_assoc($slot_result);

    if (!$slot || $slot['current_participants'] >= $slot['max_participants']) {
        throw new Exception('This timeslot is no longer available');
    }

    // Create booking
    $create_booking = "INSERT INTO booking (user_id, sub_activity_id, slot_id, booking_date, booking_time) 
                      VALUES (?, ?, ?, CURDATE(), CURTIME())";
    $stmt = mysqli_prepare($conn, $create_booking);
    mysqli_stmt_bind_param($stmt, "iii", $user['user_id'], $activity_id, $slot_id);
    mysqli_stmt_execute($stmt);

    // Update timeslot participants
    $update_slot = "UPDATE timeslots 
                   SET current_participants = current_participants + 1,
                       slot_full = (current_participants + 1 >= max_participants)
                   WHERE slot_id = ?";
    $stmt = mysqli_prepare($conn, $update_slot);
    mysqli_stmt_bind_param($stmt, "i", $slot_id);
    mysqli_stmt_execute($stmt);

    // Create notification
    $create_notification = "INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                          VALUES (?, 'Booking Confirmation', 'Your activity has been booked successfully', CURDATE(), CURTIME())";
    $stmt = mysqli_prepare($conn, $create_notification);
    mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Booking confirmed successfully']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
?>