<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to make a booking']);
    exit;
}

// Store booking details in session
$_SESSION['temp_booking'] = [
    'activity_id' => $_POST['activity_id'],
    'slot_id' => $_POST['slot_id'],
    'date' => $_POST['date']
];

echo json_encode(['success' => true]);
?>