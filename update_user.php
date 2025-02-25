<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate inputs
        if (empty($_POST['user_id']) || empty($_POST['user_name']) || empty($_POST['user_email']) || 
            empty($_POST['user_mobile']) || empty($_POST['dob'])) {
            throw new Exception('All fields are required');
        }

        $userId = $_POST['user_id'];
        $userName = $_POST['user_name'];
        $userEmail = $_POST['user_email'];
        $userMobile = $_POST['user_mobile'];
        $dob = $_POST['dob'];

        // Update user in database
        $sql = "UPDATE users SET name = :name, email = :email, mobile = :mobile, dob = :dob 
                WHERE user_id = :user_id";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            'name' => $userName,
            'email' => $userEmail,
            'mobile' => $userMobile,
            'dob' => $dob,
            'user_id' => $userId
        ]);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'User updated successfully';
        } else {
            throw new Exception('Failed to update user');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}