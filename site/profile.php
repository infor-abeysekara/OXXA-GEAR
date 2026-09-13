<?php
$page_title = 'Update Profile - OXXA GEAR';
include('../include/header.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

// Get user data
include_once("../include/connection.php");
$userid = $_SESSION['userid'];
$user_query = "SELECT * FROM users WHERE user_id = '$userid'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);
?>

<div class="container my-10 max-w-7xl mx-auto px-4">
    <!-- Breadcrumbs -->
    <div class="mb-6 flex items-center text-sm font-medium text-slate">
        <a href="index.php" class="hover:text-primary transition-colors">Home</a>
        <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
        <span class="text-navy">My Profile</span>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
                <!-- Header Area -->
                <div class="bg-navy p-8 md:p-10 relative overflow-hidden">
                    <div class="absolute top-0 right-0 transform translate-x-1/4 -translate-y-1/4 w-64 h-64 bg-primary/20 rounded-full blur-3xl"></div>
                    <div class="relative z-10 flex flex-col items-center text-center">
                        <h4 class="text-3xl font-extrabold text-white uppercase tracking-wider mb-2">
                            My <span class="text-primary">Profile</span>
                        </h4>
                        <p class="text-gray-400">Update your account settings and personal details</p>
                    </div>
                </div>
                
                <div class="p-8 md:p-10">
                    <?php
                    // Display success messages
                    if (isset($_GET['success'])) {
                        if ($_GET['success'] == 'updated') {
                            echo '<div class="bg-lime/20 border border-lime text-navy px-4 py-3 rounded-xl mb-6 font-medium flex items-center"><i class="fas fa-check-circle text-lime me-2"></i> Profile updated successfully!</div>';
                        }
                    }

                    // Display error messages
                    if (isset($_GET['error'])) {
                        $error = $_GET['error'];
                        $error_message = '';
                        switch ($error) {
                            case 'firstname':
                                $error_message = 'First name is required';
                                break;
                            case 'lastname':
                                $error_message = 'Last name is required';
                                break;
                            case 'username':
                                $error_message = 'Username is required';
                                break;
                            case 'email':
                                $error_message = 'Email is required';
                                break;
                            case 'current_password':
                                $error_message = 'Current password is required';
                                break;
                            case 'wrong_password':
                                $error_message = 'Current password is incorrect';
                                break;
                            case 'password_mismatch':
                                $error_message = 'New passwords do not match';
                                break;
                            case 'password_length':
                                $error_message = 'New password must be at least 6 characters long';
                                break;
                            case 'username_exists':
                                $error_message = 'Username already exists';
                                break;
                            case 'email_exists':
                                $error_message = 'Email already exists';
                                break;
                            case 'image_format':
                                $error_message = 'Invalid image format. Use JPG, JPEG, or PNG';
                                break;
                            case 'image_large':
                                $error_message = 'Image size too large. Maximum 5MB allowed';
                                break;
                            case 'upload_failed':
                                $error_message = 'Failed to upload image';
                                break;
                            case 'database':
                                $error_message = 'Database error occurred';
                                break;
                            default:
                                $error_message = 'An error occurred';
                        }
                        echo '<div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 font-medium flex items-center"><i class="fas fa-exclamation-circle text-red-500 me-2"></i> ' . $error_message . '</div>';
                    }
                    ?>

                    <form action="../Backend/update-backend.php" method="POST" enctype="multipart/form-data" id="updateForm">
                        
                        <!-- Profile Image -->
                        <div class="mb-10 text-center relative">
                            <div class="inline-block relative">
                                <div class="w-32 h-32 rounded-full border-4 border-white shadow-lg overflow-hidden bg-gray-50 flex items-center justify-center group relative cursor-pointer" onclick="document.getElementById('image').click()">
                                    <?php if (!empty($user['image'])): ?>
                                        <img id="imagePreview" src="../image/profile/<?php echo $user['image']; ?>"
                                            class="w-full h-full object-cover object-center transition-transform duration-300 group-hover:scale-110"
                                            onerror="this.onerror=null; this.src='https://via.placeholder.com/120x120/6c757d/ffffff?text=No+Image'; this.parentNode.querySelector('.no-image-icon').style.display='flex'; this.style.display='none';">
                                        <div class="no-image-icon hidden align-items-center justify-content-center w-full h-full absolute top-0 left-0 bg-gray-200">
                                            <i class="fas fa-user text-gray-400 text-5xl"></i>
                                        </div>
                                    <?php else: ?>
                                        <div id="imagePreview" class="w-full h-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-300 text-5xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Hover Overlay -->
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fas fa-camera text-white text-2xl"></i>
                                    </div>
                                </div>
                                <label for="image" class="absolute bottom-1 right-1 w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center shadow-md cursor-pointer hover:bg-primary-hover transition-colors border-2 border-white">
                                    <i class="fas fa-pencil-alt text-sm"></i>
                                </label>
                            </div>
                            <input type="file" id="image" name="image" accept="image/*" class="hidden">
                            <p class="text-xs text-slate mt-3">Allowed: JPG, JPEG, PNG (Max 5MB)<br>Recommended size: 500x500px</p>
                        </div>

                        <!-- Personal Details -->
                        <h5 class="font-bold text-navy uppercase tracking-wide mb-4 pb-2 border-b border-gray-100 text-sm">Personal Information</h5>
                        <div class="row g-4 mb-6">
                            <!-- First Name -->
                            <div class="col-md-6">
                                <label for="firstname" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">First Name <span class="text-primary">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                                </div>
                            </div>

                            <!-- Last Name -->
                            <div class="col-md-6">
                                <label for="lastname" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Last Name <span class="text-primary">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                                </div>
                            </div>

                            <!-- Username -->
                            <div class="col-md-6">
                                <label for="username" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Username <span class="text-primary">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                        <i class="fas fa-at"></i>
                                    </span>
                                    <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Email Address <span class="text-primary">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>

                            <!-- Account Type (Display Only) -->
                            <div class="col-md-12">
                                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Account Role</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                        <i class="fas fa-user-tag"></i>
                                    </span>
                                    <input type="text" class="w-full bg-gray-100 border border-gray-200 text-gray-500 font-bold rounded-xl py-3 pl-11 pr-4 cursor-not-allowed" value="<?php echo ucfirst($user['type']); ?>" readonly>
                                </div>
                                <p class="text-xs text-slate mt-2"><i class="fas fa-info-circle text-primary me-1"></i> Account type cannot be changed after registration.</p>
                            </div>
                        </div>

                        <!-- Security -->
                        <h5 class="font-bold text-navy uppercase tracking-wide mb-4 pb-2 border-b border-gray-100 text-sm mt-8">Security & Verification</h5>
                        <div class="row g-4 mb-6">
                            <!-- Current Password -->
                            <div class="col-md-12">
                                <label for="current_password" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Current Password <span class="text-primary">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-12 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors" id="current_password" name="current_password" required placeholder="Required to save changes">
                                    <button class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-400 hover:text-primary transition-colors focus:outline-none" type="button" id="toggleCurrentPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Password Change -->
                        <div class="bg-blue-50 rounded-xl p-5 border border-blue-100 mb-8">
                            <h6 class="font-bold text-navy mb-4 text-sm flex items-center"><i class="fas fa-shield-alt text-primary me-2"></i> Change Password (Optional)</h6>
                            <div class="row g-4">
                                <!-- New Password -->
                                <div class="col-md-6">
                                    <label for="new_password" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">New Password</label>
                                    <div class="relative">
                                        <input type="password" class="w-full bg-white border border-gray-200 text-navy font-medium rounded-lg py-2.5 px-4 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors pr-10" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                                        <button class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-primary transition-colors focus:outline-none" type="button" id="toggleNewPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Confirm New Password -->
                                <div class="col-md-6">
                                    <label for="confirm_password" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Confirm New Password</label>
                                    <div class="relative">
                                        <input type="password" class="w-full bg-white border border-gray-200 text-navy font-medium rounded-lg py-2.5 px-4 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors pr-10" id="confirm_password" name="confirm_password">
                                        <button class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-primary transition-colors focus:outline-none" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 flex flex-col md:flex-row gap-4">
                            <button type="submit" name="update_profile" class="w-full md:w-auto bg-primary hover:bg-primary-hover text-white px-8 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-md hover:shadow-lg flex items-center justify-center flex-grow">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                            <a href="../index.php" class="w-full md:w-auto bg-gray-100 hover:bg-gray-200 text-navy px-8 py-3 rounded-xl font-bold uppercase tracking-wide transition-colors flex items-center justify-center text-center">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Image preview functionality
    document.getElementById('image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('imagePreview');

                // Create new image element or update existing one
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    // Replace div with img element
                    const img = document.createElement('img');
                    img.id = 'imagePreview';
                    img.src = e.target.result;
                    img.className = 'w-full h-full object-cover object-center transition-transform duration-300 group-hover:scale-110';
                    preview.parentNode.replaceChild(img, preview);
                }
            }
            reader.readAsDataURL(file);
        }
    });

    // Toggle password visibility functions
    function togglePasswordVisibility(inputId, buttonId) {
        document.getElementById(buttonId).addEventListener('click', function() {
            const password = document.getElementById(inputId);
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }

    togglePasswordVisibility('current_password', 'toggleCurrentPassword');
    togglePasswordVisibility('new_password', 'toggleNewPassword');
    togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');

    // Form validation
    document.getElementById('updateForm').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword && newPassword !== confirmPassword) {
            e.preventDefault();
            if(typeof showToast === 'function') {
                showToast('New passwords do not match!', 'error');
            } else {
                alert('New passwords do not match!');
            }
            return false;
        }

        if (newPassword && newPassword.length < 6) {
            e.preventDefault();
            if(typeof showToast === 'function') {
                showToast('New password must be at least 6 characters long!', 'error');
            } else {
                alert('New password must be at least 6 characters long!');
            }
            return false;
        }
    });
</script>

<?php
include("../include/footer.php");
?>