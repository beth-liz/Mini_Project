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

// Add after require_once 'db_connect.php';

function isSubActivityNameUnique($name, $conn, $currentId = null) {
    $sql = "SELECT COUNT(*) FROM sub_activity WHERE LOWER(sub_activity_name) = LOWER(:name)";
    if ($currentId) {
        $sql .= " AND sub_activity_id != :currentId"; // Exclude the current ID
    }
    $stmt = $conn->prepare($sql);
    $params = ['name' => $name];
    if ($currentId) {
        $params['currentId'] = $currentId; // Add current ID to parameters
    }
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}

function validateSubActivityName($name) {
    return strlen($name) >= 3 && preg_match('/^[A-Za-z\s]+$/', $name);
}

function validatePrice($price) {
    return is_numeric($price) && $price >= 0 && $price <= 3000;
}

function validateImage($image) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    return in_array($image['type'], $allowedTypes);
}
// Add this at the very top of your PHP code, after require_once 'db_connect.php';
$activeSection = 'sub-activities';

// Update the sub-activity form submission section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_activity_name'])) {
    $sub_activity_name = trim($_POST['sub_activity_name']);
    $activity_id = $_POST['activity_id'];
    $membership_type = $_POST['membership_type']; // Capture membership_type from the form
    $sub_activity_id = $_POST['sub_activity_id'] ?? null; // Get the sub_activity_id if it exists
    
    $errors = [];

    // Validate name
    if (!validateSubActivityName($sub_activity_name)) {
        $errors[] = "Sub-activity name must be at least 3 characters long and contain only letters and spaces";
    }

    // Check if there are no errors
    if (empty($errors)) {
        if ($sub_activity_id) {
            // Update existing record in sub_activity_name
            $sql = "UPDATE sub_activity_name SET activity_id = :activity_id, sub_act_name = :sub_activity_name, 
                    membership_type = :membership_type 
                    WHERE sub_act_id = :sub_activity_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'activity_id' => $activity_id,
                'sub_activity_name' => $sub_activity_name,
                'membership_type' => $membership_type, // Include membership_type in the update
                'sub_activity_id' => $sub_activity_id
            ]);
            $_SESSION['sub_activity_success_message'] = "Sub-activity updated successfully!";
        } else {
            // Insert new record into sub_activity_name
            $sql = "INSERT INTO sub_activity_name (activity_id, sub_act_name, membership_type) 
                    VALUES (:activity_id, :sub_activity_name, :membership_type)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'activity_id' => $activity_id,
                'sub_activity_name' => $sub_activity_name,
                'membership_type' => $membership_type // Include membership_type in the insert
            ]);
            $_SESSION['sub_activity_success_message'] = "Sub-activity added successfully!";
        }
    } else {
        $_SESSION['sub_activity_error_message'] = implode(", ", $errors);
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?section=sub-activities");
    exit();
}
// Fetch users from database
function getUsers() {
    global $conn;
    $sql = "SELECT u.user_id, u.name, u.email, u.mobile, m.membership_type, u.role 
            FROM users u 
            JOIN memberships m ON u.membership_id = m.membership_id
            ORDER BY u.user_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$users = getUsers();

function getActivities() {
    global $conn;
    $sql = "SELECT * FROM activity ORDER BY activity_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$activities = getActivities();

function getSubActivities() {
    global $conn;
    $sql = "SELECT san.sub_act_id, san.sub_act_name, a.activity_type, san.activity_id, san.membership_type 
            FROM sub_activity_name san 
            JOIN activity a ON san.activity_id = a.activity_id 
            ORDER BY san.sub_act_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$subActivities = getSubActivities();

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
            background-image: url('img/r5.jpg');
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
            justify-content: center;
            gap: 10px;
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

        .centered-table {
            width: 100%;
            text-align: center;
        }
        .centered-table th, .centered-table td {
            text-align: center;
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
                <li class="sidebar-nav-item"><a href="admin_manager.php">Manager</a></li>
                <li class="sidebar-nav-item"><a href="admin_activities.php">Activities</a></li>
                <li class="sidebar-nav-item active"><a href="admin_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="admin_membership.php">Membership</a></li>
                <li class="sidebar-nav-item"><a href="admin_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="admin_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="admin_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="admin_payments.php">Payments</a></li>
                <li class="sidebar-nav-item"><a href="admin_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <!-- Add sub-activities section -->
        <div id="sub-activities-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>Sub-Activity Management</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" id="add-sub-activity-btn">Add New Sub-Activity</button>
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

            <section class="section-content">
                <!-- Add after <section class="section-content"> -->
<?php if (isset($_SESSION['sub_activity_error_message'])): ?>
    <div id="error-message" style="background: rgba(231, 76, 60, 0.2); color: #e74c3c; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
        <?php 
        echo $_SESSION['sub_activity_error_message'];
        unset($_SESSION['sub_activity_error_message']); // Clear the message after displaying
        ?>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('error-message').style.display = 'none';
        }, 2000); // Hide after 2 seconds
    </script>
<?php endif; ?>

<?php if (isset($_SESSION['sub_activity_success_message'])): ?>
    <div id="success-message" style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
        <?php 
        echo $_SESSION['sub_activity_success_message'];
        unset($_SESSION['sub_activity_success_message']); // Clear the message after displaying
        ?>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('success-message').style.display = 'none';
        }, 2000); // Hide after 2 seconds
    </script>
