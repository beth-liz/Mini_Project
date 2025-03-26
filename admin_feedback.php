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

// Add at the top of the file
require_once 'db_connect.php'; // Updated path to database connection file

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
            text-decoration: none;
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

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-edit {
            background-color: #f39c12;
            color: white;
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

        .error-message {
    color: #ff6b6b;
    font-size: 0.9em;
    margin-top: 5px;
    display: none;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.error {
    border-color: #ff6b6b !important;
}

/* Add these styles to your existing <style> section */
.filter-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.data-table td {
    vertical-align: top;
}

/* Modal styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.7);
}

.modal-content {
    background: rgba(76, 132, 196, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    margin: 10% auto;
    padding: 20px;
    width: 60%;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.close {
    color: white;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: var(--primary-color);
    text-decoration: none;
}

.modal-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

#feedback-details {
    margin: 20px 0;
}

/* Star rating styling */
td {
    color: white;
}

td:nth-child(6) {
    color: gold;
    font-size: 1.2em;
    letter-spacing: 2px;
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
                <li class="sidebar-nav-item "><a href="admin_activities.php">Activities</a></li>
                <li class="sidebar-nav-item"><a href="admin_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="admin_membership.php">Membership</a></li>
                <li class="sidebar-nav-item"><a href="admin_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="admin_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="admin_events.php">Events</a></li>
                <li class="sidebar-nav-item active"><a href="admin_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <!-- Add activities section -->
        <div id="activities-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>User Feedback</h1>
                <div class="header-actions">
                    
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

            <div class="section-content">
                <div class="dashboard-header">
                    <h2>User Feedback</h2>
                    <div class="filter-controls">
                        <select id="filter-activity-type" class="btn btn-secondary">
                            <option value="">All Activity Types</option>
                            <?php
                            try {
                                // Fetch distinct activity types from both feedback table AND activity table
                                $activityQuery = "SELECT DISTINCT activity_type FROM activity 
                                                 UNION 
                                                 SELECT DISTINCT activity_type FROM feedback 
                                                 ORDER BY activity_type";
                                $activityStmt = $conn->query($activityQuery);
                                
                                if ($activityStmt) {
                                    if ($activityStmt->rowCount() > 0) {
                                        while ($activityRow = $activityStmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='" . htmlspecialchars($activityRow['activity_type']) . "'>" . 
                                                 htmlspecialchars($activityRow['activity_type']) . "</option>";
                                        }
                                    } else {
                                        echo "<option value='' disabled>No activity types found</option>";
                                        
                                        // Debug info in a comment
                                        echo "<!-- No activity types found in either table -->";
                                    }
                                } else {
                                    echo "<option value='' disabled>Error loading activity types</option>";
                                    
                                    // Debug info in a comment
                                    echo "<!-- Query error: " . htmlspecialchars($conn->errorInfo()[2]) . " -->";
                                }
                            } catch (PDOException $e) {
                                echo "<option value='' disabled>Error: " . htmlspecialchars($e->getMessage()) . "</option>";
                            }
                            
                            // Additional debug query to see what's in the activity table
                            try {
                                echo "<!-- Activity types in activity table: ";
                                $debugQuery = "SELECT activity_id, activity_type FROM activity";
                                $debugStmt = $conn->query($debugQuery);
                                
                                if ($debugStmt && $debugStmt->rowCount() > 0) {
                                    while ($row = $debugStmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "[ID: " . $row['activity_id'] . ", Type: " . $row['activity_type'] . "] ";
                                    }
                                } else {
                                    echo "No activity types found in activity table";
                                }
                                echo " -->";
                            } catch (PDOException $e) {
                                echo "<!-- Debug error: " . htmlspecialchars($e->getMessage()) . " -->";
                            }
                            ?>
                        </select>
                        
                        <select id="filter-status" class="btn btn-secondary">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Reviewed">Reviewed</option>
                        </select>
                        
                        <select id="filter-rating" class="btn btn-secondary">
                            <option value="">All Ratings</option>
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>
                </div>

                <!-- Debug information - Remove in production -->
                <div style="background: rgba(0,0,0,0.5); padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                    <h3>Debug Information</h3>
                    <?php
                    // Check if database connection is working
                    try {
                        $conn->query("SELECT 1");
                        echo "<p>✅ Database connection is working</p>";
                    } catch (PDOException $e) {
                        echo "<p>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    
                    // Check if feedback table exists and has data
                    try {
                        $tableCheckQuery = "SELECT 1 FROM feedback LIMIT 1";
                        $conn->query($tableCheckQuery);
                        
                        echo "<p>✅ Feedback table exists</p>";
                        
                        // Check if there's data in the feedback table
                        $countQuery = "SELECT COUNT(*) as count FROM feedback";
                        $countStmt = $conn->query($countQuery);
                        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
                        
                        echo "<p>📊 Total feedback records: " . $countRow['count'] . "</p>";
                    } catch (PDOException $e) {
                        echo "<p>❌ Error with feedback table: " . htmlspecialchars($e->getMessage()) . "</p>";
                        
                        // Check if the table exists
                        try {
                            $tablesQuery = "SHOW TABLES";
                            $tablesStmt = $conn->query($tablesQuery);
                            
                            echo "<p>Available tables in database:</p><ul>";
                            while ($tableRow = $tablesStmt->fetch(PDO::FETCH_NUM)) {
                                echo "<li>" . htmlspecialchars($tableRow[0]) . "</li>";
                            }
                            echo "</ul>";
                        } catch (PDOException $e2) {
                            echo "<p>❌ Error listing tables: " . htmlspecialchars($e2->getMessage()) . "</p>";
                        }
                    }
                    ?>
                </div>

                <table class="data-table" id="feedback-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Activity Type</th>
                            <th>Activity Name</th>
                            <th>Feedback</th>
                            <th>Rating</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // First, let's check the structure of the users table to see available columns
                            $userStructureQuery = "DESCRIBE users";
                            try {
                                $userStructureStmt = $conn->query($userStructureQuery);
                                echo "<!-- User table columns: ";
                                $userColumns = [];
                                while ($column = $userStructureStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $userColumns[] = $column['Field'];
                                }
                                echo implode(", ", $userColumns);
                                echo " -->";
                                
                                // Determine which columns to use for user identification
                                $nameColumn = in_array('name', $userColumns) ? 'name' : 
                                             (in_array('username', $userColumns) ? 'username' : 'email');
                                
                                // Query to get all feedback with available user information
                                $query = "SELECT f.*, u.email, u.$nameColumn AS user_name
                                          FROM feedback f
                                          JOIN users u ON f.user_id = u.user_id
                                          ORDER BY f.feedback_date DESC, f.feedback_time DESC";
                                
                                $stmt = $conn->query($query);
                                
                                if ($stmt && $stmt->rowCount() > 0) {
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr data-id='" . htmlspecialchars($row['feedback_id']) . "' 
                                                  data-activity-type='" . htmlspecialchars($row['activity_type']) . "' 
                                                  data-status='" . htmlspecialchars($row['status']) . "' 
                                                  data-rating='" . htmlspecialchars($row['rating']) . "'>";
                                        
                                        echo "<td>" . htmlspecialchars($row['feedback_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['user_name']) . 
                                             "<br><small>" . htmlspecialchars($row['email']) . "</small></td>";
                                        echo "<td>" . htmlspecialchars($row['activity_type']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['activity_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['feedback_content']) . "</td>";
                                        
                                        // Display stars for rating
                                        echo "<td>";
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $row['rating']) {
                                                echo "★"; // Filled star
                                            } else {
                                                echo "☆"; // Empty star
                                            }
                                        }
                                        echo "</td>";
                                        
                                        echo "<td>" . htmlspecialchars($row['feedback_date']) . "</td>";
                                        
                                        // Status with appropriate styling
                                        $statusClass = ($row['status'] == 'Reviewed') ? 'status-active' : 'status-inactive';
                                        echo "<td><span class='$statusClass'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        
                                        // Action buttons
                                        echo "<td class='action-buttons'>
                                                <button class='btn btn-edit mark-reviewed' data-id='" . $row['feedback_id'] . "'>
                                                    Mark as Reviewed
                                                </button>
                                                <button class='btn btn-danger delete-feedback' data-id='" . $row['feedback_id'] . "'>
                                                    Delete
                                                </button>
                                              </td>";
                                        
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='9' style='text-align:center;'>No feedback available</td></tr>";
                                }
                                
                            } catch (PDOException $structureError) {
                                // Simpler fallback query without joining to users table
                                echo "<!-- Error checking users table structure: " . htmlspecialchars($structureError->getMessage()) . " -->";
                                echo "<!-- Using simplified query without user details -->";
                                
                                $simpleQuery = "SELECT * FROM feedback ORDER BY feedback_date DESC, feedback_time DESC";
                                $simpleStmt = $conn->query($simpleQuery);
                                
                                if ($simpleStmt && $simpleStmt->rowCount() > 0) {
                                    while ($row = $simpleStmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr data-id='" . htmlspecialchars($row['feedback_id']) . "' 
                                                  data-activity-type='" . htmlspecialchars($row['activity_type']) . "' 
                                                  data-status='" . htmlspecialchars($row['status']) . "' 
                                                  data-rating='" . htmlspecialchars($row['rating']) . "'>";
                                        
                                        echo "<td>" . htmlspecialchars($row['feedback_id']) . "</td>";
                                        echo "<td>User ID: " . htmlspecialchars($row['user_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['activity_type']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['activity_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['feedback_content']) . "</td>";
                                        
                                        // Display stars for rating
                                        echo "<td>";
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $row['rating']) {
                                                echo "★"; // Filled star
                                            } else {
                                                echo "☆"; // Empty star
                                            }
                                        }
                                        echo "</td>";
                                        
                                        echo "<td>" . htmlspecialchars($row['feedback_date']) . "</td>";
                                        
                                        // Status with appropriate styling
                                        $statusClass = ($row['status'] == 'Reviewed') ? 'status-active' : 'status-inactive';
                                        echo "<td><span class='$statusClass'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        
                                        // Action buttons
                                        echo "<td class='action-buttons'>
                                                <button class='btn btn-edit mark-reviewed' data-id='" . $row['feedback_id'] . "'>
                                                    Mark as Reviewed
                                                </button>
                                                <button class='btn btn-danger delete-feedback' data-id='" . $row['feedback_id'] . "'>
                                                    Delete
                                                </button>
                                              </td>";
                                        
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='9' style='text-align:center;'>No feedback available</td></tr>";
                                }
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='9' style='text-align:center; color:red;'>Query Error: " . 
                                 htmlspecialchars($e->getMessage()) . "</td></tr>";
                            
                            // Let's provide more detailed error info for debugging
                            echo "<tr><td colspan='9' style='text-align:left; color:orange;'>";
                            echo "<strong>Debugging Information:</strong><br>";
                            echo "Error Code: " . htmlspecialchars($e->getCode()) . "<br>";
                            echo "Let's check if the feedback table exists and has the expected structure:<br>";
                            
                            try {
                                $tableCheck = $conn->query("SHOW TABLES LIKE 'feedback'");
                                if ($tableCheck && $tableCheck->rowCount() > 0) {
                                    echo "✅ Feedback table exists<br>";
                                    
                                    // Check table structure
                                    echo "Feedback table structure:<br><ul>";
                                    $structureCheck = $conn->query("DESCRIBE feedback");
                                    while ($column = $structureCheck->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<li>" . htmlspecialchars($column['Field']) . " - " . 
                                             htmlspecialchars($column['Type']) . "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "❌ Feedback table does not exist<br>";
                                }
                            } catch (PDOException $debugError) {
                                echo "Error checking table structure: " . htmlspecialchars($debugError->getMessage());
                            }
                            
                            echo "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal for viewing feedback details -->
            <div id="feedback-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Feedback Details</h2>
                    <div id="feedback-details"></div>
                    <div class="modal-actions">
                        <button id="modal-mark-reviewed" class="btn btn-primary">Mark as Reviewed</button>
                        <button id="modal-close" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add this before the closing </body> tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filters for feedback table
        const activityTypeFilter = document.getElementById('filter-activity-type');
        const statusFilter = document.getElementById('filter-status');
        const ratingFilter = document.getElementById('filter-rating');
        const feedbackRows = document.querySelectorAll('#feedback-table tbody tr');
        
        // Function to apply filters
        function applyFilters() {
            const activityType = activityTypeFilter.value;
            const status = statusFilter.value;
            const rating = ratingFilter.value;
            
            feedbackRows.forEach(row => {
                const rowActivityType = row.getAttribute('data-activity-type');
                const rowStatus = row.getAttribute('data-status');
                const rowRating = row.getAttribute('data-rating');
                
                const activityTypeMatch = !activityType || rowActivityType === activityType;
                const statusMatch = !status || rowStatus === status;
                const ratingMatch = !rating || rowRating === rating;
                
                if (activityTypeMatch && statusMatch && ratingMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Add event listeners to filters
        if (activityTypeFilter) activityTypeFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
        if (ratingFilter) ratingFilter.addEventListener('change', applyFilters);
        
        // Mark as reviewed functionality
        const reviewButtons = document.querySelectorAll('.mark-reviewed');
        reviewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const feedbackId = this.getAttribute('data-id');
                updateFeedbackStatus(feedbackId, 'Reviewed');
            });
        });
        
        // Delete feedback functionality
        const deleteButtons = document.querySelectorAll('.delete-feedback');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete this feedback?')) {
                    const feedbackId = this.getAttribute('data-id');
                    deleteFeedback(feedbackId);
                }
            });
        });
        
        // Function to update feedback status
        function updateFeedbackStatus(feedbackId, status) {
            fetch('update_feedback_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `feedback_id=${feedbackId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback status updated successfully');
                    // Reload the page to reflect changes
                    location.reload();
                } else {
                    alert('Failed to update feedback status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the feedback status');
            });
        }
        
        // Function to delete feedback
        function deleteFeedback(feedbackId) {
            fetch('delete_feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `feedback_id=${feedbackId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback deleted successfully');
                    // Reload the page to reflect changes
                    location.reload();
                } else {
                    alert('Failed to delete feedback: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the feedback');
            });
        }
    });
    </script>
</body>
</html>