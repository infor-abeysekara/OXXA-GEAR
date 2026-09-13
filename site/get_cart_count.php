<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
    exit;
}

try {
    $cartCount = getCartCount($conn, $_SESSION['userid']);
    echo json_encode(['success' => true, 'cart_count' => $cartCount]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
}
?>