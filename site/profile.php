<?php
$page_title = 'My Profile - OXXA GEAR';
include('../include/header.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: index.php?open=login");
    exit();
}
session_write_close(); // Free session lock for parallel AJAX requests

// Get user data
include_once("../include/connection.php");
$userid = $_SESSION['userid'];

// Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$isEditMode = isset($_GET['edit']) && $_GET['edit'] == 'profile';

// Fetch Default Address
$addressStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? AND is_default_shipping = 1 LIMIT 1");
$addressStmt->execute([$userid]);
$address = $addressStmt->fetch(PDO::FETCH_ASSOC);

if (!$address) {
    $addressStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? LIMIT 1");
    $addressStmt->execute([$userid]);
    $address = $addressStmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch Recent Orders
$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$ordersStmt->execute([$userid]);
$recentOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <div class="mb-6 flex items-center text-sm font-medium text-slate">
            <a href="index.php" class="hover:text-primary transition-colors">Home</a>
            <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
            <span class="text-navy">My Profile</span>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Sidebar -->
            <div class="w-full lg:w-[260px] flex-shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <!-- Profile Header in Sidebar -->
                    <div class="p-6 text-center border-b border-gray-50 flex flex-col items-center">
                        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../assets/uploads/profiles/' . $user['profile_image'])): ?>
                            <img src="../assets/uploads/profiles/<?php echo $user['profile_image']; ?>" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-xl mb-4">
                        <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-black text-white flex items-center justify-center text-3xl font-black ring-4 ring-white shadow-xl mb-4">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="font-black text-navy text-lg uppercase tracking-wide"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <!-- Menu -->
                    <div class="p-2 lg:p-3 flex overflow-x-auto lg:flex-col gap-3 lg:gap-1 hide-scrollbar">
                        <a href="profile.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm transition-colors shrink-0 whitespace-nowrap <?php echo !$isEditMode ? 'bg-blue-50 text-[#0066FF]' : 'text-gray-600 hover:bg-gray-50 hover:text-[#0066FF]'; ?>">
                            <i class="far fa-user-circle w-6 text-lg"></i> My Profile
                        </a>
                        <a href="address-book.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-address-book w-6 text-lg"></i> Address Book
                        </a>
                        <a href="my-orders.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-shopping-bag w-6 text-lg"></i> My Orders
                        </a>
                        <a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-heart w-6 text-lg"></i> Wishlist
                        </a>
                        <a href="reviews.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-star w-6 text-lg"></i> My Reviews
                        </a>
                        <a href="returns.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-undo-alt w-6 text-lg"></i> My Returns
                        </a>
                        <a href="coupons.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-ticket-alt w-6 text-lg"></i> My Coupons
                        </a>
                        <a href="recently-viewed.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap lg:border-b border-gray-100 lg:pb-4 lg:mb-1">
                            <i class="far fa-eye w-6 text-lg"></i> Recently Viewed
                        </a>
                        
                        <?php if($user['user_type'] == 'seller'): ?>
                        <a href="seller-dashboard.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap lg:mt-2 lg:border-t border-gray-100 lg:pt-3">
                            <i class="fas fa-store w-6 text-lg"></i> Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Main Content -->
            <div class="flex-1">
                <?php if ($isEditMode): ?>
                    <!-- Edit Mode View -->
                    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden p-8 md:p-10">
                        <div class="flex justify-between items-center mb-8 border-b border-gray-100 pb-4">
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">Edit Profile</h2>
                            <a href="profile.php" class="text-sm font-bold text-gray-400 hover:text-black transition-colors"><i class="fas fa-times me-2"></i> Cancel</a>
                        </div>
                        
                        <?php
                        // Display messages
                        if (isset($_GET['success']) && $_GET['success'] == 'updated') {
                            echo '<div class="bg-lime/20 border border-lime text-navy px-4 py-3 rounded-xl mb-6 font-medium flex items-center"><i class="fas fa-check-circle text-lime me-2"></i> Profile updated successfully!</div>';
                        }
                        if (isset($_GET['error'])) {
                            $error = $_GET['error'];
                            $error_message = '';
                            switch ($error) {
                                case 'firstname': $error_message = 'First name is required'; break;
                                case 'lastname': $error_message = 'Last name is required'; break;
                                case 'username': $error_message = 'Username is required'; break;
                                case 'email': $error_message = 'Email is required'; break;
                                case 'current_password': $error_message = 'Current password is required'; break;
                                case 'wrong_password': $error_message = 'Current password is incorrect'; break;
                                case 'password_mismatch': $error_message = 'New passwords do not match'; break;
                                case 'password_length': $error_message = 'New password must be at least 6 characters long'; break;
                                case 'username_exists': $error_message = 'Username already exists'; break;
                                case 'email_exists': $error_message = 'Email already exists'; break;
                                case 'image_format': $error_message = 'Invalid image format. Use JPG, JPEG, or PNG'; break;
                                case 'image_large': $error_message = 'Image size too large. Maximum 5MB allowed'; break;
                                case 'upload_failed': $error_message = 'Failed to upload image'; break;
                                case 'database': $error_message = 'Database error occurred'; break;
                                default: $error_message = 'An error occurred';
                            }
                            echo '<div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 font-medium flex items-center"><i class="fas fa-exclamation-circle text-red-500 me-2"></i> ' . $error_message . '</div>';
                        }
                        ?>
                        
                        <form action="../Backend/update-backend.php" method="POST" enctype="multipart/form-data" id="updateForm">
                            
                            <!-- Profile Image Upload -->
                            <div class="mb-10 text-center relative">
                                <div class="inline-block relative">
                                    <div class="w-32 h-32 rounded-full border-4 border-white shadow-lg overflow-hidden bg-gray-50 flex items-center justify-center group relative cursor-pointer" onclick="document.getElementById('image').click()">
                                        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../assets/uploads/profiles/' . $user['profile_image'])): ?>
                                            <img id="imagePreview" src="../assets/uploads/profiles/<?php echo $user['profile_image']; ?>" class="w-full h-full object-cover object-center transition-transform duration-300 group-hover:scale-110">
                                            <div class="no-image-icon hidden align-items-center justify-content-center w-full h-full absolute top-0 left-0 bg-gray-200"><i class="fas fa-user text-gray-400 text-5xl"></i></div>
                                        <?php else: ?>
                                            <div id="imagePreview" class="w-full h-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-300 text-5xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <i class="fas fa-camera text-white text-2xl"></i>
                                        </div>
                                    </div>
                                    <label for="image" class="absolute bottom-1 right-1 w-10 h-10 bg-[#0066FF] text-white rounded-full flex items-center justify-center shadow-md cursor-pointer hover:bg-blue-700 transition-colors border-2 border-white">
                                        <i class="fas fa-pencil-alt text-sm"></i>
                                    </label>
                                </div>
                                <input type="file" id="image" name="image" accept="image/*" class="hidden">
                            </div>

                            <!-- Personal Details -->
                            <h5 class="font-bold text-navy uppercase tracking-wide mb-4 pb-2 border-b border-gray-100 text-sm">Personal Information</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="firstname" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">First Name <span class="text-[#0066FF]">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-user"></i></span>
                                        <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="lastname" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Last Name <span class="text-[#0066FF]">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-user"></i></span>
                                        <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="username" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Username <span class="text-[#0066FF]">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-at"></i></span>
                                        <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                    <span id="usernameFeedback" class="text-xs font-bold mt-1 hidden block"></span>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Email Address <span class="text-[#0066FF]">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Phone Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-phone-alt"></i></span>
                                        <input type="text" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div>
                                    <label for="dob" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Date of Birth</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="far fa-calendar-alt"></i></span>
                                        <input type="date" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="dob" name="dob" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div>
                                    <label for="gender" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Gender</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-venus-mars"></i></span>
                                        <select class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors appearance-none" id="gender" name="gender">
                                            <option value="unspecified" <?php echo ($user['gender'] == 'unspecified' || empty($user['gender'])) ? 'selected' : ''; ?>>Rather not say</option>
                                            <option value="male" <?php echo ($user['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($user['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($user['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 pointer-events-none"><i class="fas fa-chevron-down text-xs"></i></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Preferred Sports</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pt-3 text-gray-400 items-start"><i class="fas fa-running mt-1"></i></span>
                                        <div class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 min-h-[50px]">
                                            <?php 
                                            $userSports = !empty($user['preferred_sports']) ? explode(',', $user['preferred_sports']) : [];
                                            $allSports = ['Sports Wear', 'Footwear', 'Fitness & Gym', 'Nutrition', 'Accessories', 'Equipment'];
                                            ?>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach($allSports as $sport): ?>
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="preferred_sports[]" value="<?php echo htmlspecialchars($sport); ?>" class="peer hidden" <?php echo in_array($sport, $userSports) ? 'checked' : ''; ?>>
                                                    <span class="px-3 py-1 text-xs font-bold uppercase rounded-full border border-gray-200 text-gray-500 peer-checked:bg-[#0066FF] peer-checked:text-white peer-checked:border-[#0066FF] transition-colors"><?php echo htmlspecialchars($sport); ?></span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security -->
                            <h5 class="font-bold text-navy uppercase tracking-wide mb-4 pb-2 border-b border-gray-100 text-sm mt-8">Security & Verification</h5>
                            <div class="mb-6">
                                <div>
                                    <label for="current_password" class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Current Password <span class="text-[#0066FF]">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-12 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors" id="current_password" name="current_password" required placeholder="Required to save changes">
                                        <button class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-400 hover:text-[#0066FF] transition-colors focus:outline-none" type="button" id="toggleCurrentPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50 rounded-xl p-5 border border-blue-100 mb-8">
                                <h6 class="font-bold text-navy mb-4 text-sm flex items-center"><i class="fas fa-shield-alt text-[#0066FF] me-2"></i> Change Password (Optional)</h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="new_password" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">New Password</label>
                                        <div class="relative">
                                            <input type="password" class="w-full bg-white border border-gray-200 text-navy font-medium rounded-lg py-2.5 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors pr-10" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                                            <button class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-[#0066FF] transition-colors focus:outline-none" type="button" id="toggleNewPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="block text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Confirm New Password</label>
                                        <div class="relative">
                                            <input type="password" class="w-full bg-white border border-gray-200 text-navy font-medium rounded-lg py-2.5 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-colors pr-10" id="confirm_password" name="confirm_password">
                                            <button class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-[#0066FF] transition-colors focus:outline-none" type="button" id="toggleConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 flex flex-col md:flex-row gap-4">
                                <button type="submit" name="update_profile" id="profileSubmitBtn" class="w-full md:w-auto bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-md hover:shadow-lg flex items-center justify-center flex-grow">
                                    <i class="fas fa-save me-2" id="profileSubmitIcon"></i> <span id="profileSubmitText">Save Changes</span>
                                </button>
                                <a href="profile.php" class="w-full md:w-auto bg-gray-100 hover:bg-gray-200 text-navy px-8 py-3 rounded-xl font-bold uppercase tracking-wide transition-colors flex items-center justify-center text-center">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <script>
                        // Image preview functionality
                        document.getElementById('image').addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    const preview = document.getElementById('imagePreview');
                                    if (preview.tagName === 'IMG') {
                                        preview.src = e.target.result;
                                    } else {
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

                        // Toggle password visibility
                        function togglePasswordVisibility(inputId, buttonId) {
                            document.getElementById(buttonId).addEventListener('click', function() {
                                const password = document.getElementById(inputId);
                                const icon = this.querySelector('i');
                                if (password.type === 'password') {
                                    password.type = 'text';
                                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                                } else {
                                    password.type = 'password';
                                    icon.classList.replace('fa-eye-slash', 'fa-eye');
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
                                alert('New passwords do not match!');
                            } else if (newPassword && newPassword.length < 6) {
                                e.preventDefault();
                                alert('New password must be at least 6 characters long!');
                            }
                        });
                    </script>

                <?php else: ?>
                    <!-- Dashboard Default View -->
                    <?php if (isset($_GET['success']) && $_GET['success'] == 'updated'): ?>
                        <div class="bg-lime/20 border border-lime text-navy px-4 py-3 rounded-xl mb-6 font-medium flex items-center shadow-sm">
                            <i class="fas fa-check-circle text-lime me-2 text-lg"></i> Profile updated successfully!
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- Personal Profile Card -->
                        <div class="bg-white rounded-2xl md:rounded-[2rem] shadow-sm border border-gray-100 p-5 md:p-8 relative">
                            <div class="flex justify-between items-start mb-6">
                                <h3 class="text-base md:text-lg font-black text-navy uppercase tracking-wide">Personal Profile</h3>
                                <a href="profile.php?edit=profile" class="text-[#0066FF] font-bold text-sm hover:underline"><i class="fas fa-pen me-1 text-xs"></i> EDIT</a>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Full Name</p>
                                    <p class="text-navy font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Email Address</p>
                                    <?php 
                                        $emailParts = explode('@', $user['email']);
                                        $maskedEmail = substr($emailParts[0], 0, 2) . str_repeat('*', max(0, strlen($emailParts[0]) - 2)) . '@' . $emailParts[1];
                                    ?>
                                    <p class="text-navy font-bold flex items-center"><?php echo htmlspecialchars($maskedEmail); ?> <i class="fas fa-check-circle text-[#0066FF] ms-2 text-[10px]" title="Verified"></i></p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Phone Number</p>
                                    <?php 
                                        $phone = $user['phone'] ?? '';
                                        if (strpos($phone, '94') === 0 && strlen($phone) == 11) {
                                            $phone = '0' . substr($phone, 2);
                                        }
                                    ?>
                                    <p class="text-navy font-bold"><?php echo !empty($phone) ? htmlspecialchars($phone) : '<span class="text-gray-300 italic">Not added</span>'; ?></p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Date of Birth</p>
                                        <p class="text-navy font-bold text-sm"><?php echo !empty($user['dob']) ? date('M d, Y', strtotime($user['dob'])) : '<span class="text-gray-300 italic">Not added</span>'; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Gender</p>
                                        <p class="text-navy font-bold text-sm capitalize"><?php echo !empty($user['gender']) && $user['gender'] != 'unspecified' ? htmlspecialchars($user['gender']) : '<span class="text-gray-300 italic">Not added</span>'; ?></p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Preferred Sports</p>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        <?php 
                                        if (!empty($user['preferred_sports'])) {
                                            $sports = explode(',', $user['preferred_sports']);
                                            foreach($sports as $s) {
                                                echo '<span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">'.htmlspecialchars(trim($s)).'</span>';
                                            }
                                        } else {
                                            echo '<span class="text-gray-300 italic text-sm font-bold">Not specified</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-gray-100 flex justify-between items-center mt-2">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase">Member Since: <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                                </div>
                                <div class="pt-4 flex flex-col sm:flex-row gap-3">
                                    <a href="profile.php?edit=profile" class="flex-1 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-navy text-xs font-bold px-4 py-2 rounded-lg text-center uppercase tracking-wide transition-colors">
                                        <i class="fas fa-lock me-1"></i> Change Password
                                    </a>
                                    <button onclick="openDeleteAccountModal()" type="button" class="flex-1 bg-red-50 hover:bg-red-100 border border-red-100 text-red-500 text-xs font-bold px-4 py-2 rounded-lg text-center uppercase tracking-wide transition-colors">
                                        <i class="fas fa-trash-alt me-1"></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Address Book Card -->
                        <div class="bg-white rounded-2xl md:rounded-[2rem] shadow-sm border border-gray-100 p-5 md:p-8 relative flex flex-col">
                            <div class="flex justify-between items-start mb-6">
                                <h3 class="text-base md:text-lg font-black text-navy uppercase tracking-wide">Address Book</h3>
                                <a href="address-book.php" class="text-[#0066FF] font-bold text-sm hover:underline"><i class="fas fa-pen me-1 text-xs"></i> EDIT</a>
                            </div>
                            <?php if ($address): ?>
                                <div class="flex-grow">
                                    <div class="mb-4">
                                        <span class="bg-blue-50 text-[#0066FF] text-[10px] font-black px-2 py-1 rounded uppercase tracking-wide">Default Shipping & Billing</span>
                                    </div>
                                    <p class="text-navy font-bold mb-1 uppercase"><?php echo htmlspecialchars($address['full_name']); ?></p>
                                    <p class="text-gray-600 text-sm mb-1"><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                    <p class="text-gray-600 text-sm mb-1"><?php echo htmlspecialchars($address['city']); ?> - <?php echo htmlspecialchars($address['postal_code']); ?></p>
                                    <p class="text-gray-600 text-sm"><i class="fas fa-phone text-xs me-1"></i> <?php echo htmlspecialchars($address['phone1']); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="h-full flex flex-col items-center justify-center text-center flex-grow -mt-4">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                        <i class="far fa-map text-gray-300 text-2xl"></i>
                                    </div>
                                    <p class="text-gray-400 font-medium mb-3">No default address saved</p>
                                    <button class="px-6 py-2 border-2 border-gray-100 rounded-full text-sm font-bold text-gray-500 hover:border-[#0066FF] hover:text-[#0066FF] transition-colors">Add Address</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Orders Table -->
                    <div class="bg-white rounded-2xl md:rounded-[2rem] shadow-sm border border-gray-100 p-5 md:p-8 overflow-hidden">
                        <div class="flex justify-between items-center mb-6 border-b border-gray-50 pb-4">
                            <h3 class="text-base md:text-lg font-black text-navy uppercase tracking-wide">Recent Orders</h3>
                            <a href="my-orders.php" class="text-gray-400 font-bold text-xs hover:text-[#0066FF] transition-colors">VIEW ALL <i class="fas fa-chevron-right ms-1 text-[10px]"></i></a>
                        </div>
                        
                        <?php if (count($recentOrders) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse min-w-[600px]">
                                    <thead>
                                        <tr class="border-b border-gray-100">
                                            <th class="py-4 text-xs font-bold text-gray-400 uppercase tracking-wide">Order #</th>
                                            <th class="py-4 text-xs font-bold text-gray-400 uppercase tracking-wide">Date</th>
                                            <th class="py-4 text-xs font-bold text-gray-400 uppercase tracking-wide">Item</th>
                                            <th class="py-4 text-xs font-bold text-gray-400 uppercase tracking-wide">Status</th>
                                            <th class="py-4 text-xs font-bold text-gray-400 uppercase tracking-wide text-right">Total</th>
                                            <th class="py-4 text-xs font-bold text-gray-400 uppercase tracking-wide text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <?php foreach ($recentOrders as $order): ?>
                                            <?php
                                                // Fetch first item of the order to display as summary
                                                $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? LIMIT 1");
                                                $itemsStmt->execute([$order['id']]);
                                                $firstItem = $itemsStmt->fetch(PDO::FETCH_ASSOC);
                                                
                                                // Count total items
                                                $countStmt = $pdo->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = ?");
                                                $countStmt->execute([$order['id']]);
                                                $totalItems = $countStmt->fetchColumn() ?: 0;
                                            ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="py-4 font-bold text-sm text-navy">#<?php echo htmlspecialchars($order['order_code']); ?></td>
                                                <td class="py-4 text-gray-500 text-sm"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td class="py-4">
                                                    <?php if($firstItem): ?>
                                                    <div class="flex items-center gap-3">
                                                        <?php if(!empty($firstItem['product_image'])): ?>
                                                            <img src="../assets/uploads/products/<?php echo htmlspecialchars($firstItem['product_image']); ?>" class="w-12 h-12 rounded-lg object-cover bg-gray-100 border border-gray-100">
                                                        <?php else: ?>
                                                            <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center border border-gray-100"><i class="fas fa-box text-gray-300"></i></div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <span class="font-bold text-sm text-navy line-clamp-1 max-w-[200px]"><?php echo htmlspecialchars($firstItem['product_name']); ?></span>
                                                            <span class="text-xs text-gray-400 mt-0.5 block"><?php echo $totalItems > 1 ? '+' . ($totalItems - 1) . ' more items' : 'Qty: ' . $firstItem['quantity']; ?></span>
                                                        </div>
                                                    </div>
                                                    <?php else: ?>
                                                    <span class="text-sm text-gray-400">No items found</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4">
                                                    <?php 
                                                        $status = $order['status'];
                                                        $statusClass = '';
                                                        switch($status) {
                                                            case 'pending': $statusClass = 'bg-orange-50 text-orange-500 border-orange-100'; break;
                                                            case 'confirmed': $statusClass = 'bg-blue-50 text-[#0066FF] border-blue-100'; break;
                                                            case 'shipped': $statusClass = 'bg-purple-50 text-purple-600 border-purple-100'; break;
                                                            case 'delivered': $statusClass = 'bg-lime/20 text-lime border-lime/30'; break;
                                                            case 'cancelled': $statusClass = 'bg-red-50 text-red-500 border-red-100'; break;
                                                            default: $statusClass = 'bg-gray-50 text-gray-500 border-gray-100';
                                                        }
                                                    ?>
                                                    <span class="px-3 py-1 text-[10px] font-black uppercase rounded-full border <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                </td>
                                                <td class="py-4 font-black text-right text-[#0066FF]">Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td class="py-4 text-center">
                                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-50 hover:bg-blue-50 text-gray-400 hover:text-[#0066FF] transition-colors" title="View Details">
                                                        <i class="fas fa-chevron-right text-xs"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-shopping-bag text-gray-300 text-3xl"></i>
                                </div>
                                <h4 class="text-lg font-bold text-navy mb-2">No orders yet</h4>
                                <p class="text-gray-500 mb-6">Looks like you haven't made your first purchase.</p>
                                <a href="products.php" class="inline-block px-8 py-3 bg-[#0066FF] text-white font-bold rounded-full uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">Start Shopping</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const usernameInput = document.getElementById('username');
    const usernameFeedback = document.getElementById('usernameFeedback');
    let debounceTimer;

    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const username = this.value.trim();
            const originalUsername = '<?php echo htmlspecialchars($user['username'] ?? ''); ?>';
            
            if (username === '') {
                usernameFeedback.classList.add('hidden');
                return;
            }
            
            if (username === originalUsername) {
                usernameFeedback.textContent = 'Current username';
                usernameFeedback.className = 'text-xs font-bold mt-1 block text-gray-500';
                return;
            }
            
            usernameFeedback.textContent = 'Checking...';
            usernameFeedback.className = 'text-xs font-bold mt-1 block text-[#0066FF]';
            
            debounceTimer = setTimeout(() => {
                fetch(`../Backend/check-username.php?u=${encodeURIComponent(username)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            usernameFeedback.textContent = 'Username is available!';
                            usernameFeedback.className = 'text-xs font-bold mt-1 block text-green-500';
                        } else {
                            usernameFeedback.textContent = 'Username is already taken.';
                            usernameFeedback.className = 'text-xs font-bold mt-1 block text-red-500';
                        }
                    })
                    .catch(error => {
                        usernameFeedback.classList.add('hidden');
                    });
            }, 500);
        });
    }

    const updateForm = document.getElementById('updateForm');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('profileSubmitBtn');
            const submitIcon = document.getElementById('profileSubmitIcon');
            const submitText = document.getElementById('profileSubmitText');
            
            // UI Loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitIcon.className = 'fas fa-circle-notch fa-spin me-2';
            submitText.textContent = 'Processing...';
            
            const formData = new FormData(this);
            // Add the submit button name since JS fetch doesn't include it
            formData.append('update_profile', '1');
            
            fetch('../Backend/update-backend.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset UI state
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitIcon.className = 'fas fa-save me-2';
                submitText.textContent = 'Save Changes';
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    
                    // Clear password fields
                    document.getElementById('current_password').value = '';
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                    
                    // Optionally update the top nav or other components here
                    // If image was changed, preview is already showing the local file.
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#0066FF'
                    });
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitIcon.className = 'fas fa-save me-2';
                submitText.textContent = 'Save Changes';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again later.',
                    confirmButtonColor: '#0066FF'
                });
            });
        });
    }
});
</script>

<!-- Delete Account Modal -->
<div id="deleteAccountModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-8" onclick="event.stopPropagation()">
        <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-4">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="text-xl font-black text-navy uppercase tracking-wide mb-2">Delete Account?</h3>
            <p class="text-gray-500 text-sm mb-6">This action is permanent and cannot be undone. Please enter your password to confirm.</p>
            
            <div class="w-full relative mb-6">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><i class="fas fa-lock"></i></span>
                <input type="password" id="deleteAccountPassword" class="w-full bg-gray-50 border border-gray-200 text-navy font-medium rounded-xl py-3 pl-11 pr-4 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors" placeholder="Enter your password">
            </div>
            
            <div class="flex w-full gap-3">
                <button onclick="closeDeleteAccountModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-navy py-3 rounded-xl font-bold uppercase tracking-wide transition-colors text-sm">Cancel</button>
                <button onclick="confirmDeleteAccount()" id="confirmDeleteBtn" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold uppercase tracking-wide transition-colors text-sm shadow-md">
                    <span id="confirmDeleteText">Delete</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openDeleteAccountModal() {
    document.getElementById('deleteAccountModal').classList.remove('hidden');
    document.getElementById('deleteAccountPassword').value = '';
}

function closeDeleteAccountModal() {
    document.getElementById('deleteAccountModal').classList.add('hidden');
}

function confirmDeleteAccount() {
    const pwd = document.getElementById('deleteAccountPassword').value;
    if (!pwd) {
        Swal.fire({icon: 'warning', title: 'Wait!', text: 'Please enter your password first', confirmButtonColor: '#0066FF'});
        return;
    }
    
    const btn = document.getElementById('confirmDeleteBtn');
    const txt = document.getElementById('confirmDeleteText');
    btn.disabled = true;
    txt.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('../Backend/delete_account.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ password: pwd })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        txt.innerHTML = 'Delete';
        if(data.success) {
            window.location.href = 'index.php?success=account_deleted';
        } else {
            Swal.fire({icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0066FF'});
        }
    })
    .catch(e => {
        btn.disabled = false;
        txt.innerHTML = 'Delete';
        Swal.fire({icon: 'error', title: 'Error', text: 'Connection failed', confirmButtonColor: '#0066FF'});
    });
}
</script>

<?php
include("../include/footer.php");
?>