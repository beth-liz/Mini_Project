<?php
session_start();
require_once 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Get user details
$user_email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE email = ?");
$stmt->execute([$user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity = $_POST['activity'] ?? '';
    $date = $_POST['date'] ?? '';
    $timeslot = $_POST['timeslot'] ?? '';
    $price = $_POST['price'] ?? '';

    // Get sub_activity_id and slot_id
    $stmt = $conn->prepare("SELECT sa.sub_activity_id, ts.slot_id 
                           FROM sub_activity sa 
                           JOIN timeslots ts ON sa.sub_activity_id = ts.sub_activity_id 
                           WHERE sa.sub_activity_name = ? 
                           AND DATE(ts.slot_date) = ? 
                           AND CONCAT(TIME_FORMAT(ts.slot_start_time, '%l:%i %p'), ' - ', 
                               TIME_FORMAT(ts.slot_end_time, '%l:%i %p')) = ?");
    $stmt->execute([$activity, $date, $timeslot]);
    $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Process payment
if (isset($_POST['process_payment'])) {
    try {
        $conn->beginTransaction();

        // Insert into booking table
        $stmt = $conn->prepare("INSERT INTO booking (user_id, sub_activity_id, slot_id, booking_date, booking_time) 
                              VALUES (?, ?, ?, CURDATE(), CURTIME())");
        $stmt->execute([
            $user['user_id'],
            $booking_details['sub_activity_id'],
            $booking_details['slot_id']
        ]);
        $booking_id = $conn->lastInsertId();

        // Insert into payment table
        $stmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time, booking_id) 
                              VALUES (?, ?, CURDATE(), CURTIME(), ?)");
        $stmt->execute([$user['user_id'], $price, $booking_id]);

        // Update timeslot current participants and availability
        $stmt = $conn->prepare("UPDATE timeslots 
                              SET current_participants = current_participants + 1,
                                  slot_full = CASE 
                                      WHEN current_participants + 1 >= max_participants THEN 1 
                                      ELSE 0 
                                  END 
                              WHERE slot_id = ?");
        $stmt->execute([$booking_details['slot_id']]);

        // Create notification for user
        $stmt = $conn->prepare("INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                              VALUES (?, ?, ?, CURDATE(), CURTIME())");
        $notification_message = "Your booking for $activity on $date at $timeslot has been confirmed. Booking ID: $booking_id";
        $stmt->execute([$user['user_id'], "Booking Confirmation", $notification_message]);

        $conn->commit();

        // Send confirmation email
        $to = $user['email'];
        $subject = "Booking Confirmation - ArenaX";
        
        $message = "
        <html>
        <head>
            <title>Booking Confirmation</title>
        </head>
        <body>
            <h2>Thank you for your booking at ArenaX!</h2>
            <p>Dear {$user['name']},</p>
            <p>Your booking has been confirmed with the following details:</p>
            <ul>
                <li>Activity: $activity</li>
                <li>Date: $date</li>
                <li>Time: $timeslot</li>
                <li>Amount Paid: ₹$price</li>
            </ul>
            <p>Booking ID: $booking_id</p>
            <p>We look forward to seeing you!</p>
            <br>
            <p>Best regards,</p>
            <p>ArenaX Team</p>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ArenaX <noreply@arenax.com>' . "\r\n";

        mail($to, $subject, $message, $headers);

        // Instead of immediately redirecting, show success page
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Success - ArenaX</title>
            <style>
                .success-container {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    background: linear-gradient(rgba(225, 240, 255, 0.23), rgba(251, 253, 255, 0.15)), url('img/f3.png');
                    background-size: cover;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                .success-box {
                    background: rgba(255, 255, 255, 0.95);
                    padding: 40px;
                    border-radius: 20px;
                    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
                    text-align: center;
                    max-width: 500px;
                    width: 90%;
                }
                .success-icon {
                    color: #28a745;
                    font-size: 60px;
                    margin-bottom: 20px;
                }
                .success-message {
                    color: #28a745;
                    font-size: 24px;
                    margin-bottom: 20px;
                }
                .booking-details {
                    margin: 20px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 10px;
                }
                .loading {
                    display: inline-block;
                    width: 40px;
                    height: 40px;
                    border: 3px solid #f3f3f3;
                    border-radius: 50%;
                    border-top: 3px solid #28a745;
                    animation: spin 1s linear infinite;
                    margin: 20px 0;
                }
                .redirect-text {
                    color: #666;
                    font-size: 16px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        </head>
        <body>
            <div class="success-container">
                <div class="success-box">
                    <i class="fas fa-check-circle success-icon"></i>
                    <div class="success-message">
                        Payment Successful!
                    </div>
                    <div class="booking-details">
                        <p><strong>Activity:</strong> <?php echo htmlspecialchars($activity); ?></p>
                        <p><strong>Date:</strong> <?php echo htmlspecialchars($date); ?></p>
                        <p><strong>Time:</strong> <?php echo htmlspecialchars($timeslot); ?></p>
                        <p><strong>Amount Paid:</strong> ₹<?php echo htmlspecialchars($price); ?></p>
                    </div>
                    <div class="loading"></div>
                    <p class="redirect-text">Redirecting to your bookings...</p>
                </div>
            </div>

            <script>
                setTimeout(function() {
                    window.location.href = 'user_bookings.php';
                }, 3000); // 3 seconds delay
            </script>
        </body>
        </html>
        <?php
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "An error occurred during booking. Please try again.";
        header("Location: user_outdoor.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - ArenaX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-image: url('img/log.jpg'); /* Set your background image */
            background-size: cover; /* Cover the entire background */
            background-position: center; /* Center the background image */
            color: #2d3436;
            line-height: 1.6;
        }

        .payment-container {
            max-width: 800px;
            padding: 30px;
            background: rgba(77, 69, 69, 0.9); /* Change to a semi-transparent white */
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border-radius: 20px;
            margin: 40px auto;
        }

        h2 {
            text-align: center;
            color:rgb(103, 199, 206);
            margin-bottom: 30px;
            font-size: 28px;
            position: relative;
            padding-bottom: 10px;
        }

        h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background:rgb(103, 199, 206);
            border-radius: 2px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
            padding: 0 20px;
        }

        .payment-option {
            text-align: center;
            padding: 25px;
            border: 2px solidrgb(100, 214, 208);
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: rgb(103, 199, 206);;
        }

        .payment-option:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(90, 215, 224, 0.57);
        }

        .payment-option.active {
            border-color:rgb(103, 199, 206);;
            background-color: #f8f9ff;
        }

        .payment-option i {
            font-size: 2.5em;
            color:rgb(103, 199, 206);
            margin-bottom: 15px;
            display: block;
        }

        .payment-option span {
            font-weight: 500;
            color: #2d3436;
        }

        .payment-form {
            background:rgba(255, 255, 255, 0.42);
            padding: 30px;
            border-radius: 12px;
            margin-top: 30px;
            border: 1px solid #e9ecef;
        }

        .payment-form h3 {
            margin-bottom: 25px;
            color:rgb(255, 255, 255);;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a4a4a;
            font-size: 0.95em;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color:rgb(103, 199, 206);
        }

        button {
            width: 100%;
            padding: 14px;
            background-color: rgb(82, 170, 177);;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            background-color:rgb(58, 190, 179);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .hidden {
            display: none;
        }

        /* Card icon styles */
        .card-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .input-wrapper {
            position: relative;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .payment-container {
                margin: 20px;
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }
        }

        /* Loading animation */
        .loading {
            position: relative;
            opacity: 0.8;
            pointer-events: none;
        }

        .loading:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success message styling */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: block;
        }

        /* Error message styling */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: block;
        }

        .book-details {
            background: rgba(255, 255, 255, 0.8); /* Semi-transparent background */
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .book-details h3 {
            margin-bottom: 15px;
            color: #2d3436;
        }

        .book-details p {
            margin: 5px 0;
            color: #4a4a4a;
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

    <div class="payment-container">
        <h2>Booking Details</h2>
        <div class="booking-details">
            <div class="detail-row">
                <span>Activity:</span>
                <span><?php echo htmlspecialchars($activity); ?></span>
            </div>
            <div class="detail-row">
                <span>Date:</span>
                <span><?php echo htmlspecialchars($date); ?></span>
            </div>
            <div class="detail-row">
                <span>Time Slot:</span>
                <span><?php echo htmlspecialchars($timeslot); ?></span>
            </div>
            <div class="detail-row">
                <span>Amount:</span>
                <span>₹<?php echo htmlspecialchars($price); ?></span>
            </div>
        </div>

        <form id="paymentForm" method="POST" class="payment-form">
            <div class="form-group">
                <label for="cardName">Cardholder Name</label>
                <input type="text" id="cardName" name="card_holder" required placeholder="Name">
            </div>

            <div class="form-group">
                <label for="cardNumber">Card Number</label>
                <input type="text" id="cardNumber" name="card_number" required placeholder="1234 5678 9012 3456" maxlength="19">
            </div>

            <div class="card-details">
                <div class="form-group">
                    <label for="expiryDate">Expiry Date</label>
                    <input type="text" id="expiryDate" name="expiry" required placeholder="MM/YY" maxlength="5">
                </div>

                <div class="form-group">
                    <label for="cvv">CVV</label>
                    <input type="password" id="cvv" name="cvv" required placeholder="123" maxlength="3">
                </div>
            </div>

            <input type="hidden" name="activity" value="<?php echo htmlspecialchars($activity); ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <input type="hidden" name="timeslot" value="<?php echo htmlspecialchars($timeslot); ?>">
            <input type="hidden" name="price" value="<?php echo htmlspecialchars($price); ?>">
            <button type="submit" name="process_payment" class="pay-now-btn">Pay ₹<?php echo htmlspecialchars($price); ?></button>
            
            <div class="secure-badge">
                <i class="fas fa-lock"></i>
                <span>Your payment is secure and encrypted</span>
            </div>
        </form>
    </div>

    <script>
        function showPaymentForm(type) {
            // Hide all payment forms first
            document.getElementById('cardForm').classList.remove('hidden');
            
            // Update active state of payment options
            const options = document.querySelectorAll('.payment-option');
            options.forEach(option => {
                option.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
        }

        // Add basic form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!validateForm(e.target)) {
                    e.preventDefault();
                }
            });
        });

        function validateForm(form) {
            if (form.payment_type.value === 'card') {
                // Validate card number (16 digits, removing spaces)
                const cardNumber = form.card_number.value.replace(/\s/g, '');
                if (!/^\d{16}$/.test(cardNumber)) {
                    alert('Please enter a valid 16-digit card number');
                    return false;
                }

                // Validate expiry date (MM/YY format)
                if (!/^\d{2}\/\d{2}$/.test(form.expiry.value)) {
                    alert('Please enter a valid expiry date (MM/YY)');
                    return false;
                }

                // Validate CVV (3 digits)
                if (!/^\d{3}$/.test(form.cvv.value)) {
                    alert('Please enter a valid 3-digit CVV');
                    return false;
                }
            }
            return true;
        }

        // Update the card number formatting
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');    // Remove existing spaces
            value = value.replace(/\D/g, '');                 // Remove non-digits
            if (value.length > 16) {                          // Limit to 16 digits
                value = value.slice(0, 16);
            }
            
            // Format with spaces after every 4 digits
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            e.target.value = formattedValue;
        });

        // Format expiry date input
        document.querySelector('input[name="expiry"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2);
            }
            e.target.value = value;
        });

        // Add loading state to buttons during form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (validateForm(e.target)) {
                    const button = e.target.querySelector('button');
                    button.classList.add('loading');
                    button.textContent = 'Processing...';
                } else {
                    e.preventDefault();
                }
            });
        });

        // Function to show success message
        function showSuccess() {
            document.getElementById('successMessage').style.display = 'block';
            setTimeout(() => {
                document.getElementById('successMessage').style.display = 'none';
            }, 3000);
        }

        // Function to show error message
        function showError() {
            document.getElementById('errorMessage').style.display = 'block';
            setTimeout(() => {
                document.getElementById('errorMessage').style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html> 