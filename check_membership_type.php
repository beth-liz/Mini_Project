<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['membership_type'])) {
    $membership_type = trim($_POST['membership_type']);
    
    try {
        $sql = "SELECT COUNT(*) FROM memberships WHERE LOWER(membership_type) = LOWER(:type)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['type' => $membership_type]);
        $count = $stmt->fetchColumn();
        
        echo $count > 0 ? 'true' : 'false';
    } catch (PDOException $e) {
        echo 'error';
    }
}
?>