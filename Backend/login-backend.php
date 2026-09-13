<?php
session_start();
include('../include/connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: ../site/index.php?open=login");
        exit();
    }

    try {
        // Check user credentials (can login with username or email)
        $stmt = $pdo->prepare("SELECT u.id, u.user_code, u.first_name, u.last_name, u.username, u.email, u.password, u.user_type, u.profile_image, 
                        s.is_approved as seller_approved 
                        FROM users u 
                        LEFT JOIN seller_profiles s ON u.id = s.user_id 
                        WHERE (u.username = ? OR u.email = ?)");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && md5($password) === $user['password']) {
            // Check if seller is approved
            if ($user['user_type'] == 'seller' && $user['seller_approved'] !== 1) {
                $_SESSION['error'] = "Your seller account is pending approval. Please wait for admin approval.";
                header("Location: ../site/index.php?open=login");
                exit();
            }

            // Login successful - Set consistent session variables
            $_SESSION['userid'] = $user['id']; // Now using the INT ID
            $_SESSION['user_code'] = $user['user_code']; // For backward compat if needed
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['type'] = $user['user_type'];
            $_SESSION['profile_image'] = $user['profile_image'];

            $_SESSION['success'] = "Login successful! Welcome back, " . $user['first_name'] . "!";

            // Redirect based on user type
            if ($user['user_type'] == 'admin') {
                $_SESSION['is_admin'] = true;
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../site/index.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Invalid username/email or password.";
            header("Location: ../site/index.php?open=login");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: ../site/index.php?open=login");
        exit();
    }
} else {
    header("Location: ../site/index.php?open=login");
    exit();
}
?>