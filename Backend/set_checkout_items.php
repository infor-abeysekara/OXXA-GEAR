<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$selectedItems = json_decode($_POST['selected_items'] ?? '[]', true);

if (empty($selectedItems)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit;
}

// Store selected items in session for checkout
$_SESSION['checkout_items'] = $selectedItems;

echo json_encode(['success' => true, 'message' => 'Items selected for checkout']);
?>