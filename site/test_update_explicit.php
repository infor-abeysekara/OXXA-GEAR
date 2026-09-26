<?php
include('../include/connection.php');
$updBal = $pdo->prepare("UPDATE seller_balances SET return_window_hold = 123.45 WHERE seller_id = 6");
$updBal->execute();
echo "Affected rows: " . $updBal->rowCount();
