<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=oxxa_gear_database;charset=utf8mb4", 'root', '');
$stmt = $pdo->query("SELECT user_id, logo_path FROM seller_profiles");
$logos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Seller Logos:\n";
print_r($logos);

$stmt = $pdo->query("SELECT id, profile_image FROM users WHERE user_type = 'seller'");
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nUser Profiles:\n";
print_r($profiles);
?>
