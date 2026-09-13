<?php
session_start();
if(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: dashboard.php");
    exit();
} else {
    // Redirect to the main unified login page
    header("Location: ../site/index.php?open=login");
    exit();
}
?>
