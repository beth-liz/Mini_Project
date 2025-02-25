<?php
// Create a new file named: activity_actions.php

session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    switch ($action) {
        case 'delete':
            if (isset($_POST['activity_id'])) {
                try {
                    // First check if there are any sub-activities
                    $checkSql = "SELECT COUNT(*) FROM sub_activity WHERE activity_id = :activity_id";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->execute(['activity_id' => $_POST['activity_id']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        $response['message'] = "Cannot delete activity: Please delete associated sub-activities first.";
                    } else {
                        $sql = "DELETE FROM activity WHERE activity_id = :activity_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute(['activity_id' => $_POST['activity_id']]);
                        
                        if ($stmt->rowCount() > 0) {
                            $response['success'] = true;
                            $response['message'] = "Activity deleted successfully";
                        } else {
                            $response['message'] = "Activity not found";
                        }
                    }
                } catch (PDOException $e) {
                    $response['message'] = "Database error: " . $e->getMessage();
                }
            }
            break;

        case 'update':
            if (isset($_POST['activity_id']) && isset($_POST['activity_type'])) {
                $activity_type = trim($_POST['activity_type']);
                
                // Validation
                if (empty($activity_type)) {
                    $response['message'] = "Activity type cannot be empty";
                } elseif (strlen($activity_type) < 3) {
                    $response['message'] = "Activity type must be at least 3 characters long";
                } elseif (!preg_match("/^[a-zA-Z\s]+$/", $activity_type)) {
                    $response['message'] = "Activity type can only contain letters and spaces";
                } else {
                    try {
                        $sql = "UPDATE activity SET activity_type = :activity_type WHERE activity_id = :activity_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            'activity_type' => $activity_type,
                            'activity_id' => $_POST['activity_id']
                        ]);
                        
                        if ($stmt->rowCount() > 0) {
                            $response['success'] = true;
                            $response['message'] = "Activity updated successfully";
                        } else {
                            $response['message'] = "No changes made or activity not found";
                        }
                    } catch (PDOException $e) {
                        $response['message'] = "Database error: " . $e->getMessage();
                    }
                }
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}