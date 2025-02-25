<?php
// Database configuration
$host = "localhost";     // Your database host
$username = "root";      // Your database username
$password = "";          // Your database password
$database = "arenax";    // Your database name

// Create connection
try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    // Set PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?> 