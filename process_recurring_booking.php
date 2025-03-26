<?php
session_start();
require_once 'db_connect.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['email'])) {
    header('Location: signin.php');
    exit;
}

// First, let's add the bill column to recurring_bookings table
try {
    $conn->exec("ALTER TABLE recurring_bookings ADD COLUMN bill VARCHAR(255)");
} catch (PDOException $e) {
    // Column might already exist, continue
}

// Handle direct recurring booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Get user details
        $stmt = $conn->prepare("SELECT user_id, name, email, mobile FROM users WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get activity details
        $stmt = $conn->prepare("SELECT sa.sub_activity_price, san.sub_act_name 
                              FROM sub_activity sa 
                              JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id 
                              WHERE sa.sub_activity_id = ?");
        $stmt->execute([$_POST['activity_id']]);
        $activity_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Create bills directory if it doesn't exist
        $bill_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'bills';
        if (!file_exists($bill_dir)) {
            mkdir($bill_dir, 0777, true);
        }

        // Insert into recurring_bookings first
        $stmt = $conn->prepare("INSERT INTO recurring_bookings (user_id, sub_activity_id, start_date, end_date, booking_time, selected_days, status) 
                              VALUES (?, ?, ?, ?, ?, ?, 'active')");
        
        $start_date = $_POST['booking_date'];
        $end_date = $_POST['end_date'];
        $selected_days = $_POST['selected_days'];
        $booking_time = $_POST['start_time'];
        
        $stmt->execute([
            $user['user_id'],
            $_POST['activity_id'],
            $start_date,
            $end_date,
            $booking_time,
            $selected_days
        ]);

        $recurring_id = $conn->lastInsertId();

        // Set up bill paths
        $bill_filename = 'recurring_booking_' . $recurring_id . '.pdf';
        $absolute_bill_path = $bill_dir . DIRECTORY_SEPARATOR . $bill_filename;
        $relative_bill_path = 'bills/' . $bill_filename;

        // Calculate total sessions and amount
        $selected_days_array = json_decode($selected_days);
        $weeks = ceil((strtotime($end_date) - strtotime($start_date)) / (7 * 24 * 60 * 60));
        $total_sessions = count($selected_days_array) * $weeks;
        $price_per_session = $activity_data['sub_activity_price'];
        $total_amount = $price_per_session * $total_sessions;

        // Generate PDF bill
        generateRecurringBookingBill(
            $user,
            $activity_data,
            $recurring_id,
            $start_date,
            $end_date,
            $booking_time,
            $total_amount,
            $total_sessions,
            $price_per_session,
            $selected_days_array,
            $absolute_bill_path,
            $conn
        );

        // Update recurring_bookings with bill path
        $stmt = $conn->prepare("UPDATE recurring_bookings SET bill = ? WHERE recurring_id = ?");
        $stmt->execute([$relative_bill_path, $recurring_id]);

        // Process individual slots
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);

        while ($current_date <= $end_date_obj) {
            $day_of_week = (int)$current_date->format('w');
            
            if (in_array($day_of_week, $selected_days_array)) {
                // Find matching timeslot
                $stmt = $conn->prepare("SELECT slot_id FROM timeslots 
                                      WHERE sub_activity_id = ? 
                                      AND slot_start_time = ? 
                                      AND slot_date = ?");
                $stmt->execute([
                    $_POST['activity_id'],
                    $booking_time,
                    $current_date->format('Y-m-d')
                ]);
                $timeslot_id = $stmt->fetchColumn();

                if ($timeslot_id) {
                    // Insert recurring slot
                    $stmt = $conn->prepare("INSERT INTO recurring_slots 
                                          (recurring_id, booking_date, timeslot_id, status) 
                                          VALUES (?, ?, ?, 'booked')");
                    $stmt->execute([
                        $recurring_id,
                        $current_date->format('Y-m-d'),
                        $timeslot_id
                    ]);

                    // Update timeslot participants
                    $stmt = $conn->prepare("UPDATE timeslots 
                                          SET current_participants = current_participants + 1,
                                              slot_full = CASE 
                                                  WHEN current_participants + 1 >= max_participants THEN 1 
                                                  ELSE 0 
                                              END 
                                          WHERE slot_id = ?");
                    $stmt->execute([$timeslot_id]);
                }
            }
            $current_date->modify('+1 day');
        }

        // Create notification
        $stmt = $conn->prepare("INSERT INTO notification 
                              (user_id, title, message, created_at_date, created_at_time) 
                              VALUES (?, ?, ?, CURDATE(), CURTIME())");
        $notification_message = "Your recurring booking for {$activity_data['sub_act_name']} has been confirmed. Booking ID: $recurring_id";
        $stmt->execute([
            $user['user_id'], 
            "Recurring Booking Confirmation", 
            $notification_message
        ]);

        // Send confirmation email
        sendRecurringBookingEmail(
            $user,
            $activity_data['sub_act_name'],
            $start_date,
            $end_date,
            $booking_time,
            $recurring_id,
            $total_amount,
            $absolute_bill_path,
            $total_sessions,
            $selected_days
        );

        $conn->commit();
        header("Location: user_bookings.php");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error in recurring booking: " . $e->getMessage());
        header('Location: error.php');
        exit;
    }
} else {
    header('Location: user_outdoor.php');
    exit;
}

/**
 * Generate a PDF bill for booking
 */
function generateRecurringBookingBill($user, $activity_data, $recurring_id, $start_date, $end_date, $booking_time, $total_amount, $total_sessions, $price_per_session, $selected_days, $output_path, $conn) {
    // Include TCPDF library
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('ArenaX');
    $pdf->SetAuthor('ArenaX');
    $pdf->SetTitle('Recurring Booking Receipt');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Add logo
    $logo_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo3.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 15, 15, 40);
    }
    
    // Company details
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 20, 'ArenaX', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, 'Sports Complex', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Kerala, India', 0, 1, 'R');
    $pdf->Cell(0, 5, 'info@arenax.com', 0, 1, 'R');
    
    // Add line separator
    $pdf->Line(15, 60, 195, 60);
    $pdf->Ln(15);
    
    // Bill title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'RECURRING BOOKING RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Receipt details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Receipt Number:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'REC-' . str_pad($recurring_id, 6, '0', STR_PAD_LEFT), 0, 1);

    // Booking details
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1, '', true);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Activity:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $activity_data['sub_act_name'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Start Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, date('d-m-Y', strtotime($start_date)), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'End Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, date('d-m-Y', strtotime($end_date)), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Booking Time:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, date('h:i A', strtotime($booking_time)), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Total Sessions:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $total_sessions, 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Price per Session:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, '₹' . number_format($price_per_session, 2), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Total Amount:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, '₹' . number_format($total_amount, 2), 0, 1);

    // Customer details
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Customer Details', 0, 1, '', true);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Name:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user['name'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Email:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user['email'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Mobile:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user['mobile'], 0, 1);

    // Save PDF
    try {
        $pdf->Output($output_path, 'F');
        return true;
    } catch (Exception $e) {
        error_log("Error generating PDF: " . $e->getMessage());
        return false;
    }
}

function sendRecurringBookingEmail($user, $activity, $start_date, $end_date, $booking_time, $recurring_id, $total_amount, $bill_path, $total_sessions, $selected_days) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elizabethmaryabraham09@gmail.com';
        $mail->Password = 'xvec mfoh vkhp fabg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX Sports');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Recurring Booking Confirmation - ArenaX Sports';

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
                        <h2>Recurring Booking Confirmation</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$user['name']},</p>
                        <p>Your recurring booking has been confirmed. Here are the details:</p>
                        <ul>
                            <li>Activity: {$activity}</li>
                            <li>Start Date: {$start_date}</li>
                            <li>End Date: {$end_date}</li>
                            <li>Time: {$booking_time}</li>
                            <li>Total Sessions: {$total_sessions}</li>
                            <li>Booking ID: {$recurring_id}</li>
                            <li>Total Amount: ₹{$total_amount}</li>
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

        // Attach bill
        if (file_exists($bill_path)) {
            $mail->addAttachment($bill_path, 'booking_bill.pdf');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}
?> 