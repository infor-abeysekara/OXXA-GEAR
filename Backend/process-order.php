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

$user_id = $_SESSION['userid']; // This is now INT
$action = $_POST['action'] ?? '';

try {
    if ($action === 'place_order') {
        // Get form data
        $customerName = trim($_POST['customerName'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postalCode = trim($_POST['postalCode'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $city = trim($_POST['city'] ?? 'Colombo'); // Default to Colombo if not passed
        $contact1 = trim($_POST['contact1'] ?? '');
        $contact2 = trim($_POST['contact2'] ?? '');
        $paymentMethod = strtoupper(trim($_POST['paymentMethod'] ?? 'COD'));
        if ($paymentMethod === 'PAYHERE') {
            $paymentMethod = 'CARD';
        }
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $couponDiscount = floatval($_POST['discount_amount'] ?? 0);
        

        // Validate required fields
        if (empty($customerName) || empty($address) || empty($contact1) || empty($paymentMethod)) {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
            exit;
        }

        // Generate new Order Code (ORD-XXXX)
        $orderCode = 'ORD-' . date('Ymd') . rand(1000, 9999);

        // Fetch Cart Items from NEW structure
        $cartStmt = $pdo->prepare("
            SELECT c.product_id, c.variant_id, c.quantity,
                   p.name, p.base_price, p.cost_price, p.seller_id,
                   p.is_hot_deal, p.sale_price, p.original_price, p.hot_deal_status, p.hot_deal_expiry,
                   p.is_free_shipping, p.shipping_cost,
                   cs.size, cs.selling_price as variant_price,
                   COALESCE(
                       (SELECT ci.image_path FROM color_images ci WHERE ci.color_id = cs.color_id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1),
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1)
                   ) as image_path
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN color_sizes cs ON c.variant_id = cs.id
            WHERE c.user_id = ?
        ");
        $cartStmt->execute([$user_id]);
        $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cartItems)) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
            exit;
        }

        // Calculate Subtotal
        $subtotal = 0;
        foreach ($cartItems as &$item) {
            $basePrice = (!empty($item['variant_price']) && $item['variant_price'] > 0) ? (float)$item['variant_price'] : (float)$item['base_price'];
            $isHotDeal = ($item['is_hot_deal'] == 1 && $item['hot_deal_status'] === 'approved' && 
                          (empty($item['hot_deal_expiry']) || strtotime($item['hot_deal_expiry']) >= time()));
            
            if ($isHotDeal && !empty($item['sale_price']) && (float)$item['sale_price'] > 0) {
                $unitPrice = (float)$item['sale_price'];
            } else {
                $unitPrice = $basePrice;
            }
            $item['unit_price'] = $unitPrice;
            $item['total_price'] = $unitPrice * $item['quantity'];
            $subtotal += $item['total_price'];
        }
        
        $allFreeShipping = true;
        foreach ($cartItems as $item) {
            if (empty($item['is_free_shipping'])) {
                $allFreeShipping = false;
                break;
            }
        }
        
        $isFreeShipping = ($allFreeShipping && count($cartItems) > 0);
        $deliveryFee = ($subtotal > 0 && !$isFreeShipping) ? 300.00 : 0.00;

        $netBaseTotal = ($subtotal + $deliveryFee) - $couponDiscount;
        if ($netBaseTotal < 0) $netBaseTotal = 0;

        // Payment details & Gateway Fee
        $gatewayFee = 0.00;
        $paymentStatus = 'pending';
        $gatewayName = 'COD';
        $cardLast4 = null;
        $cardType = null;
        $kokoOrderId = null;
        $kokoInstallments = null;
        $bankSlipPath = null;
        $codCollected = 0;

        if ($paymentMethod === 'CARD') {
            $gatewayName = 'PayHere';
            $gatewayFee = round($netBaseTotal * 0.03, 2); // 3.0% gateway fee
            $paymentStatus = 'paid';
            $rawCardNum = preg_replace('/\s+/', '', $_POST['cardNumber'] ?? '4242424242424242');
            $cardLast4 = substr($rawCardNum, -4) ?: '4242';
            $firstDigit = substr($rawCardNum, 0, 1);
            $cardType = ($firstDigit === '5') ? 'Mastercard' : (($firstDigit === '3') ? 'Amex' : 'Visa');
        } elseif ($paymentMethod === 'KOKO') {
            $gatewayName = 'KOKO Pay in 3';
            $gatewayFee = round($netBaseTotal * 0.05, 2); // 5.0% gateway fee
            $paymentStatus = 'paid';
            $kokoOrderId = 'KOKO-' . date('Ymd') . rand(1000, 9999);
            $installmentAmount = round(($netBaseTotal + $gatewayFee) / 3, 2);
            $kokoInstallments = json_encode([
                ['installment' => 1, 'amount' => $installmentAmount, 'due' => date('Y-m-d'), 'status' => 'Paid Today'],
                ['installment' => 2, 'amount' => $installmentAmount, 'due' => date('Y-m-d', strtotime('+30 days')), 'status' => 'Upcoming (Month 1)'],
                ['installment' => 3, 'amount' => $installmentAmount, 'due' => date('Y-m-d', strtotime('+60 days')), 'status' => 'Upcoming (Month 2)']
            ]);
        } elseif ($paymentMethod === 'BANK') {
            $gatewayName = 'Bank Transfer';
            $gatewayFee = 0.00;
            $paymentStatus = 'pending_verification';

            // Check if slip is uploaded
            if (isset($_FILES['bankSlip']) && $_FILES['bankSlip']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../assets/uploads/slips/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $fileExt = pathinfo($_FILES['bankSlip']['name'], PATHINFO_EXTENSION);
                $slipFileName = 'slip_' . time() . '_' . rand(100, 999) . '.' . $fileExt;
                $targetFile = $uploadDir . $slipFileName;
                if (move_uploaded_file($_FILES['bankSlip']['tmp_name'], $targetFile)) {
                    $bankSlipPath = 'assets/uploads/slips/' . $slipFileName;
                }
            }
        } else {
            // COD
            $paymentMethod = 'COD';
            $gatewayName = 'COD';
            $gatewayFee = 0.00;
            $paymentStatus = 'pending';
            $codCollected = 0;
        }

        $totalAmount = $netBaseTotal + $gatewayFee;

        // DB Transaction
        $pdo->beginTransaction();

        try {
            // 1. Insert Shipping Address to user_addresses
            $addrStmt = $pdo->prepare("
                INSERT INTO user_addresses (user_id, full_name, address_line1, city, province, postal_code, phone1, phone2, is_default_shipping)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $addrStmt->execute([$user_id, $customerName, $address, $city, $province, $postalCode, $contact1, $contact2]);
            $addressId = $pdo->lastInsertId();

            // 2. Insert into orders (Header)
            $orderStmt = $pdo->prepare("
                INSERT INTO orders (
                    order_code, user_id, shipping_address_id, subtotal, delivery_fee, 
                    coupon_code, coupon_discount, total_amount, payment_method, 
                    payment_status, gateway, gateway_fee, card_last4, card_type, 
                    koko_order_id, koko_installments, cod_collected, bank_slip_path, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $orderStmt->execute([
                $orderCode, $user_id, $addressId, $subtotal, $deliveryFee, 
                $couponCode, $couponDiscount, $totalAmount, $paymentMethod,
                $paymentStatus, $gatewayName, $gatewayFee, $cardLast4, $cardType,
                $kokoOrderId, $kokoInstallments, $codCollected, $bankSlipPath
            ]);
            $orderId = $pdo->lastInsertId();

            // Calculate order-level discount ratio for proportional item distribution
            $discountRatio = $subtotal > 0 ? ($couponDiscount / $subtotal) : 0;

            // 3. Insert into order_items (Lines) and update stock
            foreach ($cartItems as $item) {
                // Calculate item level profit and fees
                $effective_unit_price = $item['unit_price'] * (1 - $discountRatio);
                $cost_price = floatval($item['cost_price']);
                
                $profit = $effective_unit_price - $cost_price;
                if ($profit < 0) {
                    $profit = 0;
                }
                
                $oxxa_fee = $profit * 0.10;
                // As per requirement: Cost price + Profit * 90%
                $seller_earning = $cost_price + ($profit * 0.90);
                
                // Insert line item
                $itemStmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_image, size, quantity, unit_price, total_price, cost_price, selling_price, profit, oxxa_fee, seller_earning, settlement_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $itemStmt->execute([
                    $orderId, 
                    $item['product_id'], 
                    $item['variant_id'], 
                    $item['name'], 
                    $item['image_path'], 
                    $item['size'] ?: 'Standard', 
                    $item['quantity'], 
                    $item['unit_price'], 
                    $item['total_price'],
                    $cost_price,
                    $effective_unit_price,
                    $profit,
                    $oxxa_fee,
                    $seller_earning
                ]);
                $orderItemId = $pdo->lastInsertId();

                // Record in seller_payouts for finance tracking
                try {
                    $itemGatewayFee = ($subtotal > 0 && $gatewayFee > 0) ? round(($item['total_price'] / $subtotal) * $gatewayFee, 2) : 0.00;
                    $sellerPayoutStatus = ($paymentStatus === 'paid') ? 'pending' : 'locked';
                    $spStmt = $pdo->prepare("
                        INSERT INTO seller_payouts (seller_id, order_item_id, selling_price, cost_price, profit, admin_commission, gateway_fee, seller_earning, payout_status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $spStmt->execute([
                        $item['seller_id'],
                        $orderItemId,
                        $item['total_price'],
                        $cost_price * $item['quantity'],
                        $profit * $item['quantity'],
                        $oxxa_fee * $item['quantity'],
                        $itemGatewayFee,
                        $seller_earning * $item['quantity'],
                        $sellerPayoutStatus
                    ]);
                } catch (Exception $payoutEx) {
                    // Fail-safe if seller_payouts already tracked elsewhere
                }

                // Update product stock
                if ($item['variant_id']) {
                    $stockStmt = $pdo->prepare("UPDATE color_sizes SET qty = qty - ? WHERE id = ?");
                    $stockStmt->execute([$item['quantity'], $item['variant_id']]);
                    
                    // Check if stock is low
                    $checkStock = $pdo->prepare("SELECT qty FROM color_sizes WHERE id = ?");
                    $checkStock->execute([$item['variant_id']]);
                    if ($stockRow = $checkStock->fetch(PDO::FETCH_ASSOC)) {
                        if ($stockRow['qty'] > 0 && $stockRow['qty'] <= 5) {
                            addNotification($mysql, $item['seller_id'], "Low Stock Alert! - {$item['name']} ({$item['size']}) - Only {$stockRow['qty']} left.", 'warning', 'Business', 'site/seller-products.php');
                        } elseif ($stockRow['qty'] <= 0) {
                            addNotification($mysql, $item['seller_id'], "Out of Stock! - {$item['name']} ({$item['size']}) is out of stock.", 'error', 'Business', 'site/seller-products.php');
                        }
                    }
                }
                
                // Update total_qty in products table
                $mainStockStmt = $pdo->prepare("UPDATE products SET total_qty = total_qty - ? WHERE id = ?");
                $mainStockStmt->execute([$item['quantity'], $item['product_id']]);
            }

            // 4. Empty Cart
            $emptyCartStmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $emptyCartStmt->execute([$user_id]);

            $pdo->commit();

            echo json_encode([
                'success' => true, 
                'message' => 'Order placed successfully!',
                'orderCode' => $orderCode,
                'date' => date('M d, Y'),
                'totalAmount' => number_format($totalAmount, 2),
                'paymentMethod' => $paymentMethod,
                'gatewayFee' => number_format($gatewayFee, 2),
                'status' => ($paymentStatus === 'paid' ? 'Paid' : ($paymentStatus === 'pending_verification' ? 'Pending Slip Verification' : 'Pending')),
                'redirect' => 'order-success.php?id=' . $orderCode
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
