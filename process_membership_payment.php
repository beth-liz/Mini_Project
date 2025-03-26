<?php
session_start();
require_once 'db_connect.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// Get the JSON POST data
$data = json_decode(file_get_contents('php://input'), true);

try {
    // Validate required data
    if (!isset($_SESSION['user_id']) || !isset($data['razorpay_payment_id']) || !isset($data['membership_id'])) {
        throw new Exception("Missing required data");
    }

    $user_id = $_SESSION['user_id'];
    $membership_id = $data['membership_id'];
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    // Get membership type and user details
    $stmt = $conn->prepare("SELECT m.membership_type, m.membership_price, u.name, u.email, u.mobile 
                           FROM memberships m, users u 
                           WHERE m.membership_id = ? AND u.user_id = ?");
    $stmt->execute([$membership_id, $user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception('Invalid membership or user data');
    }
    
    $membershipType = $userData['membership_type'];
    $amount = $userData['membership_price'];
    $userName = $userData['name'];
    $userEmail = $userData['email'];
    $userMobile = $userData['mobile'];
    
    // Calculate expiration date
    $expirationDate = $current_date;
    if (strtolower($membershipType) == 'standard') {
        $expirationDate = date('Y-m-d', strtotime($current_date . ' + 3 months'));
    } elseif (strtolower($membershipType) == 'premium') {
        $expirationDate = date('Y-m-d', strtotime($current_date . ' + 6 months'));
    }

    // Begin transaction
    $conn->beginTransaction();
    
    // Update user's membership
    $updateUserStmt = $conn->prepare("UPDATE users SET membership_id = ? WHERE user_id = ?");
    $updateUserStmt->execute([$membership_id, $user_id]);
    
    // Insert membership registration (initially with payment ID as bill)
    $regStmt = $conn->prepare("INSERT INTO membership_reg (user_id, membership_id, membership_reg_date, 
                              membership_reg_time, expiration_date, bill) 
                              VALUES (?, ?, ?, ?, ?, ?)");
    $regStmt->execute([
        $user_id,
        $membership_id,
        $current_date,
        $current_time,
        $expirationDate,
        $data['razorpay_payment_id'] // Initially use payment ID as bill
    ]);
    
    $membership_reg_id = $conn->lastInsertId();
    
    // Record payment
    $paymentStmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time, membership_reg_id) 
                                  VALUES (?, ?, ?, ?, ?)");
    $paymentStmt->execute([
        $user_id,
        $amount,
        $current_date,
        $current_time,
        $membership_reg_id
    ]);
    
    // Commit transaction
    $conn->commit();

    // Send success response immediately
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'redirect' => 'user_home.php'
    ]);

    // After sending response, try to generate bill and send email
    try {
        // Create bills directory if it doesn't exist
        $billsDir = __DIR__ . '/bills';
        if (!file_exists($billsDir)) {
            mkdir($billsDir, 0777, true);
        }
        
        // Generate bill filename
        $billFileName = 'membership_bill_' . $user_id . '_' . time() . '.pdf';
        $billPath = $billsDir . '/' . $billFileName;
        
        // Generate PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('ArenaX');
        $pdf->SetAuthor('ArenaX');
        $pdf->SetTitle('Membership Payment Receipt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 16);
        
        // Add content to PDF
        $pdf->Cell(0, 10, 'ArenaX Sports Complex', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Membership Payment Receipt', 0, 1, 'C');
        $pdf->Ln(10);
        
        // Add payment details
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(70, 10, 'Member Name:', 0);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $userName, 0, 1);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(70, 10, 'Membership Type:', 0);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $membershipType, 0, 1);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(70, 10, 'Amount Paid:', 0);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Rs. ' . number_format($amount, 2), 0, 1);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(70, 10, 'Payment Date:', 0);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $current_date, 0, 1);
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(70, 10, 'Valid Until:', 0);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $expirationDate, 0, 1);
        
        // Save PDF
        $pdf->Output($billPath, 'F');
        
        // Update bill path in database if PDF was generated successfully
        if (file_exists($billPath)) {
            $updateBillStmt = $conn->prepare("UPDATE membership_reg SET bill = ? WHERE membership_reg_id = ?");
            $updateBillStmt->execute([$billFileName, $membership_reg_id]);
            
            // Send email
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'elizabethmaryabraham09@gmail.com';
            $mail->Password = 'xvec mfoh vkhp fabg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom('elizabethmaryabraham09@gmail.com', 'ArenaX');
            $mail->addAddress($userEmail, $userName);
            $mail->addAttachment($billPath, 'ArenaX_Membership_Receipt.pdf');
            
            $mail->isHTML(true);
            $mail->Subject = 'ArenaX Membership Payment Confirmation';
            $mail->Body = "
                <h2>Thank you for your membership payment!</h2>
                <p>Dear $userName,</p>
                <p>Your payment has been successfully processed.</p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li>Membership Type: $membershipType</li>
                    <li>Amount Paid: Rs. " . number_format($amount, 2) . "</li>
                    <li>Valid Until: $expirationDate</li>
                </ul>
                <p>Please find your receipt attached to this email.</p>
                <p>Best regards,<br>ArenaX Team</p>";
            
            $mail->send();
        }
    } catch (Exception $e) {
        // Log error but don't affect user experience
        error_log("Error in bill generation or email sending: " . $e->getMessage());
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Payment processing error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your payment.'
    ]);
}
?>