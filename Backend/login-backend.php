<?php
session_start();
include('../include/connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: ../site/login.php");
        exit();
    }

    try {
        // Check user credentials (can login with username or email)
        $stmt = $pdo->prepare("SELECT user_id, firstname, lastname, username, email, password, type, approve FROM users WHERE (username = ? OR email = ?)");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && md5($password) === $user['password']) {
            // Check if user is approved
            if ($user['approve'] != 1) {
                $_SESSION['error'] = "Your account is pending approval. Please wait for admin approval.";
                header("Location: ../site/login.php");
                exit();
            }

            // Login successful - Set consistent session variables
            $_SESSION['userid'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['type'] = $user['type'];
            $_SESSION['image'] = '';
            $_SESSION['approve'] = $user['approve'];

            $_SESSION['success'] = "Login successful! Welcome back, " . $user['firstname'] . "!";

            // Redirect based on user type
            if ($user['type'] == 'admin') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../site/index.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid username/email or password.";
            header("Location: ../site/login.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: ../site/login.php");
        exit();
    }
} else {
    header("Location: ../site/login.php");
    exit();
}
?>