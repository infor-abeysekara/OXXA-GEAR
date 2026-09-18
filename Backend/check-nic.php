<?php
session_start();
include('../include/connection.php');

header('Content-Type: application/json');

if (isset($_GET['nic'])) {
    $nic = strtoupper(trim($_GET['nic']));
    
    // Basic NIC format validation (Sri Lankan 9 digits + V/X or 12 digits)
    if (!preg_match('/^([0-9]{9}[VX]|[0-9]{12})$/', $nic)) {
        echo json_encode(["available" => false, "message" => "Invalid NIC format. Use 9 digits+V/X or 12 digits."]);
        exit;
    }
    
    $current_user_id = $_SESSION['userid'] ?? 0;
    
    try {
        if ($current_user_id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM seller_profiles WHERE UPPER(owner_nic) = ? AND user_id != ?");
            $stmt->execute([$nic, $current_user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM seller_profiles WHERE UPPER(owner_nic) = ?");
            $stmt->execute([$nic]);
        }
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["available" => false, "message" => "This NIC number is already registered."]);
        } else {
            echo json_encode(["available" => true]);
        }
    } catch (PDOException $e) {
        echo json_encode(["available" => false, "error" => "Database error"]);
    }
} else {
    echo json_encode(["available" => false, "message" => "NIC parameter is required"]);
}
?>
