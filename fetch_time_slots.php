<?php
session_start();
require_once 'db_connect.php'; // Update the path as necessary

if (!isset($_SESSION['email'])) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

if (isset($_GET['sub_activity_id']) && isset($_GET['date'])) {
    $sub_activity_id = $_GET['sub_activity_id'];
    $selected_date = $_GET['date'];
    $current_time = date('H:i:s');

    try {
        $sql = "SELECT * FROM timeslots 
                WHERE sub_activity_id = :sub_activity_id 
                AND slot_date = :selected_date 
                AND (slot_date > CURDATE() 
                    OR (slot_date = CURDATE() AND slot_end_time >= :current_time))
                AND current_participants < max_participants
                ORDER BY slot_start_time ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'sub_activity_id' => $sub_activity_id,
            'selected_date' => $selected_date,
            'current_time' => $current_time
        ]);

        $timeSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug log
        error_log("Fetched time slots: " . json_encode($timeSlots));
        
        header('Content-Type: application/json');
        echo json_encode($timeSlots);
    } catch (PDOException $e) {
        error_log("Error in fetch_time_slots.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
}
?> 