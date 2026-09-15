<?php
$content = file_get_contents('../Backend/update-backend.php');
echo "First 10 chars hex: " . bin2hex(substr($content, 0, 10)) . "\n";
?>
