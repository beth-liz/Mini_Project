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
    $activity = $_POST['activity'] ?? '';
    $price = $_POST['price'] ?? '';
    $booking_type = $_POST['booking_type'] ?? 'single';
    $activity_id = $_POST['activity_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $timeslot = $_POST['timeslot'] ?? '';

    // Log the values for debugging
    error_log("Activity ID: " . $activity_id);
    error_log("Date: " . $date);
    error_log("Timeslot: " . $timeslot);
    error_log("Price: " . $price);

    if ($booking_type === 'single') {
        // Get sub_activity_id and slot_id for single booking
        $stmt = $conn->prepare("SELECT sa.sub_activity_id, ts.slot_id 
                               FROM sub_activity sa 
                               JOIN timeslots ts ON sa.sub_activity_id = ts.sub_activity_id 
                               WHERE sa.sub_activity_id = ? 
                               AND ts.slot_date = ? 
                               AND ts.slot_start_time = ? 
                               AND ts.slot_end_time = ?");

        // Convert time format from "12:00 PM - 1:00 PM" to "12:00:00" format
        $times = explode(' - ', $timeslot);
        $start_time = date('H:i:s', strtotime($times[0]));
        $end_time = date('H:i:s', strtotime($times[1]));

        error_log("Activity ID: " . $activity_id);
        error_log("Date: " . $date);
        error_log("Start Time: " . $start_time);
        error_log("End Time: " . $end_time);

        $stmt->execute([$activity_id, $date, $start_time, $end_time]);
        $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking_details) {
            error_log("No booking details found for the provided parameters.");
            throw new Exception('Invalid booking details: No matching records found.');
        }

        // Insert into booking table
        $stmt = $conn->prepare("INSERT INTO booking (user_id, sub_activity_id, slot_id, booking_date, booking_time) 
                              VALUES (?, ?, ?, CURDATE(), CURTIME())");
        
        $stmt->execute([
            $user['user_id'],
            $booking_details['sub_activity_id'],
            $booking_details['slot_id']
        ]);
        $booking_id = $conn->lastInsertId();

        // Generate PDF bill
        $bill_filename = 'booking_bill_' . $booking_id . '.pdf';
        $bill_dir = __DIR__ . '/bills/'; // Use absolute path
        
        // Create directory if it doesn't exist
        if (!file_exists($bill_dir)) {
            mkdir($bill_dir, 0777, true);
        }
        
        $bill_path = $bill_dir . $bill_filename;
        $relative_bill_path = 'bills/' . $bill_filename; // For database storage
        
        // Generate the PDF bill
        generateBookingBill($user['user_id'], $booking_details['sub_activity_id'], $booking_details['slot_id'], 
                           $booking_id, $date, $timeslot, $price, $bill_path, $conn);

        // Update booking with bill path
        $stmt = $conn->prepare("UPDATE booking SET bill = ? WHERE booking_id = ?");
        $stmt->execute([$relative_bill_path, $booking_id]);

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

        // After ALL booking operations are complete, send email
        sendBookingConfirmationEmail(
            $user,
            $activity,
            $date,
            $timeslot,
            $booking_id,
            $price,
            $bill_path
        );
    } else {
        // Store recurring booking details in session
        $_SESSION['recurring_booking'] = [
            'activity_id' => $_POST['activity_id'],
            'activity_name' => $activity,
            'start_date' => $_POST['start_date'],
            'weeks' => $_POST['weeks'],
            'selected_days' => $_POST['selected_days'],
            'booking_time' => $_POST['booking_time'],
            'total_sessions' => $_POST['total_sessions'],
            'price_per_session' => $_POST['price_per_session'],
            'total_price' => $price
        ];
    }
}

// Process payment
if (isset($_POST['process_payment']) && isset($_POST['razorpay_payment_id'])) {
    try {
        $conn->beginTransaction();

        if ($booking_type === 'single') {
            // Insert into booking table
            $stmt = $conn->prepare("INSERT INTO booking (user_id, sub_activity_id, slot_id, booking_date, booking_time) 
                                  VALUES (?, ?, ?, CURDATE(), CURTIME())");
            $stmt->execute([
                $user['user_id'],
                $booking_details['sub_activity_id'],
                $booking_details['slot_id']
            ]);
            $booking_id = $conn->lastInsertId();

            // Generate PDF bill
            $bill_filename = 'booking_bill_' . $booking_id . '.pdf';
            $bill_dir = __DIR__ . '/bills/'; // Use absolute path
            
            // Create directory if it doesn't exist
            if (!file_exists($bill_dir)) {
                mkdir($bill_dir, 0777, true);
            }
            
            $bill_path = $bill_dir . $bill_filename;
            $relative_bill_path = 'bills/' . $bill_filename; // For database storage
            
            // Generate the PDF bill
            generateBookingBill($user['user_id'], $booking_details['sub_activity_id'], $booking_details['slot_id'], 
                               $booking_id, $date, $timeslot, $price, $bill_path, $conn);

            // Update booking with bill path
            $stmt = $conn->prepare("UPDATE booking SET bill = ? WHERE booking_id = ?");
            $stmt->execute([$relative_bill_path, $booking_id]);

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

            // After ALL booking operations are complete, send email
            sendBookingConfirmationEmail(
                $user,
                $activity,
                $date,
                $timeslot,
                $booking_id,
                $price,
                $bill_path
            );
        } else {
            // Process recurring booking
            require_once 'process_recurring_booking.php';
        }

        $conn->commit();
        header("Location: user_bookings.php");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Booking error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred during booking. Please try again.";
        header("Location: user_outdoor.php");
        exit();
    }
}

/**
 * Generate a PDF bill for booking
 */
function generateBookingBill($user_id, $sub_activity_id, $slot_id, $booking_id, $booking_date, $booking_time, $amount, $output_path, $conn) {
    // Get user details including membership
    $stmt = $conn->prepare("SELECT u.name, u.email, u.mobile, m.membership_type 
                           FROM users u 
                           LEFT JOIN memberships m ON u.membership_id = m.membership_id 
                           WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get activity details
    $stmt = $conn->prepare("SELECT sa.sub_act_id, san.sub_act_name, a.activity_type 
                           FROM sub_activity sa 
                           JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id
                           JOIN activity a ON sa.activity_id = a.activity_id 
                           WHERE sa.sub_activity_id = ?");
    $stmt->execute([$sub_activity_id]);
    $activity_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get slot details
    $stmt = $conn->prepare("SELECT slot_date, slot_start_time, slot_end_time FROM timeslots WHERE slot_id = ?");
    $stmt->execute([$slot_id]);
    $slot_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the time slot
    $time_slot = date('h:i A', strtotime($slot_data['slot_start_time'])) . ' - ' . 
                 date('h:i A', strtotime($slot_data['slot_end_time']));
    
    // Include TCPDF library
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('ArenaX');
    $pdf->SetAuthor('ArenaX');
    $pdf->SetTitle('Booking Receipt');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Add logo
    $logo_path = __DIR__ . '/img/logo3.png'; // Adjust path as needed
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 15, 15, 40);
    }
    
    // Company details with custom styling
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 20, 'ArenaX', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, 'Sports Complex', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Kerala, India', 0, 1, 'R');
    $pdf->Cell(0, 5, 'info@arenax.com', 0, 1, 'R');
    
    // Add a line separator
    $pdf->Line(15, 60, 195, 60);
    $pdf->Ln(15);
    
    // Bill title with styling
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'BOOKING RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Receipt details with improved styling
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Receipt Number:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'BOOK-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT), 0, 1);
    
    // Booking date and time with separate lines
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Booking Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, date('d-m-Y', strtotime($booking_date)), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Booking Time:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, date('h:i A', strtotime($booking_time)), 0, 1);
    
    $pdf->Ln(5);
    
    // Customer details section with membership
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Customer Details', 0, 1, '', true);
    
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
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Membership Type:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user_data['membership_type'] ?? 'Non-Member', 0, 1);
    
    $pdf->Ln(5);
    
    // Booking details section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1, '', true);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Activity:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $activity_data['sub_act_name'] . ' (' . $activity_data['activity_type'] . ')', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, date('d-m-Y', strtotime($slot_data['slot_date'])), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Time:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $time_slot, 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Amount Paid:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, chr(0xE2).chr(0x82).chr(0xB9) . ' ' . number_format($amount, 2), 0, 1);
    
    $pdf->Ln(10);
    
    // Thank you note with styling
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Thank you for choosing ArenaX. We appreciate your business!', 0, 1, 'C');
    
    // Terms and conditions with better formatting
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Terms and Conditions:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, "1. Please arrive 15 minutes before your scheduled time.\n" .
                         "2. Cancellations must be made at least 24 hours in advance.\n" .
                         "3. For any queries, please contact our customer support.", 0, 'L');
    
    // Add QR code with booking ID
    $qr_data = "Booking ID: BOOK-" . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . 
               "\nDate: " . date('d-m-Y', strtotime($slot_data['slot_date'])) . 
               "\nTime: " . $time_slot;
    $pdf->write2DBarcode($qr_data, 'QRCODE,L', 160, 230, 30, 30);
    
    // Output the PDF
    $pdf->Output($output_path, 'F');
    
    return true;
}

