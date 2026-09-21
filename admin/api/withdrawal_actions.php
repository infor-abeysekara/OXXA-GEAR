<?php
session_start();
include_once("../../include/connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = $_POST['id'] ?? null;
$new_status = $_POST['status'] ?? null;
$reason = $_POST['reason'] ?? null;
$admin_id = $_SESSION['user_id'] ?? 1; // Fallback to 1 if not set in session

if (!$id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Get current state
    $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$withdrawal) {
        throw new Exception("Withdrawal not found");
    }

    $old_status = $withdrawal['status'];

    if ($old_status === $new_status) {
        throw new Exception("Status is already $new_status");
    }

    $update_fields = ["status = ?"];
    $params = [$new_status];

    // Handle timestamps and logic based on status
    if ($new_status === 'APPROVED') {
        $update_fields[] = "approved_by = ?";
        $update_fields[] = "approved_at = NOW()";
        array_push($params, $admin_id);
    } else if ($new_status === 'PROCESSING') {
        $update_fields[] = "processed_at = NOW()";
    } else if ($new_status === 'COMPLETED') {
        $update_fields[] = "completed_at = NOW()";
        // Also update seller balance total_withdrawn and pending_withdrawal
        $balStmt = $pdo->prepare("UPDATE seller_balances SET total_withdrawn = total_withdrawn + ?, pending_withdrawal = pending_withdrawal - ? WHERE seller_id = ?");
        $balStmt->execute([$withdrawal['amount'], $withdrawal['amount'], $withdrawal['seller_id']]);
    } else if ($new_status === 'REJECTED') {
        $update_fields[] = "rejection_reason = ?";
        array_push($params, $reason);
        // Refund amount back to available balance, remove from pending
        $balStmt = $pdo->prepare("UPDATE seller_balances SET available_balance = available_balance + ?, pending_withdrawal = pending_withdrawal - ? WHERE seller_id = ?");
        $balStmt->execute([$withdrawal['amount'], $withdrawal['amount'], $withdrawal['seller_id']]);
        
        // In Phase 1 we just record available_balance_after changes conceptually if needed, 
        // but here we just process the balance.
    }

    array_push($params, $id);
    $update_sql = "UPDATE withdrawals SET " . implode(", ", $update_fields) . " WHERE id = ?";
    
    $updateStmt = $pdo->prepare($update_sql);
    $updateStmt->execute($params);

    // Log the action
    $logStmt = $pdo->prepare("INSERT INTO withdrawal_logs (withdrawal_id, action, old_status, new_status, user_id, user_type, notes, ip_address) VALUES (?, ?, ?, ?, ?, 'ADMIN', ?, ?)");
    $logStmt->execute([
        $id,
        "Status changed to " . $new_status,
        $old_status,
        $new_status,
        $admin_id,
        $reason,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
