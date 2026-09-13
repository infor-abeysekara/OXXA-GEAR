<?php
include_once("include/connection.php");
$stmt = $pdo->query("DESCRIBE orders");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
