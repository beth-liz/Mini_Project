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

// Add at the top of the file
require_once 'db_connect.php'; // Updated path to database connection file

// Add after require_once 'db_connect.php';

function isSubActivityCombinationUnique($activity_id, $sub_act_id, $conn, $sub_activity_id = null) {
    $sql = "SELECT COUNT(*) FROM sub_activity 
            WHERE activity_id = :activity_id AND sub_act_id = :sub_act_id";
    
    if ($sub_activity_id) {
        $sql .= " AND sub_activity_id != :sub_activity_id"; // Exclude current record if editing
    }
    
    $stmt = $conn->prepare($sql);
    $params = [
        'activity_id' => $activity_id,
        'sub_act_id' => $sub_act_id
    ];
    
    if ($sub_activity_id) {
        $params['sub_activity_id'] = $sub_activity_id;
    }
    
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}

function validateSubActivityName($name) {
    return strlen($name) >= 3 && preg_match('/^[A-Za-z\s]+$/', $name);
}

function validatePrice($price) {
    return is_numeric($price) && $price >= 0 && $price <= 1000;
}

function validateImage($image) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    return in_array($image['type'], $allowedTypes);
}
// Add this at the very top of your PHP code, after require_once 'db_connect.php';
$activeSection = 'sub-activities';

// Handle form submission for both adding and updating sub-activities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_id = $_POST['activity_id'];
    $sub_act_id = $_POST['sub_act_id'];
    $sub_activity_price = $_POST['sub_activity_price'];
    $sub_activity_image = $_FILES['sub_activity_image'];
    $current_image = isset($_POST['current_image']) ? $_POST['current_image'] : '';
    
    $errors = [];

    // Validate price
    if (!validatePrice($sub_activity_price)) {
        $errors[] = "Price must be between 0 and 1000";
    }

    // Check if this is an update or new record
    $isUpdate = isset($_POST['sub_activity_id']) && !empty($_POST['sub_activity_id']);
    $sub_activity_id = $isUpdate ? $_POST['sub_activity_id'] : null;
    
    // Validate uniqueness of activity_id and sub_act_id combination
    if (!isSubActivityCombinationUnique($activity_id, $sub_act_id, $conn, $sub_activity_id)) {
        $errors[] = "This sub-activity already exists for the selected activity";
    }

    // Initialize image path variable
    $imagePath = $isUpdate ? $current_image : null;

    // Check if a new image is uploaded
    if ($sub_activity_image['name'] && $sub_activity_image['error'] == UPLOAD_ERR_OK) {
        // Validate the new image
        if (!validateImage($sub_activity_image)) {
            $errors[] = "Only JPG, JPEG, and PNG files are allowed";
        } else {
            // Attempt to move the uploaded file
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo($sub_activity_image['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid('sub_activity_', true) . '.' . $fileExtension;
            $imagePath = $uploadDir . $newFileName;

            // Move the uploaded file
            if (!move_uploaded_file($sub_activity_image['tmp_name'], $imagePath)) {
                $errors[] = "Failed to upload image. Error Code: " . $sub_activity_image['error'];
            }
        }
    } elseif (!$isUpdate && $sub_activity_image['error'] == UPLOAD_ERR_NO_FILE) {
        // Require image for new records
        $errors[] = "Image is required for new sub-activities";
    } elseif ($sub_activity_image['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $errors[] = "An error occurred during file upload. Error Code: " . $sub_activity_image['error'];
    }

    if (empty($errors)) {
        if ($isUpdate) {
            // Update existing sub-activity
            $sql = "UPDATE sub_activity 
                    SET activity_id = :activity_id, 
                        sub_act_id = :sub_act_id, 
                        sub_activity_price = :sub_activity_price";
            
            // Only update image if we have a new one
            if ($imagePath && $imagePath !== $current_image) {
                $sql .= ", sub_activity_image = :sub_activity_image";
            }
            
            $sql .= " WHERE sub_activity_id = :sub_activity_id";
            
            $stmt = $conn->prepare($sql);
            $params = [
                'activity_id' => $activity_id,
                'sub_act_id' => $sub_act_id,
                'sub_activity_price' => $sub_activity_price,
                'sub_activity_id' => $sub_activity_id
            ];
            
            // Only add image parameter if updated
            if ($imagePath && $imagePath !== $current_image) {
                $params['sub_activity_image'] = $imagePath;
            }
            
            // Execute the statement and check for errors
            if ($stmt->execute($params)) {
                $_SESSION['sub_activity_success_message'] = "Sub-activity updated successfully!";
            } else {
                // Log the error message
                $_SESSION['sub_activity_error_message'] = "Failed to update sub-activity: " . implode(", ", $stmt->errorInfo());
            }
        } else {
            // Insert new sub-activity
            $sql = "INSERT INTO sub_activity (activity_id, sub_act_id, sub_activity_price, sub_activity_image) 
                    VALUES (:activity_id, :sub_act_id, :sub_activity_price, :sub_activity_image)";
            
            $stmt = $conn->prepare($sql);
            $params = [
                'activity_id' => $activity_id,
                'sub_act_id' => $sub_act_id,
                'sub_activity_price' => $sub_activity_price,
                'sub_activity_image' => $imagePath
            ];
            
            // Execute the statement and check for errors
            if ($stmt->execute($params)) {
                $_SESSION['sub_activity_success_message'] = "Sub-activity added successfully!";
            } else {
                // Log the error message
                $_SESSION['sub_activity_error_message'] = "Failed to add sub-activity: " . implode(", ", $stmt->errorInfo());
            }
        }
    } else {
        $_SESSION['sub_activity_error_message'] = implode(", ", $errors);
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
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
    $sql = "SELECT sa.*, a.activity_type, san.membership_type 
            FROM sub_activity sa 
            JOIN activity a ON sa.activity_id = a.activity_id 
            JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id 
            ORDER BY sa.sub_activity_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$subActivities = getSubActivities();

?><!DOCTYPE html>
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
                <li class="sidebar-nav-item active"><a href="manager_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_membership.php">Membership</a></li>
                <li class="sidebar-nav-item"><a href="manager_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="manager_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="manager_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="manager_payments.php">Payments</a></li>
                <li class="sidebar-nav-item"><a href="manager_feedback.php">Feedback</a></li>
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
        }, 5000); // Hide after 5 seconds
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
        }, 5000); // Hide after 5 seconds
    </script>
