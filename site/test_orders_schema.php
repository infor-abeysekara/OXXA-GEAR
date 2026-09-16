<?php
include('../include/connection.php');

function getTableSchema($pdo, $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return "Table does not exist.";
    }
}

echo "orders:\n";
print_r(getTableSchema($pdo, 'orders'));

echo "\norder_items:\n";
print_r(getTableSchema($pdo, 'order_items'));

echo "\nreviews:\n";
print_r(getTableSchema($pdo, 'reviews'));

echo "\nreview_images:\n";
print_r(getTableSchema($pdo, 'review_images'));

?>
