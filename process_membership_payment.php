<?php
session_start();
require_once 'db_connect.php';

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Adjust the path as necessary

if (!isset($_SESSION['user_id']) || !isset($_POST['membership_id'])) {
    header("Location: signin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$membership_id = $_POST['membership_id'];
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Start transaction
$conn->beginTransaction();

try {
    // First insert into membership_reg
    $stmt = $conn->prepare("INSERT INTO membership_reg (user_id, membership_id, membership_reg_date, membership_reg_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $membership_id, $current_date, $current_time]);
    
    $membership_reg_id = $conn->lastInsertId();

    // Update user's membership_id
    $stmt = $conn->prepare("UPDATE users SET membership_id = ? WHERE user_id = ?");
    $stmt->execute([$membership_id, $user_id]);

    // Generate PDF bill with absolute path
    $bill_filename = 'membership_bill_' . $membership_reg_id . '.pdf';
    $bill_dir = __DIR__ . '/bills/'; // Use absolute path
    
    // Create directory if it doesn't exist
    if (!file_exists($bill_dir)) {
        mkdir($bill_dir, 0777, true);
    }
    
    $bill_path = $bill_dir . $bill_filename;
    $relative_bill_path = 'bills/' . $bill_filename; // For database storage
    
    // Generate the PDF bill
    generateMembershipBill($user_id, $membership_id, $membership_reg_id, $current_date, $current_time, $_POST['amount'], $bill_path);
    
    // Insert into payment table with the relative bill path
    $stmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time, membership_reg_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $_POST['amount'], $current_date, $current_time, $membership_reg_id]);
    
    // Update membership_reg with relative bill path
    $stmt = $conn->prepare("UPDATE membership_reg SET bill = ? WHERE membership_reg_id = ?");
    $stmt->execute([$relative_bill_path, $membership_reg_id]);

    $conn->commit();
    
    $_SESSION['success_message'] = "Payment successful! Your membership has been updated.";

    // Fetch user email and details
    $stmt = $conn->prepare("SELECT u.email, u.name, m.membership_type 
                           FROM users u 
                           JOIN memberships m ON m.membership_id = ? 
                           WHERE u.user_id = ?");
    $stmt->execute([$membership_id, $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $to = $user['email'];
        $subject = "Membership Payment Successful";
        $message = "Dear " . $user['name'] . ",\n\n";
        $message .= "Your payment of ₹" . $_POST['amount'] . " for the " . $user['membership_type'] . " membership has been successfully processed.\n";
        $message .= "Your membership has been updated.\n\n";
        $message .= "Please find your bill attached to this email.\n\n";
        $message .= "Thank you for your payment!\n\nBest Regards,\nArenaX";

        // Create a new PHPMailer instance
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
        $mail->addAddress($to); // Add a recipient

        // Attach the bill using the absolute path
        $mail->addAttachment($bill_path);

        // Content
        $mail->isHTML(false); // Set email format to plain text
        $mail->Subject = $subject;
        $mail->Body    = $message;

        // Send the email
        $mail->send();
    }

    // HTML response for success
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .success-container {
                text-align: center;
                margin-top: 100px;
                font-family: Arial, sans-serif;
            }
            .success-message {
                color: #28a745;
                font-size: 24px;
                margin-bottom: 20px;
            }
            .loading {
                display: inline-block;
                width: 50px;
                height: 50px;
                border: 3px solid #f3f3f3;
                border-radius: 50%;
                border-top: 3px solid #28a745;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-message">
                Payment Successful! Your membership has been updated.
            </div>
            <div class="loading"></div>
            <p>Redirecting to dashboard...</p>
        </div>

        <script>
            setTimeout(function() {
                window.location.href = 'user_home.php';
            }, 2000); // 2 seconds delay
        </script>
    </body>
    </html>
    <?php
    exit();
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Generate a PDF bill for membership payment
 */
function generateMembershipBill($user_id, $membership_id, $membership_reg_id, $payment_date, $payment_time, $amount, $output_path) {
    global $conn;
    
    // No need to create directory here as it's already created in the main code
    
    // Get user and membership details with the correct column names
    $stmt = $conn->prepare("SELECT u.name, u.email, u.mobile as phone, m.membership_type 
                           FROM users u 
                           JOIN memberships m ON m.membership_id = ? 
                           WHERE u.user_id = ?");
    $stmt->execute([$membership_id, $user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If we couldn't get the data, use placeholders
    if (!$data) {
        $data = [
            'name' => 'User',
            'email' => 'Not available',
            'phone' => 'Not available',
            'membership_type' => 'Unknown'
        ];
        
        // Get at least the membership type
        $stmt = $conn->prepare("SELECT membership_type FROM memberships WHERE membership_id = ?");
        $stmt->execute([$membership_id]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($membership) {
            $data['membership_type'] = $membership['membership_type'];
        }
    }
    
    // Include TCPDF library - use the correct path
    require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('ArenaX');
    $pdf->SetTitle('Membership Payment Receipt');
    $pdf->SetSubject('Membership Payment Receipt');
    $pdf->SetKeywords('TCPDF, PDF, example, test, guide');

    // Set default header data
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Title
    $pdf->Cell(0, 10, 'ArenaX', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Sports Complex', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Kerala, India', 0, 1, 'R');
    $pdf->Cell(0, 5, 'info@arenax.com', 0, 1, 'R');
    
    $pdf->Ln(10);
    
    // Bill title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'MEMBERSHIP PAYMENT RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Bill details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Receipt Number:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'MEM-' . str_pad($membership_reg_id, 6, '0', STR_PAD_LEFT), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Date:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $payment_date . ' ' . $payment_time, 0, 1);
    
    $pdf->Ln(5);
    
    // Customer details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Customer Details', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Name:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $data['name'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Email:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $data['email'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Phone:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $data['phone'], 0, 1);
    
    $pdf->Ln(5);
    
    // Membership details
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Membership Details', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Membership Type:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $data['membership_type'], 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(90, 8, 'Amount Paid:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Rs. ' . number_format($amount, 2), 0, 1);
    
    $pdf->Ln(10);
    
    // Thank you note
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Thank you for choosing ArenaX. We appreciate your business!', 0, 1, 'C');
    
    // Terms and conditions
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Terms and Conditions:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, '1. This receipt is evidence of your membership payment.
2. Membership is valid for one month from the date of payment.
3. For any queries, please contact our customer support.');
    
    // Output the PDF
    $pdf->Output($output_path, 'F');
    
    return true;
}
?>