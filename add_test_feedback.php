<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_connect.php';

try {
    // Get a random user ID
    $userQuery = "SELECT user_id FROM users LIMIT 1";
    $userStmt = $conn->query($userQuery);
    
    if (!$userStmt || $userStmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No users found in the database']);
        exit();
    }
    
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
    $userId = $userRow['user_id'];
    
    // Get a random activity
    $activityQuery = "SELECT activity_id, activity_type FROM activity LIMIT 1";
    $activityStmt = $conn->query($activityQuery);
    
    if (!$activityStmt || $activityStmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No activities found in the database']);
        exit();
    }
    
    $activityRow = $activityStmt->fetch(PDO::FETCH_ASSOC);
    $activityId = $activityRow['activity_id'];
    $activityType = $activityRow['activity_type'];
    
    // Get a random sub activity
    $subActivityQuery = "SELECT sub_act_id, sub_act_name FROM sub_activity_name WHERE activity_id = ? LIMIT 1";
    $subActivityStmt = $conn->prepare($subActivityQuery);
    $subActivityStmt->execute([$activityId]);
    
    if (!$subActivityStmt || $subActivityStmt->rowCount() === 0) {
        // If no sub-activity found for this activity, get any sub-activity
        $alternativeQuery = "SELECT sub_act_id, sub_act_name FROM sub_activity_name LIMIT 1";
        $alternativeStmt = $conn->query($alternativeQuery);
        
        if (!$alternativeStmt || $alternativeStmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No sub-activities found in the database']);
            exit();
        }
        
        $subActivityRow = $alternativeStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $subActivityRow = $subActivityStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $subActivityId = $subActivityRow['sub_act_id'];
    $activityName = $subActivityRow['sub_act_name'];
    
    // Sample feedback content
    $feedbackContent = "This is a test feedback added for debugging purposes. The facility was great!";
    $rating = rand(1, 5);
    $currentDate = date("Y-m-d");
    $currentTime = date("H:i:s");
    $status = "Pending";
    
    // Insert test feedback
    $insertQuery = "INSERT INTO feedback (user_id, activity_id, activity_type, sub_act_id, activity_name, 
                                         feedback_content, rating, feedback_date, feedback_time, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insertStmt = $conn->prepare($insertQuery);
    $result = $insertStmt->execute([
        $userId, $activityId, $activityType, $subActivityId, $activityName,
        $feedbackContent, $rating, $currentDate, $currentTime, $status
    ]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Test feedback added successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to add test feedback']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 