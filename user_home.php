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
$sql = "SELECT san.sub_act_name AS sub_activity_name, b.booking_date 
        FROM booking b
        JOIN sub_activity sa ON b.sub_activity_id = sa.sub_activity_id 
        JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id
        WHERE b.user_id = (SELECT user_id FROM users WHERE email = '$user_email') 
        AND b.booking_date >= CURDATE() 
        ORDER BY b.booking_date LIMIT 1"; // Adjusted to join with sub_activity_name
$result = $conn->query($sql);
$upcoming_booking = "No upcoming bookings"; // Default value

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $upcoming_booking = $row['sub_activity_name'] . " on " . date("F j, Y", strtotime($row['booking_date']));
}

// Update this SQL query to match your actual table structure
$sql = "SELECT event_title AS event_name, event_date 
        FROM events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC 
        LIMIT 1";
$result = $conn->query($sql);
$upcoming_event = "No upcoming events"; // Default value

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $upcoming_event = $row['event_name'] . " on " . date("F j, Y", strtotime($row['event_date']));
}

// Add this PHP code after the existing database queries
$activityQuery = "SELECT DISTINCT a.activity_type, san.sub_act_name 
                 FROM activity a 
                 LEFT JOIN sub_activity_name san ON a.activity_id = san.activity_id
                 ORDER BY a.activity_type, san.sub_act_name";
$activityResult = $conn->query($activityQuery);

$activities = [];
while ($row = $activityResult->fetch_assoc()) {
    $activities[$row['activity_type']][] = $row['sub_act_name'];
}

// Add events as a separate category (not tied to activity_type)
// This pulls events directly without requiring 'Event' as an activity type
$eventsQuery = "SELECT event_title FROM events ORDER BY event_title";
$eventsResult = $conn->query($eventsQuery);

echo "<!-- Debug: Events query executed. Found " . $eventsResult->num_rows . " events -->";

// Create an "Event" category in the activities array even though it's not an activity type
$activities['Event'] = []; 

while ($row = $eventsResult->fetch_assoc()) {
    $activities['Event'][] = $row['event_title'];
    echo "<!-- Debug: Added event: " . $row['event_title'] . " to Event category -->";
}

// Debug - print all available activity types and their activities
echo "<!-- Available activity types: " . implode(', ', array_keys($activities)) . " -->";
foreach ($activities as $type => $items) {
    echo "<!-- Type: $type, Items: " . count($items) . " -->";
}

// Convert PHP array to JSON for JavaScript use
$activitiesJson = json_encode($activities);

// Add this query to fetch membership registration date
$membershipDatesQuery = "SELECT mr.membership_reg_date";

// Check if expiration_date column exists
$checkColumnQuery = "SHOW COLUMNS FROM membership_reg LIKE 'expiration_date'";
$columnResult = $conn->query($checkColumnQuery);
if ($columnResult && $columnResult->num_rows > 0) {
    // Column exists, include it in the query
    $membershipDatesQuery .= ", mr.expiration_date";
}

// Complete the query
$membershipDatesQuery .= " FROM membership_reg mr 
                        JOIN users u ON mr.user_id = u.user_id 
                        WHERE u.email = '$user_email' 
                        ORDER BY mr.membership_reg_id DESC 
                        LIMIT 1";
$membershipDatesResult = $conn->query($membershipDatesQuery);

$registration_date = "Not available";
$expiration_date = "Not available";

if ($membershipDatesResult && $membershipDatesResult->num_rows > 0) {
    $datesRow = $membershipDatesResult->fetch_assoc();
    $registration_date = date("F j, Y", strtotime($datesRow['membership_reg_date']));
    
    // Check if expiration_date exists in the result
    if (isset($datesRow['expiration_date'])) {
        $expiration_date = date("F j, Y", strtotime($datesRow['expiration_date']));
    }
}

$conn->close();

