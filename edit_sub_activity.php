<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized access');
}

// Handle GET request to fetch sub-activity details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM sub_activity WHERE sub_activity_id = ?");
        $stmt->execute([$_GET['id']]);
        $subActivity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subActivity) {
            echo json_encode(['success' => true, 'data' => $subActivity]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sub-activity not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Handle POST request to update sub-activity
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sub_activity_id = $_POST['sub_activity_id'];
    $sub_activity_name = trim($_POST['sub_activity_name']);
    $activity_id = $_POST['activity_id'];
    $sub_activity_price = $_POST['sub_activity_price'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        if (isset($_FILES['sub_activity_image']) && $_FILES['sub_activity_image']['size'] > 0) {
            // Handle new image upload
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($_FILES['sub_activity_image']['type'], $allowedTypes)) {
                throw new Exception('Invalid image type');
            }
            
            $uploadDir = 'uploads/';
            $fileExtension = strtolower(pathinfo($_FILES['sub_activity_image']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid('sub_activity_', true) . '.' . $fileExtension;
            $imagePath = $uploadDir . $newFileName;
            
            if (!move_uploaded_file($_FILES['sub_activity_image']['tmp_name'], $imagePath)) {
                throw new Exception('Failed to upload image');
            }
            
            // Update with new image
            $sql = "UPDATE sub_activity SET 
                    activity_id = :activity_id,
                    sub_activity_name = :sub_activity_name,
                    sub_activity_price = :sub_activity_price,
                    sub_activity_image = :sub_activity_image
                    WHERE sub_activity_id = :sub_activity_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'activity_id' => $activity_id,
                'sub_activity_name' => $sub_activity_name,
                'sub_activity_price' => $sub_activity_price,
                'sub_activity_image' => $imagePath,
                'sub_activity_id' => $sub_activity_id
            ]);
        } else {
            // Update without changing image
            $sql = "UPDATE sub_activity SET 
                    activity_id = :activity_id,
                    sub_activity_name = :sub_activity_name,
                    sub_activity_price = :sub_activity_price
                    WHERE sub_activity_id = :sub_activity_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'activity_id' => $activity_id,
                'sub_activity_name' => $sub_activity_name,
                'sub_activity_price' => $sub_activity_price,
                'sub_activity_id' => $sub_activity_id
            ]);
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Sub-activity updated successfully']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}