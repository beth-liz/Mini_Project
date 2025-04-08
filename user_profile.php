<?php
session_start();
include('db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

// Fetch user details with membership information
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email']; // Ensure this is set from the session

try {
    $query = "SELECT u.*, m.membership_type 
              FROM users u 
              LEFT JOIN memberships m ON u.membership_id = m.membership_id 
              WHERE u.user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Handle case where user is not found
        $_SESSION['error'] = "User not found";
        header('Location: logout.php');
        exit();
    }
} catch(PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching user data";
    header('Location: error.php');
    exit();
}

// Modify the existing SQL query to also fetch the user's name
$sql = "SELECT membership_id, name FROM users WHERE email = :email"; // Use a prepared statement
$stmt = $conn->prepare($sql);
$stmt->bindParam(':email', $user_email, PDO::PARAM_STR);
$stmt->execute();

if ($stmt->rowCount() > 0) { // Use rowCount() instead of num_rows
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $membership_id = $row['membership_id'];
    $user_name = $row['name'];
} else {
    $user_name = "Profile"; // Default value
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_log("Starting profile update process");

// Function to send error response
if (!function_exists('sendError')) {
    function sendError($message, $details = null) {
        $response = ['success' => false, 'message' => $message];
        if ($details && ini_get('display_errors')) {
            $response['debug'] = $details;
        }
        echo json_encode($response);
        exit;
    }
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        sendError('Not logged in');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/profile/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            $error = error_get_last();
            sendError('Failed to create upload directory', $error);
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - ArenaX</title>
    
    <!-- Favicon link -->
    <link rel="icon" href="img/logo3.png" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Aboreto&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400&family=Goldman&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Bodoni+Moda:wght@400;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cinzel Decorative', cursive;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r5.jpg') no-repeat center center fixed;
            background-size: cover;
            color: white;
            overflow-x: hidden;
        }

        /* Header styles from user_home.php */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }
        
        .header nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin: 0 auto;
            padding: 0;
        }

        .header div a button {
            transition: background-color 0.3s ease-in-out, transform 0.2s ease;
        }

        .header div a button:hover {
            color: #00bcd4;
            border-color: #00bcd4;
            transform: scale(1.1);
        }

        .log {
            padding: 10px 20px;
            font-size: 1rem; 
            font-family: 'Cinzel Decorative', cursive; 
            background-color: #007cd400; 
            color: white;
            padding: 10px 50px;
            border-style: solid;
            border-width: 1px;
            border-color: white;
            border-radius: 0px; 
            cursor: pointer;
            transition: background-color 0.3s ease-in-out;
        }

        .log:hover {
            color: #00bcd4;
            border-color: #00bcd4;
        }

        .header div {
            display: flex;
            gap: 15px;
            margin-right: 40px;
        }

        .header nav ul li {
            position: relative;
        }

        .header nav ul li a {
            text-decoration: none;
            color: white;
            font-size: 1.2rem;
            transition: color 0.2s ease-in-out;
        }

        .header nav ul li a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #00bcd4;
            transition: all 0.3s ease-in-out;
            transform: translateX(-50%);
        }

        .header nav ul li a:hover::after {
            width: 100%;
        }

        .header nav ul li a:hover {
            color: #00bcd4;
        }

        .are {
            text-decoration: none;
        }

        .dropdown {
            display: none;
            position: absolute;
            background-color: rgba(0, 0, 0, 0.9);
            min-width: 200px;
            border-radius: 0;
            padding: 8px 0;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            top: 100%;
            left: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown a {
            display: block;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            font-family: 'Bodoni Moda', serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown a:last-child {
            border-bottom: none;
        }

        .dropdown a:hover {
            background-color: rgba(0, 188, 212, 0.2);
            padding-left: 25px;
            color: #00bcd4;
        }

        .dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 20px;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid rgba(0, 0, 0, 0.9);
        }

        /* Profile Container Styles */
        .profile-container {
            max-width: 1000px;
            margin: 120px auto 40px;
            padding: 20px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .profile-header h1 {
            font-size: 2.5rem;
            color: #00bcd4;
            margin-bottom: 20px;
            font-family: 'Bodoni Moda', serif;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
            background: rgba(0, 0, 0, 0.8);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 188, 212, 0.2);
            max-width: 900px;
            margin: 0 auto;
        }

        .profile-image {
            text-align: center;
            max-width: 150px;
            margin: 0 auto;
        }

        .profile-image img {
            width: 150px;
            height: 150px;
            border: 3px solid #00bcd4;
            margin-bottom: 20px;
            border-radius: 10px;
            object-fit: cover;
        }

        .profile-details {
            padding: 20px;
        }

        .detail-group {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
        }

        .detail-group h3 {
            color: #00bcd4;
            font-size: 1.2rem;
            min-width: 150px;
            margin-bottom: 0;
            font-family: 'Arial', sans-serif;
        }

        .detail-group p {
            font-size: 1.1rem;
            color: #fff;
            font-family: 'Arial', sans-serif;
            margin: 0;
        }

        .edit-button {
            background-color: transparent;
            color: white;
            padding: 10px 30px;
            border: 2px solid #00bcd4;
            cursor: pointer;
            font-family: 'Bodoni Moda', serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .edit-button:hover {
            background-color: #00bcd4;
            color: black;
        }

        /* Stats Section */
        .stats-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.8);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #00bcd4;
        }

        .stat-card h3 {
            color: #00bcd4;
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-family: 'Bodoni Moda', serif;
        }

        .stat-card p {
            font-size: 1.5rem;
            color: white;
            font-family: 'Aboreto', cursive;
        }

        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: 1fr;
            }
        }

        /* Add these to your existing styles */
        .profile-details input {
            padding: 8px 12px;
            border: 1px solid #00bcd4;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            font-family: 'Arial', sans-serif;
            font-size: 1.1rem;
            border-radius: 4px;
            width: 250px;
        }

        .profile-details input:focus {
            outline: none;
            border-color: #00eeff;
        }

        .save-button, .cancel-button {
            background-color: transparent;
            color: white;
            padding: 10px 30px;
            border: 2px solid #00bcd4;
            cursor: pointer;
            font-family: 'Bodoni Moda', serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin: 0 10px;
        }

        .save-button:hover {
            background-color: #00bcd4;
            color: black;
        }

        .cancel-button {
            border-color: #ff4081;
        }

        .cancel-button:hover {
            background-color: #ff4081;
            color: white;
        }

        /* Add to your existing styles */
        .image-upload-container {
            margin-top: 15px;
            text-align: center;
            max-width: 150px;
        }

        .upload-btn {
            background-color: transparent;
            color: white;
            padding: 6px 15px;
            border: 2px solid #00bcd4;
            cursor: pointer;
            font-family: 'Bodoni Moda', serif;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            margin-bottom: 8px;
            width: 100%;
        }

        .upload-btn:hover {
            background-color: #00bcd4;
            color: black;
        }

        .upload-text {
            font-size: 0.7rem;
            color: #888;
            margin-top: 4px;
        }

        #profile-image-preview {
            cursor: pointer;
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
            display: none;
        }
        .success-message {
            color: green;
            margin-bottom: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="logo">
            <a href="user_home.php" class="are"><h2 style="color: white;">ArenaX</h2></a>  
        </div>
        <nav>
            <ul style="display: flex; justify-content: center; width: 100%;">
                <li><a href="user_home.php">Home</a></li>
                <li><a href="user_indoor.php">Indoor</a></li>
                <li><a href="user_outdoor.php">Outdoor</a></li>
                <li><a href="user_fitness.php">Fitness</a></li>
                <li><a href="user_events.php">Events</a></li>
            </ul>
        </nav>
        <div style="margin-right: 20px; position: relative;">
    <button class="log"><?php echo htmlspecialchars($user_name); ?> <i class="fas fa-caret-down"></i></button>
    <div class="dropdown">
        <a href="user_profile.php">PROFILE</a>
        <a href="user_bookings.php">BOOKINGS</a>
        <a href="user_calendar.php">CALENDER</a>
        <a href="user_payment_history.php">PAYMENT HISTORY</a>
        <a href="logout.php">LOGOUT</a>
    </div>
</div>
    </header>

    <!-- Profile Content -->
    <div class="profile-container">
        <div class="profile-header">
            <h1>User Profile</h1>
        </div>

        <div class="profile-content">
            <div class="profile-image">
                <img src="<?php echo !empty($user['profile_image']) ? 'uploads/profile/' . $user['profile_image'] : 'img/profile.png'; ?>" 
                     alt="Profile Picture" id="profile-image-preview">
                <div class="image-upload-container" style="display: none;" id="image-upload-container">
                    <input type="file" id="profile-image-input" accept="image/*" style="display: none;">
                    <button type="button" class="upload-btn" onclick="document.getElementById('profile-image-input').click()">
                        Choose Image
                    </button>
                    <p class="upload-text">Max size: 5MB (JPG, PNG)</p>
                    <div id="image-error" class="error-message" style="display: none; color: red; margin-top: 10px;"></div>
                </div>
            </div>

            <div class="profile-details" id="profile-view">
                <div class="detail-group">
                    <h3>Full Name</h3>
                    <p><?php echo htmlspecialchars($user['name']); ?></p>
                </div>

                <div class="detail-group">
                    <h3>Email</h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <div class="detail-group">
                    <h3>Date of Birth</h3>
                    <p><?php echo date('F j, Y', strtotime($user['dob'])); ?></p>
                </div>

                <div class="detail-group">
                    <h3>Mobile Number</h3>
                    <p><?php echo htmlspecialchars($user['mobile']); ?></p>
                </div>

                <div class="detail-group">
                    <h3>Membership Type</h3>
                    <p><?php echo htmlspecialchars($user['membership_type'] ?? 'Standard'); ?></p>
                </div>

                <div class="detail-group">
                    <h3>Role</h3>
                    <p><?php echo $user['role'] == 1 ? 'User' : 'Admin'; ?></p>
                </div>
                <div class="error-message" id="edit-error" style="display: none; color: red; margin-bottom: 10px;"></div>
            </div>

            <!-- Edit Form (Hidden by default) -->
            <div class="profile-details" id="profile-edit" style="display: none;">
                <form id="edit-profile-form" method="POST" action="update_profile.php">
                <div id="error-message" class="error-message"></div>
                <div id="success-message" class="success-message"></div>
                    <div class="detail-group">
                        <h3>Full Name</h3>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        <div class="error-message" id="name-error" style="display: none; color: red; font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>

                    <div class="detail-group">
                        <h3>Email</h3>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="error-message" id="email-error" style="display: none; color: red; font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>

                    <div class="detail-group">
                        <h3>Mobile Number</h3>
                        <input type="tel" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                        <div class="error-message" id="mobile-error" style="display: none; color: red; font-size: 0.8rem; margin-top: 5px;"></div>
                    </div>

                    <div class="detail-group">
                        <h3>Date of Birth</h3>
                        <input type="text" name="dob" value="<?php echo date('Y-m-d', strtotime($user['dob'])); ?>" required readonly>
                    </div>

                    <div class="error-message" id="edit-error" style="display: none; color: red; margin-bottom: 10px;"></div>
                </form>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
            <button class="edit-button" id="editBtn">Edit Profile</button>
            <button class="save-button" id="saveBtn" style="display: none;">Save Changes</button>
            <button class="cancel-button" id="cancelBtn" style="display: none;">Cancel</button>
        </div>
    </div>

    <script>
        const header = document.querySelector('.header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        const profileButton = document.querySelector('.log');
        const dropdown = document.querySelector('.dropdown');
        dropdown.style.display = 'none'; // Ensure dropdown is hidden initially

        profileButton.addEventListener('click', () => {
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            const profileButton = document.querySelector('.log');
            
            if (!dropdown.contains(event.target) && !profileButton.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        dropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        const editBtn = document.getElementById('editBtn');
        const saveBtn = document.getElementById('saveBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const profileView = document.getElementById('profile-view');
        const profileEdit = document.getElementById('profile-edit');
        const editForm = document.getElementById('edit-profile-form');
        const errorMessage = document.getElementById('edit-error');

        editBtn.addEventListener('click', () => {
            profileView.style.display = 'none';
            profileEdit.style.display = 'block';
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
            cancelBtn.style.display = 'inline-block';
            imageUploadContainer.style.display = 'block';

            // Disable editing of Full Name and Email
            const fullNameInput = document.querySelector('input[name="name"]');
            const emailInput = document.querySelector('input[name="email"]');
            fullNameInput.disabled = true; // Disable Full Name input
            emailInput.disabled = true; // Disable Email input
        });

        cancelBtn.addEventListener('click', () => {
            profileView.style.display = 'block';
            profileEdit.style.display = 'none';
            editBtn.style.display = 'inline-block';
            saveBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            errorMessage.style.display = 'none';
            editForm.reset();
            imageUploadContainer.style.display = 'none';
            imageError.style.display = 'none';
        });

        // Save button click event
        saveBtn.addEventListener('click', async () => {
            // Get form data
            const formData = new FormData(editForm);
            
            // Clear previous error messages
            const errorDiv = document.getElementById('edit-error');
            const successDiv = document.getElementById('success-message');
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            // Add the DOB and image if present
            const dobText = document.querySelector('#profile-view .detail-group:nth-child(3) p').textContent;
            formData.append('dob', new Date(dobText).toISOString().split('T')[0]);
            
            const imageFile = profileImageInput.files[0];
            if (imageFile) {
                formData.append('profile_image', imageFile);
            }

            // Include the mobile number in the form data
            const mobileInput = document.querySelector('input[name="mobile"]');
            formData.append('mobile', mobileInput.value);

            try {
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (!result.success) {
                    // Display error message
                    errorDiv.textContent = result.debug 
                        ? `${result.message}\n${JSON.stringify(result.debug)}`
                        : result.message;
                    errorDiv.style.display = 'block';
                } else {
                    // Show success message
                    successDiv.textContent = result.message;
                    successDiv.style.display = 'block';
                    
                    // Refresh after success
                    setTimeout(() => window.location.reload(), 2000);
                }
            } catch (error) {
                console.error('Error:', error);
                errorDiv.textContent = 'An error occurred while updating the profile';
                errorDiv.style.display = 'block';
            }
        });

        // Image upload handling
        const profileImage = document.getElementById('profile-image-preview');
        const imageUploadContainer = document.getElementById('image-upload-container');
        const profileImageInput = document.getElementById('profile-image-input');
        const imageError = document.getElementById('image-error');

        // Handle image selection
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Update size limit to 10MB (10 * 1024 * 1024 bytes)
                if (file.size > 10 * 1024 * 1024) {
                    imageError.textContent = 'Image size must be less than 10MB';
                    imageError.style.display = 'block';
                    this.value = '';
                    return;
                }

                // Check file type
                if (!file.type.match('image/jpeg') && !file.type.match('image/png')) {
                    imageError.textContent = 'Only JPG and PNG images are allowed';
                    imageError.style.display = 'block';
                    this.value = '';
                    return;
                }

                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
                imageError.style.display = 'none';
            }
        });

        // Get all input elements
        const nameInput = document.querySelector('input[name="name"]');
        const emailInput = document.querySelector('input[name="email"]');
        const mobileInput = document.querySelector('input[name="mobile"]');

        // Create validation state object with initial true values since fields are pre-populated
        const validationState = {
            name: true,
            email: true,
            mobile: true,
            dob: true
        };

        // Create validation message elements
        function createValidationMessage(input) {
            const messageDiv = document.createElement('div');
            messageDiv.style.color = 'red';
            messageDiv.style.fontSize = '0.8rem';
            messageDiv.style.fontFamily = 'Arial, sans-serif';
            messageDiv.style.marginTop = '5px'; // Space above the message
            messageDiv.style.marginBottom = '10px'; // Space below the message
            messageDiv.style.display = 'none'; // Initially hidden
            
            // Add styles for the box behind the message
            messageDiv.style.backgroundColor = 'rgba(255, 0, 0, 0.1)'; // Light red background
            messageDiv.style.padding = '8px'; // Padding inside the box
            messageDiv.style.borderRadius = '4px'; // Rounded corners
            
            input.parentElement.parentElement.appendChild(messageDiv); // Append to the parent of the parent
            
            // Function to show the error message
            function showError(message) {
                messageDiv.textContent = message; // Set the error message
                messageDiv.style.display = 'block'; // Show the message box
            }

            // Function to clear the error message
            function clearError() {
                messageDiv.textContent = ''; // Clear the message
                messageDiv.style.display = 'none'; // Hide the message box
            }

            // Return both functions to allow setting and clearing the message later
            return { showError, clearError };
        }

        const nameMessage = createValidationMessage(nameInput);
        const emailMessage = createValidationMessage(emailInput);
        const mobileMessage = createValidationMessage(mobileInput);

        // Validation functions
        function validateName(name) {
            const trimmedName = name.trim();
            if (!trimmedName) {
                nameMessage.showError('Name is required');
                return false;
            }
            if (!/^[a-zA-Z\s]{2,50}$/.test(trimmedName)) {
                nameMessage.showError('Name should contain only letters and spaces (2-50 characters)');
                return false;
            }
            nameMessage.clearError();
            return true;
        }

        function validateEmail(email) {
            const trimmedEmail = email.trim();
            if (!trimmedEmail) {
                emailMessage.showError('Email is required');
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
                emailMessage.showError('Invalid email format');
                return false;
            }
            emailMessage.clearError();
            return true;
        }

        function validateMobile(mobile) {
            const trimmedMobile = mobile.trim();
            if (!trimmedMobile) {
                mobileMessage.showError('Mobile number is required');
                return false;
            }
            if (!/^[6789]\d{9}$/.test(trimmedMobile)) {
                mobileMessage.showError('Enter a valid mobile number');
                return false;
            }
            mobileMessage.clearError();
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
        const profileForm = document.getElementById('profile-form');
        const errorDiv = document.getElementById('error-message');
        const successDiv = document.getElementById('success-message');

        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear previous messages
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            errorDiv.textContent = '';
            successDiv.textContent = '';
            
            // Create FormData object from the form
            const formData = new FormData(this);
            
            // Send the form data
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Display error message
                    errorDiv.textContent = data.debug 
                        ? `${data.message}\n${JSON.stringify(data.debug)}`
                        : data.message;
                    errorDiv.style.display = 'block';
                } else {
                    // Show success message
                    successDiv.textContent = data.message;
                    successDiv.style.display = 'block';
                    
                    // Optional: Refresh the page after successful update
                    setTimeout(() => window.location.reload(), 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.textContent = 'An error occurred while updating the profile';
                errorDiv.style.display = 'block';
            });
        });
    });

        // Add event listeners for live validation
        nameInput.addEventListener('input', () => {
            validateName(nameInput.value);
        });
        emailInput.addEventListener('input', () => {
            validateEmail(emailInput.value);
        });
        mobileInput.addEventListener('input', () => {
            validateMobile(mobileInput.value);
        });
        
        // Add these event listeners for the edit form inputs
        const editNameInput = document.querySelector('input[name="name"]');
        const editEmailInput = document.querySelector('input[name="email"]');
        const editMobileInput = document.querySelector('input[name="mobile"]');

        editNameInput.addEventListener('input', () => {
            validateName(editNameInput.value);
        });
        editEmailInput.addEventListener('input', () => {
            validateEmail(editEmailInput.value);
        });
        editMobileInput.addEventListener('input', () => {
            validateMobile(editMobileInput.value);
        });
    </script>
</body>
</html> 