// Check if the modal has been shown before (only for auto-display on page load)
if (!isset($_SESSION['modal_shown'])) {
    $_SESSION['modal_shown'] = true; // Set the session variable to indicate the modal has been shown
    $auto_show_modal = true; // Variable to control automatic modal display
} else {
    $auto_show_modal = false; // Do not automatically show the modal again
}

// Always allow manual triggering of the modal via the update button
$show_modal = ($membership_id == 2 || $membership_id == 3); // Show modal if user has eligible membership
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    
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
        /* Quick Checks Section Redesign */
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

.quick-checks-container {
    display: flex;
    justify-content: space-between;
    width: 100%;
    max-width: 1200px;
    flex-wrap: wrap;
    gap: 20px;
}

.quick-check-box {
    background: rgba(0, 0, 0, 0.7);
    border: 2px solid #00bcd4;
    border-radius: 10px;
    padding: 35px 25px;
    width: calc(33.33% - 20px);
    min-width: 300px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.quick-check-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
}

.quick-check-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #00bcd4, #1e88e5, #00bcd4);
    background-size: 200% 100%;
    animation: gradientMove 3s ease infinite;
}

.quick-check-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00bcd4, #1e88e5);
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto 20px;
    box-shadow: 0 5px 15px rgba(0, 188, 212, 0.3);
}

.quick-check-icon i {
    color: white;
    font-size: 30px;
}

.quick-check-title {
    font-family: 'Bodoni Moda', serif;
    color: white;
    font-size: 1.5rem;
    margin-bottom: 15px;
    letter-spacing: 1px;
}

.quick-check-divider {
    width: 50px;
    height: 2px;
    background: rgba(255, 255, 255, 0.3);
    margin: 15px auto;
}

