<?php
include('../include/connection.php');

header('Content-Type: application/json');

if (isset($_GET['u'])) {
    $username = trim($_GET['u']);
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
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
