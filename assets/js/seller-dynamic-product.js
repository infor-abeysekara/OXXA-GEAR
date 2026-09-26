document.addEventListener('DOMContentLoaded', () => {
    const categorySelect = document.querySelector('select[name="category_id"]');
    const fixedAttributesCard = document.getElementById('fixedAttributesCard');
    const fixedAttributesContainer = document.getElementById('fixedAttributesContainer');
    const variantsCard = document.getElementById('variantsCard');
    const emptyVariantsState = document.getElementById('emptyVariantsState');
    const variantBuilder = document.getElementById('variantBuilder');
    const variantAxesToggles = document.getElementById('variantAxesToggles');
    const variantAxesValues = document.getElementById('variantAxesValues');
    const generateVariantsBtn = document.getElementById('generateVariantsBtn');
    const variantMatrixContainer = document.getElementById('variantMatrixContainer');
    const variantMatrixHead = document.getElementById('variantMatrixHead');
    const variantMatrixBody = document.getElementById('variantMatrixBody');
    const totalInventorySummary = document.getElementById('totalInventorySummary');
    const summaryTotalVariants = document.getElementById('summaryTotalVariants');
    const summaryTotalQty = document.getElementById('summaryTotalQty');

    let currentFixedAttributes = [];
    let currentVariantAttributes = [];
    
    // Track selected axes and their values
    // e.g. { "Color": ["Red", "Blue"], "Size": ["S", "M"] }
    let activeAxesData = {};

    categorySelect.addEventListener('change', async (e) => {
        const catId = e.target.value;
        if (!catId) {
            fixedAttributesCard.classList.add('hidden');
            emptyVariantsState.classList.remove('hidden');
            variantBuilder.classList.add('hidden');
            variantMatrixContainer.classList.add('hidden');
            totalInventorySummary.classList.add('hidden');
            return;
        }

        try {
            const res = await fetch(`../Backend/get_category_attributes.php?category_id=${catId}`);
            const json = await res.json();
            
            if (json.success) {
                currentFixedAttributes = json.data.fixed_attributes;
                currentVariantAttributes = json.data.variant_attributes;
                activeAxesData = {};
                renderFixedAttributes();
                
                // Hydrate fixed attributes if editing
                if (typeof existingFixedAttributes !== 'undefined') {
                    for (const [attrId, attrVal] of Object.entries(existingFixedAttributes)) {
                        const input = document.querySelector(`[name="fixed_attr[${attrId}]"]`);
                        if (input) input.value = attrVal;
                    }
                }
                
                renderVariantToggles();
                
                // Hydrate dynamic variants if editing
                if (typeof existingDynamicVariants !== 'undefined' && existingDynamicVariants.length > 0) {
                    hydrateDynamicVariants();
                }
            } else {
                alert('Failed to load attributes: ' + json.message);
            }
        } catch (err) {
            console.error('Error fetching attributes:', err);
        }
    });

    function hydrateDynamicVariants() {
        if (!existingDynamicVariants || existingDynamicVariants.length === 0) return;
        
        let axesToVals = {};
        
        // Collect all values for each axis
        existingDynamicVariants.forEach(variant => {
            if (variant.options) {
                try {
                    let opts = JSON.parse(variant.options);
                    Object.keys(opts).forEach(k => {
                        if (!axesToVals[k]) axesToVals[k] = new Set();
                        axesToVals[k].add(opts[k]);
                    });
                } catch(e) {}
            }
        });
        
        const axesKeys = Object.keys(axesToVals);
        if (axesKeys.length === 0) return;

        // Check toggles and build activeAxesData
        document.querySelectorAll('.variant-axis-toggle').forEach(cb => {
            const attrName = cb.dataset.name;
            if (axesKeys.includes(attrName)) {
                cb.checked = true;
                activeAxesData[attrName] = Array.from(axesToVals[attrName]);
                
                // Force UI render for this axis box
                handleAxisToggle({target: cb}); 
                renderTags(cb.value, attrName);
            }
        });
        
        renderMatrixWithExisting(axesKeys, existingDynamicVariants);
        // Clear global var so it doesn't re-trigger on subsequent category changes
        window.existingDynamicVariants = [];
    }

    function renderFixedAttributes() {
        if (currentFixedAttributes.length === 0) {
            fixedAttributesCard.classList.add('hidden');
            return;
        }
        fixedAttributesCard.classList.remove('hidden');
        fixedAttributesContainer.innerHTML = '';

        currentFixedAttributes.forEach(attr => {
            const col = document.createElement('div');
            col.className = 'col-span-1';
            
            const label = document.createElement('label');
            label.className = 'block text-sm font-bold text-navy mb-2 uppercase tracking-wide';
            label.innerText = attr.name + (attr.is_required ? ' *' : '');
            
            let inputHTML = '';
            const nameAttr = `fixed_attr[${attr.id}]`;
            const req = attr.is_required ? 'required' : '';
            
            if (attr.input_type === 'select' && attr.default_options && attr.default_options.length > 0) {
                let options = `<option value="">Select ${attr.name}</option>`;
                attr.default_options.forEach(opt => {
                    options += `<option value="${opt}">${opt}</option>`;
                });
                inputHTML = `<select name="${nameAttr}" ${req} class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">${options}</select>`;
            } else {
                inputHTML = `<input type="text" name="${nameAttr}" ${req} class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">`;
            }

            col.appendChild(label);
            col.insertAdjacentHTML('beforeend', inputHTML);
            fixedAttributesContainer.appendChild(col);
        });
    }

    function renderVariantToggles() {
        emptyVariantsState.classList.add('hidden');
        variantBuilder.classList.remove('hidden');
        variantMatrixContainer.classList.add('hidden');
        totalInventorySummary.classList.add('hidden');
        variantAxesToggles.innerHTML = '';
        variantAxesValues.innerHTML = '';

        if (currentVariantAttributes.length === 0) {
            variantBuilder.classList.add('hidden');
            emptyVariantsState.classList.remove('hidden');
            emptyVariantsState.innerHTML = `<i class="fas fa-info-circle text-4xl mb-4 text-gray-300"></i><span class="text-sm font-bold text-gray-400">This category has no variant options.</span>`;
            return;
        }

        currentVariantAttributes.forEach(attr => {
            const lbl = document.createElement('label');
            lbl.className = 'flex items-center gap-2 cursor-pointer bg-white px-3 py-2 border border-gray-200 rounded-lg shadow-sm hover:border-blue-400 transition-colors';
            
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = attr.id;
            cb.className = 'w-4 h-4 rounded text-[#0066FF] border-gray-300 focus:ring-[#0066FF] cursor-pointer variant-axis-toggle';
            cb.dataset.name = attr.name;
            
            lbl.appendChild(cb);
            lbl.insertAdjacentHTML('beforeend', `<span class="text-sm font-bold text-navy select-none">${attr.name}</span>`);
            variantAxesToggles.appendChild(lbl);

            cb.addEventListener('change', handleAxisToggle);
        });
    }

    function handleAxisToggle(e) {
        const cb = e.target;
        const attrId = cb.value;
        const attrName = cb.dataset.name;

        if (cb.checked) {
            if (!activeAxesData[attrName]) {
                activeAxesData[attrName] = [];
            }
            // Find attribute details to get default options
            const attrDef = currentVariantAttributes.find(a => a.id == attrId);
            
            const box = document.createElement('div');
            box.className = 'p-4 border border-gray-200 rounded-xl bg-gray-50';
            box.id = `axis-val-box-${attrId}`;
            
            let html = `<label class="block text-sm font-bold text-navy mb-2">Enter values for ${attrName}</label>`;
            html += `<div class="flex gap-2">
                        <input type="text" id="axis-input-${attrId}" class="flex-1 bg-white border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:border-blue-500" placeholder="e.g. Red, Blue, or S, M, L">
                        <button type="button" class="bg-navy hover:bg-gray-800 text-white px-4 rounded-xl font-bold text-sm btn-add-val" data-id="${attrId}" data-name="${attrName}">Add</button>
                     </div>
                     <div class="mt-3 flex flex-wrap gap-2" id="axis-tags-${attrId}"></div>`;
            
            // If default options exist, show them as quick chips
            if (attrDef && attrDef.default_options && attrDef.default_options.length > 0) {
                let chips = '';
                attrDef.default_options.forEach(opt => {
                    chips += `<span class="quick-chip cursor-pointer text-xs bg-white border border-gray-300 text-gray-600 px-2 py-1 rounded hover:bg-gray-100" data-id="${attrId}" data-name="${attrName}" data-val="${opt}">${opt}</span>`;
                });
                html += `<div class="mt-2 flex flex-wrap gap-1.5">${chips}</div>`;
            }

            box.innerHTML = html;
            variantAxesValues.appendChild(box);
            
        } else {
            delete activeAxesData[attrName];
            const box = document.getElementById(`axis-val-box-${attrId}`);
            if (box) box.remove();
        }
    }

    // Event delegation for Add Value buttons
    variantAxesValues.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-add-val')) {
            const attrId = e.target.dataset.id;
            const attrName = e.target.dataset.name;
            const input = document.getElementById(`axis-input-${attrId}`);
            const val = input.value.trim();
            if (val && !activeAxesData[attrName].includes(val)) {
                activeAxesData[attrName].push(val);
                input.value = '';
                renderTags(attrId, attrName);
            }
        }
        if (e.target.classList.contains('quick-chip')) {
            const attrId = e.target.dataset.id;
            const attrName = e.target.dataset.name;
            const val = e.target.dataset.val;
            if (!activeAxesData[attrName].includes(val)) {
                activeAxesData[attrName].push(val);
                renderTags(attrId, attrName);
            }
        }
        if (e.target.classList.contains('btn-remove-val')) {
            const attrId = e.target.dataset.id;
            const attrName = e.target.dataset.name;
            const val = e.target.dataset.val;
            activeAxesData[attrName] = activeAxesData[attrName].filter(v => v !== val);
            renderTags(attrId, attrName);
        }
    });

    function renderTags(attrId, attrName) {
        const container = document.getElementById(`axis-tags-${attrId}`);
        if (!container) return;
        container.innerHTML = '';
        activeAxesData[attrName].forEach(val => {
            const tag = document.createElement('div');
            tag.className = 'bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1.5 rounded-full flex items-center gap-2';
            tag.innerHTML = `<span>${val}</span> <i class="fas fa-times cursor-pointer hover:text-red-500 btn-remove-val" data-id="${attrId}" data-name="${attrName}" data-val="${val}"></i>`;
            container.appendChild(tag);
        });
    }

    // Generate Cartesian Product
    function cartesianProduct(arrays) {
        return arrays.reduce((a, b) => a.reduce((r, v) => r.concat(b.map(w => [].concat(v, w))), []), [[]]);
    }

    generateVariantsBtn.addEventListener('click', () => {
        const axesKeys = Object.keys(activeAxesData);
        if (axesKeys.length === 0) {
            alert('Please select at least one variant option to generate variants.');
            return;
        }

        const arraysToMultiply = [];
        axesKeys.forEach(k => {
            if (activeAxesData[k].length > 0) {
                arraysToMultiply.push(activeAxesData[k].map(val => ({ axis: k, value: val })));
            }
        });

        if (arraysToMultiply.length !== axesKeys.length) {
            alert('Please add at least one value for each selected variant option.');
            return;
        }

        const combinations = cartesianProduct(arraysToMultiply);
        renderMatrix(axesKeys, combinations);
    });

    function renderMatrix(axesKeys, combinations) {
        variantMatrixContainer.classList.remove('hidden');
        totalInventorySummary.classList.remove('hidden');
        summaryTotalVariants.innerText = combinations.length;
        
        // Build Header
        variantMatrixHead.innerHTML = '';
        axesKeys.forEach(k => {
            variantMatrixHead.insertAdjacentHTML('beforeend', `<th class="p-4">${k}</th>`);
        });
        variantMatrixHead.insertAdjacentHTML('beforeend', `
            <th class="p-4">SKU</th>
            <th class="p-4">Price (Rs)</th>
            <th class="p-4">Stock</th>
            <th class="p-4 w-12 text-center"><i class="fas fa-trash-alt"></i></th>
        `);

        // Get Base Price
        const baseSell = document.getElementById('baseSellPrice').value || 0;
        let totalQty = 0;

        variantMatrixBody.innerHTML = '';
        combinations.forEach((combo, index) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 group variant-row';
            
            // Build options JSON object
            let optObj = {};
            let comboVals = [];
            
            combo.forEach(c => {
                optObj[c.axis] = c.value;
                comboVals.push(c.value);
                tr.insertAdjacentHTML('beforeend', `<td class="p-4 font-bold text-navy text-sm">${c.value}</td>`);
            });

            // Create Auto SKU (e.g. VAL-VAL)
            const skuVal = comboVals.map(v => v.replace(/\s+/g, '-').toUpperCase()).join('-');

            tr.insertAdjacentHTML('beforeend', `
                <td class="p-4">
                    <input type="text" name="variant_sku[]" value="${skuVal}" class="w-full text-sm border-gray-200 rounded py-2 px-2">
                </td>
                <td class="p-4">
                    <input type="number" name="variant_price[]" value="${baseSell}" step="0.01" class="w-24 text-sm border-gray-200 rounded py-2 px-2 variant-price">
                </td>
                <td class="p-4">
                    <input type="number" name="variant_stock[]" value="0" class="w-20 text-sm border-gray-200 rounded py-2 px-2 variant-stock">
                </td>
                <td class="p-4 text-center">
                    <button type="button" class="text-gray-300 hover:text-red-500 transition-colors btn-remove-row"><i class="fas fa-times"></i></button>
                    <input type="hidden" name="variant_options[]" value='${JSON.stringify(optObj)}'>
                </td>
            `);
            variantMatrixBody.appendChild(tr);
        });
        
        updateTotalQty();
    }

    function renderMatrixWithExisting(axesKeys, existingVariants) {
        variantMatrixContainer.classList.remove('hidden');
        totalInventorySummary.classList.remove('hidden');
        summaryTotalVariants.innerText = existingVariants.length;
        
        // Build Header
        variantMatrixHead.innerHTML = '';
        axesKeys.forEach(k => {
            variantMatrixHead.insertAdjacentHTML('beforeend', `<th class="p-4">${k}</th>`);
        });
        variantMatrixHead.insertAdjacentHTML('beforeend', `
            <th class="p-4">SKU</th>
            <th class="p-4">Price (Rs)</th>
            <th class="p-4">Stock</th>
            <th class="p-4 w-12 text-center"><i class="fas fa-trash-alt"></i></th>
        `);

        variantMatrixBody.innerHTML = '';
        existingVariants.forEach(v => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 group variant-row';
            
            let opts = {};
            try { opts = JSON.parse(v.options || '{}'); } catch(e) {}
            
            axesKeys.forEach(k => {
                tr.insertAdjacentHTML('beforeend', `<td class="p-4 font-bold text-navy text-sm">${opts[k] || ''}</td>`);
            });

            tr.insertAdjacentHTML('beforeend', `
                <td class="p-4">
                    <input type="text" name="variant_sku[]" value="${v.sku || ''}" class="w-full text-sm border-gray-200 rounded py-2 px-2">
                </td>
                <td class="p-4">
                    <input type="number" name="variant_price[]" value="${v.price || ''}" step="0.01" class="w-24 text-sm border-gray-200 rounded py-2 px-2 variant-price">
                </td>
                <td class="p-4">
                    <input type="number" name="variant_stock[]" value="${v.stock_qty || 0}" class="w-20 text-sm border-gray-200 rounded py-2 px-2 variant-stock">
                </td>
                <td class="p-4 text-center">
                    <button type="button" class="text-gray-300 hover:text-red-500 transition-colors btn-remove-row"><i class="fas fa-times"></i></button>
                    <input type="hidden" name="variant_options[]" value='${JSON.stringify(opts)}'>
                </td>
            `);
            variantMatrixBody.appendChild(tr);
        });
        
        updateTotalQty();
    }

    variantMatrixBody.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remove-row');
        if (btn) {
            btn.closest('tr').remove();
            summaryTotalVariants.innerText = document.querySelectorAll('.variant-row').length;
            updateTotalQty();
        }
    });

    variantMatrixBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('variant-stock')) {
            updateTotalQty();
        }
    });

    function updateTotalQty() {
        let total = 0;
        document.querySelectorAll('.variant-stock').forEach(inp => {
            total += parseInt(inp.value) || 0;
        });
        summaryTotalQty.innerText = total;
    }

    // Auto-trigger on page load if category is already selected (e.g. edit product page)
    if (categorySelect && categorySelect.value) {
        categorySelect.dispatchEvent(new Event('change'));
    }

    // Expose global function for Base Pricing "Apply All" button
    window.applyBasePricesToVariants = function() {
        const baseSell = document.getElementById('selling_price').value || 0;
        if (baseSell <= 0) return;
        
        document.querySelectorAll('.variant-price').forEach(input => {
            input.value = baseSell;
        });
    };
});
