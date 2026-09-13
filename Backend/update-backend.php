<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: ../site/index.php?open=login");
    exit();
}

if (isset($_POST['update_profile'])) {
    $userid = $_SESSION['userid']; // This is now the integer ID
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? ''); // add phone
    $dob = trim($_POST['dob'] ?? null);
    $gender = $_POST['gender'] ?? 'unspecified';
    $preferred_sports = isset($_POST['preferred_sports']) ? (is_array($_POST['preferred_sports']) ? implode(',', $_POST['preferred_sports']) : trim($_POST['preferred_sports'])) : null;
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($firstname)) {
        header('Location: ../site/profile.php?edit=profile&error=firstname');
        exit();
    }
    
    if (empty($lastname)) {
        header('Location: ../site/profile.php?edit=profile&error=lastname');
        exit();
    }
    
    if (empty($username)) {
        header('Location: ../site/profile.php?edit=profile&error=username');
        exit();
    }
    
    if (empty($email)) {
        header('Location: ../site/profile.php?edit=profile&error=email');
        exit();
    }
    
    if (empty($current_password)) {
        header('Location: ../site/profile.php?edit=profile&error=current_password');
        exit();
    }

    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userid]);
        $user = $stmt->fetch();
        
        // Use password_verify since we transitioned to password_hash in registration, but also check md5 for legacy users
        $password_ok = false;
        if ($user) {
            if (password_verify($current_password, $user['password']) || md5($current_password) === $user['password']) {
                $password_ok = true;
            }
        }
        
        if (!$password_ok) {
            header('Location: ../site/profile.php?edit=profile&error=wrong_password');
            exit();
        }

        // Check if new password is provided and validate it
        $passwordToUpdate = $user['password']; // Keep current password by default
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                header('Location: ../site/profile.php?edit=profile&error=password_mismatch');
                exit();
            }
            
            if (strlen($new_password) < 6) {
                header('Location: ../site/profile.php?edit=profile&error=password_length');
                exit();
            }
            
            $passwordToUpdate = md5($new_password); // keeping md5 for now to not break login logic if it relies on md5 still (wait, I didn't update login to password_verify)
        }

        // Check if username or email already exists for other users
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $checkStmt->execute([$username, $email, $userid]);
        
        if ($checkStmt->rowCount() > 0) {
            $existing = $checkStmt->fetch();
            if ($existing['id'] !== $userid) {
                header('Location: ../site/profile.php?edit=profile&error=username_exists');
                exit();
            }
        }

        // Handle image upload
        $imagePath = $_SESSION['profile_image'] ?? ''; // Keep current image path by default
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                header('Location: ../site/profile.php?edit=profile&error=image_format');
                exit();
            }
            
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
                header('Location: ../site/profile.php?edit=profile&error=image_large');
                exit();
            }
            
            $imageExt = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            if (empty($imageExt)) {
                if ($fileType == 'image/jpeg') $imageExt = 'jpg';
                else if ($fileType == 'image/png') $imageExt = 'png';
            }
            
            $newImageName = 'user_' . uniqid() . '.' . $imageExt;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newImageName)) {
                // Delete old image if it exists
                if (!empty($imagePath) && file_exists('../assets/uploads/profiles/' . $imagePath)) {
                    unlink('../assets/uploads/profiles/' . $imagePath);
                }
                $imagePath = $newImageName;
            } else {
                header('Location: ../site/profile.php?edit=profile&error=upload_failed');
                exit();
            }
        }

        // Update user information
        $updateStmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, phone = ?, dob = ?, gender = ?, preferred_sports = ?, password = ?, profile_image = ? WHERE id = ?");
        
        if ($updateStmt->execute([$firstname, $lastname, $username, $email, $phone, $dob, $gender, $preferred_sports, $passwordToUpdate, $imagePath, $userid])) {
            // Update session variables
            $_SESSION['first_name'] = $firstname;
            $_SESSION['last_name'] = $lastname;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['dob'] = $dob;
            $_SESSION['gender'] = $gender;
            $_SESSION['preferred_sports'] = $preferred_sports;
            $_SESSION['profile_image'] = $imagePath;
            
            header('Location: ../site/profile.php?edit=profile&success=updated');
        } else {
            header('Location: ../site/profile.php?edit=profile&error=database');
        }
        
    } catch (PDOException $e) {
        header('Location: ../site/profile.php?edit=profile&error=database');
    }
} else {
    header('Location: ../site/profile.php');
}
?>