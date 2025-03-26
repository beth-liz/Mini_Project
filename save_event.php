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
if (empty($data['title']) || empty($data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Title and date are required']);
    exit();
}

// Set default values
$id = $data['id'] ?? null;
$title = $data['title'];
$date = $data['date'];
$time = !empty($data['time']) ? $data['time'] : null;
$description = $data['description'] ?? '';
$reminder_enabled = isset($data['reminder_enabled']) ? (int)$data['reminder_enabled'] : 0;

try {
    // If ID exists, update existing event
    if ($id) {
        // Check if this event belongs to the user
        $checkSQL = "SELECT id FROM user_calendar_events WHERE id = :id AND user_id = :user_id";
        $checkStmt = $conn->prepare($checkSQL);
        $checkStmt->execute(['id' => $id, 'user_id' => $user_id]);
        
        if ($checkStmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Event not found or unauthorized']);
            exit();
        }
        
        // Update the event
        $updateSQL = "UPDATE user_calendar_events 
                     SET title = :title, date = :date, time = :time, 
                     description = :description, reminder_enabled = :reminder_enabled 
                     WHERE id = :id AND user_id = :user_id";
        $stmt = $conn->prepare($updateSQL);
        $stmt->execute([
            'title' => $title,
            'date' => $date,
            'time' => $time,
            'description' => $description,
            'reminder_enabled' => $reminder_enabled,
            'id' => $id,
            'user_id' => $user_id
        ]);
        
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        // Create new event
        $insertSQL = "INSERT INTO user_calendar_events 
                     (user_id, title, date, time, description, event_type, reminder_enabled) 
                     VALUES (:user_id, :title, :date, :time, :description, 'custom', :reminder_enabled)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->execute([
            'user_id' => $user_id,
            'title' => $title,
            'date' => $date,
            'time' => $time,
            'description' => $description,
            'reminder_enabled' => $reminder_enabled
        ]);
        
        $newId = $conn->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 