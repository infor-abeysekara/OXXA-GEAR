<?php
include('include/connection.php');
try {
    $pdo->exec("ALTER TABLE reviews ADD COLUMN seller_reply text DEFAULT NULL");
    echo "Added seller_reply column to reviews.\n";
} catch(PDOException $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}
