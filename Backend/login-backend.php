<?php
session_start();
include('../include/connection.php');

// Form eka POST method eken submit unama wada karanna gannawa
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Username ekayi password ekayi dekama dila thiyenawada kiyala check karanawa
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: ../site/index.php?open=login");
        exit();
    }

    try {
        // Database eken user details gannawa. Username ekakin ho email ekakin login wenna puluwan
        $stmt = $pdo->prepare("SELECT u.id, u.user_code, u.first_name, u.last_name, u.username, u.email, u.password, u.user_type, u.profile_image, 
                        s.is_approved as seller_approved 
                        FROM users u 
                        LEFT JOIN seller_profiles s ON u.id = s.user_id 
                        WHERE (u.username = ? OR u.email = ?)");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // User kenek innawada saha password eka (md5 hash eka) hariyatama match wenawada balanawa
        if ($user && md5($password) === $user['password']) {
            
            // Seller account ekak nam, admin eka approve karala thiyenawada kiyala check karanawa. Approve naththam login wenna denne na.
            if ($user['user_type'] == 'seller' && $user['seller_approved'] !== 1) {
                $_SESSION['error'] = "Your seller account is pending approval. Please wait for admin approval.";
                header("Location: ../site/index.php?open=login");
                exit();
            }

            // Login eka success nam, user ge wisthara okkoma session ekata save karanawa idiriyata use karanna
            $_SESSION['userid'] = $user['id']; 
            $_SESSION['user_code'] = $user['user_code']; 
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['type'] = $user['user_type'];
            $_SESSION['profile_image'] = $user['profile_image'];

            $_SESSION['success'] = "Login successful! Welcome back, " . $user['first_name'] . "!";

            // User admin kenek nam dashboard ekata yanawa, naththam main page ekata yanawa
            if ($user['user_type'] == 'admin') {
                $_SESSION['is_admin'] = true;
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../site/index.php");
            }
            exit();
        } else {
            // Password eka ho username eka waradi nam
            $_SESSION['error'] = "Invalid username/email or password.";
            header("Location: ../site/index.php?open=login");
            exit();
        }
    } catch (PDOException $e) {
        // Handle DB error
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: ../site/index.php?open=login");
        exit();
    }
} else {
    // Redirect if not POST
    header("Location: ../site/index.php?open=login");
    exit();
}
?>