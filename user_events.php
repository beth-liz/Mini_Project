<?php
session_start();
require_once 'db_connect.php'; // Update the path as necessary

// Redirect to login if user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Fetch user's membership and name
$user_email = $_SESSION['email']; // Define $user_email from session
$sql = "SELECT membership_id, name FROM users WHERE email = :email";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':email', $user_email);
$stmt->execute();
$user_name = "Profile"; // Default value

if ($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $membership_id = $row['membership_id'];
    $user_name = $row['name'];
}

// Fetch events
$sql = "SELECT 
            e.event_id,
            e.event_title,
            e.event_description,
            e.event_date,
            e.event_time,
            e.event_location,
            e.event_price,
            e.event_image,
            a.activity_type
        FROM events e
        JOIN activity a ON e.activity_id = a.activity_id
        ORDER BY e.event_date DESC, e.event_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events</title>
    
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
            height: 60vh;
            background: url('img/event1.jpg') no-repeat center center;
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
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/event2.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            margin-top: -2rem; /* Remove gap between heading and grid */
        }

        .image-grid img {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 10px;
            transition: transform 0.5s ease;
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
    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/event2.jpg') no-repeat center center;
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
left: 0; /* Change from right: 0 to left: 0 to align with the button */
transform: translateX(0); /* Remove any translation */
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
left: 50%; /* Center the arrow */
transform: translateX(-50%); /* Center the arrow precisely */
border-left: 8px solid transparent;
border-right: 8px solid transparent;
border-bottom: 8px solid rgba(0, 0, 0, 0.9);
}

