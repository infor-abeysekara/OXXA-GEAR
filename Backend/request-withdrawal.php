<?php
session_start();
header('Content-Type: application/json');

include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$seller_id = $_SESSION['userid'];

// Validate inputs
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$bank_name = trim($_POST['bank_name'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');
$account_number = trim($_POST['account_number'] ?? '');
$account_holder_name = trim($_POST['account_holder_name'] ?? '');
$note = trim($_POST['note'] ?? '');

if (!$amount || $amount < 2500) {
    echo json_encode(['status' => 'error', 'message' => 'Minimum withdrawal amount is Rs. 2,500.']);
    exit();
}

if (empty($bank_name) || empty($branch_name) || empty($account_number) || empty($account_holder_name)) {
    echo json_encode(['status' => 'error', 'message' => 'All bank details are required.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Lock the wallet row to prevent race conditions
    $walletStmt = $pdo->prepare("SELECT * FROM seller_balances WHERE seller_id = ? FOR UPDATE");
    $walletStmt->execute([$seller_id]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new Exception("Wallet not found.");
    }

    if ($wallet['available_balance'] < $amount) {
        throw new Exception("Insufficient available balance.");
    }

    // Check last withdrawal for one withdrawal per day (24h)
    $lastReqStmt = $pdo->prepare("SELECT requested_at FROM withdrawals WHERE seller_id = ? ORDER BY id DESC LIMIT 1");
    $lastReqStmt->execute([$seller_id]);
    $lastReqDate = $lastReqStmt->fetchColumn();
    
    if ($lastReqDate && strtotime($lastReqDate) > strtotime('-24 hours')) {
        throw new Exception("You can only request one withdrawal per 24 hours.");
    }

    // 2. Check if a pending request already exists
    $checkReqStmt = $pdo->prepare("SELECT id FROM withdrawals WHERE seller_id = ? AND status IN ('PENDING', 'PROCESSING')");
    $checkReqStmt->execute([$seller_id]);
    if ($checkReqStmt->fetchColumn()) {
        throw new Exception("You already have a pending withdrawal request.");
    }

    // Generate withdrawal code
    $today = date('Ymd');
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE DATE(requested_at) = CURDATE()");
    $countStmt->execute();
    $dailyCount = $countStmt->fetchColumn() + 1;
    $withdrawal_code = sprintf("WTH-%s-%03d", $today, $dailyCount);

    // Calculate fees (1%, min 100)
    $fee = max(100, $amount * 0.01);
    $net_amount = $amount - $fee;

    // Get seller details
    $sellerStmt = $pdo->prepare("SELECT u.first_name, u.last_name, sp.business_name FROM users u JOIN seller_profiles sp ON u.id = sp.user_id WHERE u.id = ?");
    $sellerStmt->execute([$seller_id]);
    $sellerData = $sellerStmt->fetch(PDO::FETCH_ASSOC);
    $seller_name = $sellerData['first_name'] . ' ' . $sellerData['last_name'];
    $business_name = $sellerData['business_name'];

    // 3. Update wallet: move funds from available to pending
    $updateWalletStmt = $pdo->prepare("
        UPDATE seller_balances 
        SET available_balance = available_balance - ?, 
            pending_withdrawal = pending_withdrawal + ? 
        WHERE seller_id = ?
    ");
    $updateWalletStmt->execute([$amount, $amount, $seller_id]);

    // 4. Insert the request
    $insertReqStmt = $pdo->prepare("
        INSERT INTO withdrawals (
            withdrawal_code, seller_id, seller_name, business_name, amount, fee, net_amount, 
            available_balance_before, available_balance_after, bank_name, bank_account_no_masked, 
            bank_holder, status, requested_at, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW(), ?)
    ");
    
    $available_balance_before = $wallet['available_balance'];
    $available_balance_after = $wallet['available_balance'] - $amount;
    $masked_account = str_repeat('*', max(0, strlen($account_number) - 4)) . substr($account_number, -4);

    $insertReqStmt->execute([
        $withdrawal_code,
        $seller_id,
        $seller_name,
        $business_name,
        $amount,
        $fee,
        $net_amount,
        $available_balance_before,
        $available_balance_after,
        $bank_name,
        $masked_account,
        $account_holder_name,
        $note
    ]);

    $pdo->commit();

    // TODO: Send Email Notification to Seller and Admin (stub)

    echo json_encode(['status' => 'success', 'message' => 'Withdrawal request submitted successfully.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
