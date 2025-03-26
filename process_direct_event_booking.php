<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

// Get user details
$user_email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT u.user_id, u.membership_id, m.membership_type 
                       FROM users u 
                       JOIN memberships m ON u.membership_id = m.membership_id 
                       WHERE u.email = ?");
$stmt->bindParam(1, $user_email);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify premium membership
if ($user['membership_type'] !== 'premium') {
    header("Location: user_events.php");
    exit();
}

// Get event ID from POST
$event_id = $_POST['event_id'] ?? null;

if (!$event_id) {
    header("Location: user_events.php");
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Get event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->bindParam(1, $event_id);
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('Event not found');
    }

    // Check if already registered
    $stmt = $conn->prepare("SELECT event_reg_id FROM event_registration WHERE event_id = ? AND user_id = ?");
    $stmt->bindParam(1, $event_id);
    $stmt->bindParam(2, $user['user_id']);
    $stmt->execute();
    if ($stmt->fetch()) {
        throw new Exception('Already registered for this event');
    }

    // Create registration
    $stmt = $conn->prepare("INSERT INTO event_registration (event_id, user_id, bill) VALUES (?, ?, '')");
    $stmt->bindParam(1, $event_id);
    $stmt->bindParam(2, $user['user_id']);
    $stmt->execute();
    $registration_id = $conn->lastInsertId();

    // Create bill directory if it doesn't exist
    $bill_dir = __DIR__ . '/bill';
    if (!file_exists($bill_dir)) {
        mkdir($bill_dir, 0777, true);
    }

    // Generate bill with absolute path
    $bill_filename = 'event_bill_' . $registration_id . '.pdf';
    $bill_path = $bill_dir . '/' . $bill_filename;
    $relative_bill_path = 'bill/' . $bill_filename;

    // Generate PDF bill
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('ArenaX');
    $pdf->SetAuthor('ArenaX');
    $pdf->SetTitle('Event Registration Receipt');
    
    // Remove header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Get user details for bill
    $stmt = $conn->prepare("SELECT name, email, mobile FROM users WHERE user_id = ?");
    $stmt->bindParam(1, $user['user_id']);
    $stmt->execute();
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add content to PDF
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'Event Registration Receipt', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    
    // Add user details
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Registration ID: ' . $registration_id, 0, 1);
    $pdf->Cell(0, 10, 'Name: ' . $user_details['name'], 0, 1);
    $pdf->Cell(0, 10, 'Email: ' . $user_details['email'], 0, 1);
    
    // Add event details
    $pdf->Ln(10);
    $pdf->Cell(0, 10, 'Event: ' . $event['event_title'], 0, 1);
    $pdf->Cell(0, 10, 'Date: ' . $event['event_date'], 0, 1);
    $pdf->Cell(0, 10, 'Time: ' . $event['event_time'], 0, 1);
    $pdf->Cell(0, 10, 'Location: ' . $event['event_location'], 0, 1);
    
    // Premium member note
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Premium Member - No Payment Required', 0, 1);
    
    // Save PDF
    $pdf->Output($bill_path, 'F');

    // Update registration with relative bill path
    $stmt = $conn->prepare("UPDATE event_registration SET bill = ? WHERE event_reg_id = ?");
    $stmt->bindParam(1, $relative_bill_path);
    $stmt->bindParam(2, $registration_id);
    $stmt->execute();

    // Create notification
    $stmt = $conn->prepare("INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                           VALUES (?, 'Event Registration', 'Your registration for the event has been confirmed', CURDATE(), CURTIME())");
    $stmt->bindParam(1, $user['user_id']);
    $stmt->execute();

    // Send email
    require 'vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'elizabethmaryabraham09@gmail.com';
        $mail->Password = 'xvec mfoh vkhp fabg';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email content
        $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX');
        $mail->addAddress($user_details['email']);
        $mail->addAttachment($bill_path);
        
        $mail->isHTML(false);
        $mail->Subject = 'Event Registration Confirmation - ArenaX';
        $mail->Body = "Dear " . $user_details['name'] . ",\n\n" .
                     "Your registration has been confirmed for " . $event['event_title'] . ".\n" .
                     "Date: " . $event['event_date'] . "\n" .
                     "Time: " . $event['event_time'] . "\n" .
                     "Location: " . $event['event_location'] . "\n\n" .
                     "As a premium member, no payment was required.\n\n" .
                     "Please find your registration receipt attached to this email.\n\n" .
                     "Thank you for choosing ArenaX!\n\n" .
                     "Best Regards,\nArenaX Team";

        $mail->send();
    } catch (Exception $e) {
        // Log email error but don't stop the registration process
        error_log("Email sending failed: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();

    // Redirect to bookings page
    header("Location: user_bookings.php");
    exit();

} catch (Exception $e) {
    // Rollback on error
    $conn->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header("Location: user_events.php");
    exit();
} 