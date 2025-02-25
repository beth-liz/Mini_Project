<?php
//this is the update_memberships.php file for the manager_membership.php page

require_once 'db_connect.php'; // Ensure you have the database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $membership_id = $_POST['membership_id'] ?? null;
    $membership_type = $_POST['membership_type'] ?? null;
    $membership_price = $_POST['membership_price'] ?? null;

    // Validate inputs
    if ($membership_id && $membership_type) {
        // Prepare the SQL statement
        $sql = "UPDATE memberships SET membership_type = :membership_type, membership_price = :membership_price WHERE membership_id = :membership_id";
        $stmt = $conn->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':membership_id', $membership_id, PDO::PARAM_INT);
        $stmt->bindParam(':membership_type', $membership_type, PDO::PARAM_STR);
        $stmt->bindParam(':membership_price', $membership_price, PDO::PARAM_STR); // Ensure price is treated as a string

        // Execute the statement
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update membership.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid input data.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?> 