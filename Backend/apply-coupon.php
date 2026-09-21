<?php
session_start();
// Always include the correct database connection for oxxa_gear_database
include_once(__DIR__ . "/../include/connection.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['coupon_code']) || !isset($_POST['order_amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$coupon_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['coupon_code'])));
$order_amount = floatval($_POST['order_amount']);

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid coupon code']);
    exit;
}

// 1. Ensure table and schema compatibility
$conn->query("CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_code VARCHAR(50) UNIQUE NOT NULL,
    code VARCHAR(50) NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    min_amount DECIMAL(10,2) DEFAULT 0,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT 0,
    usage_limit INT DEFAULT 0,
    used_count INT DEFAULT 0,
    expiry_date DATE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check and normalize columns so both old and new column names exist
$col_check = $conn->query("SHOW COLUMNS FROM coupons");
$existing_cols = [];
if ($col_check) {
    while ($col = $col_check->fetch_assoc()) {
        $existing_cols[$col['Field']] = true;
    }
}
if (!isset($existing_cols['code']) && isset($existing_cols['coupon_code'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN code VARCHAR(50) NULL");
    $conn->query("UPDATE coupons SET code = coupon_code WHERE code IS NULL");
}
if (!isset($existing_cols['coupon_code']) && isset($existing_cols['code'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN coupon_code VARCHAR(50) NULL");
    $conn->query("UPDATE coupons SET coupon_code = code WHERE coupon_code IS NULL");
}
if (!isset($existing_cols['is_active']) && isset($existing_cols['active'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    $conn->query("UPDATE coupons SET is_active = active WHERE is_active IS NULL");
}
if (!isset($existing_cols['active']) && isset($existing_cols['is_active'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN active TINYINT(1) DEFAULT 1");
    $conn->query("UPDATE coupons SET active = is_active WHERE active IS NULL");
}
if (!isset($existing_cols['min_amount']) && isset($existing_cols['min_order_amount'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN min_amount DECIMAL(10,2) DEFAULT 0");
    $conn->query("UPDATE coupons SET min_amount = min_order_amount WHERE min_amount IS NULL");
}
if (!isset($existing_cols['min_order_amount']) && isset($existing_cols['min_amount'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN min_order_amount DECIMAL(10,2) DEFAULT 0");
    $conn->query("UPDATE coupons SET min_order_amount = min_amount WHERE min_order_amount IS NULL");
}
if (!isset($existing_cols['max_uses']) && isset($existing_cols['usage_limit'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN max_uses INT DEFAULT 0");
    $conn->query("UPDATE coupons SET max_uses = usage_limit WHERE max_uses IS NULL");
}
if (!isset($existing_cols['usage_limit']) && isset($existing_cols['max_uses'])) {
    $conn->query("ALTER TABLE coupons ADD COLUMN usage_limit INT DEFAULT 0");
    $conn->query("UPDATE coupons SET usage_limit = max_uses WHERE usage_limit IS NULL");
}

// 2. Auto-seed GEAR50 if it doesn't exist yet
$seedCheck = $conn->query("SELECT id FROM coupons WHERE code = 'GEAR50' OR coupon_code = 'GEAR50'");
if ($seedCheck && $seedCheck->num_rows === 0) {
    $conn->query("INSERT INTO coupons (coupon_code, code, discount_type, discount_value, min_amount, min_order_amount, max_uses, usage_limit, expiry_date, description, is_active, active) 
                  VALUES ('GEAR50', 'GEAR50', 'percentage', 50.00, 0, 0, 1000, 1000, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Special 50% Promo Discount', 1, 1)");
}

// Also seed WELCOME10 if missing
$wCheck = $conn->query("SELECT id FROM coupons WHERE code = 'WELCOME10' OR coupon_code = 'WELCOME10'");
if ($wCheck && $wCheck->num_rows === 0) {
    $conn->query("INSERT INTO coupons (coupon_code, code, discount_type, discount_value, min_amount, min_order_amount, max_uses, usage_limit, expiry_date, description, is_active, active) 
                  VALUES ('WELCOME10', 'WELCOME10', 'percentage', 10.00, 0, 0, 1000, 1000, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), '10% Welcome Discount', 1, 1)");
}

// 3. Find matching coupon
$stmt = $conn->prepare("SELECT * FROM coupons WHERE (code = ? OR coupon_code = ?) LIMIT 1");
$stmt->bind_param("ss", $coupon_code, $coupon_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid coupon code']);
    exit;
}

$coupon = $result->fetch_assoc();

// 4. Validate Status
$isActive = 1;
if (isset($coupon['is_active'])) {
    $isActive = (int)$coupon['is_active'];
} elseif (isset($coupon['active'])) {
    $isActive = (int)$coupon['active'];
}
if ($isActive !== 1) {
    echo json_encode(['success' => false, 'message' => 'This coupon is currently inactive']);
    exit;
}

// 5. Validate Expiry
if (!empty($coupon['expiry_date']) && $coupon['expiry_date'] !== '0000-00-00') {
    $expiryTime = strtotime($coupon['expiry_date'] . ' 23:59:59');
    if ($expiryTime < time()) {
        echo json_encode(['success' => false, 'message' => 'This coupon has expired on ' . date('M d, Y', $expiryTime)]);
        exit;
    }
}

// 6. Validate Usage Limit
$maxUses = intval($coupon['max_uses'] ?? ($coupon['usage_limit'] ?? 0));
$usedCount = intval($coupon['used_count'] ?? 0);
if ($maxUses > 0 && $usedCount >= $maxUses) {
    echo json_encode(['success' => false, 'message' => 'Coupon usage limit has been reached']);
    exit;
}

// 7. Validate Minimum Order Amount
$minAmount = floatval($coupon['min_amount'] ?? ($coupon['min_order_amount'] ?? 0));
if ($minAmount > 0 && $order_amount < $minAmount) {
    echo json_encode(['success' => false, 'message' => 'Minimum order amount of Rs. ' . number_format($minAmount, 2) . ' required for this coupon']);
    exit;
}

// 8. Calculate Discount
$discountType = strtolower($coupon['discount_type'] ?? 'percentage');
$discountValue = floatval($coupon['discount_value'] ?? 0);
$discountAmount = 0.0;

if ($discountType === 'percentage') {
    $discountAmount = ($order_amount * $discountValue) / 100.0;
    if (!empty($coupon['max_discount']) && floatval($coupon['max_discount']) > 0) {
        $maxDisc = floatval($coupon['max_discount']);
        if ($discountAmount > $maxDisc) {
            $discountAmount = $maxDisc;
        }
    }
} else {
    // Fixed amount
    $discountAmount = $discountValue;
}

// Never exceed the order amount
if ($discountAmount > $order_amount) {
    $discountAmount = $order_amount;
}

// Save applied coupon to session
$_SESSION['applied_coupon'] = [
    'id' => $coupon['id'],
    'code' => $coupon['code'] ?? $coupon['coupon_code'],
    'type' => $discountType,
    'value' => $discountValue,
    'discount_amount' => round($discountAmount, 2)
];

echo json_encode([
    'success' => true,
    'coupon' => $coupon,
    'discount_amount' => round($discountAmount, 2),
    'discount_formatted' => number_format($discountAmount, 2),
    'new_subtotal' => round(max(0, $order_amount - $discountAmount), 2),
    'message' => 'Coupon ' . htmlspecialchars($coupon_code) . ' applied successfully!'
]);