<?php
require_once 'include/connection.php';
$result = $conn->query("SHOW COLUMNS FROM orders");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
