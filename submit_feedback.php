<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'arenax');
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Get form data
    $activity_type = $_POST['activity_type'];
    $activity_name = $_POST['activity_name'];
    $feedback_content = $_POST['feedback_content'];
    $rating = intval($_POST['rating']); // Convert to integer explicitly
    
    // Get user_id from session
    $user_email = $_SESSION['email'];
    $user_query = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo "User not found.<br>";
        exit();
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    
    // Initialize variables
    $activity_id = null;
    $sub_act_id = null;
    
    // Handle feedback differently based on activity type
    if ($activity_type == "Event") {
        // Get a default activity_id for events
        $default_activity_query = "SELECT activity_id FROM activity LIMIT 1";
        $result = $conn->query($default_activity_query);
        $row = $result->fetch_assoc();
        $activity_id = $row['activity_id'];
        
        // For Event feedback, find the event in the events table
        $event_query = "SELECT event_id FROM events WHERE event_title = ?";
        $stmt = $conn->prepare($event_query);
        $stmt->bind_param("s", $activity_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Event doesn't exist in the database
            echo "ERROR: Event '$activity_name' not found in the database.<br>";
            echo "Available events:<br>";
            
            // Show available events for debugging
            $list_events = "SELECT event_id, event_title FROM events ORDER BY event_title";
            $events_result = $conn->query($list_events);
            
            while ($row = $events_result->fetch_assoc()) {
                echo "ID: {$row['event_id']} - Event: {$row['event_title']}<br>";
            }
            exit();
        }
        
        $event_data = $result->fetch_assoc();
        $sub_act_id = $event_data['event_id']; // Use event_id as sub_act_id for events
    } else {
        // For regular activities, get activity_id from activity_type
        $activity_query = "SELECT activity_id FROM activity WHERE activity_type = ?";
        $stmt = $conn->prepare($activity_query);
        $stmt->bind_param("s", $activity_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo "ERROR: Activity type '$activity_type' not found in the database.<br>";
            echo "Available activity types in database:<br>";
            
            // Show available activity types for debugging
            $list_query = "SELECT activity_id, activity_type FROM activity";
            $list_result = $conn->query($list_query);
            while ($row = $list_result->fetch_assoc()) {
                echo "ID: {$row['activity_id']} - Type: {$row['activity_type']}<br>";
            }
            exit();
        }
        
        $activity = $result->fetch_assoc();
        $activity_id = $activity['activity_id'];
        
        // Get sub_act_id from sub_activity_name table
        $sub_act_query = "SELECT sub_act_id FROM sub_activity_name WHERE sub_act_name = ? AND activity_id = ?";
        $stmt = $conn->prepare($sub_act_query);
        $stmt->bind_param("si", $activity_name, $activity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo "ERROR: Sub-activity name '$activity_name' not found for activity type '$activity_type'.<br>";
            echo "Available sub-activities for this activity type:<br>";
            
            // Show available sub-activities for this activity type
            $list_query = "SELECT sub_act_id, sub_act_name FROM sub_activity_name WHERE activity_id = ?";
            $stmt = $conn->prepare($list_query);
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $list_result = $stmt->get_result();
            
            while ($row = $list_result->fetch_assoc()) {
                echo "ID: {$row['sub_act_id']} - Name: {$row['sub_act_name']}<br>";
            }
            exit();
        }
        
        $sub_activity = $result->fetch_assoc();
        $sub_act_id = $sub_activity['sub_act_id'];
    }
    
    // Current date and time
    $current_date = date("Y-m-d");
    $current_time = date("H:i:s");
    
    // ALTERNATE APPROACH: Directly construct and execute the query without bind_param
    // This avoids the issues with bind_param character count
    
    // Escape all values to prevent SQL injection
    $safe_user_id = $conn->real_escape_string($user_id);
    $safe_activity_id = $conn->real_escape_string($activity_id);
    $safe_activity_type = $conn->real_escape_string($activity_type);
    $safe_sub_act_id = $conn->real_escape_string($sub_act_id);
    $safe_activity_name = $conn->real_escape_string($activity_name);
    $safe_feedback_content = $conn->real_escape_string($feedback_content);
    $safe_rating = $conn->real_escape_string($rating);
    $safe_current_date = $conn->real_escape_string($current_date);
    $safe_current_time = $conn->real_escape_string($current_time);
    
    $insert_query = "INSERT INTO feedback 
                    (user_id, activity_id, activity_type, sub_act_id, activity_name, feedback_content, rating, feedback_date, feedback_time) 
                    VALUES 
                    ($safe_user_id, '$safe_activity_id', '$safe_activity_type', $safe_sub_act_id, '$safe_activity_name', '$safe_feedback_content', $safe_rating, '$safe_current_date', '$safe_current_time')";
    
    try {
        if ($conn->query($insert_query)) {
            // Redirect back to user_home.php with success message
            header("Location: user_home.php?feedback=success");
            exit();
        } else {
            echo "Error: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
        // Display the actual error with the parameters for debugging
        echo "<pre>";
        echo "Parameters:\n";
        echo "user_id: $user_id (integer)\n";
        echo "activity_id: $activity_id (integer)\n";
        echo "activity_type: $activity_type (string)\n";
        echo "sub_act_id: $sub_act_id (integer)\n";
        echo "activity_name: $activity_name (string)\n";
        echo "feedback_content: $feedback_content (string)\n";
        echo "rating: $rating (integer)\n";
        echo "current_date: $current_date (string)\n";
        echo "current_time: $current_time (string)\n";
        echo "</pre>";
    }
    
    $conn->close();
}
?> 