<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to place order']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['userid'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'place_order') {
        // Get form data
        $customerName = sanitizeInput($_POST['customerName'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $postalCode = sanitizeInput($_POST['postalCode'] ?? '');
        $province = sanitizeInput($_POST['province'] ?? '');
        $contact1 = sanitizeInput($_POST['contact1'] ?? '');
        $contact2 = sanitizeInput($_POST['contact2'] ?? '');
        $deliveryMethod = sanitizeInput($_POST['deliveryMethod'] ?? 'Speed Post');
        $paymentMethod = sanitizeInput($_POST['paymentMethod'] ?? 'COD');
        $couponCode = sanitizeInput($_POST['coupon_code'] ?? '');
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);
        $finalTotal = floatval($_POST['final_total'] ?? 0);

        // Validate required fields
        if (empty($customerName) || empty($address) || empty($contact1) || empty($paymentMethod)) {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
            exit;
        }

        // Get cart items
        $cartItems = getCartItems($conn, $user_id);
        if (empty($cartItems)) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
            exit;
        }

        // Generate order ID
        $orderId = generateOrderId();

        // Start transaction
        $conn->autocommit(false);

        try {
            // Process each cart item and create orders
            foreach ($cartItems as $item) {
                // Get seller ID for this product
                $sellerQuery = "SELECT user_id FROM production WHERE pid = ?";
                $sellerStmt = $conn->prepare($sellerQuery);
                $sellerStmt->bind_param("s", $item['pid']);
                $sellerStmt->execute();
                $sellerResult = $sellerStmt->get_result();
                $seller = $sellerResult->fetch_assoc();
                $sellerId = $seller['user_id'] ?? '';

                // Insert into ordertable using only existing columns
                $insertOrderQuery = "INSERT INTO ordertable (
                    orderid, user_id, pid, size, qty, price, 
                    payment_method, status, orderdate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

                $insertOrderStmt = $conn->prepare($insertOrderQuery);
                $insertOrderStmt->bind_param(
                    "ssssids",
                    $orderId,
                    $user_id,
                    $item['pid'],
                    $item['size'],
                    $item['qty'],
                    $item['price'],
                    $paymentMethod
                );

                if (!$insertOrderStmt->execute()) {
                    throw new Exception("Failed to create order for product: " . $item['pname']);
                }

                // Update product stock
                if ($item['size'] === 'Standard' || empty($item['size'])) {
                    // Update main product stock
                    $updateStockQuery = "UPDATE production SET qty = qty - ? WHERE pid = ? AND qty >= ?";
                    $updateStockStmt = $conn->prepare($updateStockQuery);
                    $updateStockStmt->bind_param("isi", $item['qty'], $item['pid'], $item['qty']);
                } else {
                    // Update size-specific stock
                    $updateStockQuery = "UPDATE productsize SET qty = qty - ? WHERE pid = ? AND size = ? AND qty >= ?";
                    $updateStockStmt = $conn->prepare($updateStockQuery);
                    $updateStockStmt->bind_param("issi", $item['qty'], $item['pid'], $item['size'], $item['qty']);
                }

                if (!$updateStockStmt->execute() || $updateStockStmt->affected_rows === 0) {
                    throw new Exception("Insufficient stock for product: " . $item['pname']);
                }

                // Add notification to seller if seller exists
                if (!empty($sellerId)) {
                    $message = "New order #{$orderId} received for {$item['pname']} (Qty: {$item['qty']})";
                    addNotification($conn, $sellerId, $message, 'order');
                }
            }

            // Clear cart after successful order placement
            $clearCartQuery = "DELETE FROM cart WHERE Userid = ?";
            $clearCartStmt = $conn->prepare($clearCartQuery);
            $clearCartStmt->bind_param("s", $user_id);

            if (!$clearCartStmt->execute()) {
                throw new Exception("Failed to clear cart");
            }

            // Commit transaction
            $conn->commit();
            $conn->autocommit(true);

            // Add notification to buyer
            $buyerMessage = "Your order #{$orderId} has been placed successfully. Total: Rs. " . number_format($finalTotal, 2);
            addNotification($conn, $user_id, $buyerMessage, 'success');

            echo json_encode([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_id' => $orderId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(true);
            throw $e;
        }
    } elseif ($action === 'prepare_payment') {
        // For PayHere payment preparation
        echo json_encode([
            'success' => true,
            'order_id' => generateOrderId(),
            'amount' => $_POST['final_total']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
