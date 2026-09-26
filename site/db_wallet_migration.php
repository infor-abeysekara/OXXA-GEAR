<?php
include('../include/connection.php');

try {
    // Drop existing if we want to replace or just create new ones
    $pdo->exec("DROP TABLE IF EXISTS wallet_summary");
    
    // Create wallet_summary
    $pdo->exec("CREATE TABLE wallet_summary (
        seller_id INT PRIMARY KEY,
        available_to_withdraw DECIMAL(10,2) DEFAULT 0.00,
        locked_processing DECIMAL(10,2) DEFAULT 0.00,
        total_paid_out DECIMAL(10,2) DEFAULT 0.00
    )");
    
    // Check if delivery_date and return_window_ends exist in orders, if not add them
    $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('delivery_date', $columns)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_date DATETIME NULL");
    }
    if (!in_array('return_window_ends', $columns)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN return_window_ends DATETIME NULL");
    }
    
    // Check withdrawal_requests. If we need to alter it:
    // withdrawal_requests: id, seller_id, amount, bank_account_id, status (PENDING/APPROVED/REJECTED/COMPLETED), reference_no, slip_image, created_at
    // But wait, the user's seller dashboard already accesses bank_name and account_number.
    // If I use bank_account_id, where is the bank_accounts table? The seller_profiles table has bank_name, branch_name, account_number, account_holder_name.
    
    echo "Migration successful.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
