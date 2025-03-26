<?php
session_start();
header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email']) || !isset($data['name']) || !isset($data['google_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

// Database connection
$conn = mysqli_connect("localhost", "root", "", "arenax");

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT user_id, membership_id FROM users WHERE email = ?");
$stmt->bind_param("s", $data['email']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists - log them in
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $data['email'];
    $_SESSION['name'] = $data['name'];
    
    // Check membership_id and return appropriate redirect URL
    $redirect_url = ($user['membership_id'] == 1) ? 'membership_plans.php' : 'user_home.php';
    echo json_encode(['success' => true, 'redirect' => $redirect_url]);
} else {
    // New user - create account with membership_id = 1
    $stmt = $conn->prepare("INSERT INTO users (email, name, google_id, membership_id) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $data['email'], $data['name'], $data['google_id']);
    
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['email'] = $data['email'];
        $_SESSION['name'] = $data['name'];
        
        // New users always redirect to membership_plans.php
        echo json_encode(['success' => true, 'redirect' => 'membership_plans.php']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user account']);
    }
}

$stmt->close();
$conn->close();
?> 