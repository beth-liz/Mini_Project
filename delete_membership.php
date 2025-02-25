<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['membership_id'])) {
    try {
        // Directly proceed with deletion since we don't have user_memberships table
        $sql = "DELETE FROM memberships WHERE membership_id = :membership_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['membership_id' => $_POST['membership_id']]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>