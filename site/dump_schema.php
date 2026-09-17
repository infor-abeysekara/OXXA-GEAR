<?php
$conn = mysqli_connect("localhost", "root", "", "oxxa_gear_database");
if (!$conn) die("Connection failed");

$result = mysqli_query($conn, "SHOW TABLES");
while($row = mysqli_fetch_row($result)) {
    echo "TABLE: " . $row[0] . "\n";
    $desc = mysqli_query($conn, "DESCRIBE " . $row[0]);
    while($d = mysqli_fetch_assoc($desc)) {
        echo "  " . $d['Field'] . " (" . $d['Type'] . ")\n";
    }
}
?>
