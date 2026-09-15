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

<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 mt-10">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <h2 class="mt-6 text-center text-4xl font-space font-black text-black uppercase tracking-wide">
            Create Your Legacy
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            Join us for premium sports gear & equipment
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-xl">
        <div class="bg-white py-10 px-6 shadow sm:rounded-2xl sm:px-12 border border-gray-100">
            
            <form action="<?php echo $base_path; ?>Backend/register-backend.php" method="POST" id="modalRegisterForm" class="space-y-6" enctype="multipart/form-data">
                
                <!-- Premium Profile Photo Upload -->
                <div class="flex flex-col items-center mb-6">
                    <div class="relative group cursor-pointer" onclick="document.getElementById('modalProfileUpload').click()">
                        <div id="modalPhotoPreview" class="w-24 h-24 rounded-full bg-white shadow-[0_8px_30px_rgba(0,0,0,0.12)] border-[3px] border-white ring-2 ring-gray-100 group-hover:ring-[#0066FF] flex flex-col items-center justify-center transition-all duration-300 overflow-hidden relative">
                            <i class="fas fa-camera text-gray-300 group-hover:text-[#0066FF] text-2xl transition-colors"></i>
                            
                            <!-- Hover Overlay -->
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="text-white text-[10px] font-bold uppercase tracking-widest">Change</span>
                            </div>
                        </div>
                    </div>
                    <input type="file" name="profile_photo" id="modalProfileUpload" accept="image/jpeg, image/png, image/webp" class="hidden" onchange="previewModalImage(this)">
                    <span id="errPhoto" class="text-red-500 text-[10px] mt-2 hidden font-bold"></span>
                </div>

                <!-- Name Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">First Name *</label>
                        <input type="text" id="regFirstname" name="firstname" required class="block w-full h-12 px-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                        <span id="errFirstname" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Last Name *</label>
                        <input type="text" id="regLastname" name="lastname" required class="block w-full h-12 px-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                        <span id="errLastname" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                    </div>
                </div>

                <!-- Username -->
                <div>
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Username *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-at text-gray-400"></i>
                        </div>
                        <input type="text" id="regUsername" name="username" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                            <i id="usernameIcon" class="fas fa-spinner fa-spin text-[#0066FF] hidden"></i>
                        </div>
                    </div>
                    <span id="errUsername" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Email Address *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="regEmail" name="email" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                            <i id="emailIcon" class="fas fa-spinner fa-spin text-[#0066FF] hidden"></i>
                        </div>
                    </div>
                    <span id="errEmail" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Phone Number *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-phone text-gray-400"></i>
                        </div>
                        <input type="text" id="regPhone" name="phone" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none" placeholder="07XXXXXXXX">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                            <i id="phoneIcon" class="fas fa-spinner fa-spin text-[#0066FF] hidden"></i>
                        </div>
                    </div>
                    <span id="errPhone" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                </div>

                <!-- Premium User Type Selector -->
                <div class="mt-4 mb-5">
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-3">I AM A *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative h-[68px] px-4 py-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#0066FF]/50 transition-all duration-300 has-[:checked]:border-[#0066FF] has-[:checked]:bg-[#F0F7FF] has-[:checked]:shadow-[0_0_0_3px_rgba(0,102,255,0.1)] group flex items-center gap-3">
                            <input type="radio" name="user_type" value="customer" id="roleCustomer" class="hidden peer" required>
                            <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 text-[18px] group-hover:bg-blue-50 group-hover:text-primary peer-checked:bg-blue-100 peer-checked:text-[#0066FF] transition-colors flex-shrink-0">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="flex flex-col">
                                <h5 class="font-black text-black tracking-wide text-[13px] leading-tight mb-0.5">MEMBER</h5>
                                <p class="text-[11px] text-gray-500 leading-tight">Shop Gear</p>
                            </div>
                            <div class="absolute top-2 right-2 w-4 h-4 bg-[#0066FF] rounded-full text-white items-center justify-center text-[8px] opacity-0 peer-checked:opacity-100 transition-opacity flex">
                                <i class="fas fa-check"></i>
                            </div>
                        </label>
                        
                        <label class="relative h-[68px] px-4 py-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#0066FF]/50 transition-all duration-300 has-[:checked]:border-[#0066FF] has-[:checked]:bg-[#F0F7FF] has-[:checked]:shadow-[0_0_0_3px_rgba(0,102,255,0.1)] group flex items-center gap-3">
                            <input type="radio" name="user_type" value="seller" id="roleSeller" class="hidden peer" required>
                            <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500 text-[18px] group-hover:bg-blue-50 group-hover:text-primary peer-checked:bg-blue-100 peer-checked:text-[#0066FF] transition-colors flex-shrink-0">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="flex flex-col">
                                <h5 class="font-black text-black tracking-wide text-[13px] leading-tight mb-0.5">SELLER</h5>
                                <p class="text-[11px] text-gray-500 leading-tight">Sell Gear</p>
                            </div>
                            <div class="absolute top-2 right-2 w-4 h-4 bg-[#0066FF] rounded-full text-white items-center justify-center text-[8px] opacity-0 peer-checked:opacity-100 transition-opacity flex">
                                <i class="fas fa-check"></i>
                            </div>
                        </label>
                    </div>
                    <span id="errRole" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                </div>


                <!-- Password Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Password *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 text-[10px]"></i>
                            </div>
                            <input type="password" id="regPassword" name="password" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                            <button type="button" onclick="togglePasswordVisibility('regPassword', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition-colors">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                        <div class="flex gap-1 mt-2 h-1.5 w-full rounded-full overflow-hidden bg-gray-200">
                            <div id="pwStrength1" class="h-full w-1/3 transition-colors duration-300"></div>
                            <div id="pwStrength2" class="h-full w-1/3 transition-colors duration-300"></div>
                            <div id="pwStrength3" class="h-full w-1/3 transition-colors duration-300"></div>
                        </div>
                        <span id="errPassword" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Confirm *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 text-[10px]"></i>
                            </div>
                            <input type="password" id="regConfirm" name="confirm" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                            <button type="button" onclick="togglePasswordVisibility('regConfirm', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition-colors">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                        <span id="errConfirm" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="space-y-3 pt-2">
                    <label class="flex items-start cursor-pointer group">
                        <div class="relative flex items-center justify-center mt-0.5">
                            <input type="checkbox" name="terms" id="regTerms" required class="peer appearance-none w-5 h-5 border-2 border-gray-300 rounded-md checked:bg-[#0066FF] checked:border-[#0066FF] transition-colors cursor-pointer">
                            <i class="fas fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                        </div>
                        <span class="ml-3 text-xs text-gray-600 leading-tight">I agree to the <a href="#" class="font-bold text-black hover:text-[#0066FF]">Terms of Service</a> and <a href="#" class="font-bold text-black hover:text-[#0066FF]">Privacy Policy</a> *</span>
                    </label>
                    <span id="errTerms" class="text-red-500 text-[10px] ml-8 hidden font-bold block"></span>

                    <label class="flex items-start cursor-pointer group">
                        <div class="relative flex items-center justify-center mt-0.5">
                            <input type="checkbox" name="newsletter" id="regNewsletter" value="1" checked class="peer appearance-none w-5 h-5 border-2 border-gray-300 rounded-md checked:bg-[#0066FF] checked:border-[#0066FF] transition-colors cursor-pointer">
                            <i class="fas fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                        </div>
                        <span class="ml-3 text-xs text-gray-600 leading-tight">Subscribe to the OXXA Newsletter for exclusive drops and offers</span>
                    </label>
                </div>

                <!-- reCAPTCHA Dummy -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center justify-between mt-2 w-full">
                    <div class="flex items-center gap-3 cursor-pointer" onclick="document.getElementById('regRecaptcha').click()">
                        <input type="checkbox" id="regRecaptcha" required class="w-6 h-6 rounded border-gray-300 text-[#0066FF] focus:ring-[#0066FF] cursor-pointer">
                        <span class="text-sm font-medium text-gray-700 select-none">I'm not a robot</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <img src="https://www.gstatic.com/recaptcha/api2/logo_48.png" class="w-8 opacity-80" alt="reCAPTCHA">
                        <span class="text-[8px] text-gray-400 mt-1">reCAPTCHA</span>
                    </div>
                </div>
                <span id="errRecaptcha" class="text-red-500 text-[10px] mt-1 hidden font-bold"></span>

                <button type="submit" id="regSubmitBtn" name="register" class="w-full h-[56px] bg-black hover:bg-primary text-white font-space font-bold uppercase tracking-widest rounded-xl transition-all duration-300 mt-6 shadow-lg hover:shadow-[0_8px_25px_rgba(0,102,255,0.3)] disabled:opacity-50 disabled:cursor-not-allowed">
                    Create Account
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-600">Already have an account? <a href="#" onclick="if(typeof openAuthModal === 'function'){ openAuthModal('login'); return false; }" class="font-bold text-primary hover:text-black transition-colors uppercase tracking-wider text-xs ml-2">Login Here</a></p>
            </div>
        </div>
    </div>
</div>

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