<?php
session_start();
// If user is already logged in, redirect them
if (isset($_SESSION['userid'])) {
    header('Location: index.php');
    exit();
}

$page_title = 'Register - OXXA GEAR';
include('../include/header.php');
?>

<!-- SUPIRI UNIFIED THEME: Premium Split-Screen Registration -->
<div class="min-h-screen flex flex-col md:flex-row bg-white mt-[72px]">
    
    <!-- Left Column: Branding (Hidden on mobile) -->
    <div class="hidden md:flex md:w-5/12 lg:w-1/2 bg-[#0B1120] relative flex-col justify-between p-12 lg:p-20 overflow-hidden">
        <!-- Abstract glowing orbs -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-[#0A6CFF] opacity-20 blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-[#D4FF00] opacity-10 blur-[100px]"></div>
            <!-- Dynamic Grid Pattern -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wNSkiLz48L3N2Zz4=')] [mask-image:linear-gradient(to_bottom,white,transparent)]"></div>
        </div>

        <div class="relative z-10">
            <a href="index.php" class="inline-block">
                <h1 class="text-4xl font-black text-white tracking-tighter">OXXA<span class="text-[#0A6CFF]">GEAR</span></h1>
            </a>
        </div>

        <div class="relative z-10 my-auto">
            <span class="inline-block py-1 px-3 rounded-full bg-white/10 border border-white/20 text-[#D4FF00] text-xs font-bold uppercase tracking-widest mb-6 backdrop-blur-sm">Join the Elite</span>
            <h2 class="text-5xl lg:text-7xl font-black text-white uppercase tracking-tight leading-[0.9] mb-6">
                Create<br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-500">Your</span><br>
                Legacy
            </h2>
            <p class="text-gray-400 text-lg max-w-md font-medium">Equip yourself with premium sports gear. Track orders, earn rewards, and access exclusive drops.</p>
        </div>

        <div class="relative z-10">
            <div class="flex items-center gap-4 text-gray-500 text-sm font-bold uppercase tracking-wider">
                <i class="fas fa-check-circle text-[#0A6CFF]"></i> 100% Authentic
                <i class="fas fa-check-circle text-[#0A6CFF] ml-4"></i> Islandwide Delivery
            </div>
        </div>
    </div>

    <!-- Right Column: Form -->
    <div class="w-full md:w-7/12 lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-20 bg-gray-50/50">
        <div class="w-full max-w-xl">
            <!-- Mobile Header -->
            <div class="md:hidden text-center mb-10">
                <h2 class="text-3xl font-black text-navy uppercase tracking-tight mb-2">Create Account</h2>
                <p class="text-gray-500 text-sm">Join us for premium sports gear & equipment</p>
            </div>

            <div class="bg-white rounded-[2rem] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.05)] border border-gray-100 p-8 sm:p-10">
                
                <form action="<?php echo $base_path; ?>Backend/register-backend.php" method="POST" id="modalRegisterForm" class="space-y-6" enctype="multipart/form-data">
                    
                    <!-- Premium Profile Photo Upload -->
                    <div class="flex flex-col items-center mb-8">
                        <div class="relative group cursor-pointer" onclick="document.getElementById('modalProfileUpload').click()">
                            <div id="modalPhotoPreview" class="w-24 h-24 rounded-full bg-gray-50 shadow-inner border-2 border-dashed border-gray-200 group-hover:border-[#0A6CFF] group-hover:bg-blue-50 flex flex-col items-center justify-center transition-all duration-300 overflow-hidden relative">
                                <i class="fas fa-camera text-gray-400 group-hover:text-[#0A6CFF] text-2xl transition-colors mb-1"></i>
                                <span class="text-[9px] font-bold text-gray-400 group-hover:text-[#0A6CFF] uppercase tracking-widest">Upload</span>
                                
                                <!-- Hover Overlay for existing image -->
                                <div class="absolute inset-0 bg-navy/60 flex items-center justify-center opacity-0 transition-opacity duration-300 z-10" id="photoHoverOverlay">
                                    <i class="fas fa-pen text-white"></i>
                                </div>
                            </div>
                            <!-- Success Badge -->
                            <div class="absolute -bottom-1 -right-1 w-7 h-7 bg-lime text-navy rounded-full flex items-center justify-center border-2 border-white shadow-sm scale-0 transition-transform duration-300" id="photoSuccessBadge">
                                <i class="fas fa-check text-xs"></i>
                            </div>
                        </div>
                        <input type="file" name="profile_photo" id="modalProfileUpload" accept="image/jpeg, image/png, image/webp" class="hidden" onchange="previewModalImage(this)">
                        <span id="errPhoto" class="text-red-500 text-[10px] mt-2 hidden font-bold"></span>
                    </div>

                    <!-- Name Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">First Name <span class="text-[#0A6CFF]">*</span></label>
                            <input type="text" id="regFirstname" name="firstname" required class="block w-full h-[52px] px-4 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none placeholder-gray-400" placeholder="John">
                            <span id="errFirstname" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">Last Name <span class="text-[#0A6CFF]">*</span></label>
                            <input type="text" id="regLastname" name="lastname" required class="block w-full h-[52px] px-4 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none placeholder-gray-400" placeholder="Doe">
                            <span id="errLastname" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Username -->
                        <div>
                            <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">Username <span class="text-[#0A6CFF]">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-at text-gray-400"></i>
                                </div>
                                <input type="text" id="regUsername" name="username" required oninput="this.value=this.value.toLowerCase()" class="block w-full h-[52px] pl-11 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none placeholder-gray-400" placeholder="johndoe123">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                    <i id="usernameIcon" class="fas fa-spinner fa-spin text-[#0A6CFF] hidden"></i>
                                </div>
                            </div>
                            <span id="errUsername" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">Phone Number <span class="text-[#0A6CFF]">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-phone-alt text-gray-400"></i>
                                </div>
                                <input type="text" id="regPhone" name="phone" required class="block w-full h-[52px] pl-11 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none placeholder-gray-400" placeholder="07XXXXXXXX">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                    <i id="phoneIcon" class="fas fa-spinner fa-spin text-[#0A6CFF] hidden"></i>
                                </div>
                            </div>
                            <span id="errPhone" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">Email Address <span class="text-[#0A6CFF]">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="regEmail" name="email" required class="block w-full h-[52px] pl-11 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none placeholder-gray-400" placeholder="john@example.com">
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <i id="emailIcon" class="fas fa-spinner fa-spin text-[#0A6CFF] hidden"></i>
                            </div>
                        </div>
                        <span id="errEmail" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                    </div>

                    <!-- Premium User Type Selector -->
                    <div class="mt-2 mb-4">
                        <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-3">Account Type <span class="text-[#0A6CFF]">*</span></label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative h-[72px] px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:border-[#0A6CFF]/50 transition-all duration-300 has-[:checked]:border-[#0A6CFF] has-[:checked]:bg-[#0A6CFF]/5 has-[:checked]:shadow-[0_0_0_4px_rgba(10,108,255,0.1)] group flex items-center gap-3">
                                <input type="radio" name="user_type" value="customer" id="roleCustomer" class="hidden peer" required>
                                <div class="w-10 h-10 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 text-lg group-hover:text-[#0A6CFF] peer-checked:text-[#0A6CFF] peer-checked:bg-white transition-colors flex-shrink-0">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div class="flex flex-col">
                                    <h5 class="font-black text-navy tracking-wide text-xs leading-tight mb-0.5">CUSTOMER</h5>
                                    <p class="text-[10px] text-gray-500 font-medium leading-tight">Shop Premium Gear</p>
                                </div>
                                <div class="absolute top-2 right-2 w-4 h-4 bg-[#0A6CFF] rounded-full text-white items-center justify-center text-[8px] opacity-0 peer-checked:opacity-100 transition-opacity flex shadow-sm">
                                    <i class="fas fa-check"></i>
                                </div>
                            </label>
                            
                            <label class="relative h-[72px] px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:border-[#0A6CFF]/50 transition-all duration-300 has-[:checked]:border-[#0A6CFF] has-[:checked]:bg-[#0A6CFF]/5 has-[:checked]:shadow-[0_0_0_4px_rgba(10,108,255,0.1)] group flex items-center gap-3">
                                <input type="radio" name="user_type" value="seller" id="roleSeller" class="hidden peer" required>
                                <div class="w-10 h-10 rounded-lg bg-white shadow-sm flex items-center justify-center text-gray-400 text-lg group-hover:text-[#0A6CFF] peer-checked:text-[#0A6CFF] peer-checked:bg-white transition-colors flex-shrink-0">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div class="flex flex-col">
                                    <h5 class="font-black text-navy tracking-wide text-xs leading-tight mb-0.5">SELLER</h5>
                                    <p class="text-[10px] text-gray-500 font-medium leading-tight">Start Selling</p>
                                </div>
                                <div class="absolute top-2 right-2 w-4 h-4 bg-[#0A6CFF] rounded-full text-white items-center justify-center text-[8px] opacity-0 peer-checked:opacity-100 transition-opacity flex shadow-sm">
                                    <i class="fas fa-check"></i>
                                </div>
                            </label>
                        </div>
                        <span id="errRole" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                    </div>

                    <!-- Password Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">Password <span class="text-[#0A6CFF]">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="regPassword" name="password" required class="block w-full h-[52px] pl-11 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                                <button type="button" onclick="togglePasswordVisibility('regPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-[#0A6CFF] transition-colors focus:outline-none">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <!-- Password Strength Indicator -->
                            <div class="flex gap-1 mt-2 h-1.5 w-full rounded-full overflow-hidden bg-gray-100">
                                <div id="pwStrength1" class="h-full w-1/3 transition-colors duration-300"></div>
                                <div id="pwStrength2" class="h-full w-1/3 transition-colors duration-300"></div>
                                <div id="pwStrength3" class="h-full w-1/3 transition-colors duration-300"></div>
                            </div>
                            <span id="errPassword" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-navy uppercase tracking-widest mb-2">Confirm Password <span class="text-[#0A6CFF]">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="regConfirm" name="confirm" required class="block w-full h-[52px] pl-11 pr-10 bg-gray-50 border border-gray-200 rounded-xl text-navy font-semibold text-sm focus:bg-white focus:border-[#0A6CFF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                                <button type="button" onclick="togglePasswordVisibility('regConfirm', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-[#0A6CFF] transition-colors focus:outline-none">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <span id="errConfirm" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                        </div>
                    </div>

                    <!-- Checkboxes -->
                    <div class="space-y-4 pt-2 border-t border-gray-100">
                        <label class="flex items-start cursor-pointer group">
                            <div class="relative flex items-center justify-center mt-0.5">
                                <input type="checkbox" name="terms" id="regTerms" required class="peer appearance-none w-5 h-5 border-2 border-gray-300 rounded md checked:bg-[#0A6CFF] checked:border-[#0A6CFF] transition-colors cursor-pointer shadow-sm">
                                <i class="fas fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                            </div>
                            <span class="ml-3 text-xs text-gray-500 font-medium leading-tight">I agree to the <a href="#" class="font-bold text-navy hover:text-[#0A6CFF] underline decoration-gray-300 hover:decoration-[#0A6CFF] underline-offset-2 transition-colors">Terms of Service</a> and <a href="#" class="font-bold text-navy hover:text-[#0A6CFF] underline decoration-gray-300 hover:decoration-[#0A6CFF] underline-offset-2 transition-colors">Privacy Policy</a> <span class="text-[#0A6CFF]">*</span></span>
                        </label>
                        <span id="errTerms" class="text-red-500 text-[10px] ml-8 hidden font-bold block"></span>

                        <label class="flex items-start cursor-pointer group">
                            <div class="relative flex items-center justify-center mt-0.5">
                                <input type="checkbox" name="newsletter" id="regNewsletter" value="1" checked class="peer appearance-none w-5 h-5 border-2 border-gray-300 rounded md checked:bg-[#0A6CFF] checked:border-[#0A6CFF] transition-colors cursor-pointer shadow-sm">
                                <i class="fas fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                            </div>
                            <span class="ml-3 text-xs text-gray-500 font-medium leading-tight">Subscribe to the OXXA Newsletter for exclusive drops, early access, and special offers</span>
                        </label>
                    </div>



                    <!-- Submit Button -->
                    <button type="submit" id="regSubmitBtn" name="register" class="w-full h-[56px] bg-[#0B1120] hover:bg-[#0A6CFF] text-white font-black text-sm uppercase tracking-widest rounded-xl transition-all duration-300 shadow-lg hover:shadow-[0_10px_30px_rgba(10,108,255,0.3)] hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-2">
                        <span>Create Account</span>
                        <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                </form>
                
                <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                    <p class="text-sm text-gray-500 font-medium">Already have an account? <a href="#" onclick="if(typeof openAuthModal === 'function'){ openAuthModal('login'); return false; } else { window.location.href='index.php?open=login'; return false; }" class="font-black text-[#0A6CFF] hover:text-navy transition-colors uppercase tracking-wider text-xs ml-1">Log In Here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Enhanced Image Preview with Badge
    function previewModalImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('modalPhotoPreview');
                // Create image element or update existing
                var img = preview.querySelector('img');
                if (!img) {
                    img = document.createElement('img');
                    img.className = 'w-full h-full object-cover rounded-full relative z-0';
                    preview.insertBefore(img, preview.firstChild);
                }
                img.src = e.target.result;
                
                // Hide default icon and text
                const icon = preview.querySelector('.fa-camera');
                const text = preview.querySelector('span');
                if(icon) icon.style.display = 'none';
                if(text) text.style.display = 'none';
                
                // Show hover overlay element
                const overlay = document.getElementById('photoHoverOverlay');
                if(overlay) {
                    preview.classList.add('group');
                    overlay.classList.add('group-hover:opacity-100');
                }
                
                // Pop success badge
                const badge = document.getElementById('photoSuccessBadge');
                if(badge) badge.classList.replace('scale-0', 'scale-100');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<script src="<?php echo $base_path; ?>assets/js/register-validation.js?v=<?php echo time(); ?>"></script>
<script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

<?php include('../include/footer.php'); ?>