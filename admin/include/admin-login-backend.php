<?php
session_start();
// Redirect to the main unified login page
header("Location: ../../site/index.php?open=login");
exit();
?>