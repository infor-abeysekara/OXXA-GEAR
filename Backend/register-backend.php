<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (isset($_POST['register'])) {
    
    // Ensure session error array is fresh
    $_SESSION['reg_errors'] = [];
    
    // Get form data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    // Regex Patterns
    $nameRegex = '/^[A-Za-z ]{2,50}$/';
    $usernameRegex = '/^[a-zA-Z0-9_]{3,20}$/';
    $phoneRegex = '/^(?:\+94|0)?7[0-9]{8}$/';
    
    // Validation
    if (!preg_match($nameRegex, $firstname)) {
        $_SESSION['reg_errors'][] = "First name must be 2-50 letters only.";
    }
    
    if (!preg_match($nameRegex, $lastname)) {
        $_SESSION['reg_errors'][] = "Last name must be 2-50 letters only.";
    }
    
    if (!preg_match($usernameRegex, $username)) {
        $_SESSION['reg_errors'][] = "Username must be 3-20 characters, letters, numbers, and underscores only.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reg_errors'][] = "Invalid email format.";
    }
    
    if (!preg_match($phoneRegex, $phone)) {
        $_SESSION['reg_errors'][] = "Invalid Sri Lankan phone number.";
    } else {
        // Format phone to 947XXXXXXXX
        $phone = preg_replace('/^(?:\+94|0)?/', '94', $phone);
    }
    
    if ($password !== $confirm) {
        $_SESSION['reg_errors'][] = "Passwords do not match.";
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $password)) {
        $_SESSION['reg_errors'][] = "Password must be at least 8 characters with upper, lower, number, and special character.";
    }
    
    if (!in_array($user_type, ['customer', 'seller'])) {
        $_SESSION['reg_errors'][] = "Invalid account type selected.";
    }
    
    if (!isset($_POST['terms'])) {
        $_SESSION['reg_errors'][] = "You must agree to the Terms of Service.";
    }

    if (!empty($_SESSION['reg_errors'])) {
        header('Location: ../site/index.php?open=register');
        exit();
    }

    try {
        // Check if username or email already exists using PDO
        $checkStmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->rowCount() > 0) {
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing['username'] === $username) {
                $_SESSION['reg_errors'][] = "Username is already taken.";
            }
            if ($existing['email'] === $email) {
                $_SESSION['reg_errors'][] = "Email is already registered.";
            }
            header('Location: ../site/index.php?open=register');
            exit();
        }

        // Handle profile photo upload
        $imageName = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($_FILES['profile_photo']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['reg_errors'][] = "Only JPG, PNG, and WEBP images are allowed.";
                header('Location: ../site/index.php?open=register');
                exit();
            }
            
            if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) { // 2MB limit
                $_SESSION['reg_errors'][] = "Profile photo must be less than 2MB.";
                header('Location: ../site/index.php?open=register');
                exit();
            }
            
            $imageExt = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            if (empty($imageExt)) {
                if ($fileType == 'image/jpeg') $imageExt = 'jpg';
                else if ($fileType == 'image/png') $imageExt = 'png';
                else if ($fileType == 'image/webp') $imageExt = 'webp';
            }
            
            $imageName = 'user_' . uniqid() . '.' . $imageExt;
            $imagePath = $uploadDir . $imageName;
            
            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $imagePath)) {
                $_SESSION['reg_errors'][] = "Failed to upload profile photo.";
                header('Location: ../site/index.php?open=register');
                exit();
            }
        }

        // Generate new user ID using mysqli connection for compatibility with existing function
        $user_code = generateUserId($conn);
        
        // Use md5 for backward compatibility with old login system
        $hashedPassword = md5($password);
        
        // Insert user into database using PDO
        $insertStmt = $pdo->prepare("INSERT INTO users (user_code, first_name, last_name, username, email, phone, password, profile_image, user_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($insertStmt->execute([$user_code, $firstname, $lastname, $username, $email, $phone, $hashedPassword, $imageName, $user_type])) {
            
            $new_id = $pdo->lastInsertId();

            // Log them in automatically
            $_SESSION['userid'] = $new_id;
            $_SESSION['user_code'] = $user_code;
            $_SESSION['first_name'] = $firstname;
            $_SESSION['last_name'] = $lastname;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['profile_image'] = $imageName;
            $_SESSION['type'] = $user_type;
            
            if ($user_type == 'seller') {
                header('Location: ../site/business-registration.php');
            } else {
                header('Location: ../site/index.php?success=registered');
            }
            exit();
        } else {
            $_SESSION['reg_errors'][] = "Database error occurred during registration.";
            header('Location: ../site/index.php?open=register');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['reg_errors'][] = "Database error occurred: " . $e->getMessage();
        header('Location: ../site/index.php?open=register');
        exit();
    }
} else {
    header('Location: ../site/index.php');
    exit();
}
?>