<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['membership_id'])) {
    header("Location: signin.php");
    exit();
}

$membership_id = $_GET['membership_id'];

// Get membership details
$stmt = $conn->prepare("SELECT membership_type, membership_price FROM memberships WHERE membership_id = ?");
$stmt->execute([$membership_id]);
$membership = $stmt->fetch(PDO::FETCH_ASSOC);
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

        .payment-form {
            text-align: left;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input:focus {
            border-color: #72aab0;
            outline: none;
        }

        .card-details {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }

        button {
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

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            color: #666;
        }

        .secure-badge i {
            color: #72aab0;
        }

        @media (max-width: 500px) {
            .card-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1>Complete Your Payment</h1>
        
        <div class="membership-details">
            <h2><?php echo htmlspecialchars($membership['membership_type']); ?> Membership</h2>
            <p>₹<?php echo htmlspecialchars($membership['membership_price']); ?>/month</p>
        </div>

        <form id="paymentForm" class="payment-form" action="process_membership_payment.php" method="POST">
            <input type="hidden" name="membership_id" value="<?php echo $membership_id; ?>">
            <input type="hidden" name="amount" value="<?php echo $membership['membership_price']; ?>">
            <input type="hidden" name="payment_type" value="membership">
            
            <div class="form-group">
                <label for="cardName">Cardholder Name</label>
                <input type="text" id="cardName" name="card_holder" required placeholder="Name">
            </div>

            <div class="form-group">
                <label for="cardNumber">Card Number</label>
                <input type="text" id="cardNumber" name="card_number" required placeholder="1234 5678 9012 3456" maxlength="19">
            </div>

            <div class="card-details">
                <div class="form-group">
                    <label for="expiryDate">Expiry Date</label>
                    <input type="text" id="expiryDate" name="expiry" required placeholder="MM/YY" maxlength="5">
                </div>

                <div class="form-group">
                    <label for="cvv">CVV</label>
                    <input type="password" id="cvv" name="cvv" required placeholder="123" maxlength="3">
                </div>
            </div>

            <button type="submit">Pay ₹<?php echo htmlspecialchars($membership['membership_price']); ?></button>
            
            <div class="secure-badge">
                <i class="fas fa-lock"></i>
                <span>Your payment is secure and encrypted</span>
            </div>
        </form>
    </div>

    <script>
        // Format card number with spaces
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = '';
            for(let i = 0; i < value.length; i++) {
                if(i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            e.target.value = formattedValue;
        });

        // Format expiry date
        document.getElementById('expiryDate').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if(value.length >= 2) {
                value = value.slice(0,2) + '/' + value.slice(2);
            }
            e.target.value = value;
        });

        // Simple form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // In a real application, you would validate the card details here
            // For this dummy version, we'll just submit the form
            this.submit();
        });
    </script>
</body>
</html> 