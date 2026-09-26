<?php
include('../include/connection.php');
$seller = $pdo->query("SELECT user_id, business_name FROM seller_profiles")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($seller);
