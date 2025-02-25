<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Fetch membership_id from the database
$user_email = $_SESSION['email']; // Assuming the email is used to identify the user
$conn = new mysqli('localhost', 'root', '', 'arenax'); // Update with your actual database credentials

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT membership_id FROM users WHERE email = '$user_email'"; // Adjust the query as per your database structure
$result = $conn->query($sql);
$membership_id = 0; // Default value

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $membership_id = $row['membership_id'];
}

$sql = "SELECT membership_id, name FROM users WHERE email = '$user_email'";
$result = $conn->query($sql);
$user_name = "Profile"; // Default value

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $membership_id = $row['membership_id'];
    $user_name = $row['name'];
}

// Fetch membership type from the database
$sql = "SELECT membership_type FROM memberships WHERE membership_id = '$membership_id'"; // Adjusted column name
$result = $conn->query($sql);
$membership_type = "Unknown"; // Default value

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $membership_type = $row['membership_type'];
}

// Fetch upcoming booking details
$sql = "SELECT sub_activity_name, booking_date FROM booking 
        JOIN sub_activity ON booking.sub_activity_id = sub_activity.sub_activity_id 
        WHERE booking.user_id = (SELECT user_id FROM users WHERE email = '$user_email') 
        AND booking.booking_date >= CURDATE() 
        ORDER BY booking.booking_date LIMIT 1"; // Adjust the query as per your database structure
$result = $conn->query($sql);
$upcoming_booking = "No upcoming bookings"; // Default value

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $upcoming_booking = $row['sub_activity_name'] . " on " . date("F j, Y", strtotime($row['booking_date']));
}

$conn->close();

