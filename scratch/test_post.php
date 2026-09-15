<?php
$url = 'http://localhost/OXXA%20GEAR/Backend/update-backend.php';
$data = array(
    'update_profile' => '1',
    'firstname' => 'Test',
    'lastname' => 'User',
    'username' => 'testuser',
    'email' => 'test@example.com',
    'current_password' => 'wrongpassword'
);

$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
if ($result === FALSE) {
    echo "Error";
}

echo "Response: \n";
var_dump($result);
?>
