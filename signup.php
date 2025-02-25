<?php
    // Mock database arrays for demonstration
    $existingEmails = ["test1@example.com", "user2@example.com"];

    // Error messages
    $errors = [
        "name" => "",
        "email" => "",
        "dob" => "",
        "mobile" => "",
        "password" => "",
        "confirm_password" => ""
    ];
    
    // Database connection
    $host = 'localhost';
    $dbname = 'arenax';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = trim($_POST["name"]);
        $email = trim($_POST["email"]);
        $dob = $_POST["dob"];
        $mobile = trim($_POST["mobile"]);
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];
    
        // Name Validation
        if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
            $errors["name"] = "Name can only contain letters and spaces.";
        }
    
        // Email Validation
        // First check email format and content requirements
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Please enter a valid email address.";
        } elseif (!preg_match('/[a-zA-Z]/', $email) || preg_match('/^[0-9]+@/', $email)) {
            $errors["email"] = "Email must contain letters and cannot start with numbers only.";
        } elseif (!preg_match('/^[a-zA-Z0-9._]+@/', $email)) {
            $errors["email"] = "Email can only contain letters, numbers, dots and underscores before the @ symbol.";
        } else {
            // Check if email exists in database
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $emailExists = $stmt->fetchColumn();
            
            if ($emailExists) {
                $errors["email"] = "Email already exists.";
            }
        }

        // Date of Birth Validation
        $dobTimestamp = strtotime($dob);
        $minAgeTimestamp = strtotime("-7 years");
        if ($dobTimestamp > $minAgeTimestamp) {
            $errors["dob"] = "*INVALID AGE.";
        }

        // Mobile Number Validation
        if (!preg_match('/^[6789][0-9]{9}$/', $mobile)) {
            $errors["mobile"] = "Please enter a valid 10-digit mobile number";
        }

        // Password Validation
        if (strlen($password) < 8) {
            $errors["password"] = "Password must be at least 8 characters long.";
        }

        // Confirm Password Validation
        if ($password !== $confirm_password) {
            $errors["confirm_password"] = "Passwords do not match.";
        }

        // If there are no errors, process the form
        if (!array_filter($errors)) {
            try {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Prepare SQL statement
                $sql = "INSERT INTO users (name, email, dob, mobile, password) VALUES (:name, :email, :dob, :mobile, :password)";
                $stmt = $pdo->prepare($sql);
                
                // Execute with parameters
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':dob' => $dob,
                    ':mobile' => $mobile,
                    ':password' => $hashed_password
                ]);
                
                // Store user ID in session
                $_SESSION['user_id'] = $pdo->lastInsertId();
                
                // Redirect to signin page instead of membership plans
                header("Location: signin.php");
                exit();
            } catch(PDOException $e) {
                echo "Error: " . $e->getMessage();
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sign Up Page</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            /* Your existing styles */
            @import url('https://fonts.googleapis.com/css2?family=Newsreader:opsz@6..72&display=swap');
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
                font-family: 'Newsreader', serif;
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
                font-family: 'Aboreto', cursive;
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
                margin-right: 40px;
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

            .are {
                text-decoration: none;
            }

            .container {
                font-family: 'Aboreto', cursive; 
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
                margin-bottom:10px;
            }

            .input-group i {
                position: absolute;
                left: 15px;
                top: 50%;
                transform: translateY(-50%);
                color:rgba(255, 255, 255, 0.68);
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
                letter-spacing: 1px;
            }

            .input-group input::placeholder {
                color: rgba(255, 255, 255, 0.33);
                font-size: 14px;
            }

            .input-group input:focus {
                border-color: rgba(255, 255, 255, 0.29);
                background: rgba(255, 255, 255, 0.2);
            }
            input:-webkit-autofill {
        background: rgba(255, 255, 255, 0.1) !important; /* Maintain the background color */
        color: white !important; /* Maintain the text color */
        -webkit-text-fill-color: white !important; /* Ensure text color stays white */
        border: 2px solid rgba(255, 255, 255, 0.2) !important; /* Maintain the border style */
        transition: background-color 5000s ease-in-out 0s; /* Prevent instant white flash */
    }

    input[type="date"]::-webkit-calendar-picker-indicator {
    background-color: white; /* White color for the calendar icon */
    border-radius: 50%;
    padding: 5px;
    color: white; /* Ensures the calendar icon is white */
    right: 10px; /* Move the calendar icon further to the right */
    position: absolute;
}

input[type="date"]::-webkit-input-placeholder {
    color: red; /* Color of the placeholder text */
}
/* Adjust icon position when focused */
input[type="date"]:focus::-webkit-calendar-picker-indicator {
    background-color: white;
    color: white;
}

/* When date is selected, the icon stays white */
input[type="date"]:valid::-webkit-calendar-picker-indicator {
    background-color: white;
    color: white;
}


            .register-btn {
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
                letter-spacing: 2px;
            }

            .register-btn:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            }

            .bottom-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
                font-size: 14px;
            }

            .remember-me {
                display: flex;
                align-items: center;
                gap: 8px;
                color: white;
            }

            .remember-me input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: rgba(255, 255, 255, 0.5);
            }

            .terms {
                color: white;
                text-decoration: none;
                font-weight: 500;
                transition: color 0.3s ease;
            }

            .terms:hover {
                color: rgba(255, 255, 255, 0.8);
                text-decoration: underline;
            }

            .login-link {
                text-align: center;
                color: white;
                font-size: 14px;
                letter-spacing: 1px;
            }

            .login-link a {
                color: white;
                text-decoration: none;
                font-weight: 500;
                transition: color 0.3s ease;
            }

            .login-link a:hover {
                color: rgba(255, 255, 255, 0.33);
                text-decoration: underline;
            }

            @media (max-width: 480px) {
                .container {
                    margin: 1rem;
                    padding: 1.5rem;
                }
                
                h1 {
                    font-size: 2rem;
                }
            }

            .error-message {
                color: red;
                font-size: 12px;
                margin-top: 5px;
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
            <h1>SIGN UP</h1>
            <form method="POST" action="">
                <div class="input-group">
                    <i class="fas fa-id-badge"></i>
                    <input id="name" name="name" type="text" placeholder="Name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <div class="error-message"><b><?= $errors["name"] ?></b></div>
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input id="email" name="email" type="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="error-message"><b><?= $errors["email"] ?></b></div>
                </div>
                <div class="input-group">
                    <i class="fas fa-calendar"></i>
                    <input id="dob" name="dob" type="date" placeholder="Date of Birth" required value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                    <div class="error-message"><b><?= $errors["dob"] ?></b></div>
                </div>
                <div class="input-group">
                    <i class="fas fa-phone"></i>
                    <input id="mobile" name="mobile" type="tel" placeholder="Mobile Number" required value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                    <div class="error-message"><b><?= $errors["mobile"] ?></b></div>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input id="password" name="password" type="password" placeholder="Password" required oninput="showConfirmPassword()">
                </div>
                <div class="input-group" id="confirm-password-group" style="display: none;">
                    <i class="fas fa-lock"></i>
                    <input id="confirm-password" name="confirm_password" type="password" placeholder="Confirm Password" required>
                </div>
                <br>
                <button type="submit" class="register-btn">Register</button>
                
                
                <div class="login-link">
                    Already have an account? <a href="signin.php" style="color: #00bcd4; text-decoration: underline;">Login</a>
                </div>
            </form>
        </div>
        <script>
            function showConfirmPassword() {
                const passwordField = document.getElementById("password");
                const confirmPasswordGroup = document.getElementById("confirm-password-group");
                if (passwordField.value) {
                    confirmPasswordGroup.style.display = "block";
                } else {
                    confirmPasswordGroup.style.display = "none";
                }
            }
        </script>
    </body>
    </html>
