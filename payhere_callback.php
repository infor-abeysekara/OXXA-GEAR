<?php
// payhere_callback.php
require_once('include/connection.php');

// Example variables from PayHere
$merchant_id         = $_POST['merchant_id'] ?? '';
$order_id            = $_POST['order_id'] ?? ''; // This is order_code in our DB
$payhere_amount      = $_POST['payhere_amount'] ?? '';
$payhere_currency    = $_POST['payhere_currency'] ?? '';
$status_code         = $_POST['status_code'] ?? '';
$md5sig              = $_POST['md5sig'] ?? '';

// Normally, verify md5sig here using Merchant Secret...

if ($status_code == 2) {
    try {
        $pdo->beginTransaction();

        // Check order
        $stmt = $pdo->prepare("SELECT id, status, payment_status FROM orders WHERE order_code = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order && $order['payment_status'] !== 'PAID') {
            // Update order status
            $updateOrder = $pdo->prepare("
                UPDATE orders 
                SET payment_status = 'PAID', 
                    status = 'PAID', 
                    pay_at = NOW() 
                WHERE order_code = ?
            ");
            $updateOrder->execute([$order_id]);

            // Add earnings and locked_balance for all sellers involved
            $itemsStmt = $pdo->prepare("
                SELECT oi.seller_earning, oi.quantity, p.seller_id, oi.admin_commission, oi.gateway_fee 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $itemsStmt->execute([$order['id']]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $seller_id = $item['seller_id'];
                $earning = $item['seller_earning'] * $item['quantity'];
                $total_gross = $earning + ($item['admin_commission'] * $item['quantity']) + ($item['gateway_fee'] * $item['quantity']);
                $commission = $item['admin_commission'] * $item['quantity'];
                $gateway_fee = $item['gateway_fee'] * $item['quantity'];

                // Ensure balance record exists
                $checkBalanceStmt = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
                $checkBalanceStmt->execute([$seller_id]);
                if (!$checkBalanceStmt->fetchColumn()) {
                    $pdo->prepare("INSERT INTO seller_balances (seller_id) VALUES (?)")->execute([$seller_id]);
                }

                // Update seller balances
                $updateBalance = $pdo->prepare("
                    UPDATE seller_balances 
                    SET return_window_hold = return_window_hold + ?,
                        total_earnings = total_earnings + ?,
                        commission_deducted = commission_deducted + ?,
                        gateway_fees_deducted = gateway_fees_deducted + ?
                    WHERE seller_id = ?
                ");
                $updateBalance->execute([$earning, $total_gross, $commission, $gateway_fee, $seller_id]);
            }
            
            $pdo->commit();
            
            // Notify seller logic goes here...
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("PayHere Webhook Error: " . $e->getMessage());
    }
}
?>
