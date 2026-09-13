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
    error_log(date('Y-m-d H:i:s') . " - REMOVE_FROM_CART DEBUG: " . $message);
}

debugLog("Remove from cart script started");

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
$userId = $_SESSION['userid'];

debugLog("Processing removal - Item ID: $itemId, User ID: $userId");

// Validate item ID
if ($itemId <= 0) {
    debugLog("Invalid item ID: $itemId");
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

try {
    // Check database connection
    if (!$conn) {
        debugLog("Database connection failed");
        throw new Exception("Database connection failed");
    }

    // Verify item belongs to user before deletion
    $verifyQuery = "SELECT Id, PID, Qty FROM cart WHERE Id = ? AND Userid = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    
    if (!$verifyStmt) {
        debugLog("Failed to prepare verify query: " . $conn->error);
        throw new Exception("Database query preparation failed");
    }
    
    $verifyStmt->bind_param("is", $itemId, $userId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows === 0) {
        debugLog("Item not found or access denied - Item ID: $itemId");
        echo json_encode(['success' => false, 'message' => 'Item not found or access denied']);
        exit;
    }
    
    $itemData = $verifyResult->fetch_assoc();
    debugLog("Item found - PID: " . $itemData['PID'] . ", Qty: " . $itemData['Qty']);
    
    // Delete the item
    $deleteQuery = "DELETE FROM cart WHERE Id = ? AND Userid = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    
    if (!$deleteStmt) {
        debugLog("Failed to prepare delete query: " . $conn->error);
        throw new Exception("Delete query preparation failed");
    }
    
    $deleteStmt->bind_param("is", $itemId, $userId);
    
    if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
        debugLog("Item successfully deleted");
        
        // Get updated cart count
        $cartCount = 0;
        if (function_exists('getCartCount')) {
            $cartCount = getCartCount($conn, $userId);
            debugLog("Cart count retrieved: $cartCount");
        } else {
            // Fallback cart count calculation
            $countQuery = "SELECT COUNT(*) as count FROM cart WHERE Userid = ?";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->bind_param("s", $userId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $cartCount = $countResult->fetch_assoc()['count'];
            debugLog("Cart count calculated: $cartCount");
        }
        
        $response = [
            'success' => true, 
            'message' => 'Item removed successfully', 
            'cart_count' => $cartCount,
            'removed_item_id' => $itemId
        ];
        
        debugLog("Sending success response: " . json_encode($response));
        echo json_encode($response);
        
    } else {
        debugLog("Failed to delete item - affected rows: " . $deleteStmt->affected_rows);
        echo json_encode(['success' => false, 'message' => 'Failed to remove item from database']);
    }
    
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
    debugLog("Exception caught: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $message]);
}

debugLog("Remove from cart script completed");
?>