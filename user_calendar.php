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
$sql = "SELECT u.membership_id, u.name, m.membership_type 
        FROM users u 
        JOIN memberships m ON u.membership_id = m.membership_id 
        WHERE u.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bindParam(1, $user_email);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$membership_type = isset($userData['membership_type']) ? strtolower($userData['membership_type']) : "normal";
$isPremiumMembership = ($membership_type === 'premium');

// Check if user has normal membership
$isNormalMembership = ($membership_type === 'normal');

// Modify the existing SQL query to also fetch the user's name
$sql = "SELECT membership_id, name, user_id FROM users WHERE email = '$user_email'";
$stmt = $conn->query($sql); // Use query() to execute the SQL statement
$user_id = null;

if ($stmt && $stmt->rowCount() > 0) { // Use rowCount() to check for rows
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $membership_id = $row['membership_id'];
    $user_id = $row['user_id'];
}

// Create a new table for calendar events if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS user_calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    time TIME DEFAULT NULL,
    description TEXT,
    event_type ENUM('custom', 'booking', 'event') NOT NULL DEFAULT 'custom',
    related_id INT DEFAULT NULL,
    reminder_enabled BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

$conn->exec($createTableSQL);

// Create a table for reminders if it doesn't exist
$createRemindersTableSQL = "CREATE TABLE IF NOT EXISTS user_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    reminder_time DATETIME NOT NULL,
    is_sent BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (event_id) REFERENCES user_calendar_events(id) ON DELETE CASCADE
)";

$conn->exec($createRemindersTableSQL);

// Fetch user's bookings
$bookingsSQL = "SELECT b.booking_id, s.sub_act_name, t.slot_date, t.slot_start_time, t.slot_end_time 
                FROM booking b
                JOIN timeslots t ON b.slot_id = t.slot_id
                JOIN sub_activity sa ON b.sub_activity_id = sa.sub_activity_id
                JOIN sub_activity_name s ON sa.sub_act_id = s.sub_act_id
                WHERE b.user_id = :user_id";
                
