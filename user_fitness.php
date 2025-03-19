<?php
session_start();
require_once 'db_connect.php'; // Update the path as necessary

// Redirect to login if user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Initialize the activities array
$activities = []; 

// Update the SQL query to get all dates and their time slots
$sql = "SELECT sa.sub_activity_id, san.sub_act_name, sa.sub_activity_image, sa.sub_activity_price, 
        GROUP_CONCAT(DISTINCT 
            CASE 
                WHEN ts.slot_date >= CURDATE() 
                AND (ts.slot_date > CURDATE() 
                    OR (ts.slot_date = CURDATE() AND ts.slot_end_time >= CURTIME()))
                AND ts.current_participants < ts.max_participants
                THEN DATE_FORMAT(ts.slot_date, '%Y-%m-%d')
            END
            ORDER BY ts.slot_date ASC
        SEPARATOR '|') as available_dates,
        GROUP_CONCAT(
            CASE 
                WHEN ts.slot_date >= CURDATE() 
                AND (ts.slot_date > CURDATE() 
                    OR (ts.slot_date = CURDATE() AND ts.slot_end_time >= CURTIME()))
                AND ts.current_participants < ts.max_participants
                THEN CONCAT(
                    DATE_FORMAT(ts.slot_date, '%Y-%m-%d'), ':', 
                    TIME_FORMAT(ts.slot_start_time, '%H:%i'), '|',
                    TIME_FORMAT(ts.slot_end_time, '%H:%i')
                )
            END
            SEPARATOR ';'
        ) as all_slots
        FROM sub_activity sa
        LEFT JOIN timeslots ts ON sa.sub_activity_id = ts.sub_activity_id 
        LEFT JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id
        WHERE sa.activity_id = 3
        AND (ts.current_participants IS NULL OR ts.current_participants < ts.max_participants)
        GROUP BY sa.sub_activity_id";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    // Fetch all results into the activities array
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC); // Use fetchAll() for PDO

} catch (PDOException $e) {
    error_log("Error in user_fitness.php: " . $e->getMessage());
    // Handle error appropriately
}

// Debug output
echo "<!-- Debug output:\n";
var_dump($activities);
echo "\n-->";

// Modify the existing SQL query to also fetch the user's name
$user_email = $_SESSION['email']; // Define $user_email from session
$sql = "SELECT membership_id, name FROM users WHERE email = '$user_email'";
$stmt = $conn->query($sql); // Use query() to execute the SQL statement
$user_name = "Profile"; // Default value

