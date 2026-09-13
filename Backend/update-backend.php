<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: ../site/login.php");
    exit();
}

if (isset($_POST['update_profile'])) {
    $userid = $_SESSION['userid'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($firstname)) {
        header('Location: ../site/profile.php?error=firstname');
        exit();
    }
    
    if (empty($lastname)) {
        header('Location: ../site/profile.php?error=lastname');
        exit();
    }
    
    if (empty($username)) {
        header('Location: ../site/profile.php?error=username');
        exit();
    }
    
    if (empty($email)) {
        header('Location: ../site/profile.php?error=email');
        exit();
    }
    
    if (empty($current_password)) {
        header('Location: ../site/profile.php?error=current_password');
        exit();
    }

    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$userid]);
        $user = $stmt->fetch();
        
        if (!$user || md5($current_password) !== $user['password']) {
            header('Location: ../site/profile.php?error=wrong_password');
            exit();
        }

        // Check if new password is provided and validate it
        $passwordToUpdate = $user['password']; // Keep current password by default
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                header('Location: ../site/profile.php?error=password_mismatch');
                exit();
            }
            
            if (strlen($new_password) < 6) {
                header('Location: ../site/profile.php?error=password_length');
                exit();
            }
            
            $passwordToUpdate = md5($new_password);
        }

        // Check if username or email already exists for other users
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
        $checkStmt->execute([$username, $email, $userid]);
        
        if ($checkStmt->rowCount() > 0) {
            $existing = $checkStmt->fetch();
            if ($existing['user_id'] !== $userid) {
                header('Location: ../site/profile.php?error=username_exists');
                exit();
            }
        }

        // Handle image upload
        $imageName = $_SESSION['image']; // Keep current image by default
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../image/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $fileType = $_FILES['image']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                header('Location: ../site/profile.php?error=image_format');
                exit();
            }
            
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
                header('Location: ../site/profile.php?error=image_large');
                exit();
            }
            
            $newImageName = uploadImage($_FILES['image'], $uploadDir, 'user_');
            if ($newImageName) {
                // Delete old image if it exists
                if (!empty($imageName) && file_exists($uploadDir . $imageName)) {
                    unlink($uploadDir . $imageName);
                }
                $imageName = $newImageName;
            } else {
                header('Location: ../site/profile.php?error=upload_failed');
                exit();
            }
        }

        // Update user information
        $updateStmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, username = ?, email = ?, password = ?, image = ? WHERE user_id = ?");
        
        if ($updateStmt->execute([$firstname, $lastname, $username, $email, $passwordToUpdate, $imageName, $userid])) {
            // Update session variables
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['image'] = $imageName;
            
            header('Location: ../site/profile.php?success=updated');
        } else {
            header('Location: ../site/profile.php?error=database');
        }
        
    } catch (PDOException $e) {
        header('Location: ../site/profile.php?error=database');
    }
} else {
    header('Location: ../site/profile.php');
}
?>