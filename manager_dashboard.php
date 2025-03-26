<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Past date

// Add session validation
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    // Clear any existing session data
    session_unset();
    session_destroy();
    
    // Redirect with no-cache headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Location: signin.php");
    exit();
}

// Add session timeout check (optional but recommended)
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: signin.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: signin.php');
    exit();
}

// Add logout handling
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: signin.php');
    exit();
}

// Add at the top of the file
require_once 'db_connect.php'; // Updated path to database connection file

// Add this at the very top of your PHP code, after require_once 'db_connect.php';
$activeSection = 'overview';

// Fetch total users
function getTotalUsers() {
    global $conn;
    $sql = "SELECT COUNT(*) as total_users FROM users";
    $stmt = $conn->query($sql);
    return $stmt->fetchColumn();
}

// Fetch total bookings
function getTotalBookings() {
    global $conn;
    $sql = "SELECT COUNT(*) as total_bookings FROM booking";
    $stmt = $conn->query($sql);
    return $stmt->fetchColumn();
}

// Fetch total revenue in Rupees
function getTotalRevenue() {
    global $conn;
    $sql = "SELECT SUM(amount) as total_revenue FROM payment";
    $stmt = $conn->query($sql);
    $totalRevenue = $stmt->fetchColumn();
    return $totalRevenue ?: 0; // Return 0 if no payments found
}

// Add this function to fetch the total number of events
function getTotalEvents() {
    global $conn;
    $sql = "SELECT COUNT(*) as total_events FROM events"; // Adjust this query based on your events table
    $stmt = $conn->query($sql);
    return $stmt->fetchColumn();
}