$bookingsStmt = $conn->prepare($bookingsSQL);
$bookingsStmt->execute(['user_id' => $user_id]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's event registrations
$eventsSQL = "SELECT er.event_reg_id, e.event_title, e.event_date, e.event_time, e.event_description 
              FROM event_registration er
              JOIN events e ON er.event_id = e.event_id
              WHERE er.user_id = :user_id";
              
$eventsStmt = $conn->prepare($eventsSQL);
$eventsStmt->execute(['user_id' => $user_id]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Sync bookings and events to the calendar_events table if not already there
foreach ($bookings as $booking) {
    $checkSQL = "SELECT id FROM user_calendar_events 
                 WHERE user_id = :user_id AND event_type = 'booking' 
                 AND related_id = :booking_id";
    $checkStmt = $conn->prepare($checkSQL);
    $checkStmt->execute([
        'user_id' => $user_id,
        'booking_id' => $booking['booking_id']
    ]);
    
    if ($checkStmt->rowCount() == 0) {
        // Create a new calendar event for this booking
        $insertSQL = "INSERT INTO user_calendar_events 
                     (user_id, title, date, time, description, event_type, related_id) 
                     VALUES (:user_id, :title, :date, :time, :description, 'booking', :related_id)";
        $insertStmt = $conn->prepare($insertSQL);
        $insertStmt->execute([
            'user_id' => $user_id,
            'title' => $booking['sub_act_name'],
            'date' => $booking['slot_date'],
            'time' => $booking['slot_start_time'],
            'description' => "Booking for " . $booking['sub_act_name'] . " from " . 
                            $booking['slot_start_time'] . " to " . $booking['slot_end_time'],
            'related_id' => $booking['booking_id']
        ]);
    }
}

foreach ($events as $event) {
    $checkSQL = "SELECT id FROM user_calendar_events 
                 WHERE user_id = :user_id AND event_type = 'event' 
                 AND related_id = :event_reg_id";
    $checkStmt = $conn->prepare($checkSQL);
    $checkStmt->execute([
        'user_id' => $user_id,
        'event_reg_id' => $event['event_reg_id']
    ]);
    
    if ($checkStmt->rowCount() == 0) {
        // Create a new calendar event for this event registration
        $insertSQL = "INSERT INTO user_calendar_events 
                     (user_id, title, date, time, description, event_type, related_id) 
                     VALUES (:user_id, :title, :date, :time, :description, 'event', :related_id)";
        $insertStmt = $conn->prepare($insertSQL);
        $insertStmt->execute([
            'user_id' => $user_id,
            'title' => $event['event_title'],
            'date' => $event['event_date'],
            'time' => $event['event_time'],
            'description' => $event['event_description'],
            'related_id' => $event['event_reg_id']
        ]);
    }
}

// Process notification reminders for upcoming events/bookings
$today = date('Y-m-d');
$twoDaysLater = date('Y-m-d', strtotime('+2 days'));

// Check for events in the next 2 days that need reminders
$upcomingSQL = "SELECT uce.id, uce.title, uce.date, uce.time, uce.event_type 
                FROM user_calendar_events uce
                LEFT JOIN user_reminders ur ON uce.id = ur.event_id AND ur.is_sent = 0
                WHERE uce.user_id = :user_id 
                AND uce.date BETWEEN :today AND :two_days_later
                AND (ur.id IS NULL OR ur.is_sent = 0)
                AND uce.reminder_enabled = 1";
                
$upcomingStmt = $conn->prepare($upcomingSQL);
$upcomingStmt->execute([
    'user_id' => $user_id,
    'today' => $today,
    'two_days_later' => $twoDaysLater
]);

$upcomingEvents = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// Generate notifications for upcoming events
foreach ($upcomingEvents as $upcoming) {
    $daysUntil = (strtotime($upcoming['date']) - strtotime($today)) / (60 * 60 * 24);
    $daysText = $daysUntil <= 1 ? "tomorrow" : "in " . ceil($daysUntil) . " days";
    
    $eventType = $upcoming['event_type'] == 'booking' ? 'activity' : 'event';
    
    // Insert notification
    $notifySQL = "INSERT INTO notification 
                 (user_id, title, message, notification_viewed, created_at_date, created_at_time) 
                 VALUES (:user_id, :title, :message, 0, CURDATE(), CURTIME())";
    $notifyStmt = $conn->prepare($notifySQL);
    $notifyStmt->execute([
        'user_id' => $user_id,
        'title' => "Upcoming " . ucfirst($eventType) . " Reminder",
        'message' => "Reminder: You have " . $upcoming['title'] . " scheduled " . $daysText . 
                     " (" . $upcoming['date'] . ") at " . ($upcoming['time'] ? $upcoming['time'] : "All day") . "."
    ]);
    
    // Mark reminder as sent
    $reminderSQL = "INSERT INTO user_reminders 
                   (user_id, event_id, reminder_time, is_sent) 
                   VALUES (:user_id, :event_id, NOW(), 1)";
    $reminderStmt = $conn->prepare($reminderSQL);
    $reminderStmt->execute([
        'user_id' => $user_id,
        'event_id' => $upcoming['id']
    ]);
}

// Get all user's calendar events for JavaScript
$userEventsSQL = "SELECT id, title, date, time, description, event_type, reminder_enabled 
                  FROM user_calendar_events 
                  WHERE user_id = :user_id";
$userEventsStmt = $conn->prepare($userEventsSQL);
$userEventsStmt->execute(['user_id' => $user_id]);
$userEvents = $userEventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the calendar events data for JavaScript
$calendarEventsJson = json_encode($userEvents);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Calendar</title>
    
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
            background: url('img/out1.png') no-repeat center center;
            background-size: cover;
            overflow-x: hidden;
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
            background-color:rgba(0, 0, 0, 0);
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
        .container {
            max-width: 1000px;
            margin: 130px auto;
            background-color: rgba(9, 38, 58, 0.57);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(139, 213, 250, 0.2);
            padding: 25px;
            font-family: 'Bodoni Moda', serif;
        }
        
        h1 {
            text-align: center;
            color:rgb(87, 222, 246); /* Deep purple */
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgb(102, 224, 255); /* Light purple border */
        }
        
        .calendar-nav {
            display: flex;
            gap: 12px;
        }
        
        button {
            background-color:rgb(112, 190, 219); /* Medium purple */
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(112, 201, 219, 0.2);
        }
        
        button:hover {
            background-color:rgb(91, 176, 213); /* Darker purple on hover */
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        #addEventBtn {
            background-color:rgb(133, 231, 255); /* Soft pink */
            box-shadow: 0 4px 8px rgba(255, 133, 162, 0.3);
        }
        
        #addEventBtn:hover {
            background-color:rgb(48, 210, 228); /* Deeper pink on hover */
        }
        
        .month-year {
            font-size: 1.8rem;
            font-weight: bold;
            color:rgb(96, 212, 235); /* Deep purple */
            text-shadow: 1px 1px 2px rgba(50, 153, 160, 0.1);
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .day-header {
            text-align: center;
            font-weight: bold;
            padding: 12px;
            background-color:rgb(224, 249, 255); /* Very light purple */
            border-radius: 8px;
            color:rgb(47, 187, 202); /* Deep purple */
        }
        
        .day {
            min-height: 110px;
            border: 1px solid rgb(164, 240, 255); /* Light purple border */
            padding: 10px;
            background-color: white;
            position: relative;
            cursor: pointer;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: visible; /* Changed from hidden to visible */
        }
        
        .day:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            z-index: 10; /* Ensure the expanded day appears above others */
        }
        
        .day-number {
            font-weight: bold;
            margin-bottom: 8px;
            color:rgb(75, 192, 212); /* Deep purple */
            font-size: 1.1rem;
            text-align: right;
        }
        
        .day.inactive {
            background-color:rgb(225, 247, 251); /* Even lighter purple for inactive days */
            color:rgb(182, 224, 226); /* Medium-light purple */
            box-shadow: none;
        }
        
        .day.inactive:hover {
            transform: none;
            box-shadow: none;
        }
        
        .event {
            background: linear-gradient(to right,rgb(133, 233, 255),rgb(167, 230, 255)); /* Pink gradient */
            color: white;
            padding: 6px 10px;
            border-radius: 20px;
            margin-bottom: 5px;
            font-size: 0.85rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(133, 249, 255, 0.25);
            transition: all 0.3s ease;
            transform-origin: top;
        }
        
        .event:hover {
            transform: translateX(3px);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 50%; /* Center horizontally */
            top: 60%; /* Adjusted to move the modal down */
            transform: translate(-50%, -50%); /* Adjust position to truly center */
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(13, 12, 12, 0.53); /* Purple tinted overlay */
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background: rgb(200, 233, 236);
            border-radius: 15px;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            font-family: 'Bodoni Moda', serif; /* Ensure font is set */
            position: absolute; /* Change to absolute positioning */
            left: 50%; /* Center horizontally */
            top: 50%; /* Center vertically */
            transform: translate(-50%, -50%); /* Adjust position to truly center */
        }
        
        .modal-header {
            background: linear-gradient(135deg, #00bcd4, #007c91);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: white;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .event-details {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
        }
        
        .detail-row i {
            color: #00bcd4;
            font-size: 1.2rem;
            width: 24px;
        }
        
        .detail-label {
            font-weight: 600;
            color: black;
            min-width: 100px;
        }
        
        .detail-value {
            color: black;
            flex: 1;
        }
        
        .reminder-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .toggle-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: rgba(0, 188, 212, 0.05);
            border-radius: 8px;
        }
        
        .toggle-label {
            font-weight: 600;
            color: #333;
        }
        
        .toggle-switch-wrapper {
            position: relative;
            width: 60px;
            height: 34px;
            user-select: none;
        }
        
        .toggle-switch {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: background-color 0.3s ease;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: transform 0.3s ease;
            border-radius: 50%;
        }
        
        .toggle-switch:checked + .toggle-slider {
            background-color: #00bcd4;
        }
        
        .toggle-switch:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .toggle-switch:disabled + .toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .event {
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 50px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .booking-event {
            background:rgb(136, 226, 158);
            color: white;
        }
        
        .special-event {
            background:rgb(209, 174, 231);
            color: white;
        }

        /* Allow header to be clickable */
        .header {
            z-index: 10000; /* Ensure header is above modal */
        }

        /* Only prevent scrolling for content below header */
        body {
            overflow: auto; /* Allow scrolling */
        }

        .header {
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
        }

        .container {
            margin-top: 150px; /* Adjust based on your header height */
            overflow: auto; /* Allow scrolling */
        }

        /* Add CSS styles for the close icon */
        .modal-close-icon {
            font-size: 44px; /* Increase font size */
            color:rgb(255, 255, 255); /* Color of the icon */
            cursor: pointer; /* Pointer cursor */
            transition: color 0.3s; /* Transition effect */
        }

        .modal-close-icon:hover {
            color:rgb(26, 228, 255); /* Darker color on hover */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }

        .month-display {
            text-align: center; /* Center the text horizontally */
            margin: 20px 0; /* Optional: Add some vertical spacing */
        }

        .show-more {
            font-size: 0.8rem; /* Adjust size as needed */
            margin-top: 5px;
            transition: opacity 0.3s ease;
        }

        .show-more:hover {
            color: #ff4081; /* Change color on hover */
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
            <button class="log"><?php echo htmlspecialchars($userData['name']); ?> <i class="fas fa-caret-down"></i></button>
            <div class="dropdown">
                <a href="user_profile.php">PROFILE</a>
                <a href="user_bookings.php">BOOKINGS</a>
                <a href="user_calendar.php">CALENDAR</a>
                <a href="user_payment_history.php">PAYMENT HISTORY</a>
                <a href="logout.php">LOGOUT</a>
            </div>
        </div>
    </header>
    <div class="container">
        <h1>My Calendar</h1>
        
        <?php if ($isPremiumMembership): ?>
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button id="prevMonth">← Previous</button>
                    <button id="nextMonth">Next →</button>
                </div>
                <div class="month-year" id="monthYear"></div>
                <?php
                // <button id="addEventBtn">+ Add Event</button>
                ?>
            </div>
            
            <div class="calendar" id="calendar">
                <!-- Calendar will be generated with JavaScript -->
            </div>
        <?php else: ?>
            <!-- Upgrade Modal for Normal and Standard Membership Users -->
            <div id="upgradeModal" class="modal" style="display: block;">
                <div class="modal-content" style="font-family: 'Bodoni Moda', serif; padding: 20px;">
                    <h2 style="text-align: center; margin-bottom: 20px; color: #00bcd4;">MEMBERSHIP REQUIRED</h2>
                    <hr style="border: 1px solid white; margin: 10px 0;"/>
                    <div style="text-align: center; margin-bottom: 30px;">
                        <i class="fas fa-lock" style="font-size: 60px; color: #00bcd4; margin-bottom: 20px;"></i>
                        <p style="font-size: 1.2rem; margin-bottom: 20px;">Access to the calendar feature requires a premium membership.</p>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;">Your current membership: <strong><?php echo strtoupper($membership_type); ?></strong>. This feature requires PREMIUM membership.</p>
                    </div>
                    <div style="text-align: center;">
                        <button style="background-color: #00bcd4; color: white; border: none; padding: 12px 30px; font-size: 1.1rem; border-radius: 5px; cursor: pointer; transition: all 0.3s ease; font-family: 'Bodoni Moda', serif; margin-top: 20px;" onclick="window.location.href='user_home.php?showMembershipModal=true'">Upgrade Membership</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <span class="modal-close-icon" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Add Event</h2>
            <form id="eventForm">
                <input type="hidden" id="eventId" value="">
                <input type="hidden" id="eventDate" value="">
                
                <div class="form-group">
                    <label for="eventTitle">Event Title:</label>
                    <input type="text" id="eventTitle" name="eventTitle" required placeholder="Enter event title...">
                </div>
                
                <div class="form-group">
                    <label for="eventTime">Time:</label>
                    <input type="time" id="eventTime" name="eventTime">
                </div>
                
                <div class="form-group">
                    <label for="eventDescription">Description:</label>
                    <textarea id="eventDescription" name="eventDescription" placeholder="Add details about your event..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" id="deleteEvent">Delete</button>
                    <button type="submit" id="saveEvent">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calendar state
            let currentDate = new Date();
            let events = <?php echo $calendarEventsJson; ?> || [];
            
            // DOM elements
            const calendar = document.getElementById('calendar');
            const monthYear = document.getElementById('monthYear');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');
            const addEventBtn = document.getElementById('addEventBtn');
            const eventModal = document.getElementById('eventModal');
            const closeModal = document.querySelector('.modal-close-icon');
            const eventForm = document.getElementById('eventForm');
            const modalTitle = document.getElementById('modalTitle');
            const eventIdInput = document.getElementById('eventId');
            const eventDateInput = document.getElementById('eventDate');
            const eventTitleInput = document.getElementById('eventTitle');
            const eventTimeInput = document.getElementById('eventTime');
            const eventDescriptionInput = document.getElementById('eventDescription');
            const saveEventBtn = document.getElementById('saveEvent');
            const deleteEventBtn = document.getElementById('deleteEvent');
            
            // Add reminder checkbox to the form
            const reminderDiv = document.createElement('div');
            reminderDiv.className = 'form-group';
            reminderDiv.innerHTML = `
                <label for="eventReminder">
                    <input type="checkbox" id="eventReminder" name="eventReminder">
                    Enable reminder for this event
                </label>
            `;
            
            // Find the right position to insert the reminder checkbox
            const formActions = eventForm.querySelector('.form-actions');
            eventForm.insertBefore(reminderDiv, formActions);
            
            // Initialize calendar
            renderCalendar();
            
            // Event listeners
            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });
            
            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });
            
            addEventBtn.addEventListener('click', () => {
                openModal();
            });
            
            closeModal.addEventListener('click', () => {
                eventModal.style.display = 'none';
            });
            
            window.addEventListener('click', (event) => {
                if (event.target === eventModal) {
                    eventModal.style.display = 'none';
                }
            });
            
            eventForm.addEventListener('submit', (e) => {
                e.preventDefault();
                saveEvent();
            });
            
            deleteEventBtn.addEventListener('click', () => {
                deleteEvent();
            });
            
            // Functions
            function renderCalendar() {
                // Clear calendar
                calendar.innerHTML = '';
                
                // Update month and year display
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                monthYear.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
                
                // Add day headers
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                dayNames.forEach(day => {
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'day-header';
                    dayHeader.textContent = day;
                    calendar.appendChild(dayHeader);
                });
                
                // Get first day of month and number of days
                const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
                const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startingDayOfWeek = firstDay.getDay();
                
                // Add blank cells for days before start of month
                for (let i = 0; i < startingDayOfWeek; i++) {
                    const blankDay = document.createElement('div');
                    blankDay.className = 'day inactive';
                    calendar.appendChild(blankDay);
                }
                
                // Get today's date for highlighting current day
                const today = new Date();
                const isCurrentMonth = today.getMonth() === currentDate.getMonth() && today.getFullYear() === currentDate.getFullYear();
                
                // Add days of the month
                for (let i = 1; i <= daysInMonth; i++) {
                    const dayCell = document.createElement('div');
                    
                    // Add "today" class if this is the current day
                    if (isCurrentMonth && i === today.getDate()) {
                        dayCell.className = 'day today';
                    } else {
                        dayCell.className = 'day';
                    }
                    
                    const dayNumber = document.createElement('div');
                    dayNumber.className = 'day-number';
                    dayNumber.textContent = i;
                    dayCell.appendChild(dayNumber);
                    
                    // Format date string for comparison with events
                    const dateStr = formatDate(new Date(currentDate.getFullYear(), currentDate.getMonth(), i));
                    dayCell.dataset.date = dateStr;
                    
                    // Add click event to open modal for this day
                    dayCell.addEventListener('click', () => {
                        openModal(null, dateStr);
                    });
                    
                    calendar.appendChild(dayCell);
                }
                
                // Add events to calendar
                displayEvents();
            }
            
            function displayEvents() {
                // Clear existing events from calendar
                document.querySelectorAll('.event').forEach(el => el.remove());
                
                // Add events to corresponding days
                events.forEach(event => {
                    const dayCell = document.querySelector(`.day[data-date="${event.date}"]`);
                    if (dayCell) {
                        const eventDiv = document.createElement('div');
                        eventDiv.className = 'event';
                        
                        // Set different class based on event type
                        if (event.event_type === 'booking') {
                            eventDiv.classList.add('booking-event');
                        } else if (event.event_type === 'event') {
                            eventDiv.classList.add('special-event');
                        }
                        
                        eventDiv.textContent = event.title;
                        eventDiv.title = event.description || event.title;
                        
                        // Add smooth transition for events
                        eventDiv.style.transition = 'all 0.3s ease';
                        eventDiv.style.opacity = '1';
                        eventDiv.style.transform = 'translateY(0)';

                        eventDiv.addEventListener('click', (e) => {
                            e.stopPropagation();
                            openModal(event);
                        });

                        dayCell.appendChild(eventDiv);
                    }
                });

                // Handle events display limit
                const dayCells = document.querySelectorAll('.day');
                dayCells.forEach(dayCell => {
                    const eventsInCell = dayCell.querySelectorAll('.event');
                    if (eventsInCell.length > 2) {
                        // Initially hide all but the first two events
                        for (let i = 2; i < eventsInCell.length; i++) {
                            eventsInCell[i].style.opacity = '0';
                            eventsInCell[i].style.transform = 'translateY(-10px)';
                            eventsInCell[i].style.display = 'none';
                        }

                        // Create a "Show more" element
                        const showMore = document.createElement('div');
                        showMore.className = 'show-more';
                        showMore.textContent = `+${eventsInCell.length - 2} more`;
                        showMore.style.color = '#00bcd4';
                        showMore.style.textAlign = 'center';
                        showMore.style.marginTop = '5px';
                        showMore.style.transition = 'opacity 0.3s ease';
                        dayCell.appendChild(showMore);

                        // Add hover effect to the entire day cell
                        dayCell.addEventListener('mouseenter', () => {
                            showMore.style.opacity = '0';
                            eventsInCell.forEach((event, index) => {
                                if (index >= 2) {
                                    event.style.display = 'block';
                                    // Delay the appearance slightly for each event
                                    setTimeout(() => {
                                        event.style.opacity = '1';
                                        event.style.transform = 'translateY(0)';
                                    }, (index - 2) * 100);
                                }
                            });
                        });

                        dayCell.addEventListener('mouseleave', () => {
                            showMore.style.opacity = '1';
                            for (let i = 2; i < eventsInCell.length; i++) {
                                eventsInCell[i].style.opacity = '0';
                                eventsInCell[i].style.transform = 'translateY(-10px)';
                                // Hide after transition completes
                                setTimeout(() => {
                                    eventsInCell[i].style.display = 'none';
                                }, 300);
                            }
                        });
                    }
                });
            }
            
            function toggleReminder(event) {
                // Ensure we're working with numbers for reminder_enabled
                const currentState = parseInt(event.reminder_enabled);
                const newReminderState = currentState === 1 ? 0 : 1;
                
                // Update through AJAX
                fetch('toggle_reminder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: event.id,
                        reminder_enabled: newReminderState
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update local event state in the events array
                        const eventIndex = events.findIndex(e => e.id === event.id);
                        if (eventIndex !== -1) {
                            events[eventIndex].reminder_enabled = newReminderState;
                        }
                        
                        // Update the current event object
                        event.reminder_enabled = newReminderState;
                        
                        // Re-render the modal content to reflect the new state
                        openModal(event);
                        
                        // Show message
                        const message = newReminderState === 1
                            ? 'Reminder set successfully!' 
                            : 'Reminder disabled.';
                        
                        // Optional: show a less intrusive notification instead of alert
                        const notification = document.createElement('div');
                        notification.style.cssText = `
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background-color: #00bcd4;
                            color: white;
                            padding: 15px 25px;
                            border-radius: 5px;
                            z-index: 1000;
                            animation: fadeIn 0.3s, fadeOut 0.3s 2.7s;
                        `;
                        notification.textContent = message;
                        document.body.appendChild(notification);
                        
                        setTimeout(() => {
                            notification.remove();
                        }, 3000);
                    } else {
                        alert('Error updating reminder: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating reminder. Please try again.');
                });
            }
            
            function openModal(event = null, dateStr = null) {
                if (event) {
                    // Get the current state of the event from the events array
                    const currentEvent = events.find(e => e.id == event.id) || event;
                    
                    const modalContent = `
                        <div class="modal-header">
                            <h2>${currentEvent.event_type === 'booking' ? 'Booking Details' : 'Event Details'}</h2>
                            <span class="modal-close-icon" onclick="closeModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            ${generateEventDetails(currentEvent)}
                        </div>
                    `;
                    
                    document.querySelector('.modal-content').innerHTML = modalContent;
                    eventModal.style.display = 'block';
                } else {
                    // Handle new event creation...
                }
            }
            
            function generateEventDetails(event) {
                // Ensure reminder_enabled is properly parsed as a number
                const reminderEnabled = Number(event.reminder_enabled);
                
                return `
                    <div class="event-details">
                        <div class="detail-row">
                            <i class="fas fa-calendar-day"></i>
                            <span class="detail-label">Event:</span>
                            <span class="detail-value">${event.title}</span>
                        </div>
                        <div class="detail-row">
                            <i class="far fa-calendar"></i>
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">${formatDateForDisplay(event.date)}</span>
                        </div>
                        <div class="detail-row">
                            <i class="far fa-clock"></i>
                            <span class="detail-label">Time:</span>
                            <span class="detail-value">${formatTime(event.time)}</span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-info-circle"></i>
                            <span class="detail-label">Description:</span>
                            <span class="detail-value">${event.description || 'No description available'}</span>
                        </div>
                        <div class="reminder-section">
                            <label class="toggle-container">
                                <span class="toggle-label">Reminder</span>
                                <div class="toggle-switch-wrapper">
                                    <input type="checkbox" 
                                           id="reminderToggle_${event.id}"
                                           class="toggle-switch" 
                                           ${reminderEnabled === 1 ? 'checked' : ''}
                                           onchange="handleReminderToggle(${event.id})"
                                           data-event-id="${event.id}">
                                    <span class="toggle-slider"></span>
                                </div>
                            </label>
                        </div>
                    </div>
                `;
            }
            
            function handleReminderToggle(eventId) {
                const toggleElement = document.getElementById(`reminderToggle_${eventId}`);
                const newReminderState = toggleElement.checked ? 1 : 0;
                
                // Disable the toggle while the request is being processed
                toggleElement.disabled = true;
                
                fetch('toggle_reminder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: eventId,
                        reminder_enabled: newReminderState
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the events array
                        const eventIndex = events.findIndex(e => e.id == eventId);
                        if (eventIndex !== -1) {
                            events[eventIndex].reminder_enabled = newReminderState;
                        }
                        
                        // Ensure the toggle reflects the correct state
                        toggleElement.checked = newReminderState === 1;
                        
                        showNotification(
                            newReminderState === 1 
                                ? 'Email reminder set for 1 day before the event!' 
                                : 'Reminder disabled'
                        );
                    } else {
                        // Revert the checkbox if there was an error
                        toggleElement.checked = !toggleElement.checked;
                        showNotification('Error updating reminder', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    toggleElement.checked = !toggleElement.checked;
                    showNotification('Error updating reminder', 'error');
                })
                .finally(() => {
                    // Re-enable the toggle after the request completes
                    toggleElement.disabled = false;
                });
            }
            
            function showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 25px;
                    border-radius: 5px;
                    z-index: 1000;
                    animation: fadeIn 0.3s, fadeOut 0.3s 2.7s;
                    color: white;
                    background-color: ${type === 'success' ? '#00bcd4' : '#ff4444'};
                `;
                notification.textContent = message;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
            
            function saveEvent() {
                const reminderCheckbox = document.getElementById('eventReminder');
                
                const eventData = {
                    id: eventIdInput.value || null,
                    date: eventDateInput.value,
                    title: eventTitleInput.value,
                    time: eventTimeInput.value,
                    description: eventDescriptionInput.value,
                    reminder_enabled: reminderCheckbox.checked ? 1 : 0
                };
                
                // Use AJAX to save event to PHP backend
                fetch('save_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(eventData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // If successful, update local events
                        if (eventData.id) {
                            // Update existing event
                            const index = events.findIndex(e => e.id == eventData.id);
                            if (index !== -1) {
                                events[index] = { ...events[index], ...eventData };
                            }
                        } else {
                            // Add new event
                            events.push({
                                ...eventData,
                                id: data.id,
                                event_type: 'custom'
                            });
                        }
                        
                        // Close modal and redisplay events
                        eventModal.style.display = 'none';
                        displayEvents();
                    } else {
                        alert('Error saving event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving event. Please try again.');
                });
            }
            
            function deleteEvent() {
                const eventId = eventIdInput.value;
                
                if (confirm('Are you sure you want to delete this event?')) {
                    fetch('delete_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: eventId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // If successful, remove from local events
                            events = events.filter(e => e.id != eventId);
                            
                            // Close modal and redisplay events
                            eventModal.style.display = 'none';
                            displayEvents();
                        } else {
                            alert('Error deleting event: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting event. Please try again.');
                    });
                }
            }
            
            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            function formatDateForDisplay(dateStr) {
                const date = new Date(dateStr);
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                const month = monthNames[date.getMonth()];
                const day = date.getDate();
                return `${month} ${day}`;
            }
            
            function formatTime(timeStr) {
                if (timeStr) {
                    const time = new Date('1970-01-01T' + timeStr);
                    const hours = time.getHours();
                    const minutes = time.getMinutes();
                    return `${hours}:${minutes.toString().padStart(2, '0')}`;
                }
                return 'All day';
            }
        });
        
        // Add this after your existing script
function closeModal() {
    document.getElementById('eventModal').style.display = 'none';
}
    </script>
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

        
    </script>
</body>
</html>
