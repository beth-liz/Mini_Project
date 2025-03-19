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

// Add at the top of the file
require_once 'db_connect.php'; // Updated path to database connection file

// Add this at the very top of your PHP code, after require_once 'db_connect.php';
$activeSection = 'overview';

function getMemberships() {
    global $conn;
    $sql = "SELECT * FROM memberships ORDER BY membership_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$memberships = getMemberships();

// Add these functions after getMemberships()
function validateMembershipType($type, $conn) {
    // Check if empty
    if (empty($type)) {
        return "Membership type cannot be empty";
    }
    
    // Check length
    if (strlen($type) < 3) {
        return "Membership type must be at least 3 characters long";
    }
    
    // Check for special characters and numbers
    if (!preg_match('/^[a-zA-Z\s]+$/', $type)) {
        return "Membership type can only contain letters and spaces";
    }
    
    // Check if membership type already exists
    $sql = "SELECT COUNT(*) FROM memberships WHERE LOWER(membership_type) = LOWER(:type)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['type' => $type]);
    if ($stmt->fetchColumn() > 0) {
        return "This membership type already exists";
    }
    
    return null; // Return null if validation passes
}

function validateMembershipPrice($price) {
    // Allow empty input for optional price
    if ($price === '') {
        return null; // Price is optional
    }
    
    // Check if the price is numeric
    if (!is_numeric($price)) {
        return "Price must be a number";
    }
    
    // Convert to float for further checks
    $price = floatval($price);
    
    // Check for negative values
    if ($price < 0) {
        return "Price cannot be negative";
    }
    
    // Check for maximum value
    if ($price > 3000) {
        return "Price cannot exceed $3000";
    }
    
    return null; // Return null if validation passes
}

// Update the form processing logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['membership_type'])) {
    $membership_type = trim($_POST['membership_type']);
    $membership_price = !empty($_POST['membership_price']) ? trim($_POST['membership_price']) : '0'; // Default to '0' if empty
    
    // Validate membership type
    $type_error = validateMembershipType($membership_type, $conn);
    $price_error = validateMembershipPrice($membership_price);
    
    if (!$type_error && !$price_error) {
        // Store the price as is, including 0
        try {
            $sql = "INSERT INTO memberships (membership_type, membership_price) 
                    VALUES (:membership_type, :membership_price)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'membership_type' => $membership_type,
                'membership_price' => $membership_price
            ]);
            
            // Set success message
            $success_message = "Membership added successfully!";
            
            // Redirect to the same page to prevent resubmission
            header("Location: " . $_SERVER['PHP_SELF']);
            exit(); // Ensure no further code is executed after redirection
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } else {
        $error_message = $type_error ?: $price_error;
    }
}

// Clear messages after redirection
if (isset($success_message) || isset($error_message)) {
    unset($success_message);
    unset($error_message);
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

        /* Add this new style for disabled buttons */
        .btn.disabled {
            opacity: 0.5; /* Make the button look disabled */
            pointer-events: none; /* Prevent any interaction */
            cursor: not-allowed; /* Change cursor to indicate disabled state */
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
                <li class="sidebar-nav-item active"><a href="manager_membership.php">Membership</a></li>
                <li class="sidebar-nav-item"><a href="manager_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="manager_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="manager_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="manager_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <!-- Add memberships section -->
        <div id="membership-section" class="dashboard-section">
            <header class="dashboard-header" style="text-align: center;">
                <h1>Membership Management</h1>
                <div class="header-actions" style="text-align: center;">
                    <div class="notifications" style="display: inline-block;">
                        <span class="notifications-icon">🔔</span>
                        <span class="notifications-badge">3</span>
                    </div>
                    <div class="dropdown" style="display: inline-block;">
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

            <section class="section-content">
                <!-- Memberships table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Membership Type</th>
                            <th>Price</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberships as $membership): ?>
                            <tr>
                                <td><?php echo $membership['membership_id']; ?></td>
                                <td><?php echo htmlspecialchars($membership['membership_type']); ?></td>
                                <td>
                                    <?php 
                                    // Display 'Free' if the membership price is 0
                                    if ($membership['membership_price'] === '0') {
                                        echo 'Free';
                                    } elseif ($membership['membership_price'] === null) {
                                        echo 'Free'; // If null, also display as Free
                                    } else {
                                        echo '₹' . number_format($membership['membership_price'], 0); // No decimal places
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-buttons" style="display: flex; justify-content: center;">
                                        <button class="btn btn-edit" data-membershipid="<?php echo $membership['membership_id']; ?>" disabled style="cursor: not-allowed;">Edit</button>
                                        <button class="btn btn-danger" data-membershipid="<?php echo $membership['membership_id']; ?>" disabled style="cursor: not-allowed;">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
</body>
</html>