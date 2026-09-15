<?php
include('C:\wamp64\www\OXXA GEAR\include\connection.php');
$res = $conn->query('DESCRIBE seller_profiles');
while($r = $res->fetch_assoc()) {
    print_r($r);
}
?>
