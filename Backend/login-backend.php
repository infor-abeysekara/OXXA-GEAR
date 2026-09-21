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
        $stmt = $pdo->prepare("SELECT u.id, u.user_code, u.first_name, u.last_name, u.username, u.email, u.password, u.user_type, u.profile_image, u.is_approved,
                        s.id as seller_profile_id, s.is_approved as seller_approved 
                        FROM users u 
                        LEFT JOIN seller_profiles s ON u.id = s.user_id 
                        WHERE (u.username = ? OR u.email = ?)");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // User kenek innawada saha password eka (md5 hash eka) hariyatama match wenawada balanawa
        if ($user && md5($password) === $user['password']) {
            
            // Check if user account is suspended by admin
            if (isset($user['is_approved']) && $user['is_approved'] == 0) {
                $_SESSION['error'] = "Your account has been suspended. Please contact support.";
                header("Location: ../site/index.php?open=login");
                exit();
            }

            // Login eka success nam, user ge wisthara okkoma session ekata save karanawa
            $_SESSION['userid'] = $user['id']; 
            $_SESSION['user_code'] = $user['user_code']; 
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['type'] = $user['user_type'];
            $_SESSION['profile_image'] = $user['profile_image'];

            // User admin kenek nam dashboard ekata yanawa
            if ($user['user_type'] == 'admin') {
                $_SESSION['is_admin'] = true;
                header("Location: ../admin/dashboard.php");
                exit();
            }

            // Seller account routing
            if ($user['user_type'] == 'seller') {
                if (empty($user['seller_profile_id'])) {
                    // Seller has NOT submitted business registration yet -> redirect to complete it!
                    $_SESSION['info'] = "Please complete your business verification to start selling on OXXA GEAR.";
                    header("Location: ../site/business-registration.php");
                    exit();
                } elseif ($user['seller_approved'] == 1) {
                    // Seller approved -> send to seller dashboard
                    $_SESSION['success'] = "Login successful! Welcome back to your Seller Dashboard.";
                    header("Location: ../site/seller-dashboard.php");
                    exit();
                } else {
                    // Verification submitted and pending review (0) or rejected (-1)
                    header("Location: ../site/business-registration.php");
                    exit();
                }
            }

            // Customer
            $_SESSION['success'] = "Login successful! Welcome back, " . $user['first_name'] . "!";
            header("Location: ../site/index.php");
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