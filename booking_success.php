<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

$activity_name = isset($_GET['activity']) ? htmlspecialchars($_GET['activity']) : 'activity';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Success - ArenaX</title>
    <link rel="icon" href="img/logo3.png" type="image/png">
    <style>
        body {
            font-family: 'Bodoni Moda', serif;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('img/r12.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
        }

        .success-container {
            background: rgba(76, 132, 196, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .success-icon {
            color: #00bcd4;
            font-size: 80px;
            margin-bottom: 20px;
        }

        h1 {
            color: #00bcd4;
            margin-bottom: 20px;
            font-size: 2.5rem;
        }

        p {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .button {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: 'Bodoni Moda', serif;
        }

        .primary-button {
            background-color: #00bcd4;
            color: white;
        }

        .secondary-button {
            background-color: transparent;
            border: 2px solid #00bcd4;
            color: #00bcd4;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 188, 212, 0.3);
        }

        @media (max-width: 480px) {
            .buttons {
                flex-direction: column;
            }
            
            .button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1>Booking Confirmed!</h1>
        <p>Your booking for <?php echo $activity_name; ?> has been successfully confirmed. You can view your booking details in your bookings section.</p>
        <div class="buttons">
            <a href="user_bookings.php" class="button primary-button">View Bookings</a>
            <a href="user_indoor.php" class="button secondary-button">Back to Activities</a>
        </div>
    </div>
</body>
</html>