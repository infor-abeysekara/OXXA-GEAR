<?php
require 'C:\wamp64\www\OXXA GEAR\include\connection.php';
$stmt = $pdo->query('DESCRIBE orders');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
