<style>
/* Custom Scrollbar for the modal */
.auth-scroll::-webkit-scrollbar {
    width: 5px;
}
.auth-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.auth-scroll::-webkit-scrollbar-thumb {
    background: #E5E7EB; /* bg-gray-200 */
    border-radius: 9999px;
}
</style>

<!-- Auth Overlay -->
<div id="authOverlay" class="fixed inset-0 bg-black/70 backdrop-blur-md z-[100] hidden opacity-0 transition-opacity duration-300" onclick="closeAuthModal()"></div>

<!-- Auth Modal (Top Slide-In) -->
<div id="authModal" class="fixed top-0 left-0 right-0 z-[101] transform -translate-y-full transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] max-w-4xl mx-auto flex shadow-[0_20px_80px_rgba(0,0,0,0.5)] rounded-b-[24px] overflow-hidden hidden">
    
    <!-- Left Side: Dark Premium Banner -->
    <div class="w-[40%] bg-[#0A0A0A] p-10 flex-col justify-between hidden md:flex relative">
        <!-- Background Effects -->
        <div class="absolute inset-0 bg-[url('../image/oxxa_gear_logo.png')] bg-[length:150px] opacity-[0.03] bg-repeat"></div>
        <div class="absolute inset-0" style="background: radial-gradient(circle at 20% 80%, rgba(0,102,255,0.25) 0%, transparent 50%);"></div>
        
        <!-- Content -->
        <div class="relative z-10 flex flex-col h-full justify-between">
            <div class="mt-14 mb-10 relative self-center inline-block">
                <img src="<?php echo $base_path; ?>image/oxxa_gear_logo.png" alt="OXXA GEAR" class="w-40 md:w-48 h-auto filter brightness-0 invert opacity-100 drop-shadow-lg">
            </div>
            
            <div class="mt-auto mb-10">
                <h2 class="text-white text-5xl font-black leading-[1] font-space uppercase tracking-tight">
                    JOIN THE<br>
                    <span class="text-primary">MOVEMENT<span class="text-white">.</span></span>
                </h2>
                <div class="w-16 h-1 bg-primary mt-6 mb-0"></div>
            </div>

            <div class="border-t border-white/10 pt-6">
                <h5 class="text-2xl font-black text-white font-space mb-0">10K+</h5>
                <div class="text-[10px] tracking-[0.2em] text-gray-500 font-bold uppercase mt-1">MEMBERS</div>
            </div>
        </div>
    </div>

    <!-- Right Side: Form Area -->
    <div class="w-full md:w-[60%] bg-white p-10 relative max-h-[90vh] overflow-y-auto auth-scroll flex flex-col">
        <!-- Close Button -->
        <button onclick="closeAuthModal()" class="absolute top-6 right-6 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-black hover:text-white transition-colors z-20">
            <i class="fas fa-times"></i>
        </button>

        <!-- Mobile Logo -->
        <div class="md:hidden mb-2 mt-2 relative self-center inline-block">
            <img src="<?php echo $base_path; ?>image/oxxa_gear_logo.png" alt="OXXA GEAR" class="w-24 h-auto opacity-100 drop-shadow-sm">
        </div>

        <!-- LOGIN VIEW -->
        <div id="loginView" class="flex-1 flex flex-col justify-center mt-8">
            <div class="mb-8">
                <h4 class="text-3xl font-space font-bold text-black uppercase tracking-wide">Welcome Back</h4>
                <p class="text-gray-500 mt-2 text-sm">Sign in to continue your fitness journey</p>
            </div>
            
            <form action="<?php echo $base_path; ?>Backend/login-backend.php" method="POST" id="modalLoginForm" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wide mb-2">Username or Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input type="text" name="username" required class="block w-full h-[56px] pl-12 pr-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wide mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="modalLoginPassword" name="password" required class="block w-full h-[56px] pl-12 pr-12 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                        <button type="button" onclick="togglePasswordVisibility('modalLoginPassword', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-primary transition-colors">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="rememberMe" class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary focus:ring-2">
                        <label class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>
                    <a href="#" class="text-sm font-bold text-primary hover:text-black transition-colors">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="w-full h-[56px] bg-black hover:bg-primary text-white font-space font-bold uppercase tracking-widest rounded-xl transition-all duration-300 mt-4 shadow-lg hover:shadow-[0_8px_25px_rgba(0,102,255,0.3)]">
                    Sign In
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <p class="text-sm text-gray-600">Don't have an account? <button type="button" onclick="showView('register')" class="font-bold text-primary hover:text-black transition-colors uppercase tracking-wider text-xs ml-2">Join the Movement</button></p>
            </div>
        </div>

        <!-- REGISTER VIEW -->
        <div id="registerView" class="hidden flex-1 flex flex-col justify-center mt-4">
            <div class="mb-8">
                <h4 class="text-3xl font-space font-bold text-black uppercase tracking-wide">Create Your Legacy</h4>
                <p class="text-gray-500 mt-2 text-sm">Join us for premium sports gear & equipment</p>
            </div>

            <form action="<?php echo $base_path; ?>Backend/register-backend.php" method="POST" id="modalRegisterForm" class="space-y-5" enctype="multipart/form-data">
                
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
                    <input type="file" name="profile_photo" id="modalProfileUpload" accept="image/*" class="hidden" onchange="previewModalImage(this)">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">First Name</label>
                        <input type="text" name="firstname" required class="block w-full h-12 px-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Last Name</label>
                        <input type="text" name="lastname" required class="block w-full h-12 px-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-at text-gray-400"></i>
                        </div>
                        <input type="text" name="username" required class="block w-full h-[56px] pl-12 pr-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" name="email" required class="block w-full h-[56px] pl-12 pr-4 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                    </div>
                </div>

                <!-- Premium User Type Selector -->
                <div class="mt-4 mb-5">
                    <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-3">I AM A *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative h-[68px] px-4 py-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[#0066FF]/50 transition-all duration-300 has-[:checked]:border-[#0066FF] has-[:checked]:bg-[#F0F7FF] has-[:checked]:shadow-[0_0_0_3px_rgba(0,102,255,0.1)] group flex items-center gap-3">
                            <input type="radio" name="user_type" value="customer" class="hidden peer" required checked>
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
                            <input type="radio" name="user_type" value="seller" class="hidden peer" required>
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
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 text-[10px]"></i>
                            </div>
                            <input type="password" id="modalRegisterPassword" name="password" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                            <button type="button" onclick="togglePasswordVisibility('modalRegisterPassword', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition-colors">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-black uppercase tracking-wide mb-2">Confirm</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 text-[10px]"></i>
                            </div>
                            <input type="password" id="modalConfirmPassword" name="confirm" required class="block w-full h-[56px] pl-12 pr-10 bg-[#F8FAFC] border-2 border-gray-200 rounded-xl text-black text-sm focus:bg-white focus:border-[#0066FF] focus:ring-4 focus:ring-blue-50 transition-all outline-none">
                            <button type="button" onclick="togglePasswordVisibility('modalConfirmPassword', this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition-colors">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" name="register" class="w-full h-[56px] bg-black hover:bg-primary text-white font-space font-bold uppercase tracking-widest rounded-xl transition-all duration-300 mt-6 shadow-lg hover:shadow-[0_8px_25px_rgba(0,102,255,0.3)]">
                    Create Account
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <p class="text-sm text-gray-600">Already have an account? <button type="button" onclick="showView('login')" class="font-bold text-primary hover:text-black transition-colors uppercase tracking-wider text-xs ml-2">Login Here</button></p>
            </div>
        </div>
    </div>
</div>
