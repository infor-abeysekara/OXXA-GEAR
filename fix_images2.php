<?php
require __DIR__ . '/include/connection.php';

$prodCheck = $pdo->query("SELECT id, name FROM products");
echo "Products in DB: <br>";
while($row = $prodCheck->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " - " . $row['name'] . "<br>";
}

$imgCheck = $pdo->query("SELECT * FROM product_images");
echo "Images in DB: <br>";
while($row = $imgCheck->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
    echo "<br>";
}
