<?php
include_once(__DIR__ . "/../include/connection.php");

try {
    $stmt = $pdo->query("SELECT id, product_id FROM reviews WHERE seller_id IS NULL");
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($reviews as $rev) {
        $pStmt = $pdo->prepare("SELECT seller_id FROM products WHERE id = ?");
        $pStmt->execute([$rev['product_id']]);
        $seller_id = $pStmt->fetchColumn();
        
        $uStmt = $pdo->prepare("UPDATE reviews SET seller_id = ?, status = 'active' WHERE id = ?");
        $uStmt->execute([$seller_id, $rev['id']]);
        echo "Fixed review " . $rev['id'] . " for seller " . $seller_id . "<br>";
    }
    
    // Also fix any reviews where status is empty string or 'pending' but seller_id is not null
    $uStmt2 = $pdo->prepare("UPDATE reviews SET status = 'active' WHERE status NOT IN ('active', 'reported', 'hidden')");
    $uStmt2->execute();
    echo "Fixed invalid statuses. Updated " . $uStmt2->rowCount() . " rows.<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
