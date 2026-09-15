<?php
session_start();
$page_title = 'Add Product - OXXA GEAR Seller';
include('../include/header.php');
include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}
session_write_close(); // Free session lock for parallel AJAX requests

$stmt = $pdo->prepare("SELECT * FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['userid']]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$business || $business['is_approved'] == 0) {
    header('Location: business-registration.php');
    exit();
}

// Fetch categories
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active brands
$brandStmt = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC");
$brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Master Variants for dynamic UI
$masterStmt = $pdo->query("SELECT * FROM master_variants ORDER BY category_id, variant_type, display_order");
$masterRows = $masterStmt->fetchAll(PDO::FETCH_ASSOC);
$masterVariants = [];
foreach ($masterRows as $row) {
    $catId = $row['category_id'];
    $type = $row['variant_type'];
    if (!isset($masterVariants[$catId])) $masterVariants[$catId] = [];
    if (!isset($masterVariants[$catId][$type])) $masterVariants[$catId][$type] = [];
    $masterVariants[$catId][$type][] = [
        'value' => $row['variant_value'],
        'meta' => $row['meta_data'] ? json_decode($row['meta_data'], true) : null
    ];
}
?>
<script>
    const categoryVariants = <?= json_encode($masterVariants) ?>;
</script>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex items-center gap-4 mb-8">
            <a href="seller-dashboard.php" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-navy hover:bg-gray-50 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-navy uppercase tracking-wide">Add New Product</h1>
                <p class="text-sm text-slate">List a new item in your store</p>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center">
                <i class="fas fa-exclamation-circle me-3"></i>
                <span class="font-bold">Error: <?= htmlspecialchars($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <form id="addProductForm" action="../Backend/seller-product-backend.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <!-- Basic Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-box text-[#0066FF] me-2"></i> Product Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Product Name *</label>
                        <input type="text" name="name" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Brand</label>
                        <select name="brand_id" class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">No Brand</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Category *</label>
                        <select name="category_id" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Description *</label>
                        <textarea name="description" rows="4" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all"></textarea>
                    </div>
                </div>
            </div>

            <!-- Financials & 10% Rule -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-green-50 rounded-full opacity-50 pointer-events-none"></div>
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-coins text-green-500 me-2"></i> Pricing & Profit</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Inputs -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">
                                Cost Price (Rs.) * 
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                                <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all font-bold">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">
                                Selling Price (Rs.) *
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                                <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all font-bold">
                            </div>
                        </div>
                    </div>

                    <!-- Calculator UI -->
                    <div class="bg-navy rounded-xl p-6 text-white shadow-inner flex flex-col justify-center">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Financial Breakdown</h3>
                        
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm text-gray-300">Selling Price</span>
                            <span class="font-bold" id="calc_selling">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-700">
                            <span class="text-sm text-gray-300">- Cost Price</span>
                            <span class="font-bold text-red-400" id="calc_cost">Rs. 0.00</span>
                        </div>
                        
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-bold text-[#0066FF]">Gross Profit</span>
                            <span class="font-bold text-[#0066FF]" id="calc_profit">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-700">
                            <span class="text-sm text-gray-300">- Admin Commission (10%)</span>
                            <span class="font-bold text-yellow-400" id="calc_commission">Rs. 0.00</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-base font-black uppercase tracking-wide text-green-400">Your Net Earning</span>
                            <span class="text-xl font-black text-green-400" id="calc_earning">Rs. 0.00</span>
                        </div>
                        <p class="text-[10px] text-gray-500 text-right mt-1">Per item sold</p>
                    </div>
                </div>
            </div>

                        <!-- Images & Variants -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-images text-purple-500 me-2"></i> Media & Inventory</h2>
                
                <!-- 1. Product Images -->
                <div class="mb-10">
                    <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Product Images * <span class="text-xs text-gray-400 font-normal normal-case ml-2">(Min 4, Max 10 images, 2MB each. First image is primary)</span></label>
                    <div id="imageDropzone" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-[#0066FF] transition-colors relative cursor-pointer bg-gray-50/50">
                        <input type="file" name="images[]" id="imageInput" multiple accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer hidden">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                        <p class="text-sm text-slate font-bold">Click to select files or drag & drop</p>
                    </div>
                    <!-- Image Previews Container -->
                    <div id="imagePreviewContainer" class="flex flex-wrap gap-4 mt-4 hidden">
                        <!-- Dynamic Thumbnails Go Here -->
                    </div>
                </div>

                <hr class="border-gray-100 mb-8">

                <!-- 2. Dynamic Variants UI -->
                <div id="dynamicVariantUI" class="mb-8">
                    <!-- Rendered by JS -->
                    <div class="p-6 text-center text-gray-400 text-sm font-medium border-2 border-dashed border-gray-200 rounded-xl">
                        Select a Category above to load Variant options.
                    </div>
                </div>

                <!-- 3. Variant Table -->
                <div>
                    <div class="flex justify-between items-end mb-3">
                        <label class="block text-sm font-bold text-navy uppercase tracking-wide">Variant Table & Inventory</label>
                        <button type="button" id="generateVariantsBtn" class="bg-navy hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase transition-colors hidden">
                            <i class="fas fa-magic me-1"></i> Generate Table
                        </button>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-left border-collapse" id="variantTable">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200" id="variantTableHeader">
                                    <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Variant 1</th>
                                    <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Variant 2</th>
                                    <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                    <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">SKU</th>
                                    <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-12 text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="variantTableBody" class="divide-y divide-gray-100">
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">
                                        Select a Category first.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 flex justify-between items-center">
                        <button type="button" id="addManualRowBtn" class="text-sm font-bold text-[#0066FF] hover:underline hidden"><i class="fas fa-plus me-1"></i> Add Manual Row</button>
                        <div class="text-sm font-bold text-navy bg-gray-50 px-4 py-2 rounded-lg border border-gray-200">Total Qty: <span id="totalQtyCounter" class="text-[#0066FF] text-lg ms-1">0</span></div>
                    </div>
                </div>

            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex items-start">
                <i class="fas fa-info-circle text-yellow-600 mt-0.5 me-3"></i>
                <p class="text-sm text-yellow-800">
                    <strong>Note:</strong> Once submitted, your product will be marked as <span class="font-bold uppercase tracking-widest text-[10px] bg-yellow-200 px-2 py-0.5 rounded ml-1">Pending Approval</span>. It will go live on the OXXA GEAR store immediately after admin verification.
                </p>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" name="add_product" class="bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center text-lg">
                    Submit for Approval <i class="fas fa-paper-plane ms-2"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<script>
// Financial Calculator Logic
const costInput = document.getElementById('cost_price');
const sellInput = document.getElementById('selling_price');

const vSell = document.getElementById('calc_selling');
const vCost = document.getElementById('calc_cost');
const vProfit = document.getElementById('calc_profit');
const vComm = document.getElementById('calc_commission');
const vEarn = document.getElementById('calc_earning');

function updateFinancials() {
    let cost = parseFloat(costInput.value) || 0;
    let sell = parseFloat(sellInput.value) || 0;
    
    const submitBtn = document.querySelector('button[name="add_product"]');
    
    // Check if selling is higher than cost
    if (sell > 0 && cost > 0 && sell < cost) {
        vSell.textContent = 'Error';
        vSell.classList.add('text-red-500');
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        return;
    } else {
        vSell.classList.remove('text-red-500');
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    
    let profit = sell - cost;
    if(profit < 0) profit = 0; // Prevent negative profit calculations in UI
    
    let commission = profit * 0.10;
    let earning = cost + (profit * 0.90);
    
    vSell.textContent = 'Rs. ' + sell.toFixed(2);
    vCost.textContent = 'Rs. ' + cost.toFixed(2);
    vProfit.textContent = 'Rs. ' + profit.toFixed(2);
    vComm.textContent = 'Rs. ' + commission.toFixed(2);
    vEarn.textContent = 'Rs. ' + earning.toFixed(2);
}

costInput.addEventListener('input', updateFinancials);
sellInput.addEventListener('input', updateFinancials);



    // --- ADVANCED INVENTORY LOGIC ---
    
    // 1. Image Upload Logic
    const imageInput = document.getElementById('imageInput');
    const imageDropzone = document.getElementById('imageDropzone');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    let selectedFiles = [];
    const MAX_FILES = 10;
    const MAX_SIZE = 2 * 1024 * 1024; // 2MB

    imageDropzone.addEventListener('click', () => imageInput.click());

    imageDropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        imageDropzone.classList.add('border-[#0066FF]', 'bg-blue-50/50');
    });

    imageDropzone.addEventListener('dragleave', () => {
        imageDropzone.classList.remove('border-[#0066FF]', 'bg-blue-50/50');
    });

    imageDropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        imageDropzone.classList.remove('border-[#0066FF]', 'bg-blue-50/50');
        handleFiles(e.dataTransfer.files);
    });

    imageInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        for (let i = 0; i < files.length; i++) {
            if (selectedFiles.length >= MAX_FILES) {
                Swal.fire({ icon: 'warning', title: 'Limit Reached', text: `Maximum ${MAX_FILES} images allowed.` });
                break;
            }
            const file = files[i];
            if (file.size > MAX_SIZE) {
                Swal.fire({ icon: 'error', title: 'File Too Large', text: `File ${file.name} is larger than 2MB.` });
                continue;
            }
            if (file.type.startsWith('image/')) {
                selectedFiles.push(file);
            }
        }
        updatePreviews();
        updateFileInput();
    }

    function updatePreviews() {
        imagePreviewContainer.innerHTML = '';
        if (selectedFiles.length > 0) {
            imagePreviewContainer.classList.remove('hidden');
        } else {
            imagePreviewContainer.classList.add('hidden');
        }

        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'relative w-24 h-24 rounded-lg border border-gray-200 overflow-hidden group cursor-move shadow-sm bg-white shrink-0';
                div.draggable = true;
                
                div.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <button type="button" class="text-white hover:text-red-400 p-1" onclick="removeImage(${index})"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                
                if (index === 0) {
                    div.innerHTML += `<div class="absolute top-0 left-0 right-0 bg-[#0066FF] text-white text-[9px] font-bold text-center uppercase py-0.5 tracking-wider">Primary</div>`;
                }

                // Drag Events for reordering
                div.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('text/plain', index);
                });
                div.addEventListener('dragover', (e) => e.preventDefault());
                div.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
                    const toIndex = index;
                    if (fromIndex !== toIndex) {
                        const temp = selectedFiles[fromIndex];
                        selectedFiles.splice(fromIndex, 1);
                        selectedFiles.splice(toIndex, 0, temp);
                        updatePreviews();
                        updateFileInput();
                    }
                });

                imagePreviewContainer.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }

    function removeImage(index) {
        selectedFiles.splice(index, 1);
        updatePreviews();
        updateFileInput();
    }

    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        imageInput.files = dt.files;
    }

    const addProductForm = document.getElementById('addProductForm');
    if (addProductForm) {
        addProductForm.addEventListener('submit', function(e) {
            if (selectedFiles.length < 4) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Not Enough Images',
                    text: 'You must upload at least 4 images to showcase your product properly.'
                });
            }
        });
    }

    // 2. Dynamic Categories Logic
    const standardColors = [
        {name: 'Black', hex: '#000000'}, {name: 'White', hex: '#FFFFFF'}, {name: 'Red', hex: '#EF4444'},
        {name: 'Blue', hex: '#3B82F6'}, {name: 'Green', hex: '#10B981'}, {name: 'Yellow', hex: '#F59E0B'},
        {name: 'Orange', hex: '#F97316'}, {name: 'Purple', hex: '#8B5CF6'}, {name: 'Pink', hex: '#EC4899'},
        {name: 'Gray', hex: '#6B7280'}, {name: 'Brown', hex: '#92400E'}, {name: 'Navy', hex: '#1E3A8A'}
    ];

    let activeSet1 = new Set(); // Stores text values
    let activeSet2 = new Set(); // Stores text or objects {name, hex}
    let activeSet3 = new Set(); // Text values

    const categorySelect = document.querySelector('select[name="category_id"]');
    const dynamicUI = document.getElementById('dynamicVariantUI');
    const tableHeader = document.getElementById('variantTableHeader');
    const tableBody = document.getElementById('variantTableBody');
    const genBtn = document.getElementById('generateVariantsBtn');
    const addManualBtn = document.getElementById('addManualRowBtn');
    const productNameInput = document.querySelector('input[name="name"]');

    let currentConfig = null; 

    categorySelect.addEventListener('change', (e) => {
        const catId = e.target.value;
        if (!catId) {
            dynamicUI.innerHTML = `<div class="p-6 text-center text-gray-400 text-sm font-medium border-2 border-dashed border-gray-200 rounded-xl">Select a Category above to load Variant options.</div>`;
            tableBody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-gray-400 text-sm font-medium">Select a Category first.</td></tr>`;
            genBtn.classList.add('hidden');
            addManualBtn.classList.add('hidden');
            return;
        }

        renderCategoryUI(catId);
    });

    function getCatConfig(catId) {
        const catName = categorySelect.options[categorySelect.selectedIndex].text.toUpperCase();
        if (catName.includes('SPORTS WEAR')) return { type: 'sports', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: true };
        if (catName.includes('FOOTWEAR')) return { type: 'footwear', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('FITNESS')) return { type: 'fitness', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('ACCESSORIES')) return { type: 'accessories', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('EQUIPMENT')) return { type: 'equipment', col1: 'Variant', col2: null, dbCol1: 'variant_size[]', dbCol2: null, hasFit: false };
        if (catName.includes('NUTRITION')) return { type: 'nutrition', col1: 'Flavor', col2: 'Weight', dbCol1: 'variant_flavor[]', dbCol2: 'variant_weight[]', hasFit: false };
        return { type: 'default', col1: 'Variant 1', col2: 'Variant 2', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
    }

    function renderCategoryUI(catId) {
        activeSet1.clear();
        activeSet2.clear();
        activeSet3.clear();
        tableBody.innerHTML = `<tr><td colspan="5" class="p-6 text-center text-gray-400 text-sm font-medium">Click "Generate Table" to create inventory rows.</td></tr>`;
        genBtn.classList.remove('hidden');
        addManualBtn.classList.remove('hidden');

        currentConfig = getCatConfig(catId);
        const data = categoryVariants[catId] || {};

        let html = '<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">';
        
        // Render Column 1
        if (currentConfig.col1) {
            html += `<div><label class="block text-sm font-bold text-navy uppercase tracking-wide mb-3">${currentConfig.col1}</label>`;
            
            if (currentConfig.type === 'footwear') {
                html += `<div class="flex gap-2 mb-3">
                    <select class="text-xs font-bold bg-blue-50 text-[#0066FF] border-none rounded-lg py-1 px-3 cursor-pointer outline-none focus:ring-2 focus:ring-blue-300">
                        <option value="US">US System</option>
                    </select>
                </div>`;
            }

            const dbTypeKey = currentConfig.col1; 
            let chips = data[dbTypeKey] || [];
            
            html += `<div class="flex flex-wrap gap-2 mb-3">`;
            chips.forEach(c => {
                let metaText = '';
                if (c.meta && currentConfig.type === 'footwear') {
                    metaText = `UK ${c.meta.UK} / EU ${c.meta.EU}`;
                }
                html += `
                    <div class="border border-gray-200 rounded-lg px-4 py-2 cursor-pointer hover:border-[#0066FF] transition-colors bg-white var-chip set1-chip text-center" data-val="${c.value}">
                        <span class="block text-sm font-bold text-navy">${c.value}</span>
                        ${metaText ? `<span class="block text-[10px] text-gray-400">${metaText}</span>` : ''}
                    </div>
                `;
            });
            html += `</div>`;
            
            if (currentConfig.hasFit) {
                html += `<label class="block text-sm font-bold text-navy uppercase tracking-wide mb-2 mt-4">Fit Type</label>
                <div class="flex flex-wrap gap-2">`;
                (data['Fit Type'] || []).forEach(f => {
                    html += `<div class="border border-gray-200 rounded-lg px-3 py-1 cursor-pointer hover:border-[#0066FF] transition-colors bg-white var-chip set3-chip text-xs font-bold text-navy" data-val="${f.value}">${f.value}</div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        // Render Column 2
        if (currentConfig.col2) {
            html += `<div><label class="block text-sm font-bold text-navy uppercase tracking-wide mb-3">${currentConfig.col2}</label>`;
            
            if (currentConfig.col2 === 'Color') {
                html += `<div class="flex flex-wrap gap-2.5 mb-4">`;
                standardColors.forEach(c => {
                    html += `<div class="w-8 h-8 rounded-full cursor-pointer transition-all flex items-center justify-center color-chip" data-val="${c.name}" data-hex="${c.hex}" style="background-color: ${c.hex}; ${c.hex==='#FFFFFF'?'border:1px solid #e5e7eb;':''}"></div>`;
                });
                html += `</div>`;
            } else {
                const dbTypeKey = currentConfig.col2; 
                let chips = data[dbTypeKey] || [];
                html += `<div class="flex flex-wrap gap-2 mb-3">`;
                chips.forEach(c => {
                    html += `<div class="border border-gray-200 rounded-lg px-4 py-2 cursor-pointer hover:border-[#0066FF] transition-colors bg-white var-chip set2-chip" data-val="${c.value}">${c.value}</div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        html += '</div>';
        dynamicUI.innerHTML = html;

        // Build Table Headers
        let th = '';
        if (currentConfig.col1) th += `<th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">${currentConfig.col1}</th>`;
        if (currentConfig.col2) th += `<th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">${currentConfig.col2}</th>`;
        if (currentConfig.hasFit) th += `<th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Fit Type</th>`;
        th += `<th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Qty</th>
               <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider">SKU</th>
               <th class="p-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-12 text-center"></th>`;
        tableHeader.innerHTML = th;

        bindChipEvents();
    }

    function bindChipEvents() {
        document.querySelectorAll('.var-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const val = this.dataset.val;
                let activeSet = this.classList.contains('set1-chip') ? activeSet1 : (this.classList.contains('set2-chip') ? activeSet2 : activeSet3);
                
                if (activeSet.has(val)) {
                    activeSet.delete(val);
                    this.classList.remove('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]');
                    this.classList.add('bg-white', 'text-navy');
                } else {
                    activeSet.add(val);
                    this.classList.add('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]');
                    this.classList.remove('bg-white', 'text-navy');
                }
            });
        });

        document.querySelectorAll('.color-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const val = this.dataset.val;
                const hex = this.dataset.hex;
                let exists = false;
                let objRef = null;
                activeSet2.forEach(c => { if(c.name === val) { exists = true; objRef = c; }});

                if (exists) {
                    activeSet2.delete(objRef);
                    this.classList.remove('ring-2', 'ring-offset-2', 'ring-[#0066FF]');
                    this.innerHTML = '';
                } else {
                    activeSet2.add({name: val, hex: hex});
                    this.classList.add('ring-2', 'ring-offset-2', 'ring-[#0066FF]');
                    const checkColor = hex.toUpperCase() === '#FFFFFF' ? '#000' : '#FFF';
                    this.innerHTML = `<i class="fas fa-check text-[10px]" style="color: ${checkColor}"></i>`;
                }
            });
        });
    }

    function generateSKU(v1, v2, v3) {
        let base = (productNameInput && productNameInput.value) ? productNameInput.value.substring(0, 4).toUpperCase() : 'PRD';
        if (!base) base = 'PRD';
        
        let p1 = v1 ? v1.replace(/[^a-zA-Z0-9]/g, '').substring(0, 4).toUpperCase() : '';
        let p2 = '';
        if (v2) {
            if (typeof v2 === 'object') p2 = v2.name.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase();
            else p2 = v2.replace(/[^a-zA-Z0-9]/g, '').substring(0, 4).toUpperCase();
        }
        let p3 = v3 ? v3.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase() : '';

        let sku = base;
        if(p1) sku += '-' + p1;
        if(p2) sku += '-' + p2;
        if(p3) sku += '-' + p3;
        return sku;
    }

    function createVariantRow(v1, v2, v3) {
        const tr = document.createElement('tr');
        const sku = generateSKU(v1, v2, v3);
        let html = '';
        
        if (currentConfig.col1) {
            html += `<td class="p-3 border-b border-gray-100">
                <input type="text" name="${currentConfig.dbCol1}" value="${v1||''}" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-lg py-1.5 px-3 text-sm focus:border-[#0066FF] outline-none">
            </td>`;
        }

        if (currentConfig.col2) {
            if (currentConfig.col2 === 'Color') {
                const colorName = v2 ? v2.name : '';
                const colorHex = v2 ? v2.hex : 'transparent';
                html += `<td class="p-3 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        ${v2 ? `<span class="w-4 h-4 rounded-full border border-gray-200 block shrink-0" style="background-color: ${colorHex};"></span>` : ''}
                        <input type="text" name="${currentConfig.dbCol2}" value="${colorName}" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-lg py-1.5 px-3 text-sm focus:border-[#0066FF] outline-none">
                    </div>
                </td>`;
            } else {
                html += `<td class="p-3 border-b border-gray-100">
                    <input type="text" name="${currentConfig.dbCol2}" value="${v2||''}" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-lg py-1.5 px-3 text-sm focus:border-[#0066FF] outline-none">
                </td>`;
            }
        }

        if (currentConfig.hasFit) {
            html += `<td class="p-3 border-b border-gray-100">
                <input type="text" name="variant_fit[]" value="${v3||''}" class="w-full bg-gray-50 border border-gray-200 text-navy rounded-lg py-1.5 px-3 text-sm focus:border-[#0066FF] outline-none">
            </td>`;
        }

        html += `<td class="p-3 border-b border-gray-100">
                <input type="number" name="variant_qty[]" value="0" min="0" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-lg py-1.5 px-3 text-sm focus:border-[#0066FF] outline-none variant-qty-input" oninput="updateTotalQty()">
            </td>
            <td class="p-3 border-b border-gray-100">
                <input type="text" name="variant_sku[]" value="${sku}" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-lg py-1.5 px-3 text-sm focus:border-[#0066FF] outline-none uppercase">
            </td>
            <td class="p-3 border-b border-gray-100 text-center">
                <button type="button" class="text-red-400 hover:text-red-600 p-1" onclick="this.closest('tr').remove(); updateTotalQty();"><i class="fas fa-trash"></i></button>
            </td>`;
        
        tr.innerHTML = html;
        return tr;
    }

    genBtn.addEventListener('click', () => {
        let arr1 = Array.from(activeSet1);
        let arr2 = Array.from(activeSet2);
        let arr3 = Array.from(activeSet3);

        if (arr1.length === 0) arr1 = [null];
        if (arr2.length === 0) arr2 = [null];
        if (arr3.length === 0) arr3 = [null];

        if (activeSet1.size === 0 && activeSet2.size === 0 && activeSet3.size === 0) {
            alert('Please select at least one variant option to generate the table.');
            return;
        }

        tableBody.innerHTML = '';

        arr1.forEach(v1 => {
            arr2.forEach(v2 => {
                arr3.forEach(v3 => {
                    tableBody.appendChild(createVariantRow(v1, v2, v3));
                });
            });
        });
        updateTotalQty();
    });

    addManualBtn.addEventListener('click', () => {
        if (tableBody.querySelector('td[colspan="5"]')) tableBody.innerHTML = '';
        tableBody.appendChild(createVariantRow('', '', ''));
    });

    function updateTotalQty() {
        const qtyInputs = document.querySelectorAll('.variant-qty-input');
        let total = 0;
        qtyInputs.forEach(input => total += parseInt(input.value) || 0);
        document.getElementById('totalQtyCounter').textContent = total;
    }
    // Make sure we attach event to financial calculator submit to not break existing logic
    // The existing updateFinancials() does not conflict with this new logic.
</script>



<?php include('../include/footer.php'); ?>
