document.addEventListener('DOMContentLoaded', function() {
    let colorIdCounter = 0;

    const SIZING_SYSTEMS = {
        'US Sizes': ['3.5', '4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12', '12.5', '13', '14', '15', '16'],
        'UK Sizes': ['3', '3.5', '4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12', '13', '14', '15'],
        'EUR Sizes': ['35.5', '36', '37', '37.5', '38', '39', '39.5', '40', '40.5', '41.5', '42', '42.5', '43.5', '44', '44.5', '45', '46', '46.5', '47', '48', '49', '50.5'],
        'JP Sizes (cm)': ['22.5', '23', '23.5', '24', '24.5', '25', '25.25', '25.5', '26', '26.5', '27', '27.5', '28', '28.25', '28.5', '29', '29.5', '30', '30.5', '31', '32', '33'],
        'CN Sizes (mm)': ['225', '230', '235', '240', '245', '250', '255', '260', '265', '270', '275', '280', '285', '290', '295', '300', '305', '310', '320', '330'],
        'CM Sizes': ['22.5', '23', '23.5', '24', '24.5', '25', '25.25', '25.5', '26', '26.5', '27', '27.5', '28', '28.25', '28.5', '29', '29.5', '30', '30.5', '31', '32', '33'],
        'Clothing Sizes': ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
        'Weight (LBS)': ['1 LBS', '2 LBS', '5 LBS', '10 LBS'],
        'Weight (KG)': ['0.5 KG', '1 KG', '2.2 KG', '5 KG'],
        'Weight (Grams)': ['50g', '100g', '250g', '500g', '750g'],
        'Pieces / Bars': ['Single Bar', 'Pack of 3', 'Pack of 6', 'Box of 12', 'Box of 24'],
        'Servings': ['14 Servings', '30 Servings', '60 Servings', '90 Servings'],
        'One Size': ['Standard']
    };

    // ==========================================
    // GLOBAL PRODUCT IMAGES (DRAG & DROP + ACCUMULATIVE)
    // ==========================================
    const globalFileInput = document.getElementById('globalFileInput') || document.querySelector('.global-file-input');
    const globalPreviewContainer = document.querySelector('.global-preview-container');
    const globalFileLabel = document.getElementById('globalDropzone') || document.querySelector('.global-file-label');
    const globalDropzoneWrapper = document.getElementById('globalDropzoneWrapper');
    const globalImageCountBadge = document.getElementById('globalImageCountBadge');
    const imageUploadNotice = document.getElementById('imageUploadNotice');

    let globalUploadedFiles = []; // Master list of files

    // Helper: Extract File objects from DragEvent
    function extractFilesFromEvent(e) {
        let files = [];
        if (e.dataTransfer) {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                files = Array.from(e.dataTransfer.files);
            } else if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
                for (let i = 0; i < e.dataTransfer.items.length; i++) {
                    if (e.dataTransfer.items[i].kind === 'file') {
                        const f = e.dataTransfer.items[i].getAsFile();
                        if (f) files.push(f);
                    }
                }
            }
        }
        return files;
    }

    // Helper: Sync globalUploadedFiles to <input type="file">
    function syncFilesToInput() {
        if (!globalFileInput) return;
        try {
            const dt = new DataTransfer();
            globalUploadedFiles.forEach(file => dt.items.add(file));
            globalFileInput.files = dt.files;
        } catch (err) {
            console.error('Error syncing files to globalFileInput:', err);
        }
    }

    // Helper: Process new incoming files (from file picker OR drag-drop)
    function handleNewIncomingFiles(fileList) {
        if (!fileList || fileList.length === 0) return;

        let addedCount = 0;
        let oversizedCount = 0;
        const maxFiles = 10;
        const maxSizeBytes = 10 * 1024 * 1024; // 10MB

        Array.from(fileList).forEach(file => {
            const isImage = (file.type && file.type.startsWith('image/')) || /\.(jpe?g|png|webp|gif|bmp|jfif|avif|heic|svg)$/i.test(file.name || '');
            if (!isImage) return;
            if (file.size > maxSizeBytes) {
                oversizedCount++;
                return;
            }
            if (globalUploadedFiles.length >= maxFiles) return;

            // Check for duplicate file by name and size
            const isDup = globalUploadedFiles.some(f => f.name === file.name && f.size === file.size);
            if (isDup) return;

            globalUploadedFiles.push(file);
            addedCount++;
        });

        syncFilesToInput();
        renderGlobalPreviews();

        if (oversizedCount > 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: `${oversizedCount} photo(s) exceeded the 10MB limit and were skipped.`,
                    confirmButtonColor: '#0066FF'
                });
            } else {
                alert(`${oversizedCount} photo(s) exceeded the 10MB limit.`);
            }
        }
    }

    // Native file input change
    if (globalFileInput) {
        globalFileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                handleNewIncomingFiles(Array.from(e.target.files));
            }
        });
    }

    // Prevent browser default on window for all drag & drop events (prevents browser navigating away or dropping outside)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => {
        window.addEventListener(ev, (e) => {
            e.preventDefault();
        }, false);
    });

    // Helper to toggle visual active state on dropzone
    function setDropzoneHighlight(active) {
        if (!globalFileLabel) return;
        const dropText = globalFileLabel.querySelector('.dropzone-text');
        if (active) {
            globalFileLabel.classList.add('border-[#0066FF]', 'bg-blue-50/80', 'ring-4', 'ring-blue-100');
            if (dropText) dropText.innerHTML = '<span class="text-[#0066FF] font-black text-base">Release mouse to drop photos now!</span>';
        } else {
            globalFileLabel.classList.remove('border-[#0066FF]', 'bg-blue-50/80', 'ring-4', 'ring-blue-100');
            if (dropText) dropText.innerHTML = 'Drag & drop photos here, or <span class="text-[#0066FF] underline font-black">browse</span>';
        }
    }

    // Main dropzone drag & drop with counter to prevent false dragleaves
    let dropzoneCounter = 0;
    const dropzoneTargets = [globalFileLabel, globalDropzoneWrapper].filter(Boolean);

    dropzoneTargets.forEach(el => {
        el.addEventListener('dragenter', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneCounter++;
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
            setDropzoneHighlight(true);
        }, false);

        el.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
            setDropzoneHighlight(true);
        }, false);

        el.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneCounter--;
            if (dropzoneCounter <= 0) {
                dropzoneCounter = 0;
                setDropzoneHighlight(false);
            }
        }, false);

        el.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneCounter = 0;
            setDropzoneHighlight(false);

            const files = extractFilesFromEvent(e);
            if (files.length > 0) {
                handleNewIncomingFiles(files);
            }
        }, false);
    });

    // Drag and drop onto the preview container area
    if (globalPreviewContainer) {
        let previewCounter = 0;

        globalPreviewContainer.addEventListener('dragenter', (e) => {
            e.preventDefault();
            e.stopPropagation();
            previewCounter++;
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
            globalPreviewContainer.classList.add('ring-2', 'ring-[#0066FF]', 'rounded-xl', 'bg-blue-50/30');
        }, false);

        globalPreviewContainer.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        }, false);

        globalPreviewContainer.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            previewCounter--;
            if (previewCounter <= 0) {
                previewCounter = 0;
                globalPreviewContainer.classList.remove('ring-2', 'ring-[#0066FF]', 'rounded-xl', 'bg-blue-50/30');
            }
        }, false);

        globalPreviewContainer.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            previewCounter = 0;
            globalPreviewContainer.classList.remove('ring-2', 'ring-[#0066FF]', 'rounded-xl', 'bg-blue-50/30');
            const files = extractFilesFromEvent(e);
            if (files.length > 0) {
                handleNewIncomingFiles(files);
            }
        }, false);
    }

    function renderGlobalPreviews() {
        if (!globalPreviewContainer) return;
        globalPreviewContainer.innerHTML = '';
        const count = globalUploadedFiles.length;

        // Update badge and status notice
        if (globalImageCountBadge) {
            if (count === 0) {
                globalImageCountBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-500 border border-gray-200';
                globalImageCountBadge.innerHTML = '<i class="fas fa-camera me-1"></i> 0 / 10 photos (Min 1 required)';
            } else {
                globalImageCountBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-green-50 text-green-700 border border-green-200';
                globalImageCountBadge.innerHTML = `<i class="fas fa-check-circle me-1"></i> ${count} / 10 photos (Ready)`;
            }
        }

        if (imageUploadNotice) {
            if (count === 0) {
                imageUploadNotice.classList.remove('hidden');
                imageUploadNotice.querySelector('span').textContent = `Minimum 1 photo required for store listing.`;
            } else {
                imageUploadNotice.classList.add('hidden');
            }
        }

        // Show large dropzone when empty, hide when photos exist
        if (globalDropzoneWrapper) {
            if (count === 0) {
                globalDropzoneWrapper.classList.remove('hidden');
            } else {
                globalDropzoneWrapper.classList.add('hidden');
            }
        }

        // Render photo cards
        globalUploadedFiles.forEach((file, index) => {
            const objectUrl = URL.createObjectURL(file);
            const div = document.createElement('div');
            div.className = 'relative w-full aspect-square rounded-xl border border-gray-200 overflow-hidden bg-white shadow-sm group transition-all hover:shadow-md hover:border-gray-300';
            div.innerHTML = `
                <img src="${objectUrl}" class="w-full h-full object-cover">
                
                <button type="button" class="absolute top-2 right-2 w-7 h-7 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-red-500 shadow-md hover:bg-red-500 hover:text-white hover:scale-110 transition-all z-10" onclick="removeGlobalImage(${index})" title="Remove Photo">
                    <i class="fas fa-times text-xs"></i>
                </button>

                <span class="absolute top-2 left-2 bg-black/60 backdrop-blur text-white text-[10px] font-black px-1.5 py-0.5 rounded shadow">
                    #${index + 1}
                </span>

                ${index === 0 
                    ? '<span class="absolute bottom-0 left-0 right-0 bg-[#0066FF] text-white text-[10px] font-black tracking-wider text-center py-1.5 cursor-default flex items-center justify-center gap-1"><i class="fas fa-star text-xs"></i> PRIMARY</span>' 
                    : `<button type="button" class="absolute bottom-0 left-0 right-0 bg-gray-900/80 hover:bg-[#0066FF] text-white text-[10px] font-bold tracking-wider text-center py-1.5 transition-colors opacity-90 group-hover:opacity-100 flex items-center justify-center gap-1" onclick="makeGlobalImagePrimary(${index})"><i class="far fa-star text-xs"></i> SET PRIMARY</button>`
                }
            `;
            globalPreviewContainer.appendChild(div);
        });

        // Add "+ Add More" slot if count is between 1 and 9
        if (count > 0 && count < 10) {
            const addSlot = document.createElement('label');
            addSlot.className = 'border-2 border-dashed border-gray-300 hover:border-[#0066FF] rounded-xl flex flex-col items-center justify-center p-3 aspect-square cursor-pointer hover:bg-blue-50/40 transition-all text-center group';
            addSlot.title = 'Add more photos (up to 10)';
            addSlot.innerHTML = `
                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-blue-100 group-hover:scale-110 transition-all">
                    <i class="fas fa-plus text-gray-400 group-hover:text-[#0066FF] text-sm"></i>
                </div>
                <span class="text-xs font-bold text-navy group-hover:text-[#0066FF]">Add More</span>
                <span class="text-[10px] text-gray-400 mt-0.5">${count} / 10</span>
                <input type="file" multiple accept="image/*" class="hidden add-more-input">
            `;

            const addMoreInput = addSlot.querySelector('.add-more-input');
            addMoreInput.addEventListener('change', (e) => {
                handleNewIncomingFiles(e.target.files);
                addMoreInput.value = '';
            });

            // Drag over / drop on Add More card
            ['dragenter', 'dragover'].forEach(eventName => {
                addSlot.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    addSlot.classList.add('border-[#0066FF]', 'bg-blue-50');
                });
            });
            ['dragleave', 'dragend'].forEach(eventName => {
                addSlot.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    addSlot.classList.remove('border-[#0066FF]', 'bg-blue-50');
                });
            });
            addSlot.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                addSlot.classList.remove('border-[#0066FF]', 'bg-blue-50');
                if (e.dataTransfer && e.dataTransfer.files) {
                    handleNewIncomingFiles(e.dataTransfer.files);
                }
            });

            globalPreviewContainer.appendChild(addSlot);
        }
    }

    window.removeGlobalImage = function(indexToRemove) {
        globalUploadedFiles.splice(indexToRemove, 1);
        syncFilesToInput();
        renderGlobalPreviews();
    };

    window.makeGlobalImagePrimary = function(indexToPrimary) {
        if (indexToPrimary <= 0 || indexToPrimary >= globalUploadedFiles.length) return;
        const selected = globalUploadedFiles.splice(indexToPrimary, 1)[0];
        globalUploadedFiles.unshift(selected); // Move to front
        syncFilesToInput();
        renderGlobalPreviews();
    };

    const CATEGORY_SIZING = {
        '1': ['Clothing Sizes', 'One Size'], // Sports Wear
        '2': ['US Sizes', 'UK Sizes', 'EUR Sizes', 'JP Sizes (cm)', 'CN Sizes (mm)', 'CM Sizes'], // Footwear
        '3': ['Clothing Sizes', 'One Size'], // Fitness & Gym
        '4': ['Weight (LBS)', 'Weight (KG)', 'Weight (Grams)', 'Pieces / Bars', 'Servings'], // Nutrition
        '5': ['One Size', 'Clothing Sizes'], // Accessories
        '6': ['One Size'] // Equipment
    };

    const CATEGORY_VARIANT_TYPE = {
        '4': 'Flavor', // Nutrition uses Flavors
        'default': 'Color'
    };

    // Base Pricing Apply All Logic
    const applyBaseBtn = document.getElementById('applyBaseBtn');
    const baseBuyPrice = document.getElementById('baseBuyPrice');
    const baseSellPrice = document.getElementById('baseSellPrice');
    
    if (applyBaseBtn) {
        applyBaseBtn.addEventListener('click', () => {
            const costVal = baseBuyPrice.value;
            const sellingVal = baseSellPrice.value;
            
            if (costVal || sellingVal) {
                document.querySelectorAll('.color-block').forEach(block => {
                    const priceToggle = block.querySelector('.price-toggle');
                    
                    // Ensure the 'different prices' toggle is unchecked
                    if (priceToggle && priceToggle.checked) {
                        priceToggle.click(); 
                    }
                    
                    const masterCostInput = block.querySelector('.master-cost-input');
                    const masterSellingInput = block.querySelector('.master-selling-input');
                    
                    if (costVal && masterCostInput) masterCostInput.value = costVal;
                    if (sellingVal && masterSellingInput) masterSellingInput.value = sellingVal;
                    
                    const applyBtn = block.querySelector('.apply-master-price-btn');
                    if (applyBtn) applyBtn.click();
                });
                
                applyBaseBtn.innerHTML = '<i class="fas fa-check"></i> Applied to All!';
                applyBaseBtn.classList.replace('bg-black', 'bg-green-600');
                
                setTimeout(() => {
                    applyBaseBtn.innerHTML = '<i class="fas fa-check-double"></i> Apply All';
                    applyBaseBtn.classList.replace('bg-green-600', 'bg-black');
                }, 2000);
            }
        });
    }

    const categorySelect = document.querySelector('select[name="category_id"]');
    
    function getAvailableSizingSystems() {
        const catId = categorySelect ? categorySelect.value : '';
        if (catId && CATEGORY_SIZING[catId]) {
            return CATEGORY_SIZING[catId];
        }
        return ['US Sizes', 'UK Sizes', 'EUR Sizes', 'JP Sizes (cm)', 'CN Sizes (mm)', 'CM Sizes', 'Clothing Sizes', 'Weight (LBS)', 'Weight (KG)', 'One Size']; // fallback
    }

    function getVariantTerm() {
        const catId = categorySelect ? categorySelect.value : '';
        if (catId && CATEGORY_VARIANT_TYPE[catId]) {
            return CATEGORY_VARIANT_TYPE[catId];
        }
        return CATEGORY_VARIANT_TYPE['default'];
    }

    const addColorBtn = document.getElementById('addColorVariantBtn');
    const container = document.getElementById('colorVariantsContainer');
    const emptyState = document.getElementById('emptyColorsState');
    const summaryCard = document.getElementById('totalInventorySummary');

    // Handle category change
    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            const hasCategory = !!categorySelect.value;
            const variantTerm = getVariantTerm();
            
            if (hasCategory) {
                addColorBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                addColorBtn.disabled = false;
                addColorBtn.innerHTML = `<i class="fas fa-plus"></i> Add ${variantTerm} Variant`;
                if(emptyState.querySelector('span strong')) emptyState.querySelector('span strong').textContent = `+ Add ${variantTerm} Variant`;
                if(emptyState.querySelector('p')) emptyState.querySelector('p').classList.add('hidden');
            } else {
                addColorBtn.classList.add('opacity-50', 'cursor-not-allowed');
                addColorBtn.disabled = true;
                addColorBtn.innerHTML = `<i class="fas fa-plus"></i> Add Variant`;
                if(emptyState.querySelector('p')) emptyState.querySelector('p').classList.remove('hidden');
            }
            
            const allowedSystems = getAvailableSizingSystems();
            document.querySelectorAll('.sizing-system-select').forEach(select => {
                const currentVal = select.value;
                select.innerHTML = '';
                allowedSystems.forEach(sys => {
                    const opt = document.createElement('option');
                    opt.value = sys;
                    opt.textContent = sys;
                    select.appendChild(opt);
                });
                if (allowedSystems.includes(currentVal)) {
                    select.value = currentVal;
                }
                select.dispatchEvent(new Event('change'));
            });
        });
        // Initial state
        categorySelect.dispatchEvent(new Event('change'));
    }

    // 2. Add New Variant
    addColorBtn.addEventListener('click', () => {
        emptyState.classList.add('hidden');
        const variantTerm = getVariantTerm();
        
        // Collapse all previously open blocks
        document.querySelectorAll('.color-block').forEach(b => {
            const content = b.querySelector('.accordion-content');
            const icon = b.querySelector('.accordion-icon');
            if (content && !content.classList.contains('hidden')) {
                content.classList.add('hidden');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });
        
        const cId = colorIdCounter++;
        const block = document.createElement('div');
        block.className = 'color-block bg-white border border-gray-200 rounded-xl shadow-sm mb-4 transition-all duration-300';
        block.dataset.id = cId;
        
        block.innerHTML = `
            <!-- Accordion Header -->
            <div class="accordion-header flex justify-between items-center p-4 border-b border-gray-100 cursor-pointer hover:bg-gray-50 rounded-t-xl transition-colors">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chevron-up text-gray-400 accordion-icon"></i>
                    <span class="font-bold text-navy text-sm uppercase block-title">New ${variantTerm} Variant</span>
                </div>
                <button type="button" class="remove-color-btn text-gray-400 hover:text-red-500 transition-colors p-2" title="Remove ${variantTerm}">
                    <i class="fas fa-trash-alt text-lg"></i>
                </button>
            </div>
            
            <!-- Accordion Content -->
            <div class="accordion-content p-6">
                
                <!-- Variant Name & Thumbnail -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">${variantTerm} Name *</label>
                        <input type="text" name="colors[${cId}][name]" required placeholder="e.g. ${variantTerm === 'Flavor' ? 'Chocolate' : 'Green'}" class="color-name-input w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">${variantTerm} Thumbnail</label>
                        <div class="flex items-center gap-3">
                            <label class="w-10 h-10 rounded border border-gray-300 flex items-center justify-center cursor-pointer hover:bg-gray-50 overflow-hidden relative" title="Upload Swatch/Thumbnail">
                                <i class="fas fa-image text-gray-400"></i>
                                <img src="" class="thumbnail-preview hidden absolute inset-0 w-full h-full object-cover">
                                <input type="file" name="thumbnail_${cId}" accept="image/*" class="thumbnail-input hidden">
                            </label>
                            <span class="text-xs text-gray-400 font-bold">Buyer selection icon</span>
                        </div>
                    </div>
                </div>

                <!-- Sizes Section -->
                <div class="mb-4 pt-6 border-t border-gray-100">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Available Sizes For This ${variantTerm}</h3>
                            <p class="text-[10px] text-gray-400 mt-1">Select a sizing system to load chips.</p>
                        </div>
                        <select class="sizing-system-select w-full md:w-auto bg-white border border-gray-200 text-navy text-xs font-bold rounded px-3 py-2 outline-none">
                            ${getAvailableSizingSystems().map(sys => `<option value="${sys}">${sys}</option>`).join('')}
                        </select>
                    </div>
                    
                    <!-- Chips Container -->
                    <div class="sizes-container flex flex-wrap gap-2 mb-6">
                        <span class="text-xs text-gray-400">Please select a sizing system above.</span>
                    </div>
                </div>
                
                <!-- Pricing toggle & Master Price -->
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between bg-gray-50 p-4 rounded-xl mb-4 border border-gray-200">
                    <div class="flex items-center gap-2 mb-3 md:mb-0">
                        <input type="checkbox" id="diffPrice_${cId}" name="colors[${cId}][diff_price]" value="1" class="price-toggle w-4 h-4 text-[#0066FF] bg-white border-gray-300 rounded focus:ring-[#0066FF]">
                        <label for="diffPrice_${cId}" class="text-xs font-bold text-navy uppercase cursor-pointer tracking-wide">Different prices for each size</label>
                    </div>
                    
                    <div class="master-price-container flex flex-wrap items-center gap-3 w-full md:w-auto mt-3 md:mt-0">
                        <label class="text-[10px] font-bold text-gray-500 uppercase whitespace-nowrap">Master Cost (Rs)</label>
                        <input type="number" step="0.01" class="master-cost-input w-full md:w-24 bg-white border border-gray-200 rounded py-1.5 px-3 text-xs font-bold text-navy focus:border-[#0066FF] outline-none" placeholder="0.00">
                        
                        <label class="text-[10px] font-bold text-gray-500 uppercase whitespace-nowrap ml-2">Master Selling (Rs)</label>
                        <input type="number" step="0.01" class="master-selling-input w-full md:w-24 bg-white border border-gray-200 rounded py-1.5 px-3 text-xs font-bold text-navy focus:border-[#0066FF] outline-none" placeholder="0.00">
                        
                        <button type="button" class="apply-master-price-btn bg-navy text-white text-[10px] font-bold uppercase px-3 py-1.5 rounded hover:bg-gray-800 transition-colors">Apply</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-left border-collapse min-w-[600px] size-table">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-[120px]">Size</th>
                                <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-[100px]">Qty *</th>
                                <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider price-col hidden">Cost Price (Rs)</th>
                                <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider price-col hidden">Selling Price (Rs)</th>
                                <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider">SKU (Optional)</th>
                            </tr>
                        </thead>
                        <tbody class="tbody bg-white divide-y divide-gray-100">
                            <tr><td colspan="4" class="p-8 text-center text-xs text-gray-400 font-medium">Select sizes above to generate inventory rows</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        container.appendChild(block);
        
        setupBlockLogic(block);
        
        // Scroll to new block
        block.scrollIntoView({ behavior: 'smooth', block: 'end' });
        summaryCard.classList.remove('hidden');
        updateSummary();
    });

    function setupBlockLogic(block) {
        const cId = block.dataset.id;
        
        // Accordion Header Click
        const header = block.querySelector('.accordion-header');
        const content = block.querySelector('.accordion-content');
        const icon = block.querySelector('.accordion-icon');
        
        header.addEventListener('click', (e) => {
            if (e.target.closest('.remove-color-btn')) return; // Ignore if clicking remove
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });
        
        // Update Block Title when Variant Name is typed
        const nameInput = block.querySelector('.color-name-input');
        const blockTitle = block.querySelector('.block-title');
        nameInput.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            const variantTerm = getVariantTerm();
            blockTitle.textContent = val ? val : `New ${variantTerm} Variant`;
        });

        // Remove button
        block.querySelector('.remove-color-btn').addEventListener('click', () => {
            const variantTerm = getVariantTerm();
            Swal.fire({
                title: `Remove ${variantTerm}?`,
                text: `All sizes and inventory for this ${variantTerm.toLowerCase()} will be deleted.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove'
            }).then((result) => {
                if (result.isConfirmed) {
                    block.remove();
                    if (document.querySelectorAll('.color-block').length === 0) {
                        emptyState.classList.remove('hidden');
                        summaryCard.classList.add('hidden');
                    }
                    updateSummary();
                }
            });
        });

        // Price toggle
        const priceToggle = block.querySelector('.price-toggle');
        const masterPriceContainer = block.querySelector('.master-price-container');
        
        priceToggle.addEventListener('change', () => {
            const table = block.querySelector('.size-table');
            const priceCols = table.querySelectorAll('.price-col');
            if (priceToggle.checked) {
                priceCols.forEach(c => c.classList.remove('hidden'));
                masterPriceContainer.classList.add('hidden'); // Hide master price if different
            } else {
                priceCols.forEach(c => c.classList.add('hidden'));
                masterPriceContainer.classList.remove('hidden'); // Show master price
            }
        });
        
        // Apply Master Price Button
        const masterCostInput = block.querySelector('.master-cost-input');
        const masterSellingInput = block.querySelector('.master-selling-input');
        const applyBtn = block.querySelector('.apply-master-price-btn');
        applyBtn.addEventListener('click', () => {
            const costVal = masterCostInput.value;
            const sellingVal = masterSellingInput.value;
            if (costVal || sellingVal) {
                const table = block.querySelector('.size-table');
                if (costVal) {
                    table.querySelectorAll('.cost-input').forEach(input => input.value = costVal);
                }
                if (sellingVal) {
                    table.querySelectorAll('.selling-input').forEach(input => input.value = sellingVal);
                }
                
                // Add visual confirmation
                applyBtn.textContent = "Applied!";
                applyBtn.classList.add('bg-green-600');
                setTimeout(() => {
                    applyBtn.textContent = "Apply";
                    applyBtn.classList.remove('bg-green-600');
                }, 2000);
            }
        });

        // Thumbnail Preview with Drag & Drop
        const thumbnailInput = block.querySelector('.thumbnail-input');
        const thumbnailPreview = block.querySelector('.thumbnail-preview');
        const thumbnailLabel = thumbnailInput ? thumbnailInput.closest('label') : null;

        if (thumbnailInput) {
            thumbnailInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    thumbnailPreview.src = URL.createObjectURL(file);
                    thumbnailPreview.classList.remove('hidden');
                } else {
                    thumbnailPreview.classList.add('hidden');
                }
            });
        }

        if (thumbnailLabel && thumbnailInput) {
            ['dragenter', 'dragover'].forEach(eventName => {
                thumbnailLabel.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    thumbnailLabel.classList.add('border-[#0066FF]', 'bg-blue-50');
                });
            });
            ['dragleave', 'dragend'].forEach(eventName => {
                thumbnailLabel.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    thumbnailLabel.classList.remove('border-[#0066FF]', 'bg-blue-50');
                });
            });
            thumbnailLabel.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                thumbnailLabel.classList.remove('border-[#0066FF]', 'bg-blue-50');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    const file = e.dataTransfer.files[0];
                    if (file.type.startsWith('image/')) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        thumbnailInput.files = dt.files;
                        thumbnailPreview.src = URL.createObjectURL(file);
                        thumbnailPreview.classList.remove('hidden');
                    }
                }
            });
        }


        // Initialize Master prices from Base Pricing if present
        const baseBuyPrice = document.getElementById('baseBuyPrice');
        const baseSellPrice = document.getElementById('baseSellPrice');
        if (baseBuyPrice && baseBuyPrice.value && masterCostInput && !masterCostInput.value) {
            masterCostInput.value = baseBuyPrice.value;
        }
        if (baseSellPrice && baseSellPrice.value && masterSellingInput && !masterSellingInput.value) {
            masterSellingInput.value = baseSellPrice.value;
        }

        // Sizing System Dropdown
        const sizingSelect = block.querySelector('.sizing-system-select');
        sizingSelect.addEventListener('change', (e) => {
            const system = e.target.value;
            renderSizesForBlock(block, system);
        });
        
        // Qty change listener for summary
        block.addEventListener('input', (e) => {
            if(e.target.classList.contains('qty-input')) updateSummary();
        });

        // Trigger initial sizing system render immediately!
        sizingSelect.dispatchEvent(new Event('change'));
    }

    function renderSizesForBlock(block, system) {
        const sizeContainer = block.querySelector('.sizes-container');
        sizeContainer.innerHTML = '';
        const cId = block.dataset.id;
        
        if (!system || !SIZING_SYSTEMS[system]) {
            sizeContainer.innerHTML = '<span class="text-xs text-gray-400">Please select a sizing system above.</span>';
            renderTableForBlock(block); // Will clear table
            return;
        }

        const sizes = SIZING_SYSTEMS[system];

        // If generic/one-size: auto-select Standard and render inventory row immediately
        if (sizes.length === 1 && sizes[0].toLowerCase() === 'standard') {
            const chip = createASICSChip(sizes[0], '1');
            sizeContainer.appendChild(chip);
            renderTableForBlock(block);
            return;
        }

        // Multi-size toolbar (Select All / Clear All)
        const toolbar = document.createElement('div');
        toolbar.className = 'w-full flex justify-between items-center mb-2 pb-1 border-b border-gray-100';
        toolbar.innerHTML = `
            <span class="text-[11px] font-bold text-gray-500"><i class="fas fa-hand-pointer text-[#0066FF] me-1"></i> Click sizes you have in stock to enter QTY:</span>
            <div class="flex gap-2">
                <button type="button" class="select-all-sizes-btn text-[11px] font-bold text-[#0066FF] hover:underline cursor-pointer">Select All</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="clear-all-sizes-btn text-[11px] font-bold text-gray-400 hover:underline cursor-pointer">Clear All</button>
            </div>
        `;
        toolbar.querySelector('.select-all-sizes-btn').addEventListener('click', () => {
            sizeContainer.querySelectorAll('.size-chip').forEach(chip => {
                chip.dataset.active = '1';
                chip.className = 'size-chip bg-white text-navy border-blue-600 text-center px-5 py-3 rounded-full text-base font-medium cursor-pointer border-2 shadow-[inset_0_0_0_1px_rgba(37,99,235,1)] transition-colors select-none flex items-center justify-center';
            });
            renderTableForBlock(block);
        });
        toolbar.querySelector('.clear-all-sizes-btn').addEventListener('click', () => {
            sizeContainer.querySelectorAll('.size-chip').forEach(chip => {
                chip.dataset.active = '0';
                chip.className = 'size-chip bg-white text-navy border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-center px-5 py-3 rounded-full text-base font-medium cursor-pointer border transition-colors select-none flex items-center justify-center';
            });
            renderTableForBlock(block);
        });
        sizeContainer.appendChild(toolbar);

        sizes.forEach(sizeVal => {
            const chip = createASICSChip(sizeVal, '0');
            
            chip.addEventListener('click', () => {
                if (chip.dataset.active === '1') {
                    chip.dataset.active = '0';
                    chip.className = 'size-chip bg-white text-navy border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-center px-5 py-3 rounded-full text-base font-medium cursor-pointer border transition-colors select-none flex items-center justify-center';
                } else {
                    chip.dataset.active = '1';
                    chip.className = 'size-chip bg-white text-navy border-blue-600 text-center px-5 py-3 rounded-full text-base font-medium cursor-pointer border-2 shadow-[inset_0_0_0_1px_rgba(37,99,235,1)] transition-colors select-none flex items-center justify-center';
                }
                renderTableForBlock(block);
            });
            sizeContainer.appendChild(chip);
        });
        
        renderTableForBlock(block); // Clear table since system changed
    }
    
    function createASICSChip(val, active) {
        const chip = document.createElement('div');
        if (active === '1') {
            chip.className = 'size-chip bg-white text-navy border-blue-600 text-center px-5 py-3 rounded-full text-base font-medium cursor-pointer border-2 shadow-[inset_0_0_0_1px_rgba(37,99,235,1)] transition-colors select-none flex items-center justify-center';
        } else {
            // Unselected style (ASICS style: rounded, white bg, thin gray border, navy text)
            chip.className = 'size-chip bg-white text-navy border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-center px-5 py-3 rounded-full text-base font-medium cursor-pointer border transition-colors select-none flex items-center justify-center';
        }
        chip.style.minWidth = '4rem';
        chip.dataset.val = val;
        chip.dataset.active = active;
        chip.textContent = val;
        return chip;
    }

    function renderTableForBlock(block) {
        const cId = block.dataset.id;
        const tbody = block.querySelector('.tbody');
        const showPrice = block.querySelector('.price-toggle').checked;
        const masterCostInput = block.querySelector('.master-cost-input');
        const masterSellingInput = block.querySelector('.master-selling-input');
        
        // Get active sizes
        const activeChips = Array.from(block.querySelectorAll('.size-chip[data-active="1"]'));
        
        if (activeChips.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-xs text-gray-400 font-medium">Select sizes above to generate inventory rows</td></tr>';
            updateSummary();
            return;
        }

        // Save existing input values before re-rendering
        const currentData = {};
        tbody.querySelectorAll('tr[data-size]').forEach(tr => {
            const sVal = tr.dataset.size;
            currentData[sVal] = {
                qty: tr.querySelector('.qty-input').value,
                cost: tr.querySelector('.cost-input') ? tr.querySelector('.cost-input').value : '',
                selling: tr.querySelector('.selling-input') ? tr.querySelector('.selling-input').value : '',
                sku: tr.querySelector('.sku-input').value
            };
        });

        tbody.innerHTML = '';
        
        // Use Master price if available, or fallback to Base prices
        const baseBuyPrice = document.getElementById('baseBuyPrice');
        const baseSellPrice = document.getElementById('baseSellPrice');
        const defaultCost = masterCostInput.value || (baseBuyPrice ? baseBuyPrice.value : '');
        const defaultSelling = masterSellingInput.value || (baseSellPrice ? baseSellPrice.value : '');

        activeChips.forEach(chip => {
            const size = chip.dataset.val;
            const safeSize = encodeURIComponent(size);
            const prev = currentData[size] || {qty:'', cost: defaultCost, selling: defaultSelling, sku:''};
            
            const tr = document.createElement('tr');
            tr.dataset.size = size;
            tr.className = 'hover:bg-gray-50';
            
            tr.innerHTML = `
                <td class="p-3 text-sm font-bold text-navy border-r border-gray-100">
                    ${size}
                    <input type="hidden" name="colors[${cId}][sizes][${safeSize}][active]" value="1">
                </td>
                <td class="p-2 border-r border-gray-100">
                    <input type="number" name="colors[${cId}][sizes][${safeSize}][qty]" value="${prev.qty}" required min="0" placeholder="Enter Qty" class="qty-input w-full bg-white border border-gray-200 rounded py-2 px-3 text-sm font-bold text-navy focus:border-[#0066FF] outline-none">
                </td>
                <td class="p-2 border-r border-gray-100 price-col ${showPrice ? '' : 'hidden'}">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                        <input type="number" name="colors[${cId}][sizes][${safeSize}][cost]" value="${prev.cost}" step="0.01" class="cost-input w-full bg-white border border-gray-200 rounded py-2 pl-8 pr-3 text-sm font-bold text-navy focus:border-[#0066FF] outline-none" placeholder="Cost">
                    </div>
                </td>
                <td class="p-2 border-r border-gray-100 price-col ${showPrice ? '' : 'hidden'}">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                        <input type="number" name="colors[${cId}][sizes][${safeSize}][selling]" value="${prev.selling}" step="0.01" class="selling-input w-full bg-white border border-gray-200 rounded py-2 pl-8 pr-3 text-sm font-bold text-navy focus:border-[#0066FF] outline-none" placeholder="Selling">
                    </div>
                </td>
                <td class="p-2">
                    <input type="text" name="colors[${cId}][sizes][${safeSize}][sku]" value="${prev.sku}" class="sku-input w-full bg-white border border-gray-200 rounded py-2 px-3 text-sm text-navy focus:border-[#0066FF] outline-none" placeholder="Optional">
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        updateSummary();
    }

    function updateSummary() {
        const blocks = document.querySelectorAll('.color-block');
        let totalColors = blocks.length;
        let totalVariants = 0;
        let totalQty = 0;
        
        blocks.forEach(block => {
            const rows = block.querySelectorAll('.tbody tr[data-size]');
            totalVariants += rows.length;
            rows.forEach(tr => {
                const qty = parseInt(tr.querySelector('.qty-input').value) || 0;
                totalQty += qty;
            });
        });
        
        document.getElementById('summaryTotalColors').textContent = totalColors;
        document.getElementById('summaryTotalVariants').textContent = totalVariants;
        document.getElementById('summaryTotalQty').textContent = totalQty;
    }

    // Form submission validation for photos
    const addProductForm = document.getElementById('addProductForm');
    if (addProductForm) {
        addProductForm.addEventListener('submit', function(e) {
            // Validate minimum 1 image
            if (globalUploadedFiles.length < 1) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Photo Required',
                        text: `Please upload at least 1 photo for this product.`,
                        confirmButtonColor: '#0066FF'
                    });
                } else {
                    alert(`Please upload at least 1 photo for this product.`);
                }

                const imgCard = document.getElementById('productImagesCard');
                if (imgCard) {
                    imgCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    imgCard.classList.add('ring-4', 'ring-amber-300');
                    setTimeout(() => imgCard.classList.remove('ring-4', 'ring-amber-300'), 2500);
                }
                return false;
            }

            // Sync files one last time right before submitting
            syncFilesToInput();
        });
    }
});
