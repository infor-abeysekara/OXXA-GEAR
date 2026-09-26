<?php
$placeholder = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
file_put_contents('image/placeholder.png', base64_decode($placeholder));
echo "Placeholder created.";
?>
