<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get user ID
$user_email = $_SESSION['email'];
$sql = "SELECT user_id FROM users WHERE email = :email";
$stmt = $conn->prepare($sql);
$stmt->execute(['email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$user_id = $user['user_id'];

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit();
}

$id = $data['id'];

try {
    // Check if this event belongs to the user
    $checkSQL = "SELECT id FROM user_calendar_events WHERE id = :id AND user_id = :user_id";
    $checkStmt = $conn->prepare($checkSQL);
    $checkStmt->execute(['id' => $id, 'user_id' => $user_id]);
    
    if ($checkStmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Event not found or unauthorized']);
        exit();
    }
    
    // Delete the event
    $deleteSQL = "DELETE FROM user_calendar_events WHERE id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($deleteSQL);
    $stmt->execute(['id' => $id, 'user_id' => $user_id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 