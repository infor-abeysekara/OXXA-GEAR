<?php
include_once("include/connection.php");
echo "=== products ===\n";
$r = $pdo->query("SHOW COLUMNS FROM products");
foreach($r as $row) echo $row['Field'] . " | " . $row['Type'] . "\n";
echo "\n=== seller_profiles ===\n";
$r2 = $pdo->query("SHOW COLUMNS FROM seller_profiles");
foreach($r2 as $row) echo $row['Field'] . " | " . $row['Type'] . "\n";
?>
