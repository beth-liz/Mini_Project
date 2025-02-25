<?php
// Start session and include required files
session_start();
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Turn off output buffering
ob_start();

$conn = mysqli_connect("localhost", "root", "", "arenax");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['email'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        
        // Check if email exists
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            $error_message = "Database error occurred.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error_message = "Database error occurred.";
            } else {
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    // Generate OTP
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
    // Change expiry to 24 hours instead of 10 minutes
    $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Store OTP in database
                    $update_sql = "UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    
                    if (!$update_stmt) {
                        $error_message = "Failed to prepare update statement.";
                    } else {
                        mysqli_stmt_bind_param($update_stmt, "sss", $otp, $expiry, $email);
                        
                        if (!mysqli_stmt_execute($update_stmt)) {
                            $error_message = "Failed to update token.";
                        } else {
                            // Send OTP via email
                            $mail = new PHPMailer(true);
                            // Inside the try block where email is sent
try {
    // Server settings
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'elizabethmaryabraham09@gmail.com';
    $mail->Password = 'xvec mfoh vkhp fabg';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Add error logging
    error_log("Attempting to send email to: " . $email);
    
    // Recipients
    $mail->setFrom('elizabethmaryabrahm09@gmail.com', 'ArenaX');
    $mail->addAddress($email);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset OTP';
    $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Password Reset OTP</h2>
            <p>Your OTP for password reset is: <strong>{$otp}</strong></p>
            <p>This code will expire in 24 hours.</p>
            <p>If you didn't request this password reset, please ignore this email.</p>
        </body>
        </html>";
    $mail->AltBody = "Your OTP for password reset is: {$otp}\nThis code will expire in 24 hours.";
    
    // Log before sending
    error_log("Email configured, attempting to send...");
    
    $mail->send();
    error_log("Email sent successfully to: " . $email);
    
    $_SESSION['reset_email'] = $email;
    
    // Clear any output buffers before redirecting
    ob_end_clean();
    header("Location: verify_otp.php");
    exit();
} catch (Exception $e) {
    error_log("Failed to send email. Error: " . $mail->ErrorInfo);
    $error_message = "Failed to send OTP. Error: " . $mail->ErrorInfo;
}
                        }
                    }
                } else {
                    $error_message = "Email address not found in our records.";
                }
            }
        }
    }
}

// Rest of your HTML remains the same...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Aboreto&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400&family=Goldman&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('img/log.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            font-family: 'Aboreto', cursive;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .container {
            background: rgba(76, 132, 196, 0.15);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            width: 100%;
            max-width: 400px;
            position: relative;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 1;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: scale(0.95);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: white;
            font-size: 2rem;
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffffff;
            z-index: 2;
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            outline: none;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }

        .input-group input:focus {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.2);
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }

        .login-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .links {
            text-align: center;
        }

        .links a {
            color: #00bcd4;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password</h1>
        <?php if(isset($error_message)) echo "<p style='color: red; text-align: center; margin-bottom: 1rem;'>$error_message</p>"; ?>
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="login-btn">Send OTP</button>
            <div class="links">
                <a href="signin.php">Back to Sign In</a>
            </div>
        </form>
    </div>
</body>
</html>