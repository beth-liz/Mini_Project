<?php
session_start();
require_once 'db_connect.php'; // Update the path as necessary

if (!isset($_SESSION['email'])) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

$sub_activity_id = $_GET['sub_activity_id'] ?? null;
$date = $_GET['date'] ?? null;

if ($sub_activity_id && $date) {
    $sql = "SELECT slot_start_time, slot_end_time 
            FROM timeslots 
            WHERE sub_activity_id = :sub_activity_id AND slot_date = :date";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['sub_activity_id' => $sub_activity_id, 'date' => $date]);
    $timeSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($timeSlots);
} else {
    echo json_encode([]);
}
?> 