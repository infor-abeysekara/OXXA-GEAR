<?php
require_once 'include/connection.php';
$result = $conn->query("SHOW COLUMNS FROM seller_profiles");
while($row = $result->fetch_assoc()) echo $row['Field'] . "\n";
?>
