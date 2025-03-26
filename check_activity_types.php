<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'arenax');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check for existing activity types
$activity_query = "SELECT activity_id, activity_type FROM activity";
$result = $conn->query($activity_query);

$event_exists = false;
echo "<h2>Activity Types in Database:</h2>";
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>ID: " . $row['activity_id'] . " - Type: " . $row['activity_type'] . "</li>";
    if ($row['activity_type'] == 'Event') {
        $event_exists = true;
    }
}
echo "</ul>";

// Add Event type if it doesn't exist
if (!$event_exists) {
    $insert = "INSERT INTO activity (activity_type) VALUES ('Event')";
    if ($conn->query($insert) === TRUE) {
        echo "<p>Added 'Event' activity type</p>";
    } else {
        echo "<p>Error adding 'Event' activity type: " . $conn->error . "</p>";
    }
}

$conn->close();
?> 