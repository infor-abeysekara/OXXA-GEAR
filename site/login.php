<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Pass along any success/error messages
$query_string = $_SERVER['QUERY_STRING'];
$redirect_url = '../index.php?open=login';
if (!empty($query_string)) {
    $redirect_url .= '&' . $query_string;
}
header("Location: $redirect_url");
exit();
?>