document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('modalRegisterForm');
    if (!form) return;

    // Inputs
    const firstname = document.getElementById('regFirstname');
    const lastname = document.getElementById('regLastname');
    const username = document.getElementById('regUsername');
    const email = document.getElementById('regEmail');
    const phone = document.getElementById('regPhone');
    const password = document.getElementById('regPassword');
    const confirm = document.getElementById('regConfirm');
    const terms = document.getElementById('regTerms');
    const recaptcha = document.getElementById('regRecaptcha');
    const submitBtn = document.getElementById('regSubmitBtn');
    
    // Photo
    const photoUpload = document.getElementById('modalProfileUpload');
    const errPhoto = document.getElementById('errPhoto');

    // Regex Rules
    const nameRegex = /^[A-Za-z ]{2,50}$/;
    const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const phoneRegex = /^(?:\+94|0)?7[0-9]{8}$/;
    
    // State
    const validState = {
        firstname: false,
        lastname: false,
        username: false, // Initially false, will be checked by AJAX
        email: false,
        phone: false,
        password: false,
        confirm: false,
        terms: false,
        recaptcha: false,
        photo: true // Optional initially, but validated if uploaded
    };

    let usernameTimeout = null;

    // Utility: Show/Hide Error
    const showError = (id, message) => {
        const errEl = document.getElementById(`err${id}`);
        const inputEl = document.getElementById(`reg${id}`);
        if(errEl) {
            errEl.textContent = message;
            errEl.classList.remove('hidden');
        }
        if(inputEl) {
            inputEl.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-50');
            inputEl.classList.remove('border-gray-200', 'focus:border-[#0066FF]', 'focus:ring-blue-50');
        }
    };

    const clearError = (id) => {
        const errEl = document.getElementById(`err${id}`);
        const inputEl = document.getElementById(`reg${id}`);
        if(errEl) {
            errEl.classList.add('hidden');
        }
        if(inputEl) {
            inputEl.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-50');
            inputEl.classList.add('border-green-500', 'focus:border-green-500', 'focus:ring-green-50');
        }
    };

    const resetInputStyle = (id) => {
        const errEl = document.getElementById(`err${id}`);
        const inputEl = document.getElementById(`reg${id}`);
        if(errEl) errEl.classList.add('hidden');
        if(inputEl) {
            inputEl.classList.remove('border-red-500', 'border-green-500', 'focus:border-red-500', 'focus:border-green-500', 'focus:ring-red-50', 'focus:ring-green-50');
            inputEl.classList.add('border-gray-200', 'focus:border-[#0066FF]', 'focus:ring-blue-50');
        }
    };

    const validateForm = () => {
        const isValid = Object.values(validState).every(val => val === true);
        submitBtn.disabled = !isValid;
    };

    // Validators
    const validateFirstname = () => {
        const val = firstname.value.trim();
        if (!val) { resetInputStyle('Firstname'); validState.firstname = false; }
        else if (!nameRegex.test(val)) { showError('Firstname', '2-50 letters only'); validState.firstname = false; }
        else { clearError('Firstname'); validState.firstname = true; }
        validateForm();
    };

    const validateLastname = () => {
        const val = lastname.value.trim();
        if (!val) { resetInputStyle('Lastname'); validState.lastname = false; }
        else if (!nameRegex.test(val)) { showError('Lastname', '2-50 letters only'); validState.lastname = false; }
        else { clearError('Lastname'); validState.lastname = true; }
        validateForm();
    };

    let emailTimeout = null;

    const validateEmail = () => {
        const val = email.value.trim();
        const icon = document.getElementById('emailIcon');
        
        clearTimeout(emailTimeout);

        if (!val) { 
            resetInputStyle('Email'); 
            if(icon) icon.classList.add('hidden');
            validState.email = false;
            validateForm();
            return;
        }

        if (!emailRegex.test(val)) { 
            showError('Email', 'Invalid email format'); 
            if(icon) icon.classList.add('hidden');
            validState.email = false;
            validateForm();
            return;
        }

        // Show spinner
        if(icon) {
            icon.classList.remove('hidden', 'fa-check', 'text-green-500', 'fa-times', 'text-red-500');
            icon.classList.add('fa-spinner', 'fa-spin', 'text-[#0066FF]');
        }
        
        emailTimeout = setTimeout(async () => {
            try {
                const basePath = window.location.pathname.includes('/site/') ? '../' : '';
                const response = await fetch(`${basePath}Backend/check-email.php?e=${encodeURIComponent(val)}`);
                const data = await response.json();
                
                if(icon) icon.classList.remove('fa-spinner', 'fa-spin', 'text-[#0066FF]');
                
                if (data.available) {
                    clearError('Email');
                    if(icon) icon.classList.add('fa-check', 'text-green-500');
                    validState.email = true;
                } else {
                    showError('Email', 'Email is already registered');
                    if(icon) icon.classList.add('fa-times', 'text-red-500');
                    validState.email = false;
                }
            } catch (error) {
                console.error("Email check failed", error);
                validState.email = false;
            }
            validateForm();
        }, 500);
    };

    let phoneTimeout = null;

    const validatePhone = () => {
        const val = phone.value.trim();
        const icon = document.getElementById('phoneIcon');
        
        clearTimeout(phoneTimeout);

        if (!val) { 
            resetInputStyle('Phone'); 
            if(icon) icon.classList.add('hidden');
            validState.phone = false;
            validateForm();
            return;
        }
        
        if (!phoneRegex.test(val)) { 
            showError('Phone', 'Invalid Sri Lankan phone number'); 
            if(icon) icon.classList.add('hidden');
            validState.phone = false;
            validateForm();
            return;
        }
        
        // Show spinner
        if(icon) {
            icon.classList.remove('hidden', 'fa-check', 'text-green-500', 'fa-times', 'text-red-500');
            icon.classList.add('fa-spinner', 'fa-spin', 'text-[#0066FF]');
        }
        
        phoneTimeout = setTimeout(async () => {
            try {
                const basePath = window.location.pathname.includes('/site/') ? '../' : '';
                const response = await fetch(`${basePath}Backend/check-phone.php?p=${encodeURIComponent(val)}`);
                const data = await response.json();
                
                if(icon) icon.classList.remove('fa-spinner', 'fa-spin', 'text-[#0066FF]');
                
                if (data.available) {
                    clearError('Phone');
                    if(icon) icon.classList.add('fa-check', 'text-green-500');
                    validState.phone = true;
                } else {
                    showError('Phone', 'Phone number is already registered');
                    if(icon) icon.classList.add('fa-times', 'text-red-500');
                    validState.phone = false;
                }
            } catch (error) {
                console.error("Phone check failed", error);
                validState.phone = false;
            }
            validateForm();
        }, 500);
    };

    const validatePassword = () => {
        const val = password.value;
        let strength = 0;
        
        // Rules
        const hasLower = /[a-z]/.test(val);
        const hasUpper = /[A-Z]/.test(val);
        const hasNumber = /\d/.test(val);
        const hasSpecial = /[@$!%*?&]/.test(val);
        const isLongEnough = val.length >= 8;

        if (val.length > 0) {
            if (isLongEnough) strength++;
            if (hasLower && hasUpper) strength++;
            if (hasNumber && hasSpecial) strength++;
        }

        // Update meter
        const b1 = document.getElementById('pwStrength1');
        const b2 = document.getElementById('pwStrength2');
        const b3 = document.getElementById('pwStrength3');

        b1.className = 'h-full w-1/3 transition-colors duration-300 ' + (strength >= 1 ? 'bg-red-500' : 'bg-transparent');
        b2.className = 'h-full w-1/3 transition-colors duration-300 ' + (strength >= 2 ? 'bg-yellow-500' : 'bg-transparent');
        b3.className = 'h-full w-1/3 transition-colors duration-300 ' + (strength >= 3 ? 'bg-green-500' : 'bg-transparent');

        if (!val) {
            resetInputStyle('Password');
            validState.password = false;
        } else if (!isLongEnough || !hasLower || !hasUpper || !hasNumber || !hasSpecial) {
            showError('Password', 'Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char');
            validState.password = false;
        } else {
            clearError('Password');
            validState.password = true;
        }

        // Revalidate confirm password if it has value
        if (confirm.value) validateConfirm();
        validateForm();
    };

    const validateConfirm = () => {
        const val = confirm.value;
        if (!val) {
            resetInputStyle('Confirm');
            validState.confirm = false;
        } else if (val !== password.value) {
            showError('Confirm', 'Passwords do not match');
            validState.confirm = false;
        } else {
            clearError('Confirm');
            validState.confirm = true;
        }
        validateForm();
    };

    // Username AJAX
    const validateUsername = () => {
        const val = username.value.trim();
        const icon = document.getElementById('usernameIcon');
        
        clearTimeout(usernameTimeout);

        if (!val) { 
            resetInputStyle('Username'); 
            icon.classList.add('hidden');
            validState.username = false;
            validateForm();
            return;
        }

        if (!usernameRegex.test(val)) {
            showError('Username', '3-20 chars, letters/numbers/_ only');
            icon.classList.add('hidden');
            validState.username = false;
            validateForm();
            return;
        }

        // Show spinner
        icon.classList.remove('hidden', 'fa-check', 'text-green-500', 'fa-times', 'text-red-500');
        icon.classList.add('fa-spinner', 'fa-spin', 'text-[#0066FF]');
        
        usernameTimeout = setTimeout(async () => {
            try {
                // Determine base path based on current URL structure
                const basePath = window.location.pathname.includes('/site/') ? '../' : '';
                const response = await fetch(`${basePath}Backend/check-username.php?u=${encodeURIComponent(val)}`);
                const data = await response.json();
                
                icon.classList.remove('fa-spinner', 'fa-spin', 'text-[#0066FF]');
                
                if (data.available) {
                    clearError('Username');
                    icon.classList.add('fa-check', 'text-green-500');
                    validState.username = true;
                } else {
                    showError('Username', 'Username is already taken');
                    icon.classList.add('fa-times', 'text-red-500');
                    validState.username = false;
                }
            } catch (error) {
                console.error("Username check failed", error);
            }
            validateForm();
        }, 500);
    };

    // Photo Validation
    photoUpload.addEventListener('change', function() {
        const file = this.files[0];
        errPhoto.classList.add('hidden');
        validState.photo = true;

        if (file) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                errPhoto.textContent = 'Only JPG, PNG, WEBP allowed';
                errPhoto.classList.remove('hidden');
                validState.photo = false;
                this.value = ''; // clear
            } else if (file.size > 2 * 1024 * 1024) {
                errPhoto.textContent = 'Max file size is 2MB';
                errPhoto.classList.remove('hidden');
                validState.photo = false;
                this.value = ''; // clear
            }
        }
        validateForm();
    });

    // Attach Listeners
    firstname.addEventListener('input', validateFirstname);
    lastname.addEventListener('input', validateLastname);
    username.addEventListener('input', validateUsername);
    email.addEventListener('input', validateEmail);
    phone.addEventListener('input', validatePhone);
    password.addEventListener('input', validatePassword);
    confirm.addEventListener('input', validateConfirm);
    
    terms.addEventListener('change', (e) => {
        validState.terms = e.target.checked;
        if(!e.target.checked) {
            showError('Terms', 'You must agree to the terms');
        } else {
            document.getElementById('errTerms').classList.add('hidden');
        }
        validateForm();
    });

    recaptcha.addEventListener('change', (e) => {
        validState.recaptcha = e.target.checked;
        if(!e.target.checked) {
            showError('Recaptcha', 'Please complete the bot check');
        } else {
            document.getElementById('errRecaptcha').classList.add('hidden');
        }
        validateForm();
    });
    
    // Initial State Check
    validateForm();
    
    // Form AJAX Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (submitBtn.disabled) return;
        
        // Show loading state
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating Account...';
        
        // Clear previous generic errors
        let errorContainer = document.getElementById('regGenericErrors');
        if (errorContainer) {
            errorContainer.classList.add('hidden');
            errorContainer.querySelector('ul').innerHTML = '';
        }
        
        try {
            const formData = new FormData(form);
            const basePath = window.location.pathname.includes('/site/') ? '../' : '';
            
            const response = await fetch(`${basePath}Backend/register-backend.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Redirect on success
                window.location.href = data.redirect;
            } else {
                // Show errors
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                if (data.errors && data.errors.length > 0) {
                    let errHtml = '';
                    data.errors.forEach(err => {
                        errHtml += `<li class="text-xs text-red-700 font-medium">${err}</li>`;
                    });
                    
                    if (!errorContainer) {
                        // Create error container dynamically
                        const errDiv = document.createElement('div');
                        errDiv.id = 'regGenericErrors';
                        errDiv.className = 'mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg';
                        errDiv.innerHTML = `
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-bold text-red-800 uppercase tracking-wider mb-1">Registration Failed</h3>
                                    <ul class="list-disc pl-5 space-y-1">${errHtml}</ul>
                                </div>
                            </div>
                        `;
                        form.parentNode.insertBefore(errDiv, form);
                        errorContainer = errDiv;
                    } else {
                        errorContainer.querySelector('ul').innerHTML = errHtml;
                        errorContainer.classList.remove('hidden');
                    }
                    
                    // Scroll to top of modal
                    const modalBody = form.closest('.overflow-y-auto');
                    if (modalBody) modalBody.scrollTop = 0;
                }
            }
        } catch (err) {
            console.error("Submission failed", err);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            alert('An unexpected error occurred. Please check your connection and try again.');
        }
    });
});

// Function used by the file input directly via onchange in HTML
function previewModalImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('modalPhotoPreview');
            // Remove existing image if any
            const existingImg = preview.querySelector('img');
            if (existingImg) existingImg.remove();
            
            // Add new image
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'w-full h-full object-cover absolute inset-0 z-0';
            preview.insertBefore(img, preview.firstChild);
            
            // Hide camera icon
            const icon = preview.querySelector('i');
            if(icon) icon.classList.add('opacity-0');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
