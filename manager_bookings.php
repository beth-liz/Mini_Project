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

$activeSection = 'bookings';

function getBookings() {
    global $conn;
    $sql = "SELECT b.*, 
            u.name as user_name,
            sa.sub_activity_name,
            a.activity_type,
            ts.slot_date,
            ts.slot_start_time,
            ts.slot_end_time
            FROM booking b
            JOIN users u ON b.user_id = u.user_id
            JOIN sub_activity sa ON b.sub_activity_id = sa.sub_activity_id
            JOIN activity a ON sa.activity_id = a.activity_id
            JOIN timeslots ts ON b.slot_id = ts.slot_id
            ORDER BY b.booking_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$bookings = getBookings();

// Add this near the top where other form processing happens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    $user_id = $_POST['user_id'];
    $sub_activity_id = $_POST['sub_activity_id'];
    $slot_id = $_POST['slot_id'];
    $membership_id = $_POST['membership_id'];
    
    if (!empty($user_id) && !empty($sub_activity_id) && !empty($slot_id) && !empty($membership_id)) {
        try {
            $sql = "INSERT INTO booking (user_id, sub_activity_id, slot_id, booking_date, booking_time, membership_id) 
                    VALUES (:user_id, :sub_activity_id, :slot_id, CURDATE(), CURTIME(), :membership_id)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'user_id' => $user_id,
                'sub_activity_id' => $sub_activity_id,
                'slot_id' => $slot_id,
                'membership_id' => $membership_id
            ]);
            
            // Refresh the bookings list
            $bookings = getBookings();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
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
                <li class="sidebar-nav-item active"><a href="manager_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="manager_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="manager_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div id="bookings-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>Booking Management</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" id="add-booking-btn">Add New Booking</button>
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
                            <a href="signin.php" class="dropdown-item">Log Out</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="section-content">
                <!-- Add booking form -->
                <div id="add-booking-form" style="display: none; margin-bottom: 20px;">
                    <form method="POST" class="form-container">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="user_id" required 
                                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                           background: rgba(255,255,255,0.1); color: white;">
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="sub_activity_id" required 
                                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                           background: rgba(255,255,255,0.1); color: white;">
                                <option value="">Select Activity</option>
                                <?php foreach ($subActivities as $subActivity): ?>
                                    <option value="<?php echo $subActivity['sub_activity_id']; ?>">
                                        <?php echo htmlspecialchars($subActivity['activity_type'] . ' - ' . $subActivity['sub_activity_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="slot_id" required 
                                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                           background: rgba(255,255,255,0.1); color: white;">
                                <option value="">Select Time Slot</option>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?php echo $slot['slot_id']; ?>">
                                        <?php echo date('Y-m-d', strtotime($slot['slot_date'])) . ' ' . 
                                                 date('H:i', strtotime($slot['slot_start_time'])) . '-' . 
                                                 date('H:i', strtotime($slot['slot_end_time'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="membership_id" required 
                                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                           background: rgba(255,255,255,0.1); color: white;">
                                <option value="">Select Membership</option>
                                <?php foreach ($memberships as $membership): ?>
                                    <option value="<?php echo $membership['membership_id']; ?>">
                                        <?php echo htmlspecialchars($membership['membership_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="add_booking" value="1">
                            <button type="submit" class="btn btn-primary">Save Booking</button>
                            <button type="button" class="btn btn-secondary" id="cancel-booking-btn">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Bookings table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>User</th>
                            <th>Activity</th>
                            <th>Sub-Activity</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Membership</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking['booking_id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['activity_type']); ?></td>
                                <td><?php echo htmlspecialchars($booking['sub_activity_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($booking['booking_time'])); ?></td>
                                <td><?php echo isset($booking['user_membership_type']) ? htmlspecialchars($booking['user_membership_type']) : ''; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-edit" data-bookingid="<?php echo $booking['booking_id']; ?>">Edit</button>
                                        <button class="btn btn-danger" data-bookingid="<?php echo $booking['booking_id']; ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <!-- Add JavaScript for navigation -->
    <script>

        document.getElementById('add-booking-btn').addEventListener('click', function() {
            document.getElementById('add-booking-form').style.display = 'block';
            this.style.display = 'none';
        });

        document.getElementById('cancel-booking-btn').addEventListener('click', function() {
            document.getElementById('add-booking-form').style.display = 'none';
            document.getElementById('add-booking-btn').style.display = 'block';
        });
    </script>
</body>
</html>