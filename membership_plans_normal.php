<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade Membership Plans</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Newsreader', serif;
            line-height: 1.6;
            background-image: linear-gradient(rgba(225, 240, 255, 0.23), rgba(251, 253, 255, 0.15)), url('img/f3.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 20px;
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }
        .container {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }
        h1 {
            font-size: 3rem;
            color: #2c3e50;
            margin-bottom: 30px;
            position: relative;
            display: inline-block;
            text-align: center;
            width: 100%;
            margin-top: 20px;
        }
        h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #72aab0, #eead88);
        }
        .plans-container {
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 0px;
            opacity: 0;
            transform: translateY(50px);
            transition: transform 1.5s ease, opacity 1.5s ease;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: nowrap;
            padding: 20px;
        }
        .plans-container.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .plan-card {
            position: relative;
            width: 400px;
            min-height: 450px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            padding: 20px;
            align-items: center;
            cursor: pointer;
            position: relative;
        }
        .plan-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            padding: 3px;
            background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
            -webkit-mask: 
                linear-gradient(#fff 0 0) content-box, 
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .plan-card:hover::before {
            opacity: 1;
        }
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }
        .plan-price {
            font-size: 1.6rem;
            margin: 5px 0 10px;
            color: #2c3e50;
        }
        .features-list {
            text-align: left;
            margin: 10px 0;
            padding: 0 15px;
            list-style: none;
        }
        .features-list li {
            font-size: 0.95rem;
            margin: 8px 0;
            color: #555;
            padding-left: 25px;
            position: relative;
        }
        .features-list li:before {
            content: '✓';
            color: #72aab0;
            position: absolute;
            left: 0;
            margin-right: 10px;
            font-weight: bold;
        }
        .select-button {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-size: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        .select-button:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        /* Custom colors for each plan */
        .plan-card:nth-child(1) .plan-name { color: #DAA520; }
        .plan-card:nth-child(2) .plan-name { color: #b76e79; }

        @media (max-width: 1200px) {
            .plans-container {
                flex-wrap: wrap;
            }
            .plan-card {
                width: calc(50% - 20px);
            }
        }
        @media (max-width: 768px) {
            .plan-card {
                width: 100%;
                max-width: 350px;
            }
        }
        .continue-button {
            display: none;
            padding: 15px 40px;
            background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-size: 1.2rem;
            margin-top: 20px;
            cursor: pointer;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }
        .continue-button.visible {
            display: inline-block;
            opacity: 1;
            transform: translateY(0);
            animation: fadeIn 0.5s ease forwards;
        }
        .continue-button:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
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
        /* Add these new styles */
        .close-icon {
            position: fixed;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            color: #2c3e50;
            cursor: pointer;
            z-index: 1000;
            text-decoration: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .close-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            color: #72aab0;
        }
    </style>
</head>
<body>
    <a href="user_home.php" class="close-icon">×</a>
    <div class="container">
        <h1>Upgrade Membership Plans</h1>
        <div class="plans-container">
            <!-- Standard Plan -->
            <div class="plan-card">
                <div class="plan-name">Standard</div>
                <div class="plan-price">₹999/month</div>
                <ul class="features-list">
                    <li>All Free benefits included</li>
                    <li>Full access to indoor activities</li>
                    <li>2 personal training sessions</li>
                    <li>Basic fitness assessment</li>
                    <li>Access to group classes</li>
                </ul>
                <button class="select-button">Select Standard</button>
            </div>

            <!-- Premium Plan -->
            <div class="plan-card">
                <div class="plan-name">Premium</div>
                <div class="plan-price">₹1999/month</div>
                <ul class="features-list">
                    <li>All Standard benefits included</li>
                    <li>Unlimited personal training</li>
                    <li>Priority facility access</li>
                    <li>Nutrition consultation</li>
                    <li>Exclusive member events</li>
                </ul>
                <button class="select-button">Select Premium</button>
            </div>
        </div>
        <button class="continue-button">Continue</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const plansContainer = document.querySelector('.plans-container');
            plansContainer.classList.add('visible');

            const planCards = document.querySelectorAll('.plan-card');
            const continueButton = document.querySelector('.continue-button');
            let selectedCard = null;
            let selectedMembershipId = null;

            planCards.forEach((card, index) => {
                card.addEventListener('click', () => {
                    // Remove selected state from all cards
                    planCards.forEach(c => c.style.transform = 'translateY(0)');
                    
                    // If clicking the same card, deselect it
                    if (selectedCard === card) {
                        selectedCard = null;
                        selectedMembershipId = null;
                        continueButton.classList.remove('visible');
                        return;
                    }

                    // Select the new card
                    selectedCard = card;
                    // Set membership ID based on the plan (index 0 = Standard, index 1 = Premium)
                    selectedMembershipId = index === 0 ? 3 : 4;

                    card.style.transform = 'translateY(-10px)';
                    continueButton.classList.add('visible');
                });
            });

            continueButton.addEventListener('click', () => {
                if (selectedMembershipId) {
                    window.location.href = `update_membership.php?membership_id=${selectedMembershipId}`;
                }
            });
        });
    </script>
</body>
</html> 