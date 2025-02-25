<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ArenaX Manager Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background-color: #333;
            color: white;
            height: 100vh;
            padding: 20px;
        }
        .sidebar-menu {
            list-style-type: none;
            padding: 0;
        }
        .sidebar-menu li {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #444;
        }
        .sidebar-menu li:hover {
            background-color: #444;
        }
        .dashboard-content {
            flex-grow: 1;
            padding: 20px;
        }
        .dashboard-section {
            display: none;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .active-section {
            display: block;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-table th, .user-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .quick-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .stat-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            width: 22%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .action-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .action-button:hover {
            background-color: #0056b3;
        }
        .search-bar {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        .search-bar input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        .status-active { background-color: #28a745; color: white; }
        .status-pending { background-color: #ffc107; color: black; }
        .status-inactive { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>ArenaX Manager</h2>
        <ul class="sidebar-menu">
            <li onclick="showSection('dashboard')">Dashboard</li>
            <li onclick="showSection('users')">User Management</li>
            <li onclick="showSection('bookings')">Bookings</li>
            <li onclick="showSection('events')">Events</li>
            <li onclick="showSection('facilities')">Facilities</li>
        </ul>
    </div>

    <div class="dashboard-content">
        <!-- Dashboard Section -->
        <div id="dashboard" class="dashboard-section active-section">
            <h1>Dashboard Overview</h1>
            
            <div class="quick-stats">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p id="totalUsers">150</p>
                </div>
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <p id="totalBookings">320</p>
                </div>
                <div class="stat-card">
                    <h3>Active Events</h3>
                    <p id="activeEvents">12</p>
                </div>
                <div class="stat-card">
                    <h3>Revenue</h3>
                    <p id="totalRevenue">$45,000</p>
                </div>
            </div>
        </div>

        <!-- User Management Section -->
        <div id="users" class="dashboard-section">
            <h1>User Management</h1>
            
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search users by name or email">
                <button class="action-button" onclick="filterUsers()">Search</button>
                <button class="action-button" onclick="openAddUserModal()">Add New User</button>
            </div>

            <table class="user-table" id="userTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Membership</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <!-- User rows will be dynamically populated -->
                </tbody>
            </table>
        </div>

        <!-- Bookings Section -->
        <div id="bookings" class="dashboard-section">
            <h1>Bookings Management</h1>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>B001</td>
                        <td>John Doe</td>
                        <td>Chess</td>
                        <td>2024-02-15</td>
                        <td>Confirmed</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Events Section -->
        <div id="events" class="dashboard-section">
            <h1>Events Management</h1>
            <div class="search-bar">
                <input type="text" placeholder="Search events">
                <button class="action-button">Search</button>
                <button class="action-button">Create New Event</button>
            </div>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Participants</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Chess Tournament</td>
                        <td>2024-03-15</td>
                        <td>Main Hall</td>
                        <td>32/50</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td>
                            <button class="action-button">Edit</button>
                            <button class="action-button">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Facilities Section -->
        <div id="facilities" class="dashboard-section">
            <h1>Facilities Management</h1>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>Capacity</th>
                        <th>Available</th>
                        <th>Status</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            const sections = document.querySelectorAll('.dashboard-section');
            sections.forEach(section => section.classList.remove('active-section'));

            // Show selected section
            const selectedSection = document.getElementById(sectionId);
            selectedSection.classList.add('active-section');
        }

        // User Management Script (Previous script remains the same)
        let users = [
            { id: 1, name: 'John Doe', email: 'john@example.com', membership: 'silver' },
            { id: 2, name: 'Jane Smith', email: 'jane@example.com', membership: 'gold' },
            { id: 3, name: 'Mike Johnson', email: 'mike@example.com', membership: 'platinum' }
        ];

        let selectedUserId = null;

        function renderUsers(userList) {
            const tableBody = document.getElementById('userTableBody');
            tableBody.innerHTML = '';
            userList.forEach(user => {
                const row = `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.name}</td>
                        <td>${user.email}</td>
                        <td>${user.membership}</td>
                        <td>
                            <button onclick="openUserModal(${user.id})">Edit</button>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
        }

        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filteredUsers = users.filter(user => 
                user.name.toLowerCase().includes(searchTerm) || 
                user.email.toLowerCase().includes(searchTerm)
            );
            renderUsers(filteredUsers);
        }

        function openAddUserModal() {
            // Implementation for adding new user
            alert('Add user functionality to be implemented');
        }

        function openUserModal(userId) {
            // Implementation for editing user
            alert('Edit user functionality to be implemented');
        }

        renderUsers(users);
    </script>
</body>
</html>