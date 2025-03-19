<?php
session_start();
require_once 'db_connect.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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
    $event_id = isset($_POST['event_id']) ? $_POST['event_id'] : '';
    $event_title = isset($_POST['event_title']) ? $_POST['event_title'] : '';
    $event_date = isset($_POST['event_date']) ? $_POST['event_date'] : '';
    $event_time = isset($_POST['event_time']) ? $_POST['event_time'] : '';
    $event_location = isset($_POST['event_location']) ? $_POST['event_location'] : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00; // Convert to float and provide default value
    
    // If event_id is set but other fields are empty, fetch them from the database
    if (!empty($event_id) && (empty($event_title) || empty($price))) {
        $stmt = $conn->prepare("SELECT event_title, event_date, event_time, event_location, event_price FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $event_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event_details) {
            $event_title = empty($event_title) ? $event_details['event_title'] : $event_title;
            $event_date = empty($event_date) ? $event_details['event_date'] : $event_date;
            $event_time = empty($event_time) ? $event_details['event_time'] : $event_time;
            $event_location = empty($event_location) ? $event_details['event_location'] : $event_location;
            $price = empty($price) ? (float)$event_details['event_price'] : $price;
        }
    }
}

// Process payment
if (isset($_POST['process_payment'])) {
    try {
        $conn->beginTransaction();

        // Check for existing registration
        $stmt = $conn->prepare("SELECT event_reg_id FROM event_registration WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $user['user_id']]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($registration) {
            echo json_encode(['success' => false, 'message' => 'You are already registered for this event.']);
            exit;
        }

        // Insert into event_registration table
        $stmt = $conn->prepare("INSERT INTO event_registration (event_id, user_id) 
                              VALUES (?, ?)");
        $stmt->execute([
            $event_id,
            $user['user_id']
        ]);
        $event_reg_id = $conn->lastInsertId();

        // Generate PDF bill
        $bill_filename = 'event_bill_' . $event_reg_id . '.pdf';
        $bill_dir = __DIR__ . '/bills/'; // Use absolute path
        
        // Create directory if it doesn't exist
        if (!file_exists($bill_dir)) {
            mkdir($bill_dir, 0777, true);
        }
        
        $bill_path = $bill_dir . $bill_filename;
        $relative_bill_path = 'bills/' . $bill_filename; // For database storage
        
        // Generate the PDF bill
        generateEventBill($user['user_id'], $event_id, $event_reg_id, date('Y-m-d'), date('H:i:s'), $price, $bill_path, $conn);

        // Update event_registration with bill path
        $stmt = $conn->prepare("UPDATE event_registration SET bill = ? WHERE event_reg_id = ?");
        $stmt->execute([$relative_bill_path, $event_reg_id]);

        // Insert into payment table
        $stmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time, event_reg_id) 
                              VALUES (?, ?, CURDATE(), CURTIME(), ?)");
        $stmt->execute([$user['user_id'], $price, $event_reg_id]);

        // Create notification for user
        $stmt = $conn->prepare("INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                              VALUES (?, ?, ?, CURDATE(), CURTIME())");
        $notification_message = "Your registration for event '$event_title' on $event_date at $event_time has been confirmed. Registration ID: $event_reg_id";
        $stmt->execute([$user['user_id'], "Event Registration Confirmation", $notification_message]);

        $conn->commit();

        // Send confirmation email using PHPMailer
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'elizabethmaryabraham09@gmail.com'; // Your Gmail address
            $mail->Password = 'xvec mfoh vkhp fabg'; // Your Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX');
            $mail->addAddress($user['email'], $user['name']);

            // Attach the bill
            $mail->addAttachment($bill_path);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Event Registration Confirmation - ArenaX";
            $mail->Body = "
            <html>
            <head>
                <title>Event Registration Confirmation</title>
            </head>
            <body>
                <h2>Thank you for registering for an event at ArenaX!</h2>
                <p>Dear {$user['name']},</p>
                <p>Your event registration has been confirmed with the following details:</p>
                <ul>
                    <li>Event: $event_title</li>
                    <li>Date: $event_date</li>
                    <li>Time: $event_time</li>
                    <li>Location: $event_location</li>
                    <li>Amount Paid: ₹$price</li>
                </ul>
                <p>Registration ID: $event_reg_id</p>
                <p>Please find your registration receipt attached to this email.</p>
                <p>We look forward to seeing you at the event!</p>
                <br>
                <p>Best regards,</p>
                <p>ArenaX Team</p>
            </body>
            </html>";

            $mail->send();
        } catch (Exception $e) {
            // Log the error but don't stop the process
            error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

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
                .event-details {
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
                    <div class="event-details">
                        <p><strong>Event:</strong> <?php echo htmlspecialchars($event_title); ?></p>
                        <p><strong>Date:</strong> <?php echo htmlspecialchars($event_date); ?></p>
                        <p><strong>Time:</strong> <?php echo htmlspecialchars($event_time); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($event_location); ?></p>
                        <p><strong>Amount Paid:</strong> ₹<?php echo htmlspecialchars($price); ?></p>
                    </div>
                    <div class="loading"></div>
                    <p class="redirect-text">Redirecting to your events...</p>
                </div>
            </div>

            <script>
                setTimeout(function() {
                    window.location.href = 'user_events.php';
                }, 3000); // 3 seconds delay
            </script>
        </body>
        </html>
        <?php
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "An error occurred during event registration. Please try again.";
        header("Location: user_events.php");
        exit();
    }
}

/**
 * Generate a PDF bill for event registration
 */
function generateEventBill($user_id, $event_id, $event_reg_id, $reg_date, $reg_time, $amount, $output_path, $conn) {
    // Convert amount to float if it's not already
    $amount = (float)$amount;
    // Get user details
    $stmt = $conn->prepare("SELECT name, email, mobile FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get event details
    $stmt = $conn->prepare("SELECT e.event_title, e.event_date, e.event_time, e.event_location, a.activity_type 
                           FROM events e
                           JOIN activity a ON e.activity_id = a.activity_id
                           WHERE e.event_id = ?");
    $stmt->execute([$event_id]);
    $event_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the time
    $event_time = date('h:i A', strtotime($event_data['event_time']));
    
    // Include TCPDF library
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('ArenaX');
    $pdf->SetAuthor('ArenaX');
    $pdf->SetTitle('Event Registration Receipt');
    $pdf->SetSubject('Event Registration Receipt');
    
    // Remove header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 20, 'ArenaX', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, 'Sports Complex', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Kerala, India', 0, 1, 'R');
    $pdf->Cell(0, 5, 'info@arenax.com', 0, 1, 'R');
    
    $pdf->Ln(10);
    
    // Bill title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'EVENT REGISTRATION RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Bill details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Receipt Number:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'EVENT-' . str_pad($event_reg_id, 6, '0', STR_PAD_LEFT), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Registration Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $reg_date . ' ' . $reg_time, 0, 1);
    
    $pdf->Ln(5);
    
    // Customer details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Customer Details', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Name:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user_data['name'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Email:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user_data['email'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Phone:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user_data['mobile'], 0, 1);
    
    $pdf->Ln(5);
    
    // Event details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Event Details', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Event Title:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_title'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Event Type:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['activity_type'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_date'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Time:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_time, 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Location:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_location'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Amount Paid:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, '₹' . number_format($amount, 2), 0, 1);
    
    $pdf->Ln(10);
    
    // Thank you note
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Thank you for registering for our event. We look forward to seeing you there!', 0, 1, 'C');
    
    // Terms and conditions
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Terms and Conditions:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, '1. Please arrive 15 minutes before the event starts.
2. This ticket is non-refundable.
3. For any queries, please contact our customer support.', 0, 'L');
    
    // Output the PDF
    $pdf->Output($output_path, 'F');
    
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Payment - ArenaX</title>
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

        .event-details {
            background: rgba(255, 255, 255, 0.8); /* Semi-transparent background */
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .event-details h3 {
            margin-bottom: 15px;
            color: #2d3436;
        }

        .event-details p {
            margin: 5px 0;
            color: #4a4a4a;
        }
        
        .booking-details {
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .secure-badge i {
            margin-right: 5px;
            color: #28a745;
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

    <div class="payment-container">
        <h2>Event Registration Details</h2>
        <div class="booking-details">
            <div class="detail-row">
                <span>Event:</span>
                <span><?php echo htmlspecialchars($event_title); ?></span>
            </div>
            <div class="detail-row">
                <span>Date:</span>
                <span><?php echo htmlspecialchars($event_date); ?></span>
            </div>
            <div class="detail-row">
                <span>Time:</span>
                <span><?php echo htmlspecialchars($event_time); ?></span>
            </div>
            <div class="detail-row">
                <span>Location:</span>
                <span><?php echo htmlspecialchars($event_location); ?></span>
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

            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
            <input type="hidden" name="event_title" value="<?php echo htmlspecialchars($event_title); ?>">
            <input type="hidden" name="event_date" value="<?php echo htmlspecialchars($event_date); ?>">
            <input type="hidden" name="event_time" value="<?php echo htmlspecialchars($event_time); ?>">
            <input type="hidden" name="event_location" value="<?php echo htmlspecialchars($event_location); ?>">
            <input type="hidden" name="price" value="<?php echo htmlspecialchars($price); ?>">
            <button type="submit" name="process_payment" class="pay-now-btn">Pay ₹<?php echo htmlspecialchars($price); ?></button>
            
            <div class="secure-badge">
                <i class="fas fa-lock"></i>
                <span>Your payment is secure and encrypted</span>
            </div>
        </form>
    </div>

    <script>
        // Format the card number with spaces
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