<?php
$conn = mysqli_connect("localhost", "root", "", "oxxa_gear_database");
$res = mysqli_query($conn, "DESCRIBE ordertable");
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
