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
        province: document.getElementById('province'),
        district: document.getElementById('district'),
        city: document.getElementById('city'),
        postal_code: document.getElementById('postal_code'),
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
        business_name: /^.{3,100}$/,
        owner_name: /^[a-zA-Z\s\.\']{3,100}$/,
        br_number: /^[A-Za-z0-9\/\-\. ]{3,30}$/,
        nic: /^([0-9]{9}[vVxX]|[0-9]{12})$/,
        personal_phone: /^07[0-9]\d{7}$/,
        business_phone: /^0\d{9}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        postal_code: /^[0-9]{5}$/,
        account_number: /^\d{10,16}$/,
        url: /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i
    };

    // Helper: Show/Clear Error
    const showError = (fieldId, msg) => {
        let errSpan = document.getElementById(`err_${fieldId}`);
        if (!errSpan && fieldId === 'br_number') errSpan = document.getElementById('err_business_reg_id');
        if (!errSpan && fieldId === 'business_reg_id') errSpan = document.getElementById('err_br_number');
        if (errSpan) {
            errSpan.textContent = msg;
            errSpan.classList.remove('hidden');
        }
        let input = document.getElementById(fieldId);
        if (!input && fieldId === 'business_reg_id') input = document.getElementById('br_number');
        if (input) {
            input.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-100');
            input.classList.remove('border-gray-200', 'focus:border-[#0066FF]', 'focus:ring-[#0066FF]');
        }
    };

    const clearError = (fieldId) => {
        let errSpan = document.getElementById(`err_${fieldId}`);
        if (!errSpan && fieldId === 'br_number') errSpan = document.getElementById('err_business_reg_id');
        if (!errSpan && fieldId === 'business_reg_id') errSpan = document.getElementById('err_br_number');
        if (errSpan) {
            errSpan.textContent = '';
            errSpan.classList.add('hidden');
        }
        let input = document.getElementById(fieldId);
        if (!input && fieldId === 'business_reg_id') input = document.getElementById('br_number');
        if (input) {
            input.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-100');
            input.classList.add('border-gray-200', 'focus:border-[#0066FF]', 'focus:ring-[#0066FF]');
        }
    };

    // Field Validation Handlers
    const validateField = (id) => {
        const el = inputs[id];
        if (!el) return true;
        const val = el.value ? el.value.trim() : '';
        let isValid = true;

        if (id === 'declaration') {
            if (!el.checked) {
                showError(id, 'You must agree to the declaration before submitting.');
                return false;
            } else {
                clearError(id);
                return true;
            }
        }

        if (el.hasAttribute('required') && !val) {
            showError(id, 'This field is required');
            return false;
        }

        switch (id) {
            case 'business_name':
                if (val && !rules.business_name.test(val)) {
                    showError(id, 'Min 3 characters required.');
                    isValid = false;
                }
                break;
            case 'br_number':
                el.value = val.toUpperCase();
                if (val && !rules.br_number.test(val)) {
                    showError(id, 'Invalid BR format. Min 3 characters required.');
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
                    showError(id, 'Min 3 chars. Letters, spaces, and initials only.');
                    isValid = false;
                }
                break;
            case 'owner_nic':
                el.value = val.toUpperCase();
                const cleanNic = el.value.trim();
                if (cleanNic && !rules.nic.test(cleanNic)) {
                    showError(id, 'Invalid NIC format. Use 9 digits+V/X or 12 digits.');
                    isValid = false;
                }
                break;
            case 'district':
                if (el.hasAttribute('required') && (!val || el.disabled)) {
                    showError(id, 'Please select a district.');
                    isValid = false;
                }
                break;
            case 'city':
                if (el.hasAttribute('required') && (!val || el.disabled)) {
                    showError(id, 'Please select a city.');
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
                // Strip +94 or 94 prefix and replace with 0
                let bpVal = val.replace(/\D/g, '');
                if (bpVal.startsWith('94') && bpVal.length === 11) bpVal = '0' + bpVal.slice(2);
                el.value = bpVal.slice(0, 10);
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
                    showError(id, 'Address too short (min 5 chars).');
                    isValid = false;
                }
                break;
            case 'postal_code':
                el.value = val.replace(/\D/g, '').slice(0,5);
                if (el.value && !rules.postal_code.test(el.value)) {
                    showError(id, 'Invalid postal code. Must be 5 digits.');
                    isValid = false;
                }
                break;
            case 'account_number':
                el.value = val.replace(/\D/g, '');
                if (el.value && !rules.account_number.test(el.value)) {
                    showError(id, 'Account number must be 10-16 digits.');
                    isValid = false;
                }
                break;
            case 'estimated_products':
                if (val) {
                    const num = parseInt(val);
                    if (num < 1) {
                        showError(id, 'Must be at least 1.');
                        isValid = false;
                    } else if (num > 1000) {
                        const errSpan = document.getElementById(`err_${id}`);
                        if(errSpan) {
                            errSpan.textContent = 'High volume sellers (>1,000) will receive dedicated onboarding support.';
                            errSpan.classList.remove('hidden');
                            errSpan.classList.add('text-orange-500');
                        }
                    } else {
                        const errSpan = document.getElementById(`err_${id}`);
                        if(errSpan) {
                            errSpan.classList.remove('text-orange-500');
                        }
                    }
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
                if (val) {
                    let testUrl = val;
                    if (!/^https?:\/\//i.test(testUrl)) {
                        testUrl = 'https://' + testUrl;
                    }
                    if (!rules.url.test(testUrl)) {
                        showError(id, 'Enter a valid website or social page URL.');
                        isValid = false;
                    }
                }
                break;
        }

        if (isValid) clearError(id);
        checkFormValidity();
        return isValid;
    };

    // Async NIC duplicate check
    let nicAbortCtrl = null;
    const checkNicAvailability = async () => {
        const el = inputs.owner_nic;
        if (!el) return;
        const nic = el.value.trim().toUpperCase();
        if (!nic || !rules.nic.test(nic)) return;

        if (nicAbortCtrl) nicAbortCtrl.abort();
        nicAbortCtrl = new AbortController();

        try {
            const res = await fetch(`../Backend/check-nic.php?nic=${encodeURIComponent(nic)}`, {
                signal: nicAbortCtrl.signal
            });
            const data = await res.json();
            if (!data.available) {
                showError('owner_nic', data.message || 'This NIC number has already been registered.');
                checkFormValidity();
            } else {
                const errSpan = document.getElementById('err_owner_nic');
                if (errSpan && errSpan.textContent.includes('already registered')) {
                    clearError('owner_nic');
                    checkFormValidity();
                }
            }
        } catch (e) {
            if (e.name !== 'AbortError') console.error('NIC check error', e);
        }
    };

    // Attach listeners to text/select inputs
    Object.keys(inputs).forEach(key => {
        if(inputs[key]) {
            inputs[key].addEventListener('blur', () => {
                validateField(key);
                if (key === 'owner_nic') checkNicAvailability();
            });
            inputs[key].addEventListener('input', () => {
                if(inputs[key].classList.contains('border-red-500')) {
                    validateField(key);
                    if (key === 'owner_nic' && rules.nic.test(inputs.owner_nic.value.trim())) {
                        checkNicAvailability();
                    }
                } else {
                    checkFormValidity();
                }
            });
            inputs[key].addEventListener('change', () => {
                validateField(key);
                checkFormValidity();
            });
        }
    });

    // Categories Validation
    const validateCategories = () => {
        const checked = document.querySelectorAll('.category-checkbox:checked');
        const container = document.getElementById('categories_container');
        const errSpan = document.getElementById('err_categories');
        if (checked.length === 0) {
            if(errSpan) {
                errSpan.textContent = 'Please select at least one category that you sell.';
                errSpan.classList.remove('hidden');
            }
            if (container) {
                container.classList.add('border-2', 'border-red-500', 'p-3', 'rounded-xl', 'bg-red-50/40');
            }
            return false;
        } else {
            if(errSpan) errSpan.classList.add('hidden');
            if (container) {
                container.classList.remove('border-2', 'border-red-500', 'p-3', 'rounded-xl', 'bg-red-50/40');
            }
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
        const file = input.files ? input.files[0] : null;
        const parentLabel = input.closest('label') || input.parentElement;
        const display = parentLabel ? parentLabel.querySelector('.file-name-display') : null;
        const icon = parentLabel ? parentLabel.querySelector('i') : null;
        const errSpan = document.getElementById(`err_${input.id}`);
        
        if (!file && input.hasAttribute('required')) {
            if(errSpan) {
                errSpan.textContent = 'This document is required.';
                errSpan.classList.remove('hidden');
            }
            if (parentLabel) {
                parentLabel.classList.add('!border-red-500', '!bg-red-50/40');
            }
            return false;
        }

        if (!file) {
            if (errSpan) errSpan.classList.add('hidden');
            if (parentLabel) {
                parentLabel.classList.remove('!border-red-500', '!bg-red-50/40');
            }
            return true;
        }

        // Check Size
        const maxSizeMB = parseFloat(input.getAttribute('data-max-size') || 5);
        if (file.size > maxSizeMB * 1024 * 1024) {
            if(errSpan) {
                errSpan.textContent = `File too large. Maximum size is ${maxSizeMB}MB.`;
                errSpan.classList.remove('hidden');
            }
            if (parentLabel) {
                parentLabel.classList.add('!border-red-500', '!bg-red-50/40');
            }
            input.value = ''; // clear
            resetFileUI(input, display, icon);
            return false;
        }

        // Check Type
        const acceptedTypes = (input.getAttribute('accept') || '').split(',').map(t => t.trim().toLowerCase());
        const fileExt = '.' + file.name.split('.').pop().toLowerCase();
        if (acceptedTypes.length > 0 && !acceptedTypes.includes(fileExt) && !acceptedTypes.includes(file.type.toLowerCase())) {
            if(errSpan) {
                errSpan.textContent = `Invalid format. Accepted: ${acceptedTypes.join(', ')}`;
                errSpan.classList.remove('hidden');
            }
            if (parentLabel) {
                parentLabel.classList.add('!border-red-500', '!bg-red-50/40');
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
                    if (parentLabel) {
                        parentLabel.classList.add('!border-red-500', '!bg-red-50/40');
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
                    if (parentLabel) {
                        parentLabel.classList.remove('!border-red-500', '!bg-red-50/40');
                    }
                }
            };
            img.src = objectUrl;
        }

        if(errSpan) errSpan.classList.add('hidden');
        if (parentLabel) {
            parentLabel.classList.remove('!border-red-500', '!bg-red-50/40');
            parentLabel.classList.add('border-[#0066FF]', 'bg-blue-50');
        }
        
        // Update UI Success
        if (display && icon) {
            display.textContent = file.name;
            display.classList.add('text-[#0066FF]', 'font-bold');
            display.classList.remove('text-gray-500');
            icon.classList.remove('fa-cloud-upload-alt', 'fa-image', 'fa-id-card', 'fa-store', 'fa-university', 'text-gray-400');
            icon.classList.add('fa-check-circle', 'text-[#0066FF]');
        }
        
        checkFormValidity();
        return true;
    };

    const resetFileUI = (input, display, icon) => {
        const parentLabel = input.closest('label') || input.parentElement;
        if(display) {
            display.textContent = 'Click to upload';
            display.classList.remove('text-[#0066FF]', 'font-bold');
            display.classList.add('text-gray-500');
        }
        if (parentLabel) {
            parentLabel.classList.remove('border-[#0066FF]', 'bg-blue-50');
            parentLabel.classList.add('!border-red-500', '!bg-red-50/40');
        }
        if(icon) {
            icon.className = 'fas fa-exclamation-circle text-2xl text-red-400 mb-2';
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
                else if (el.disabled) valid = false;
                else if (el.type !== 'checkbox' && !el.value.trim()) valid = false;
            }
            if (el.classList.contains('border-red-500')) valid = false;
        });

        const nicErr = document.getElementById('err_owner_nic');
        if (nicErr && !nicErr.classList.contains('hidden') && nicErr.textContent.trim() !== '') {
            valid = false;
        }

        if (!validateCategories()) valid = false;

        Object.values(fileInputs).forEach(fi => {
            if (fi && fi.hasAttribute('required') && !fi.files[0]) valid = false;
            const errSpan = document.getElementById(`err_${fi?.id}`);
            if (errSpan && !errSpan.classList.contains('hidden')) valid = false;
        });
    };

    window.checkSellerFormValidity = checkFormValidity;

    // Field human readable labels
    const fieldLabels = {
        business_name: 'Business Name',
        business_type: 'Business Type',
        br_number: 'Business Registration Number (BR)',
        date_of_incorporation: 'Date of Incorporation',
        nature_of_business: 'Nature of Business',
        owner_name: 'Owner Full Name',
        owner_nic: 'Owner NIC Number',
        personal_phone: 'Personal Phone',
        personal_email: 'Personal Email',
        business_phone: 'Business Phone',
        business_email: 'Business Email',
        address_line1: 'Address Line 1',
        address_line2: 'Address Line 2',
        province: 'Province',
        district: 'District',
        city: 'City',
        postal_code: 'Postal Code',
        bank_name: 'Bank Name',
        branch_name: 'Branch Name',
        account_number: 'Account Number',
        account_holder_name: 'Account Holder Name',
        social_website: 'Website / Facebook Page',
        declaration: 'Declaration Agreement'
    };

    const fileLabels = {
        certificate_file: 'BR Certificate (PDF required)',
        nic_file: 'Owner NIC Copy (PDF required)',
        logo_file: 'Business Logo (Min 200x200px image required)',
        shop_photo_file: 'Shop Photo',
        bank_book_file: 'Bank Book / Slip Photo'
    };

    // AJAX Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        let allValid = true;
        const invalidFields = [];
        let firstInvalidEl = null;

        // 1. Check regular inputs
        Object.keys(inputs).forEach(key => {
            if (!validateField(key)) {
                allValid = false;
                const label = fieldLabels[key] || key;
                let msg = '';
                const errSpan = document.getElementById(`err_${key}`) || document.getElementById(`err_business_reg_id`);
                if (errSpan && !errSpan.classList.contains('hidden') && errSpan.textContent.trim()) {
                    msg = errSpan.textContent.trim();
                }
                invalidFields.push(`<strong>${label}</strong>: ${msg || 'Please fill this field correctly'}`);
                if (!firstInvalidEl && inputs[key]) {
                    firstInvalidEl = inputs[key];
                }
            }
        });

        // 2. Check categories
        if (!validateCategories()) {
            allValid = false;
            invalidFields.push(`<strong>What do you sell?</strong>: Please select at least one category`);
            if (!firstInvalidEl) {
                firstInvalidEl = document.getElementById('categories_container');
            }
        }

        // 3. Check required files
        Object.keys(fileInputs).forEach(fileKey => {
            const fi = fileInputs[fileKey];
            if (fi && !validateFile(fi)) {
                allValid = false;
                const label = fileLabels[fileKey] || fileKey;
                let msg = '';
                const errSpan = document.getElementById(`err_${fi.id}`);
                if (errSpan && !errSpan.classList.contains('hidden') && errSpan.textContent.trim()) {
                    msg = errSpan.textContent.trim();
                }
                invalidFields.push(`<strong>${label}</strong>: ${msg || 'Document required'}`);
                if (!firstInvalidEl) {
                    firstInvalidEl = fi.closest('.file-upload-wrapper') || fi.closest('label') || fi;
                }
            }
        });

        if (!allValid) {
            formErrorText.innerHTML = `
                <div class="font-bold text-base text-red-800 mb-2">
                    <i class="fas fa-exclamation-triangle me-1"></i> Please correct the following ${invalidFields.length} issue(s) before submitting:
                </div>
                <ul class="list-disc list-inside space-y-1.5 text-sm font-medium text-red-700 bg-red-100/50 p-3 rounded-xl border border-red-200 mt-2">
                    ${invalidFields.map(f => `<li>${f}</li>`).join('')}
                </ul>
            `;
            formErrorBanner.classList.remove('hidden');
            formErrorBanner.classList.add('flex');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Registration',
                    html: `
                        <div class="text-left text-sm">
                            <p class="mb-3 font-semibold text-gray-700">Please complete the following required items:</p>
                            <ul class="list-disc list-inside space-y-1.5 text-red-600 bg-red-50 p-3 rounded-xl border border-red-100 max-h-60 overflow-y-auto">
                                ${invalidFields.map(f => `<li>${f}</li>`).join('')}
                            </ul>
                        </div>
                    `,
                    confirmButtonColor: '#0066FF',
                    confirmButtonText: 'Fix Highlighted Fields'
                }).then(() => {
                    if (firstInvalidEl) {
                        firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (typeof firstInvalidEl.focus === 'function') firstInvalidEl.focus();
                    }
                });
            } else {
                if (firstInvalidEl) {
                    firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof firstInvalidEl.focus === 'function') firstInvalidEl.focus();
                }
            }
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
                    const backendErrorList = [];
                    Object.keys(result.errors).forEach(key => {
                        showError(key, result.errors[key]);
                        const label = fieldLabels[key] || fileLabels[key] || key;
                        backendErrorList.push(`<strong>${label}</strong>: ${result.errors[key]}`);
                    });
                    formErrorText.innerHTML = `
                        <div class="font-bold text-base text-red-800 mb-2">
                            <i class="fas fa-exclamation-circle me-1"></i> We found issues with your submission:
                        </div>
                        <ul class="list-disc list-inside space-y-1.5 text-sm font-medium text-red-700 bg-red-100/50 p-3 rounded-xl border border-red-200 mt-2">
                            ${backendErrorList.map(f => `<li>${f}</li>`).join('')}
                        </ul>
                    `;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Submission Issues',
                            html: `
                                <div class="text-left text-sm">
                                    <ul class="list-disc list-inside space-y-1.5 text-red-600 bg-red-50 p-3 rounded-xl border border-red-100 max-h-60 overflow-y-auto">
                                        ${backendErrorList.map(f => `<li>${f}</li>`).join('')}
                                    </ul>
                                </div>
                            `,
                            confirmButtonColor: '#0066FF'
                        });
                    }
                } else if (result.message) {
                    formErrorText.textContent = result.message;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Submission Error',
                            text: result.message,
                            confirmButtonColor: '#0066FF'
                        });
                    }
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
