<?php
session_start();
$page_title = 'Edit Product - OXXA GEAR Seller';
include('../include/connection.php');

// Auth check BEFORE any HTML output
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

// Business profile check BEFORE any HTML output
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

// Fetch existing product data
$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header('Location: seller-dashboard.php');
    exit();
}

$prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$prodStmt->execute([$product_id, $_SESSION['userid']]);
$product = $prodStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: seller-dashboard.php?error=Product+Not+Found');
    exit();
}

// Fetch existing images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgStmt->execute([$product_id]);
$existingImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing colors and sizes
$colorStmt = $pdo->prepare("SELECT pc.id, pc.color_name, pc.thumbnail_path, cs.size, cs.qty as stock, cs.cost_price as cost, cs.selling_price as price, cs.sku 
                            FROM product_colors pc 
                            LEFT JOIN color_sizes cs ON pc.id = cs.color_id 
                            WHERE pc.product_id = ? ORDER BY pc.id ASC, cs.id ASC");
$colorStmt->execute([$product_id]);
$rawColors = $colorStmt->fetchAll(PDO::FETCH_ASSOC);

$existingColors = [];
foreach ($rawColors as $row) {
    $cName = $row['color_name'];
    if (!isset($existingColors[$cName])) {
        $existingColors[$cName] = [
            'name' => $cName,
            'thumbnail' => $row['thumbnail_path'],
            'sizes' => []
        ];
    }
    if (!empty($row['size'])) {
        $existingColors[$cName]['sizes'][] = [
            'size' => $row['size'],
            'stock' => $row['stock'],
            'cost' => $row['cost'],
            'price' => $row['price'],
            'sku' => $row['sku']
        ];
    }
}
$existingColors = array_values($existingColors);

session_write_close(); // Free session lock for parallel AJAX requests
include('../include/header.php');
?>
<script>
    const categoryVariants = <?= json_encode($masterVariants) ?>;
    const existingColors = <?= json_encode($existingColors) ?>;
</script>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex items-center gap-4 mb-8">
            <a href="seller-dashboard.php" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-navy hover:bg-gray-50 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-navy uppercase tracking-wide">Edit Product</h1>
                <p class="text-sm text-slate">Update item in your store</p>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center">
                <i class="fas fa-exclamation-circle me-3"></i>
                <span class="font-bold">Error: <?= htmlspecialchars($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <form id="addProductForm" action="../Backend/seller-product-backend.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <!-- CARD 1: PRODUCT DETAILS -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-box text-[#0066FF] me-2"></i> Product Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Product Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Brand</label>
                        <select name="brand_id" class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">No Brand</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $b['id'] == $product['brand_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Category *</label>
                        <select name="category_id" id="category_id" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <div class="flex justify-between items-end mb-2">
                            <label class="block text-sm font-bold text-navy uppercase tracking-wide">Description *</label>
                            <span id="descCounter" class="text-xs text-gray-400 font-bold"><?= strlen($product['description']) ?> / 2000</span>
                        </div>
                        <textarea name="description" id="productDesc" rows="4" maxlength="2000" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- CARD 2: BASE PRICING (BULK APPLY) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-tags text-green-500 me-2"></i> Base Pricing (Bulk Apply)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Base Cost Price (Rs) *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                            <input type="number" id="baseBuyPrice" name="cost_price" value="<?= $product['cost_price'] ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-9 pr-4 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none transition-all" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Base Selling Price (Rs) *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                            <input type="number" id="baseSellPrice" name="selling_price" value="<?= $product['base_price'] ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-9 pr-4 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none transition-all" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div></div>
                    <div>
                        <button type="button" id="applyBaseBtn" class="w-full bg-black hover:bg-gray-800 text-white px-6 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors shadow-sm flex items-center justify-center gap-2 h-[46px]">
                            <i class="fas fa-check-double"></i> Apply All
                        </button>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-3 font-medium"><i class="fas fa-info-circle me-1"></i> Entering a price here and clicking <strong>"Apply All"</strong> will automatically set the buy/sell price for all generated variants.</p>
            </div>

            <!-- CARD 2.5: SHIPPING OPTIONS -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-truck text-emerald-500 me-2"></i> Shipping Options</h2>
                
                <div class="mb-4">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="is_free_shipping" id="is_free_shipping" value="1" <?= $product['is_free_shipping'] ? 'checked' : '' ?> class="w-5 h-5 rounded text-[#0066FF] border-gray-300 focus:ring-[#0066FF] cursor-pointer">
                        <span class="text-sm font-bold text-navy uppercase tracking-wide group-hover:text-[#0066FF] transition-colors">🚚 Free Shipping - Offer free delivery for this product</span>
                    </label>
                    <p id="freeShippingHelp" class="text-xs text-gray-400 mt-2 ml-8 font-medium">Customer ta delivery free. <span class="text-emerald-500 font-bold <?= $product['is_free_shipping'] ? '' : 'hidden' ?>" id="freeShippingSuccessMsg">Free delivery will be shown on product page</span></p>
                </div>

                <div id="shippingCostContainer">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Shipping Cost (Rs) *</label>
                    <div class="relative max-w-xs">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                        <input type="number" name="shipping_cost" id="shippingCost" value="<?= $product['shipping_cost'] ?>" <?= $product['is_free_shipping'] ? 'disabled' : '' ?> class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-9 pr-4 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none transition-all disabled:opacity-50 disabled:bg-gray-100" step="0.01">
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const freeShippingCheckbox = document.getElementById('is_free_shipping');
                    const shippingCostInput = document.getElementById('shippingCost');
                    const freeShippingSuccessMsg = document.getElementById('freeShippingSuccessMsg');
                    
                    freeShippingCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            shippingCostInput.disabled = true;
                            shippingCostInput.value = '0';
                            freeShippingSuccessMsg.classList.remove('hidden');
                        } else {
                            shippingCostInput.disabled = false;
                            shippingCostInput.value = '300';
                            freeShippingSuccessMsg.classList.add('hidden');
                        }
                    });
                });
            </script>

            <!-- CARD 3: PRODUCT IMAGES -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" id="productImagesCard">
                <div class="flex justify-between items-center mb-6 pb-2 border-b border-gray-100">
                    <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-0">
                        <i class="fas fa-images text-pink-500 me-2"></i> Product Images
                    </h2>
                </div>
                
                <div class="mb-6" id="existingImagesWrapper">
                    <label class="block text-sm font-bold text-navy uppercase tracking-wide mb-2">Existing Images</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4" id="existingImagesGrid">
                        <?php foreach($existingImages as $img): ?>
                            <div class="relative group rounded-xl overflow-hidden border border-gray-200 aspect-square existing-img-box" data-img="<?= htmlspecialchars($img['image_path']) ?>">
                                <img src="../assets/uploads/products/<?= htmlspecialchars($img['image_path']) ?>" class="w-full h-full object-cover">
                                <button type="button" class="absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow delete-existing-img">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                                <?php if($img['is_primary']): ?>
                                    <div class="absolute bottom-0 left-0 right-0 bg-[#0066FF] text-white text-[10px] font-bold text-center py-1 uppercase tracking-wider">Primary</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                    document.addEventListener('click', function(e) {
                        const btn = e.target.closest('.delete-existing-img');
                        if (btn) {
                            const box = btn.closest('.existing-img-box');
                            const imgName = box.dataset.img;
                            const form = document.getElementById('addProductForm');
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'deleted_images[]';
                            hidden.value = imgName;
                            form.appendChild(hidden);
                            box.remove();
                            window.existingImagesCount = (window.existingImagesCount || 0) - 1;
                        }
                    });
                    window.existingImagesCount = <?= count($existingImages) ?>;
                </script>

                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-bold text-navy uppercase tracking-wide">Add New Images</label>
                        <span id="globalImageCountBadge" class="text-xs font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-500 border border-gray-200">
                            0 / 10 photos
                        </span>
                    </div>

                    <style>
                        #globalDropzone * { pointer-events: none !important; }
                    </style>

                    <div class="global-preview-container grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-4">
                    </div>
                    
                    <div class="global-dropzone-wrapper relative" id="globalDropzoneWrapper">
                        <label class="border-2 border-dashed border-gray-300 rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer hover:bg-blue-50/40 hover:border-[#0066FF] transition-all group global-file-label text-center" id="globalDropzone" for="globalFileInput">
                            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-blue-100 group-hover:scale-110 transition-all dropzone-icon-wrap">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl group-hover:text-[#0066FF] dropzone-icon"></i>
                            </div>
                            <span class="text-sm font-bold text-navy dropzone-text">Drag & drop photos here, or <span class="text-[#0066FF] underline font-black">browse</span></span>
                        </label>
                    </div>

                    <input type="file" name="product_images[]" multiple accept="image/*" class="global-file-input sr-only" id="globalFileInput">
                    
                    <div id="imageUploadNotice" class="text-xs text-amber-600 font-bold mt-2 hidden flex items-center gap-1.5 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg">
                        <i class="fas fa-info-circle"></i> <span id="imgReqMsg">Minimum 1 photo required for store listing.</span>
                    </div>
                </div>
            </div>

            <!-- CARD 4: COLOR VARIANTS & INVENTORY -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-6">
                <div class="flex justify-between items-center mb-6 pb-2 border-b border-gray-100">
                    <h2 class="text-lg font-black text-navy uppercase tracking-wide"><i class="fas fa-layer-group text-purple-500 me-2"></i> Variants & Inventory</h2>
                    <button type="button" id="addColorVariantBtn" class="bg-[#0066FF] hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-wider transition-colors shadow-sm flex items-center gap-2">
                        <i class="fas fa-plus"></i> Add Color Variant
                    </button>
                </div>

                <div id="colorVariantsContainer" class="space-y-6">
                    <div class="flex flex-col items-center justify-center py-12 text-gray-300 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl" id="emptyColorsState">
                        <i class="fas fa-palette text-4xl mb-4 text-gray-300"></i>
                        <span class="text-sm font-bold text-gray-400">Click <strong class="text-[#0066FF]">+ Add Color Variant</strong> to start adding product variations</span>
                        <p class="text-xs text-gray-400 mt-2 font-medium max-w-md text-center">You must select a Category in Product Details first to load the correct sizing systems.</p>
                    </div>
                </div>

                <div id="totalInventorySummary" class="mt-8 p-4 bg-gray-50 border border-gray-200 rounded-xl hidden">
                    <div class="flex justify-between items-center text-xs font-black uppercase tracking-wider text-navy">
                        <div>Total Colors: <span id="summaryTotalColors" class="text-[#0066FF]">0</span></div>
                        <div>Total Variants (Sizes): <span id="summaryTotalVariants" class="text-[#0066FF]">0</span></div>
                        <div>Total QTY: <span id="summaryTotalQty" class="text-[#0066FF]">0</span></div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" name="update_product_btn" class="bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center text-lg">
                    Save Changes <i class="fas fa-save ms-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const descInput = document.getElementById('productDesc');
        const descCounter = document.getElementById('descCounter');
        if (descInput && descCounter) {
            descInput.addEventListener('input', function() {
                descCounter.textContent = this.value.length + ' / 2000';
            });
        }
    });
