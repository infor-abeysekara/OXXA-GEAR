<?php
$conn = mysqli_connect("localhost", "root", "", "oxxa_gear_database");
$res = mysqli_query($conn, "DESCRIBE users");
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>
