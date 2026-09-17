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
    <div class="max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8">
        
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
                        <div class="flex justify-between items-end mb-2">
                            <label class="block text-sm font-bold text-navy uppercase tracking-wide">Description *</label>
                            <span id="descCounter" class="text-xs text-gray-400 font-bold">0 / 2000</span>
                        </div>
                        <textarea name="description" id="productDesc" rows="4" maxlength="2000" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all"></textarea>
                    </div>
                </div>
            </div>
            </div>

            <!-- CARD 2: PRICING, MEDIA & VARIANTS -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8 min-h-[1500px] flex flex-col">
                <div class="flex items-center mb-6 pb-2 border-b border-gray-100">
                    <h2 class="text-lg font-black text-navy uppercase tracking-wide"><i class="fas fa-layer-group text-purple-500 me-2"></i> Pricing, Media & Variants</h2>
                </div>

                <!-- A) PRODUCT IMAGES -->
                <div class="mb-10">
                    <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">A) Product Images * <span class="text-xs text-gray-400 font-normal normal-case ml-2">(Min 4, Max 10 images, 1MB each. First image is primary)</span></label>
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

                <!-- B) VARIANT TOGGLE -->
                <div class="bg-gray-50 px-5 py-4 rounded-xl border border-gray-200 shadow-sm mb-10 flex justify-between items-center">
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-navy uppercase">This product has variants</span>
                        <span class="text-[10px] text-gray-500 font-medium normal-case">If your product has different sizes or colors, keep this ON</span>
                    </div>
                    <label class="flex items-center cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" id="hasVariantsToggle" class="sr-only" checked>
                            <div class="block bg-gray-200 w-12 h-7 rounded-full transition-colors toggle-bg"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full transition-transform transform translate-x-5"></div>
                        </div>
                    </label>
                </div>

                <!-- SIMPLE MODE (Toggle OFF) -->
                <div id="simpleModeWrapper" class="hidden">
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8">
                        <h3 class="text-sm font-bold text-navy mb-4 uppercase tracking-wide">Single Product Pricing & Inventory</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Buy Price (Rs)</label>
                                <input type="number" name="cost_price" id="cost_price" class="w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm focus:border-[#0066FF] outline-none font-bold text-navy" step="0.01">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Sell Price (Rs)</label>
                                <input type="number" name="selling_price" id="selling_price" class="w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm focus:border-[#0066FF] outline-none font-bold text-navy" step="0.01">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Quantity</label>
                                <input type="number" name="qty" class="w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm focus:border-[#0066FF] outline-none font-bold text-navy">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">SKU</label>
                                <input type="text" name="sku" class="w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm focus:border-[#0066FF] outline-none uppercase font-bold text-navy">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VARIANTS MODE (Toggle ON) -->
                <div id="variantsSectionWrapper" class="flex-1 flex flex-col">
                    
                    <!-- C) VARIANT OPTIONS -->
                    <div id="dynamicVariantUI" class="mb-10">
                        <!-- Rendered strictly via JS without placeholders -->
                    </div>

                    <!-- D) DEFAULT PRICING -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8 relative">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-navy uppercase tracking-wide">Default Pricing</h3>
                            <label class="flex items-center cursor-pointer gap-2">
                                <input type="checkbox" id="samePriceToggle" class="w-4 h-4 text-[#0066FF] bg-gray-100 border-gray-300 rounded focus:ring-[#0066FF]" checked>
                                <span class="text-xs font-bold text-navy uppercase">Same price for all variants?</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Buy Price</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                                    <input type="number" id="defaultBuyPrice" class="w-full bg-white border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-sm font-bold text-navy focus:border-[#0066FF] outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Sell Price</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                                    <input type="number" id="defaultSellPrice" class="w-full bg-white border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-sm font-bold text-navy focus:border-[#0066FF] outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Quantity</label>
                                <input type="number" id="defaultQty" class="w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm font-bold text-navy focus:border-[#0066FF] outline-none">
                            </div>
                            <div>
                                <button type="button" id="updateAllBtn" class="bg-black hover:bg-gray-800 text-white px-6 py-2 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors shadow-sm w-full h-[38px] flex items-center justify-center gap-2">
                                    <i class="fas fa-sync-alt"></i> Apply To Selected
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- E) GENERATE BUTTON -->
                    <div class="mb-8">
                        <button type="button" id="generateVariantsBtn" class="w-full border-2 border-[#0066FF] hover:bg-[#0066FF] hover:text-white text-[#0066FF] py-4 rounded-xl text-lg font-black uppercase tracking-widest transition-all shadow-sm hidden">
                            <i class="fas fa-magic me-2"></i> GENERATE VARIANTS TABLE - <span id="generateCountBadge" class="mx-1">0</span> variants
                        </button>
                    </div>

                    <!-- F) VARIANTS TABLE -->
                    <div class="overflow-x-auto border border-gray-200 rounded-xl custom-scrollbar relative flex-1">
                        <table class="w-full text-left border-collapse min-w-[1000px]" id="variantTable">
                            <thead class="sticky top-0 z-20 bg-gray-50 shadow-[0_1px_0_rgba(229,231,235,1)]">
                                <tr id="variantTableHeader">
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[180px] sticky left-0 z-30 bg-gray-50 shadow-[1px_0_0_rgba(229,231,235,1)]">Variant</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[110px]">Buy Price</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[110px]">Sell Price</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[90px]">Profit</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[90px]">Qty</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[120px]">SKU</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[50px] text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="variantTableBody" class="divide-y divide-gray-100 bg-white">
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">
                                        Select options and click Generate Table.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="sticky bottom-0 z-20 bg-gray-50 shadow-[0_-1px_0_rgba(229,231,235,1)]" id="tableFooter">
                                <tr>
                                    <td colspan="7" class="p-3">
                                        <div class="flex justify-between items-center">
                                            <button type="button" id="addManualBtn" class="text-sm font-bold text-[#0066FF] hover:underline hidden">
                                                <i class="fas fa-plus me-1"></i> Add Manual Row
                                            </button>
                                            <div class="flex gap-6 text-[11px] font-black uppercase tracking-wider text-navy ml-auto">
                                                <div>VARIANTS: <span id="totalVariantsCounter" class="text-[#0066FF]">0</span></div>
                                                <div>QTY: <span id="totalQtyCounter" class="text-[#0066FF]">0</span></div>
                                                <div>BUY: <span id="totalBuyCounter" class="text-gray-500">RS. 0</span></div>
                                                <div>SELL: <span id="totalSellCounter" class="text-green-600">RS. 0</span></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Mobile View -->
                    <div id="mobileVariantCards" class="md:hidden space-y-4 mt-4">
                        <div class="p-6 text-center text-gray-400 text-sm font-medium border border-gray-200 rounded-xl bg-gray-50" id="cardsEmptyState">
                            Please use a Desktop device to easily edit variant tables.
                        </div>
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
    const MAX_SIZE = 1 * 1024 * 1024; // 1MB

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
                Swal.fire({ icon: 'error', title: 'File Too Large', text: `File ${file.name} is larger than 1MB.` });
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
                    div.innerHTML += `<div class="absolute top-0 left-0 right-0 bg-[#0066FF] text-white text-[9px] font-bold text-center uppercase py-0.5 tracking-wider flex items-center justify-center gap-1"><i class="fas fa-star text-[8px]"></i> Primary</div>`;
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
    
    // Description Counter
    const productDesc = document.getElementById('productDesc');
    const descCounter = document.getElementById('descCounter');
    if (productDesc && descCounter) {
        productDesc.addEventListener('input', () => {
            descCounter.textContent = `${productDesc.value.length} / 2000`;
            if (productDesc.value.length >= 2000) {
                descCounter.classList.add('text-red-500');
            } else {
                descCounter.classList.remove('text-red-500');
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

    let activeSet1 = new Set(); // Stores text values (e.g. Size)
    let activeSet2 = new Set(); // Stores text or objects {name, hex} (e.g. Color)
    let activeSet3 = new Set(); // Stores Fit Type

    const categorySelect = document.querySelector('select[name="category_id"]');
    const dynamicUI = document.getElementById('dynamicVariantUI');
    const tableBody = document.getElementById('variantTableBody');
    const genBtn = document.getElementById('generateVariantsBtn');
    const genCountBadge = document.getElementById('generateCountBadge');
    const updateAllBtn = document.getElementById('updateAllBtn');
    const addManualBtn = document.getElementById('addManualRowBtn');
    const productNameInput = document.querySelector('input[name="name"]');
    const hasVariantsToggle = document.getElementById('hasVariantsToggle');
    const simpleModeWrapper = document.getElementById('simpleModeWrapper');
    const variantsSectionWrapper = document.getElementById('variantsSectionWrapper');

    let currentConfig = null; 

    function updateToggleVisual(isOn) {
        const bg = hasVariantsToggle.parentElement.querySelector('.toggle-bg');
        const dot = hasVariantsToggle.parentElement.querySelector('.dot');
        
        if (isOn) {
            bg.classList.remove('bg-gray-200');
            bg.classList.add('bg-[#0066FF]');
            dot.classList.add('translate-x-4');
            variantsSectionWrapper.style.display = 'flex';
            simpleModeWrapper.style.display = 'none';
        } else {
            bg.classList.remove('bg-[#0066FF]');
            bg.classList.add('bg-gray-200');
            dot.classList.remove('translate-x-4');
            variantsSectionWrapper.style.display = 'none';
            simpleModeWrapper.style.display = 'block';
            
            // Clear table
            tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">Select variants above and generate the table.</td></tr>`;
            updateTotalQty();
        }
    }

    // Handle Toggle
    hasVariantsToggle.addEventListener('change', function() {
        updateToggleVisual(this.checked);
    });
    // Init toggle state
    updateToggleVisual(hasVariantsToggle.checked);

    categorySelect.addEventListener('change', (e) => {
        const catId = e.target.value;
        if (!catId) {
            dynamicUI.innerHTML = '';
            tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">Select a Category first.</td></tr>`;
            genBtn.classList.add('hidden');
            updateAllBtn.classList.add('hidden');
            addManualBtn.classList.add('hidden');
            updateGenerateCount();
            return;
        }

        // Auto Toggle Logic
        const catName = e.target.options[e.target.selectedIndex].text.toUpperCase();
        if (catName.includes('FOOTWEAR') || catName.includes('SPORTS WEAR') || catName.includes('NUTRITION')) {
            hasVariantsToggle.checked = true;
            updateToggleVisual(true);
        } else {
            hasVariantsToggle.checked = false;
            updateToggleVisual(false);
        }

        renderCategoryUI(catId);
    });

    function getCatConfig(catId) {
        const catName = categorySelect.options[categorySelect.selectedIndex].text.toUpperCase();
        if (catName.includes('SPORTS WEAR')) return { type: 'sports', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: true, hasWidth: false };
        if (catName.includes('FOOTWEAR')) return { type: 'footwear', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false, hasWidth: true };
        if (catName.includes('FITNESS')) return { type: 'fitness', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false, hasWidth: false };
        if (catName.includes('ACCESSORIES')) return { type: 'accessories', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false, hasWidth: false };
        if (catName.includes('EQUIPMENT')) return { type: 'equipment', col1: 'Weight', col2: null, dbCol1: 'variant_size[]', dbCol2: null, hasFit: false, hasWidth: false };
        if (catName.includes('NUTRITION')) return { type: 'nutrition', col1: 'Scale', col2: 'Flavor', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false, hasWidth: false };
        return { type: 'default', col1: 'Variant 1', col2: 'Variant 2', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false, hasWidth: false };
    }

    function renderCategoryUI(catId) {
        activeSet1.clear();
        activeSet2.clear();
        activeSet3.clear();
        tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">Click "Generate" to create inventory rows.</td></tr>`;
        genBtn.classList.remove('hidden');
        updateAllBtn.classList.remove('hidden');
        addManualBtn.classList.remove('hidden');
        updateGenerateCount();

        currentConfig = getCatConfig(catId);
        const data = categoryVariants[catId] || {};

        let html = '<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">';
        
        // Render Column 1
        if (currentConfig.col1) {
            html += `<div><label class="block text-sm font-bold text-navy uppercase tracking-wide mb-3">${currentConfig.col1}</label>`;
            
            if (currentConfig.type === 'footwear') {
                html += `<div class="flex gap-2 mb-4">
                    <button type="button" class="text-xs font-bold bg-[#0066FF] text-white rounded-lg py-1.5 px-4">US</button>
                    <button type="button" class="text-xs font-bold bg-gray-100 text-gray-500 rounded-lg py-1.5 px-4 hover:bg-gray-200 transition-colors">UK</button>
                    <button type="button" class="text-xs font-bold bg-gray-100 text-gray-500 rounded-lg py-1.5 px-4 hover:bg-gray-200 transition-colors">EU</button>
                </div>`;
            }

            let chips = [];
            if (currentConfig.type === 'sports' || currentConfig.type === 'fitness') {
                chips = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];
            } else if (currentConfig.type === 'footwear') {
                chips = ['5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '12'];
            } else if (currentConfig.type === 'nutrition' && currentConfig.col1 === 'Scale') {
                chips = ['500g / 1.1 LBS', '1KG / 2.2 LBS', '2KG / 4.4 LBS', '2.5KG / 5.5 LBS', '5KG / 11 LBS'];
            } else if (currentConfig.type === 'accessories') {
                chips = ['One Size', 'S/M', 'L/XL'];
            } else if (currentConfig.type === 'equipment') {
                chips = ['5KG', '10KG', '20KG', 'One Size'];
            }
            
            html += `<div class="flex flex-wrap gap-2 mb-3">`;
            chips.forEach(val => {
                html += `
                    <div class="border border-gray-200 rounded-lg px-4 py-2 cursor-pointer hover:border-[#0066FF] transition-colors bg-white var-chip set1-chip text-center" data-val="${val}">
                        <span class="block text-sm font-bold text-navy">${val}</span>
                    </div>
                `;
            });
            html += `</div>`;
            
            if (currentConfig.type === 'footwear') {
                html += `
                <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3 flex justify-between items-center">
                    <span class="text-sm font-bold text-navy">Different price per size?</span>
                    <label class="flex items-center cursor-pointer gap-2">
                        <input type="checkbox" id="diffPricePerSizeToggle" class="w-4 h-4 text-[#0066FF] bg-white border-gray-300 rounded focus:ring-[#0066FF]">
                    </label>
                </div>
                `;
            }
            html += `</div>`;
        }

        // Render Column 2
        if (currentConfig.col2) {
            html += `<div><label class="block text-sm font-bold text-navy uppercase tracking-wide mb-3">${currentConfig.col2}</label>`;
            
            if (currentConfig.col2 === 'Color') {
                html += `<div class="flex flex-wrap gap-2.5 mb-4">`;
                standardColors.forEach(c => {
                    html += `<div class="w-8 h-8 rounded-full cursor-pointer transition-all flex items-center justify-center color-chip shadow-sm" data-val="${c.name}" data-hex="${c.hex}" style="background-color: ${c.hex}; ${c.hex==='#FFFFFF'?'border:1px solid #e5e7eb;':''}"></div>`;
                });
                html += `</div>`;
            } else {
                let chips = [];
                if (currentConfig.type === 'nutrition' && currentConfig.col2 === 'Flavor') {
                    chips = ['Chocolate', 'Vanilla', 'Strawberry', 'Cookies & Cream'];
                }
                
                html += `<div class="flex flex-wrap gap-2 mb-3">`;
                chips.forEach(val => {
                    html += `<div class="border border-gray-200 rounded-lg px-4 py-2 cursor-pointer hover:border-[#0066FF] transition-colors bg-white var-chip set2-chip text-sm font-bold text-navy" data-val="${val}">${val}</div>`;
                });
                html += `</div>`;
            }
            
            if (currentConfig.hasFit || currentConfig.hasWidth) {
                const label = currentConfig.hasFit ? 'Fit Type' : 'Width';
                const key = currentConfig.hasFit ? 'Fit Type' : 'Width';
                html += `<label class="block text-sm font-bold text-navy uppercase tracking-wide mb-2 mt-4 flex justify-between items-center">
                            Advanced: ${label}
                            <label class="flex items-center cursor-pointer gap-2 normal-case">
                                <span class="text-xs text-gray-400">Enable</span>
                                <div class="relative">
                                    <input type="checkbox" id="enableFitToggle" class="sr-only">
                                    <div class="block bg-gray-200 w-8 h-5 rounded-full transition-colors"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-3 h-3 rounded-full transition-transform transform"></div>
                                </div>
                            </label>
                        </label>
                <div id="fitTypeContainer" class="flex flex-wrap gap-2 hidden">`;
                const extraData = data[key] || [];
                if (extraData.length === 0) {
                    if (currentConfig.hasFit) {
                        extraData.push({value: 'Regular'}, {value: 'Slim'}, {value: 'Oversized'});
                    } else if (currentConfig.hasWidth) {
                        extraData.push({value: 'Regular'}, {value: 'Wide'});
                    }
                }
                extraData.forEach(f => {
                    html += `<div class="border border-gray-200 rounded-lg px-3 py-1 cursor-pointer hover:border-[#0066FF] transition-colors bg-white var-chip set3-chip text-xs font-bold text-navy" data-val="${f.value}">${f.value}</div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        html += '</div>';
        dynamicUI.innerHTML = html;
        bindChipEvents();

        // Specific logic for 'Different price per size'
        const diffPriceToggle = document.getElementById('diffPricePerSizeToggle');
        if (diffPriceToggle) {
            diffPriceToggle.addEventListener('change', function() {
                const samePriceToggle = document.getElementById('samePriceToggle');
                if (samePriceToggle) {
                    samePriceToggle.checked = !this.checked;
                    samePriceToggle.dispatchEvent(new Event('change'));
                }
            });
        }
    }

    function updateGenerateCount() {
        const c1 = Math.max(1, activeSet1.size);
        const c2 = Math.max(1, activeSet2.size);
        const c3 = document.getElementById('enableFitToggle')?.checked ? Math.max(1, activeSet3.size) : 1;
        
        let count = 0;
        if (activeSet1.size > 0 || activeSet2.size > 0 || (document.getElementById('enableFitToggle')?.checked && activeSet3.size > 0)) {
            count = (activeSet1.size || 1) * (activeSet2.size || 1) * (document.getElementById('enableFitToggle')?.checked ? (activeSet3.size || 1) : 1);
        }
        genCountBadge.textContent = count;
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
                updateGenerateCount();
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
                updateGenerateCount();
            });
        });

        const fitToggle = document.getElementById('enableFitToggle');
        if (fitToggle) {
            fitToggle.addEventListener('change', function() {
                const dot = this.nextElementSibling.nextElementSibling;
                const bg = this.nextElementSibling;
                const container = document.getElementById('fitTypeContainer');
                if (this.checked) {
                    bg.classList.replace('bg-gray-200', 'bg-[#0066FF]');
                    dot.classList.add('translate-x-3');
                    container.classList.remove('hidden');
                } else {
                    bg.classList.replace('bg-[#0066FF]', 'bg-gray-200');
                    dot.classList.remove('translate-x-3');
                    container.classList.add('hidden');
                    // clear selections
                    activeSet3.clear();
                    container.querySelectorAll('.set3-chip').forEach(c => {
                        c.classList.remove('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]');
                        c.classList.add('bg-white', 'text-navy');
                    });
                }
                updateGenerateCount();
            });
        }
    }

    function generateSKU(v1, v2, v3) {
        let base = (productNameInput && productNameInput.value) ? productNameInput.value.substring(0, 4).toUpperCase() : 'PRD';
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

    function createVariantRow(v1, v2, v3, defBuy, defSell, defQty, isMobile = false) {
        const sku = generateSKU(v1, v2, v3);
        
        let label = [];
        if (v1) label.push(v1);
        if (v2) {
            if (typeof v2 === 'object') label.push(v2.name);
            else label.push(v2);
        }
        if (v3 && v3 !== 'Regular') label.push(`(${v3})`); // Include fit type if selected
        const variantLabel = label.join(' / ') || '-';
        
        let colorHtml = '';
        if (currentConfig.col2 === 'Color' && typeof v2 === 'object') {
            colorHtml = `<span class="w-3.5 h-3.5 rounded-full border border-gray-200 block shrink-0 shadow-sm mr-2" style="background-color: ${v2.hex};"></span>`;
        }

        // Default to provided values or empty
        const b = defBuy || '';
        const s = defSell || '';
        const q = defQty || '0';

        const samePrice = document.getElementById('samePriceToggle')?.checked;
        const readOnlyAttr = samePrice ? 'readonly' : '';
        const bgClass = samePrice ? 'bg-gray-50' : 'bg-white';

        // --- Desktop Row ---
        if (!isMobile) {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-[#f0f7ff] transition-colors h-[56px] even:bg-gray-50";
            let html = '';
            
            // Merged Variant Name
            html += `<td class="p-4 border-b border-gray-100 sticky left-0 z-10 bg-white group-hover:bg-[#f0f7ff] transition-colors shadow-[1px_0_0_rgba(229,231,235,1)]">
                <div class="flex items-center text-xs font-bold text-navy whitespace-nowrap">
                    ${colorHtml}
                    ${variantLabel}
                </div>
                <!-- Hidden inputs for backend -->
                <input type="hidden" name="${currentConfig.dbCol1 || 'variant_size[]'}" value="${v1||''}">
                <input type="hidden" name="${currentConfig.dbCol2 || 'variant_color[]'}" value="${typeof v2 === 'object' ? v2.name : (v2||'')}">
                <input type="hidden" name="variant_fit[]" value="${v3||'Regular'}">
            </td>`;

            // Buy Price
            html += `<td class="p-4 border-b border-gray-100">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                    <input type="number" step="0.01" min="0" name="variant_cost_price[]" value="${b}" placeholder="0.00" required class="w-full ${bgClass} border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-buy" oninput="calculateVarProfit(this)" ${readOnlyAttr}>
                </div>
            </td>`;

            // Sell Price
            html += `<td class="p-4 border-b border-gray-100">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                    <input type="number" step="0.01" min="0" name="variant_price[]" value="${s}" placeholder="0.00" required class="w-full ${bgClass} border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-sell" oninput="calculateVarProfit(this)" ${readOnlyAttr}>
                </div>
            </td>`;

            // Profit
            html += `<td class="p-4 border-b border-gray-100">
                <span class="text-xs font-black text-gray-400 var-profit">-</span>
            </td>`;

            // Qty Stepper
            html += `<td class="p-4 border-b border-gray-100">
                <div class="flex items-center border border-gray-200 rounded-lg bg-white overflow-hidden w-[90px]">
                    <button type="button" class="px-2 py-1 bg-gray-50 text-gray-500 hover:bg-gray-100 font-bold border-r border-gray-200" onclick="const i=this.nextElementSibling; i.value=Math.max(0,(parseInt(i.value)||0)-1); updateTotalQty();">-</button>
                    <input type="number" name="variant_qty[]" value="${q}" min="0" required class="w-full text-center py-1 text-xs font-bold focus:outline-none variant-qty-input" oninput="updateTotalQty()">
                    <button type="button" class="px-2 py-1 bg-gray-50 text-gray-500 hover:bg-gray-100 font-bold border-l border-gray-200" onclick="const i=this.previousElementSibling; i.value=(parseInt(i.value)||0)+1; updateTotalQty();">+</button>
                </div>
            </td>`;

            // SKU
            html += `<td class="p-4 border-b border-gray-100">
                <div class="relative">
                    <input type="text" name="variant_sku[]" value="${sku}" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none uppercase pr-6">
                    <i class="fas fa-pencil-alt absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-300"></i>
                </div>
            </td>`;

            // Delete
            html += `<td class="p-4 border-b border-gray-100 text-center">
                <button type="button" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors shadow-sm" onclick="if(confirm('Remove this variant?')) { this.closest('tr').remove(); syncMobileCards(); updateTotalQty(); }"><i class="fas fa-trash-alt"></i></button>
            </td>`;
            
            tr.innerHTML = html;
            
            // Auto-calc profit on mount if values exist
            setTimeout(() => {
                if (b && s) calculateVarProfit(tr.querySelector('.var-buy'));
            }, 10);
            
            return tr;
        } 
        
        // --- Mobile Card ---
        else {
            const div = document.createElement('div');
            div.className = "bg-white border border-gray-200 rounded-xl p-4 shadow-sm relative";
            let html = `<button type="button" class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors" onclick="if(confirm('Remove this variant?')) { this.closest('.bg-white').remove(); }"><i class="fas fa-trash-alt"></i></button>`;
            
            html += `<div class="flex flex-wrap items-center gap-3 mb-4 pr-10">`;
            if (colorHtml) html += colorHtml;
            html += `<span class="text-xs font-bold text-navy">${variantLabel}</span>`;
            html += `</div>`;

            html += `<div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Buy Price (Rs)</label>
                    <input type="number" step="0.01" value="${b}" placeholder="0.00" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold" readonly>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Sell Price (Rs)</label>
                    <input type="number" step="0.01" value="${s}" placeholder="0.00" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold" readonly>
                </div>
            </div>`;

            html += `<div class="flex justify-between items-center bg-gray-50 p-2 rounded-lg mb-3">
                <span class="text-[10px] font-bold text-gray-500 uppercase">Profit</span>
                <span class="text-xs font-black text-gray-400">-</span>
            </div>`;

            html += `<div class="grid grid-cols-2 gap-3 items-end">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Qty</label>
                    <input type="number" value="${q}" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold" readonly>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">SKU</label>
                    <input type="text" value="${sku}" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold uppercase" readonly>
                </div>
            </div>`;

            div.innerHTML = html;
            return div;
        }
    }

    function syncMobileCards() {
        const cardsContainer = document.getElementById('mobileVariantCards');
        if(!cardsContainer) return;
        cardsContainer.innerHTML = '<div class="p-6 text-center text-gray-500 text-xs font-bold bg-yellow-50 border border-yellow-200 rounded-xl"><i class="fas fa-desktop mb-2 text-xl block"></i> Please use a Desktop device to easily edit variant prices and quantities.</div>';
    }

    genBtn.addEventListener('click', () => {
        let arr1 = Array.from(activeSet1);
        let arr2 = Array.from(activeSet2);
        let arr3 = [];
        
        if (document.getElementById('enableFitToggle')?.checked) {
            arr3 = Array.from(activeSet3);
        }

        if (arr1.length === 0) arr1 = [null];
        if (arr2.length === 0) arr2 = [null];
        if (arr3.length === 0) arr3 = [null];

        if (activeSet1.size === 0 && activeSet2.size === 0 && arr3.length === 1 && arr3[0] === null) {
            Swal.fire({icon: 'warning', title: 'No Variants Selected', text: 'Please select at least one variant option to generate the table.'});
            return;
        }

        const bBuy = document.getElementById('defaultBuyPrice').value;
        const bSell = document.getElementById('defaultSellPrice').value;
        const bQty = document.getElementById('defaultQty').value;

        // Check duplicates logic
        const existingRows = Array.from(tableBody.querySelectorAll('tr'));
        const existingCombinations = existingRows.map(tr => {
            const inputs = tr.querySelectorAll('input[type="hidden"]');
            if(inputs.length >= 2) return inputs[0].value + '|' + inputs[1].value + '|' + (inputs[2]?inputs[2].value:'Regular');
            return null;
        }).filter(Boolean);

        const emptyState = document.getElementById('tableEmptyState');
        if(emptyState) emptyState.parentElement.remove();

        let addedCount = 0;

        arr1.forEach(v1 => {
            arr2.forEach(v2 => {
                arr3.forEach(v3 => {
                    const v1Val = v1 || '';
                    const v2Val = v2 ? (typeof v2 === 'object' ? v2.name : v2) : '';
                    const v3Val = v3 || 'Regular';
                    const combo = v1Val + '|' + v2Val + '|' + v3Val;
                    
                    if (!existingCombinations.includes(combo) || combo === '||Regular') {
                        tableBody.appendChild(createVariantRow(v1, v2, v3, bBuy, bSell, bQty, false));
                        addedCount++;
                    }
                });
            });
        });
        
        if (addedCount > 0) {
            syncMobileCards();
            updateTotalQty();
        } else {
            Swal.fire({icon: 'info', title: 'Already Exists', text: 'Selected variants already exist in the table.'});
        }
    });


    // Card 3 Update All Logic
    updateAllBtn.addEventListener('click', () => {
        const rows = document.querySelectorAll('#variantTableBody tr:not(:has(#tableEmptyState))');
        if(rows.length === 0) return;
        
        Swal.fire({
            title: 'Apply to Selected?',
            text: "This will overwrite all prices and quantities in the table with your default values.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0066FF',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, apply'
        }).then((result) => {
            if (result.isConfirmed) {
                const bBuy = document.getElementById('defaultBuyPrice').value;
                const bSell = document.getElementById('defaultSellPrice').value;
                const bQty = document.getElementById('defaultQty').value;
                
                rows.forEach(tr => {
                    if(bBuy !== '') tr.querySelector('.var-buy').value = bBuy;
                    if(bSell !== '') tr.querySelector('.var-sell').value = bSell;
                    if(bQty !== '') tr.querySelector('.variant-qty-input').value = bQty;
                    
                    calculateVarProfit(tr.querySelector('.var-buy'));
                    
                    // Flash effect
                    tr.classList.add('bg-yellow-100');
                    setTimeout(() => {
                        tr.classList.remove('bg-yellow-100');
                    }, 1000);
                });
                updateTotalQty();
            }
        });
    });

    const samePriceToggle = document.getElementById('samePriceToggle');
    if (samePriceToggle) {
        samePriceToggle.addEventListener('change', function() {
            const isSame = this.checked;
            const rows = document.querySelectorAll('#variantTableBody tr:not(:has(#tableEmptyState))');
            rows.forEach(tr => {
                const buyInput = tr.querySelector('.var-buy');
                const sellInput = tr.querySelector('.var-sell');
                if (buyInput && sellInput) {
                    if (isSame) {
                        buyInput.setAttribute('readonly', 'true');
                        sellInput.setAttribute('readonly', 'true');
                        buyInput.classList.replace('bg-white', 'bg-gray-50');
                        sellInput.classList.replace('bg-white', 'bg-gray-50');
                    } else {
                        buyInput.removeAttribute('readonly');
                        sellInput.removeAttribute('readonly');
                        buyInput.classList.replace('bg-gray-50', 'bg-white');
                        sellInput.classList.replace('bg-gray-50', 'bg-white');
                    }
                }
            });
        });
    }

    addManualBtn.addEventListener('click', () => {
        if (tableBody.querySelector('td[colspan="7"]')) tableBody.innerHTML = '';
        tableBody.appendChild(createVariantRow('', '', '', '', '', '', false));
        syncMobileCards();
    });

    function updateTotalQty() {
        const rows = document.querySelectorAll('#variantTableBody tr');
        let totalQty = 0;
        let totalBuy = 0;
        let totalSell = 0;
        let variantCount = 0;

        rows.forEach(tr => {
            if (tr.querySelector('td[colspan="7"]')) return; // empty state
            variantCount++;
            const qty = parseInt(tr.querySelector('.variant-qty-input').value) || 0;
            const buy = parseFloat(tr.querySelector('.var-buy').value) || 0;
            const sell = parseFloat(tr.querySelector('.var-sell').value) || 0;
            
            totalQty += qty;
            totalBuy += (buy * qty);
            totalSell += (sell * qty);
        });

        document.getElementById('totalVariantsCounter').textContent = variantCount;
        document.getElementById('totalQtyCounter').textContent = totalQty;
        document.getElementById('totalBuyCounter').textContent = 'Rs. ' + totalBuy.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('totalSellCounter').textContent = 'Rs. ' + totalSell.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function calculateVarProfit(el) {
        const tr = el.closest('tr');
        const buy = parseFloat(tr.querySelector('.var-buy').value) || 0;
        const sell = parseFloat(tr.querySelector('.var-sell').value) || 0;
        const profitEl = tr.querySelector('.var-profit');
        
        let profit = sell - buy;
        const sellInput = tr.querySelector('.var-sell');
        if (buy > 0 && sell > 0 && sell < buy) {
            profitEl.textContent = '-Rs. ' + Math.abs(profit).toFixed(2);
            profitEl.classList.remove('text-[#0066FF]', 'text-gray-400', 'text-green-500');
            profitEl.classList.add('text-red-500');
            sellInput.classList.add('border-red-500', 'focus:border-red-500');
        } else if (buy > 0 || sell > 0) {
            profitEl.textContent = '+Rs. ' + profit.toFixed(2);
            profitEl.classList.remove('text-red-500', 'text-gray-400', 'text-[#0066FF]');
            profitEl.classList.add('text-green-500');
            sellInput.classList.remove('border-red-500', 'focus:border-red-500');
        } else {
            profitEl.textContent = '-';
            profitEl.classList.remove('text-red-500', 'text-green-500', 'text-[#0066FF]');
            profitEl.classList.add('text-gray-400');
            sellInput.classList.remove('border-red-500', 'focus:border-red-500');
        }
        updateTotalQty();
    }

    
    // Make sure we attach event to financial calculator submit to not break existing logic
    // The existing updateFinancials() does not conflict with this new logic.

    const addProductForm = document.getElementById('addProductForm') || document.querySelector('form');
    if (addProductForm) {
        addProductForm.addEventListener('submit', function(e) {
            // Validate based on mode
            const isVariants = hasVariantsToggle.checked;
            let isValid = true;
            let errorMsg = '';

            if (!isVariants) {
                // Simple Mode Validation
                const buy = parseFloat(document.getElementById('cost_price').value) || 0;
                const sell = parseFloat(document.getElementById('selling_price').value) || 0;
                const qty = parseInt(document.querySelector('input[name="qty"]').value) || 0;

                if (buy > 0 && sell > 0 && buy >= sell) {
                    isValid = false;
                    errorMsg = 'Sell Price must be strictly greater than Buy Price.';
                } else if (qty <= 0) {
                    isValid = false;
                    errorMsg = 'Quantity must be greater than 0.';
                }
            } else {
                // Variants Mode Validation
                const rows = document.querySelectorAll('#variantTableBody tr:not(:has(#tableEmptyState))');
                if (rows.length === 0) {
                    isValid = false;
                    errorMsg = 'Please generate at least one variant row.';
                } else {
                    rows.forEach(tr => {
                        const buy = parseFloat(tr.querySelector('.var-buy').value) || 0;
                        const sell = parseFloat(tr.querySelector('.var-sell').value) || 0;
                        const qty = parseInt(tr.querySelector('.variant-qty-input').value) || 0;

                        if (buy > 0 && sell > 0 && buy >= sell) {
                            isValid = false;
                            errorMsg = 'One or more variants have Sell Price <= Buy Price. Please correct them (highlighted in red).';
                        }
                        // We might allow QTY 0 if out of stock, but the user said "Qty >0 required"
                        if (qty <= 0) {
                            isValid = false;
                            errorMsg = 'All variants must have a Quantity greater than 0.';
                        }
                    });
                }
            }

            if (!isValid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: errorMsg,
                    confirmButtonColor: '#0066FF'
                });
            }
        });
    }
</script>



<?php include('../include/footer.php'); ?>
