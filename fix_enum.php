<?php
require 'include/connection.php';
$sql = "ALTER TABLE seller_payouts MODIFY COLUMN payout_status ENUM('locked', 'pending', 'paid') NOT NULL DEFAULT 'locked'";
if (mysqli_query($conn, $sql)) {
    echo "Table altered successfully.\n";
} else {
    echo "Error altering table: " . mysqli_error($conn) . "\n";
}
// Fix the empty status for the test record
mysqli_query($conn, "UPDATE seller_payouts SET payout_status = 'locked' WHERE payout_status = ''");
?>