if ($stmt && $stmt->rowCount() > 0) { // Use rowCount() to check for rows
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $membership_id = $row['membership_id'];
    $user_name = $row['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Activities</title>
    
    <!-- Favicon link -->
    <link rel="icon" href="img/logo3.png" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Aboreto&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400&family=Goldman&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Bodoni+Moda:wght@400;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cinzel Decorative', cursive;
            color: #333;
            overflow-x: hidden;
        }

        /* First Section: Hero Image for Indoor Games */
        .hero-section {
            position: relative;
            width: 100%;
            height: 80vh;
            background: url('img/r10.jpg') no-repeat center center;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-text {
            color: white;
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 1.5s ease forwards;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Update scroll down arrow styles to match homepage */
        .scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 50px;
            border-radius: 60%;
            cursor: pointer;
            z-index: 10;
            animation: bounce 2s infinite;
        }

        .scroll-indicator::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            width: 24px;
            height: 24px;
            border-left: 3px solid white;
            border-bottom: 3px solid white;
            transform: translateX(-50%) rotate(-45deg);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }

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
        
        .header.scrolled {
            background: rgba(0, 0, 0, 1);
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
            margin-right: 40px; /* Adjust spacing to push buttons further right */
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

        /* Second Section: About What We Offer for Indoor Games */
        .about-section {
            padding: 4rem 2rem;
            background: linear-gradient(rgba(0, 0, 0, 0.38), rgba(0, 0, 0, 0.64)), url('img/r13.jpg') no-repeat center center;
            background-size: cover;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3rem;
            max-width: 100%;
            margin: 0 auto;
            color: white;
            text-align: center;
            scroll-margin-top: 100px;
        }

        .about-content {
            opacity: 0;
            transform: translateY(50px);
            transition: all 1.5s ease;
        }

        .about-content.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .about-image {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 1.5s ease;
        }

        .about-image.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .about-image img {
            width: 100%;
            max-width: 2000px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .about-content h2 {
            font-size: 2.8rem;
            margin-bottom: 3rem;
            text-align: center;
            color: white;
            font-family: 'Bodoni Moda', serif;
            position: relative;
        }

        .about-content h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #00bcd4, #ff4081, #00bcd4);
            background-size: 200% 100%;
            animation: gradientMove 3s ease infinite;
        }

        .about-content p {
            font-size: 1.2rem;
            line-height: 1.6;
            font-family: 'Aboreto', cursive;
            color: white;
        }

        @media screen and (max-width: 768px) {
            .about-section {
                flex-direction: column;
                padding: 2rem 1rem;
            }
            
            .about-image {
                min-width: 100%;
            }
        }

        /* Third Section - Grid of Indoor Game Images */
        .image-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            gap: 1rem;
            padding: 4rem 2rem;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r14.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            margin-top: -2rem; /* Remove gap between heading and grid */
        }

        .image-grid img {
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        /* Remove zoom effect on hover */
        .image-grid img:hover {
            transform: none;
        }

        /* Make sure to have four images per row */
        .image-grid .image {
            width: calc(25% - 1rem);
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .image-grid .image:hover {
            transform: scale(1.05);
        }

        /* Hide text and button initially */
        .image-grid .overlay {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            text-align: center;
            width: 100%;
            transition: all 0.5s ease;
        }

        /* On hover, the text and button slide into view */
        .image-grid .image:hover .overlay {
            background: rgba(29, 32, 33, 0.27);
            top: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* Text for images */
        .image-grid .overlay h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            transition: top 0.5s ease;
            color: white;
            font-family: 'Bodoni Moda', serif;
        }

        /* "Book Now" button */
        .image-grid .overlay .book-now {
            background-color: #00bcd4;
            color: white;
            padding: 10px 20px;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease, top 0.5s ease;
            margin-top: 10px;
            font-family: 'Bodoni Moda', serif;
        }

        .image-grid .image:hover .overlay .book-now {
            opacity: 1;
            top: 20px;
        }

        @media screen and (max-width: 768px) {
            .image-grid .image {
                width: calc(50% - 1rem); /* 2 images per row for smaller screens */
            }
        }

        @media screen and (max-width: 480px) {
            .image-grid .image {
                width: 100%; /* 1 image per row for very small screens */
            }
        }

        /* Footer Styles */
        footer {
            background-color: #282c34;
            font-family: 'Goldman', cursive;
            color: white;
            padding: 40px 20px;
        }

        .footer-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-column {
            flex: 1;
            min-width: 250px;
            margin: 10px;
        }

        .footer-column h3 {
            margin-bottom: 15px;
            font-size: 18px;
            text-transform: uppercase;
            color: #9f799e; /* Highlighted color for headings */
        }

        .footer-column p,
        .footer-column ul {
            font-size: 14px;
            line-height: 1.6;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
        }

        .footer-column ul li {
            margin-bottom: 10px;
        }

        .footer-column ul li a {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column ul li a:hover {
            color: #00eeff; /* Highlight color on hover */
        }

        .social-links {
            display: flex;
            gap: 10px;
        }

        .social-links a {
            color: white;
            font-size: 20px;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: #6ad3d8; /* Highlight color on hover */
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            font-size: 14px;
            border-top: 1px solid #444;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .footer-container {
                flex-direction: column;
                text-align: center;
            }

            .footer-column {
                margin: 20px 0;
            }
        }

        /* Add styles for the activities heading */
        .activities-heading {
            text-align: center;
            padding: 4rem 0 2rem 0;
            position: relative;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r14.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
        }

        .activities-heading h2 {
            font-size: 2.8rem;
            color: white;
            font-family: 'Bodoni Moda', serif;
            position: relative;
            display: inline-block;
        }

        .activities-heading h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #00bcd4, #ff4081, #00bcd4);
            background-size: 200% 100%;
            animation: gradientMove 3s ease infinite;
        }

        @keyframes gradientMove {
            0% {
                background-position: 100% 0;
            }
            50% {
                background-position: 0 0;
            }
            100% {
                background-position: 100% 0;
            }
        }

        /* Dropdown styles */
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

        /* Arrow at the top of dropdown */
        .dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 20px;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid rgba(0, 0, 0, 0.9);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1001;
            overflow-y: auto;
        }

        .modal-content {
            position: relative;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin: 5% auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            color: white;
            font-family: 'Bodoni Moda', serif;
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 30px;
            cursor: pointer;
            color: white;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #00bcd4;
        }

        .modal-body {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .modal-image-container {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            border-radius: 10px;
            overflow: hidden;
        }

        .modal-image-container img {
            width: 100%;
            height: auto;
            display: block;
        }

        .modal-details {
            flex: 1;
            padding: 0 20px;
        }

        .modal-details h2 {
            font-family: 'Bodoni Moda', serif;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }

        .activity-details {
            margin-bottom: 20px;
        }

        .detail-row {
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        .detail-label {
            font-family: 'Bodoni Moda', serif;
            font-weight: bold;
            color: #00bcd4;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .time-slot-select {
            width: 100%;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 5px;
            font-family: 'Aboreto', cursive;
        }

        .time-slot-select option {
            background: #2c3e50;
            color: white;
        }

        .price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #00bcd4;
        }

        .proceed-payment {
            background-color: #00bcd4;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Bodoni Moda', serif;
            width: 100%;
        }

        .proceed-payment:hover {
            background-color: #008ba3;
            transform: translateY(-2px);
        }

        .proceed-payment:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        @media screen and (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }

        .time-slot-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }

        .time-slot-button {
            padding: 10px 20px;
            border: 2px solid #00bcd4;
            background-color: transparent;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Bodoni Moda', serif;
            font-size: 1rem;
            min-width: 200px; /* Ensure buttons are wide enough for the time format */
            text-align: center;
            letter-spacing: 0.5px;
        }

        .time-slot-button:hover {
            background-color: rgba(0, 188, 212, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 188, 212, 0.3);
        }

        .time-slot-button.selected {
            background-color: #00bcd4;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 188, 212, 0.3);
        }

        .time-slot-button:disabled {
            border-color: #666;
            color: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .detail-row {
            margin-bottom: 20px;
        }

        .time-slot-select {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 5px;
            font-family: 'Aboreto', cursive;
            margin-bottom: 10px;
        }

        .time-slot-select option {
            background: #2c3e50;
            color: white;
        }

        /* Add these styles for the date picker */
        input[type="date"] {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 5px;
            font-family: 'Aboreto', cursive;
            margin-bottom: 10px;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
    </style>
</head>
<body>
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
                <a href="user_calendar.php">CALENDAR</a>
                <a href="user_payment_history.php">PAYMENT HISTORY</a>
                <a href="logout.php">LOGOUT</a>
            </div>
        </div>
    </header>

    <script>
        const header = document.querySelector('.header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>

    <!-- First Section: Hero Image for Indoor Games -->
    <section class="hero-section">
        <div class="hero-text">
            gym activities
        </div>
        <div class="scroll-indicator" onclick="scrollToAbout()"></div>
    </section>

    <!-- Second Section: About What We Offer for Indoor Games -->
    <section class="about-section">
        <div class="about-content" style="margin-bottom: -2rem; margin-top: -2rem;">
            <h2 style="font-family: 'Bodoni Moda', serif;">What We Offer</h2>
        </div>
        <div style="display: flex; align-items: center; gap: 3rem;">
            <div class="about-image">
                <img src="img/r6.jpg" alt="About Indoor Games">
            </div>
            <div class="about-content">
                <p style="font-family: 'Bodoni Moda', serif; font-size: 1.4rem;">
                Welcome to FitZone, your ultimate destination for achieving your fitness goals. From top-of-the-line cardio equipment to strength training machines, we provide everything you need to stay fit and healthy. Join our community of fitness enthusiasts and start your transformation today!
                </p>
            </div>
        </div>
    </section>

    <!-- Update the HTML structure to combine the sections -->
    <div class="activities-heading">
        <h2>OUR ACTIVITIES</h2>
    </div>

    <section class="image-grid">
        <?php if (!empty($activities)): ?>
            <?php foreach ($activities as $activity): ?>
                <div class="image">
                    <img src="<?php echo htmlspecialchars($activity['sub_activity_image']); ?>" alt="<?php echo htmlspecialchars($activity['sub_act_name']); ?>">
                    <div class="overlay">
                        <h3><?php echo htmlspecialchars($activity['sub_act_name']); ?></h3>
                        <button class="book-now" onclick="openBookingModal(<?php echo htmlspecialchars(json_encode($activity)); ?>)">Book Now</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No activities available.</p>
        <?php endif; ?>
    </section>

    <!-- Update the modal HTML -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-body">
                <h2 style="text-align: center; margin-bottom: 20px; color: #00bcd4;">DETAILS</h2>
                <div class="modal-image-container">
                    <img id="modalImage" src="" alt="Activity Image">
                </div>
                <div class="modal-details">
                    <h2 id="modalTitle"></h2>
                    <div class="activity-details">
                        <div class="detail-row">
                            <span class="detail-label">Activity:</span>
                            <span id="modalActivityName"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Select Date:</span>
                            <div id="dateContainer">
                                <input type="date" id="datePicker" class="time-slot-select">
                            </div>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Available Time Slots:</span>
                            <div id="timeSlotContainer" class="time-slot-buttons">
                                <!-- Time slot buttons will be inserted here -->
                            </div>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Price:</span>
                            <span class="price">₹<span id="modalPrice"></span></span>
                        </div>
                    </div>
                    <button class="proceed-payment" onclick="proceedToPayment()" id="proceedBtn" disabled>Proceed to Payment</button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-container">
            <div class="footer-column">
                <h3>ArenaX</h3>
                <p>Your premier destination for sports and fitness. Explore a variety of activities and join our vibrant community.</p>
            </div>
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="homepage.php">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="indoor.php">Indoor Activities</a></li>
                    <li><a href="outdoor.php">Outdoor Activities</a></li>
                    <li><a href="homepage.php#membership">Membership</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Contact Us</h3>
                <p>Email: arenax@gmail.com</p>
                <p>Phone: 9544147855</p>
                <p>Address: 123 ArenaX Avenue, Sportstown</p>
            </div>
            <div class="footer-column">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 ArenaX. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function scrollToAbout() {
            const aboutSection = document.querySelector('.about-section');
            const headerHeight = document.querySelector('.header').offsetHeight;
            const elementPosition = aboutSection.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerHeight;

            window.scrollTo({
                top: offsetPosition,
                behavior: "smooth"
            });
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.2 // Trigger when 20% of the element is visible
        });

        // Observe both the content and image
        document.querySelectorAll('.about-content, .about-image').forEach(element => {
            observer.observe(element);
        });

        // Profile dropdown functionality
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

        // Handle clicking outside dropdown
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            const profileButton = document.querySelector('.log');
            
            if (!dropdown.contains(event.target) && !profileButton.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Prevent the dropdown from closing when clicking inside it
        dropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // Add the JavaScript functions for modal handling
        let selectedTimeSlot = null;

        function formatTime(timeStr) {
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            return date.toLocaleDateString('en-US', options);
        }

        function openBookingModal(activity) {
            const modal = document.getElementById('bookingModal');
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('modalTitle');
            const modalActivityName = document.getElementById('modalActivityName');
            const modalPrice = document.getElementById('modalPrice');
            const datePicker = document.getElementById('datePicker');
            const timeSlotContainer = document.getElementById('timeSlotContainer');
            const proceedBtn = document.getElementById('proceedBtn');

            // Set the modal content
            modalImage.src = activity.sub_activity_image;
            modalTitle.textContent = activity.sub_act_name;
            modalActivityName.textContent = activity.sub_act_name;
            modalPrice.textContent = activity.sub_activity_price;

            // Clear existing selections
            timeSlotContainer.innerHTML = '';
            selectedTimeSlot = null;
            proceedBtn.disabled = true;

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            datePicker.min = today;
            datePicker.value = today;

            // Remove existing event listener and add new one
            datePicker.removeEventListener('change', handleDateChange);
            datePicker.addEventListener('change', handleDateChange);

            // Show the modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';

            // Store the sub_activity_id for use in handleDateChange
            datePicker.dataset.subActivityId = activity.sub_activity_id;

            // Trigger initial time slots fetch
            fetchTimeSlots(activity.sub_activity_id, today);
        }

        // Separate function to handle date changes
        function handleDateChange(event) {
            const selectedDate = event.target.value;
            const subActivityId = event.target.dataset.subActivityId;
            if (selectedDate && subActivityId) {
                fetchTimeSlots(subActivityId, selectedDate);
            }
        }

        // Fetch time slots from the database based on selected date and sub-activity
        function fetchTimeSlots(subActivityId, selectedDate) {
            const timeSlotContainer = document.getElementById('timeSlotContainer');
            const proceedBtn = document.getElementById('proceedBtn');

            timeSlotContainer.innerHTML = ''; // Clear previous time slots
            proceedBtn.disabled = true; // Disable proceed button

            fetch(`fetch_time_slots.php?sub_activity_id=${subActivityId}&date=${selectedDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(slot => {
                            const button = document.createElement('button');
                            button.className = 'time-slot-button';
                            const formattedStart = formatTime(slot.slot_start_time);
                            const formattedEnd = formatTime(slot.slot_end_time);
                            button.textContent = `${formattedStart} - ${formattedEnd}`;
                            button.onclick = function() {
                                document.querySelectorAll('.time-slot-button').forEach(btn => {
                                    btn.classList.remove('selected');
                                });
                                this.classList.add('selected');
                                selectedTimeSlot = `${formattedStart} - ${formattedEnd}`;
                                proceedBtn.disabled = false;
                            };
                            timeSlotContainer.appendChild(button);
                        });
                    } else {
                        timeSlotContainer.innerHTML = '<p style="color: white; text-align: center;">No time slots available for this date.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching time slots:', error);
                    timeSlotContainer.innerHTML = '<p style="color: white; text-align: center;">Error loading time slots.</p>';
                });
        }

        function proceedToPayment() {
            const selectedDate = document.getElementById('datePicker').value;
            const activityName = document.getElementById('modalActivityName').textContent;
            const price = document.getElementById('modalPrice').textContent;
            
            if (selectedDate && selectedTimeSlot) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'payment.php';

                const fields = {
                    'activity': activityName,
                    'date': selectedDate,
                    'timeslot': selectedTimeSlot,
                    'price': price
                };

                for (const [key, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking the close button
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('bookingModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });

        // Close modal when clicking outside the modal content
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>
