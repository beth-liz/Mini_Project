<?php
session_start();
require_once 'db_connect.php';  // Your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Add this PHP code at the top after session_start()
$user_id = $_SESSION['user_id'];

// Get current user's membership status
$stmt = $conn->prepare("SELECT membership_id FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);  // Changed from bind_param to execute with array
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user already has a membership other than 1, redirect to user dashboard
if ($user['membership_id'] != 1) {
    header("Location: user_home.php");
    exit();
}
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Membership Plans</title>
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
                z-index: 2; /* Place content above the overlay */
                max-width: 1200px; /* Reduced from 1800px */
                margin: 0 auto;
                padding: 0 20px; /* Removed top padding */
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
                margin-top: 20px;  /* Reduced from higher value */
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
                margin-top: 0px;  /* Removed top margin */
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
                min-height: 450px;  /* Reduced from 500px */
                background: white;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                padding: 20px;  /* Reduced from 25px */
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
                font-size: 1.8rem;  /* Reduced from 2rem */
                font-weight: 700;
                margin-bottom: 10px;  /* Reduced from 15px */
                color: #333;
            }
            .plan-price {
                font-size: 1.6rem;  /* Reduced from 1.8rem */
                margin: 5px 0 10px;  /* Reduced margins */
                color: #2c3e50;
            }
            .features-list {
                text-align: left;
                margin: 10px 0;  /* Reduced from 15px */
                padding: 0 15px;  /* Reduced from 20px */
            }
            .features-list li {
                font-size: 0.95rem;  /* Reduced from 1rem */
                margin: 8px 0;  /* Reduced from 12px */
                color: #555;
                padding-left: 25px;
            }
            .features-list li:before {
                content: '✓';
                color: #72aab0;
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
            .plan-card:nth-child(1) .plan-name { color: #808080; }
            .plan-card:nth-child(2) .plan-name { color: #DAA520; }
            .plan-card:nth-child(3) .plan-name { color: #666666; }
            .plan-card:nth-child(4) .plan-name { color: #b76e79; }
            /* Updated responsive breakpoints */
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
                    max-width: 350px; /* Reduced from 450px */
                }
            }
            .continue-button {
                display: none;  /* Hidden by default */
                padding: 15px 40px;
                background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-size: 1.2rem;
                margin-top: 20px;  /* Reduced from 30px */
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
        </style>
    </head>
    <body>
        <h1>Choose Your Membership Plan</h1>
        <div class="container">
            <div class="plans-container">
                <!-- Normal Plan -->
                <div class="plan-card">
                    <div class="plan-name">Normal</div>
                    <div class="plan-price">Free</div>
                    <ul class="features-list">
                        <li>Basic access to indoor activities</li>
                        <li>Community forum access</li>
                        <li>Monthly newsletter</li>
                        <li>Basic fitness tips</li>
                        <li>Limited facility hours</li>
                    </ul>
                    <button class="select-button">Select Normal</button>
                </div>

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
            // Add this at the end of your body
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.2
            });

            const plansContainer = document.querySelector('.plans-container');
            observer.observe(plansContainer);

            const planCards = document.querySelectorAll('.plan-card');
            const continueButton = document.querySelector('.continue-button');
            let selectedCard = null;
            let selectedMembershipId = null;

            planCards.forEach(card => {
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
                    const planName = card.querySelector('.plan-name').textContent;
                    
                    // Set membership_id based on plan
                    switch(planName) {
                        case 'Normal':
                            selectedMembershipId = 2;  // Set to 2 for Normal plan
                            break;
                        case 'Standard':
                            selectedMembershipId = 3;
                            break;
                        case 'Premium':
                            selectedMembershipId = 4;
                            break;
                    }

                    card.style.transform = 'translateY(-10px)';
                    continueButton.classList.add('visible');
                });
            });

            continueButton.addEventListener('click', () => {
                if (selectedMembershipId) {
                    // Check if Normal plan is selected
                    if (selectedMembershipId === 2) {
                        // For Normal plan, directly update membership and redirect
                        fetch('update_membership.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `membership_id=${selectedMembershipId}`
                        })
                        .then(response => {
                            console.log('Response:', response);  // Log the response
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                window.location.href = 'user_home.php';  // Redirect to user_home.php
                            } else {
                                alert('Error updating membership: ' + data.message);  // Show error message
                            }
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);  // Log any fetch errors
                            alert('An error occurred while updating membership: ' + error.message);  // Show error message
                        });
                    } else {
                        // For Standard and Premium plans, redirect to membership_payment.php
                        window.location.href = 'membership_payment.php?membership_id=' + selectedMembershipId;
                    }
                }
            });
            
        </script>
    </body>
    </html>
