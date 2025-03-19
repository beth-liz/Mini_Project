<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Past date

// Add session validation
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    // Clear any existing session data
    session_unset();
    session_destroy();
    
    // Redirect with no-cache headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Location: signin.php");
    exit();
}

// Add session timeout check (optional but recommended)
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: signin.php");
    exit();
}
$_SESSION['last_activity'] = time();

require_once 'db_connect.php';

$activeSection = 'events';

// Initialize the $events variable
$events = []; // Ensure $events is defined

function getEvents() {
    global $conn;
    $sql = "SELECT e.*, a.activity_type 
            FROM events e
            JOIN activity a ON e.activity_id = a.activity_id
            ORDER BY e.event_date DESC, e.event_time ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add this after getEvents() function
function getActivities() {
    global $conn;
    $sql = "SELECT activity_id, activity_type FROM activity ORDER BY activity_type";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$activities = getActivities();

// Add this for handling form validation and error messages
$error_message = '';
$success_message = '';

// Modify the existing POST handler with validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $activity_id = $_POST['activity_id'];
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $event_location = trim($_POST['event_location']);
    $max_participants = $_POST['max_participants'];
    $event_age_limit = $_POST['event_age_limit'];
    $event_price = $_POST['event_price'];
    $event_image = null; // Initialize the event image variable

    // Validation
    if (empty($activity_id)) {
        $error_message = "Please select an activity.";
    } elseif (empty($event_title)) {
        $error_message = "Event title cannot be empty.";
    } elseif (empty($event_description)) {
        $error_message = "Event description cannot be empty.";
    } elseif (empty($event_date)) {
        $error_message = "Please select a date.";
    } elseif (empty($event_time)) {
        $error_message = "Please select a time.";
    } elseif (empty($event_location)) {
        $error_message = "Event location cannot be empty.";
    } elseif (!is_numeric($max_participants) || $max_participants <= 0) {
        $error_message = "Maximum participants must be a positive number.";
    } elseif (!is_numeric($event_age_limit) || $event_age_limit < 0) {
        $error_message = "Age limit must be a non-negative number.";
    } elseif (!is_numeric($event_price) || $event_price < 0) {
        $error_message = "Price must be a non-negative number.";
    } elseif (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $fileType = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileType), ['png', 'jpg', 'jpeg'])) {
            $error_message = "Image must be a PNG, JPG, or JPEG file.";
        } else {
            // Handle file upload
            $uploadDir = 'uploads/'; // Directory where images will be stored
            $event_image = $uploadDir . basename($_FILES['event_image']['name']);
            if (!move_uploaded_file($_FILES['event_image']['tmp_name'], $event_image)) {
                $error_message = "Failed to upload image.";
            }
        }
    } else {
        $error_message = "Image is required.";
    }

    if (empty($error_message)) {
        try {
            // Debugging: Log the values being inserted
            error_log("Inserting event with values: " . json_encode([
                'activity_id' => $activity_id,
                'event_title' => $event_title,
                'event_description' => $event_description,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'event_location' => $event_location,
                'max_participants' => $max_participants,
                'event_age_limit' => $event_age_limit,
                'event_price' => $event_price,
                'event_image' => $event_image
            ]));

            $sql = "INSERT INTO events (activity_id, event_title, event_description, event_date, 
                    event_time, event_location, max_participants, event_age_limit, event_price, event_image) 
                    VALUES (:activity_id, :event_title, :event_description, :event_date, 
                    :event_time, :event_location, :max_participants, :event_age_limit, :event_price, :event_image)";
            $stmt = $conn->prepare($sql);
            
            // Execute the statement
            $stmt->execute([
                'activity_id' => $activity_id,
                'event_title' => $event_title,
                'event_description' => $event_description,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'event_location' => $event_location,
                'max_participants' => $max_participants,
                'event_age_limit' => $event_age_limit,
                'event_price' => $event_price,
                'event_image' => $event_image // Include the image path
            ]);

            // Check if the row was inserted
            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "Event added successfully!";
            } else {
                error_log("No rows inserted. Check if the data is valid.");
                $_SESSION['error_message'] = "Event could not be added. Please check the data.";
            }

            // Redirect to the same page to prevent resubmission
            header("Location: manager_events.php");
            exit();
        } catch (PDOException $e) {
            // Log the error message with SQLSTATE code
            error_log("Error inserting event: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            header("Location: manager_events.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = $error_message;
        header("Location: manager_events.php");
        exit();
    }
}

