<?php
require 'include/connection.php';
$stmt = $pdo->query('DESCRIBE seller_profiles');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
?>
