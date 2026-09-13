<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (isset($_POST['register'])) {
    // Get form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';

    // Validation
    if (empty($firstname)) {
        header('Location: ../site/register.php?error=firstname');
        exit();
    }
    
    if (empty($lastname)) {
        header('Location: ../site/register.php?error=lastname');
        exit();
    }
    
    if (empty($username)) {
        header('Location: ../site/register.php?error=username');
        exit();
    }
    
    if (empty($email)) {
        header('Location: ../site/register.php?error=email');
        exit();
    }
    
    if (empty($password)) {
        header('Location: ../site/register.php?error=password');
        exit();
    }
    
    if (empty($confirm)) {
        header('Location: ../site/register.php?error=confirm');
        exit();
    }
    
    if ($password !== $confirm) {
        header('Location: ../site/register.php?error=notmatch');
        exit();
    }
    
    if (strlen($password) < 6) {
        header('Location: ../site/register.php?error=password_length');
        exit();
    }
    
    if (empty($user_type)) {
        header('Location: ../site/register.php?error=account_type');
        exit();
    }

    try {
        // Check if username or email already exists using PDO
        $checkStmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->rowCount() > 0) {
            header('Location: ../site/register.php?error=exists');
            exit();
        }

        // Add missing columns if they don't exist
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_type VARCHAR(50) DEFAULT 'customer'");
        } catch (PDOException $e) {
            // Ignore if columns already exist or syntax not supported (older MySQL)
        }

        // Handle profile photo upload
        $imageName = '';
        $dbImagePath = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $fileType = $_FILES['profile_photo']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                header('Location: ../site/register.php?error=format');
                exit();
            }
            
            if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) { // 5MB limit
                header('Location: ../site/register.php?error=large');
                exit();
            }
            
            $imageExt = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $imageName = 'user_' . uniqid() . '.' . $imageExt;
            $imagePath = $uploadDir . $imageName;
            
            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $imagePath)) {
                header('Location: ../site/register.php?error=upload');
                exit();
            }
            $dbImagePath = 'assets/uploads/profiles/' . $imageName;
        }

        // Generate new user ID using mysqli connection for compatibility with existing function
        $userid = generateUserId($conn);
        
        // Hash password
        $hashedPassword = md5($password);
        
        // FIXED: Set approval status to 1 for both buyers and sellers
        $approve = 1;
        
        // Insert user into database using PDO
        $insertStmt = $pdo->prepare("INSERT INTO users (user_id, firstname, lastname, username, email, password, image, type, approve, profile_image, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($insertStmt->execute([$userid, $firstname, $lastname, $username, $email, $hashedPassword, $imageName, $user_type, $approve, $dbImagePath, $user_type])) {
            // Set session variables for immediate login for both buyers and sellers
            $_SESSION['userid'] = $userid;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['image'] = $imageName;
            $_SESSION['profile_image'] = $dbImagePath;
            $_SESSION['type'] = $user_type;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['approve'] = $approve;
            
            header('Location: ../site/index.php?success=registered');
        } else {
            header('Location: ../site/register.php?error=database');
        }
    } catch (PDOException $e) {
        header('Location: ../site/register.php?error=database');
    }
} else {
    header('Location: ../site/register.php');
}
?>