// Add these handlers for edit and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
    $event_id = $_POST['event_id'];
    $activity_id = trim($_POST['activity_id']);
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = trim($_POST['event_date']);
    $event_time = trim($_POST['event_time']);
    $event_location = trim($_POST['event_location']);
    $max_participants = intval($_POST['max_participants']);
    $event_age_limit = intval($_POST['event_age_limit']);
    $event_price = floatval($_POST['event_price']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($activity_id)) {
        $errors[] = "Activity is required";
    }
    
    if (empty($event_title)) {
        $errors[] = "Event title is required";
    }
    
    if (empty($event_description)) {
        $errors[] = "Event description is required";
    }
    
    if (empty($event_date)) {
        $errors[] = "Event date is required";
    }
    
    if (empty($event_time)) {
        $errors[] = "Event time is required";
    }
    
    if (empty($event_location)) {
        $errors[] = "Event location is required";
    }
    
    if ($max_participants <= 0) {
        $errors[] = "Maximum participants must be greater than 0";
    }
    
    if ($event_age_limit < 0) {
        $errors[] = "Age limit cannot be negative";
    }
    
    if ($event_price < 0) {
        $errors[] = "Price cannot be negative";
    }
    
    // If no errors, update the event
    if (empty($errors)) {
        try {
            // Get current event image
            $getCurrentImageSql = "SELECT event_image FROM events WHERE event_id = :event_id";
            $getCurrentImageStmt = $conn->prepare($getCurrentImageSql);
            $getCurrentImageStmt->execute(['event_id' => $event_id]);
            $currentEventData = $getCurrentImageStmt->fetch(PDO::FETCH_ASSOC);
            $current_image = $currentEventData['event_image'];
            
            // Check if a new image is uploaded
            $event_image = $current_image; // Default to current image
            
            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK && $_FILES['event_image']['size'] > 0) {
                $fileType = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                if (!in_array(strtolower($fileType), ['png', 'jpg', 'jpeg'])) {
                    $errors[] = "Image must be a PNG, JPG, or JPEG file.";
                } else {
                    // Handle file upload
                    $uploadDir = 'uploads/'; // Directory where images will be stored
                    
                    // Make sure the directory exists
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Generate a unique filename to avoid overwriting
                    $fileName = uniqid() . '_' . basename($_FILES['event_image']['name']);
                    $event_image = $uploadDir . $fileName;
                    
                    if (!move_uploaded_file($_FILES['event_image']['tmp_name'], $event_image)) {
                        $errors[] = "Failed to upload image.";
                    }
                }
            }
            
            if (empty($errors)) {
                // Update SQL statement
                $sql = "UPDATE events 
                        SET activity_id = :activity_id, 
                            event_title = :event_title, 
                            event_description = :event_description, 
                            event_date = :event_date, 
                            event_time = :event_time, 
                            event_location = :event_location, 
                            max_participants = :max_participants, 
                            event_age_limit = :event_age_limit, 
                            event_price = :event_price,
                            event_image = :event_image
                        WHERE event_id = :event_id";
                
                $params = [
                    'event_id' => $event_id,
                    'activity_id' => $activity_id,
                    'event_title' => $event_title,
                    'event_description' => $event_description,
                    'event_date' => $event_date,
                    'event_time' => $event_time,
                    'event_location' => $event_location,
                    'max_participants' => $max_participants,
                    'event_age_limit' => $event_age_limit,
                    'event_price' => $event_price,
                    'event_image' => $event_image
                ];
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['success_message'] = "Event updated successfully!";
                header("Location: manager_events.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Please correct the following errors: " . implode(", ", $errors);
                header("Location: manager_events.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            header("Location: manager_events.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Please correct the following errors: " . implode(", ", $errors);
        header("Location: manager_events.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    
    try {
        $sql = "DELETE FROM events WHERE event_id = :event_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['event_id' => $event_id]);
        
        $_SESSION['success_message'] = "Event deleted successfully!";
        header("Location: manager_events.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: manager_events.php");
        exit();
    }
}

// Fetch events after handling any POST requests
$events = getEvents(); // Ensure events are fetched

// Function to get event details for editing
function getEventById($event_id) {
    global $conn;
    $sql = "SELECT * FROM events WHERE event_id = :event_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if edit request is made
$edit_mode = false;
$event_to_edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_mode = true;
    $event_to_edit = getEventById($_GET['edit']);
    if (!$event_to_edit) {
        $error_message = "Event not found!";
        $edit_mode = false;
    }
}
// Edit event handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
    $event_id = $_POST['event_id'];
    $activity_id = trim($_POST['activity_id']);
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = trim($_POST['event_date']);
    $event_time = trim($_POST['event_time']);
    $event_location = trim($_POST['event_location']);
    $max_participants = intval($_POST['max_participants']);
    $event_age_limit = intval($_POST['event_age_limit']);
    $event_price = floatval($_POST['event_price']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($activity_id)) {
        $errors[] = "Activity is required";
    }
    
    if (empty($event_title)) {
        $errors[] = "Event title is required";
    }
    
    if (empty($event_description)) {
        $errors[] = "Event description is required";
    }
    
    if (empty($event_date)) {
        $errors[] = "Event date is required";
    }
    
    if (empty($event_time)) {
        $errors[] = "Event time is required";
    }
    
    if (empty($event_location)) {
        $errors[] = "Event location is required";
    }
    
    if ($max_participants <= 0) {
        $errors[] = "Maximum participants must be greater than 0";
    }
    
    if ($event_age_limit < 0) {
        $errors[] = "Age limit cannot be negative";
    }
    
    if ($event_price < 0) {
        $errors[] = "Price cannot be negative";
    }
    
    // If no errors, update the event
    if (empty($errors)) {
        try {
            // Get current event image
            $getCurrentImageSql = "SELECT event_image FROM events WHERE event_id = :event_id";
            $getCurrentImageStmt = $conn->prepare($getCurrentImageSql);
            $getCurrentImageStmt->execute(['event_id' => $event_id]);
            $currentEventData = $getCurrentImageStmt->fetch(PDO::FETCH_ASSOC);
            $current_image = $currentEventData['event_image'];
            
            // Check if a new image is uploaded
            $event_image = $current_image; // Default to current image
            
            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK && $_FILES['event_image']['size'] > 0) {
                $fileType = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                if (!in_array(strtolower($fileType), ['png', 'jpg', 'jpeg'])) {
                    $errors[] = "Image must be a PNG, JPG, or JPEG file.";
                } else {
                    // Handle file upload
                    $uploadDir = 'uploads/'; // Directory where images will be stored
                    
                    // Make sure the directory exists
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Generate a unique filename to avoid overwriting
                    $fileName = uniqid() . '_' . basename($_FILES['event_image']['name']);
                    $event_image = $uploadDir . $fileName;
                    
                    if (!move_uploaded_file($_FILES['event_image']['tmp_name'], $event_image)) {
                        $errors[] = "Failed to upload image.";
                    }
                }
            }
            
            if (empty($errors)) {
                // Update SQL statement
                $sql = "UPDATE events 
                        SET activity_id = :activity_id, 
                            event_title = :event_title, 
                            event_description = :event_description, 
                            event_date = :event_date, 
                            event_time = :event_time, 
                            event_location = :event_location, 
                            max_participants = :max_participants, 
                            event_age_limit = :event_age_limit, 
                            event_price = :event_price,
                            event_image = :event_image
                        WHERE event_id = :event_id";
                
                $params = [
                    'event_id' => $event_id,
                    'activity_id' => $activity_id,
                    'event_title' => $event_title,
                    'event_description' => $event_description,
                    'event_date' => $event_date,
                    'event_time' => $event_time,
                    'event_location' => $event_location,
                    'max_participants' => $max_participants,
                    'event_age_limit' => $event_age_limit,
                    'event_price' => $event_price,
                    'event_image' => $event_image
                ];
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['success_message'] = "Event updated successfully!";
                header("Location: manager_events.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Please correct the following errors: " . implode(", ", $errors);
                header("Location: manager_events.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            header("Location: manager_events.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Please correct the following errors: " . implode(", ", $errors);
        header("Location: manager_events.php");
        exit();
    }
}

// Display messages after redirect
$message = '';
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
} elseif (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the message after displaying
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard - Events</title>
    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: rgba(255, 255, 255, 0.2);
            --background-light: rgba(76, 132, 196, 0.15);
            --text-dark: #ffffff;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Unna', serif;
            background-image: url('img/log.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }

        .sidebar {
            width: 250px;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 2;
            padding: 20px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .sidebar-logo {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            font-family: 'Cinzel Decorative', cursive;
            margin-bottom: 30px;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav-item {
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }

        .sidebar-nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-nav-item.active {
            background-color: var(--primary-color);
            color: white;
        }

        .sidebar-nav-item a {
            display: block;
            color: white;
            text-decoration: none;
            width: 100%;
            height: 100%;
        }

        .dashboard-content {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            z-index: 1;
            position: relative;
            margin-left: 250px;
            padding-left: 20px;
            width: calc(100% - 250px);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            overflow: hidden;
            min-width: 800px;
        }

        .data-table th, .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--background-light);
            color: white;
        }

        .data-table th {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: bold;
        }

        .btn {
            padding: 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-family: 'Unna', serif;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications {
            position: relative;
            cursor: pointer;
        }

        .notifications-icon {
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .notifications-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 15px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid white;
            transition: all 0.3s ease;
            color: white;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .user-profile:hover .user-avatar {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-edit {
            background-color: var(--warning-color);
            color: white;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 5px;
            z-index: 1;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-item {
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            color: white;
            transition: background-color 0.3s;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .dashboard-section {
            display: block;
        }

        .section-content {
            padding: 20px;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            margin: 20px 0;
            width: 100%;
            overflow-x: auto;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }

        .modal-content {
            background: rgba(76, 132, 196, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            color: white;
            font-family: 'Unna', serif;
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .form-group-full {
            grid-column: span 2;
        }

        .form-actions {
            grid-column: span 2;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: white;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.2);
            border: 1px solid var(--success-color);
        }

        .alert-error {
            background-color: rgba(231, 76, 60, 0.2);
            border: 1px solid var(--danger-color);
        }

        /* Style for select options */
        select.form-control option {
            background: #4c84c4;
            color: white;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">Manager Dashboard</div>
        <nav>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item"><a href="manager_dashboard.php">Overview</a></li>
                <li class="sidebar-nav-item"><a href="manager_user.php">Users</a></li>
                <li class="sidebar-nav-item"><a href="manager_activities.php">Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_membership.php">Membership</a></li>
                <li class="sidebar-nav-item"><a href="manager_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="manager_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item active"><a href="manager_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="manager_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div id="events-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>Event Management</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" id="openAddEventModal">Add New Event</button>
                    <div class="notifications">
                        <span class="notifications-icon">🔔</span>
                        <span class="notifications-badge">3</span>
                    </div>
                    <div class="dropdown">
                        <div class="user-profile">
                            <div class="user-avatar">MG</div>
                            <span>Manager</span>
                        </div>
                        <div class="dropdown-content">
                            <a href="logout.php" class="dropdown-item">Log Out</a>
                        </div>
                    </div>
                </div>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

    <!-- Add Event Modal -->
    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><?php echo $edit_mode ? 'Edit Event' : 'Add New Event'; ?></h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="activity_id">Activity</label>
                        <select id="activity_id" name="activity_id" class="form-control" required>
                            <option value="">Select Activity</option>
                            <?php foreach ($activities as $activity): ?>
                                <option value="<?php echo $activity['activity_id']; ?>" 
                                    <?php echo $edit_mode && $event_to_edit['activity_id'] == $activity['activity_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($activity['activity_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_title">Event Title</label>
                        <input type="text" id="event_title" name="event_title" class="form-control" 
                            value="<?php echo $edit_mode ? htmlspecialchars($event_to_edit['event_title']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label for="event_description">Event Description</label>
                        <textarea id="event_description" name="event_description" class="form-control" required><?php echo $edit_mode ? htmlspecialchars($event_to_edit['event_description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_date">Event Date</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" 
                            value="<?php echo $edit_mode ? $event_to_edit['event_date'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_time">Event Time</label>
                        <input type="time" id="event_time" name="event_time" class="form-control" 
                            value="<?php echo $edit_mode ? $event_to_edit['event_time'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_location">Event Location</label>
                        <input type="text" id="event_location" name="event_location" class="form-control" 
                            value="<?php echo $edit_mode ? htmlspecialchars($event_to_edit['event_location']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_participants">Maximum Participants</label>
                        <input type="number" id="max_participants" name="max_participants" class="form-control" 
                            value="<?php echo $edit_mode ? $event_to_edit['max_participants'] : ''; ?>" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_age_limit">Age Limit</label>
                        <input type="number" id="event_age_limit" name="event_age_limit" class="form-control" 
                            value="<?php echo $edit_mode ? $event_to_edit['event_age_limit'] : ''; ?>" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_price">Price (₹)</label>
                        <input type="number" id="event_price" name="event_price" class="form-control" 
                            value="<?php echo $edit_mode ? $event_to_edit['event_price'] : ''; ?>" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_image">Event Image (Optional - leave empty to keep current image)</label>
                        <input type="file" id="event_image" name="event_image" class="form-control" accept=".png, .jpg, .jpeg">
                        <?php if ($edit_mode && !empty($event_to_edit['event_image'])): ?>
                            <div>
                                <img src="<?php echo htmlspecialchars($event_to_edit['event_image']); ?>" alt="Current Image" style="width: 100px; height: auto; margin-top: 10px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
    <?php if ($edit_mode): ?>
        <input type="hidden" name="edit_event" value="1">
        <input type="hidden" name="event_id" value="<?php echo $event_to_edit['event_id']; ?>">
    <?php else: ?>
        <input type="hidden" name="add_event" value="1">
    <?php endif; ?>
    <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Update Event' : 'Save Event'; ?></button>
    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
</div>
                </div>
            </form>
        </div>
    </div>

            <section class="section-content">
                <!-- Events table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Activity</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Participants</th>
                            <th>Age Limit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?php echo $event['event_id']; ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($event['event_image']); ?>" alt="<?php echo htmlspecialchars($event['event_title']); ?>" style="width: 100px; height: auto;">
                                    </td>
                                    <td><?php echo htmlspecialchars($event['event_title']); ?></td>
                                    <td><?php echo htmlspecialchars($event['activity_type']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($event['event_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['event_location']); ?></td>
                                    <td>₹<?php echo number_format($event['event_price'], 2); ?></td>
                                    <td><?php echo $event['max_participants']; ?></td>
                                    <td><?php echo $event['event_age_limit']; ?>+</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-edit edit-event-btn" 
                                                data-id="<?php echo $event['event_id']; ?>"
                                                data-activity="<?php echo $event['activity_id']; ?>"
                                                data-title="<?php echo htmlspecialchars($event['event_title']); ?>"
                                                data-description="<?php echo htmlspecialchars($event['event_description']); ?>"
                                                data-date="<?php echo date('Y-m-d', strtotime($event['event_date'])); ?>"
                                                data-time="<?php echo date('H:i', strtotime($event['event_time'])); ?>"
                                                data-location="<?php echo htmlspecialchars($event['event_location']); ?>"
                                                data-max="<?php echo $event['max_participants']; ?>"
                                                data-age="<?php echo $event['event_age_limit']; ?>"
                                                data-price="<?php echo $event['event_price']; ?>">
                                                Edit
                                            </button>
                                            <button class="btn btn-danger delete-event-btn" 
                                                data-id="<?php echo $event['event_id']; ?>"
                                                data-title="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center;">No events found</td>
                            </tr>
            <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <script>
        // Get the modal
    var modal = document.getElementById("addEventModal");

    // Get the button that opens the modal
    var btn = document.getElementById("openAddEventModal");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks the button, open the modal 
    btn.onclick = function() {
      modal.style.display = "block";
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
      modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Add event listeners for edit buttons
document.querySelectorAll('.edit-event-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        // Get the data from data attributes
        var modal = document.getElementById("addEventModal");
        var form = modal.querySelector("form");
        
        // Update modal title to indicate edit mode
        modal.querySelector("h2").textContent = "Edit Event";
        
        // Get the event ID
        var eventId = this.getAttribute('data-id');
        
        // Set form values from button's data attributes
        form.querySelector('select[name="activity_id"]').value = this.getAttribute('data-activity');
        form.querySelector('input[name="event_title"]').value = this.getAttribute('data-title');
        form.querySelector('textarea[name="event_description"]').value = this.getAttribute('data-description');
        form.querySelector('input[name="event_date"]').value = this.getAttribute('data-date');
        form.querySelector('input[name="event_time"]').value = this.getAttribute('data-time');
        form.querySelector('input[name="event_location"]').value = this.getAttribute('data-location');
        form.querySelector('input[name="max_participants"]').value = this.getAttribute('data-max');
        form.querySelector('input[name="event_age_limit"]').value = this.getAttribute('data-age');
        form.querySelector('input[name="event_price"]').value = this.getAttribute('data-price');
        
        // Change image input to be optional
        var imageInput = form.querySelector('input[name="event_image"]');
        var imageLabel = imageInput.previousElementSibling;
        imageLabel.textContent = "Event Image (Optional - leave empty to keep current image)";
        
        // Get the current image from the table
        var currentImageSrc = this.closest('tr').querySelector('img').src;
        
        // Create container for displaying current image
        var currentImageContainer = document.createElement('div');
        currentImageContainer.className = 'current-image-container';
        currentImageContainer.style.marginTop = '10px';
        
        // Create the image element
        var currentImage = document.createElement('img');
        currentImage.src = currentImageSrc;
        currentImage.style.maxWidth = '100px';
        currentImage.style.marginTop = '5px';
        
        // Add a label
        var imageInfoText = document.createElement('p');
        imageInfoText.textContent = 'Current Image:';
        imageInfoText.style.marginBottom = '5px';
        
        // Add elements to container
        currentImageContainer.appendChild(imageInfoText);
        currentImageContainer.appendChild(currentImage);
        
        // Remove any existing current image container
        var existingContainer = form.querySelector('.current-image-container');
        if (existingContainer) {
            existingContainer.remove();
        }
        
        // Insert after the file input field
        imageInput.parentNode.appendChild(currentImageContainer);
        
        // Change form to edit mode
        // Remove existing hidden inputs if any
        var existingHiddenInputs = form.querySelectorAll('input[name="event_id"], input[name="edit_event"], input[name="add_event"]');
        existingHiddenInputs.forEach(function(input) {
            input.remove();
        });
        
        // Add new hidden inputs
        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'event_id';
        hiddenInput.value = eventId;
        form.appendChild(hiddenInput);
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'edit_event';
        actionInput.value = '1';
        form.appendChild(actionInput);
        
        // Change button text
        form.querySelector('button[type="submit"]').textContent = 'Update Event';
        
        // Show the modal
        modal.style.display = "block";
    });
});

        // Add event listeners for delete buttons
        document.querySelectorAll('.delete-event-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete "' + this.getAttribute('data-title') + '"?')) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'event_id';
                    input.value = this.getAttribute('data-id');
                    form.appendChild(input);
                    
                    var submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'delete_event';
                    submitInput.value = '1';
                    form.appendChild(submitInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Close modal with cancel button
document.querySelectorAll('.modal-close').forEach(function(button) {
    button.addEventListener('click', function() {
        var modal = document.getElementById("addEventModal");
        modal.style.display = "none";
        
        // Reset the form when cancelling
        var form = modal.querySelector("form");
        form.reset();
        
        // Remove any existing hidden inputs for edit mode
        var existingHiddenInputs = form.querySelectorAll('input[name="event_id"], input[name="edit_event"]');
        existingHiddenInputs.forEach(function(input) {
            input.remove();
        });
        
        // Reset button text and form title
        form.querySelector('button[type="submit"]').textContent = 'Save Event';
        modal.querySelector("h2").textContent = "Add New Event";
        
        // Reset image label
        var imageInput = form.querySelector('input[name="event_image"]');
        var imageLabel = imageInput.previousElementSibling;
        imageLabel.textContent = "Event Image (PNG, JPG, JPEG)";
        
        // Remove current image display if exists
        var existingContainer = form.querySelector('.current-image-container');
        if (existingContainer) {
            existingContainer.remove();
        }
        
        // Add back the add_event hidden input
        var addEventInput = document.createElement('input');
        addEventInput.type = 'hidden';
        addEventInput.name = 'add_event';
        addEventInput.value = '1';
        form.appendChild(addEventInput);
    });
});
    </script>
</body>
</html>