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

// Get user's bookings with activity details
$stmt = $conn->prepare("
    SELECT 
        b.booking_id,
        sa.sub_activity_id,
        san.sub_act_name AS sub_activity_name,
        a.activity_type,
        DATE_FORMAT(ts.slot_date, '%d %M %Y') as formatted_date,
        TIME_FORMAT(ts.slot_start_time, '%l:%i %p') as start_time,
        TIME_FORMAT(ts.slot_end_time, '%l:%i %p') as end_time,
        p.amount,
        DATE_FORMAT(b.booking_date, '%d %M %Y') as booking_date,
        TIME_FORMAT(b.booking_time, '%l:%i %p') as booking_time
    FROM booking b
    JOIN sub_activity sa ON b.sub_activity_id = sa.sub_activity_id
    JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id
    JOIN activity a ON sa.activity_id = a.activity_id
    JOIN timeslots ts ON b.slot_id = ts.slot_id
    JOIN payment p ON b.booking_id = p.booking_id
    WHERE b.user_id = ?
    ORDER BY ts.slot_date DESC, ts.slot_start_time DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// After the existing bookings query, add this new query for events
$stmt = $conn->prepare("
    SELECT 
        er.event_reg_id,
        e.event_title,
        e.event_description,
        DATE_FORMAT(e.event_date, '%d %M %Y') as formatted_date,
        TIME_FORMAT(e.event_time, '%l:%i %p') as event_time,
        e.event_location,
        e.event_price,
        a.activity_type,
        DATE_FORMAT(p.payment_date, '%d %M %Y') as registration_date,
        TIME_FORMAT(p.payment_time, '%l:%i %p') as registration_time
    FROM event_registration er
    JOIN events e ON er.event_id = e.event_id
    JOIN activity a ON e.activity_id = a.activity_id
    JOIN payment p ON er.event_reg_id = p.event_reg_id
    WHERE er.user_id = ?
    ORDER BY e.event_date DESC, e.event_time DESC
");
$stmt->execute([$_SESSION['user_id']]);
$event_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
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
            font-family: 'Bodoni Moda', serif;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/event2.jpg') no-repeat center center fixed;
            background-size: cover;
            color: white;
            overflow-x: hidden;
        }

        /* Header styles from user_home.php */
        .header {
            font-family: 'Cinzel Decorative', cursive;
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

        

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-title {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 2.5rem;
            position: relative;
            padding-top: 100px;
            text-transform: uppercase;
        }

        .page-title:after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: #72aab0;
            margin: 10px auto;
            border-radius: 2px;
        }

        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .booking-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .activity-name {
            font-size: 1.4rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .booking-id {
            color: #72aab0;
            font-size: 0.9rem;
        }

        .booking-details {
            margin-bottom: 20px;
            font-family: 'Bodoni Moda', serif;
        }

        .booking-details .detail-row {
            font-family: Arial, sans-serif;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
            align-items: center;
        }

        .detail-label {
            width: 120px;
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #2c3e50;
            flex: 1;
        }

        .booking-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #eee;
        }

        .price {
            font-size: 1.2rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .booking-date {
            color: #666;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-upcoming {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .no-bookings {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin: 20px auto;
            max-width: 600px;
            font-family: 'Bodoni Moda', serif;
        }

        .no-bookings i {
            font-size: 50px;
            color: #72aab0;
            margin-bottom: 20px;
        }

        .no-bookings h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .no-bookings p {
            color: #666;
            margin-bottom: 20px;
        }

        .book-now-btn {
            display: inline-block;
            padding: 12px 25px;
            background: #72aab0;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: background 0.3s ease;
        }

        .book-now-btn:hover {
            background: #5d8f94;
        }

        @media (max-width: 768px) {
            .bookings-grid {
                grid-template-columns: 1fr;
            }

            .booking-card {
                margin: 10px;
            }

            .page-title {
                font-size: 2rem;
            }
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
    <div class="container">
        <h1 class="page-title">My Bookings</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-alt"></i>
                <h3>No Bookings Found</h3>
                <p>You haven't made any bookings yet. Start exploring our activities!</p>
                <a href="user_outdoor.php" class="book-now-btn">Book Now</a>
            </div>
        <?php else: ?>
            <div class="bookings-grid">
                <?php foreach ($bookings as $booking): 
                    $isUpcoming = strtotime($booking['formatted_date']) >= strtotime('today');
                ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="activity-name"><?php echo strtoupper(htmlspecialchars($booking['sub_activity_name'])); ?></div>
                            <!-- 
                            <div class="booking-id">#<?php echo $booking['booking_id']; ?></div>
                            -->
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-row">
                                <span class="detail-label">Activity Type:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['activity_type']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date:</span>
                                <span class="detail-value"><?php echo $booking['formatted_date']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Time:</span>
                                <span class="detail-value">
                                    <?php echo $booking['start_time'] . ' - ' . $booking['end_time']; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="status-badge <?php echo $isUpcoming ? 'status-upcoming' : 'status-completed'; ?>">
                                    <?php echo $isUpcoming ? 'Upcoming' : 'Completed'; ?>
                                </span>
                            </div>
                            <div class="detail-row" style="padding-left: 0px;">
                                <span class="detail-label">Booked on:</span>
                                <span class="detail-value">
                                    <?php echo $booking['booking_date'] . ' at ' . $booking['booking_time']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="booking-footer">
                            <div class="price">₹<?php echo number_format($booking['amount'], 2); ?></div>
                            <!-- 
                            <div class="booking-date">
                                Booked on <?php echo $booking['booking_date']; ?> at <?php echo $booking['booking_time']; ?>
                            </div>
                            -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h1 class="page-title">My Event Registrations</h1>

        <?php if (empty($event_registrations)): ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-check"></i>
                <h3>No Event Registrations Found</h3>
                <p>You haven't registered for any events yet. Check out our upcoming events!</p>
                <a href="user_events.php" class="book-now-btn">View Events</a>
            </div>
        <?php else: ?>
            <div class="bookings-grid">
                <?php foreach ($event_registrations as $event): 
                    $isUpcoming = strtotime($event['formatted_date']) >= strtotime('today');
                ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="activity-name"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="detail-row">
                                <span class="detail-label">Activity Type:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($event['activity_type']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date:</span>
                                <span class="detail-value"><?php echo $event['formatted_date']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Time:</span>
                                <span class="detail-value"><?php echo $event['event_time']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($event['event_location']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="status-badge <?php echo $isUpcoming ? 'status-upcoming' : 'status-completed'; ?>">
                                    <?php echo $isUpcoming ? 'Upcoming' : 'Completed'; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Registered on:</span>
                                <span class="detail-value">
                                    <?php echo $event['registration_date'] . ' at ' . $event['registration_time']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="booking-footer">
                            <div class="price">₹<?php echo number_format($event['event_price'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
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

        dropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        

        
    </script>
</body>
</html> 