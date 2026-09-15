<?php
include('../include/connection.php');
$stmt = $pdo->query("DESCRIBE users");
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
