<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if the request is POST and contains the required parameter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_id'])) {
    require_once 'db_connect.php';
    
    $feedback_id = intval($_POST['feedback_id']);
    
    try {
        // Delete the feedback
        $stmt = $conn->prepare("DELETE FROM feedback WHERE feedback_id = ?");
        $result = $stmt->execute([$feedback_id]);
        
        if ($result) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete feedback']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
}
?> 