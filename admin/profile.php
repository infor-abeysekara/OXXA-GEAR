<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['userid'] ?? 0;
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    // Check if email already exists for another user
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkStmt->execute([$email, $admin_id]);
    if ($checkStmt->rowCount() > 0) {
        $error_msg = "Email is already in use by another account.";
    } else {
        $profile_image = $_SESSION['profile_image'] ?? '';
        
        // Handle Image Upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $profile_image = $new_filename;
                } else {
                    $error_msg = "Failed to upload image.";
                }
            } else {
                $error_msg = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
            }
        }
        
        if (empty($error_msg)) {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, password=?, profile_image=? WHERE id=?");
                $stmt->execute([$first_name, $last_name, $email, $hashed_password, $profile_image, $admin_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, profile_image=? WHERE id=?");
                $stmt->execute([$first_name, $last_name, $email, $profile_image, $admin_id]);
            }
            
            // Update session
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['profile_image'] = $profile_image;
            
            $success_msg = "Profile updated successfully!";
        }
    }
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #f0f0f0; border-radius: 15px 15px 0 0 !important; padding: 20px 25px; }
        
        .profile-img-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 102, 255, 0.25);
            border-color: #0066FF;
        }
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <?php include("components/topbar.php"); ?>

        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold text-dark">My Profile</h2>
                    <p class="text-muted">Manage your admin account details.</p>
                </div>
            </div>

            <?php if(!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0 fw-bold">Update Profile Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="" method="POST" enctype="multipart/form-data">
                                
                                <div class="text-center mb-5">
                                    <?php if(!empty($admin['profile_image'])): ?>
                                        <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($admin['profile_image']); ?>" id="previewImg" class="profile-img-preview mb-3">
                                    <?php else: ?>
                                        <div class="mx-auto profile-img-preview mb-3 d-flex align-items-center justify-content-center bg-primary text-white fs-1 fw-bold">
                                            <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <label for="profile_image" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                                            <i class="fas fa-camera me-2"></i> Change Photo
                                        </label>
                                        <input type="file" name="profile_image" id="profile_image" class="d-none" accept="image/*">
                                    </div>
                                    <small class="text-muted mt-2 d-block">Recommended: Square image, max 2MB.</small>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    </div>
                                    
                                    <div class="col-12 mt-4">
                                        <hr class="text-muted">
                                        <h6 class="fw-bold mb-3">Change Password</h6>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">New Password</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                                    </div>
                                    
                                    <div class="col-12 mt-4 text-end">
                                        <button type="submit" name="update_profile" class="btn btn-primary px-5 py-2 rounded-pill fw-bold">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview logic
        document.getElementById('profile_image').addEventListener('change', function(e) {
            if(this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('previewImg');
                    if(preview) {
                        preview.src = e.target.result;
                    } else {
                        // If it was the letter block, replace it with img
                        const container = document.querySelector('.profile-img-preview').parentNode;
                        container.innerHTML = `<img src="${e.target.result}" id="previewImg" class="profile-img-preview mb-3">` + container.innerHTML.replace(/<div.*?<\/div>/s, '');
                        // re-bind listener if needed, but not strictly necessary for simple preview
                    }
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>
