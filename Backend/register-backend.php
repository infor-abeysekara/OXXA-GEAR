<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

header('Content-Type: application/json');

// Form eka POST method eken awillada balanawa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $errors = [];
    
    // Get form data
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : '';
    
    // Regex Patterns (Data eka valid format ekakda kiyala check karanna use karana patterns)
    $nameRegex = '/^[A-Za-z ]{2,50}$/';
    $usernameRegex = '/^[a-zA-Z0-9_]{3,20}$/';
    $phoneRegex = '/^(?:\+94|0)?7[0-9]{8}$/';
    
    // Validation
    // Name eke akuru vitharak tiyenawada kiyala balanawa
    if (!preg_match($nameRegex, $firstname)) {
        $errors[] = "First name must be 2-50 letters only.";
    }
    
    if (!preg_match($nameRegex, $lastname)) {
        $errors[] = "Last name must be 2-50 letters only.";
    }
    
    if (!preg_match($usernameRegex, $username)) {
        $errors[] = "Username must be 3-20 characters, letters, numbers, and underscores only.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Phone number eka Sri Lankan number ekakda kiyala balanawa
    if (!preg_match($phoneRegex, $phone)) {
        $errors[] = "Invalid Sri Lankan phone number.";
    } else {
        // Format phone to 947XXXXXXXX (Phone number eka database ekata save karanna kalin standard format ekata gannawa)
        $phone = preg_replace('/^(?:\+94|0)?/', '94', $phone);
    }
    
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }
    
    // Password eke strongness eka balanawa (akuru, ilakkam, symbols thiyenawada kiyala)
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $password)) {
        $errors[] = "Password must be at least 8 characters with upper, lower, number, and special character.";
    }
    
    if (!in_array($user_type, ['customer', 'seller'])) {
        $errors[] = "Invalid account type selected.";
    }
    
    if (empty($_POST['terms'])) {
        $errors[] = "You must agree to the Terms of Service.";
    }

    // Errors thiyenawanam wada karanne na, e errors tika apahu pass karanawa frontend ekata
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit();
    }

    try {
        // Check if username, email, or phone already exists using PDO (Database eke kalinma me details thiyenawada kiyala balanawa)
        $checkStmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ?");
        $checkStmt->execute([$username, $email, $phone]);
        
        if ($checkStmt->rowCount() > 0) {
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing['username'] === $username) {
                $errors[] = "Username is already taken.";
            }
            if ($existing['email'] === $email) {
                $errors[] = "Email is already registered.";
            }
            if ($existing['phone'] === $phone) {
                $errors[] = "Phone number is already registered.";
            }
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }

        // Handle profile photo upload
        $imageName = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/uploads/profiles/';
            // Folder eka naththam eka hadanawa
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($_FILES['profile_photo']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Only JPG, PNG, and WEBP images are allowed.";
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
            
            if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) { // 2MB limit
                $errors[] = "Profile photo must be less than 2MB.";
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
            
            $imageExt = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            if (empty($imageExt)) {
                if ($fileType == 'image/jpeg') $imageExt = 'jpg';
                else if ($fileType == 'image/png') $imageExt = 'png';
                else if ($fileType == 'image/webp') $imageExt = 'webp';
            }
            
            // Image ekata unique namak deela save karanawa server eke
            $imageName = 'user_' . uniqid() . '.' . $imageExt;
            $imagePath = $uploadDir . $imageName;
            
            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $imagePath)) {
                $errors[] = "Failed to upload profile photo.";
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
        }

        // Generate new user ID using mysqli connection for compatibility with existing function
        $user_code = generateUserId($conn);
        
        // Use md5 for backward compatibility with old login system
        $hashedPassword = md5($password);
        
        // Insert user into database using PDO (Aluth user wa database eke users table ekata save karanawa)
        $insertStmt = $pdo->prepare("INSERT INTO users (user_code, first_name, last_name, username, email, phone, password, profile_image, user_type, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        
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
            
            // User seller kenek nam, business registration ekata yawai. Naththam home ekata yawai.
            if ($user_type == 'seller') {
                $redirect = '../site/business-registration.php';
            } else {
                $redirect = '../site/index.php?success=registered';
            }
            
            echo json_encode(['success' => true, 'redirect' => $redirect]);
            exit();
        } else {
            // DB insertion failed
            echo json_encode(['success' => false, 'errors' => ["Database error occurred during registration."]]);
            exit();
        }
    } catch (PDOException $e) {
        // Exception caught
        echo json_encode(['success' => false, 'errors' => ["Database error occurred: " . $e->getMessage()]]);
        exit();
    }
} else {
    // Bad request
    echo json_encode(['success' => false, 'errors' => ["Invalid request."]]);
    exit();
}
?>