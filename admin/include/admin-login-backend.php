<?php
session_start();
include_once("../../include/connection.php");

if(isset($_POST['login'])){
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate required fields
    if(empty($username)){
        header("Location: ../index.php?error=username");
        exit();
    }
    if(empty($password)){
        header("Location: ../index.php?error=password");
        exit();
    }
    
    try {
        // Query to check admin credentials using PDO for consistency
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND password = ? AND type = 'admin' AND approve = 1");
        $hashedPassword = md5($password);
        $stmt->execute([$username, $username, $hashedPassword]);
        $user = $stmt->fetch();
        
        if($user){
            // Set admin session variables - using consistent naming
            $_SESSION['userid'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['image'] = $user['image'];
            $_SESSION['type'] = $user['type'];
            $_SESSION['approve'] = $user['approve'];
            $_SESSION['is_admin'] = true;
            
            // Also set admin-specific session variables for backward compatibility
            $_SESSION['admin_userid'] = $user['user_id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_firstname'] = $user['firstname'];
            $_SESSION['admin_lastname'] = $user['lastname'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_image'] = $user['image'];
            $_SESSION['admin_type'] = $user['type'];
            
            // Redirect to admin dashboard
            header("Location: ../dashboard.php");
            exit();
        } else {
            header("Location: ../index.php?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../index.php?error=database");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>