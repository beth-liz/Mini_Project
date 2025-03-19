<?php
session_start();
require_once 'db_connect.php'; // Update the path as necessary

// Redirect to login if user is not logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

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
    <title>Calendar</title>
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
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }
        
        .day:hover {
            background-color:rgb(230, 250, 255); /* Very light pink/purple on hover */
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(106, 50, 160, 0.1);
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
            transition: transform 0.2s;
        }
        
        .event:hover {
            transform: translateX(3px);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(50, 143, 160, 0.4); /* Purple tinted overlay */
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border: 1px solid #e6d8ff;
            width: 80%;
            max-width: 500px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(50, 131, 160, 0.2);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close {
            color:rgb(182, 215, 226); /* Medium-light purple */
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close:hover {
            color:rgb(50, 132, 160); /* Deep purple */
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        h2 {
            color:rgb(50, 154, 160); /* Deep purple */
            margin-top: 5px;
            border-bottom: 2px solid #f0e6ff;
            padding-bottom: 10px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color:rgb(91, 199, 213); /* Darker purple */
        }
        
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgb(216, 251, 255); /* Light purple border */
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        input:focus, textarea:focus {
            border-color:rgb(112, 215, 219); /* Medium purple */
            outline: none;
            box-shadow: 0 0 0 3px rgba(147, 112, 219, 0.2);
        }
        
        textarea {
            height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            text-align: right;
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        #deleteEvent {
            background-color: #FF6B8B; /* Deeper pink */
            box-shadow: 0 4px 8px rgba(255, 107, 139, 0.25);
        }
        
        #deleteEvent:hover {
            background-color: #FF4F75; /* Even deeper pink on hover */
        }
        
        #saveEvent {
            min-width: 100px;
        }
        
        /* Day highlighting for current day */
        .day.today {
            background-color:rgb(233, 251, 255);
            border: 2px solid rgb(105, 253, 253);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background:rgb(240, 251, 255);
        }
        
        ::-webkit-scrollbar-thumb {
            background:rgb(182, 213, 226);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background:rgb(112, 207, 219);
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
    <div class="container">
        <h1>My Calendar</h1>
        
        <div class="calendar-header">
            <div class="calendar-nav">
                <button id="prevMonth">← Previous</button>
                <button id="nextMonth">Next →</button>
            </div>
            <div class="month-year" id="monthYear"></div>
            <button id="addEventBtn">+ Add Event</button>
        </div>
        
        <div class="calendar" id="calendar">
            <!-- Calendar will be generated with JavaScript -->
        </div>
    </div>
    
    <!-- Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
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
            let events = [];
            
            // DOM elements
            const calendar = document.getElementById('calendar');
            const monthYear = document.getElementById('monthYear');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');
            const addEventBtn = document.getElementById('addEventBtn');
            const eventModal = document.getElementById('eventModal');
            const closeModal = document.querySelector('.close');
            const eventForm = document.getElementById('eventForm');
            const modalTitle = document.getElementById('modalTitle');
            const eventIdInput = document.getElementById('eventId');
            const eventDateInput = document.getElementById('eventDate');
            const eventTitleInput = document.getElementById('eventTitle');
            const eventTimeInput = document.getElementById('eventTime');
            const eventDescriptionInput = document.getElementById('eventDescription');
            const saveEventBtn = document.getElementById('saveEvent');
            const deleteEventBtn = document.getElementById('deleteEvent');
            
            // Initialize calendar
            renderCalendar();
            fetchEvents();
            
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
                        const eventEl = document.createElement('div');
                        eventEl.className = 'event';
                        eventEl.textContent = `${event.time ? event.time + ': ' : ''}${event.title}`;
                        eventEl.title = event.description || event.title;
                        
                        // Add click event to open modal with this event
                        eventEl.addEventListener('click', (e) => {
                            e.stopPropagation(); // Prevent triggering the day cell click
                            openModal(event);
                        });
                        
                        dayCell.appendChild(eventEl);
                    }
                });
            }
            
            function openModal(event = null, dateStr = null) {
                if (event) {
                    // Edit existing event
                    modalTitle.textContent = 'Edit Event';
                    eventIdInput.value = event.id;
                    eventDateInput.value = event.date;
                    eventTitleInput.value = event.title;
                    eventTimeInput.value = event.time || '';
                    eventDescriptionInput.value = event.description || '';
                    deleteEventBtn.style.display = 'inline-block';
                } else {
                    // Add new event
                    modalTitle.textContent = 'Add Event';
                    eventForm.reset();
                    eventIdInput.value = '';
                    eventDateInput.value = dateStr || formatDate(new Date());
                    deleteEventBtn.style.display = 'none';
                }
                
                eventModal.style.display = 'block';
            }
            
            function saveEvent() {
                const eventData = {
                    id: eventIdInput.value || Date.now().toString(),
                    date: eventDateInput.value,
                    title: eventTitleInput.value,
                    time: eventTimeInput.value,
                    description: eventDescriptionInput.value
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
                        // If successful, update local events and close modal
                        fetchEvents();
                        eventModal.style.display = 'none';
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
                            // If successful, update local events and close modal
                            fetchEvents();
                            eventModal.style.display = 'none';
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
            
            function fetchEvents() {
                // Fetch events from PHP backend
                fetch('get_events.php')
                .then(response => response.json())
                .then(data => {
                    events = data;
                    displayEvents();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
            
            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
        });
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
