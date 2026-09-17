<?php
session_start();
include('../include/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => true, 'items' => [], 'subtotal' => 0, 'count' => 0]);
    exit;
}

$user_id = $_SESSION['userid'];

try {
    $query = "
        SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.base_price, 
               cs.id as variant_id, cs.size, pc.color_name as color, cs.selling_price as variant_price, cs.qty as stock,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) as image
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN color_sizes cs ON c.variant_id = cs.id
        LEFT JOIN product_colors pc ON cs.color_id = pc.id
        WHERE c.user_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    $subtotal = 0;
    $count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $price = (!empty($row['variant_price']) && $row['variant_price'] > 0) ? $row['variant_price'] : $row['base_price'];
        $itemTotal = $price * $row['quantity'];
        $subtotal += $itemTotal;
        $count += $row['quantity'];
        
        $items[] = [
            'cart_id' => $row['cart_id'],
            'product_id' => $row['product_id'],
            'name' => $row['name'],
            'image' => $row['image'] ? '../assets/uploads/products/' . $row['image'] : '../image/placeholder.png',
            'price' => $price,
            'quantity' => $row['quantity'],
            'stock' => $row['stock'] ?? 999, // Fallback if no variant
            'size' => $row['size'],
            'color' => $row['color'],
            'variant_label' => ($row['size'] || $row['color']) ? trim($row['size'] . ' ' . $row['color']) : 'Standard',
            'item_total' => $itemTotal
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'items' => $items, 
        'subtotal' => $subtotal, 
        'count' => $count
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