/* Events Container Section */
.events-container {
        padding: 3rem 5%;
        background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url('img/event2.jpg') no-repeat center center;
        background-size: cover;
        background-attachment: fixed;
        display: flex;
        flex-direction: column;
        gap: 2.5rem;
    }

    .event-card {
        display: flex;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        max-height: 350px;
    }

    .event-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
    }

    .event-image {
        flex: 0 0 40%;
        overflow: hidden;
    }

    .event-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .event-card:hover .event-image img {
        transform: scale(1.05);
    }

    .event-details {
        flex: 0 0 60%;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .event-details h3 {
        font-family: 'Bodoni Moda', serif;
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 1rem;
        position: relative;
        padding-bottom: 0.5rem;
    }

    .event-details h3::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: #00bcd4;
    }

    .event-info {
        margin-bottom: 1.5rem;
    }

    .event-info p {
        margin-bottom: 0.7rem;
        font-family: 'Aboreto', cursive;
        color: #555;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
    }

    .event-info p i {
        margin-right: 10px;
        color: #00bcd4;
        width: 20px;
    }

    .event-description {
        line-height: 1.6;
        margin-top: 1rem;
        color: #666;
    }

    .event-actions {
        display: flex;
        gap: 1rem;
        margin-top: auto;
    }

    .event-details-btn, .book-now-btn {
        padding: 10px 25px;
        border: none;
        border-radius: 4px;
        font-family: 'Bodoni Moda', serif;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .event-details-btn {
        background-color: transparent;
        border: 1px solid #00bcd4;
        color: #00bcd4;
    }

    .event-details-btn:hover {
        background-color: #00bcd4;
        color: white;
    }

    .book-now-btn {
        background-color: #00bcd4;
        color: white;
    }

    .book-now-btn:hover {
        background-color: #008fa1;
    }

    @media screen and (max-width: 1024px) {
        .event-card {
            max-height: none;
        }
    }

    @media screen and (max-width: 768px) {
        .event-card {
            flex-direction: column;
        }

        .event-image, .event-details {
            flex: 1 1 auto;
        }

        .event-image {
            height: 250px;
        }
    }

    /* Add animation for elements */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .event-card {
        animation: fadeIn 0.6s ease forwards;
        opacity: 0;
    }

    .event-card:nth-child(2) {
        animation-delay: 0.2s;
    }

    .event-card:nth-child(3) {
        animation-delay: 0.4s;
    }

    .event-card:nth-child(4) {
        animation-delay: 0.6s;
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1200;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.8);
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        width: 85%;
        max-width: 900px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        position: relative;
        animation: slideIn 0.4s ease;
    }

    @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-flex {
        display: flex;
        min-height: 450px;
    }

    .modal-image-container {
        flex: 0 0 40%;
        position: relative;
        background-color: #000;
        overflow: hidden;
    }

    #modalEventImage {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
        opacity: 0.9;
    }

    .modal-image-container:hover #modalEventImage {
        transform: scale(1.05);
    }

    .event-badge {
        position: absolute;
        top: 20px;
        left: 20px;
        background-color: #00bcd4;
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-family: 'Bodoni Moda', serif;
        font-size: 0.9rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .modal-details {
        flex: 0 0 60%;
        padding: 40px;
        display: flex;
        flex-direction: column;
    }

    .modal-details h2 {
        font-family: 'Bodoni Moda', serif;
        font-size: 2.2rem;
        color: #333;
        margin-bottom: 20px;
        position: relative;
        padding-bottom: 15px;
    }

    .modal-details h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, #00bcd4, #4db6ac);
    }

    .modal-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .info-item {
        display: flex;
        align-items: center;
        font-family: 'Aboreto', cursive;
        color: #555;
    }

    .info-item i {
        margin-right: 10px;
        color: #00bcd4;
        font-size: 1.2rem;
    }

    .modal-description {
        margin: 25px 0;
    }

    .modal-description h3 {
        font-family: 'Bodoni Moda', serif;
        font-size: 1.3rem;
        color: #333;
        margin-bottom: 15px;
    }

    .modal-description p {
        font-family: 'Aboreto', cursive;
        line-height: 1.6;
        color: #666;
    }

    .modal-actions {
        display: flex;
        gap: 15px;
        margin-top: auto;
    }

    .cancel-btn, .proceed-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 5px;
        font-family: 'Bodoni Moda', serif;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .cancel-btn {
        background-color: transparent;
        border: 1px solid #ccc;
        color: #555;
    }

    .cancel-btn:hover {
        background-color: #f5f5f5;
    }

    .proceed-btn {
        background: linear-gradient(45deg, #00bcd4, #4db6ac);
        color: white;
        box-shadow: 0 4px 10px rgba(0, 188, 212, 0.3);
    }

    .proceed-btn:hover {
        background: linear-gradient(45deg, #00acc1, #009688);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 188, 212, 0.4);
    }

    .close {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 24px;
        color: #555;
        cursor: pointer;
        z-index: 10;
        transition: all 0.3s ease;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
    }

    .close:hover {
        background-color: #f5f5f5;
        transform: rotate(90deg);
        color: #00bcd4;
    }

    @media screen and (max-width: 768px) {
        .modal-flex {
            flex-direction: column;
        }

        .modal-image-container {
            height: 250px;
        }

        .modal-details {
            padding: 25px;
        }

        .modal-info {
            grid-template-columns: 1fr;
            gap: 15px;
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

    <!-- First Section: Hero Image for Indoor Games -->
    <section class="hero-section">
        <div class="hero-text">
            events
        </div>
        <div class="scroll-indicator" onclick="scrollToAbout()"></div>
    </section>
    
    <div class="activities-heading">
        <h2>OUR EVENTS</h2>
    </div>

    <section class="events-container">
        <?php if (count($events) > 0): ?>
            <?php foreach ($events as $event): ?>
                <div class="event-card" data-event-id="<?php echo htmlspecialchars($event['event_id']); ?>">
                    <div class="event-image">
                        <img src="<?php echo htmlspecialchars($event['event_image']); ?>" alt="<?php echo htmlspecialchars($event['event_title']); ?>" style="width: 100%; height: auto;">
                    </div>
                    <div class="event-details">
                        <h3><?php echo htmlspecialchars($event['event_title']); ?></h3>
                        <div class="event-info">
                            <p class="event-date"><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($event['event_date']); ?></p>
                            <p class="event-time"><i class="far fa-clock"></i> <?php echo htmlspecialchars($event['event_time']); ?></p>
                            <p class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['event_location']); ?></p>
                            <p class="event-price"><i class="fas fa-tag"></i> $<?php echo htmlspecialchars($event['event_price']); ?></p>
                            <p class="event-description" style="display:none;"><?php echo htmlspecialchars($event['event_description']); ?></p>
                        </div>
                        <div class="event-actions">
                            <button class="book-now-btn">Book Now</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No events available at the moment.</p>
        <?php endif; ?>
    </section>

    <!-- Modal for Event Details -->
    <div id="eventModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div class="modal-flex">
            <div class="modal-image-container">
                <img id="modalEventImage" src="" alt="Event Image">
                <div class="event-badge" id="modalEventType"></div>
            </div>
            <div class="modal-details">
                <h2 id="modalEventTitle"></h2>
                <div class="modal-info">
                    <div class="info-item">
                        <i class="far fa-calendar-alt"></i>
                        <span id="modalEventDate"></span>
                    </div>
                    <div class="info-item">
                        <i class="far fa-clock"></i>
                        <span id="modalEventTime"></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="modalEventLocation"></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-tag"></i>
                        <span id="modalEventPrice"></span>
                    </div>
                </div>
                <div class="modal-description">
                    <h3>Event Description</h3>
                    <p id="modalEventDescription"></p>
                </div>
                <div class="modal-actions">
                    <button id="cancelBooking" class="cancel-btn">Cancel</button>
                    <button id="proceedToPayment" class="proceed-btn">Proceed to Payment</button>
                </div>
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

        // Event details modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const bookNowBtns = document.querySelectorAll('.book-now-btn');
    const modal = document.getElementById("eventModal");
    const closeModal = document.querySelector(".close");
    const cancelBtn = document.getElementById("cancelBooking");
    const proceedToPayment = document.getElementById("proceedToPayment");
    
    // Display the modal when "Book Now" is clicked
    bookNowBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.event-card');
            const eventId = card.getAttribute('data-event-id');
            const eventTitle = card.querySelector('h3').textContent;
            const eventDescription = card.querySelector('.event-description').textContent;
            const eventDate = card.querySelector('.event-date').textContent;
            const eventTime = card.querySelector('.event-time').textContent;
            const eventLocation = card.querySelector('.event-location').textContent;
            const eventPrice = card.querySelector('.event-price').textContent;
            const eventImage = card.querySelector('img').src;
            const eventType = card.querySelector('.event-type') ? card.querySelector('.event-type').textContent : 'Event';
            
            // Set modal content
            document.getElementById("modalEventTitle").textContent = eventTitle;
            document.getElementById("modalEventDescription").textContent = eventDescription;
            document.getElementById("modalEventDate").textContent = eventDate.replace('calendar-alt', '');
            document.getElementById("modalEventTime").textContent = eventTime.replace('clock', '');
            document.getElementById("modalEventLocation").textContent = eventLocation.replace('map-marker-alt', '');
            document.getElementById("modalEventPrice").textContent = eventPrice.replace('tag', '');
            document.getElementById("modalEventImage").src = eventImage;
            document.getElementById("modalEventType").textContent = eventType;
            
            // Store event ID in the modal for later use
            modal.setAttribute('data-event-id', eventId);
            
            // Show the modal with animation
            modal.style.display = "block";
            document.body.style.overflow = "hidden"; // Prevent scrolling
        });
    });
    
    // Close modal functions
    function closeModalFunc() {
        modal.style.display = "none";
        document.body.style.overflow = "auto"; // Allow scrolling again
    }
    
    closeModal.onclick = closeModalFunc;
    cancelBtn.onclick = closeModalFunc;
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModalFunc();
        }
    }
    
    // Proceed to payment button functionality
    proceedToPayment.addEventListener('click', function() {
        const eventId = modal.getAttribute('data-event-id');
        
        // Create a form to submit the event_id
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'events_payment.php';
        form.style.display = 'none'; // Hide the form
        
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'event_id';
        hiddenField.value = eventId;
        
        form.appendChild(hiddenField);
        document.body.appendChild(form);
        form.submit();
    });
});
    </script>
</body>
</html>
