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
};

// Check if user is logged out
if (!isset($_SESSION['email']) || !isset($_SESSION['role'])) {
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

// Add this at the very top of your PHP code, after require_once 'db_connect.php';
$activeSection = 'users';

// Fetch users from database
function getUsers() {
    global $conn;
    $sql = "SELECT u.user_id, u.name, u.email, u.mobile, u.dob, m.membership_type, u.role 
            FROM users u 
            JOIN memberships m ON u.membership_id = m.membership_id
            ORDER BY u.user_id ASC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$users = getUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate user name
    if (empty($_POST['user_name'])) {
        $errors[] = "User name is required.";
    }

    // Validate email
    if (empty($_POST['user_email'])) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate mobile number
    if (empty($_POST['user_mobile'])) {
        $errors[] = "Mobile number is required.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $_POST['user_mobile'])) {
        $errors[] = "Mobile number must be between 10 to 15 digits.";
    }

    // Validate date of birth
    if (empty($_POST['dob'])) {
        $errors[] = "Date of birth is required.";
    } elseif (strtotime($_POST['dob']) > time()) {
        $errors[] = "Date of birth cannot be in the future.";
    }

    // If there are no errors, proceed with user creation
    if (empty($errors)) {
        // Prepare user data for insertion
        $user_name = $_POST['user_name'];
        $user_email = $_POST['user_email'];
        $user_mobile = $_POST['user_mobile'];
        $dob = $_POST['dob'];
        $user_role = 1; // Default role

        // Insert user into the database
        try {
            $sql = "INSERT INTO users (name, email, mobile, dob, role) VALUES (:name, :email, :mobile, :dob, :role)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'name' => $user_name,
                'email' => $user_email,
                'mobile' => $user_mobile,
                'dob' => $dob,
                'role' => $user_role
            ]);
            // Redirect or show success message
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
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
            display: block;
            width: 100%;
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
            width: 100%;
            height: 100%;
            color: white;
            text-decoration: none;
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
            display:block;
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

        .btn:disabled {
            background-color: rgba(255, 255, 255, 0.1); /* Change to your desired disabled background color */
            color: rgba(255, 255, 255, 0.5); /* Change to your desired disabled text color */
            cursor: not-allowed; /* Change cursor to indicate it's disabled */
        }

        .btn:disabled:hover {
            background-color: rgba(255, 255, 255, 0.1); /* Keep the same background on hover */
            color: rgba(255, 255, 255, 0.5); /* Keep the same text color on hover */
        }

        .btn-danger:disabled {
            background-color: #e74c3c; /* Keep the red color */
            color: rgba(255, 255, 255, 0.5); /* Change text color to indicate it's disabled */
            cursor: not-allowed; /* Change cursor to indicate it's disabled */
        }

        .btn-danger:disabled:hover {
            background-color: #e74c3c; /* Keep the same red background on hover */
            color: rgba(255, 255, 255, 0.5); /* Keep the same text color on hover */
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">Admin Dashboard</div>
        <nav>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item"><a href="admin_overview.php">Overview</a></li>
            <li class="sidebar-nav-item active"><a href="admin_user.php">Users</a></li>
            <li class="sidebar-nav-item"><a href="admin_manager.php">Manager</a></li>
            <li class="sidebar-nav-item"><a href="admin_activities.php">Activities</a></li>
            <li class="sidebar-nav-item"><a href="admin_sub_activities.php">Sub-Activities</a></li>
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
        <div id="users-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>User Management</h1>
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

            <section class="section-content">
                <div id="add-user-form" style="display: none; margin-bottom: 20px;">
                    <form method="POST" class="form-container">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" name="user_name" placeholder="Enter User Name" 
                                   style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                          background: rgba(255,255,255,0.1); color: white;">
                            <input type="email" name="user_email" placeholder="Enter User Email" 
                                   style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                          background: rgba(255,255,255,0.1); color: white;">
                            <input type="text" name="user_mobile" placeholder="Enter User Mobile" 
                                   style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                          background: rgba(255,255,255,0.1); color: white;">
                            <input type="date" name="dob" placeholder="Date of Birth" 
                                   style="padding: 10px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); 
                                          background: rgba(255,255,255,0.1); color: white;">
                            <input type="hidden" name="user_role" value="1">
                            <button type="submit" class="btn btn-primary">Save User</button>
                            <button type="button" class="btn btn-secondary" id="cancel-user-btn">Cancel</button>
                        </div>
                    </form>
                    <?php if (!empty($errors)): ?>
                        <div class="error-messages">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Date of Birth</th>
                            <th>Membership Type</th>
                            <!-- <th>Actions</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($user['dob']); ?></td>
                                <td><?php echo htmlspecialchars($user['membership_type']); ?></td>
                                <!-- <td> -->
                                    <!-- <div class="action-buttons"> -->
                                        <!-- <button class="btn btn-edit" data-userid="<?php echo $user['user_id']; ?>">Edit</button> -->
                                        <!-- <button class="btn btn-danger" data-userid="<?php echo $user['user_id']; ?>">Delete</button> -->
                                    <!-- </div> -->
                                <!-- </td> -->
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
    <script>
        document.getElementById('cancel-user-btn').addEventListener('click', function() {
            document.getElementById('add-user-form').style.display = 'none';
        });

        // Function to handle editing a user
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-userid');
                const row = this.closest('tr');
                const name = row.cells[1].innerText;
                const email = row.cells[2].innerText;
                const mobile = row.cells[3].innerText;
                const dob = row.cells[4].innerText;

                // Populate the form with current user data
                document.querySelector('input[name="user_name"]').value = name;
                document.querySelector('input[name="user_email"]').value = email;
                document.querySelector('input[name="user_mobile"]').value = mobile;
                document.querySelector('input[name="dob"]').value = dob;
                document.querySelector('input[name="edit_user_id"]').value = userId; // Store user ID for update

                // Validate the name field before showing the form
                const nameRegex = /^[A-Za-z]{2,}$/; // Regex for minimum 2 letters only
                if (!nameRegex.test(name)) {
                    alert("User name must be at least 2 characters long and contain only letters.");
                    return; // Stop if the name is invalid
                }

                document.getElementById('add-user-form').style.display = 'block';
            });
        });

       // Update the delete button click handlers
