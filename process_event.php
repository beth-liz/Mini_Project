<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to register for this event']);
    exit;
}

// Get the user ID from session
$conn = mysqli_connect("localhost", "root", "", "arenax");
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Adjust the path as necessary

$email = $_SESSION['email'];
$user_query = "SELECT user_id FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Get POST data
$event_id = $_POST['event_id'] ?? null;
$amount = $_POST['amount'] ?? 0; // Payment amount

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required event information']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Check if event exists and has space
$check_event = "SELECT e.event_id, e.event_title, e.event_price, e.max_participants, e.event_date, e.event_time, 
e.event_location, a.activity_type as activity_name, 
(SELECT COUNT(*) FROM event_registration er WHERE er.event_id = e.event_id) as current_participants
FROM events e
JOIN activity a ON e.activity_id = a.activity_id
WHERE e.event_id = ?";
    $stmt = mysqli_prepare($conn, $check_event);
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $event_result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($event_result);

    if (!$event) {
        throw new Exception('Event not found');
    }

    if ($event['current_participants'] >= $event['max_participants']) {
        throw new Exception('This event is already full');
    }

    // Check if user is already registered
    $check_registration = "SELECT event_reg_id FROM event_registration WHERE event_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $check_registration);
    mysqli_stmt_bind_param($stmt, "ii", $event_id, $user['user_id']);
    mysqli_stmt_execute($stmt);
    $registration_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($registration_result) > 0) {
        throw new Exception('You are already registered for this event');
    }

    // Verify payment amount
    if ($amount < $event['event_price']) {
        throw new Exception('Payment amount does not match event price');
    }

    // Create event registration
    $create_registration = "INSERT INTO event_registration (event_id, user_id, bill) VALUES (?, ?, '')";
    $stmt = mysqli_prepare($conn, $create_registration);
    mysqli_stmt_bind_param($stmt, "ii", $event_id, $user['user_id']);
    mysqli_stmt_execute($stmt);
    $registration_id = mysqli_insert_id($conn);

    // Generate PDF bill
    $bill_filename = 'event_bill_' . $registration_id . '.pdf';
    $bill_path = 'bill/' . $bill_filename;
    
    // Generate the PDF bill
    generateEventBill($user['user_id'], $event_id, $registration_id, date('Y-m-d'), date('H:i:s'), $amount, $bill_path, $conn);
    
    // Update registration with bill path
    $update_bill = "UPDATE event_registration SET bill = ? WHERE event_reg_id = ?";
    $stmt = mysqli_prepare($conn, $update_bill);
    mysqli_stmt_bind_param($stmt, "si", $bill_path, $registration_id);
    mysqli_stmt_execute($stmt);

    // Insert into payment table
    if ($amount > 0) {
        $create_payment = "INSERT INTO payment (user_id, amount, payment_date, payment_time, event_reg_id) 
                          VALUES (?, ?, CURDATE(), CURTIME(), ?)";
        $stmt = mysqli_prepare($conn, $create_payment);
        mysqli_stmt_bind_param($stmt, "idi", $user['user_id'], $amount, $registration_id);
        mysqli_stmt_execute($stmt);
    }

    // Create notification
    $create_notification = "INSERT INTO notification (user_id, title, message, created_at_date, created_at_time) 
                          VALUES (?, 'Event Registration', 'Your registration for the event has been confirmed', CURDATE(), CURTIME())";
    $stmt = mysqli_prepare($conn, $create_notification);
    mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
    mysqli_stmt_execute($stmt);

    // Send email with bill attachment
    $get_user_details = "SELECT name, email, mobile FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $get_user_details);
    mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    $user_details = mysqli_fetch_assoc($user_result);

    if ($user_details) {
        $mail = new PHPMailer(true);
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Change to your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'elizabethmaryabraham09@gmail.com'; // Your email
        $mail->Password = 'xvec mfoh vkhp fabg'; // Your email password or App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port = 587; // TCP port to connect to

        // Recipients
        $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX'); // Change to your domain
        $mail->addAddress($user_details['email']); // Add a recipient

        // Attach the bill
        $mail->addAttachment($bill_path);

        // Content
        $mail->isHTML(false);
        $mail->Subject = 'Event Registration Confirmation - ArenaX';
        $mail->Body = "Dear " . $user_details['name'] . ",\n\n" .
                     "Your registration has been confirmed for " . $event['event_title'] . " (" . $event['activity_name'] . ").\n" .
                     "Date: " . $event['event_date'] . "\n" .
                     "Time: " . $event['event_time'] . "\n" .
                     "Location: " . $event['event_location'] . "\n\n" .
                     "Please find your registration receipt attached to this email.\n\n" .
                     "Thank you for choosing ArenaX!\n\n" .
                     "Best Regards,\nArenaX Team";

        $mail->send();
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Event registration confirmed successfully']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);

/**
 * Generate a PDF bill for event registration
 */
function generateEventBill($user_id, $event_id, $registration_id, $reg_date, $reg_time, $amount, $output_path, $conn) {
    // Ensure the bill directory exists
    if (!file_exists('bill')) {
        mkdir('bill', 0777, true);
    }
    
    // Get user details
    $get_user = "SELECT name, email, mobile FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $get_user);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($user_result);
    
    // Get event details
    $get_event = "SELECT e.event_title, e.event_date, e.event_time, e.event_location, a.activity_type 
                  FROM events e 
                  JOIN activity a ON e.activity_id = a.activity_id 
                  WHERE e.event_id = ?";
    $stmt = mysqli_prepare($conn, $get_event);
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $event_result = mysqli_stmt_get_result($stmt);
    $event_data = mysqli_fetch_assoc($event_result);
    
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
    $pdf->Cell(0, 8, 'EVENT-' . str_pad($registration_id, 6, '0', STR_PAD_LEFT), 0, 1);
    
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
    $pdf->Cell(90, 8, 'Mobile:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $user_data['mobile'], 0, 1);
    
    $pdf->Ln(5);
    
    // Event details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Event Details', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Event Name:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_title'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Activity Type:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['activity_type'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_date'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Time:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_time'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Location:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $event_data['event_location'], 0, 1);
    
    // Payment details
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Payment Details', 0, 1);
    
    if ($amount > 0) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(90, 8, 'Amount Paid:', 0, 0);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, '₹' . number_format($amount, 2), 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Thank you note
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Thank you for choosing ArenaX. We appreciate your business!', 0, 1, 'C');
    
    // Terms and conditions
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Terms and Conditions:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, '1. Please arrive 15 minutes before your scheduled event time.\n2. Cancellations must be made at least 48 hours in advance.\n3. For any queries, please contact our customer support.', 0, 'L');
    
    // Output the PDF
    $pdf->Output($output_path, 'F');
    
    return true;
}
?>