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
$activeSection = 'activity';


// Process activity form submission
// Process activity form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_type'])) {
    $activity_type = trim($_POST['activity_type']);
    
    // Validation
    $error = '';
    if (empty($activity_type)) {
        $error = "Activity type cannot be empty";
    } elseif (strlen($activity_type) < 3) {
        $error = "Activity type must be at least 3 characters long";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $activity_type)) {
        $error = "Activity type can only contain letters and spaces";
    }
    
    if (empty($error)) {
        try {
            $sql = "INSERT INTO activity (activity_type) VALUES (:activity_type)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['activity_type' => $activity_type]);
            
            // Set success message
            $_SESSION['success_message'] = "Activity added successfully!";
            
            // Refresh the activities list
            $activities = getActivities();
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // If this is an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['error' => $error, 'success' => empty($error)]);
        exit;
    }
}
// Process sub-activity form submission
// Replace the existing sub-activity form processing section with this updated code

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_activity_name'])) {
    $sub_activity_name = trim($_POST['sub_activity_name']);
    $activity_id = $_POST['activity_id'];
    $sub_activity_price = $_POST['sub_activity_price'];
    $sub_activity_image = $_FILES['sub_activity_image'];
    
    if (!empty($sub_activity_name) && !empty($activity_id) && !empty($sub_activity_price)) {
        // Check if image was uploaded
        if (isset($sub_activity_image) && $sub_activity_image['error'] == 0) {
            // Set upload directory
            $uploadDir = 'uploads/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Get file extension
            $fileExtension = strtolower(pathinfo($sub_activity_image['name'], PATHINFO_EXTENSION));
            
            // Allowed file types
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedTypes)) {
                // Generate unique filename
                $newFileName = uniqid('sub_activity_', true) . '.' . $fileExtension;
                $imagePath = $uploadDir . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($sub_activity_image['tmp_name'], $imagePath)) {
                    try {
                        $sql = "INSERT INTO sub_activity (activity_id, sub_activity_name, sub_activity_price, sub_activity_image) 
                                VALUES (:activity_id, :sub_activity_name, :sub_activity_price, :sub_activity_image)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            'activity_id' => $activity_id,
                            'sub_activity_name' => $sub_activity_name,
                            'sub_activity_price' => $sub_activity_price,
                            'sub_activity_image' => $imagePath
                        ]);
                        
                        // Refresh the lists
                        $subActivities = getSubActivities();
                        $activities = getActivities();
                        
                        // Add success message
                        $_SESSION['success_message'] = "Sub-activity added successfully!";
                    } catch (PDOException $e) {
                        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['error_message'] = "Failed to move uploaded file.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid file type. Allowed types: " . implode(', ', $allowedTypes);
            }
        } else {
            // Handle specific upload errors
            switch ($sub_activity_image['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $_SESSION['error_message'] = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $_SESSION['error_message'] = "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $_SESSION['error_message'] = "The uploaded file was only partially uploaded";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $_SESSION['error_message'] = "No file was uploaded";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $_SESSION['error_message'] = "Missing a temporary folder";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $_SESSION['error_message'] = "Failed to write file to disk";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $_SESSION['error_message'] = "A PHP extension stopped the file upload";
                    break;
                default:
                    $_SESSION['error_message'] = "Unknown upload error";
            }
        }
    } else {
        $_SESSION['error_message'] = "Please fill in all required fields";
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?section=sub-activities");
    exit();
}

