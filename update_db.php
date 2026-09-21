<?php
include('include/connection.php');
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN cancellation_reason TEXT NULL DEFAULT NULL AFTER completed_at");
    echo "SUCCESS";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SUCCESS";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
?>
