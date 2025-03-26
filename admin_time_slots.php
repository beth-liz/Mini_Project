<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Past date

// Add session validation
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Clear any existing session data
    session_unset();
    session_destroy();
    
    // Redirect with no-cache headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Location: signin.php");
    exit();
}

require_once 'db_connect.php';

// Check if time slots need to be generated
$lastGeneration = isset($_SESSION['last_timeslot_generation']) ? $_SESSION['last_timeslot_generation'] : 0;
$currentTime = time();

// Only generate once per day (86400 seconds)
if ($currentTime - $lastGeneration > 86400) {
    generateTimeSlots($conn);
    $_SESSION['last_timeslot_generation'] = $currentTime;
}

function convertTo24Hour($time, $period) {
    // Remove any leading/trailing spaces
    $time = trim($time);
    $period = trim(strtoupper($period));
    
    // Split time into hours and minutes
    list($hours, $minutes) = explode(':', $time);
    
    // Convert to 24-hour format
    if ($period === 'PM' && $hours != 12) {
        $hours = $hours + 12;
    } elseif ($period === 'AM' && $hours == 12) {
        $hours = '00';
    }
    
    // Ensure hours and minutes are two digits
    $hours = str_pad($hours, 2, '0', STR_PAD_LEFT);
    $minutes = str_pad($minutes, 2, '0', STR_PAD_LEFT);
    
    return "$hours:$minutes:00";
}

function convertTo12Hour($time24) {
    $timestamp = strtotime($time24);
    return date('h:i A', $timestamp);
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

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit();
}

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: signin.php');
    exit();
}

$activeSection = 'time-slots';

function getTimeSlots() {
    global $conn;
    $sql = "SELECT ts.*, a.activity_type, ts.current_participants 
            FROM timeslots ts
            JOIN sub_activity sa ON ts.sub_activity_id = sa.sub_activity_id
            JOIN activity a ON sa.activity_id = a.activity_id
            ORDER BY ts.slot_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$timeSlots = getTimeSlots();

// Fetch sub-activities from the database
function getSubActivities() {
    global $conn;
    $sql = "SELECT sa.sub_activity_id, san.sub_act_name 
            FROM sub_activity sa
            JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$subActivities = getSubActivities();

// Handle form submission for adding new time slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_activity_id'])) {
    error_log("Form submitted"); // Log form submission
    $sub_activity_id = $_POST['sub_activity_id'];
    $slot_date = $_POST['slot_date'];
    
    // Convert times to 24-hour format for database storage
    $start_time_12 = $_POST['slot_start_time'];
    $start_period = $_POST['start_period'];
    $slot_start_time = convertTo24Hour($start_time_12, $start_period);
    
    $end_time_12 = $_POST['slot_end_time'];
    $end_period = $_POST['end_period'];
    $slot_end_time = convertTo24Hour($end_time_12, $end_period);
    
    $max_participants = $_POST['max_participants'];
    
    // Add date validation
    if (strtotime($slot_date) < strtotime(date('Y-m-d'))) {
        $_SESSION['error_message'] = "Cannot create time slots for past dates.";
        error_log("Error: Cannot create time slots for past dates."); // Log the error
    } else if ($max_participants < 1) {
        $_SESSION['error_message'] = "Minimum 1 participant required.";
        error_log("Error: Minimum 1 participant required."); // Log the error
    } else if ($max_participants > 12) {
        $_SESSION['error_message'] = "Exceeded limit of 12 participants.";
        error_log("Error: Exceeded limit of 12 participants."); // Log the error
    } else if (!empty($sub_activity_id) && !empty($slot_date) && !empty($slot_start_time) && !empty($slot_end_time)) {
        try {
            $sql = "INSERT INTO timeslots (sub_activity_id, slot_date, slot_start_time, slot_end_time, max_participants) 
                    VALUES (:sub_activity_id, :slot_date, :slot_start_time, :slot_end_time, :max_participants)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'sub_activity_id' => $sub_activity_id,  
                'slot_date' => $slot_date,
                'slot_start_time' => $slot_start_time,
                'slot_end_time' => $slot_end_time,
                'max_participants' => $max_participants
            ]);
            
            // Return JSON response with a custom success message
            echo json_encode(['success' => true, 'message' => "Time slot added successfully!"]);
            exit();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log($error_message); // Log the error message
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit();
        }
    }
}

