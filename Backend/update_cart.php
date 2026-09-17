<?php
session_start();
include('../include/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to update cart']);
    exit;
}

$user_id = $_SESSION['userid'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action']) || !isset($data['cart_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$action = $data['action'];
$cart_id = (int)$data['cart_id'];
$qty = isset($data['qty']) ? (int)$data['qty'] : 0;

try {
    if ($action === 'remove') {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } else if ($action === 'update') {
        if ($qty <= 0) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
        } else {
            // Check stock first
            $stmt = $conn->prepare("
                SELECT c.product_id, c.variant_id, 
                       p.total_qty as product_stock, pv.qty as variant_stock
                FROM cart c
                JOIN products p ON c.product_id = p.id
                LEFT JOIN product_variants pv ON c.variant_id = pv.id
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $max_stock = isset($row['variant_stock']) ? $row['variant_stock'] : $row['product_stock'];
                
                if ($qty > $max_stock) {
                    echo json_encode(['success' => false, 'message' => 'Not enough stock']);
                    exit;
                }
                
                $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $update->bind_param("iii", $qty, $cart_id, $user_id);
                $update->execute();
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>