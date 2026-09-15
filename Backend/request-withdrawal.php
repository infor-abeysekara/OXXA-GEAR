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
    $walletStmt = $pdo->prepare("SELECT * FROM seller_wallets WHERE seller_id = ? FOR UPDATE");
    $walletStmt->execute([$seller_id]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new Exception("Wallet not found.");
    }

    if ($wallet['pending_balance'] < $amount) {
        throw new Exception("Insufficient pending balance.");
    }

    // 2. Check if a pending request already exists
    $checkReqStmt = $pdo->prepare("SELECT id FROM withdrawal_requests WHERE seller_id = ? AND status = 'Pending'");
    $checkReqStmt->execute([$seller_id]);
    if ($checkReqStmt->fetchColumn()) {
        throw new Exception("You already have a pending withdrawal request.");
    }

    // 3. Update wallet: move funds from pending to locked
    $updateWalletStmt = $pdo->prepare("
        UPDATE seller_wallets 
        SET pending_balance = pending_balance - ?, 
            locked_balance = locked_balance + ? 
        WHERE seller_id = ?
    ");
    $updateWalletStmt->execute([$amount, $amount, $seller_id]);

    // 4. Insert the request
    $insertReqStmt = $pdo->prepare("
        INSERT INTO withdrawal_requests (seller_id, amount, bank_name, branch_name, account_number, account_holder_name, note, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
    ");
    $insertReqStmt->execute([
        $seller_id,
        $amount,
        $bank_name,
        $branch_name,
        $account_number,
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
