<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: signin.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli('localhost', 'root', '', 'arenax');

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get user_id from session email
    $email = $_SESSION['email'];
    $sql = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];

    // Prepare and insert feedback
    $sql = "INSERT INTO feedback (user_id, feedback_content, rating, feedback_date, feedback_time) 
            VALUES (?, ?, ?, CURDATE(), CURTIME())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $user_id, $_POST['feedback_content'], $_POST['rating']);

    if ($stmt->execute()) {
        $_SESSION['feedback_success'] = "Thank you for your feedback!";
    } else {
        $_SESSION['feedback_error'] = "Error submitting feedback. Please try again.";
    }

    $stmt->close();
    $conn->close();

    header("Location: user_home.php#feedback-section");
    exit();
}
?> 