<?php
require_once 'db_connect.php';

if (!isset($_GET['sub_activity_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing sub_activity_id']);
    exit;
}

$sub_activity_id = $_GET['sub_activity_id'];

try {
    // Define default time slots from 6 AM to 4 PM
    $timeSlots = [];
    $startHour = 6; // 6 AM
    $endHour = 16;  // 4 PM

    for ($hour = $startHour; $hour < $endHour; $hour++) {
        $startTime = sprintf('%02d:00:00', $hour);
        $endTime = sprintf('%02d:00:00', $hour + 1);
        
        $timeSlots[] = [
            'slot_start_time' => $startTime,
            'slot_end_time' => $endTime
        ];
    }

    // Check if these slots exist in timeslots table, if not, create them
    foreach ($timeSlots as $slot) {
        $checkSql = "SELECT COUNT(*) FROM timeslots 
                     WHERE sub_activity_id = ? 
                     AND slot_start_time = ? 
                     AND slot_end_time = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->execute([$sub_activity_id, $slot['slot_start_time'], $slot['slot_end_time']]);
        $exists = $stmt->fetchColumn();

        if ($exists == 0) {
            // Create slots for the next 56 days (8 weeks)
            for ($i = 0; $i < 56; $i++) {
                $date = date('Y-m-d', strtotime("+$i days"));
                $insertSql = "INSERT INTO timeslots (sub_activity_id, slot_date, slot_start_time, 
                                                   slot_end_time, max_participants, current_participants) 
                             VALUES (?, ?, ?, ?, 20, 0)";
                $stmt = $conn->prepare($insertSql);
                $stmt->execute([$sub_activity_id, $date, $slot['slot_start_time'], $slot['slot_end_time']]);
            }
        }
    }
    
    echo json_encode($timeSlots);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 