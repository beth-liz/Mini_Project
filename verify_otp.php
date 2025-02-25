<?php
session_start();
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "arenax");
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = $_POST['otp'];
    $email = $_SESSION['reset_email'];
    
    // Verify OTP with debug logging
    $sql = "SELECT * FROM users WHERE email = ? AND reset_token = ? AND token_expiry > NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            // Check if OTP exists but expired
            $check_sql = "SELECT token_expiry FROM users WHERE email = ? AND reset_token = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ss", $email, $otp);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if ($row = mysqli_fetch_assoc($check_result)) {
                if (strtotime($row['token_expiry']) < time()) {
                    $error_message = "OTP has expired. Please request a new one.";
                } else {
                    $error_message = "Invalid OTP. Please try again.";
                }
            } else {
                $error_message = "Invalid OTP. Please try again.";
            }
        }
    } else {
        $error_message = "Database error occurred.";
    }
}
// Rest of your HTML code remains the same...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
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
        }

        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: white;
            font-size: 2rem;
            text-transform: uppercase;
            letter-spacing: 4px;
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
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }

        .verify-btn {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .verify-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .message {
            color: white;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verify OTP</h1>
        <?php if(isset($error_message)) echo "<p class='message' style='color: red;'>$error_message</p>"; ?>
        <p class="message">Please enter the OTP sent to your email</p>
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-key"></i>
                <input type="text" name="otp" placeholder="Enter OTP" required>
            </div>
            <button type="submit" class="verify-btn">Verify OTP</button>
        </form>
    </div>
</body>
</html>