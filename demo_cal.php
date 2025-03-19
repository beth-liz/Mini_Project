<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f9f0ff; /* Soft lavender background */
            color: #333;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(186, 139, 250, 0.2);
            padding: 25px;
        }
        
        h1 {
            text-align: center;
            color: #6a32a0; /* Deep purple */
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0e6ff; /* Light purple border */
        }
        
        .calendar-nav {
            display: flex;
            gap: 12px;
        }
        
        button {
            background-color: #9370DB; /* Medium purple */
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(147, 112, 219, 0.2);
        }
        
        button:hover {
            background-color: #7F5BD5; /* Darker purple on hover */
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        #addEventBtn {
            background-color: #FF85A2; /* Soft pink */
            box-shadow: 0 4px 8px rgba(255, 133, 162, 0.3);
        }
        
        #addEventBtn:hover {
            background-color: #FF6B8B; /* Deeper pink on hover */
        }
        
        .month-year {
            font-size: 1.8rem;
            font-weight: bold;
            color: #6a32a0; /* Deep purple */
            text-shadow: 1px 1px 2px rgba(106, 50, 160, 0.1);
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
            background-color: #E6E0FF; /* Very light purple */
            border-radius: 8px;
            color: #6a32a0; /* Deep purple */
        }
        
        .day {
            min-height: 110px;
            border: 1px solid #f0e6ff; /* Light purple border */
            padding: 10px;
            background-color: white;
            position: relative;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }
        
        .day:hover {
            background-color: #fef4ff; /* Very light pink/purple on hover */
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(106, 50, 160, 0.1);
        }
        
        .day-number {
            font-weight: bold;
            margin-bottom: 8px;
            color: #6a32a0; /* Deep purple */
            font-size: 1.1rem;
            text-align: right;
        }
        
        .day.inactive {
            background-color: #f9f7ff; /* Even lighter purple for inactive days */
            color: #c8b6e2; /* Medium-light purple */
            box-shadow: none;
        }
        
        .day.inactive:hover {
            transform: none;
            box-shadow: none;
        }
        
        .event {
            background: linear-gradient(to right, #FF85A2, #FFA7C0); /* Pink gradient */
            color: white;
            padding: 6px 10px;
            border-radius: 20px;
            margin-bottom: 5px;
            font-size: 0.85rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(255, 133, 162, 0.25);
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
            background-color: rgba(106, 50, 160, 0.4); /* Purple tinted overlay */
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
            box-shadow: 0 10px 30px rgba(106, 50, 160, 0.2);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close {
            color: #c8b6e2; /* Medium-light purple */
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close:hover {
            color: #6a32a0; /* Deep purple */
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        h2 {
            color: #6a32a0; /* Deep purple */
            margin-top: 5px;
            border-bottom: 2px solid #f0e6ff;
            padding-bottom: 10px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #7F5BD5; /* Darker purple */
        }
        
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e6d8ff; /* Light purple border */
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        input:focus, textarea:focus {
            border-color: #9370DB; /* Medium purple */
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
            background-color: #fef4ff;
            border: 2px solid #9370DB;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f9f0ff;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c8b6e2;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #9370DB;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✨ My Calendar ✨</h1>
        
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
</body>
</html>