// Check if the modal has been shown before
if (!isset($_SESSION['modal_shown'])) {
    $_SESSION['modal_shown'] = true; // Set the session variable to indicate the modal has been shown
    $show_modal = true; // Variable to control modal display
} else {
    $show_modal = false; // Do not show the modal again
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indoor Games</title>
    
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
            color: #333;
            overflow-x: hidden;
        }

        /* First Section: Hero Image for Indoor Games */
        .hero-section {
            position: relative;
            width: 100%;
            height: 80vh;
            background: url('img/h2.jpg') no-repeat center center;
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
.log
{
    padding: 10px 20px;
    font-size: 1rem; 
    font-family: 'Cinzel Decorative', cursive; 
    background-color: #007cd400; 
    color: white;
    padding: 10px 50px;
    border-style: solid;
    border-width:1px;
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

        .are{
            text-decoration: none;
        }

        /* Second Section: About What We Offer for Indoor Games */
        .about-section {
            padding: 4rem 2rem;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/abt3.jpg') no-repeat center center;
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
            display: flex;
            justify-content: space-around;
            margin-top: 2rem;
            flex-wrap: wrap; /* Allow wrapping if necessary */
        }
        
        .info-box {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 30%; /* Adjust width to fit three boxes */
            text-align: center;
            margin: 10px; /* Add margin for spacing */
        }
        
        .info-box h3 {
            font-family: 'Bodoni Moda', serif;
            color: #333;
            margin-bottom: 10px;
        }
        
        .info-box p {
            font-size: 1rem;
            color: #666;
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
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .about-content h2 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
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

        .about-section {
            margin-bottom: -2rem;
            margin-top: -2rem;
        }
        
        .offer-box {
            position: relative;
            display: inline-block;
            width: 400px;
            height: 200px;
            background: rgba(255, 255, 255, 0.72);
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .icon-container {
            position: absolute;
            right: 10px;
            top: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #00bcd4;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .offer-title {
            color: black;
            font-family: 'Aboreto', cursive;
        }
        
        .offer-description {
            color: black;
            font-family: 'Aboreto', cursive;
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
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r12.jpg') no-repeat center center;
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
            background: rgba(88, 177, 222, 0.27);
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
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r12.jpg') no-repeat center center;
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

/* Updated arrow position */
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
.membership-modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1000; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0, 0, 0, 0.8); /* Black w/ opacity */
    opacity: 0; /* Start invisible */
    transform: translateY(100%); /* Start below the viewport */
    transition: opacity 0.5s ease, transform 0.5s ease; /* Transition for smooth appearance */
}

.membership-modal.show {
    display: block; /* Show the modal */
    opacity: 1; /* Fully visible */
    transform: translateY(0); /* Slide into view */
}

.modal-content {
    font-family: 'Bodoni Moda', serif; /* Font family */
    background-color: black; /* Background color */
    margin: 10% auto; /* Center the modal */
    padding: 20px; /* Padding inside the modal */
    border-radius: 10px; /* Rounded corners */
    box-shadow: 0 4px 20px rgba(255, 255, 255, 0.2); /* Shadow effect */
    width: 80%; /* Could be more or less, depending on screen size */
    text-align: center; /* Center text */
}

.close {
    color: #aaa;
    float: right;
    font-size: 70px;
    font-weight: bold;
}

.close:hover,
.close:focus {
    color: #00bcd4; /* Change color on hover */
    text-decoration: none;
    cursor: pointer;
}

.membership-options {
    display: flex;
    justify-content: space-around;
    margin-bottom: 20px;
}

.membership {
    width: 45%; /* Width of each membership box */
    text-align: center;
    background-color: rgba(0, 0, 0, 0.39);
    border: 2px solid #00bcd4; /* Updated border color */
    border-radius: 8px; /* Rounded corners */
    padding: 10px; /* Padding inside the box */
    transform: translateY(20px); /* Start position below */
    opacity: 0; /* Start invisible */
    transition: transform 0.5s ease, opacity 0.5s ease; /* Transition for sliding and fading */
}

.membership.visible {
    transform: translateY(0); /* End position */
    opacity: 1; /* Fully visible */
}

.membership h3,
.membership .price-tag,
.membership ul {
    font-family: 'Bodoni Moda', serif; /* Font family for text */
    color: white; /* Text color */
}

.membership ul {
    padding: 0; /* Remove padding */
    text-align: left; /* Align text to the left */
}

.membership ul li {
    margin-bottom: 10px;
    margin-left: 150px; /* Added left margin */
}

.price-tag {
    font-size: 1.4rem;
}

.price-tag span {
    font-size: 1rem; /* Adjusted size for the month text */
}

.membership-btn {
    padding: 10px 20px;
    margin: 10px;
    font-size: 1.2rem;
    cursor: pointer;
    background-color: rgba(0, 187, 212, 0.04); /* Button background color */
    color: white; /* Button text color */
    border: 2px solid #00bcd4; /* Remove border */
    border-radius: 0px; /* Rounded corners */
    transition: background-color 0.3s ease; /* Smooth transition */
}

.membership-btn:hover {
    background-color: #00bcd4; /* Darker shade on hover */
}

.modal-title {
    color: white;
    margin-top: 10px;
    margin-bottom: 20px;
}

.modal-divider {
    width: 50px;
    height: 2px;
    background-color: white;
    margin: 0 auto;
    margin-bottom: 40px;
}

.membership-title {
    font-size: 1.5rem;
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
        <a href="user_calendar.php">CALENDER</a>
        <a href="user_payment_history.php">PAYMENT HISTORY</a>
        <a href="logout.php">LOGOUT</a>
    </div>
</div>
    </header>

    <!-- First Section: Hero Image for Indoor Games -->
    <section class="hero-section">
        <div class="hero-text">
            ArenaX
        </div>
        <div class="scroll-indicator" onclick="scrollToAbout()"></div>
    </section>

    <!-- Second Section: About What We Offer for Indoor Games -->
    <section class="about-section">
        <div class="about-content">
            <h2 style="font-family: 'Bodoni Moda', serif;">Quick Checks</h2>
        </div>
        <div style="display: flex; align-items: center; gap: 3rem;">
            <div class="offer-box">
                <div class="icon-container">
                    <i class="fas fa-user" style="color: white; font-size: 20px;"></i>
                </div>
                <h3 class="offer-title">MEMBERSHIP STATUS</h3><br>
                <p class="offer-description"><?php echo htmlspecialchars($membership_type); ?></p>
            </div>
            <div class="offer-box">
                <div class="icon-container">
                    <i class="fas fa-calendar" style="color: white; font-size: 20px;"></i>
                </div>
                <h3 class="offer-title">UPCOMING BOOKING</h3><br>
                <p class="offer-description"><?php echo htmlspecialchars($upcoming_booking); ?></p>
            </div>
            <div class="offer-box">
                <div class="icon-container">
                    <i class="fas fa-calendar-alt" style="color: white; font-size: 20px;"></i>
                </div>
                <h3 class="offer-title">UPCOMING EVENTS</h3><br>
                <p class="offer-description">No upcoming events</p>
            </div>
        </div>
    </section>
    <!-- Update the HTML structure to combine the sections -->
    <div class="activities-heading">
        <h2>OUR ACTIVITIES</h2>
    </div>
    <section class="image-grid">
        <!-- Row 1 of 4 images -->
        <div class="image">
            <img src="img/in1.png" alt="Outdoor Game 1">
            <div class="overlay">
                <h3>TABLE TENNIS</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
        <div class="image">
            <img src="img/in2.png" alt="Outdoor Game 2">
            <div class="overlay">
                <h3>ICE SKATING</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
        <div class="image">
            <img src="img/in3.png" alt="Outdoor Game 3">
            <div class="overlay">
                <h3>CHESS</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
        <div class="image">
            <img src="img/in4.png" alt="Outdoor Game 4">
            <div class="overlay">
                <h3>RIFLE SHOOTING</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>

        <!-- Row 2 of 4 images -->
        <div class="image">
            <img src="img/in5.png" alt="Outdoor Game 5">
            <div class="overlay">
                <h3>JENGA</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
        <div class="image">
            <img src="img/in6.png" alt="Outdoor Game 6">
            <div class="overlay">
                <h3>CARDS</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
        <div class="image">
            <img src="img/in7.png" alt="Outdoor Game 7">
            <div class="overlay">
                <h3>BILLARDS</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
        <div class="image">
            <img src="img/in7.png" alt="Outdoor Game 8">
            <div class="overlay">
                <h3>CAROMS</h3>
                <a href="signup.php"><button class="book-now">Book Now</button></a>
            </div>
        </div>
    </section>
    <footer>
    <div class="footer-container">
        <div class="footer-column">
            <h3>ArenaX</h3>
            <p>Your premier destination for sports and fitness. Explore a variety of activities and join our vibrant community.</p>
        </div>
        <div class="footer-column">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="user_home.php">Home</a></li>
                <li><a href="user_indoor.php">Indoor Activities</a></li>
                <li><a href="user_outdoor.php">Outdoor Activities</a></li>
                <li><a href="user_fitness.php">Fitness Activities</a></li>
                <li><a href="user_events.php">Events</a></li>
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

    <!-- Check membership_id before displaying the modal -->
    <?php if ($membership_id != 4 && $show_modal): ?>
        <div class="membership-modal" id="membershipModal">
            <div class="modal-content"> 
                <span class="close" id="closeModal">&times;</span>
                <h2 class="modal-title">UPGRADE &nbsp; MEMBERSHIP</h2>
                <div class="modal-divider"></div>
                <div class="membership-options">
                    <?php if ($membership_id == 2): ?>
                        <div class="membership" style="border: 2px solid #00bcd4; border-radius: 10px; padding: 20px; background-color: rgba(0, 0, 0, 0.5);">
                            <h3 class="membership-title">STANDARD</h3>
                            <div class="price-tag">₹1000<span>/month</span></div>
                            <ul>
                                <li>All Free benefits included</li>
                                <li>Full access to indoor activities</li>
                                <li>Personal Gallery Access</li>
                                <li>Calendar Access to view schedules</li>
                                <li>Limited access to events</li>
                            </ul>
                            <a href="membership_payment.php?membership_id=3"><button class="membership-btn">Select Standard</button></a>
                        </div>
                        <div class="membership" style="border: 2px solid #00bcd4; border-radius: 10px; padding: 20px; background-color: rgba(0, 0, 0, 0.5);">
                            <h3 class="membership-title">PREMIUM</h3>
                            <div class="price-tag">₹2000<span>/month</span></div>
                            <ul>
                                <li>All Standard benefits included</li>
                                <li>Full access to all facilities</li>
                                <li>Access to all events</li>
                                <li>Access to booking for guests</li>
                                <li>20% Discount on bookings</li>
                            </ul>
                            <a href="membership_payment.php?membership_id=4"><button class="membership-btn">Select Premium</button></a>
                        </div>
                    <?php elseif ($membership_id == 3): ?>
                        <div class="membership" style="border: 2px solid #00bcd4; border-radius: 10px; padding: 20px; background-color: rgba(0, 0, 0, 0.5);">
                            <h3 class="membership-title">PREMIUM</h3>
                            <div class="price-tag">₹2000<span>/month</span></div>
                            <ul>
                                <li>All Standard benefits included</li>
                                <li>Full access to all facilities</li>
                                <li>Access to all events</li>
                                <li>Access to booking for guests</li>
                                <li>20% Discount on bookings</li>
                            </ul>
                            <a href="membership_payment.php?membership_id=4"><button class="membership-btn">Select Premium</button></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const header = document.querySelector('.header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
        
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

        // Add this to your script section
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            const profileButton = document.querySelector('.log');
            
            // Check if the click is outside both the dropdown and the profile button
            if (!dropdown.contains(event.target) && !profileButton.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Prevent the dropdown from closing when clicking inside it
        dropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        // Show the membership modal after a 2-second delay on page load
        window.onload = function() {
            const modal = document.getElementById('membershipModal');
            if (<?php echo json_encode($show_modal); ?>) { // Check if modal should be shown
                setTimeout(() => {
                    modal.classList.add('show'); // Add the show class to trigger the transition
                    modal.style.display = 'block'; // Ensure the modal is displayed
                }, 2000); // 2000 milliseconds = 2 seconds
            }

            // Close the modal when the close button is clicked
            document.getElementById('closeModal').onclick = function() {
                modal.classList.remove('show'); // Remove the show class to hide the modal
                setTimeout(() => {
                    modal.style.display = 'none'; // Set display to none after transition
                }, 500); // Match the timeout with the transition duration
            };

            // Close the modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.classList.remove('show'); // Remove the show class to hide the modal
                    setTimeout(() => {
                        modal.style.display = 'none'; // Set display to none after transition
                    }, 500); // Match the timeout with the transition duration
                }
            };
        };

        // Add this to your existing JavaScript
        const membershipBoxes = document.querySelectorAll('.membership');
        membershipBoxes.forEach(box => {
            observer.observe(box); // Observe each membership box for visibility
        });

        function redirectToPayment(membershipId) {
            // Redirect to the payment page with the selected membership ID
            window.location.href = 'payment.php?membership_id=' + membershipId; // Redirect to payment page
        }
    </script>
</body>
</html>
