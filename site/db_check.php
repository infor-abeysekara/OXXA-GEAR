<?php
$conn = mysqli_connect("localhost", "root", "", "oxxa_gear_database");
if (!$conn) die("Connection failed");

// Add columns
$queries = [
    "ALTER TABLE notifications ADD COLUMN category VARCHAR(50) DEFAULT 'System' AFTER type",
    "ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER category"
];

foreach ($queries as $q) {
    if (mysqli_query($conn, $q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . mysqli_error($conn) . " on $q\n";
    }
}
?>
