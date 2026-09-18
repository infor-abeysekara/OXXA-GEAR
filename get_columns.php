<?php
require_once 'include/connection.php';
error_reporting(E_ALL); ini_set('display_errors', 1);

$q = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.phone, u.profile_image, 
           u.user_type, u.is_approved, u.created_at,
           sp.business_name, sp.business_type, sp.is_approved as business_approved,
           (SELECT COUNT(*) FROM products WHERE seller_id = u.id) as total_products,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as buyer_orders,
           (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status = 'delivered') as total_spent,
           (SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = u.id) as seller_orders,
           (SELECT SUM(oi.unit_price * oi.quantity) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.seller_id = u.id AND o.status = 'delivered') as total_earned
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    ORDER BY u.id DESC";

$stmt = $conn->prepare($q);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while($r = $res->fetch_assoc()) $data[] = $r;
echo json_encode($data, JSON_PRETTY_PRINT);
?>