<?php endif; ?>
               <!-- Add sub-activity form -->
<div id="add-sub-activity-form" style="display: none; margin-bottom: 20px;">
    <form method="POST" class="form-container" enctype="multipart/form-data" action="manager_sub_activities.php">
        <input type="hidden" name="sub_activity_id" value="">
        <input type="hidden" name="current_image" value="">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
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
            <select name="sub_act_id" required 
                    style="padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                           background: rgba(255,255,255,0.1); color: white;">
                <option value="">Select Sub-Activity</option>
                <!-- This will be populated dynamically based on the selected activity -->
            </select>
            <div style="flex: 1; min-width: 200px;">
                <input type="number" id="sub_activity_price" name="sub_activity_price" placeholder="Price" step="0.01" required
                       style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                              background: rgba(255,255,255,0.1); color: white;">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <div id="file-input-container">
                    <input type="file" id="sub_activity_image" name="sub_activity_image"
                           style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                  background: rgba(255,255,255,0.1); color: white;">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Sub-Activity</button>
            <button type="button" class="btn btn-secondary" id="cancel-sub-activity-btn">Cancel</button>
        <!-- Add this new div for image preview -->
<div id="image-preview-container" style="display: none; margin-top: 10px;">
    <p style="color: white; font-size: 0.9em;">Current Image:</p>
    <img id="current-image" src="" alt="Sub-Activity Image" style="width: 60px; height: auto; border-radius: 5px; margin: 5px 0;">
    <p style="color: #2ecc71; font-size: 0.9em;">* Uploading a new image is optional when editing</p>
</div>
        </div>
    </form>
</div>


<div id="selected-sub-activity-name" style="margin-top: 10px; color: white;">
    Selected Sub-Activity: <span id="sub-activity-name-display"></span>
</div>

                <!-- Sub-activities table -->
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Activity Type</th>
                            <th>Sub-Activity Name</th>
                            <th>Membership Type</th>
                            <th>Price</th>
                            <th>Image</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subActivities as $subActivity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subActivity['activity_type']); ?></td>
                                
                                <?php
                                // Fetch the sub_act_name from the sub_activity_name table
                                $stmt = $conn->prepare("SELECT sub_act_name FROM sub_activity_name WHERE sub_act_id = :sub_act_id");
                                $stmt->execute(['sub_act_id' => $subActivity['sub_act_id']]);
                                $subActNameResult = $stmt->fetch(PDO::FETCH_ASSOC);
                                $subActName = $subActNameResult ? htmlspecialchars($subActNameResult['sub_act_name']) : 'Unknown';
                                ?>
                                
                                <td><?php echo $subActName; ?></td>
                                <td><?php echo htmlspecialchars($subActivity['membership_type']); ?></td>
                                <td>₹<?php echo number_format($subActivity['sub_activity_price']); ?></td>
                                <td><img src="<?php echo $subActivity['sub_activity_image']; ?>" alt="Sub-Activity Image" style="width: 50px; height: auto;"></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-edit" data-subactivityid="<?php echo $subActivity['sub_activity_id']; ?>">Edit</button>
                                        <button class="btn btn-danger" data-subactivityid="<?php echo $subActivity['sub_activity_id']; ?>">Delete</button>
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
            // Reset the form completely before showing it
            resetSubActivityForm();
            
            // Then show the form and hide the add button
            document.getElementById('add-sub-activity-form').style.display = 'block';
            this.style.display = 'none';                
        });

        document.getElementById('cancel-sub-activity-btn').addEventListener('click', function() {
    // First hide the form
    document.getElementById('add-sub-activity-form').style.display = 'none';
    
    // Then show the add button
    document.getElementById('add-sub-activity-btn').style.display = 'block';
    
    // Finally reset the form (after it's hidden)
    resetSubActivityForm();
});
        // Fetch sub-activities based on selected activity
