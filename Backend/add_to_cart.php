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
$variant_id = sanitizeInput($_POST['variant_id'] ?? '');
$size = sanitizeInput($_POST['size'] ?? 'Standard');
$quantity = (int)($_POST['quantity'] ?? 1);
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

debugLog("POST data - Product ID: $product_id, Variant ID: $variant_id, Size: $size, Quantity: $quantity, IsAjax: " . ($isAjax ? 'true' : 'false'));

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
    $productQuery = "SELECT * FROM products WHERE id = ? AND is_approved = 1 AND status = 'active'";
    $productStmt = $conn->prepare($productQuery);
    
    if (!$productStmt) {
        debugLog("Failed to prepare product query: " . $conn->error);
        throw new Exception("Database query preparation failed");
    }
    
    // id is INT, but bind_param "s" will cast to int, better to use "i"
    $productStmt->bind_param("i", $product_id);
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
    debugLog("Product found: " . $product['name']);

    // Get price and check stock based on size or variant
    $price = $product['base_price'];
    $availableStock = $product['total_qty'];

    if (!empty($variant_id)) {
        debugLog("Checking variant-specific pricing for variant: $variant_id");
        $sizeQuery = "SELECT cs.selling_price as price, cs.qty FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE cs.id = ? AND pc.product_id = ?";
        $sizeStmt = $conn->prepare($sizeQuery);
        $sizeStmt->bind_param("ii", $variant_id, $product_id);
    } elseif ($size !== 'Standard') {
        debugLog("Checking size-specific pricing for size: $size");
        $sizeQuery = "SELECT cs.selling_price as price, cs.qty FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = ? AND cs.size = ?";
        $sizeStmt = $conn->prepare($sizeQuery);
        $sizeStmt->bind_param("is", $product_id, $size);
    }

    if (isset($sizeStmt)) {
        if (!$sizeStmt) {
            debugLog("Failed to prepare variant query: " . $conn->error);
            throw new Exception("Variant query preparation failed");
        }
        
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();

        if ($sizeResult->num_rows > 0) {
            $sizeData = $sizeResult->fetch_assoc();
            $price = $sizeData['price'];
            $availableStock = $sizeData['qty'];
            debugLog("Variant found - Price: $price, Stock: $availableStock");
        } else {
            $message = 'Selected variant not available';
            debugLog("Variant not available");
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
    if (!empty($variant_id)) {
        $checkCartQuery = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND variant_id = ?";
        $checkCartStmt = $conn->prepare($checkCartQuery);
        if (!$checkCartStmt) {
            debugLog("Failed to prepare cart check query: " . $conn->error);
            throw new Exception("Cart check query preparation failed");
        }
        $checkCartStmt->bind_param("iii", $user_id, $product_id, $variant_id);
    } else {
        $checkCartQuery = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND variant_id = (SELECT cs.id FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = ? AND cs.size = ? LIMIT 1)";
        $checkCartStmt = $conn->prepare($checkCartQuery);
        if (!$checkCartStmt) {
            debugLog("Failed to prepare cart check query: " . $conn->error);
            throw new Exception("Cart check query preparation failed");
        }
        $checkCartStmt->bind_param("iiis", $user_id, $product_id, $product_id, $size);
    }
    
    $checkCartStmt->execute();
    $checkCartResult = $checkCartStmt->get_result();

    debugLog("Cart check completed, existing items: " . $checkCartResult->num_rows);

    if ($checkCartResult->num_rows > 0) {
        // Update existing cart item
        debugLog("Updating existing cart item");
        $existingItem = $checkCartResult->fetch_assoc();
        $newQuantity = $existingItem['quantity'] + $quantity;

        // Check if new quantity exceeds stock
        if ($newQuantity > $availableStock) {
            $maxAddable = $availableStock - $existingItem['quantity'];
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

        $updateQuery = "UPDATE cart SET quantity = ?, added_at = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        if (!$updateStmt) {
            debugLog("Failed to prepare update query: " . $conn->error);
            throw new Exception("Update query preparation failed");
        }
        
        $updateStmt->bind_param("ii", $newQuantity, $existingItem['id']);
        
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
        
        if (!empty($variant_id)) {
            $insertQuery = "INSERT INTO cart (user_id, product_id, variant_id, quantity, added_at) VALUES (?, ?, ?, ?, NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            if (!$insertStmt) {
                debugLog("Failed to prepare insert query: " . $conn->error);
                throw new Exception("Insert query preparation failed");
            }
            $insertStmt->bind_param("iiii", $user_id, $product_id, $variant_id, $quantity);
        } else {
            $insertQuery = "INSERT INTO cart (user_id, product_id, variant_id, quantity, added_at) VALUES (?, ?, (SELECT cs.id FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = ? AND cs.size = ? LIMIT 1), ?, NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            if (!$insertStmt) {
                debugLog("Failed to prepare insert query: " . $conn->error);
                throw new Exception("Insert query preparation failed");
            }
            $insertStmt->bind_param("iiisi", $user_id, $product_id, $product_id, $size, $quantity);
        }
        
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