<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['email']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    // Get user_id
    $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $userStmt->execute([$_SESSION['email']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Get event data
    $eventStmt = $conn->prepare("SELECT * FROM user_calendar_events WHERE id = ? AND user_id = ?");
    $eventStmt->execute([$_GET['id'], $user['user_id']]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        echo json_encode(['success' => true, 'event' => $event]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 