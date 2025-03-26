<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['membership_id'])) {
    header("Location: signin.php");
    exit();
}

$membership_id = $_GET['membership_id'];

// Get membership and user details
$stmt = $conn->prepare("SELECT m.membership_type, m.membership_price, u.name, u.email, u.mobile 
                       FROM memberships m 
                       JOIN users u ON u.user_id = ? 
                       WHERE m.membership_id = ?");
$stmt->execute([$_SESSION['user_id'], $membership_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Razorpay API Keys (Test Mode)
$keyId = 'rzp_test_iZLI83hLdG7JqU';
$keySecret = '9WLU1dQJsvK4xHhAg6n7UJV1'; // Your test secret key
$amount = $data['membership_price'] * 100; // Convert to paise
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Membership Payment</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Newsreader', serif;
            line-height: 1.6;
            background-image: linear-gradient(rgba(225, 240, 255, 0.23), rgba(251, 253, 255, 0.15)), url('img/f3.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            text-align: center;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5rem;
        }

        .membership-details {
            background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .membership-details h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .membership-details p {
            font-size: 1.5rem;
            font-weight: bold;
        }

        button#rzp-button {
            background: linear-gradient(135deg, #72aab0 0%, #eead88 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        button#rzp-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Complete Your Payment</h1>
        
        <div class="membership-details">
            <h2><?php echo htmlspecialchars($data['membership_type']); ?> Membership</h2>
            <p>₹<?php echo htmlspecialchars($data['membership_price']); ?>/month</p>
        </div>

        <button id="rzp-button">Pay Now</button>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        var options = {
            "key": "<?php echo $keyId; ?>",
            "amount": "<?php echo $amount; ?>",
            "currency": "INR",
            "name": "ArenaX",
            "description": "<?php echo $data['membership_type']; ?> Membership Payment",
            "image": "img/logo3.png",
            "handler": function (response) {
                // Get membership type for expiration calculation
                const membershipType = "<?php echo $data['membership_type']; ?>";
                
                // Log the payment ID and membership ID for debugging
                console.log('Payment ID:', response.razorpay_payment_id);
                console.log('Membership ID:', <?php echo $membership_id; ?>);
                
                // Process the payment
                fetch('process_membership_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        membership_id: <?php echo $membership_id; ?>,
                        amount: <?php echo $data['membership_price']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Payment processing response:', data);
                    if (data.success) {
                        // Redirect on success
                        window.location.href = 'user_home.php';
                    } else {
                        console.error('Payment processing error:', data.message);
                        alert('Payment processing error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your payment.');
                });
            },
            "prefill": {
                "name": "<?php echo htmlspecialchars($data['name']); ?>",
                "email": "<?php echo htmlspecialchars($data['email']); ?>",
                "contact": "<?php echo htmlspecialchars($data['mobile']); ?>"
            },
            "theme": {
                "color": "#72aab0"
            },
            "modal": {
                "ondismiss": function() {
                    // Handle modal dismissal (optional)
                    console.log('Payment modal closed');
                }
            }
        };
        var rzp = new Razorpay(options);
        document.getElementById('rzp-button').onclick = function(e) {
            rzp.open();
            e.preventDefault();
        }
    </script>
</body>
</html> 