// Fetch time slots for each sub-activity
function getTimeSlotsBySubActivity($sub_activity_id) {
    global $conn;
    $sql = "SELECT * FROM timeslots WHERE sub_activity_id = :sub_activity_id ORDER BY slot_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['sub_activity_id' => $sub_activity_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Modify the getTimeSlotsBySubActivityId function to accept a date parameter
function getTimeSlotsBySubActivityId($sub_activity_id, $date = null) {
    global $conn;
    $sql = "SELECT * FROM timeslots WHERE sub_activity_id = :sub_activity_id";
    
    // Add date filter if provided
    if ($date) {
        $sql .= " AND slot_date = :slot_date";
    }
    
    $sql .= " ORDER BY slot_date ASC, slot_start_time ASC";
    $stmt = $conn->prepare($sql);
    $params = ['sub_activity_id' => $sub_activity_id];
    
    if ($date) {
        $params['slot_date'] = $date;
    }
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update the AJAX endpoint to handle date parameter
if (isset($_GET['sub_activity_id'])) {
    $sub_activity_id = $_GET['sub_activity_id'];
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    $_SESSION['last_sub_activity_id'] = $sub_activity_id; // Store in session
    $timeSlots = getTimeSlotsBySubActivityId($sub_activity_id, $date);
    echo json_encode($timeSlots);
    exit(); // Stop further execution
}

// Modify the delete functionality to redirect back to the last viewed sub-activity
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $slot_id = $_GET['delete_slot'] ?? null;
    if ($slot_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM timeslots WHERE slot_id = ?");
            $result = $stmt->execute([$slot_id]);
            echo json_encode(['success' => $result, 'message' => 'Time slot deleted successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit(); // Ensure to exit after sending the response
    } else {
        echo json_encode(['success' => false, 'message' => 'No slot ID provided.']);
    }
}

// Handle form submission for updating time slot
// Handle form submission for adding or updating time slot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isUpdate = isset($_POST['slot_id']) && !empty($_POST['slot_id']);
    
    error_log("Form submitted - " . ($isUpdate ? "Update" : "New")); // Log form submission
    
    $sub_activity_id = $_POST['sub_activity_id'];
    $slot_date = $_POST['slot_date'];
    
    // Convert times to 24-hour format for database storage
    $start_time_12 = $_POST['slot_start_time'];
    $start_period = $_POST['start_period'];
    $slot_start_time = convertTo24Hour($start_time_12, $start_period);
    
    $end_time_12 = $_POST['slot_end_time'];
    $end_period = $_POST['end_period'];
    $slot_end_time = convertTo24Hour($end_time_12, $end_period);
    
    $max_participants = $_POST['max_participants'];
    
    // Add date validation
    if (strtotime($slot_date) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success' => false, 'message' => "Cannot create time slots for past dates."]);
        exit();
    } else if ($max_participants < 1) {
        echo json_encode(['success' => false, 'message' => "Minimum 1 participant required."]);
        exit();
    } else if ($max_participants > 12) {
        echo json_encode(['success' => false, 'message' => "Exceeded limit of 12 participants."]);
        exit();
    } else if (!empty($sub_activity_id) && !empty($slot_date) && !empty($slot_start_time) && !empty($slot_end_time)) {
        try {
            if ($isUpdate) {
                $sql = "UPDATE timeslots SET 
                        sub_activity_id = :sub_activity_id,
                        slot_date = :slot_date,
                        slot_start_time = :slot_start_time,
                        slot_end_time = :slot_end_time,
                        max_participants = :max_participants
                        WHERE slot_id = :slot_id";
                $params = [
                    'sub_activity_id' => $sub_activity_id,
                    'slot_date' => $slot_date,
                    'slot_start_time' => $slot_start_time,
                    'slot_end_time' => $slot_end_time,
                    'max_participants' => $max_participants,
                    'slot_id' => $_POST['slot_id']
                ];
            } else {
                $sql = "INSERT INTO timeslots (sub_activity_id, slot_date, slot_start_time, slot_end_time, max_participants) 
                        VALUES (:sub_activity_id, :slot_date, :slot_start_time, :slot_end_time, :max_participants)";
                $params = [
                    'sub_activity_id' => $sub_activity_id,
                    'slot_date' => $slot_date,
                    'slot_start_time' => $slot_start_time,
                    'slot_end_time' => $slot_end_time,
                    'max_participants' => $max_participants
                ];
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => $isUpdate ? "Time slot updated successfully!" : "Time slot added successfully!"]);
            exit();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log($error_message);
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit();
        }
    }
}

if (isset($_GET['slot_id'])) {
    $slot_id = $_GET['slot_id'];
    $sql = "SELECT * FROM timeslots WHERE slot_id = :slot_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['slot_id' => $slot_id]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($slot);
    exit();
}

// Add this function to your file, before the HTML section

function generateTimeSlots($conn) {
    // Get current date and date 2 months from now
    $currentDate = new DateTime();
    $twoMonthsLater = new DateTime();
    $twoMonthsLater->modify('+2 months');
    
    // Format dates for use in database queries
    $startDate = $currentDate->format('Y-m-d');
    $endDate = $twoMonthsLater->format('Y-m-d');
    
    // Check the latest date for which time slots exist
    $checkSql = "SELECT MAX(slot_date) as latest_date FROM timeslots";
    $checkStmt = $conn->query($checkSql);
    $latestDate = $checkStmt->fetch(PDO::FETCH_ASSOC)['latest_date'];
    
    // If there's no data or the latest date is less than the end date
    if (!$latestDate || strtotime($latestDate) < strtotime($endDate)) {
        
        // Define the start date for slot generation
        $generationStartDate = $latestDate ? new DateTime($latestDate) : $currentDate;
        $generationStartDate->modify('+1 day'); // Start from the next day
        
        // Define sub-activities to assign time slots
        $subActivitySql = "SELECT sub_activity_id FROM sub_activity";
        $subActivityStmt = $conn->query($subActivitySql);
        $subActivities = $subActivityStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If we don't have any sub-activities, we can't create time slots
        if (empty($subActivities)) {
            error_log("No sub-activities found for time slot generation");
            return;
        }
        
        // Define the time slots to generate
        $timeSlots = [
            ['06:00:00', '07:00:00'],
            ['07:00:00', '08:00:00'],
            ['08:00:00', '09:00:00'],
            ['09:00:00', '10:00:00'],
            ['10:00:00', '11:00:00'],
            ['11:00:00', '12:00:00'],
            ['12:00:00', '13:00:00'],
            ['13:00:00', '14:00:00'],
            ['14:00:00', '15:00:00'],
            ['15:00:00', '16:00:00']
        ];
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Generate time slots for each day within our range
            $insertSql = "INSERT INTO timeslots (sub_activity_id, slot_date, slot_start_time, slot_end_time, max_participants) 
                          VALUES (:sub_activity_id, :slot_date, :slot_start_time, :slot_end_time, :max_participants)";
            $insertStmt = $conn->prepare($insertSql);
            
            $currentDateToGenerate = clone $generationStartDate;
            
            while ($currentDateToGenerate <= $twoMonthsLater) {
                $dateString = $currentDateToGenerate->format('Y-m-d');
                
                // For each sub-activity
                foreach ($subActivities as $subActivityId) {
                    // For each time slot
                    foreach ($timeSlots as $timeSlot) {
                        // Check if this time slot already exists for this date and sub-activity
                        $checkSlotSql = "SELECT COUNT(*) FROM timeslots 
                                         WHERE sub_activity_id = :sub_activity_id 
                                         AND slot_date = :slot_date 
                                         AND slot_start_time = :slot_start_time";
                        $checkSlotStmt = $conn->prepare($checkSlotSql);
                        $checkSlotStmt->execute([
                            'sub_activity_id' => $subActivityId,
                            'slot_date' => $dateString,
                            'slot_start_time' => $timeSlot[0]
                        ]);
                        
                        // Only insert if it doesn't already exist
                        if ($checkSlotStmt->fetchColumn() == 0) {
                            $insertStmt->execute([
                                'sub_activity_id' => $subActivityId,
                                'slot_date' => $dateString,
                                'slot_start_time' => $timeSlot[0],
                                'slot_end_time' => $timeSlot[1],
                                'max_participants' => 10 // Default max participants
                            ]);
                        }
                    }
                }
                
                $currentDateToGenerate->modify('+1 day');
            }
            
            $conn->commit();
            error_log("Time slots generated successfully through " . $twoMonthsLater->format('Y-m-d'));
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Error generating time slots: " . $e->getMessage());
        }
    }
}

