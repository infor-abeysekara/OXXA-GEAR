<?php
session_start();
$_SESSION['userid'] = 4; // Mocking logged in user 4 (Ravindu)
$_SESSION['type'] = 'seller';

$_POST['update_profile'] = '1';
$_POST['firstname'] = 'Ravindu';
$_POST['lastname'] = 'Chandeepa';
$_POST['username'] = 'ravindu';
$_POST['email'] = 'test@example.com';
$_POST['phone'] = '';
$_POST['dob'] = '';
$_POST['gender'] = 'unspecified';
$_POST['current_password'] = 'wrongpassword'; // Let's see if this throws warning

// capture output
ob_start();
include('../Backend/update-backend.php');
$output = ob_get_clean();

echo "OUTPUT:\n";
var_dump($output);
?>