function getActivities() {
    global $conn;
    $sql = "SELECT * FROM activity ORDER BY activity_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$activities = getActivities();

function getSubActivities() {
    global $conn;
    $sql = "SELECT sa.*, a.activity_type 
            FROM sub_activity sa 
            JOIN activity a ON sa.activity_id = a.activity_id 
            ORDER BY sa.sub_activity_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$subActivities = getSubActivities();
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
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">Manager Dashboard</div>
        <nav>
        <ul class="sidebar-nav">
                <li class="sidebar-nav-item"><a href="manager_dashboard.php">Overview</a></li>
                <li class="sidebar-nav-item"><a href="manager_user.php">Users</a></li>
                <li class="sidebar-nav-item active"><a href="manager_activities.php">Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_sub_activities.php">Sub-Activities</a></li>
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
        <!-- Add activities section -->
        <div id="activities-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>Activity Management</h1>
                <div class="header-actions">
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
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Activity Type</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td style="text-align: center;"><?php echo htmlspecialchars($activity['activity_type']); ?></td>
                                <td style="text-align: center;">
                                    <div class="action-buttons" style="display: flex; justify-content: center;">
                                        <button class="btn btn-edit" data-activityid="<?php echo $activity['activity_id']; ?>" disabled style="cursor: not-allowed;">Edit</button>
                                        <button class="btn btn-danger" data-activityid="<?php echo $activity['activity_id']; ?>" disabled style="cursor: not-allowed;">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
    <script>
        document.getElementById('add-activity-btn').addEventListener('click', function() {
    document.getElementById('add-activity-form').style.display = 'block';
    this.style.display = 'none';
});

document.getElementById('cancel-activity-btn').addEventListener('click', function() {
    document.getElementById('add-activity-form').style.display = 'none';
    document.getElementById('add-activity-btn').style.display = 'block';
    // Clear any error messages and reset form
    document.getElementById('activity-error').style.display = 'none';
    document.getElementById('activity_type').classList.remove('error');
    document.getElementById('activityForm').reset();
});

document.getElementById('activityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const activityInput = document.getElementById('activity_type');
    const errorElement = document.getElementById('activity-error');
    const activityValue = activityInput.value.trim();
    
    // Reset previous error states
    errorElement.style.display = 'none';
    activityInput.classList.remove('error');
    
    // Validation
    if (activityValue.length === 0) {
        showError("Activity type cannot be empty");
        return;
    }
    
    if (activityValue.length < 3) {
        showError("Activity type must be at least 3 characters long");
        return;
    }
    
    if (!/^[a-zA-Z\s]+$/.test(activityValue)) {
        showError("Activity type can only contain letters and spaces");
        return;
    }
    
    // If validation passes, submit the form via AJAX
    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(new FormData(this))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page to show the new activity
            window.location.reload();
        } else {
            showError(data.error);
        }
    })
    .catch(error => {
        showError("An error occurred while saving the activity");
    });
});

function showError(message) {
    const errorElement = document.getElementById('activity-error');
    const activityInput = document.getElementById('activity_type');
    
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    activityInput.classList.add('error');
}

// Add these event listeners after your existing JavaScript code

// Handle Edit button clicks
document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', function() {
        const activityId = this.dataset.activityid;
        const row = this.closest('tr');
        const activityTypeCell = row.querySelector('td:nth-child(2)');
        const originalText = activityTypeCell.textContent;

        // Create input field
        const input = document.createElement('input');
        input.type = 'text';
        input.value = originalText;
        input.style.padding = '5px';
        input.style.borderRadius = '3px';
        input.style.border = '1px solid rgba(255,255,255,0.2)';
        input.style.background = 'rgba(255,255,255,0.1)';
        input.style.color = 'white';
        input.style.width = '200px';

        // Create save button
        const saveBtn = document.createElement('button');
        saveBtn.textContent = 'Save';
        saveBtn.className = 'btn btn-primary';
        saveBtn.style.marginLeft = '5px';

        // Create cancel button
        const cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'Cancel';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.style.marginLeft = '5px';

        // Replace cell content
        const originalContent = activityTypeCell.innerHTML;
        activityTypeCell.innerHTML = '';
        activityTypeCell.appendChild(input);
        activityTypeCell.appendChild(saveBtn);
        activityTypeCell.appendChild(cancelBtn);

        // Handle save
        saveBtn.addEventListener('click', function() {
            const newValue = input.value.trim();
            
            fetch('activity_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'update',
                    'activity_id': activityId,
                    'activity_type': newValue
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    activityTypeCell.innerHTML = newValue;
                } else {
                    alert(data.message);
                    activityTypeCell.innerHTML = originalContent;
                }
            })
            .catch(error => {
                alert('An error occurred while saving');
                activityTypeCell.innerHTML = originalContent;
            });
        });

        // Handle cancel
        cancelBtn.addEventListener('click', function() {
            activityTypeCell.innerHTML = originalContent;
        });
    });
});

// Handle Delete button clicks
document.querySelectorAll('.btn-danger').forEach(button => {
    button.addEventListener('click', function() {
        const activityId = this.dataset.activityid;
        const row = this.closest('tr');
        
        if (confirm('Are you sure you want to delete this activity? This action cannot be undone.')) {
            fetch('activity_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'delete',
                    'activity_id': activityId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('An error occurred while deleting');
            });
        }
    });
});
    </script>
</body>
</html>