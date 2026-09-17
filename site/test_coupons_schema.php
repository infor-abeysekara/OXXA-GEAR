<?php
$conn = mysqli_connect("localhost", "root", "", "oxxa_gear_database");
if (!$conn) die("Connection failed");

$result = mysqli_query($conn, "DESCRIBE coupons");
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
} else {
    echo "No coupons table";
}
?>