// Update this function to match your actual database schema
function getRecentUsers($limit = 5) {
    global $conn;
    $sql = "SELECT u.name, u.email, u.mobile, m.membership_type 
            FROM users u 
            JOIN memberships m ON u.membership_id = m.membership_id
            ORDER BY u.user_id DESC 
            LIMIT :limit"; // Removed username from the selection
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recentUsers = getRecentUsers(5); // Fetch recent users, limiting to 5

// Get the values
$totalUsers = getTotalUsers();
$totalBookings = getTotalBookings();
$totalRevenue = getTotalRevenue();
$totalEvents = getTotalEvents(); // Fetch the total number of events

// Add these functions after your existing functions and before the HTML
function getMonthlyStats() {
    global $conn;
    try {
        $sql = "SELECT 
                DATE_FORMAT(mr.membership_reg_date, '%Y-%m') as month,
                COUNT(DISTINCT u.user_id) as user_count,
                COUNT(DISTINCT CASE WHEN m.membership_type != 'normal' THEN u.user_id END) as active_users
                FROM users u
                LEFT JOIN membership_reg mr ON u.user_id = mr.user_id
                LEFT JOIN memberships m ON u.membership_id = m.membership_id
                GROUP BY DATE_FORMAT(mr.membership_reg_date, '%Y-%m')
                ORDER BY month DESC 
                LIMIT 12";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getMonthlyRevenue() {
    global $conn;
    try {
        $sql = "SELECT 
                DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                SUM(p.amount) as revenue
                FROM payment p
                GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                ORDER BY month DESC 
                LIMIT 12";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getEmptyMonthsData() {
    $data = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $data[] = [
            'month' => $month,
            'user_count' => 0,
            'active_users' => 0,
            'revenue' => 0
        ];
    }
    return $data;
}

// Add these lines after your existing variable declarations
$monthlyStats = getMonthlyStats();
$monthlyRevenue = getMonthlyRevenue();

// If no data is returned, use empty data
if (empty($monthlyStats)) {
    $monthlyStats = getEmptyMonthsData();
}
if (empty($monthlyRevenue)) {
    $monthlyRevenue = getEmptyMonthsData();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: rgba(255, 255, 255, 0.2);
            --background-light: rgba(76, 132, 196, 0.15);
            --text-dark: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Unna', serif;
            background-image: url('img/log.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }

        .sidebar {
            width: 250px;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 2;
            padding: 20px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .sidebar-logo {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            font-family: 'Cinzel Decorative', cursive;
            margin-bottom: 30px;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav-item {
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }

        .sidebar-nav-item a {
            display: block;
            width: 100%;
            height: 100%;
            color: inherit;
            text-decoration: none;
        }

        .sidebar-nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-nav-item.active {
            background-color: var(--primary-color);
            color: white;
        }

        .dashboard-content {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            z-index: 1;
            position: relative;
            margin-left: 250px;
            padding-left: 20px;
            width: calc(100% - 250px);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin: 30px 0;
            padding-right: 40px;
        }

        .stat-card {
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
            padding: 25px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            min-width: 200px;
        }

        .stat-card h3 {
            color: white;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            overflow: hidden;
            min-width: 800px;
            margin-top: 20px;
        }

        .data-table th, .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--background-light);
            color: white;
        }

        .data-table th {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-weight: bold;
        }

        .data-table tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .data-table tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .btn {
            padding: 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        /* Add new styles */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications {
            position: relative;
            cursor: pointer;
        }

        .notifications-icon {
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .notifications-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 15px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid white;
            transition: all 0.3s ease;
            color: white;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .user-profile:hover .user-avatar {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-edit {
            background-color: #f39c12;
            color: white;
        }

        /* Add dropdown styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 5px;
            z-index: 1;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-item {
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            color: white;
            transition: background-color 0.3s;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .dashboard-section {
            display: block;
        }

        .section-content {
            padding: 20px;
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            margin: 20px 0;
            width: 100%;
            overflow-x: auto;
        }

        .status-active, .status-inactive {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        #add-user-btn {
            margin-right: 20px;
        }

        /* Add this new style for select options */
        select option {
            background: white;
            color: black;
        }

        /* Add this new style for input placeholders */
        input::placeholder {
            color: white; /* Change to your desired color */
            opacity: 0.7; /* Optional: Adjusts the opacity of the placeholder text */
        }

        /* Add this CSS rule to remove text decoration from links */
        .sidebar-nav-item a {
            display: block; /* Make the anchor tag a block element */
            width: 100%; /* Ensure it takes the full width */
            height: 100%; /* Ensure it takes the full height */
            color: inherit; /* Inherit the color from the parent */
            text-decoration: none; /* Remove underline */
        }

        .sidebar-nav-item a:hover {
            text-decoration: none; /* Ensure no underline on hover */
        }

        /* Add a placeholder for charts */
        .chart-placeholder {
            height: 300px; /* Set height for the chart */
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            margin-top: 20px; /* Add margin for spacing */
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        /* Add shadow effect to stat cards */
        .stat-card {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); /* Shadow effect */
        }

        /* Improve table styling */
        .data-table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px; /* Add margin for spacing */
        }

        .data-table tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.05); /* Alternate row color */
        }

        .data-table tr:hover {
            background-color: rgba(255, 255, 255, 0.1); /* Hover effect */
        }

        /* Add a placeholder for charts */
        .chart-placeholder {
            height: 300px; /* Set height for the chart */
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            margin-top: 20px; /* Add margin for spacing */
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .btn:disabled {
            background-color: rgba(255, 255, 255, 0.1); /* Change to your desired disabled background color */
            color: rgba(255, 255, 255, 0.5); /* Change to your desired disabled text color */
            cursor: not-allowed; /* Change cursor to indicate it's disabled */
        }

        .btn:disabled:hover {
            background-color: rgba(255, 255, 255, 0.1); /* Keep the same background on hover */
            color: rgba(255, 255, 255, 0.5); /* Keep the same text color on hover */
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">Manager Dashboard</div>
        <nav>
            <ul class="sidebar-nav">
                <li class="sidebar-nav-item active"><a href="manager_dashboard.php">Overview</a></li>
                <li class="sidebar-nav-item"><a href="manager_user.php">Users</a></li>
                <li class="sidebar-nav-item"><a href="manager_activities.php">Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_sub_activities.php">Sub-Activities</a></li>
                <li class="sidebar-nav-item"><a href="manager_membership.php">Membership</a></li>
                <li class="sidebar-nav-item"><a href="manager_time_slots.php">Time Slots</a></li>
                <li class="sidebar-nav-item"><a href="manager_bookings.php">Bookings</a></li>
                <li class="sidebar-nav-item"><a href="manager_events.php">Events</a></li>
                <li class="sidebar-nav-item"><a href="manager_payments.php">Payments</a></li>
                <li class="sidebar-nav-item"><a href="manager_feedback.php">Feedback</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div id="overview-section" class="dashboard-section">
            <header class="dashboard-header">
                <h1>Manager Overview</h1>
                <div class="header-actions">
                    <div class="dropdown">
                        <div class="user-profile">
                            <div class="user-avatar">MG</div>
                            <span>Manager</span>
                        </div>
                        <div class="dropdown-content">
                            <a href="logout.php" class="dropdown-item">Log Out</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p class="stat-value"><?php echo $totalUsers; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <p class="stat-value"><?php echo $totalBookings; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Revenue</h3>
                    <p class="stat-value">₹<?php echo number_format($totalRevenue, 2); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Events Scheduled</h3>
                    <p class="stat-value"><?php echo $totalEvents; ?></p>
                </div>
            </section>

            <div class="chart-placeholder" style="background-color: white !important; height: 400px;">
                <canvas id="myChart"></canvas>
            </div>

            <section>
                <h2>Recent Users</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Membership</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($user['membership_type']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('myChart').getContext('2d');
        
        // Parse PHP data
        const monthlyStats = <?php echo json_encode($monthlyStats); ?>; // Ensure this variable is defined
        const monthlyRevenue = <?php echo json_encode($monthlyRevenue); ?>; // Ensure this variable is defined

        // Process data for the chart
        const labels = monthlyStats.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }).reverse();

        const userData = monthlyStats.map(item => item.user_count).reverse();
        const activeUserData = monthlyStats.map(item => item.active_users).reverse();
        const revenueData = monthlyRevenue.map(item => item.revenue / 1000).reverse();

        // Create gradient for the background
        const gradient1 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient1.addColorStop(0, 'rgba(54, 162, 235, 0.3)');
        gradient1.addColorStop(1, 'rgba(54, 162, 235, 0.1)');

        const gradient2 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient2.addColorStop(0, 'rgba(255, 99, 132, 0.3)');
        gradient2.addColorStop(1, 'rgba(255, 99, 132, 0.1)');

        const gradient3 = ctx.createLinearGradient(0, 0, 0, 400);
        gradient3.addColorStop(0, 'rgba(75, 192, 192, 0.3)');
        gradient3.addColorStop(1, 'rgba(75, 192, 192, 0.1)');

        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Users',
                        data: userData,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: gradient1,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Active Users',
                        data: activeUserData,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: gradient2,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Revenue (₹ thousands)',
                        data: revenueData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: gradient3,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'revenue'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#333', // Changed to dark color
                            font: {
                                size: 12
                            },
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#333',
                        bodyColor: '#333',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        displayColors: true,
                        borderColor: '#ddd',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'revenue') {
                                    label += '₹' + context.parsed.y.toFixed(2) + 'k';
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#333'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#333'
                        }
                    },
                    revenue: {
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#333'
                        }
                    }
                },
                backgroundColor: 'white', // Add white background
            }
        });
    });
    </script>
</body>
</html>