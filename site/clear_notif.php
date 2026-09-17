<?php
$conn = mysqli_connect("localhost", "root", "", "oxxa_gear_database");
mysqli_query($conn, "TRUNCATE TABLE notifications");
echo "Notifications truncated";
?>
