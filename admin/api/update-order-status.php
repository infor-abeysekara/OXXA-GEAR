<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../../include/connection.php");
include_once(__DIR__ . "/../../include/functions.php");

header('Content-Type: application/json');

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $tracking_number = $_POST['tracking_number'] ?? null;
    $courier_company = $_POST['courier_company'] ?? null;

    if (empty($order_id) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update the order status
        if ($status === 'delivered' || $status === 'completed') {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ?, courier_company = ?, delivered_at = NOW() WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ?, courier_company = ? WHERE id = ?");
        }
        $stmt->execute([$status, $tracking_number, $courier_company, $order_id]);

        // Fetch buyer's user_id to send a notification
        $buyerStmt = $pdo->prepare("SELECT user_id, order_code FROM orders WHERE id = ?");
        $buyerStmt->execute([$order_id]);
        $buyer = $buyerStmt->fetch(PDO::FETCH_ASSOC);

        if ($buyer) {
            $buyerId = $buyer['user_id'];
            $orderCode = $buyer['order_code'];
            
            $notifMsg = "Your order #$orderCode has been updated to " . strtoupper(str_replace('_', ' ', $status));
            if ($status === 'shipped' && $tracking_number) {
                $notifMsg .= " (Tracking: $tracking_number via $courier_company)";
            }
            
            addNotification($conn, $buyerId, $notifMsg, 'info', 'Orders', 'site/order-details.php?id=' . $order_id);
        }

        // If status is delivered, process payouts for ALL pending items in this order
        if ($status === 'delivered' || $status === 'completed') {
            $itemsStmt = $pdo->prepare("
                SELECT o.id, o.seller_earning, o.oxxa_fee, o.selling_price, o.cost_price, o.profit, p.seller_id 
                FROM order_items o
                JOIN products p ON o.product_id = p.id
                WHERE o.order_id = ? AND o.settlement_status = 'Pending'
            ");
            $itemsStmt->execute([$order_id]);
            $pendingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendingItems as $item) {
                $seller_id = $item['seller_id'];

                // Mark item as Locked
                $updateItem = $pdo->prepare("UPDATE order_items SET settlement_status = 'Locked' WHERE id = ?");
                $updateItem->execute([$item['id']]);

                // Ensure seller wallet exists
                $walletCheck = $pdo->prepare("SELECT seller_id FROM seller_wallets WHERE seller_id = ?");
                $walletCheck->execute([$seller_id]);
                if (!$walletCheck->fetch()) {
                    $pdo->prepare("INSERT INTO seller_wallets (seller_id, total_earnings, pending_balance, locked_balance, paid_balance, total_oxxa_fee) VALUES (?, 0, 0, 0, 0, 0)")->execute([$seller_id]);
                }

                // Update seller wallet
                $updateWallet = $pdo->prepare("
                    UPDATE seller_wallets 
                    SET total_earnings = total_earnings + ?,
                        locked_balance = locked_balance + ?,
                        total_oxxa_fee = total_oxxa_fee + ?
                    WHERE seller_id = ?
                ");
                $updateWallet->execute([$item['seller_earning'], $item['seller_earning'], $item['oxxa_fee'], $seller_id]);
                
                // Insert into seller_payouts ledger
                $insertPayout = $pdo->prepare("
                    INSERT INTO seller_payouts (
                        seller_id, order_item_id, seller_earning, payout_status, 
                        selling_price, cost_price, profit, admin_commission, created_at
                    ) VALUES (?, ?, ?, 'locked', ?, ?, ?, ?, NOW())
                ");
                $insertPayout->execute([
                    $seller_id, 
                    $item['id'], 
                    $item['seller_earning'], 
                    $item['selling_price'], 
                    $item['cost_price'], 
                    $item['profit'], 
                    $item['oxxa_fee']
                ]);
                
                // Add Notification for Seller
                addNotification($conn, $seller_id, "Order #$orderCode has been delivered! Rs." . number_format($item['seller_earning'], 2) . " has been added to your locked balance for 14 days.", 'success', 'Payouts', 'site/seller-dashboard.php?tab=earnings');
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Order status updated to " . strtoupper(str_replace('_', ' ', $status))]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "Failed to update order status: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
