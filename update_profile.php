<?php
session_start();
header('Content-Type: application/json');
include('db_connect.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/profile/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        error_log("Failed to create directory: " . $uploadDir);
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Handle image upload
$imageFileName = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_image'];
    
    // Log file upload details
    error_log("Uploaded file details: " . print_r($file, true));
    
    // Validate file size (10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image size must be less than 10MB']);
        exit;
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG and PNG images are allowed']);
        exit;
    }

    // Generate unique filename
    $imageFileName = uniqid() . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $targetPath = $uploadDir . $imageFileName;

    // Check directory permissions
    if (!is_writable($uploadDir)) {
        error_log("Upload directory not writable: " . $uploadDir);
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit;
    }

    // Try to move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $uploadError = error_get_last();
        error_log("Failed to move uploaded file. Error: " . print_r($uploadError, true));
        echo json_encode(['success' => false, 'message' => 'Failed to upload image: ' . $uploadError['message']]);
        exit;
    }

    // Verify file was uploaded
    if (!file_exists($targetPath)) {
        error_log("Failed to verify uploaded file at: " . $targetPath);
        echo json_encode(['success' => false, 'message' => 'Failed to verify uploaded file']);
        exit;
    }

    // Try to delete old profile image
    try {
        $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $oldImage = $stmt->fetchColumn();
        
        if ($oldImage && file_exists($uploadDir . $oldImage)) {
            if (!unlink($uploadDir . $oldImage)) {
                error_log("Failed to delete old image: " . $uploadDir . $oldImage);
            }
        }
    } catch (PDOException $e) {
        error_log("Database error while handling old image: " . $e->getMessage());
    }
}

// Get and validate form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$dob = $_POST['dob'] ?? '';

// Log received data
error_log("Received form data: " . print_r($_POST, true));

// Validate inputs
if (empty($name) || empty($email) || empty($mobile) || empty($dob)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Name validation
if (!preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
    echo json_encode(['success' => false, 'message' => 'Invalid name format']);
    exit;
}

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Mobile validation
if (!preg_match("/^\d{10}$/", $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Invalid mobile number']);
    exit;
}

// Age validation
$dobDate = new DateTime($dob);
$today = new DateTime();
$age = $today->diff($dobDate)->y;
if ($age < 18) {
    echo json_encode(['success' => false, 'message' => 'Must be at least 18 years old']);
    exit;
}

// Check if email already exists for other users
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$stmt->execute([$email, $_SESSION['user_id']]);
if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();

    $sql = "UPDATE users SET name = ?, email = ?, mobile = ?, dob = ?";
    $params = [$name, $email, $mobile, $dob];

    if ($imageFileName) {
        $sql .= ", profile_image = ?";
        $params[] = $imageFileName;
    }

    $sql .= " WHERE user_id = ?";
    $params[] = $_SESSION['user_id'];

    $stmt = $conn->prepare($sql);
    
    // Log the query and parameters
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    $stmt->execute($params);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Database error during update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 