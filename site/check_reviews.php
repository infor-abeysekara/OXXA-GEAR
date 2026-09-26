<?php
include('../include/connection.php');
$schema = $pdo->query("SHOW COLUMNS FROM reviews")->fetchAll(PDO::FETCH_ASSOC);
$reviews = $pdo->query("SELECT * FROM reviews ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['schema' => $schema, 'reviews' => $reviews]);
