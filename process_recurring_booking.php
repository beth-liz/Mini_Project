<?php
require_once 'db_connect.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Start transaction
$conn->beginTransaction();

try {
    // Get user details from session
    $user_email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT user_id, name, email, membership_id FROM users WHERE email = ?");
    $stmt->execute([$user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Calculate end date (start_date + weeks * 7)
    $start_date = $_POST['start_date'];
    $weeks = (int)$_POST['weeks'];
    $end_date = date('Y-m-d', strtotime($start_date . ' + ' . ($weeks * 7) . ' days'));

    // Insert into recurring_bookings table
    $stmt = $conn->prepare("INSERT INTO recurring_bookings 
        (user_id, sub_activity_id, start_date, end_date, booking_time, selected_days) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $user['user_id'],
        $_POST['activity_id'],
        $start_date,
        $end_date,
        $_POST['booking_time'],
        $_POST['selected_days']
    ]);
    
    $recurring_id = $conn->lastInsertId();

    // Generate bill for recurring booking
    $bill_filename = 'recurring_booking_bill_' . $recurring_id . '.pdf';
    
    // Use absolute path for storage but relative for DB
    $bill_dir = $_SERVER['DOCUMENT_ROOT'] . '/bills/';
    
    // Create directory if it doesn't exist
    if (!file_exists($bill_dir)) {
        if (!mkdir($bill_dir, 0755, true)) {
            throw new Exception("Failed to create bill directory");
        }
    }
    
    $bill_path = $bill_dir . $bill_filename;
    $relative_bill_path = '/bills/' . $bill_filename; // Store with leading slash

    // Get activity details
    $stmt = $conn->prepare("SELECT sa.sub_activity_id, san.sub_act_name, a.activity_type 
                           FROM sub_activity sa 
                           JOIN sub_activity_name san ON sa.sub_act_id = san.sub_act_id
                           JOIN activity a ON sa.activity_id = a.activity_id 
                           WHERE sa.sub_activity_id = ?");
    $stmt->execute([$_POST['activity_id']]);
    $activity_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity_data) {
        throw new Exception("Activity not found");
    }

    // Generate PDF bill
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
    $logo_path = $_SERVER['DOCUMENT_ROOT'] . '/img/logo3.png';
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
    
    // Add a line separator
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
    
    // Customer details section
    $pdf->SetFillColor(240, 240, 240);
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
    
    // Booking details section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Recurring Booking Details', 0, 1, '', true);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Activity:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $activity_data['sub_act_name'] . ' (' . $activity_data['activity_type'] . ')', 0, 1);
    
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
    $pdf->Cell(0, 8, date('h:i A', strtotime($_POST['booking_time'])), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Selected Days:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $_POST['selected_days'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Total Sessions:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $_POST['total_sessions'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Amount Paid:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, '₹ ' . number_format($_POST['price'], 2), 0, 1);
    
    // Save PDF
    try {
        $pdf->Output($bill_path, 'F');
        
        if (!file_exists($bill_path)) {
            throw new Exception("Failed to save PDF file");
        }
    } catch (Exception $e) {
        error_log("PDF generation error: " . $e->getMessage());
        throw new Exception("Failed to generate bill: " . $e->getMessage());
    }

    // Update bill path in recurring_bookings table
    $stmt = $conn->prepare("UPDATE recurring_bookings SET bill = ? WHERE recurring_id = ?");
    $result = $stmt->execute([$relative_bill_path, $recurring_id]);
    
    if (!$result) {
        throw new Exception("Failed to update bill path in database");
    }

    // Insert into payment table
    $stmt = $conn->prepare("INSERT INTO payment 
        (user_id, amount, payment_date, payment_time, recurring_id) 
        VALUES (?, ?, CURDATE(), CURTIME(), ?)");
    $result = $stmt->execute([
        $user['user_id'],
        $_POST['price'],
        $recurring_id
    ]);
    
    if (!$result) {
        throw new Exception("Failed to insert payment record");
    }

    // Send confirmation email
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0; // Enable for debugging: SMTP::DEBUG_SERVER
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elizabethmaryabraham09@gmail.com';
        $mail->Password = 'xvec mfoh vkhp fabg'; // Consider using environment variables for credentials
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30; // Set timeout value in seconds

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
                            <li>Activity: {$activity_data['sub_act_name']}</li>
                            <li>Start Date: " . date('d-m-Y', strtotime($start_date)) . "</li>
                            <li>End Date: " . date('d-m-Y', strtotime($end_date)) . "</li>
                            <li>Booking Time: " . date('h:i A', strtotime($_POST['booking_time'])) . "</li>
                            <li>Selected Days: {$_POST['selected_days']}</li>
                            <li>Total Sessions: {$_POST['total_sessions']}</li>
                            <li>Amount Paid: ₹{$_POST['price']}</li>
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

        // Verify bill exists before attaching
        if (file_exists($bill_path)) {
            // Attach bill
            $mail->addAttachment($bill_path, 'recurring_booking_bill.pdf');
        } else {
            error_log("Bill file not found at: " . $bill_path);
            // Continue without attachment
        }

        // Send email
        if (!$mail->send()) {
            throw new Exception("Email could not be sent. Mailer Error: " . $mail->ErrorInfo);
        }
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        // Continue execution even if email fails
    }

    // Create notification
    $stmt = $conn->prepare("INSERT INTO notification 
        (user_id, title, message, created_at_date, created_at_time) 
        VALUES (?, ?, ?, CURDATE(), CURTIME())");
    
    $notification_message = "Your recurring booking for {$activity_data['sub_act_name']} has been confirmed. 
        Start Date: " . date('d-m-Y', strtotime($start_date)) . ", 
        End Date: " . date('d-m-Y', strtotime($end_date));
    
    $stmt->execute([
        $user['user_id'],
        "Recurring Booking Confirmation",
        $notification_message
    ]);

    // Commit transaction
    $conn->commit();

    // Redirect to success page
    header("Location: user_bookings.php?success=1");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Recurring booking error: " . $e->getMessage());
    header("Location: user_outdoor.php?error=" . urlencode("An error occurred during booking: " . $e->getMessage()));
    exit();
}
?>