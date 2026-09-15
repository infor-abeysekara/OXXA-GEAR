<?php
$_SESSION['userid'] = 4;
$_SESSION['type'] = 'seller';

$_POST['update_profile'] = '1';
$_POST['firstname'] = 'Ravindu';
$_POST['lastname'] = 'Chandeepa';
$_POST['username'] = 'ravindu';
$_POST['email'] = 'test@example.com';
$_POST['phone'] = '';
$_POST['dob'] = '';
$_POST['gender'] = 'unspecified';
$_POST['current_password'] = 'wrongpassword';

// Use a wrapper to intercept session_start or just let it fail? No, if we do a curl request we can pass a session cookie!
// But since I don't have the user's session cookie, let's just make a modified update-backend that doesn't start session and check.
$content = file_get_contents('../Backend/update-backend.php');
$content = str_replace('session_start();', '', $content);
file_put_contents('temp_update.php', $content);

ob_start();
include('temp_update.php');
$output = ob_get_clean();

echo "OUTPUT:\n";
var_dump($output);
?>