document.querySelector('select[name="activity_id"]').addEventListener('change', async function() {
    const activityId = this.value;
    const subActivitySelect = document.querySelector('select[name="sub_act_id"]');
    const subActivityNameDisplay = document.getElementById('sub-activity-name-display');
    
    // Clear existing options
    subActivitySelect.innerHTML = '<option value="">Select Sub-Activity</option>';
    subActivityNameDisplay.textContent = ''; // Clear displayed name
    
    if (activityId) {
        try {
            const response = await fetch(`get_sub_activities.php?activity_id=${activityId}`);
            const subActivities = await response.json();
            
            if (subActivities.length > 0) {
                subActivities.forEach(subActivity => {
                    const option = document.createElement('option');
                    option.value = subActivity.sub_act_id;
                    option.textContent = subActivity.sub_act_name;
                    subActivitySelect.appendChild(option);
                });
            } else {
                // No sub-activities found for this activity
                const option = document.createElement('option');
                option.value = "";
                option.textContent = "No sub-activities found";
                subActivitySelect.appendChild(option);
            }
        } catch (error) {
            console.error('Error fetching sub-activities:', error);
        }
    }
});

// Update the displayed sub-activity name when a sub-activity is selected
document.querySelector('select[name="sub_act_id"]').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const subActivityNameDisplay = document.getElementById('sub-activity-name-display');
    subActivityNameDisplay.textContent = selectedOption.text; // Display the selected sub-activity name
});

// Edit functionality
document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', async function() {
        const subActivityId = this.dataset.subactivityid;
        
        try {
            const response = await fetch(`edit_sub_activity.php?id=${subActivityId}`);
            const result = await response.json();
            
            if (result.success) {
                const subActivity = result.data;
                
                // Show the form and update its values
                const form = document.getElementById('add-sub-activity-form');
                form.style.display = 'block';
                document.getElementById('add-sub-activity-btn').style.display = 'none';
                
                // Update form fields
                const activitySelect = form.querySelector('select[name="activity_id"]');
                activitySelect.value = subActivity.activity_id;
                
                // Set the current image value in the hidden input
                form.querySelector('input[name="current_image"]').value = subActivity.sub_activity_image;
                
                // Remove required attribute from file input when editing
                form.querySelector('input[name="sub_activity_image"]').removeAttribute('required');
                
                // Trigger the activity change event to load sub-activities
                const changeEvent = new Event('change');
                activitySelect.dispatchEvent(changeEvent);
                
                // Set a timeout to make sure sub-activities are loaded before setting the value
                setTimeout(() => {
                    const subActSelect = form.querySelector('select[name="sub_act_id"]');
                    subActSelect.value = subActivity.sub_act_id;
                    
                    // Trigger change event to update displayed name
                    subActSelect.dispatchEvent(new Event('change'));
                }, 500);
                
                form.querySelector('input[name="sub_activity_price"]').value = subActivity.sub_activity_price;
                
                // Set hidden field for sub_activity_id
                form.querySelector('input[name="sub_activity_id"]').value = subActivityId;
                
                // Set the current image path in the image preview
                const imagePreview = document.getElementById('image-preview-container');
                const currentImage = document.getElementById('current-image');
                currentImage.src = subActivity.sub_activity_image;
                imagePreview.style.display = 'block'; // Show the image preview
            } else {
                alert('Failed to fetch sub-activity details');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while fetching sub-activity details');
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

// Update the reset form functionality
function resetSubActivityForm() {
    // Get the form element
    const form = document.getElementById('add-sub-activity-form').querySelector('form');
    
    // Use the native form reset method
    form.reset();
    
    // Clear any hidden values
    form.querySelector('input[name="sub_activity_id"]').value = '';
    form.querySelector('input[name="current_image"]').value = '';
    
    // Reset file input required attribute for new entries
    const fileInput = form.querySelector('input[name="sub_activity_image"]');
    fileInput.setAttribute('required', 'required');
    
    // Hide the image preview container and clear the image source
    const imagePreview = document.getElementById('image-preview-container');
    imagePreview.style.display = 'none';
    document.getElementById('current-image').src = '';
    
    // Clear the displayed sub-activity name
    document.getElementById('sub-activity-name-display').textContent = '';
    
    // Reset the select elements to their default state
    const activitySelect = form.querySelector('select[name="activity_id"]');
    const subActivitySelect = form.querySelector('select[name="sub_act_id"]');
    
    activitySelect.selectedIndex = 0;
    subActivitySelect.innerHTML = '<option value="">Select Sub-Activity</option>';
}

    </script>
    
</body>
</html>
