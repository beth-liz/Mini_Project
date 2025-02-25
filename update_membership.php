<?php
//this is the update_membership.php file for the membership updation that is done in the pop up of user_home.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

// Get the membership_id from the POST request
$membership_id = $_POST['membership_id'] ?? null;

if ($membership_id) {
    // Update the membership in the database
    $stmt = $conn->prepare("UPDATE users SET membership_id = ? WHERE user_id = ?");
    $result = $stmt->execute([$membership_id, $_SESSION['user_id']]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Membership updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update membership.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid membership ID.']);
}
?> 