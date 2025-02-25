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
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: signin.php');
    exit();
}

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: signin.php');
    exit();
}

require_once 'db_connect.php';

$activeSection = 'time-slots';

function getTimeSlots() {
    global $conn;
    $sql = "SELECT ts.*, sa.sub_activity_name, a.activity_type, ts.current_participants 
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
    $sql = "SELECT * FROM sub_activity";
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
        // Check for overlapping time slots
        $sql = "SELECT * FROM timeslots WHERE sub_activity_id = :sub_activity_id AND slot_date = :slot_date 
                AND ((slot_start_time <= :slot_end_time AND slot_end_time >= :slot_start_time))";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'sub_activity_id' => $sub_activity_id,
            'slot_date' => $slot_date,
            'slot_start_time' => $slot_start_time,
            'slot_end_time' => $slot_end_time
        ]);
        $existingSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($existingSlots) > 0) {
            $_SESSION['error_message'] = "Activity is already allotted for this time.";
            error_log("Error: Activity is already allotted for this time."); // Log the error
        } else {
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
}

// Fetch time slots for each sub-activity
function getTimeSlotsBySubActivity($sub_activity_id) {
    global $conn;
    $sql = "SELECT * FROM timeslots WHERE sub_activity_id = :sub_activity_id ORDER BY slot_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['sub_activity_id' => $sub_activity_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTimeSlotsBySubActivityId($sub_activity_id) {
    global $conn;
    $sql = "SELECT * FROM timeslots WHERE sub_activity_id = :sub_activity_id ORDER BY slot_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['sub_activity_id' => $sub_activity_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Store the last viewed sub-activity ID in session when viewing time slots
if (isset($_GET['sub_activity_id'])) {
    $sub_activity_id = $_GET['sub_activity_id'];
    $_SESSION['last_sub_activity_id'] = $sub_activity_id; // Store in session
    $timeSlots = getTimeSlotsBySubActivityId($sub_activity_id);
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
        // Check for overlapping time slots
        $sql = "SELECT * FROM timeslots WHERE sub_activity_id = :sub_activity_id AND slot_date = :slot_date 
                AND ((slot_start_time <= :slot_end_time AND slot_end_time >= :slot_start_time))";
        
        // Exclude the current slot being updated from the check
        if ($isUpdate) {
            $sql .= " AND slot_id != :slot_id";
        }
        
        $stmt = $conn->prepare($sql);
        $params = [
            'sub_activity_id' => $sub_activity_id,
            'slot_date' => $slot_date,
            'slot_start_time' => $slot_start_time,
            'slot_end_time' => $slot_end_time
        ];
        
        if ($isUpdate) {
            $params['slot_id'] = $_POST['slot_id'];
        }
        
        $stmt->execute($params);
        $existingSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($existingSlots) > 0) {
            echo json_encode(['success' => false, 'message' => "Activity is already allotted for this time."]);
            exit();
        } else {
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
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
        <div class="sidebar-logo">Manager Dashboard</div>
        <nav>
        <ul class="sidebar-nav">
                <li class="sidebar-nav-item"><a href="manager_dashboard.php">Overview</a></li>
                <li class="sidebar-nav-item"><a href="manager_user.php">Users</a></li>
                <li class="sidebar-nav-item"><a href="manager_activities.php">Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_membership.php">Membership</a></li>
                <li class="sidebar-nav-item active"><a href="manager_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="manager_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="manager_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="manager_feedback.php">Feedback</a></li>
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

            <!-- New Time Slot Form -->
            <form id="new-time-slot-form" method="POST" style="display: none; margin-top: 20px;">
                <input type="hidden" id="edit-slot-id" name="slot_id" value="">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select name="sub_activity_id" id="sub_activity_id" required style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                        <option value="">Select Activity</option>
                        <?php foreach ($subActivities as $subActivity): ?>
                            <option value="<?php echo $subActivity['sub_activity_id']; ?>"><?php echo htmlspecialchars($subActivity['sub_activity_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="slot_date" id="slot_date" required min="<?php echo date('Y-m-d'); ?>" style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                    
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <input type="text" name="slot_start_time" id="slot_start_time" required placeholder="Start Time (hh:mm)" 
                            style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;" 
                            pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]$" title="Enter time in hh:mm format (12-hour)">
                        <select name="start_period" id="start_period" required style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <input type="text" name="slot_end_time" id="slot_end_time" required placeholder="End Time (hh:mm)" 
                            style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;" 
                            pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]$" title="Enter time in hh:mm format (12-hour)">
                        <select name="end_period" id="end_period" required style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                    
                    <input type="number" name="max_participants" id="max_participants" required min="1" max="12" placeholder="Max Participants" 
                        style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255, 255, 255, 0.5); background: rgba(255, 255, 255, 0.2); color: white;">
                    
                    <button type="submit" id="submit-btn" class="btn btn-primary">Save Time Slot</button>
                    <button type="button" id="update-btn" class="btn btn-primary" style="display: none;">Update Time Slot</button>
                </div>
            </form>
            <section class="section-content" style="display: flex; flex-wrap: wrap; gap: 20px;">
                <!-- Success message container -->
                <div id="activity-success-message" style="flex: 0 0 100%; margin-bottom: 20px; color: #2ecc71; display: none;"></div>
                
                <!-- Loop through each sub-activity -->
                <?php foreach ($subActivities as $subActivity): ?>
                    <div class="sub-activity-box" style="flex: 0 0 calc(25% - 20px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 10px; padding: 15px; background: rgba(255, 255, 255, 0.1); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); text-align: center;">
                        <h2 style="font-size: 1.2rem; color: #00bcd4; margin-bottom: 10px; text-transform: uppercase;"><?php echo htmlspecialchars($subActivity['sub_activity_name']); ?></h2>
                        <button class="btn btn-secondary view-timeslots-btn" data-subactivity-id="<?php echo $subActivity['sub_activity_id']; ?>" style="background-color: #00bcd4; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.3s;">View Time Slots</button>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- New container for displaying time slots -->
            <div id="time-slots-display" style="margin-top: 20px; display: none;">
                <h3 style="color: #00bcd4;">Time Slots for <span id="activity-name"></span></h3>
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
        form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';
    });

    // Form submission handler
    document.getElementById('new-time-slot-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate inputs
        const startTime = document.querySelector('input[name="slot_start_time"]');
        const endTime = document.querySelector('input[name="slot_end_time"]');
        const maxParticipants = document.getElementById('max_participants');
        const slotDate = document.getElementById('slot_date');
        
        // Validate time format
        const timeRegex = /^(0?[1-9]|1[0-2]):[0-5][0-9]$/;
        if (!timeRegex.test(startTime.value) || !timeRegex.test(endTime.value)) {
            alert('Please enter time in hh:mm format (12-hour)');
            return;
        }
        
        // Validate max participants
        const participantsValue = parseInt(maxParticipants.value);
        if (isNaN(participantsValue) || participantsValue < 1 || participantsValue > 12) {
            alert('Number of participants must be between 1 and 12');
            return;
        }
        
        // Validate date is not in the past
        const selectedDate = new Date(slotDate.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            alert('Cannot create time slots for past dates');
            return;
        }

        const formData = new FormData(this);
        const slotId = document.getElementById('edit-slot-id').value;
        const isUpdate = slotId !== '';

        if (isUpdate) {
            formData.append('slot_id', slotId);
        }

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
                location.reload();
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

            // Clear previous time slots
            timeSlotsTableBody.innerHTML = '';
            document.getElementById('activity-name').innerText = activityName;

            // Fetch time slots for the selected sub-activity
            fetch(`manager_time_slots.php?sub_activity_id=${subActivityId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(slot => {
                            const row = document.createElement('tr');
                            const startTime = new Date(`2000-01-01 ${slot.slot_start_time}`);
                            const endTime = new Date(`2000-01-01 ${slot.slot_end_time}`);
                            const formatTime = (date) => {
                                return date.toLocaleTimeString('en-US', { 
                                    hour: '2-digit', 
                                    minute: '2-digit', 
                                    hour12: true 
                                });
                            };

                            row.innerHTML = `
                                <td>${slot.slot_id}</td>
                                <td>${slot.slot_date}</td>
                                <td>${formatTime(startTime)}</td>
                                <td>${formatTime(endTime)}</td>
                                <td>${slot.max_participants}</td>
                                <td>${slot.current_participants}</td>
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
                        timeSlotsTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No time slots available for this activity.</td></tr>';
                    }
                    timeSlotsDisplay.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching time slots:', error);
                    timeSlotsTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Error loading time slots.</td></tr>';
                });
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

    // Helper function to convert 24-hour time to 12-hour format
    function convertTo12Hour(time24) {
        const timestamp = new Date(`2000-01-01T${time24}`);
        return timestamp.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit', 
            hour12: true 
        });
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
                    
                    // Convert and set times
                    const startTime = convertTo12Hour(data.slot_start_time);
                    const endTime = convertTo12Hour(data.slot_end_time);
                    
                    const [startHourMin, startPeriod] = startTime.split(' ');
                    const [endHourMin, endPeriod] = endTime.split(' ');
                    
                    document.getElementById('slot_start_time').value = startHourMin;
                    document.getElementById('start_period').value = startPeriod;
                    document.getElementById('slot_end_time').value = endHourMin;
                    document.getElementById('end_period').value = endPeriod;
                    
                    // Update button visibility
                    document.getElementById('submit-btn').style.display = 'none';
                    document.getElementById('update-btn').style.display = 'inline-block';
                    
                    // Scroll to form
                    window.scrollTo(0, 0);
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
                    location.reload();
                } else {
                    alert('Error deleting time slot: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    }

    // Helper function to reset form
    function resetForm() {
        const form = document.getElementById('new-time-slot-form');
        form.reset();
        document.getElementById('edit-slot-id').value = '';
        document.getElementById('submit-btn').style.display = 'inline-block';
        document.getElementById('update-btn').style.display = 'none';
    }

    // Update button click handler
    document.getElementById('update-btn').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('new-time-slot-form').dispatchEvent(new Event('submit'));
    });
});
    </script>
</body>
</html>