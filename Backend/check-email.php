<?php
include('../include/connection.php');

header('Content-Type: application/json');

if (isset($_GET['e'])) {
    $email = trim($_GET['e']);
    
    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["available" => false, "message" => "Invalid email format"]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["available" => false]);
        } else {
            echo json_encode(["available" => true]);
        }
    } catch (PDOException $e) {
        // Return false on database error for safety
        echo json_encode(["available" => false, "error" => "Database error"]);
    }
} else {
    echo json_encode(["available" => false]);
}
?>