<?php endif; ?>
                <!-- Add sub-activity form -->
                <div id="add-sub-activity-form" style="display: none; margin-bottom: 20px;">
                    <form method="POST" class="form-container" enctype="multipart/form-data">
                        <input type="hidden" name="active_section" value="sub-activities">
                        <input type="hidden" name="sub_activity_id" value="">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="activity_id" required 
                                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                           background: rgba(255,255,255,0.1); color: white;">
                                <option value="">Select Activity</option>
                                <?php foreach ($activities as $activity): ?>
                                    <option value="<?php echo $activity['activity_id']; ?>">
                                        <?php echo htmlspecialchars($activity['activity_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="sub_activity_name" placeholder="Enter Sub-Activity Name" required
                                   style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                          background: rgba(255,255,255,0.1); color: white;">
                            <select name="membership_type" required 
                                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                           background: rgba(255,255,255,0.1); color: white;">
                                <option value="">Select Membership Type</option>
                                <option value="normal">Normal</option>
                                <option value="standard">Standard</option>
                                <option value="premium">Premium</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Save Sub-Activity</button>
                            <button type="button" class="btn btn-secondary" id="cancel-sub-activity-btn">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Sub-activities table -->
                <table class="data-table centered-table">
                    <thead>
                        <tr>
                            <th>Activity Type</th>
                            <th>Sub-Activity Name</th>
                            <th>Membership Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subActivities as $subActivity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subActivity['activity_type']); ?></td>
                                <td><?php echo htmlspecialchars($subActivity['sub_act_name']); ?></td>
                                <td><?php echo htmlspecialchars($subActivity['membership_type']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-edit" data-subactivityid="<?php echo $subActivity['sub_act_id']; ?>">Edit</button>
                                        <button class="btn btn-danger" data-subactivityid="<?php echo $subActivity['sub_act_id']; ?>">Delete</button>
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

        // Add sub-activity form toggle functionality
        document.getElementById('add-sub-activity-btn').addEventListener('click', function() {
            document.getElementById('add-sub-activity-form').style.display = 'block';
            this.style.display = 'none';
        });

        document.getElementById('cancel-sub-activity-btn').addEventListener('click', function() {
            document.getElementById('add-sub-activity-form').style.display = 'none';
            document.getElementById('add-sub-activity-btn').style.display = 'block';
        });

        // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const subActivityName = document.querySelector('input[name="sub_activity_name"]').value;
        const price = document.querySelector('input[name="sub_activity_price"]').value;
        const imageInput = document.querySelector('input[name="sub_activity_image"]');
        
        // Name validation
        const nameRegex = /^[A-Za-z\s]{3,}$/;
        if (!nameRegex.test(subActivityName)) {
            e.preventDefault();
            alert('Sub-activity name must be at least 3 characters long and contain only letters and spaces');
            return;
        }

        
        
    });

    // Edit functionality
document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', function() {
        const subActivityId = this.dataset.subactivityid;

        // Find the sub-activity in the subActivities array
        const subActivity = <?php echo json_encode($subActivities); ?>.find(sa => sa.sub_act_id == subActivityId);
        
        if (subActivity) {
            // Show the form and update its values
            const form = document.querySelector('#add-sub-activity-form');
            form.style.display = 'block'; // Ensure the form is displayed
            document.getElementById('add-sub-activity-btn').style.display = 'none'; // Hide the add button
            
            // Update form fields
            form.querySelector('select[name="activity_id"]').value = subActivity.activity_id; // Set activity ID
            form.querySelector('input[name="sub_activity_name"]').value = subActivity.sub_act_name; // Set sub activity name
            
            // Populate hidden field for sub_activity_id
            form.querySelector('input[name="sub_activity_id"]').value = subActivityId; // Set the hidden field value
            
            // Scroll to the top of the page
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            alert('Failed to fetch sub-activity details');
        }
    });
});

// Delete functionality
document.querySelectorAll('.btn-danger').forEach(button => {
    button.addEventListener('click', async function() {
        if (confirm('Are you sure you want to delete this sub-activity?')) {
            const subActivityId = this.dataset.subactivityid;
            
            try {
                const formData = new FormData();
                formData.append('sub_activity_id', subActivityId);
                
                const response = await fetch('delete_sub_activity.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Remove the row from the table
                    this.closest('tr').remove();
                    alert('Sub-activity deleted successfully');
                } else {
                    alert(result.message || 'Failed to delete sub-activity');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the sub-activity');
            }
        }
    });
});
    </script>
    
</body>
</html>