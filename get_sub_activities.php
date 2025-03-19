<?php
require_once 'db_connect.php';

if (isset($_GET['activity_id'])) {
    $activity_id = $_GET['activity_id'];
    
    // Query to get sub-activity names for the selected activity
    $sql = "SELECT * FROM sub_activity_name WHERE activity_id = :activity_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['activity_id' => $activity_id]);
    $sub_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return as JSON
    header('Content-Type: application/json');
    echo json_encode($sub_activities);
} else {
    // Return empty array if no activity_id provided
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>