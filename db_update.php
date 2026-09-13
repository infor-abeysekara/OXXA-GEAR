<?php
include_once("include/connection.php");
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN dob DATE DEFAULT NULL AFTER phone;");
    echo "DOB added.\n";
} catch (Exception $e) { echo "DOB error: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN gender ENUM('male', 'female', 'other', 'unspecified') DEFAULT 'unspecified' AFTER dob;");
    echo "Gender added.\n";
} catch (Exception $e) { echo "Gender error: " . $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN preferred_sports VARCHAR(255) DEFAULT NULL AFTER gender;");
    echo "Preferred sports added.\n";
} catch (Exception $e) { echo "Preferred sports error: " . $e->getMessage() . "\n"; }
?>
