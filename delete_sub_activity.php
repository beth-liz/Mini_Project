<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_activity_id'])) {
    try {
        // Get the image path before deleting
        $stmt = $conn->prepare("SELECT sub_activity_image FROM sub_activity WHERE sub_activity_id = ?");
        $stmt->execute([$_POST['sub_activity_id']]);
        $subActivity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the record
        $stmt = $conn->prepare("DELETE FROM sub_activity WHERE sub_activity_id = ?");
        $stmt->execute([$_POST['sub_activity_id']]);
        
        // Delete the image file if it exists
        if ($subActivity && file_exists($subActivity['sub_activity_image'])) {
            unlink($subActivity['sub_activity_image']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Sub-activity deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete sub-activity']);
    }
    exit();
}