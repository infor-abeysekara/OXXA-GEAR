<?php
include('../include/connection.php');

header('Content-Type: application/json');

if (isset($_GET['p'])) {
    $phone = trim($_GET['p']);
    
    // Basic phone format validation
    $phoneRegex = '/^(?:\+94|0)?7[0-9]{8}$/';
    if (!preg_match($phoneRegex, $phone)) {
        echo json_encode(["available" => false, "message" => "Invalid phone format"]);
        exit;
    }
    
    // Format phone to 947XXXXXXXX for checking
    $phone = preg_replace('/^(?:\+94|0)?/', '94', $phone);
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        
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
