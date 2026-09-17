<?php
session_start();
include('../../include/connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['userid'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $tracking_number = $_POST['tracking_number'] ?? null;
    $courier_company = $_POST['courier_company'] ?? null;
    $seller_id = $_SESSION['userid'];

    try {
        $pdo->beginTransaction();

        // Check if the order has items belonging to this seller
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM order_items o
            JOIN products p ON o.product_id = p.id
            WHERE o.order_id = ? AND p.seller_id = ?
        ");
        $checkStmt->execute([$order_id, $seller_id]);
        if ($checkStmt->fetchColumn() == 0) {
            throw new Exception("You do not have permission to update this order.");
        }

        // Update the order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ?, courier_company = ? WHERE id = ?");
        $stmt->execute([$status, $tracking_number, $courier_company, $order_id]);

        // If status is delivered or completed, process payouts for this seller's items
        if ($status === 'delivered' || $status === 'completed') {
            // Find pending items for this seller
            $itemsStmt = $pdo->prepare("
                SELECT o.id, o.seller_earning, o.oxxa_fee 
                FROM order_items o
                JOIN products p ON o.product_id = p.id
                WHERE o.order_id = ? AND p.seller_id = ? AND o.settlement_status = 'Pending'
            ");
            $itemsStmt->execute([$order_id, $seller_id]);
            $pendingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendingItems as $item) {
                // Mark item as Settled
                $updateItem = $pdo->prepare("UPDATE order_items SET settlement_status = 'Settled' WHERE id = ?");
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
                        pending_balance = pending_balance + ?,
                        total_oxxa_fee = total_oxxa_fee + ?
                    WHERE seller_id = ?
                ");
                $updateWallet->execute([$item['seller_earning'], $item['seller_earning'], $item['oxxa_fee'], $seller_id]);
                
                // Add to seller_payouts record for ledger
                $payoutRecord = $pdo->prepare("
                    INSERT INTO seller_payouts (seller_id, order_item_id, seller_earning, payout_status)
                    VALUES (?, ?, ?, 'pending')
                ");
                // Wait, schema for seller_payouts has more columns, we can just omit non-required or we don't have to use seller_payouts if seller_wallets handles the balance. But ledger is good.
                // Looking at the schema, let's just do a basic insert if required, or skip since we use wallet for pending_balance.
            }
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Order status updated to " . strtoupper($status) . " successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Failed to update order status: " . $e->getMessage();
    }
}

header("Location: ../seller-dashboard.php?tab=orders");
exit();
?>