document.querySelectorAll('.btn-danger').forEach(button => {
button.addEventListener('click', function() {
    const userId = this.getAttribute('data-userid');
    const row = this.closest('tr');
    const userName = row.cells[1].innerText;

    // Show confirmation dialog
    if (confirm(`Are you sure you want to delete user "${userName}"?`)) {
        // Create form data
        const formData = new FormData();
        formData.append('user_id', userId);

        // Send delete request
        fetch('delete_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the row from the table
                row.remove();
                alert('User deleted successfully');
            } else {
                alert('Error deleting user: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting user. Please try again.');
        });
    }
});
});

// Add function to refresh the table after deletion
function refreshUserTable() {
fetch('get_users.php')
    .then(response => response.json())
    .then(data => {
        const tbody = document.querySelector('.data-table tbody');
        tbody.innerHTML = '';
        
        data.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.user_id}</td>
                <td>${user.name}</td>
                <td>${user.email}</td>
                <td>${user.mobile}</td>
                <td>${user.dob}</td>
                <td>${user.role == 1 ? 'User' : 'Admin'}</td>
                <td>${user.membership_type}</td>
                <!-- <td> -->
                    <!-- <div class="action-buttons"> -->
                        <!-- <button class="btn btn-edit" data-userid="${user.user_id}">Edit</button> -->
                        <!-- <button class="btn btn-danger" data-userid="${user.user_id}">Delete</button> -->
                    <!-- </div> -->
                <!-- </td> -->
            `;
            tbody.appendChild(row);
        });
        
        // Reattach event listeners
        attachEventListeners();
    })
    .catch(error => {
        console.error('Error refreshing table:', error);
    });
}

// Function to attach event listeners to buttons
function attachEventListeners() {
// Reattach delete button listeners
document.querySelectorAll('.btn-danger').forEach(button => {
    button.addEventListener('click', handleDelete);
});

// Reattach edit button listeners
document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', handleEdit);
});
}

// Separate handler functions for clarity
function handleDelete() {
const userId = this.getAttribute('data-userid');
const row = this.closest('tr');
const userName = row.cells[1].innerText;

if (confirm(`Are you sure you want to delete user "${userName}"?`)) {
    const formData = new FormData();
    formData.append('user_id', userId);

    fetch('delete_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            row.remove();
            alert('User deleted successfully');
        } else {
            alert('Error deleting user: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting user. Please try again.');
    });
}
}

        // Handle form submission for both adding and editing users
        // Add this right after your existing form HTML
document.querySelector('.form-container').innerHTML += `
<input type="hidden" name="edit_user_id" value="">
`;

// Update the edit button click handler
document.querySelectorAll('.btn-edit').forEach(button => {
button.addEventListener('click', function() {
    const userId = this.getAttribute('data-userid');
    const row = this.closest('tr');
    const name = row.cells[1].innerText;
    const email = row.cells[2].innerText;
    const mobile = row.cells[3].innerText;
    const dob = row.cells[4].innerText;

    // Populate the form with current user data
    document.querySelector('input[name="user_name"]').value = name;
    document.querySelector('input[name="user_email"]').value = email;
    document.querySelector('input[name="user_mobile"]').value = mobile;
    document.querySelector('input[name="dob"]').value = dob;
    document.querySelector('input[name="edit_user_id"]').value = userId; // Store user ID for update

    // Validate the name field before showing the form
    const nameRegex = /^[A-Za-z]{2,}$/; // Regex for minimum 2 letters only
    if (!nameRegex.test(name)) {
        alert("User name must be at least 2 characters long and contain only letters.");
        return; // Stop if the name is invalid
    }

    document.getElementById('add-user-form').style.display = 'block';
});
});

// Update the form submission handler
document.querySelector('.form-container').addEventListener('submit', function(event) {
event.preventDefault();

const userId = document.querySelector('input[name="edit_user_id"]').value;
const formData = new FormData(this);

// Validate user name
const userName = formData.get('user_name');
const nameRegex = /^[A-Za-z]{2,}$/; // Regex for minimum 2 letters only
if (!nameRegex.test(userName)) {
    alert("User name must be at least 2 characters long and contain only letters.");
    return; // Stop form submission
}

// Determine if this is an edit or add operation
const url = userId ? 'update_user.php' : 'add_user.php';

// If editing, add the user ID to the form data
if (userId) {
    formData.append('user_id', userId);
}

fetch(url, {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        if (userId) {
            // Update existing row
            const row = document.querySelector(`button[data-userid="${userId}"]`).closest('tr');
            row.cells[1].innerText = formData.get('user_name');
            row.cells[2].innerText = formData.get('user_email');
            row.cells[3].innerText = formData.get('user_mobile');
            row.cells[4].innerText = formData.get('dob');
        } else {
            // Add new row logic (existing code)
        }
        // Clear form and hide it
        this.reset();
        document.querySelector('input[name="edit_user_id"]').value = '';
        document.getElementById('add-user-form').style.display = 'none';
        
        // Show success message
        alert('User data saved successfully!');
    } else {
        alert('Error saving user: ' + (data.message || 'Unknown error'));
    }
})
.catch(error => {
    console.error('Error:', error);
    alert('Error saving user. Please try again.');
});
});

// Update cancel button to clear the edit_user_id
document.getElementById('cancel-user-btn').addEventListener('click', function() {
document.querySelector('input[name="edit_user_id"]').value = '';
document.getElementById('add-user-form').style.display = 'none';
document.querySelector('.form-container').reset();
});
    </script>
</body>
</html>