// Add this new function
function sendBookingConfirmationEmail($user, $activity, $date, $timeslot, $booking_id, $price, $bill_path) {
    try {
        $mail = new PHPMailer(true);
        
        // Enable verbose debug output
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elizabethmaryabraham09@gmail.com';
        $mail->Password = 'xvec mfoh vkhp fabg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        error_log("Starting email send process for booking ID: " . $booking_id);
        error_log("Sending to email: " . $user['email']);

        // Recipients
        $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX Sports');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmation - ArenaX Sports';

        // Email body
        $emailBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { padding: 20px; }
                    .header { background-color: #00bcd4; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { text-align: center; padding: 20px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Booking Confirmation</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$user['name']},</p>
                        <p>Your booking has been confirmed. Here are the details:</p>
                        <ul>
                            <li>Activity: {$activity}</li>
                            <li>Date: {$date}</li>
                            <li>Time: {$timeslot}</li>
                            <li>Booking ID: {$booking_id}</li>
                            <li>Amount Paid: ₹{$price}</li>
                        </ul>
                        <p>Your booking bill has been attached to this email.</p>
                        <p>Thank you for choosing ArenaX Sports!</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated email. Please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody);

        // Verify and attach bill
        if (file_exists($bill_path)) {
            $mail->addAttachment($bill_path, 'booking_bill.pdf');
            error_log("Bill attached successfully: " . $bill_path);
        } else {
            error_log("Bill file not found: " . $bill_path);
        }

        // Send email
        if($mail->send()) {
            error_log("Email sent successfully to {$user['email']}");
            return true;
        } else {
            error_log("Email not sent. Mailer Error: " . $mail->ErrorInfo);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

error_log(print_r($_POST, true)); // Log the entire POST array

// Make sure this is at the top of your file
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
            <?php if ($booking_type === 'single'): ?>
                <div class="detail-row">
                    <span>Date:</span>
                    <span><?php echo htmlspecialchars($date); ?></span>
                </div>
                <div class="detail-row">
                    <span>Time Slot:</span>
                    <span><?php echo htmlspecialchars($timeslot); ?></span>
                </div>
            <?php else: ?>
                <div class="detail-row">
                    <span>Start Date:</span>
                    <span><?php echo htmlspecialchars($_POST['start_date']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Duration:</span>
                    <span><?php echo htmlspecialchars($_POST['weeks']); ?> weeks</span>
                </div>
                <div class="detail-row">
                    <span>Total Sessions:</span>
                    <span><?php echo htmlspecialchars($_POST['total_sessions']); ?></span>
                </div>
            <?php endif; ?>
            <div class="detail-row">
                <span>Amount:</span>
                <span>₹<?php echo htmlspecialchars($price); ?></span>
            </div>
        </div>

        <button id="rzp-button">Pay Now</button>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        var options = {
            "key": "rzp_test_iZLI83hLdG7JqU", // Replace with your Razorpay key
            "amount": "<?php echo $price * 100; ?>",
            "currency": "INR",
            "name": "ArenaX",
            "description": "<?php echo $booking_type === 'recurring' ? 'Recurring' : 'Single'; ?> Booking Payment",
            "image": "img/logo3.png",
            "handler": function (response) {
                // Create a form to submit payment details
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_payment.php';

                // Add all necessary fields
                var formData = {
                    'booking_type': '<?php echo $booking_type; ?>',
                    'activity': '<?php echo addslashes($activity); ?>',
                    'price': '<?php echo addslashes($price); ?>',
                    'process_payment': '1',
                    'razorpay_payment_id': response.razorpay_payment_id
                };

                <?php if ($booking_type === 'recurring'): ?>
                // Add recurring booking specific fields
                Object.assign(formData, {
                    'activity_id': '<?php echo $_POST['activity_id']; ?>',
                    'start_date': '<?php echo $_POST['start_date']; ?>',
                    'weeks': '<?php echo $_POST['weeks']; ?>',
                    'selected_days': '<?php echo $_POST['selected_days']; ?>',
                    'booking_time': '<?php echo $_POST['booking_time']; ?>',
                    'total_sessions': '<?php echo $_POST['total_sessions']; ?>',
                    'price_per_session': '<?php echo $_POST['price_per_session']; ?>'
                });
                <?php else: ?>
                // Add single booking specific fields
                Object.assign(formData, {
                    'date': '<?php echo addslashes($date ?? ''); ?>',
                    'timeslot': '<?php echo addslashes($timeslot ?? ''); ?>'
                });
                <?php endif; ?>

                // Add all fields to form
                for (var key in formData) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = formData[key];
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.submit();
            },
            "prefill": {
                "name": "<?php echo htmlspecialchars($user['name']); ?>",
                "email": "<?php echo htmlspecialchars($user['email']); ?>"
            },
            "theme": {
                "color": "#72aab0"
            }
        };
        var rzp = new Razorpay(options);
        document.getElementById('rzp-button').onclick = function(e) {
            rzp.open();
            e.preventDefault();
        }
    </script>
</body>
</html> 