// Call this function when the page loads to ensure time slots are up to date
generateTimeSlots($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: rgba(255, 255, 255, 0.2);
            --background-light: rgba(76, 132, 196, 0.15);
            --text-dark: #ffffff;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin: 30px 0;
            padding-right: 40px;
        }

        .stat-card {
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
            padding: 25px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            min-width: 200px;
        }

        .stat-card h3 {
            color: white;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        /* Replace the existing .data-table and related styles with these */
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

        .data-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .btn {
            padding: 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.2);
            color: white;
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

        /* Add new styles */
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

        .btn-edit {
            background-color: #f39c12;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        /* Add dropdown styles */
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

        .status-active, .status-inactive {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        #add-user-btn {
            margin-right: 20px;
        }

        /* Add this new style for select options */
        select option {
            background: white;
            color: black;
        }

        /* Add this new style for input placeholders */
        input::placeholder {
            color: white; /* Change to your desired color */
            opacity: 0.7; /* Optional: Adjusts the opacity of the placeholder text */
        }

        #time-slots-content {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); /* For Safari */
            border: 1px solid rgba(255, 255, 255, 0.3); /* Optional border for better visibility */
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">Admin Dashboard</div>
        <nav>
        <ul class="sidebar-nav">
                <li class="sidebar-nav-item"><a href="admin_overview.php">Overview</a></li>
                <li class="sidebar-nav-item"><a href="admin_user.php">Users</a></li>
                <li class="sidebar-nav-item"><a href="admin_activities.php">Activities</a></li>
                <li class="sidebar-nav-item"><a href="admin_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="admin_membership.php">Membership</a></li>
                <li class="sidebar-nav-item active"><a href="admin_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="admin_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="admin_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="admin_payments.php">Payments</a></li>
                <li class="sidebar-nav-item"><a href="admin_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <!-- Add a div for displaying messages -->
        <div id="message-container" style="margin-bottom: 20px;">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div style="color: #e74c3c;"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="color: #2ecc71;"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                <script>
                    // Automatically reload the page after 3 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                </script>
            <?php endif; ?>
        </div>
        <div id="time-slots-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>Time Slot Management</h1>
                <div class="header-actions">
                    <button id="add-timeslot-btn" class="btn btn-primary">Add New Time Slot</button>
                    
                    <div class="dropdown">
                        <div class="user-profile">
                            <div class="user-avatar">AD</div>
                            <span>Admin</span>
                        </div>
                        <div class="dropdown-content">
                            <a href="logout.php" class="dropdown-item">Log Out</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- New Time Slot Form -->
            <form id="new-time-slot-form" method="POST" style="display: none; margin-top: 20px; background: rgba(76, 132, 196, 0.15); padding: 20px; border-radius: 10px;">
                <input type="hidden" id="edit-slot-id" name="slot_id" value="">
                
                <div style="margin-bottom: 15px;">
                    <label for="sub_activity_id" style="display: block; margin-bottom: 5px;">Sub Activity:</label>
                    <select name="sub_activity_id" id="sub_activity_id" required style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                        <option value="">Select Sub Activity</option>
                        <?php foreach ($subActivities as $subActivity): ?>
                            <option value="<?php echo $subActivity['sub_activity_id']; ?>"><?php echo htmlspecialchars($subActivity['sub_act_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="slot_date" style="display: block; margin-bottom: 5px;">Date:</label>
                    <input type="date" name="slot_date" id="slot_date" required style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Start Time:</label>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <select name="slot_start_time" id="slot_start_time" required style="flex: 1; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                            <option value="">Select Start Time</option>
                            <option value="06:00">6:00</option>
                            <option value="07:00">7:00</option>
                            <option value="08:00">8:00</option>
                            <option value="09:00">9:00</option>
                            <option value="10:00">10:00</option>
                            <option value="11:00">11:00</option>
                            <option value="12:00">12:00</option>
                            <option value="13:00">1:00</option>
                            <option value="14:00">2:00</option>
                            <option value="15:00">3:00</option>
                        </select>
                        <select name="start_period" id="start_period" required style="width: 80px; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">End Time:</label>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <select name="slot_end_time" id="slot_end_time" required style="flex: 1; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                            <option value="">Select End Time</option>
                            <option value="07:00">7:00</option>
                            <option value="08:00">8:00</option>
                            <option value="09:00">9:00</option>
                            <option value="10:00">10:00</option>
                            <option value="11:00">11:00</option>
                            <option value="12:00">12:00</option>
                            <option value="13:00">1:00</option>
                            <option value="14:00">2:00</option>
                            <option value="15:00">3:00</option>
                            <option value="16:00">4:00</option>
                        </select>
                        <select name="end_period" id="end_period" required style="width: 80px; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="max_participants" style="display: block; margin-bottom: 5px;">Max Participants:</label>
                    <input type="number" name="max_participants" id="max_participants" required min="1" max="12" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="cancel-btn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" id="submit-btn" class="btn btn-primary">Add Time Slot</button>
                    <button type="button" id="update-btn" class="btn btn-primary" style="display: none;">Update Time Slot</button>
                </div>
            </form>
            <section class="section-content" style="display: flex; flex-wrap: wrap; gap: 20px;">
                <!-- Success message container -->
                <div id="activity-success-message" style="flex: 0 0 100%; margin-bottom: 20px; color: #2ecc71; display: none;"></div>
                
                <!-- Loop through each sub-activity -->
                <?php foreach ($subActivities as $subActivity): ?>
                    <div class="sub-activity-box" style="flex: 0 0 calc(25% - 20px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 10px; padding: 15px; background: rgba(255, 255, 255, 0.1); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); text-align: center;">
                        <h2 style="font-size: 1.2rem; color: #00bcd4; margin-bottom: 10px; text-transform: uppercase;"><?php echo htmlspecialchars($subActivity['sub_act_name']); ?></h2>
                        <button class="btn btn-secondary view-timeslots-btn" data-subactivity-id="<?php echo $subActivity['sub_activity_id']; ?>" style="background-color: #00bcd4; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.3s;">View Time Slots</button>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- New container for displaying time slots -->
            <div id="time-slots-display" style="margin-top: 20px; display: none;">
                <h3 style="color: #00bcd4;">Time Slots for <span id="activity-name"></span></h3>
                
                <!-- Add date selection form -->
                <div style="margin: 20px 0; display: flex; gap: 10px; align-items: center;">
                    <label for="date-filter">Select Date:</label>
                    <input type="date" id="date-filter" style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                    <button id="filter-date-btn" class="btn btn-primary" style="margin-left: 10px;">Filter</button>
                    <button id="clear-filter-btn" class="btn btn-secondary">Show All</button>
                </div>
                
                <section class="section-content">
                    <!-- Updated Time Slots Table -->
                    <table class="data-table">
                    <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Max Participants</th>
            <th>Current Participants</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="time-slots-table-body">
<?php foreach ($timeSlots as $slot): ?>
    <tr>
        <td><?php echo $slot['slot_id']; ?></td>
        <td><?php echo $slot['slot_date']; ?></td>
        <td><?php echo convertTo12Hour($slot['slot_start_time']); ?></td>
        <td><?php echo convertTo12Hour($slot['slot_end_time']); ?></td>
        <td><?php echo $slot['max_participants']; ?></td>
        <td><?php echo $slot['current_participants']; ?></td>
        <td>
            <div class="action-buttons">
                <button class="btn btn-edit" data-slotid="<?php echo $slot['slot_id']; ?>">Edit</button>
                <button class="btn btn-danger" data-slotid="<?php echo $slot['slot_id']; ?>">Delete</button>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
                    </table>
                </section>
            </div>
        </div>
    </main>

    <!-- Add this modal for editing time slots -->
    <div id="edit-time-slot-modal" style="display: none;">
        <form id="edit-time-slot-form" method="POST">
            <input type="hidden" name="slot_id" id="edit-slot-id">
            <div>
                <label for="edit-slot-date">Date:</label>
                <input type="date" name="slot_date" id="edit-slot-date" required>
            </div>
            <div>
                <label for="edit-slot-start-time">Start Time (hh:mm):</label>
                <input type="text" name="slot_start_time" id="edit-slot-start-time" required placeholder="Start Time (hh:mm)">
                <select name="start_period" id="edit-start-period" required>
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
            <div>
                <label for="edit-slot-end-time">End Time (hh:mm):</label>
                <input type="text" name="slot_end_time" id="edit-slot-end-time" required placeholder="End Time (hh:mm)">
                <select name="end_period" id="edit-end-period" required>
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
            <div>
                <label for="edit-max-participants">Max Participants:</label>
                <input type="number" name="max_participants" id="edit-max-participants" required min="1" max="12">
            </div>
            <button type="submit" class="btn btn-primary">Update Time Slot</button>
        </form>
    </div>

    <!-- Add JavaScript for toggle functionality -->
    <script>
       // Starting of script section
document.addEventListener('DOMContentLoaded', function() {
    // Add button click handler
    document.getElementById('add-timeslot-btn').addEventListener('click', function() {
        resetForm();
        const form = document.getElementById('new-time-slot-form');
        form.style.display = 'block';
    });

    // Cancel button handler
    document.getElementById('cancel-btn').addEventListener('click', function() {
        document.getElementById('new-time-slot-form').style.display = 'none';
    });

    // Form submission handler
    document.getElementById('new-time-slot-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form values
        const subActivityId = document.getElementById('sub_activity_id').value;
        const slotDate = document.getElementById('slot_date').value;
        const maxParticipants = document.getElementById('max_participants').value;
        
        // Validate inputs
        if (!subActivityId) {
            alert('Please select a sub-activity');
            return;
        }
        
        if (!slotDate) {
            alert('Please select a date');
            return;
        }
        
        // Validate max participants
        const participantsValue = parseInt(maxParticipants);
        if (isNaN(participantsValue) || participantsValue < 1 || participantsValue > 12) {
            alert('Number of participants must be between 1 and 12');
            return;
        }
        
        // Validate date is not in the past
        const selectedDate = new Date(slotDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            alert('Cannot create time slots for past dates');
            return;
        }

        const formData = new FormData(this);
        const slotId = document.getElementById('edit-slot-id').value;
        const isUpdate = slotId !== '';

        // Send request to server
        fetch('manager_time_slots.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                resetForm();
                document.getElementById('new-time-slot-form').style.display = 'none';
                
                // If we're viewing time slots for this sub-activity, refresh the view
                const currentSubActivityId = document.getElementById('time-slots-display').getAttribute('data-subactivity-id');
                if (currentSubActivityId === subActivityId) {
                    const dateFilter = document.getElementById('date-filter').value;
                    if (dateFilter) {
                        fetchTimeSlots(subActivityId, dateFilter);
                    }
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request');
        });
    });

    // View time slots functionality
    document.querySelectorAll('.view-timeslots-btn').forEach(button => {
        button.addEventListener('click', function() {
            const subActivityId = this.getAttribute('data-subactivity-id');
            const activityName = this.parentElement.querySelector('h2').innerText;
            const timeSlotsTableBody = document.getElementById('time-slots-table-body');
            const timeSlotsDisplay = document.getElementById('time-slots-display');
            
            // Store the current sub-activity ID for later use
            timeSlotsDisplay.setAttribute('data-subactivity-id', subActivityId);

            // Clear previous time slots
            timeSlotsTableBody.innerHTML = '';
            document.getElementById('activity-name').innerText = activityName;
            
            // Reset date filter
            document.getElementById('date-filter').value = '';
            
            // Show the time slots display with empty table and prompt
            timeSlotsDisplay.style.display = 'block';
            
            // Display a message prompting the user to select a date
            timeSlotsTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Please select a date and click "Filter" to view time slots.</td></tr>';
            
            // Scroll to the time slots section
            timeSlotsDisplay.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Edit and Delete handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-edit')) {
            const slotId = e.target.dataset.slotid;
            editTimeSlot(slotId);
        }
        
        if (e.target.classList.contains('btn-danger')) {
            const slotId = e.target.dataset.slotid;
            deleteTimeSlot(slotId);
        }
    });

    // Update button click handler
    document.getElementById('update-btn').addEventListener('click', function(e) {
        document.getElementById('new-time-slot-form').dispatchEvent(new Event('submit'));
    });

    // Function to fetch time slots with date filter
    function fetchTimeSlots(subActivityId, date) {
        const timeSlotsTableBody = document.getElementById('time-slots-table-body');
        
        // Show loading indicator
        timeSlotsTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Loading time slots...</td></tr>';
        
        // Build the URL with parameters
        let url = `manager_time_slots.php?sub_activity_id=${subActivityId}`;
        
        // Add date parameter if provided
        if (date) {
            url += `&date=${date}`;
        }
        
        console.log("Fetching time slots from URL:", url); // Debug log
        
        // Fetch time slots
        fetch(url)
            .then(response => {
                console.log("Response status:", response.status);
                return response.json();
            })
            .then(data => {
                console.log("Received data:", data); // Debug log
                
                // Clear previous time slots
                timeSlotsTableBody.innerHTML = '';
                
                if (data && data.length > 0) {
                    data.forEach(slot => {
                        const row = document.createElement('tr');
                        
                        // Format times properly
                        const formatTime = (timeString) => {
                            try {
                                // Try to parse the time string
                                const [hours, minutes, seconds] = timeString.split(':');
                                const hour = parseInt(hours);
                                const period = hour >= 12 ? 'PM' : 'AM';
                                const hour12 = hour % 12 || 12; // Convert 0 to 12 for 12 AM
                                return `${hour12}:${minutes} ${period}`;
                            } catch (e) {
                                console.error("Error parsing time:", e, timeString);
                                return timeString; // Return the original string if parsing fails
                            }
                        };

                        row.innerHTML = `
                            <td>${slot.slot_id}</td>
                            <td>${slot.slot_date}</td>
                            <td>${formatTime(slot.slot_start_time)}</td>
                            <td>${formatTime(slot.slot_end_time)}</td>
                            <td>${slot.max_participants}</td>
                            <td>${slot.current_participants || 0}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-edit" data-slotid="${slot.slot_id}">Edit</button>
                                    <button class="btn btn-danger" data-slotid="${slot.slot_id}">Delete</button>
                                </div>
                            </td>
                        `;
                        timeSlotsTableBody.appendChild(row);
                    });
                } else {
                    // Display message when no time slots are found for the selected date
                    const dateMessage = date ? `on ${date}` : '';
                    timeSlotsTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center;">No time slots available for this activity ${dateMessage}.</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Error fetching time slots:', error);
                timeSlotsTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center;">Error loading time slots: ${error.message}</td></tr>`;
            });
    }

    // Add event listeners for the date filter buttons
    document.getElementById('filter-date-btn').addEventListener('click', function() {
        const subActivityId = document.getElementById('time-slots-display').getAttribute('data-subactivity-id');
        const dateFilter = document.getElementById('date-filter').value;
        
        if (!dateFilter) {
            alert('Please select a date to filter');
            return;
        }
        
        console.log("Filtering for sub-activity:", subActivityId, "date:", dateFilter); // Debug log
        fetchTimeSlots(subActivityId, dateFilter);
    });

    document.getElementById('clear-filter-btn').addEventListener('click', function() {
        // Clear the date input and fetch all time slots for the current sub-activity
        document.getElementById('date-filter').value = '';
        const subActivityId = document.getElementById('time-slots-display').getAttribute('data-subactivity-id');
        fetchTimeSlots(subActivityId);
    });

    // Helper function to reset form
    function resetForm() {
        const form = document.getElementById('new-time-slot-form');
        form.reset();
        document.getElementById('edit-slot-id').value = '';
        document.getElementById('submit-btn').style.display = 'inline-block';
        document.getElementById('update-btn').style.display = 'none';
    }

    // Edit time slot handler
    function editTimeSlot(slotId) {
        fetch(`manager_time_slots.php?slot_id=${slotId}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    // Show the form
                    const form = document.getElementById('new-time-slot-form');
                    form.style.display = 'block';
                    
                    // Set the slot ID
                    document.getElementById('edit-slot-id').value = slotId;
                    
                    // Fill form data
                    document.getElementById('slot_date').value = data.slot_date;
                    document.getElementById('sub_activity_id').value = data.sub_activity_id;
                    document.getElementById('max_participants').value = data.max_participants;
                    
                    // Parse and set times
                    const parseTime = (timeString) => {
                        const [hours, minutes] = timeString.split(':');
                        const hour = parseInt(hours);
                        const period = hour >= 12 ? 'PM' : 'AM';
                        const hour12 = hour % 12 || 12; // Convert 0 to 12 for 12 AM
                        return {
                            time: `${hour12}:${minutes}`,
                            period: period
                        };
                    };
                    
                    const startTime = parseTime(data.slot_start_time);
                    const endTime = parseTime(data.slot_end_time);
                    
                    // Set the start time
                    for (let i = 0; i < document.getElementById('slot_start_time').options.length; i++) {
                        const option = document.getElementById('slot_start_time').options[i];
                        if (option.value === startTime.time) {
                            document.getElementById('slot_start_time').selectedIndex = i;
                            break;
                        }
                    }
                    document.getElementById('start_period').value = startTime.period;
                    
                    // Set the end time
                    for (let i = 0; i < document.getElementById('slot_end_time').options.length; i++) {
                        const option = document.getElementById('slot_end_time').options[i];
                        if (option.value === endTime.time) {
                            document.getElementById('slot_end_time').selectedIndex = i;
                            break;
                        }
                    }
                    document.getElementById('end_period').value = endTime.period;
                    
                    // Update button visibility
                    document.getElementById('submit-btn').style.display = 'none';
                    document.getElementById('update-btn').style.display = 'inline-block';
                    
                    // Scroll to form
                    form.scrollIntoView({ behavior: 'smooth' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching time slot data');
            });
    }

    // Delete time slot handler
    function deleteTimeSlot(slotId) {
        if (confirm('Are you sure you want to delete this time slot?')) {
            fetch(`manager_time_slots.php?delete_slot=${slotId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    
                    // Refresh the current view if we're looking at time slots
                    const timeSlotsDisplay = document.getElementById('time-slots-display');
                    if (timeSlotsDisplay.style.display !== 'none') {
                        const subActivityId = timeSlotsDisplay.getAttribute('data-subactivity-id');
                        const dateFilter = document.getElementById('date-filter').value;
                        if (dateFilter) {
                            fetchTimeSlots(subActivityId, dateFilter);
                        } else {
                            fetchTimeSlots(subActivityId);
                        }
                    }
                } else {
                    alert('Error deleting time slot: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting time slot');
            });
        }
    }

    // Add this to your existing JavaScript
    document.getElementById('slot_start_time').addEventListener('change', function() {
        const startTime = this.value;
        const endTimeSelect = document.getElementById('slot_end_time');
        
        if (startTime) {
            // Parse the hour from the start time
            const startHour = parseInt(startTime.split(':')[0]);
            const endHour = startHour + 1;
            
            // Format the end time
            let endTimeValue = endHour.toString().padStart(2, '0') + ':00';
            
            // Set the end time select value
            for (let i = 0; i < endTimeSelect.options.length; i++) {
                if (endTimeSelect.options[i].value === endTimeValue) {
                    endTimeSelect.selectedIndex = i;
                    break;
                }
            }
            
            // Also set the AM/PM dropdowns appropriately
            const startPeriodSelect = document.getElementById('start_period');
            const endPeriodSelect = document.getElementById('end_period');
            
            if (startHour < 12) {
                startPeriodSelect.value = 'AM';
            } else {
                startPeriodSelect.value = 'PM';
            }
            
            if (endHour < 12) {
                endPeriodSelect.value = 'AM';
            } else {
                endPeriodSelect.value = 'PM';
            }
        }
    });
});
    </script>
</body>
</html>