<?php
session_start();
require_once 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Get user's bookings with activity details
$stmt = $conn->prepare("
    SELECT 
        b.booking_id,
        sa.sub_activity_name,
        a.activity_type,
        DATE_FORMAT(ts.slot_date, '%d %M %Y') as formatted_date,
        TIME_FORMAT(ts.slot_start_time, '%l:%i %p') as start_time,
        TIME_FORMAT(ts.slot_end_time, '%l:%i %p') as end_time,
        p.amount,
        DATE_FORMAT(b.booking_date, '%d %M %Y') as booking_date,
        TIME_FORMAT(b.booking_time, '%l:%i %p') as booking_time
    FROM booking b
    JOIN sub_activity sa ON b.sub_activity_id = sa.sub_activity_id
    JOIN activity a ON sa.activity_id = a.activity_id
    JOIN timeslots ts ON b.slot_id = ts.slot_id
    JOIN payment p ON b.booking_id = p.booking_id
    WHERE b.user_id = ?
    ORDER BY ts.slot_date DESC, ts.slot_start_time DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - ArenaX</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/event2.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding: 20px;
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
                            <div class="activity-name"><?php echo htmlspecialchars($booking['sub_activity_name']); ?></div>
                            <div class="booking-id">#<?php echo $booking['booking_id']; ?></div>
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
                        </div>

                        <div class="booking-footer">
                            <div class="price">₹<?php echo number_format($booking['amount'], 2); ?></div>
                            <div class="booking-date">
                                Booked on <?php echo $booking['booking_date']; ?> at <?php echo $booking['booking_time']; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 