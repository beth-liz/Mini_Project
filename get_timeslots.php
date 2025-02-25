<?php
session_start();
header('Content-Type: application/json');

$activity_id = $_GET['activity_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$activity_id || !$date) {
    echo json_encode(['error' => 'Activity ID and date are required']);
    exit;
}

// Connect to database
$conn = mysqli_connect("localhost", "root", "", "arenax");
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get available timeslots for the activity and date
$sql = "SELECT t.* 
        FROM timeslots t 
        WHERE t.sub_activity_id = ? 
        AND t.slot_date = ?
        AND t.current_participants < t.max_participants
        AND NOT t.slot_full
        ORDER BY t.slot_start_time";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $activity_id, $date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$timeslots = [];
while ($row = mysqli_fetch_assoc($result)) {
    $timeslots[] = [
        'slot_id' => $row['slot_id'],
        'date' => $row['slot_date'],
        'start_time' => $row['slot_start_time'],
        'end_time' => $row['slot_end_time'],
        'available_spots' => $row['max_participants'] - $row['current_participants']
    ];
}

echo json_encode(['timeslots' => $timeslots]);

mysqli_close($conn);
?>