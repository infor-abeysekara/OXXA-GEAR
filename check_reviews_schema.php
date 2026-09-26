<?php
include('include/connection.php');
$stmt = $pdo->query('SHOW CREATE TABLE reviews');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
