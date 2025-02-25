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

    // Insert into payment table with the correct column name membership_reg_id
    $stmt = $conn->prepare("INSERT INTO payment (user_id, amount, payment_date, payment_time, membership_reg_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $_POST['amount'], $current_date, $current_time, $membership_reg_id]);

    $conn->commit();
    
    $_SESSION['success_message'] = "Payment successful! Your membership has been updated.";

    // Fetch user email
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $to = $user['email'];
        $subject = "Membership Payment Successful";
        $message = "Dear User,\n\nYour payment of " . $_POST['amount'] . " has been successfully processed.\n";
        $message .= "Your membership has been updated.\n\nThank you for your payment!\n\nBest Regards,\nArenaX";

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
?>