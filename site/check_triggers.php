<?php
include('../include/connection.php');
$triggers = $pdo->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($triggers);
