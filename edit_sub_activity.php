<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    if (isset($_GET['id'])) {
        // If this is an AJAX request for fetching sub-activity details
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    } else {
        // If this is a form submission
        header('Location: signin.php');
    }
    exit();
}

// Handle GET request to fetch sub-activity details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $sub_activity_id = $_GET['id'];

    try {
        // Prepare the SQL statement
        $stmt = $conn->prepare("
            SELECT sa.*, a.activity_type, san.sub_act_name 
            FROM sub_activity sa
            JOIN activity a ON sa.activity_id = a.activity_id
            JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id
            WHERE sa.sub_activity_id = :id
        ");
        $stmt->execute(['id' => $sub_activity_id]);
        $subActivity = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($subActivity) {
            echo json_encode(['success' => true, 'data' => $subActivity]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sub-activity not found']);
        }
    } catch (PDOException $e) {
        // Log the error message for debugging
        error_log('Database error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle POST request to update sub-activity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_activity_id'])) {
    $sub_activity_id = $_POST['sub_activity_id'];
    $activity_id = $_POST['activity_id'];
    $sub_act_id = $_POST['sub_act_id'];
    $sub_activity_price = $_POST['sub_activity_price'];
    $current_image = isset($_POST['current_image']) ? $_POST['current_image'] : '';
    
    $errors = [];
    
    // Validate price
    if (!validatePrice($sub_activity_price)) {
        $errors[] = "Price must be between 0 and 3000";
    }
    
    // Set image path to current image by default
    $imagePath = $current_image;
    
    // Check if a new image is uploaded
    if (!empty($_FILES['sub_activity_image']['name'])) {
        // Validate the new image
        if (!validateImage($_FILES['sub_activity_image'])) {
            $errors[] = "Only JPG, JPEG, and PNG files are allowed";
        } else {
            // Attempt to move the uploaded file
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['sub_activity_image']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid('sub_activity_', true) . '.' . $fileExtension;
            $newImagePath = $uploadDir . $newFileName;

            // Move the uploaded file
            if (!move_uploaded_file($_FILES['sub_activity_image']['tmp_name'], $newImagePath)) {
                $errors[] = "Failed to upload image. Error Code: " . $_FILES['sub_activity_image']['error'];
            } else {
                // Delete old image if exists and it's not the default
                if (!empty($current_image) && file_exists($current_image) && strpos($current_image, 'default') === false) {
                    unlink($current_image);
                }
                $imagePath = $newImagePath;
            }
        }
    }

    if (empty($errors)) {
        // Update sub-activity without sub_activity_name field
        $sql = "UPDATE sub_activity 
                SET activity_id = :activity_id, 
                    sub_act_id = :sub_act_id, 
                    sub_activity_price = :sub_activity_price";
        
        // Only update image if we have a new one
        if ($imagePath !== $current_image || empty($current_image)) {
            $sql .= ", sub_activity_image = :sub_activity_image";
        }
        
        $sql .= " WHERE sub_activity_id = :sub_activity_id";
        
        $stmt = $conn->prepare($sql);
        $params = [
            'activity_id' => $activity_id,
            'sub_act_id' => $sub_act_id,
            'sub_activity_price' => $sub_activity_price,
            'sub_activity_id' => $sub_activity_id
        ];
        
        // Only add image parameter if updated
        if ($imagePath !== $current_image || empty($current_image)) {
            $params['sub_activity_image'] = $imagePath;
        }
        
        $stmt->execute($params);
        
        $_SESSION['sub_activity_success_message'] = "Sub-activity updated successfully!";
    } else {
        $_SESSION['sub_activity_error_message'] = implode(", ", $errors);
    }
    
    // Redirect back to sub-activities page
    header("Location: manager_sub_activities.php");
    exit();
}

// Helper functions
function validatePrice($price) {
    return is_numeric($price) && $price >= 0 && $price <= 3000;
}

function validateImage($image) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    return in_array($image['type'], $allowedTypes);
}

// If it's not a GET or POST request, redirect to sub-activities page
header("Location: manager_sub_activities.php");
exit();
?>