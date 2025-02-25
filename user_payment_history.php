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

$user_id = $_SESSION['user_id'];

// Fetch membership payment history
$sql = "SELECT p.payment_id, p.amount, p.payment_date, p.payment_time, 
        m.membership_type, mr.membership_reg_date
        FROM payment p
        INNER JOIN membership_reg mr ON p.membership_reg_id = mr.membership_reg_id
        INNER JOIN memberships m ON mr.membership_id = m.membership_id
        WHERE p.user_id = ? AND p.membership_reg_id IS NOT NULL
        ORDER BY p.payment_date DESC, p.payment_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Payment History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
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
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/event1.jpg') no-repeat center center fixed;
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
        .payment-history-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background-color: rgba(199, 143, 94, 0.5); /* Semi-transparent background for readability */
            border-radius: 10px; /* Rounded corners */
            box-shadow: 0 0 15px rgba(0,0,0,0.2); /* Shadow effect */
            margin-top: 150px; /* Added margin to create space below the header */
            font-family: 'Bodoni Moda', serif; /* Set font to Bodoni for all content in this container */
        }
        .payment-card {
            margin-bottom: 20px;
            border: 1px solid rgb(0, 0, 0);
            border-radius: 10px;
            padding: 15px;
            background-color: rgb(165, 150, 136);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .payment-card p {
            margin: 10px 0; /* Added margin for spacing between details */
        }
        .status-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            background-color: rgb(195, 144, 96); /* Changed background color to yellow */
        }
        .no-payments {
            text-align: center;
            padding: 40px;
            color: #6c757d;
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

    <div class="payment-history-container">
        <h2 class="mb-4 text-white" style="font-size: 2em; margin-bottom: 20px;">
            <i class="fas fa-history"></i> Membership Payment History
        </h2>
        
        <?php if (count($result) > 0): ?>
            <?php foreach ($result as $row): ?>
                <div class="payment-card">
                    <p>
                        <span style="font-size: 1.5em; color: white;">
                            <?php echo date('d M Y', strtotime($row['payment_date'])); ?>
                        </span>
                    </p>
                    <p><strong>Membership Type:</strong> 
                        <span class="badge status-badge">
                            <?php echo htmlspecialchars($row['membership_type']); ?>
                        </span>
                    </p>
                    <p><strong>Amount:</strong> ₹<?php echo intval($row['amount']); ?></p>
                    <p><strong>Payment Time:</strong> <?php echo date('h:i A', strtotime($row['payment_time'])); ?></p>
                    <p><strong>Registration Date:</strong> <?php echo date('d M Y', strtotime($row['membership_reg_date'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-payments">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p>No membership payment history found.</p>
            </div>
        <?php endif; ?>

        <!-- Booking Payment History Section -->
        <h2 class="mb-4 text-white" style="font-size: 2em; margin-bottom: 20px;">
            <i class="fas fa-history"></i> Booking Payment History
        </h2>

        <?php
        // Fetch booking payment history
        $sql_booking = "SELECT p.payment_id, p.amount, p.payment_date, p.payment_time, 
                        b.booking_date
                        FROM payment p
                        INNER JOIN booking b ON p.booking_id = b.booking_id
                        WHERE p.user_id = ? AND p.booking_id IS NOT NULL
                        ORDER BY p.payment_date DESC, p.payment_time DESC";

        $stmt_booking = $conn->prepare($sql_booking);
        $stmt_booking->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt_booking->execute();
        $booking_result = $stmt_booking->fetchAll(PDO::FETCH_ASSOC);

        if (count($booking_result) > 0): ?>
            <?php foreach ($booking_result as $booking_row): ?>
                <div class="payment-card">
                    <p>
                        <span style="font-size: 1.5em; color: white;">
                            <?php echo date('d M Y', strtotime($booking_row['payment_date'])); ?>
                        </span>
                    </p>
                    <p><strong>Amount:</strong> ₹<?php echo intval($booking_row['amount']); ?></p>
                    <p><strong>Payment Time:</strong> <?php echo date('h:i A', strtotime($booking_row['payment_time'])); ?></p>
                    <p><strong>Booking Date:</strong> <?php echo date('d M Y', strtotime($booking_row['booking_date'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-payments">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p>No booking payment history found.</p>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html> 