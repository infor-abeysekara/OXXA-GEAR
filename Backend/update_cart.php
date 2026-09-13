<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function debugLog($message) {
    error_log(date('Y-m-d H:i:s') . " - UPDATE_CART DEBUG: " . $message);
}

debugLog("Update cart script started");

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    debugLog("User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$itemId = intval($_POST['item_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$userId = $_SESSION['userid'];

debugLog("Processing update - Item ID: $itemId, New Quantity: $quantity, User ID: $userId");

// Validate parameters
if ($itemId <= 0) {
    debugLog("Invalid item ID: $itemId");
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

if ($quantity < 0) {
    debugLog("Invalid quantity: $quantity");
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit;
}

try {
    // Check database connection
    if (!$conn) {
        debugLog("Database connection failed");
        throw new Exception("Database connection failed");
    }

    // If quantity is 0, remove the item
    if ($quantity === 0) {
        debugLog("Quantity is 0, removing item");
        
        $deleteQuery = "DELETE FROM cart WHERE Id = ? AND Userid = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        
        if (!$deleteStmt) {
            debugLog("Failed to prepare delete query: " . $conn->error);
            throw new Exception("Delete query preparation failed");
        }
        
        $deleteStmt->bind_param("is", $itemId, $userId);
        
        if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
            // Get updated cart count
            $cartCount = 0;
            if (function_exists('getCartCount')) {
                $cartCount = getCartCount($conn, $userId);
            } else {
                $countQuery = "SELECT COUNT(*) as count FROM cart WHERE Userid = ?";
                $countStmt = $conn->prepare($countQuery);
                $countStmt->bind_param("s", $userId);
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $cartCount = $countResult->fetch_assoc()['count'];
            }
            
            debugLog("Item removed successfully, cart count: $cartCount");
            echo json_encode([
                'success' => true, 
                'message' => 'Item removed from cart', 
                'cart_count' => $cartCount,
                'removed' => true
            ]);
        } else {
            debugLog("Failed to remove item");
            echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
        }
        exit;
    }

    // Get current cart item with product details
    $checkQuery = "SELECT c.Id, c.PID, c.Qty, c.Size, c.Userid,
                          p.pname, p.price, p.qty as base_qty, p.brand
                   FROM cart c 
                   JOIN production p ON c.PID = p.pid 
                   WHERE c.Id = ? AND c.Userid = ?";
    
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        debugLog("Failed to prepare check query: " . $conn->error);
        throw new Exception("Check query preparation failed");
    }
    
    $checkStmt->bind_param("is", $itemId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        debugLog("Item not found in cart");
        echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
        exit;
    }
    
    $cartItem = $checkResult->fetch_assoc();
    debugLog("Item found - PID: " . $cartItem['PID'] . ", Current Qty: " . $cartItem['Qty'] . ", Size: " . $cartItem['Size']);
    
    // Get current price and stock availability
    $currentPrice = $cartItem['price'];
    $availableStock = $cartItem['base_qty'];
    
    // Check for size-specific pricing and stock
    if ($cartItem['Size'] !== 'Standard') {
        debugLog("Checking size-specific data for size: " . $cartItem['Size']);
        $sizeQuery = "SELECT price, qty FROM productsize WHERE pid = ? AND size = ?";
        $sizeStmt = $conn->prepare($sizeQuery);
        
        if (!$sizeStmt) {
            debugLog("Failed to prepare size query: " . $conn->error);
            throw new Exception("Size query preparation failed");
        }
        
        $sizeStmt->bind_param("ss", $cartItem['PID'], $cartItem['Size']);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();
        
        if ($sizeResult->num_rows > 0) {
            $sizeData = $sizeResult->fetch_assoc();
            $currentPrice = $sizeData['price'];
            $availableStock = $sizeData['qty'];
            debugLog("Size data found - Price: $currentPrice, Stock: $availableStock");
        } else {
            debugLog("Size data not found, using base product data");
        }
    }
    
    // Check stock availability
    if ($quantity > $availableStock) {
        debugLog("Insufficient stock - Requested: $quantity, Available: $availableStock");
        echo json_encode([
            'success' => false, 
            'message' => "Only $availableStock items available in stock"
        ]);
        exit;
    }
    
    // Update quantity
    $updateQuery = "UPDATE cart SET Qty = ?, AddedAt = NOW() WHERE Id = ? AND Userid = ?";
    $updateStmt = $conn->prepare($updateQuery);
    
    if (!$updateStmt) {
        debugLog("Failed to prepare update query: " . $conn->error);
        throw new Exception("Update query preparation failed");
    }
    
    $updateStmt->bind_param("iis", $quantity, $itemId, $userId);
    
    if ($updateStmt->execute()) {
        // Get updated cart count
        $cartCount = 0;
        if (function_exists('getCartCount')) {
            $cartCount = getCartCount($conn, $userId);
        } else {
            $countQuery = "SELECT COUNT(*) as count FROM cart WHERE Userid = ?";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->bind_param("s", $userId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $cartCount = $countResult->fetch_assoc()['count'];
        }
        
        $itemTotal = $currentPrice * $quantity;
        
        $response = [
            'success' => true, 
            'message' => 'Cart updated successfully', 
            'cart_count' => $cartCount,
            'new_quantity' => $quantity,
            'item_total' => number_format($itemTotal, 2),
            'unit_price' => $currentPrice,
            'removed' => false
        ];
        
        debugLog("Update successful: " . json_encode($response));
        echo json_encode($response);
        
    } else {
        debugLog("Failed to update cart: " . $updateStmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
    }
    
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
    debugLog("Exception caught: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $message]);
}

debugLog("Update cart script completed");
?>