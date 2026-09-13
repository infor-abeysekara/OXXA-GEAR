<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['userid'];
$item_id = (int)($_POST['item_id'] ?? 0);
$change = (int)($_POST['change'] ?? 0);

// Validate inputs
if ($item_id <= 0 || $change == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Get current cart item
    $query = "SELECT c.Id, c.PID, c.Qty, c.Size, c.Userid,
                     p.price, p.qty as stock_qty
              FROM cart c 
              JOIN production p ON c.PID = p.pid 
              WHERE c.Id = ? AND c.Userid = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
        exit;
    }
    
    $item = $result->fetch_assoc();
    $currentQty = $item['Qty'];
    $newQty = $currentQty + $change;
    
    // Get price and stock for the specific size
    $price = $item['price'];
    $availableStock = $item['stock_qty'];
    
    if ($item['Size'] !== 'Standard') {
        $sizeQuery = "SELECT price, qty FROM productsize WHERE pid = ? AND size = ?";
        $sizeStmt = $conn->prepare($sizeQuery);
        $sizeStmt->bind_param("ss", $item['PID'], $item['Size']);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();
        
        if ($sizeResult->num_rows > 0) {
            $sizeData = $sizeResult->fetch_assoc();
            $price = $sizeData['price'];
            $availableStock = $sizeData['qty'];
        }
    }
    
    // Check if item should be removed (quantity becomes 0 or less)
    if ($newQty <= 0) {
        $deleteQuery = "DELETE FROM cart WHERE Id = ? AND Userid = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("is", $item_id, $user_id);
        
        if ($deleteStmt->execute()) {
            $cartCount = getCartCount($conn, $user_id);
            echo json_encode([
                'success' => true,
                'removed' => true,
                'cart_count' => $cartCount,
                'message' => 'Item removed from cart'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
        }
        exit;
    }
    
    // Check stock availability
    if ($newQty > $availableStock) {
        echo json_encode([
            'success' => false, 
            'message' => "Only $availableStock items available in stock"
        ]);
        exit;
    }
    
    // Update quantity
    $updateQuery = "UPDATE cart SET Qty = ?, AddedAt = NOW() WHERE Id = ? AND Userid = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("iis", $newQty, $item_id, $user_id);
    
    if ($updateStmt->execute()) {
        $cartCount = getCartCount($conn, $user_id);
        $itemTotal = number_format($price * $newQty, 2);
        
        echo json_encode([
            'success' => true,
            'new_quantity' => $newQty,
            'item_total' => $itemTotal,
            'cart_count' => $cartCount,
            'message' => 'Cart updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>