.quick-check-content {
    font-family: 'Bodoni Moda', serif;
    color: #00bcd4;
    font-size: 1.1rem;
    margin-top: 10px;
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

@media (max-width: 992px) {
    .quick-check-box {
        width: calc(50% - 20px);
    }
}

@media (max-width: 768px) {
    .quick-check-box {
        width: 100%;
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

/* Updated arrow position for right-aligned dropdown */
.dropdown::before {
    content: '';
    position: absolute;
    top: -8px;
    right: 20px; /* Position arrow to the right instead of left */
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

.feedback-section {
    padding: 4rem 2rem;
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r12.jpg') no-repeat center center;
    background-size: cover;
    text-align: center;
    color: white;
}

.feedback-heading h2 {
    font-family: 'Bodoni Moda', serif;
    font-size: 2.8rem;
    margin-bottom: 2rem;
    position: relative;
    display: inline-block;
}

.feedback-heading h2::after {
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

.feedback-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
    background: rgba(0, 0, 0, 0.5);
    border: 2px solid #00bcd4;
    border-radius: 10px;
}

.rating-container {
    margin-bottom: 2rem;
}

.rating-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
}

.rate-text {
    font-family: 'Bodoni Moda', serif;
    font-size: 1.5rem;
    color: white;
}

.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 10px;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    font-size: 30px;
    color: #ddd;
    transition: color 0.3s ease;
}

.rating label:hover,
.rating label:hover ~ label,
.rating input:checked ~ label {
    color: #00bcd4;
}

textarea {
    width: 100%;
    height: 150px;
    padding: 15px;
    margin-bottom: 1rem;
    border: 2px solid #00bcd4;
    border-radius: 5px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-family: 'Bodoni Moda', serif;
    resize: vertical;
}

textarea::placeholder {
    color: #aaa;
}

.submit-feedback {
    background-color: transparent;
    color: white;
    padding: 12px 30px;
    border: 2px solid #00bcd4;
    border-radius: 5px;
    cursor: pointer;
    font-family: 'Bodoni Moda', serif;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.submit-feedback:hover {
    background-color: #00bcd4;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .feedback-heading h2 {
        font-size: 2rem;
    }
    
    .feedback-container {
        padding: 1rem;
    }
    
    .rating label {
        font-size: 24px;
    }

    .rating-wrapper {
        flex-direction: column;
        gap: 10px;
    }
    
    .rate-text {
        font-size: 1.2rem;
    }
}

.features-section {
    padding: 4rem 2rem;
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r12.jpg') no-repeat center center;
    background-size: cover;
    background-attachment: fixed;
}

.features-heading {
    text-align: center;
    margin-bottom: 3rem;
}

.features-heading h2 {
    font-family: 'Bodoni Moda', serif;
    font-size: 2.8rem;
    color: white;
    position: relative;
    display: inline-block;
}

.features-heading h2::after {
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

.features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.feature-card {
    background: rgba(0, 0, 0, 0.7);
    border: 2px solid #00bcd4;
    border-radius: 10px;
    padding: 2rem;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 20px rgba(0, 188, 212, 0.3);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #00bcd4, #1e88e5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}

.feature-icon i {
    font-size: 2rem;
    color: white;
}

.feature-card h3 {
    color: white;
    font-family: 'Bodoni Moda', serif;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin-bottom: 2rem;
}

.feature-list li {
    color: #fff;
    font-family: 'Bodoni Moda', serif;
    margin-bottom: 0.8rem;
    font-size: 1.1rem;
    opacity: 0.9;
}

.feature-btn {
    display: inline-block;
    padding: 0.8rem 1.5rem;
    background: transparent;
    border: 2px solid #00bcd4;
    color: white;
    text-decoration: none;
    font-family: 'Bodoni Moda', serif;
    font-size: 1rem;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.feature-btn:hover {
    background: #00bcd4;
    color: white;
    transform: translateY(-2px);
}

@media (max-width: 992px) {
    .features-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .features-grid {
        grid-template-columns: 1fr;
    }
}

.activity-selection {
    display: flex;
    gap: 20px;
    margin-bottom: 2rem;
    justify-content: center;
}

.select-wrapper {
    position: relative;
    width: 250px;
}

.select-wrapper select {
    width: 100%;
    padding: 12px;
    border: 2px solid #00bcd4;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    border-radius: 5px;
    font-family: 'Bodoni Moda', serif;
    font-size: 1rem;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.select-wrapper::after {
    content: '\f107';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #00bcd4;
    pointer-events: none;
}

.select-wrapper select:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.select-wrapper select option {
    background: rgba(0, 0, 0, 0.9);
    color: white;
}

@media (max-width: 768px) {
    .activity-selection {
        flex-direction: column;
        align-items: center;
    }
    
    .select-wrapper {
        width: 100%;
        max-width: 300px;
    }
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
        <!-- Replace the header div containing buttons with this code -->
<div style="margin-right: 20px; display: flex; gap: 15px;">
    <?php if ($membership_id == 2 || $membership_id == 3): ?>
        <button class="log" id="updateButton">upgrade</button>
    <?php endif; ?>
    <div style="position: relative;">
        <button class="log" id="profileButton"><?php echo htmlspecialchars($user_name); ?> <i class="fas fa-caret-down"></i></button>
        <div class="dropdown">
            <a href="user_profile.php">PROFILE</a>
            <a href="user_bookings.php">BOOKINGS</a>
            <a href="user_calendar.php">CALENDAR</a>
            <a href="user_payment_history.php">PAYMENT HISTORY</a>
            <a href="logout.php">LOGOUT</a>
        </div>
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
        <div class="quick-checks-container">
            <div class="quick-check-box">
                <div class="quick-check-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="quick-check-title">MEMBERSHIP STATUS</h3>
                <div class="quick-check-divider"></div>
                <div class="quick-check-content"><?php echo htmlspecialchars($membership_type); ?></div>
                <?php if ($membership_id > 1): // Only show dates for paid memberships ?>
                    <!--<div class="quick-check-divider"></div> -->
                    <div class="quick-check-content" style="font-size: 1rem;">
                        <div style="margin-bottom: 5px;">Registered: <?php echo htmlspecialchars($registration_date); ?></div>
                        <div>Expires: <?php echo htmlspecialchars($expiration_date); ?></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="quick-check-box">
                <div class="quick-check-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3 class="quick-check-title">UPCOMING BOOKING</h3>
                <div class="quick-check-divider"></div>
                <div class="quick-check-content"><?php echo htmlspecialchars($upcoming_booking); ?></div>
            </div>
            <div class="quick-check-box">
                <div class="quick-check-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="quick-check-title">UPCOMING EVENTS</h3>
                <div class="quick-check-divider"></div>
                <div class="quick-check-content"><?php echo htmlspecialchars($upcoming_event); ?></div>
            </div>
        </div>
    </section>
    <!-- Update the HTML structure to combine the sections -->
    <section class="features-section">
        <div class="features-heading">
            <h2>EXPLORE ARENAX</h2>
        </div>
        <div class="features-grid">
            <!-- Popular Activities Card -->
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-running"></i>
                </div>
                <h3>Popular Activities</h3>
                <ul class="feature-list">
                    <li>Indoor Sports</li>
                    <li>Outdoor Adventures</li>
                    <li>Fitness Programs</li>
                    <li>Special Events</li>
                </ul>
                <a href="user_indoor.php" class="feature-btn">Explore Activities</a>
            </div>

            <!-- Quick Booking Card -->
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Quick Booking</h3>
                <ul class="feature-list">
                    <li>Easy Slot Selection</li>
                    <li>Instant Confirmation</li>
                    <li>Flexible Scheduling</li>
                    <li>Bill Generation</li>
                </ul>
                <a href="user_bookings.php" class="feature-btn">Book Now</a>
            </div>

            <!-- Community Card -->
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Check Out Events</h3>
                <ul class="feature-list">
                    <li>Group Activities</li>
                    <li>Social Events</li>
                    <li>Tournaments</li>
                    <li>Fitness Challenges</li>
                </ul>
                <a href="user_events.php" class="feature-btn">Join Events</a>
            </div>
        </div>
    </section>
    <section class="feedback-section">
        <div class="feedback-heading">
            <h2>YOUR FEEDBACK MATTERS</h2>
        </div>
        <div class="feedback-container">
            <form id="feedbackForm" method="POST" action="submit_feedback.php">
                <div class="activity-selection">
                    <div class="select-wrapper">
                        <select name="activity_type" id="activityType" required>
                            <option value="" disabled selected>Select Activity Type</option>
                            <option value="Indoor">Indoor Activities</option>
                            <option value="Outdoor">Outdoor Activities</option>
                            <option value="Fitness">Fitness Activities</option>
                            <option value="Event">Events</option>
                        </select>
                    </div>
                    <div class="select-wrapper">
                        <select name="activity_name" id="activityName" required disabled>
                            <option value="" disabled selected>Select Specific Activity</option>
                        </select>
                    </div>
                </div>
                <div class="rating-container">
                    <div class="rating-wrapper">
                        <span class="rate-text">Rate your experience:</span>
                        <div class="rating">
                            <input type="radio" id="star5" name="rating" value="5" required>
                            <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4">
                            <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3">
                            <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2">
                            <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1">
                            <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                </div>
                <textarea name="feedback_content" placeholder="Share your experience with us..." required></textarea>
                <button type="submit" class="submit-feedback">Submit Feedback</button>
            </form>
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
                
                <!-- Add membership dates information -->
                <div style="margin-bottom: 20px; color: white; text-align: center;">
                    <p style="margin-bottom: 10px;">Current Membership: <span style="color: #00bcd4;"><?php echo htmlspecialchars($membership_type); ?></span></p>
                    <p style="margin-bottom: 10px;">Registration Date: <span style="color: #00bcd4;"><?php echo htmlspecialchars($registration_date); ?></span></p>
                    <p>Expiration Date: <span style="color: #00bcd4;"><?php echo htmlspecialchars($expiration_date); ?></span></p>
                </div>
                
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
    document.addEventListener('DOMContentLoaded', function() {
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

        // Profile dropdown and modal functionality
        const profileButton = document.getElementById('profileButton');
        const dropdown = document.querySelector('.dropdown');
        const updateButton = document.getElementById('updateButton');
        const modal = document.getElementById('membershipModal');
        const closeModal = document.getElementById('closeModal');
        
        // Ensure dropdown is hidden initially
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Toggle dropdown when profile button is clicked
        if (profileButton) {
            profileButton.addEventListener('click', function(event) {
                event.stopPropagation(); // Prevent event from bubbling up
                dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
            });
        }
        
        // Close dropdown when clicking elsewhere
        document.addEventListener('click', function(event) {
            if (dropdown && !dropdown.contains(event.target) && profileButton && !profileButton.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Prevent dropdown from closing when clicking inside it
        if (dropdown) {
            dropdown.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        }
        
        // Handle update button click to show modal
        if (updateButton && modal) {
            updateButton.addEventListener('click', function() {
                modal.classList.add('show');
                modal.style.display = 'block';
            });
        }
        
        // Close modal when X is clicked
        if (closeModal && modal) {
            closeModal.addEventListener('click', function() {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 500);
            });
        }
        
        // Close modal when clicking outside
        if (modal) {
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.remove('show');
                    setTimeout(() => {
                        modal.style.display = 'none';
                    }, 500);
                }
            });
        }
        

// Show membership modal after delay if applicable
if (modal && <?php echo $auto_show_modal ? 'true' : 'false'; ?>) {
    setTimeout(() => {
        modal.classList.add('show');
        modal.style.display = 'block';
    }, 2000);
}
        
        // Add this to your existing JavaScript
        const membershipBoxes = document.querySelectorAll('.membership');
        membershipBoxes.forEach(box => {
            observer.observe(box); // Observe each membership box for visibility
        });

        function redirectToPayment(membershipId) {
            // Redirect to the payment page with the selected membership ID
            window.location.href = 'payment.php?membership_id=' + membershipId; // Redirect to payment page
        }

        // Check for the query parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('showMembershipModal') === 'true') {
            // Show membership modal after a short delay to ensure DOM is ready
            setTimeout(() => {
                const modal = document.getElementById('membershipModal');
                if (modal) {
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                    console.log('Modal displayed');
                } else {
                    console.error('Modal element not found');
                }
                // Remove the query parameter
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 500);
        }
    });
    
    // Debug check - log if script is running
    console.log('Modal script loaded');
    console.log('URL parameters:', window.location.search);

    // Replace the hardcoded activityOptions with the database values
    const activityOptions = <?php echo $activitiesJson; ?>;

    // Debug the content of activityOptions
    console.log('Available activity options:', activityOptions);

    document.getElementById('activityType').addEventListener('change', function() {
        const activityNameSelect = document.getElementById('activityName');
        activityNameSelect.disabled = false;
        
        // Clear existing options
        activityNameSelect.innerHTML = '<option value="" disabled selected>Select Specific Activity</option>';
        
        // Log selected activity type and available options
        console.log('Selected activity type:', this.value);
        console.log('Available activities for this type:', activityOptions[this.value]);
        
        // Add new options based on selected activity type
        const activities = activityOptions[this.value] || [];
        
        if (activities.length === 0) {
            console.log('No activities found for type:', this.value);
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No activities available';
            option.disabled = true;
            activityNameSelect.appendChild(option);
        } else {
            console.log(`Found ${activities.length} activities for type:`, this.value);
            activities.forEach(activity => {
                if (activity) { // Only add non-null activities
                    const option = document.createElement('option');
                    option.value = activity;
                    option.textContent = activity;
                    activityNameSelect.appendChild(option);
                    console.log('Added option:', activity);
                }
            });
        }
    });

    // Add animation when form is submitted
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Animate the submit button
        const submitButton = this.querySelector('.submit-feedback');
        submitButton.style.transform = 'scale(0.95)';
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        // Submit the form after a brief delay
        setTimeout(() => {
            this.submit();
        }, 1000);
    });
</script>
</body>
</html>
