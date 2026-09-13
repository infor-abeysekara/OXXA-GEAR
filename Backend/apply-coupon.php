<?php
session_start();
include_once("connection.php");

header('Content-Type: application/json');

if(!isset($_POST['coupon_code']) || !isset($_POST['order_amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$coupon_code = strtoupper(trim($_POST['coupon_code']));
$order_amount = floatval($_POST['order_amount']);

// Get coupon details - using correct column names from database
$coupon_query = "SELECT * FROM coupons WHERE code = ? AND active = 1";
$stmt = $conn->prepare($coupon_query);
$stmt->bind_param("s", $coupon_code);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid coupon code']);
    exit;
}

$coupon = $result->fetch_assoc();

// Check if coupon is expired
if($coupon['expiry_date'] && strtotime($coupon['expiry_date']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Coupon has expired']);
    exit;
}

// Check if coupon usage limit is reached
if($coupon['usage_limit'] && $coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
    echo json_encode(['success' => false, 'message' => 'Coupon usage limit reached']);
    exit;
}

// Check minimum order amount
if($coupon['min_order_amount'] && $coupon['min_order_amount'] > 0 && $order_amount < $coupon['min_order_amount']) {
    echo json_encode(['success' => false, 'message' => 'Minimum order amount of Rs. ' . number_format($coupon['min_order_amount'], 2) . ' required']);
    exit;
}

// Calculate discount
$discount_amount = 0;
if($coupon['discount_type'] === 'percentage') {
    $discount_amount = ($order_amount * $coupon['discount_value']) / 100;
    // Apply maximum discount limit if set
    if($coupon['max_discount'] && $coupon['max_discount'] > 0 && $discount_amount > $coupon['max_discount']) {
        $discount_amount = $coupon['max_discount'];
    }
} else {
    $discount_amount = $coupon['discount_value'];
}

// Ensure discount doesn't exceed order amount
if($discount_amount > $order_amount) {
    $discount_amount = $order_amount;
}

echo json_encode([
    'success' => true,
    'coupon' => $coupon,
    'discount_amount' => $discount_amount
]);
?>