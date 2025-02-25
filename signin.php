<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection (adjust these values according to your database settings)
    $conn = mysqli_connect("localhost", "root", "", "arenax");
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Check for admin credentials
    if ($email === "manager@gmail.com" && $password === "12345678") {
        $_SESSION['admin'] = true;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'manager';
        header("Location: manager_dashboard.php");
        exit();
    }
    
    // Regular user authentication
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        $error_message = "<p style='color: red; text-align: center;'>Email does not exist!</p>";
    } else {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $email;
            
            // Check membership_id and redirect accordingly
            if ($user['membership_id'] == 1) {
                header("Location: membership_plans.php");
            } else {
                header("Location: user_home.php"); // Redirect for all other membership_ids
            }
            exit();
        } else {
            $error_message = "<p style='color: red; text-align: center;'>Incorrect password!</p>";
        }
    }
    
    mysqli_close($conn);
}

if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager')
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In Page</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Added Google Fonts -->
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

        .header {
            font-family: 'Cinzel Decorative', cursive;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .header nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin: 0 auto;
            padding: 0;
        }

        .header div a button {
            transition: background-color 0.3s ease-in-out, transform 0.2s ease;
        }

        .header div a button:hover {
            color: #00bcd4;
            border-color: #00bcd4;
            transform: scale(1.1);
        }

        .log {
            padding: 10px 20px;
            font-size: 1rem; 
            font-family: 'Cinzel Decorative', cursive; 
            background-color: #007cd400; 
            color: white;
            border-style: solid;
            border-width: 1px;
            border-color: white;
            border-radius: 0px; 
            cursor: pointer;
        }

        .header div {
            display: flex;
            gap: 15px;
            margin-right: 40px; /* Adjust spacing to push buttons further right */
        }

        .header nav ul li {
            position: relative;
        }

        .header nav ul li a {
            text-decoration: none;
            color: white;
            font-size: 1.2rem;
            transition: color 0.2s ease-in-out;
        }

        .header nav ul li a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #00bcd4;
            transition: all 0.3s ease-in-out;
            transform: translateX(-50%);
        }

        .header nav ul li a:hover::after {
            width: 100%;
        }

        .header nav ul li a:hover {
            color: #00bcd4;
        }

        .are{
            text-decoration: none;
        }

        .container {
            background: rgba(76, 132, 196, 0.15);
            padding: 2rem;
            margin-top: 100px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            width: 100%;
            max-width: 400px;
            position: relative;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            z-index: 1;
            opacity: 0;
            transform: scale(0.95);
            animation: fadeIn 0.5s ease-out 0.5s forwards;
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
            font-size: 2.5rem;
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

        .bottom-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            font-size: 14px;
        }

        .forgot-password {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
          <a href="homepage.php" class="are"><h2 style="color: white;">ArenaX</h2></a>  
        </div>
        <nav>
            <ul>
                <li><a href="homepage.php">Home</a></li>
                <li><a href="indoor.php">Indoor</a></li>
                <li><a href="outdoor.php">Outdoor</a></li>
                <li><a href="fitness.php">Fitness</a></li>
                <li><a href="homepage.php#membership">Membership</a></li>
            </ul>
        </nav>
        <div>
            <a href="signup.php">
                <button class="log">Sign Up</button>
            </a>
            <a href="signin.php">
                <button class="log">Sign In</button>
            </a>
        </div>
    </header>
    <div class="container">
        <h1>SIGN IN</h1>
        <?php if(isset($error_message)) echo $error_message; ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <div class="bottom-row" style="margin-bottom: 15px; text-align: left; display: block;">
                <a href="forgot_password.php" class="forgot-password">FORGOT PASSWORD?</a>
            </div>
            <button type="submit" class="login-btn">Sign In</button>
            <div class="links" style="text-align: center;">
                <p style="color: white; font-size: 14px;">DONT HAVE AN ACCOUNT? <a href="signup.php" style="color: #00bcd4; text-decoration: underline;">SIGN UP</a></p>
            </div>
        </form>
    </div>
</body>
</html>
