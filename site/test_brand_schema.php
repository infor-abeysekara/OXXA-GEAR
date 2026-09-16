<?php
include('../include/connection.php');

$stmt = $pdo->query("DESCRIBE brands");
echo "brands:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
