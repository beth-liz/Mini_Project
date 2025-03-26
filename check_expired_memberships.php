<?php
require_once 'db_connect.php';

try {
    $currentDate = date('Y-m-d');
    
    // Get the ID of the "normal" membership
    $stmt = $conn->prepare("SELECT membership_id FROM memberships WHERE membership_type = 'Normal' LIMIT 1");
    $stmt->execute();
    $normalMembership = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$normalMembership) {
        die("Normal membership type not found");
    }
    
    $normalMembershipId = $normalMembership['membership_id'];
    
    // Find users with expired memberships
    $stmt = $conn->prepare("SELECT user_id FROM membership_reg 
                           WHERE expiration_date <= ? 
                           AND membership_id != ?
                           ORDER BY expiration_date DESC");
    $stmt->execute([$currentDate, $normalMembershipId]);
    
    $expiredUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reset expired memberships to normal
    foreach ($expiredUsers as $user) {
        $updateStmt = $conn->prepare("UPDATE users SET membership_id = ? WHERE user_id = ?");
        $updateStmt->execute([$normalMembershipId, $user['user_id']]);
        
        echo "Reset membership for user ID: " . $user['user_id'] . " to Normal\n";
    }
    
    echo "Membership check completed. " . count($expiredUsers) . " memberships reset.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 