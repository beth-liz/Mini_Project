<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get activity ID from query parameter
$activity_id = isset($_GET['activity_id']) ? $_GET['activity_id'] : null;

if (!$activity_id) {
    echo json_encode([]);
    exit();
}

try {
    // Get all sub-activities for the selected activity
    $stmt = $conn->prepare("
        SELECT san.sub_act_id, san.sub_act_name 
        FROM sub_activity_name san
        ORDER BY san.sub_act_name ASC
    ");
    $stmt->execute();
    $subActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($subActivities);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>