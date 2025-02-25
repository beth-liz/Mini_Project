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
        if (!isset($_POST['user_id'])) {
            throw new Exception('User ID is required');
        }

        $userId = $_POST['user_id'];

        // First check if the user exists
        $checkSql = "SELECT user_id FROM users WHERE user_id = :user_id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute(['user_id' => $userId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('User not found');
        }

        // Delete the user
        $sql = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute(['user_id' => $userId]);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'User deleted successfully';
        } else {
            throw new Exception('Failed to delete user');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}