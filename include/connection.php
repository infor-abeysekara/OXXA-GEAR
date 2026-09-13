<?php
// Database configuration
$host = 'localhost';
$dbname = 'nutrition.lk';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Also create mysqli connection for legacy code compatibility
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Legacy variables for backward compatibility
$servername = $host;
$database = $dbname;
$mysql = $conn; // Alias for mysqli connection
?>
