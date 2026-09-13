<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function debugLog($message) {
    error_log(date('Y-m-d H:i:s') . " - ADD_TO_CART DEBUG: " . $message);
}

debugLog("Script started");

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    debugLog("User not logged in");
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
        exit;
    } else {
        header('Location: ../site/login.php');
        exit;
    }
}

debugLog("User logged in: " . $_SESSION['userid']);

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    } else {
        header('Location: ../site/products.php');
        exit;
    }
}

$user_id = $_SESSION['userid'];
$product_id = sanitizeInput($_POST['product_id'] ?? '');
$size = sanitizeInput($_POST['size'] ?? 'Standard');
$quantity = (int)($_POST['quantity'] ?? 1);
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

debugLog("POST data - Product ID: $product_id, Size: $size, Quantity: $quantity, IsAjax: " . ($isAjax ? 'true' : 'false'));

// Validate inputs
if (empty($product_id) || $quantity <= 0) {
    $message = 'Invalid product or quantity';
    debugLog("Validation failed: $message");
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    } else {
        $_SESSION['error_message'] = $message;
        header('Location: ../site/products.php');
        exit;
    }
}

try {
    debugLog("Starting database operations");
    
    // Check database connection
    if (!$conn) {
        debugLog("Database connection failed");
        throw new Exception("Database connection failed");
    }
    
    // Get product details
    $productQuery = "SELECT * FROM production WHERE pid = ? AND approve = 1 AND status = 'active'";
    $productStmt = $conn->prepare($productQuery);
    
    if (!$productStmt) {
        debugLog("Failed to prepare product query: " . $conn->error);
        throw new Exception("Database query preparation failed");
    }
    
    $productStmt->bind_param("s", $product_id);
    $productStmt->execute();
    $productResult = $productStmt->get_result();

    debugLog("Product query executed, rows found: " . $productResult->num_rows);

    if ($productResult->num_rows === 0) {
        $message = 'Product not found or not available';
        debugLog("Product not found: $product_id");
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        } else {
            $_SESSION['error_message'] = $message;
            header('Location: ../site/products.php');
            exit;
        }
    }

    $product = $productResult->fetch_assoc();
    debugLog("Product found: " . $product['pname']);

    // Get price and check stock based on size
    $price = $product['price'];
    $availableStock = $product['qty'];

    if ($size !== 'Standard') {
        debugLog("Checking size-specific pricing for size: $size");
        $sizeQuery = "SELECT price, qty FROM productsize WHERE pid = ? AND size = ?";
        $sizeStmt = $conn->prepare($sizeQuery);
        
        if (!$sizeStmt) {
            debugLog("Failed to prepare size query: " . $conn->error);
            throw new Exception("Size query preparation failed");
        }
        
        $sizeStmt->bind_param("ss", $product_id, $size);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();

        if ($sizeResult->num_rows > 0) {
            $sizeData = $sizeResult->fetch_assoc();
            $price = $sizeData['price'];
            $availableStock = $sizeData['qty'];
            debugLog("Size found - Price: $price, Stock: $availableStock");
        } else {
            $message = 'Selected size not available';
            debugLog("Size not available: $size");
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            } else {
                $_SESSION['error_message'] = $message;
                header('Location: ../site/products.php');
                exit;
            }
        }
    }

    // Check stock availability
    if ($quantity > $availableStock) {
        $message = "Only $availableStock items available in stock";
        debugLog("Insufficient stock - Requested: $quantity, Available: $availableStock");
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        } else {
            $_SESSION['error_message'] = $message;
            header('Location: ../site/products.php');
            exit;
        }
    }

    // Check if item already exists in cart
    debugLog("Checking existing cart items");
    $checkCartQuery = "SELECT Id, Qty FROM cart WHERE Userid = ? AND PID = ? AND Size = ?";
    $checkCartStmt = $conn->prepare($checkCartQuery);
    
    if (!$checkCartStmt) {
        debugLog("Failed to prepare cart check query: " . $conn->error);
        throw new Exception("Cart check query preparation failed");
    }
    
    $checkCartStmt->bind_param("sss", $user_id, $product_id, $size);
    $checkCartStmt->execute();
    $checkCartResult = $checkCartStmt->get_result();

    debugLog("Cart check completed, existing items: " . $checkCartResult->num_rows);

    if ($checkCartResult->num_rows > 0) {
        // Update existing cart item
        debugLog("Updating existing cart item");
        $existingItem = $checkCartResult->fetch_assoc();
        $newQuantity = $existingItem['Qty'] + $quantity;

        // Check if new quantity exceeds stock
        if ($newQuantity > $availableStock) {
            $maxAddable = $availableStock - $existingItem['Qty'];
            if ($maxAddable <= 0) {
                $message = 'This item is already at maximum quantity in your cart';
            } else {
                $message = "You can only add $maxAddable more of this item";
            }
            
            debugLog("Quantity limit exceeded: $message");
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            } else {
                $_SESSION['error_message'] = $message;
                header('Location: ../site/products.php');
                exit;
            }
        }

        $updateQuery = "UPDATE cart SET Qty = ?, AddedAt = NOW() WHERE Id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        if (!$updateStmt) {
            debugLog("Failed to prepare update query: " . $conn->error);
            throw new Exception("Update query preparation failed");
        }
        
        $updateStmt->bind_param("ii", $newQuantity, $existingItem['Id']);
        
        if ($updateStmt->execute()) {
            $message = 'Cart updated successfully!';
            $success = true;
            debugLog("Cart updated successfully - New quantity: $newQuantity");
        } else {
            $message = 'Failed to update cart';
            $success = false;
            debugLog("Failed to update cart: " . $updateStmt->error);
        }
    } else {
        // Add new item to cart
        debugLog("Adding new item to cart");
        $insertQuery = "INSERT INTO cart (Userid, PID, Size, Qty, AddedAt) VALUES (?, ?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertQuery);
        
        if (!$insertStmt) {
            debugLog("Failed to prepare insert query: " . $conn->error);
            throw new Exception("Insert query preparation failed");
        }
        
        $insertStmt->bind_param("sssi", $user_id, $product_id, $size, $quantity);
        
        if ($insertStmt->execute()) {
            $message = 'Product added to cart successfully!';
            $success = true;
            debugLog("Product added to cart successfully");
        } else {
            $message = 'Failed to add product to cart';
            $success = false;
            debugLog("Failed to add product to cart: " . $insertStmt->error);
        }
    }

    // Return response
    if ($isAjax) {
        debugLog("Preparing AJAX response");
        
        // Check if getCartCount function exists
        if (function_exists('getCartCount')) {
            $cartCount = getCartCount($conn, $user_id);
            debugLog("Cart count retrieved: $cartCount");
        } else {
            $cartCount = 0;
            debugLog("getCartCount function not found, using 0");
        }
        
        $response = [
            'success' => $success, 
            'message' => $message,
            'cart_count' => $cartCount
        ];
        
        debugLog("Sending response: " . json_encode($response));
        echo json_encode($response);
    } else {
        debugLog("Handling non-AJAX response");
        if ($success) {
            $_SESSION['success_message'] = $message;
        } else {
            $_SESSION['error_message'] = $message;
        }
        
        if (isset($_POST['redirect_to_cart'])) {
            header('Location: ../site/checkout.php');
        } else {
            header('Location: ../site/products.php');
        }
    }

} catch (Exception $e) {
    $message = 'An error occurred: ' . $e->getMessage();
    debugLog("Exception caught: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
    
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred', 'debug' => $e->getMessage()]);
    } else {
        $_SESSION['error_message'] = 'Database error occurred';
        header('Location: ../site/products.php');
    }
}

debugLog("Script completed");
?>