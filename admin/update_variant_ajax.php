<?php
session_start();
include_once("../include/connection.php");

header('Content-Type: application/json');

if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if(isset($_POST['action']) && $_POST['action'] == 'update_matrix_field') {
    $variant_id = (int)$_POST['variant_id'];
    $field = $_POST['field']; // 'qty' or 'price'
    $value = (float)$_POST['value'];
    
    if($field === 'price') {
        $query = "UPDATE color_sizes SET selling_price = ? WHERE id = ?";
    } else {
        $value = (int)$value; // Cast strictly to int for qty
        $query = "UPDATE color_sizes SET qty = ? WHERE id = ?";
    }
    
    $stmt = $conn->prepare($query);
    if($field === 'price') {
        $stmt->bind_param("di", $value, $variant_id);
    } else {
        $stmt->bind_param("ii", $value, $variant_id);
    }
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
