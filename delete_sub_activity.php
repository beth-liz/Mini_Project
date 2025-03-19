<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if sub_activity_id is provided
if (!isset($_POST['sub_activity_id']) || empty($_POST['sub_activity_id'])) {
    echo json_encode(['success' => false, 'message' => 'No sub-activity ID provided']);
    exit();
}

$sub_activity_id = $_POST['sub_activity_id'];

try {
    // First, get the image path to delete the file
    $stmt = $conn->prepare("SELECT sub_activity_image FROM sub_activity WHERE sub_activity_id = :id");
    $stmt->execute(['id' => $sub_activity_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['sub_activity_image'])) {
        // Delete the image file if it exists
        $imagePath = $result['sub_activity_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    // Delete the sub-activity from database
    $stmt = $conn->prepare("DELETE FROM sub_activity WHERE sub_activity_id = :id");
    $stmt->execute(['id' => $sub_activity_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Sub-activity deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sub-activity not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>