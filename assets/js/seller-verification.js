document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('sellerVerifyForm');
    if (!form) return;

    const inputs = {
        business_name: document.getElementById('business_name'),
        business_type: document.getElementById('business_type'),
        br_number: document.getElementById('br_number'),
        date_of_incorporation: document.getElementById('date_of_incorporation'),
        nature_of_business: document.getElementById('nature_of_business'),
        owner_name: document.getElementById('owner_name'),
        owner_nic: document.getElementById('owner_nic'),
        personal_phone: document.getElementById('personal_phone'),
        personal_email: document.getElementById('personal_email'),
        business_phone: document.getElementById('business_phone'),
        business_email: document.getElementById('business_email'),
        address_line1: document.getElementById('address_line1'),
        address_line2: document.getElementById('address_line2'),
        city: document.getElementById('city'),
        postal_code: document.getElementById('postal_code'),
        province: document.getElementById('province'),
        bank_name: document.getElementById('bank_name'),
        branch_name: document.getElementById('branch_name'),
        account_number: document.getElementById('account_number'),
        account_holder_name: document.getElementById('account_holder_name'),
        social_website: document.getElementById('social_website'),
        declaration: document.getElementById('declaration')
    };

    const fileInputs = {
        certificate_file: document.getElementById('certificate_file'),
        nic_file: document.getElementById('nic_file'),
        logo_file: document.getElementById('logo_file'),
        shop_photo_file: document.getElementById('shop_photo_file'),
        bank_book_file: document.getElementById('bank_book_file')
    };

    const submitBtn = document.getElementById('submitVerificationBtn');
    const submitLoader = document.getElementById('submitLoader');
    const formErrorBanner = document.getElementById('formErrorBanner');
    const formErrorText = document.getElementById('formErrorText');

    // Regex Rules
    const rules = {
        business_name: /^[a-zA-Z0-9 &'.-]{3,100}$/,
        owner_name: /^[a-zA-Z\s]{3,100}$/,
        br_number: /^[A-Z]{1,3}-[A-Z]?-?\d{4,6}$/,
        nic: /^([0-9]{9}[vVxX]|[0-9]{12})$/,
        personal_phone: /^07[0-8]\d{7}$/,
        business_phone: /^0\d{9}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        postal_code: /^[0-9]{5}$/,
        account_number: /^\d{10,16}$/,
        url: /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/
    };

    // Helper: Show/Clear Error
    const showError = (fieldId, msg) => {
        const errSpan = document.getElementById(`err_${fieldId}`);
        if (errSpan) {
            errSpan.textContent = msg;
            errSpan.classList.remove('hidden');
        }
        const input = document.getElementById(fieldId);
        if (input) {
            input.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-100');
            input.classList.remove('border-gray-200', 'focus:border-[#0066FF]', 'focus:ring-[#0066FF]');
        }
    };

    const clearError = (fieldId) => {
        const errSpan = document.getElementById(`err_${fieldId}`);
        if (errSpan) {
            errSpan.textContent = '';
            errSpan.classList.add('hidden');
        }
        const input = document.getElementById(fieldId);
        if (input) {
            input.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-100');
            input.classList.add('border-gray-200', 'focus:border-[#0066FF]', 'focus:ring-[#0066FF]');
        }
    };

    // Field Validation Handlers
    const validateField = (id) => {
        const el = inputs[id];
        const val = el.value.trim();
        let isValid = true;

        if (el.hasAttribute('required') && !val && id !== 'declaration') {
            showError(id, 'This field is required');
            return false;
        }

        switch (id) {
            case 'business_name':
                if (val && !rules.business_name.test(val)) {
                    showError(id, 'Min 3 chars. Only letters, numbers, spaces, and & \'. - allowed.');
                    isValid = false;
                }
                break;
            case 'br_number':
                el.value = val.toUpperCase();
                if (val && !rules.br_number.test(val)) {
                    showError(id, 'Invalid BR format. E.g. WP-C-32194');
                    isValid = false;
                }
                break;
            case 'date_of_incorporation':
                if (val) {
                    const selected = new Date(val);
                    const now = new Date();
                    const min = new Date();
                    min.setFullYear(1975);
                    if (selected > now) {
                        showError(id, 'Date cannot be in the future.');
                        isValid = false;
                    } else if (selected < min) {
                        showError(id, 'Date cannot be older than 1975.');
                        isValid = false;
                    }
                }
                break;
            case 'owner_name':
                if (val && !rules.owner_name.test(val)) {
                    showError(id, 'Min 3 chars. Only letters and spaces allowed.');
                    isValid = false;
                }
                break;
            case 'owner_nic':
                if (val && !rules.nic.test(val)) {
                    showError(id, 'Invalid NIC format. Use 9 digits+V/X or 12 digits.');
                    isValid = false;
                }
                break;
            case 'personal_phone':
                // Auto format numbers only
                el.value = val.replace(/\D/g, '').slice(0,10);
                if (el.value && !rules.personal_phone.test(el.value)) {
                    showError(id, 'Invalid SL mobile. E.g. 07XXXXXXXX');
                    isValid = false;
                }
                break;
            case 'business_phone':
                el.value = val.replace(/\D/g, '').slice(0,10);
                if (el.value && !rules.business_phone.test(el.value)) {
                    showError(id, 'Invalid phone format. E.g. 011XXXXXXX or 07XXXXXXXX');
                    isValid = false;
                }
                break;
            case 'personal_email':
                if (val && !rules.email.test(val)) {
                    showError(id, 'Enter a valid email address.');
                    isValid = false;
                }
                break;
            case 'business_email':
                if (val && !rules.email.test(val)) {
                    showError(id, 'Enter a valid email address.');
                    isValid = false;
                }
                break;
            case 'address_line1':
                if (val && val.length < 5) {
                    showError(id, 'Address must be at least 5 characters long.');
                    isValid = false;
                }
                break;
            case 'postal_code':
                if (val && !rules.postal_code.test(val)) {
                    showError(id, 'Postal code must be exactly 5 digits.');
                    isValid = false;
                }
                break;
            case 'account_number':
                el.value = val.replace(/\D/g, '').slice(0,16);
                if (el.value && !rules.account_number.test(el.value)) {
                    showError(id, 'Account number must be 10-16 digits.');
                    isValid = false;
                }
                break;
            case 'account_holder_name':
                if (val && inputs.business_name.value) {
                    // Just a soft check, no hard error block if they differ, but we warn
                    if (val.toLowerCase() !== inputs.business_name.value.toLowerCase().trim() && val.toLowerCase() !== inputs.owner_name.value.toLowerCase().trim()) {
                        // Soft warning - we won't block submission but will show a message
                        const errSpan = document.getElementById(`err_${id}`);
                        if (errSpan) {
                            errSpan.textContent = 'Note: Usually matches Business Name or Owner Name';
                            errSpan.classList.remove('hidden', 'text-red-500');
                            errSpan.classList.add('text-orange-500');
                        }
                    } else {
                        const errSpan = document.getElementById(`err_${id}`);
                        if (errSpan) {
                            errSpan.classList.add('hidden', 'text-red-500');
                            errSpan.classList.remove('text-orange-500');
                        }
                    }
                }
                break;
            case 'social_website':
                if (val && !rules.url.test(val)) {
                    showError(id, 'Enter a valid URL (e.g. https://domain.com).');
                    isValid = false;
                }
                break;
        }

        if (isValid) clearError(id);
        checkFormValidity();
        return isValid;
    };

    // Attach listeners to text/select inputs
    Object.keys(inputs).forEach(key => {
        if(inputs[key]) {
            inputs[key].addEventListener('blur', () => validateField(key));
            inputs[key].addEventListener('input', () => {
                if(inputs[key].classList.contains('border-red-500')) {
                    validateField(key);
                } else {
                    checkFormValidity();
                }
            });
        }
    });

    // Categories Validation
    const validateCategories = () => {
        const checked = document.querySelectorAll('.category-checkbox:checked');
        if (checked.length === 0) {
            const errSpan = document.getElementById('err_categories');
            if(errSpan) {
                errSpan.textContent = 'Please select at least one category.';
                errSpan.classList.remove('hidden');
            }
            return false;
        } else {
            const errSpan = document.getElementById('err_categories');
            if(errSpan) errSpan.classList.add('hidden');
            return true;
        }
    };

    document.querySelectorAll('.category-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            validateCategories();
            checkFormValidity();
        });
    });

    // File Upload Validation & UI
    const validateFile = (input) => {
        const file = input.files[0];
        const display = input.parentElement.querySelector('.file-name-display');
        const icon = input.parentElement.querySelector('i');
        const errSpan = document.getElementById(`err_${input.id}`);
        
        if (!file && input.hasAttribute('required')) {
            if(errSpan) {
                errSpan.textContent = 'This document is required.';
                errSpan.classList.remove('hidden');
            }
            return false;
        }

        if (!file) return true; // Optional file

        // Check Size
        const maxSizeMB = parseFloat(input.getAttribute('data-max-size') || 5);
        if (file.size > maxSizeMB * 1024 * 1024) {
            if(errSpan) {
                errSpan.textContent = `File too large. Maximum size is ${maxSizeMB}MB.`;
                errSpan.classList.remove('hidden');
            }
            input.value = ''; // clear
            resetFileUI(input, display, icon);
            return false;
        }

        // Check Type
        const acceptedTypes = input.getAttribute('accept').split(',');
        const fileExt = '.' + file.name.split('.').pop().toLowerCase();
        if (!acceptedTypes.includes(fileExt) && !acceptedTypes.includes(file.type)) {
            if(errSpan) {
                errSpan.textContent = `Invalid format. Accepted: ${acceptedTypes.join(', ')}`;
                errSpan.classList.remove('hidden');
            }
            input.value = ''; // clear
            resetFileUI(input, display, icon);
            return false;
        }

        // Logo specific: image preview
        if (input.getAttribute('data-is-logo') === 'true') {
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);
            img.onload = function () {
                if (this.width < 200 || this.height < 200) {
                    if(errSpan) {
                        errSpan.textContent = `Image dimensions must be at least 200x200px.`;
                        errSpan.classList.remove('hidden');
                    }
                    input.value = '';
                    resetFileUI(input, display, icon);
                    URL.revokeObjectURL(objectUrl);
                    return false;
                } else {
                    const preview = document.getElementById('logoPreviewImage');
                    const wrapper = document.getElementById('logoPreviewWrapper');
                    if(preview && wrapper) {
                        preview.src = objectUrl;
                        preview.classList.remove('hidden');
                        wrapper.classList.add('hidden');
                    }
                }
            };
            img.src = objectUrl;
        }

        if(errSpan) errSpan.classList.add('hidden');
        
        // Update UI Success
        if (display && icon) {
            display.textContent = file.name;
            display.classList.add('text-[#0066FF]', 'font-bold');
            display.classList.remove('text-gray-500');
            input.parentElement.classList.add('border-[#0066FF]', 'bg-blue-50');
            icon.classList.remove('fa-cloud-upload-alt', 'fa-image', 'fa-id-card', 'fa-store', 'fa-university', 'text-gray-400');
            icon.classList.add('fa-check-circle', 'text-[#0066FF]');
        }
        
        checkFormValidity();
        return true;
    };

    const resetFileUI = (input, display, icon) => {
        if(display) {
            display.textContent = 'Click to upload';
            display.classList.remove('text-[#0066FF]', 'font-bold');
            display.classList.add('text-gray-500');
        }
        input.parentElement.classList.remove('border-[#0066FF]', 'bg-blue-50');
        if(icon) {
            icon.className = 'fas fa-exclamation-circle text-2xl text-red-400 mb-2'; // show error icon
        }
        
        if (input.getAttribute('data-is-logo') === 'true') {
            const preview = document.getElementById('logoPreviewImage');
            const wrapper = document.getElementById('logoPreviewWrapper');
            if(preview && wrapper) {
                preview.classList.add('hidden');
                wrapper.classList.remove('hidden');
            }
        }
    };

    Object.values(fileInputs).forEach(fi => {
        if(fi) {
            fi.addEventListener('change', () => validateFile(fi));
        }
    });

    // Check Form Validity State
    const checkFormValidity = () => {
        let valid = true;
        
        Object.keys(inputs).forEach(key => {
            const el = inputs[key];
            if (!el) return;
            if (el.hasAttribute('required')) {
                if (key === 'declaration' && !el.checked) valid = false;
                else if (el.type !== 'checkbox' && !el.value.trim()) valid = false;
            }
            if (el.classList.contains('border-red-500')) valid = false;
        });

        if (!validateCategories()) valid = false;

        Object.values(fileInputs).forEach(fi => {
            if (fi && fi.hasAttribute('required') && !fi.files[0]) valid = false;
            const errSpan = document.getElementById(`err_${fi?.id}`);
            if (errSpan && !errSpan.classList.contains('hidden')) valid = false;
        });

        submitBtn.disabled = !valid;
    };

    // AJAX Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Final sanity check
        let allValid = true;
        Object.keys(inputs).forEach(key => { if(!validateField(key)) allValid = false; });
        if(!validateCategories()) allValid = false;
        Object.values(fileInputs).forEach(fi => { if(!validateFile(fi)) allValid = false; });

        if (!allValid) {
            formErrorText.textContent = "Please correct the highlighted errors before submitting.";
            formErrorBanner.classList.remove('hidden');
            formErrorBanner.classList.add('flex');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        formErrorBanner.classList.add('hidden');
        formErrorBanner.classList.remove('flex');
        submitBtn.disabled = true;
        submitBtn.querySelector('span').textContent = 'Verifying...';
        submitLoader.classList.remove('hidden');

        try {
            const formData = new FormData(form);
            const response = await fetch('../Backend/process-business-registration.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = 'business-registration.php?success=1';
            } else {
                // Show errors
                if (result.errors && typeof result.errors === 'object') {
                    // Field specific errors
                    Object.keys(result.errors).forEach(key => {
                        showError(key, result.errors[key]);
                    });
                    formErrorText.textContent = "We found some issues with your submission. Please check the fields below.";
                } else if (result.message) {
                    formErrorText.textContent = result.message;
                } else {
                    formErrorText.textContent = "An unknown error occurred.";
                }
                formErrorBanner.classList.remove('hidden');
                formErrorBanner.classList.add('flex');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Submit Registration';
                submitLoader.classList.add('hidden');
            }

        } catch (error) {
            console.error('Submission failed', error);
            formErrorText.textContent = "Network error. Please try again later.";
            formErrorBanner.classList.remove('hidden');
            formErrorBanner.classList.add('flex');
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = 'Submit Registration';
            submitLoader.classList.add('hidden');
        }
    });
});
