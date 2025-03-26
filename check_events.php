<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'arenax');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check for 'Event' activity type
$eventType = "SELECT activity_id, activity_type FROM activity WHERE activity_type = 'Event'";
$result = $conn->query($eventType);

if ($result->num_rows == 0) {
    echo "<p>ERROR: 'Event' activity type not found in the database.</p>";
    echo "<p>Creating 'Event' activity type...</p>";
    
    $createType = "INSERT INTO activity (activity_type) VALUES ('Event')";
    if ($conn->query($createType) === TRUE) {
        echo "<p>Created 'Event' activity type successfully.</p>";
        $eventActivityId = $conn->insert_id;
    } else {
        echo "<p>Error creating 'Event' activity type: " . $conn->error . "</p>";
        exit;
    }
} else {
    $row = $result->fetch_assoc();
    $eventActivityId = $row['activity_id'];
    echo "<p>Found 'Event' activity type with ID: $eventActivityId</p>";
}

// Check for events
$eventsQuery = "SELECT event_id, event_title FROM events WHERE activity_id = $eventActivityId";
$eventsResult = $conn->query($eventsQuery);

if ($eventsResult->num_rows == 0) {
    echo "<p>No events found in the database with activity_id = $eventActivityId.</p>";
    
    // Add sample events
    echo "<p>Adding sample events...</p>";
    $sampleEvents = [
        "Basketball Tournament",
        "Swimming Competition",
        "Yoga Workshop",
        "Chess Championship"
    ];
    
    foreach ($sampleEvents as $event) {
        $insertEvent = "INSERT INTO events (activity_id, event_title, event_description, event_date, event_time, 
                                           event_location, max_participants, event_age_limit, event_price) 
                        VALUES ($eventActivityId, '$event', 'Sample event', 
                               CURDATE() + INTERVAL 10 DAY, '10:00:00', 
                               'ArenaX', 20, 18, 500.00)";
        
        if ($conn->query($insertEvent) === TRUE) {
            echo "<p>Added event: $event</p>";
        } else {
            echo "<p>Error adding event: " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p>Found " . $eventsResult->num_rows . " events in the database:</p>";
    echo "<ul>";
    while ($event = $eventsResult->fetch_assoc()) {
        echo "<li>" . $event['event_title'] . " (ID: " . $event['event_id'] . ")</li>";
    }
    echo "</ul>";
}

// Test the query used in user_home.php
echo "<p>Testing query from user_home.php:</p>";
$testQuery = "SELECT a.activity_type, e.event_title 
             FROM events e
             JOIN activity a ON e.activity_id = a.activity_id
             ORDER BY e.event_title";
$testResult = $conn->query($testQuery);

echo "<p>Query found " . $testResult->num_rows . " results:</p>";
if ($testResult->num_rows > 0) {
    echo "<ul>";
    while ($row = $testResult->fetch_assoc()) {
        echo "<li>Activity Type: " . $row['activity_type'] . " | Event: " . $row['event_title'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No results found with the test query.</p>";
}

$conn->close();
?> 