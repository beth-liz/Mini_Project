<?php
session_start();

// Check if user is logged in and is a manager
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: signin.php');
    exit();
}
?>

// Add at the t


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArenaX - Membership Plans</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Unna&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400&family=Goldman&display=swap');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-image: url('img/mem.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            
        }

        .header {
                font-family: 'Cinzel Decorative', cursive;
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

            .log {
                padding: 10px 20px;
                font-size: 1rem; 
                font-family: 'Cinzel Decorative', cursive; 
                background-color: #007cd400; 
                color: white;
                border-style: solid;
                border-width: 1px;
                border-color: white;
                border-radius: 0px; 
                cursor: pointer;
            }

            .header div {
                display: flex;
                gap: 15px;
                margin-right: 40px;
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

        .membership-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-header h1 {
            padding-top:100px;
            font-size: 2.5rem;
            color:rgb(0, 0, 0);
        }

        .membership-plans {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .plan-card {
            background-color: rgba(216, 198, 158, 0.86);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .plan-card:hover {
            transform: translateY(-10px);
        }

        .plan-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color:rgb(2, 10, 15);
            margin-bottom: 20px;
        }

        .plan-features {
            list-style-type: none;
            margin-bottom: 30px;
        }

        .plan-features li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .plan-features li i {
            margin-right: 10px;
            color:rgb(23, 127, 148);
        }

        .select-plan-btn {
            display: inline-block;
            padding: 12px 30px;
            background-color:rgba(219, 127, 52, 0.7);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .select-plan-btn:hover {
            background-color:rgb(65, 162, 145);
        }

        @media (max-width: 768px) {
            .membership-plans {
                grid-template-columns: 1fr;
            }
        }

        
    </style>
</head>
<body>
<header class="header">
        <div class="logo">
          <a href="homepage.php" class="are"><h2 style="color: white;">ArenaX</h2></a>  
        </div>
        <nav>
            <ul>
                <li><a href="homepage.php">Home</a></li>
                <li><a href="indoor.php">Indoor</a></li>
                <li><a href="outdoor.php">Outdoor</a></li>
                <li><a href="fitness.php">Fitness</a></li>
                <li><a href="membership.php">Membership</a></li>
            </ul>
        </nav>
        <div>
            <a href="signup.php">
                <button class="log">Sign Up</button>
            </a>
            <a href="signin.php">
                <button class="log">Sign In</button>
            </a>
        </div>
    </header>
    <div class="membership-container">
        <div class="section-header">
            <h1>ArenaX Membership Plans</h1>
        </div>
        <div class="membership-plans">
            <div class="plan-card">
                <h2 class="plan-title">Silver Membership</h2>
                <div class="plan-price">$49/month</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i>Basic Access to All Facilities</li>
                    <li><i class="fas fa-check"></i>1 Guest Pass per Month</li>
                    <li><i class="fas fa-check"></i>Standard Booking Priority</li>
                    <li><i class="fas fa-check"></i>Limited Indoor Game Access</li>
                    <li><i class="fas fa-check"></i>Online Scheduling</li>
                    <li><i class="fas fa-check"></i>Monthly Activity Reports</li>
                </ul>
                <a href="#" class="select-plan-btn">Select Silver Plan</a>
            </div>

            <div class="plan-card">
                <h2 class="plan-title">Gold Membership</h2>
                <div class="plan-price">$99/month</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i>Full Access to All Facilities</li>
                    <li><i class="fas fa-check"></i>3 Guest Passes per Month</li>
                    <li><i class="fas fa-check"></i>Priority Booking</li>
                    <li><i class="fas fa-check"></i>Unlimited Indoor & Outdoor Games</li>
                    <li><i class="fas fa-check"></i>Advanced Scheduling</li>
                    <li><i class="fas fa-check"></i>Personalized Training Sessions</li>
                    <li><i class="fas fa-check"></i>Quarterly Performance Analysis</li>
                </ul>
                <a href="#" class="select-plan-btn">Select Gold Plan</a>
            </div>

            <div class="plan-card">
                <h2 class="plan-title">Platinum Membership</h2>
                <div class="plan-price">$199/month</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i>Premium VIP Access</li>
                    <li><i class="fas fa-check"></i>Unlimited Guest Passes</li>
                    <li><i class="fas fa-check"></i>Highest Booking Priority</li>
                    <li><i class="fas fa-check"></i>All Facilities Unrestricted</li>
                    <li><i class="fas fa-check"></i>Personal Fitness Consultant</li>
                    <li><i class="fas fa-check"></i>Private Coaching Sessions</li>
                    <li><i class="fas fa-check"></i>Annual Health & Performance Assessment</li>
                    <li><i class="fas fa-check"></i>Exclusive Member Events</li>
                </ul>
                <a href="#" class="select-plan-btn">Select Platinum Plan</a>
            </div>
        </div>
    </div>
</body>
</html>