</script>
<script src="../assets/js/seller-add-product-colors.js?v=<?= time() ?>"></script>
<script>
// Auto-populate variants
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        // Trigger category change to load sizing systems
        const catSelect = document.getElementById('category_id');
        if(catSelect) catSelect.dispatchEvent(new Event('change'));
        
        if (typeof existingColors !== 'undefined' && existingColors.length > 0) {
            existingColors.forEach(colorObj => {
                const addBtn = document.getElementById('addColorVariantBtn');
                if(addBtn) addBtn.click();
                
                const blocks = document.querySelectorAll('.color-block');
                const lastBlock = blocks[blocks.length - 1];
                if(lastBlock) {
                    const cId = lastBlock.dataset.id;
                    const nameInput = lastBlock.querySelector('.color-name-input');
                    if(nameInput) {
                        nameInput.value = colorObj.name;
                        nameInput.dispatchEvent(new Event('input')); 
                    }
                    
                    // Hidden input for existing thumbnail
                    if (colorObj.thumbnail) {
                        const hiddenThumb = document.createElement('input');
                        hiddenThumb.type = 'hidden';
                        hiddenThumb.name = `colors[${cId}][existing_thumbnail]`;
                        hiddenThumb.value = colorObj.thumbnail;
                        lastBlock.appendChild(hiddenThumb);
                        
                        const previewImg = lastBlock.querySelector('.thumbnail-preview');
                        if (previewImg) {
                            previewImg.src = '../assets/uploads/products/' + colorObj.thumbnail;
                            previewImg.classList.remove('hidden');
                            if(previewImg.closest('label').querySelector('i')) previewImg.closest('label').querySelector('i').classList.add('hidden');
                            if(previewImg.closest('label').querySelector('span')) previewImg.closest('label').querySelector('span').classList.add('hidden');
                        }
                    }
                    
                    const chips = lastBlock.querySelectorAll('.size-chip');
                    
                    colorObj.sizes.forEach(v => {
                        let clicked = false;
                        chips.forEach(chip => {
                            if (chip.textContent.trim() === v.size) {
                                chip.click();
                                clicked = true;
                            }
                        });
                        
                        if(!clicked) {
                            const sysSelect = lastBlock.querySelector('.sizing-system-select');
                            if(sysSelect) {
                                for(let opt of sysSelect.options) {
                                    if(v.size === 'Standard' || opt.value === 'One Size') {
                                        sysSelect.value = opt.value;
                                        sysSelect.dispatchEvent(new Event('change'));
                                        break;
                                    }
                                }
                                const newChips = lastBlock.querySelectorAll('.size-chip');
                                newChips.forEach(chip => {
                                    if (chip.textContent.trim() === v.size || (v.size==='Standard' && chip.textContent.trim()==='Standard')) {
                                        chip.click();
                                    }
                                });
                            }
                        }
                    });
                    
                    setTimeout(() => {
                        const rows = lastBlock.querySelectorAll('.tbody tr[data-size]');
                        rows.forEach(tr => {
                            const sizeVal = tr.getAttribute('data-size');
                            const match = colorObj.sizes.find(v => v.size === sizeVal || (v.size==='Standard' && sizeVal==='Standard'));
                            if (match) {
                                const qtyInp = tr.querySelector('.qty-input');
                                const costInp = tr.querySelector('.cost-input');
                                const sellInp = tr.querySelector('.selling-input');
                                const skuInp = tr.querySelector('.sku-input');
                                const priceCb = lastBlock.querySelector('.price-toggle');
                                
                                if(qtyInp) qtyInp.value = match.stock;
                                if(sellInp) {
                                    sellInp.value = match.price;
                                    const baseSell = parseFloat(document.getElementById('baseSellPrice').value) || 0;
                                    if(parseFloat(match.price) !== baseSell && priceCb && !priceCb.checked) {
                                        priceCb.click();
                                    }
                                }
                                if(costInp && match.cost) costInp.value = match.cost;
                                if(skuInp && match.sku) skuInp.value = match.sku;
                                if(qtyInp) qtyInp.dispatchEvent(new Event('input'));
                            }
                        });
                    }, 100);
                }
            });
        }
    }, 500); // Wait for masterVariants to init

    // Handle form validation for images: if existingImagesCount > 0, we don't block submission
    const form = document.getElementById('addProductForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (window.existingImagesCount === 0 && (typeof window.globalUploadedFiles === 'undefined' || window.globalUploadedFiles.length === 0)) {
                e.preventDefault();
                alert('Please upload at least 1 photo.');
                return false;
            }
        });
    }
});
</script>
<?php include('../include/footer.php'); ?>
