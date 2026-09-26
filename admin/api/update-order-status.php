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
        if ($status === 'delivered') {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ?, courier_company = ?, delivered_at = NOW(), return_window_ends = DATE_ADD(CURDATE(), INTERVAL 14 DAY) WHERE id = ?");
        } else if ($status === 'completed') {
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
            
            $statusMsgs = [
                'pending' => "is pending and waiting for seller acceptance.",
                'accepted' => "has been accepted by the seller.",
                'confirmed' => "has been confirmed and is ready to pack.",
                'handover_to_center' => "has been handed over to the collecting center.",
                'received_at_center' => "has been received at our sorting center.",
                'packed' => "has been packed and is ready to ship.",
                'shipped' => "has been shipped and is on the way.",
                'out_for_delivery' => "is out for delivery.",
                'delivered' => "has been delivered.",
                'return_window' => "is in the 14-day return window.",
                'completed' => "has been completed.",
                'cancelled' => "has been cancelled."
            ];
            
            $friendlyMsg = isset($statusMsgs[strtolower($status)]) ? $statusMsgs[strtolower($status)] : "has been updated to " . strtoupper(str_replace('_', ' ', $status));
            $notifMsg = "Your order #$orderCode $friendlyMsg";
            
            if (strtolower($status) === 'shipped' && $tracking_number) {
                $notifMsg .= " (Tracking: $tracking_number via $courier_company)";
            }
            
            addNotification($conn, $buyerId, $notifMsg, 'info', 'Orders', 'site/order-details.php?id=' . $order_id);
        }

        // If status is delivered, process payouts for ALL pending items in this order
        if ($status === 'delivered' || $status === 'completed') {
            $itemsStmt = $pdo->prepare("
                SELECT o.id, sp.seller_earning, o.oxxa_fee, o.selling_price, o.cost_price, o.profit, p.seller_id 
                FROM order_items o
                JOIN products p ON o.product_id = p.id
                LEFT JOIN seller_payouts sp ON o.id = sp.order_item_id
                WHERE o.order_id = ? AND o.settlement_status = 'Pending'
            ");
            $itemsStmt->execute([$order_id]);
            $pendingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendingItems as $item) {
                $seller_id = $item['seller_id'];
                $earning = $item['seller_earning'];

                // Mark item as Locked
                $updateItem = $pdo->prepare("UPDATE order_items SET settlement_status = 'Locked' WHERE id = ?");
                $updateItem->execute([$item['id']]);
                
                // Add to seller_balances return_window_hold
                $checkBal = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
                $checkBal->execute([$seller_id]);
                if (!$checkBal->fetch()) {
                    $insBal = $pdo->prepare("INSERT INTO seller_balances (seller_id, available_balance, return_window_hold, pending_withdrawal, total_withdrawn, total_earnings) VALUES (?, 0, ?, 0, 0, ?)");
                    $insBal->execute([$seller_id, $earning, $earning]);
                } else {
                    $updBal = $pdo->prepare("UPDATE seller_balances SET return_window_hold = return_window_hold + ?, total_earnings = total_earnings + ? WHERE seller_id = ?");
                    $updBal->execute([$earning, $earning, $seller_id]);
                }

                // Add Notification for Seller
                addNotification($conn, $seller_id, "Order #$orderCode has been delivered! The 14-day return window for this item has started.", 'success', 'Payouts', 'site/seller-dashboard.php?tab=earnings');
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
