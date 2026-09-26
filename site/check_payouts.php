<?php
include('../include/connection.php');
$payouts = $pdo->query("SELECT * FROM seller_payouts")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($payouts);
