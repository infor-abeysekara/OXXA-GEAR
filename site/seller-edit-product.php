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

// Fetch product data
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

// Fetch existing variants for this product from color_sizes and product_colors
$varStmt = $pdo->prepare("
    SELECT 
        cs.size as size, 
        pc.color_name as color, 
        '' as flavor, 
        '' as weight, 
        '' as fit_type, 
        cs.cost_price as cost_price, 
        cs.selling_price as price, 
        cs.qty as qty, 
        cs.sku as sku
    FROM color_sizes cs
    JOIN product_colors pc ON cs.color_id = pc.id
    WHERE pc.product_id = ?
    ORDER BY pc.color_name, cs.id
");
$varStmt->execute([$product_id]);
$existingVariants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch product images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgStmt->execute([$product_id]);
$existingImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch latest hot deal request for this product
$hdrStmt = $pdo->prepare("SELECT * FROM hot_deal_requests WHERE product_id = ? ORDER BY id DESC LIMIT 1");
$hdrStmt->execute([$product_id]);
$latestHotDealRequest = $hdrStmt->fetch(PDO::FETCH_ASSOC);

// Count active hot deals for this seller
$activeDealsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND is_hot_deal = 1 AND hot_deal_status = 'approved' AND id != ?");
$activeDealsCountStmt->execute([$_SESSION['userid'], $product_id]);
$sellerActiveDealsCount = (int)$activeDealsCountStmt->fetchColumn();

// Check 7-day cooldown after rejection
$hasCooldown = false;
$daysLeftCooldown = 0;
if ($latestHotDealRequest && $latestHotDealRequest['status'] === 'rejected' && !empty($latestHotDealRequest['reviewed_at'])) {
    $reviewedTime = strtotime($latestHotDealRequest['reviewed_at']);
    if ($reviewedTime > strtotime('-7 days')) {
        $hasCooldown = true;
        $daysLeftCooldown = ceil(($reviewedTime + (7 * 86400) - time()) / 86400);
    }
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
                <h1 class="text-2xl font-black text-navy uppercase tracking-wide">Edit Product</h1>
                <p class="text-sm text-slate">Update details for <?= htmlspecialchars($product['name']) ?></p>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'hot_deal_requested'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-xl mb-8 flex items-center shadow-sm">
                <i class="fas fa-check-circle text-emerald-500 text-xl me-3"></i>
                <div>
                    <h4 class="font-bold text-sm">Request Sent for Review!</h4>
                    <p class="text-xs text-emerald-700 mt-0.5">Your Hot Deal promotion request has been sent to our administrators. You will be notified once reviewed.</p>
                </div>
            </div>
        <?php elseif (isset($_GET['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-xl mb-8 flex items-center shadow-sm">
                <i class="fas fa-check-circle text-emerald-500 text-xl me-3"></i>
                <span class="font-bold text-sm">Product updated successfully!</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-8 flex items-center">
                <i class="fas fa-exclamation-circle me-3"></i>
                <span class="font-bold">Error: <?= htmlspecialchars($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <form id="addProductForm" action="../Backend/seller-product-backend.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="update_product" value="1">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
            
            <!-- Basic Details -->
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
                                <option value="<?= $b['id'] ?>" <?= $product['brand_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Category *</label>
                        <select name="category_id" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all appearance-none">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Description *</label>
                        <textarea name="description" rows="4" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 px-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Bulk Apply Pricing -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-50 rounded-full opacity-50 pointer-events-none"></div>
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-coins text-[#0066FF] me-2"></i> Base Pricing (Bulk Apply)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                    <div class="col-span-1 md:col-span-5">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">
                            Base Cost Price (Rs.) * 
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                            <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" value="<?= $product['cost_price'] ?>" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all font-bold">
                        </div>
                    </div>
                    
                    <div class="col-span-1 md:col-span-5">
                        <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">
                            Base Selling Price (Rs.) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                            <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" value="<?= $product['base_price'] ?>" required class="w-full bg-gray-50 border border-gray-200 text-navy rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all font-bold">
                        </div>
                    </div>
                    
                    <div class="col-span-1 md:col-span-2">
                        <button type="button" onclick="applyBasePricesToVariants()" class="w-full bg-navy hover:bg-gray-800 text-white rounded-xl py-3 font-bold uppercase tracking-wide transition-colors h-[50px] shadow-sm flex items-center justify-center">
                            Apply All
                        </button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-4 font-bold"><i class="fas fa-info-circle me-1"></i> Entering prices here and clicking "Apply All" will automatically set the price for all generated variants below. You can then individually adjust variant prices.</p>
            </div>

            <!-- CARD 2.5: SHIPPING OPTIONS -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-truck text-emerald-500 me-2"></i> Shipping Options</h2>
                
                <div class="mb-4">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="is_free_shipping" id="is_free_shipping" value="1" <?= (isset($product['is_free_shipping']) && $product['is_free_shipping'] == 1) ? 'checked' : '' ?> class="w-5 h-5 rounded text-[#0066FF] border-gray-300 focus:ring-[#0066FF] cursor-pointer">
                        <span class="text-sm font-bold text-navy uppercase tracking-wide group-hover:text-[#0066FF] transition-colors">🚚 Free Shipping - Offer free delivery for this product</span>
                    </label>
                    <p id="freeShippingHelp" class="text-xs text-gray-400 mt-2 ml-8 font-medium">Customer ta delivery free. <span class="text-emerald-500 font-bold <?= (isset($product['is_free_shipping']) && $product['is_free_shipping'] == 1) ? '' : 'hidden' ?>" id="freeShippingSuccessMsg">Free delivery will be shown on product page</span></p>
                </div>

                <div id="shippingCostContainer">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Shipping Cost (Rs) *</label>
                    <div class="relative max-w-xs">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                        <input type="number" name="shipping_cost" id="shippingCost" value="<?= (isset($product['shipping_cost']) && $product['shipping_cost'] > 0) ? $product['shipping_cost'] : '300' ?>" <?= (isset($product['is_free_shipping']) && $product['is_free_shipping'] == 1) ? 'disabled' : '' ?> class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-9 pr-4 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none transition-all disabled:opacity-50 disabled:bg-gray-100" step="0.01">
                    </div>
                    <p class="text-xs text-gray-400 mt-2 font-medium">Leave 300 for standard islandwide delivery</p>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const freeShippingCheckbox = document.getElementById('is_free_shipping');
                    const shippingCostInput = document.getElementById('shippingCost');
                    const freeShippingSuccessMsg = document.getElementById('freeShippingSuccessMsg');
                    
                    if (freeShippingCheckbox && shippingCostInput) {
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
                    }
                });
            </script>



            <!-- Images & Variants -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-images text-purple-500 me-2"></i> Media & Inventory</h2>
                
                <!-- 1. Product Images Management -->
                <div class="mb-10" id="productImagesManager">
                    <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-bold text-navy uppercase tracking-wide">
                            Product Images * <span class="text-xs text-gray-400 font-normal normal-case ml-2">(Upload 1 to 10 photos, JPG/PNG/WEBP up to 10MB each)</span>
                        </label>
                        <span id="editImageCountBadge" class="text-xs font-bold px-3 py-1 rounded-full bg-blue-50 text-[#0066FF] border border-blue-200">
                            <?= count($existingImages) ?> / 10 photos
                        </span>
                    </div>

                    <!-- Hidden inputs container for deleted images -->
                    <div id="deletedImagesInputsContainer"></div>
                    <input type="hidden" name="primary_image" id="primaryImageInput" value="<?= !empty($existingImages) ? htmlspecialchars($existingImages[0]['image_path']) : '' ?>">

                    <style>
                        #editDropzone * {
                            pointer-events: none !important;
                        }
                    </style>

                    <!-- Grid of images (Existing + Newly Added + Add More Slot) -->
                    <div id="allImagesGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-4">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Drag & Drop Zone (Visible when 0 images) -->
                    <div id="editDropzoneWrapper" class="relative <?= count($existingImages) > 0 ? 'hidden' : '' ?>">
                        <label id="editDropzone" for="editFileInput" class="border-2 border-dashed border-gray-300 rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer hover:bg-blue-50/40 hover:border-[#0066FF] transition-all text-center group">
                            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-blue-100 group-hover:scale-110 transition-all">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl group-hover:text-[#0066FF]"></i>
                            </div>
                            <span class="text-sm font-bold text-navy dropzone-text">Drag & drop photos here, or <span class="text-[#0066FF] underline font-black">browse</span></span>
                            <span class="text-xs text-gray-400 mt-1.5 dropzone-subtext">Upload 1 to 10 photos. First photo will be the primary store image.</span>
                        </label>
                    </div>

                    <!-- Real input holding newly uploaded files -->
                    <input type="file" name="images[]" id="editFileInput" multiple accept="image/*" class="sr-only">

                    <div id="imageEditNotice" class="text-xs text-amber-600 font-bold mt-2 hidden flex items-center gap-1.5 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg">
                        <i class="fas fa-exclamation-triangle"></i> <span>Minimum 1 photo required for store listing.</span>
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
                <div class="mt-8">
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                        <div>
                            <label class="block text-sm font-bold text-navy uppercase tracking-wide">Variant Table & Inventory</label>
                            <p class="text-xs text-slate mt-0.5">Manage existing variations or add new ones below.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="addManualRowBtnTop" class="bg-white border-2 border-blue-200 hover:border-[#0066FF] text-[#0066FF] hover:bg-blue-50 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-sm flex items-center gap-1.5">
                                <i class="fas fa-plus"></i> Add Custom Variant
                            </button>
                            <button type="button" id="generateVariantsBtn" class="bg-[#0066FF] hover:bg-blue-700 text-white px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-sm flex items-center gap-1.5">
                                <i class="fas fa-layer-group"></i> Add Selected Chips
                            </button>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div id="bulkActionBar" class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4 flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Bulk Buy Price</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                                <input type="number" id="bulkBuyPrice" class="w-full bg-white border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-sm focus:border-[#0066FF] outline-none">
                            </div>
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Bulk Sell Price</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                                <input type="number" id="bulkSellPrice" class="w-full bg-white border border-gray-200 rounded-lg py-2 pl-9 pr-3 text-sm focus:border-[#0066FF] outline-none">
                            </div>
                        </div>
                        <div class="flex-1 min-w-[100px]">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Bulk Qty</label>
                            <input type="number" id="bulkQty" class="w-full bg-white border border-gray-200 rounded-lg py-2 px-3 text-sm focus:border-[#0066FF] outline-none">
                        </div>
                        <div>
                            <button type="button" id="applyBulkBtn" class="bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold transition-colors h-[38px]">
                                Apply to All
                            </button>
                        </div>
                    </div>

                    <!-- Desktop Table Wrapper (Hidden on Mobile) -->
                    <div class="hidden md:block overflow-x-auto border border-gray-200 rounded-xl custom-scrollbar" style="max-height: 500px;">
                        <table class="w-full text-left border-collapse min-w-[1200px]" id="variantTable">
                            <thead class="sticky top-0 z-10 bg-gray-50 shadow-[0_1px_0_rgba(229,231,235,1)]">
                                <tr id="variantTableHeader">
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[120px]">Variant 1</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[120px]">Variant 2</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[140px]">Fit Type</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[130px]">Buy Price</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[130px]">Sell Price</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[110px]">Profit</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[120px]">Qty</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[150px]">SKU</th>
                                    <th class="p-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider w-[60px] text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="variantTableBody" class="divide-y divide-gray-100 bg-white">
                                <?php if (empty($existingVariants)): ?>
                                <tr>
                                    <td colspan="9" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">
                                        Select a Category first.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($existingVariants as $v): 
                                        $v1 = urldecode($v['size'] ?? $v['flavor'] ?? '');
                                        $v2 = urldecode($v['color'] ?? $v['weight'] ?? '');
                                        $vFit = urldecode($v['fit_type'] ?? '');
                                        $profit = $v['price'] - $v['cost_price'];
                                    ?>
                                    <tr class="hover:bg-[#f0f7ff] transition-colors h-[60px] even:bg-[#fafafa]">
                                        <td class="p-4 border-b border-gray-100">
                                            <div class="inline-block bg-blue-50 text-[#0066FF] border border-[#0066FF]/20 px-3 py-1.5 rounded-lg text-xs font-black min-w-[80px] text-center">
                                                <?= htmlspecialchars($v1 ?: '-') ?>
                                                <input type="hidden" name="variant_size[]" value="<?= htmlspecialchars($v1) ?>">
                                            </div>
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <span class="text-xs font-bold text-navy"><?= htmlspecialchars($v2 ?: '-') ?></span>
                                            <input type="hidden" name="variant_color[]" value="<?= htmlspecialchars($v2) ?>">
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <select name="variant_fit[]" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none">
                                                <option value="Regular" <?= $vFit==='Regular'?'selected':'' ?>>Regular</option>
                                                <option value="Slim" <?= $vFit==='Slim'?'selected':'' ?>>Slim</option>
                                                <option value="Oversized" <?= $vFit==='Oversized'?'selected':'' ?>>Oversized</option>
                                                <option value="<?= htmlspecialchars($vFit) ?>" <?= $vFit && $vFit!=='Regular' && $vFit!=='Slim' && $vFit!=='Oversized' ? 'selected':'' ?> class="hidden"><?= htmlspecialchars($vFit) ?></option>
                                            </select>
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                                                <input type="number" step="0.01" min="0" name="variant_cost_price[]" value="<?= $v['cost_price'] ?>" placeholder="0.00" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-buy" oninput="calculateVarProfit(this)">
                                            </div>
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                                                <input type="number" step="0.01" min="0" name="variant_price[]" value="<?= $v['price'] ?>" placeholder="0.00" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-sell" oninput="calculateVarProfit(this)">
                                            </div>
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <?php if($profit < 0): ?>
                                                <span class="text-xs font-black text-red-500 var-profit">-Rs. <?= number_format(abs($profit), 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-xs font-black text-green-500 var-profit">+Rs. <?= number_format($profit, 2) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <div class="flex items-center border border-gray-200 rounded-lg bg-white overflow-hidden w-[80px]">
                                                <button type="button" class="px-2 py-1 bg-gray-50 text-gray-500 hover:bg-gray-100 font-bold border-r border-gray-200" onclick="const i=this.nextElementSibling; i.value=Math.max(0,(parseInt(i.value)||0)-1); updateTotalQty();">-</button>
                                                <input type="number" name="variant_qty[]" value="<?= $v['qty'] ?>" min="0" required class="w-full text-center py-1 text-xs font-bold focus:outline-none variant-qty-input" oninput="updateTotalQty()">
                                                <button type="button" class="px-2 py-1 bg-gray-50 text-gray-500 hover:bg-gray-100 font-bold border-l border-gray-200" onclick="const i=this.previousElementSibling; i.value=(parseInt(i.value)||0)+1; updateTotalQty();">+</button>
                                            </div>
                                        </td>
                                        <td class="p-4 border-b border-gray-100">
                                            <div class="relative">
                                                <input type="text" name="variant_sku[]" value="<?= htmlspecialchars($v['sku']) ?>" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none uppercase pr-6">
                                                <i class="fas fa-pencil-alt absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-300"></i>
                                            </div>
                                        </td>
                                        <td class="p-4 border-b border-gray-100 text-center">
                                            <button type="button" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors shadow-sm" onclick="if(confirm('Remove this variant?')) { this.closest('tr').remove(); syncMobileCards(); updateTotalQty(); }"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards Wrapper (Hidden on Desktop) -->
                    <div id="mobileVariantCards" class="md:hidden space-y-4">
                        <?php if (empty($existingVariants)): ?>
                            <div class="p-6 text-center text-gray-400 text-sm font-medium border border-gray-200 rounded-xl bg-gray-50" id="cardsEmptyState">
                                Select a Category first.
                            </div>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500 text-xs font-bold bg-yellow-50 border border-yellow-200 rounded-xl"><i class="fas fa-desktop mb-2 text-xl block"></i> Please use a Desktop device to easily edit variant prices and quantities.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Footer Summary -->
                    <div class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-4 flex flex-wrap gap-4 justify-between items-center">
                        <button type="button" id="addManualRowBtn" class="bg-white border border-gray-300 hover:border-[#0066FF] text-[#0066FF] hover:bg-blue-50 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5"><i class="fas fa-plus"></i> Add Custom Variant</button>
                        <div class="flex flex-wrap gap-4 md:gap-6 ml-auto">
                            <div class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-wide">Variants: <span id="totalVariantsCounter" class="text-navy text-sm ms-1 font-black">0</span></div>
                            <div class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-wide">Qty: <span id="totalQtyCounter" class="text-navy text-sm ms-1 font-black">0</span></div>
                            <div class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-wide">Buy: <span id="totalBuyCounter" class="text-navy text-sm ms-1 font-black">Rs. 0.00</span></div>
                            <div class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-wide">Sell: <span id="totalSellCounter" class="text-navy text-sm ms-1 font-black">Rs. 0.00</span></div>
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
                <button type="submit" name="update_product_btn" class="bg-[#0066FF] hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center text-lg">
                    Update Product <i class="fas fa-save ms-2"></i>
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
    
    const submitBtn = document.querySelector('button[name="update_product_btn"]') || document.querySelector('button[name="add_product"]');
    
    // Check if selling is higher than cost
    if (sell > 0 && cost > 0 && sell < cost) {
        vSell.textContent = 'Error';
        vSell.classList.add('text-red-500');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        return;
    } else {
        vSell.classList.remove('text-red-500');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
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



    // ==========================================
    // 1. PRODUCT IMAGES MANAGEMENT (EDIT MODE)
    // ==========================================
    let existingImages = <?= json_encode(array_values(array_map(function($img) {
        return [
            'id' => (int)$img['id'],
            'image_path' => $img['image_path'],
            'is_primary' => (int)$img['is_primary']
        ];
    }, $existingImages))) ?>;

    let deletedImages = [];
    let newUploadedFiles = []; // Array of File objects
    let currentPrimaryId = <?= !empty($existingImages) ? json_encode($existingImages[0]['image_path']) : '""' ?>;

    // Ensure we know which image is initial primary
    const initialPrimaryObj = existingImages.find(img => img.is_primary === 1);
    if (initialPrimaryObj) {
        currentPrimaryId = initialPrimaryObj.image_path;
    } else if (existingImages.length > 0) {
        currentPrimaryId = existingImages[0].image_path;
    }

    const editFileInput = document.getElementById('editFileInput');
    const editDropzone = document.getElementById('editDropzone');
    const editDropzoneWrapper = document.getElementById('editDropzoneWrapper');
    const allImagesGrid = document.getElementById('allImagesGrid');
    const editImageCountBadge = document.getElementById('editImageCountBadge');
    const imageEditNotice = document.getElementById('imageEditNotice');
    const primaryImageInput = document.getElementById('primaryImageInput');
    const deletedImagesInputsContainer = document.getElementById('deletedImagesInputsContainer');

    function syncEditFileInput() {
        if (!editFileInput) return;
        try {
            const dt = new DataTransfer();
            newUploadedFiles.forEach(file => dt.items.add(file));
            editFileInput.files = dt.files;
        } catch (e) {
            console.error('DataTransfer error:', e);
        }
    }

    function renderAllImages() {
        if (!allImagesGrid) return;
        allImagesGrid.innerHTML = '';

        const totalCount = existingImages.length + newUploadedFiles.length;

        // Update badge
        if (editImageCountBadge) {
            if (totalCount === 0) {
                editImageCountBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-red-50 text-red-600 border border-red-200';
                editImageCountBadge.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> 0 / 10 photos (Min 1 required)';
            } else {
                editImageCountBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-green-50 text-green-700 border border-green-200';
                editImageCountBadge.innerHTML = `<i class="fas fa-check-circle me-1"></i> ${totalCount} / 10 photos`;
            }
        }

        // Show/hide large dropzone when 0 images
        if (editDropzoneWrapper) {
            if (totalCount === 0) {
                editDropzoneWrapper.classList.remove('hidden');
            } else {
                editDropzoneWrapper.classList.add('hidden');
            }
        }

        // Show/hide warning notice
        if (imageEditNotice) {
            if (totalCount === 0) {
                imageEditNotice.classList.remove('hidden');
            } else {
                imageEditNotice.classList.add('hidden');
            }
        }

        // Sync hidden input for primary
        if (primaryImageInput) {
            primaryImageInput.value = currentPrimaryId;
        }

        // Render Existing Images
        existingImages.forEach((img, idx) => {
            const isPrimary = (currentPrimaryId === img.image_path || (currentPrimaryId === '' && idx === 0));
            const card = document.createElement('div');
            card.className = 'relative w-full aspect-square rounded-xl border border-gray-200 overflow-hidden bg-white shadow-sm group hover:shadow-md transition-all';
            
            card.innerHTML = `
                <img src="../assets/uploads/products/${img.image_path}" class="w-full h-full object-cover">
                
                <button type="button" class="absolute top-2 right-2 w-7 h-7 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-red-500 shadow-md hover:bg-red-500 hover:text-white hover:scale-110 transition-all z-10" onclick="removeExistingImage(${idx})" title="Delete Photo">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>

                <span class="absolute top-2 left-2 bg-black/60 backdrop-blur text-white text-[10px] font-black px-1.5 py-0.5 rounded shadow">
                    #${idx + 1}
                </span>

                ${isPrimary 
                    ? '<span class="absolute bottom-0 left-0 right-0 bg-[#0066FF] text-white text-[10px] font-black tracking-wider text-center py-1.5 cursor-default flex items-center justify-center gap-1"><i class="fas fa-star text-xs"></i> PRIMARY</span>'
                    : `<button type="button" class="absolute bottom-0 left-0 right-0 bg-gray-900/80 hover:bg-[#0066FF] text-white text-[10px] font-bold tracking-wider text-center py-1.5 transition-colors opacity-90 group-hover:opacity-100 flex items-center justify-center gap-1" onclick="setPrimaryExisting('${img.image_path}')"><i class="far fa-star text-xs"></i> SET PRIMARY</button>`
                }
            `;
            allImagesGrid.appendChild(card);
        });

        // Render New Uploaded Files
        newUploadedFiles.forEach((file, idx) => {
            const newKey = 'new_' + idx;
            const isPrimary = (currentPrimaryId === newKey || (existingImages.length === 0 && idx === 0));
            const objectUrl = URL.createObjectURL(file);
            const card = document.createElement('div');
            card.className = 'relative w-full aspect-square rounded-xl border-2 border-dashed border-[#0066FF]/60 overflow-hidden bg-white shadow-sm group hover:shadow-md transition-all';

            card.innerHTML = `
                <img src="${objectUrl}" class="w-full h-full object-cover">
                
                <button type="button" class="absolute top-2 right-2 w-7 h-7 bg-white/90 backdrop-blur rounded-full flex items-center justify-center text-red-500 shadow-md hover:bg-red-500 hover:text-white hover:scale-110 transition-all z-10" onclick="removeNewFile(${idx})" title="Remove New Photo">
                    <i class="fas fa-times text-xs"></i>
                </button>

                <span class="absolute top-2 left-2 bg-[#0066FF] text-white text-[10px] font-black px-1.5 py-0.5 rounded shadow">
                    NEW
                </span>

                ${isPrimary 
                    ? '<span class="absolute bottom-0 left-0 right-0 bg-[#0066FF] text-white text-[10px] font-black tracking-wider text-center py-1.5 cursor-default flex items-center justify-center gap-1"><i class="fas fa-star text-xs"></i> PRIMARY</span>'
                    : `<button type="button" class="absolute bottom-0 left-0 right-0 bg-gray-900/80 hover:bg-[#0066FF] text-white text-[10px] font-bold tracking-wider text-center py-1.5 transition-colors opacity-90 group-hover:opacity-100 flex items-center justify-center gap-1" onclick="setPrimaryNew(${idx})"><i class="far fa-star text-xs"></i> SET PRIMARY</button>`
                }
            `;
            allImagesGrid.appendChild(card);
        });

        // Render "+ Add More" slot if totalCount between 1 and 9
        if (totalCount > 0 && totalCount < 10) {
            const addSlot = document.createElement('label');
            addSlot.className = 'border-2 border-dashed border-gray-300 hover:border-[#0066FF] rounded-xl flex flex-col items-center justify-center p-3 aspect-square cursor-pointer hover:bg-blue-50/40 transition-all text-center group';
            addSlot.title = 'Add more photos (up to 10)';
            addSlot.innerHTML = `
                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mb-2 group-hover:bg-blue-100 group-hover:scale-110 transition-all">
                    <i class="fas fa-plus text-gray-400 group-hover:text-[#0066FF] text-sm"></i>
                </div>
                <span class="text-xs font-bold text-navy group-hover:text-[#0066FF]">Add More</span>
                <span class="text-[10px] text-gray-400 mt-0.5">${totalCount} / 10</span>
                <input type="file" multiple accept="image/*" class="hidden edit-add-more-input">
            `;

            const addMoreInput = addSlot.querySelector('.edit-add-more-input');
            addMoreInput.addEventListener('change', (e) => {
                handleNewIncomingFiles(e.target.files);
                addMoreInput.value = '';
            });

            allImagesGrid.appendChild(addSlot);
        }
    }

    // Set Primary Handlers
    window.setPrimaryExisting = function(path) {
        currentPrimaryId = path;
        renderAllImages();
    };

    window.setPrimaryNew = function(idx) {
        currentPrimaryId = 'new_' + idx;
        renderAllImages();
    };

    // Remove Image Handlers
    window.removeExistingImage = function(idx) {
        const removed = existingImages.splice(idx, 1)[0];
        if (removed) {
            deletedImages.push(removed.image_path);
            
            // Add hidden input so backend receives it
            if (deletedImagesInputsContainer) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'deleted_images[]';
                hiddenInput.value = removed.image_path;
                deletedImagesInputsContainer.appendChild(hiddenInput);
            }

            // If the deleted image was primary, choose new primary
            if (currentPrimaryId === removed.image_path) {
                if (existingImages.length > 0) {
                    currentPrimaryId = existingImages[0].image_path;
                } else if (newUploadedFiles.length > 0) {
                    currentPrimaryId = 'new_0';
                } else {
                    currentPrimaryId = '';
                }
            }
        }
        renderAllImages();
    };

    window.removeNewFile = function(idx) {
        newUploadedFiles.splice(idx, 1);
        syncEditFileInput();
        
        // If current primary was this new file, re-assign
        if (currentPrimaryId === 'new_' + idx) {
            if (existingImages.length > 0) {
                currentPrimaryId = existingImages[0].image_path;
            } else if (newUploadedFiles.length > 0) {
                currentPrimaryId = 'new_0';
            } else {
                currentPrimaryId = '';
            }
        }
        renderAllImages();
    };

    // Process new incoming files
    function handleNewIncomingFiles(fileList) {
        if (!fileList || fileList.length === 0) return;

        let oversizedCount = 0;
        const maxTotal = 10;
        const maxSizeBytes = 10 * 1024 * 1024; // 10MB

        Array.from(fileList).forEach(file => {
            const isImage = (file.type && file.type.startsWith('image/')) || 
                            /\.(jpe?g|png|webp|gif|bmp|jfif|avif|heic|svg)$/i.test(file.name || '');
            if (!isImage) return;

            if (file.size > maxSizeBytes) {
                oversizedCount++;
                return;
            }

            if (existingImages.length + newUploadedFiles.length >= maxTotal) return;

            // Check duplicate
            const isDup = newUploadedFiles.some(f => f.name === file.name && f.size === file.size);
            if (isDup) return;

            newUploadedFiles.push(file);
        });

        syncEditFileInput();
        renderAllImages();

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
    if (editFileInput) {
        editFileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                handleNewIncomingFiles(Array.from(e.target.files));
            }
        });
    }

    // Drag & Drop on main dropzone and grid
    function extractFiles(e) {
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

    // Dropzone events
    if (editDropzone) {
        ['dragenter', 'dragover'].forEach(ev => {
            editDropzone.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
                editDropzone.classList.add('border-[#0066FF]', 'bg-blue-50/70', 'ring-4', 'ring-blue-100');
            }, false);
        });

        ['dragleave', 'dragend'].forEach(ev => {
            editDropzone.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                editDropzone.classList.remove('border-[#0066FF]', 'bg-blue-50/70', 'ring-4', 'ring-blue-100');
            }, false);
        });

        editDropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            editDropzone.classList.remove('border-[#0066FF]', 'bg-blue-50/70', 'ring-4', 'ring-blue-100');
            const files = extractFiles(e);
            if (files.length > 0) {
                handleNewIncomingFiles(files);
            }
        }, false);
    }

    // Also support dropping directly onto the image grid
    if (allImagesGrid) {
        ['dragenter', 'dragover'].forEach(ev => {
            allImagesGrid.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
                allImagesGrid.classList.add('ring-2', 'ring-[#0066FF]', 'rounded-xl', 'bg-blue-50/20');
            }, false);
        });
        ['dragleave', 'dragend'].forEach(ev => {
            allImagesGrid.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
                allImagesGrid.classList.remove('ring-2', 'ring-[#0066FF]', 'rounded-xl', 'bg-blue-50/20');
            }, false);
        });
        allImagesGrid.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            allImagesGrid.classList.remove('ring-2', 'ring-[#0066FF]', 'rounded-xl', 'bg-blue-50/20');
            const files = extractFiles(e);
            if (files.length > 0) {
                handleNewIncomingFiles(files);
            }
        }, false);
    }

    // Initial render
    renderAllImages();

    // Form submit validation
    const addProductForm = document.getElementById('addProductForm');
    if (addProductForm) {
        addProductForm.addEventListener('submit', function(e) {
            const totalRemaining = existingImages.length + newUploadedFiles.length;
            if (totalRemaining < 1) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Photo Required',
                        text: 'Please keep or upload at least 1 photo for this product.',
                        confirmButtonColor: '#0066FF'
                    });
                } else {
                    alert('Please keep or upload at least 1 photo for this product.');
                }
                const pManager = document.getElementById('productImagesManager');
                if (pManager) {
                    pManager.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    pManager.classList.add('ring-4', 'ring-amber-300');
                    setTimeout(() => pManager.classList.remove('ring-4', 'ring-amber-300'), 2500);
                }
                return false;
            }

            syncEditFileInput();
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

    const addManualBtnTop = document.getElementById('addManualRowBtnTop');
    const bulkActionBar = document.getElementById('bulkActionBar');

    function getCatConfig(catId) {
        if (!categorySelect || !categorySelect.options || categorySelect.selectedIndex < 0) {
            return { type: 'default', col1: 'Variant / Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        }
        const catName = categorySelect.options[categorySelect.selectedIndex].text.toUpperCase();
        if (catName.includes('SPORTS WEAR')) return { type: 'sports', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: true };
        if (catName.includes('FOOTWEAR')) return { type: 'footwear', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('FITNESS')) return { type: 'fitness', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('ACCESSORIES')) return { type: 'accessories', col1: 'Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('EQUIPMENT')) return { type: 'equipment', col1: 'Variant / Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        if (catName.includes('NUTRITION')) return { type: 'nutrition', col1: 'Flavor', col2: 'Weight', dbCol1: 'variant_flavor[]', dbCol2: 'variant_weight[]', hasFit: false };
        return { type: 'default', col1: 'Variant / Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
    }

    function renderCategoryUI(catId, preserveExisting = false) {
        if (!preserveExisting) {
            activeSet1.clear();
            activeSet2.clear();
            activeSet3.clear();
            tableBody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">Select variant chips above and click "Add Selected Chips" or use "Add Custom Variant".</td></tr>`;
        }

        if (genBtn) genBtn.classList.remove('hidden');
        if (addManualBtn) addManualBtn.classList.remove('hidden');
        if (addManualBtnTop) addManualBtnTop.classList.remove('hidden');
        if (bulkActionBar) {
            bulkActionBar.classList.remove('hidden');
            bulkActionBar.classList.add('flex');
        }

        currentConfig = getCatConfig(catId);
        const data = categoryVariants[catId] || {};

        let html = '<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 bg-gray-50 p-6 rounded-2xl border border-gray-200">';
        
        // Render Column 1
        if (currentConfig.col1) {
            html += `<div>
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-bold text-navy uppercase tracking-wide">${currentConfig.col1} Options</label>
                    <span class="text-[11px] text-gray-400 font-medium">Click to select or type custom below</span>
                </div>`;
            
            if (currentConfig.type === 'footwear') {
                html += `<div class="flex gap-2 mb-3">
                    <select class="text-xs font-bold bg-blue-50 text-[#0066FF] border-none rounded-lg py-1 px-3 cursor-pointer outline-none focus:ring-2 focus:ring-blue-300">
                        <option value="US">US System</option>
                    </select>
                </div>`;
            }

            const dbTypeKey = (currentConfig.col1 === 'Variant / Size') ? 'Variant' : currentConfig.col1; 
            let chips = data[dbTypeKey] || data['Size'] || data['Variant'] || [];
            
            html += `<div class="flex flex-wrap gap-2 mb-3" id="chipsContainer1">`;
            chips.forEach(c => {
                let metaText = '';
                if (c.meta && currentConfig.type === 'footwear') {
                    metaText = `UK ${c.meta.UK} / EU ${c.meta.EU}`;
                }
                const isSelected = activeSet1.has(c.value);
                html += `
                    <div class="border rounded-xl px-4 py-2 cursor-pointer transition-all var-chip set1-chip text-center select-none shadow-xs ${isSelected ? 'bg-blue-50 border-[#0066FF] text-[#0066FF] font-bold' : 'bg-white border-gray-200 text-navy hover:border-[#0066FF]'}" data-val="${c.value}">
                        <span class="block text-sm font-bold">${c.value}</span>
                        ${metaText ? `<span class="block text-[10px] text-gray-400">${metaText}</span>` : ''}
                    </div>
                `;
            });
            html += `</div>`;

            // Custom variant / size adder
            html += `
                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-200/60">
                    <input type="text" id="customChipInput1" placeholder="+ Type custom ${currentConfig.col1} (e.g. Short Handle, 2KG)" class="bg-white border border-gray-200 text-navy rounded-xl py-2 px-3 text-xs font-medium focus:border-[#0066FF] outline-none flex-1">
                    <button type="button" id="addCustomChipBtn1" class="bg-blue-50 hover:bg-blue-100 text-[#0066FF] border border-[#0066FF]/30 px-3 py-2 rounded-xl text-xs font-bold transition-all shrink-0">
                        <i class="fas fa-plus me-1"></i> Add Chip
                    </button>
                </div>
            `;
            
            if (currentConfig.hasFit) {
                html += `<label class="block text-sm font-bold text-navy uppercase tracking-wide mb-2 mt-4">Fit Type</label>
                <div class="flex flex-wrap gap-2">`;
                (data['Fit Type'] || [{value:'Regular'}, {value:'Slim'}, {value:'Oversized'}]).forEach(f => {
                    const isSelected = activeSet3.has(f.value);
                    html += `<div class="border rounded-lg px-3 py-1 cursor-pointer transition-colors var-chip set3-chip text-xs font-bold select-none ${isSelected ? 'bg-blue-50 border-[#0066FF] text-[#0066FF]' : 'bg-white border-gray-200 text-navy hover:border-[#0066FF]'}" data-val="${f.value}">${f.value}</div>`;
                });
                html += `</div>`;
            }
            html += `</div>`;
        }

        // Render Column 2
        if (currentConfig.col2) {
            html += `<div>
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-bold text-navy uppercase tracking-wide">${currentConfig.col2} Options</label>
                    <span class="text-[11px] text-gray-400 font-medium">Click to select or type custom below</span>
                </div>`;
            
            if (currentConfig.col2 === 'Color') {
                html += `<div class="flex flex-wrap gap-2.5 mb-3" id="chipsContainer2">`;
                standardColors.forEach(c => {
                    let isSelected = false;
                    activeSet2.forEach(sc => { if(sc.name === c.name) isSelected = true; });
                    const checkColor = c.hex.toUpperCase() === '#FFFFFF' ? '#000' : '#FFF';
                    html += `<div class="w-8 h-8 rounded-full cursor-pointer transition-all flex items-center justify-center color-chip hover:scale-110 ${isSelected ? 'ring-2 ring-offset-2 ring-[#0066FF]' : ''}" data-val="${c.name}" data-hex="${c.hex}" title="${c.name}" style="background-color: ${c.hex}; ${c.hex==='#FFFFFF'?'border:1px solid #e5e7eb;':''}">${isSelected ? `<i class="fas fa-check text-[10px]" style="color: ${checkColor}"></i>` : ''}</div>`;
                });
                html += `</div>`;
            } else {
                const dbTypeKey = currentConfig.col2; 
                let chips = data[dbTypeKey] || [];
                html += `<div class="flex flex-wrap gap-2 mb-3" id="chipsContainer2">`;
                chips.forEach(c => {
                    const isSelected = activeSet2.has(c.value);
                    html += `<div class="border rounded-xl px-4 py-2 cursor-pointer transition-colors var-chip set2-chip text-sm font-bold select-none ${isSelected ? 'bg-blue-50 border-[#0066FF] text-[#0066FF]' : 'bg-white border-gray-200 text-navy hover:border-[#0066FF]'}" data-val="${c.value}">${c.value}</div>`;
                });
                html += `</div>`;
            }

            // Custom variant 2 adder (Color / Weight)
            html += `
                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-200/60">
                    <input type="text" id="customChipInput2" placeholder="+ Type custom ${currentConfig.col2} (e.g. Special Edition, 500g)" class="bg-white border border-gray-200 text-navy rounded-xl py-2 px-3 text-xs font-medium focus:border-[#0066FF] outline-none flex-1">
                    <button type="button" id="addCustomChipBtn2" class="bg-blue-50 hover:bg-blue-100 text-[#0066FF] border border-[#0066FF]/30 px-3 py-2 rounded-xl text-xs font-bold transition-all shrink-0">
                        <i class="fas fa-plus me-1"></i> Add Chip
                    </button>
                </div>
            `;
            html += `</div>`;
        }

        html += '</div>';
        dynamicUI.innerHTML = html;

        bindChipEvents();
        bindCustomChipAdders();
    }

    function bindChipEvents() {
        document.querySelectorAll('.var-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const val = this.dataset.val;
                let activeSet = this.classList.contains('set1-chip') ? activeSet1 : (this.classList.contains('set2-chip') ? activeSet2 : activeSet3);
                
                if (activeSet.has(val)) {
                    activeSet.delete(val);
                    this.classList.remove('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]', 'font-bold');
                    this.classList.add('bg-white', 'border-gray-200', 'text-navy');
                } else {
                    activeSet.add(val);
                    this.classList.add('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]', 'font-bold');
                    this.classList.remove('bg-white', 'border-gray-200', 'text-navy');
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

    function bindCustomChipAdders() {
        const btn1 = document.getElementById('addCustomChipBtn1');
        const inp1 = document.getElementById('customChipInput1');
        const cont1 = document.getElementById('chipsContainer1');
        if (btn1 && inp1 && cont1) {
            const add1 = () => {
                const val = inp1.value.trim();
                if (!val) return;
                activeSet1.add(val);
                const chip = document.createElement('div');
                chip.className = 'border rounded-xl px-4 py-2 cursor-pointer transition-all var-chip set1-chip text-center select-none shadow-xs bg-blue-50 border-[#0066FF] text-[#0066FF] font-bold';
                chip.dataset.val = val;
                chip.innerHTML = `<span class="block text-sm font-bold">${val}</span>`;
                chip.addEventListener('click', function() {
                    if (activeSet1.has(val)) {
                        activeSet1.delete(val);
                        this.classList.remove('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]', 'font-bold');
                        this.classList.add('bg-white', 'border-gray-200', 'text-navy');
                    } else {
                        activeSet1.add(val);
                        this.classList.add('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]', 'font-bold');
                        this.classList.remove('bg-white', 'border-gray-200', 'text-navy');
                    }
                });
                cont1.appendChild(chip);
                inp1.value = '';
            };
            btn1.addEventListener('click', add1);
            inp1.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add1(); } });
        }

        const btn2 = document.getElementById('addCustomChipBtn2');
        const inp2 = document.getElementById('customChipInput2');
        const cont2 = document.getElementById('chipsContainer2');
        if (btn2 && inp2 && cont2) {
            const add2 = () => {
                const val = inp2.value.trim();
                if (!val) return;
                activeSet2.add({name: val, hex: '#4B5563'});
                const chip = document.createElement('div');
                chip.className = 'border rounded-xl px-4 py-2 cursor-pointer transition-all var-chip set2-chip text-center select-none shadow-xs bg-blue-50 border-[#0066FF] text-[#0066FF] font-bold text-xs';
                chip.dataset.val = val;
                chip.innerHTML = `<span class="block text-xs font-bold">${val}</span>`;
                chip.addEventListener('click', function() {
                    let exists = false;
                    let objRef = null;
                    activeSet2.forEach(c => { if(c.name === val) { exists = true; objRef = c; }});
                    if (exists) {
                        activeSet2.delete(objRef);
                        this.classList.remove('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]', 'font-bold');
                        this.classList.add('bg-white', 'border-gray-200', 'text-navy');
                    } else {
                        activeSet2.add({name: val, hex: '#4B5563'});
                        this.classList.add('bg-blue-50', 'border-[#0066FF]', 'text-[#0066FF]', 'font-bold');
                        this.classList.remove('bg-white', 'border-gray-200', 'text-navy');
                    }
                });
                cont2.appendChild(chip);
                inp2.value = '';
            };
            btn2.addEventListener('click', add2);
            inp2.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add2(); } });
        }
    }

    categorySelect.addEventListener('change', (e) => {
        const catId = e.target.value;
        if (!catId) {
            dynamicUI.innerHTML = `<div class="p-6 text-center text-gray-400 text-sm font-medium border-2 border-dashed border-gray-200 rounded-xl">Select a Category above to load Variant options.</div>`;
            tableBody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-gray-400 text-sm font-medium" id="tableEmptyState">Select a Category first.</td></tr>`;
            genBtn.classList.add('hidden');
            addManualBtn.classList.add('hidden');
            if (addManualBtnTop) addManualBtnTop.classList.add('hidden');
            return;
        }

        const rowCount = tableBody.querySelectorAll('tr').length;
        const hasEmptyState = tableBody.querySelector('#tableEmptyState');
        if (rowCount > 0 && !hasEmptyState) {
            if (confirm('Category has been changed. Would you like to keep existing variants? Click OK to keep them, or Cancel to reset the table.')) {
                renderCategoryUI(catId, true);
            } else {
                renderCategoryUI(catId, false);
            }
        } else {
            renderCategoryUI(catId, false);
        }
    });

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

    function createVariantRow(v1, v2, v3, isCustom = false) {
        const sku = generateSKU(v1, v2, v3);
        const defCost = document.getElementById('cost_price')?.value || '';
        const defSell = document.getElementById('selling_price')?.value || '';
        
        const tr = document.createElement('tr');
        tr.className = "hover:bg-[#f0f7ff] transition-colors h-[60px] even:bg-[#fafafa]";
        let html = '';
        
        const col1Field = (currentConfig && currentConfig.dbCol1) ? currentConfig.dbCol1 : 'variant_size[]';
        const col2Field = (currentConfig && currentConfig.dbCol2) ? currentConfig.dbCol2 : 'variant_color[]';

        // Variant 1 (Size / Flavor / Variant)
        if (isCustom || !v1) {
            html += `<td class="p-4 border-b border-gray-100">
                <input type="text" name="${col1Field}" value="${v1 || ''}" placeholder="Size / Variant (e.g. Short Handle)" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none" required>
            </td>`;
        } else {
            html += `<td class="p-4 border-b border-gray-100">
                <div class="inline-block bg-blue-50 text-[#0066FF] border border-[#0066FF]/20 px-3 py-1.5 rounded-lg text-xs font-black min-w-[80px] text-center">
                    ${v1}
                    <input type="hidden" name="${col1Field}" value="${v1}">
                </div>
            </td>`;
        }

        // Variant 2 (Color / Weight)
        if (isCustom || !v2) {
            const colVal = v2 ? (typeof v2 === 'object' ? v2.name : v2) : '';
            html += `<td class="p-4 border-b border-gray-100">
                <input type="text" name="${col2Field}" value="${colVal}" placeholder="Color / Variant (e.g. Default)" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none">
            </td>`;
        } else {
            const colorName = typeof v2 === 'object' ? v2.name : v2;
            const colorHex = typeof v2 === 'object' ? v2.hex : 'transparent';
            html += `<td class="p-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    ${colorHex !== 'transparent' ? `<span class="w-5 h-5 rounded-full border border-gray-200 block shrink-0 shadow-sm" style="background-color: ${colorHex};"></span>` : ''}
                    <span class="text-xs font-bold text-navy">${colorName || 'Default'}</span>
                    <input type="hidden" name="${col2Field}" value="${colorName || 'Default'}">
                </div>
            </td>`;
        }

        // Fit Type
        html += `<td class="p-4 border-b border-gray-100">
            <select name="variant_fit[]" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none">
                <option value="Regular" ${v3==='Regular'?'selected':''}>Regular</option>
                <option value="Slim" ${v3==='Slim'?'selected':''}>Slim</option>
                <option value="Oversized" ${v3==='Oversized'?'selected':''}>Oversized</option>
                <option value="${v3||''}" ${v3 && v3!=='Regular' && v3!=='Slim' && v3!=='Oversized' ? 'selected':''} class="hidden">${v3||''}</option>
            </select>
        </td>`;

        // Buy Price
        html += `<td class="p-4 border-b border-gray-100">
            <div class="relative">
                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                <input type="number" step="0.01" min="0" name="variant_cost_price[]" value="${defCost}" placeholder="0.00" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-buy" oninput="calculateVarProfit(this)">
            </div>
        </td>`;

        // Sell Price
        html += `<td class="p-4 border-b border-gray-100">
            <div class="relative">
                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                <input type="number" step="0.01" min="0" name="variant_price[]" value="${defSell}" placeholder="0.00" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-sell" oninput="calculateVarProfit(this)">
            </div>
        </td>`;

        // Profit
        html += `<td class="p-4 border-b border-gray-100">
            <span class="text-xs font-black text-gray-400 var-profit">-</span>
        </td>`;

        // Qty Stepper
        html += `<td class="p-4 border-b border-gray-100">
            <div class="flex items-center border border-gray-200 rounded-lg bg-white overflow-hidden w-[80px]">
                <button type="button" class="px-2 py-1 bg-gray-50 text-gray-500 hover:bg-gray-100 font-bold border-r border-gray-200" onclick="const i=this.nextElementSibling; i.value=Math.max(0,(parseInt(i.value)||0)-1); updateTotalQty();">-</button>
                <input type="number" name="variant_qty[]" value="1" min="0" required class="w-full text-center py-1 text-xs font-bold focus:outline-none variant-qty-input" oninput="updateTotalQty()">
                <button type="button" class="px-2 py-1 bg-gray-50 text-gray-500 hover:bg-gray-100 font-bold border-l border-gray-200" onclick="const i=this.previousElementSibling; i.value=(parseInt(i.value)||0)+1; updateTotalQty();">+</button>
            </div>
        </td>`;

        // SKU
        html += `<td class="p-4 border-b border-gray-100">
            <div class="relative">
                <input type="text" name="variant_sku[]" value="${sku}" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none uppercase pr-6">
                <i class="fas fa-pencil-alt absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-300"></i>
            </div>
        </td>`;

        // Delete
        html += `<td class="p-4 border-b border-gray-100 text-center">
            <button type="button" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors shadow-sm" onclick="if(confirm('Remove this variant?')) { this.closest('tr').remove(); syncMobileCards(); updateTotalQty(); }"><i class="fas fa-trash-alt"></i></button>
        </td>`;
        
        tr.innerHTML = html;
        return tr;
    }

    function handleAddCustomVariant() {
        const emptyState = document.getElementById('tableEmptyState');
        if (emptyState) {
            const trParent = emptyState.closest('tr');
            if (trParent) trParent.remove();
        }
        if (!currentConfig && categorySelect && categorySelect.value) {
            currentConfig = getCatConfig(categorySelect.value);
        }
        if (!currentConfig) {
            currentConfig = { type: 'default', col1: 'Variant / Size', col2: 'Color', dbCol1: 'variant_size[]', dbCol2: 'variant_color[]', hasFit: false };
        }
        const row = createVariantRow('', '', '', true);
        tableBody.appendChild(row);
        calculateVarProfit(row.querySelector('.var-buy'));
        updateTotalQty();
        syncMobileCards();
    }

    if (addManualBtn) addManualBtn.addEventListener('click', handleAddCustomVariant);
    if (addManualBtnTop) addManualBtnTop.addEventListener('click', handleAddCustomVariant);

    function syncMobileCards() {
        const cardsContainer = document.getElementById('mobileVariantCards');
        if(!cardsContainer) return;
        cardsContainer.innerHTML = '<div class="p-6 text-center text-gray-500 text-xs font-bold bg-yellow-50 border border-yellow-200 rounded-xl"><i class="fas fa-desktop mb-2 text-xl block"></i> Please use a Desktop device to easily edit variant prices and quantities.</div>';
    }

    genBtn.addEventListener('click', () => {
        let arr1 = Array.from(activeSet1);
        let arr2 = Array.from(activeSet2);
        let arr3 = Array.from(activeSet3);

        if (arr1.length === 0) arr1 = [null];
        if (arr2.length === 0) arr2 = [null];
        if (arr3.length === 0) arr3 = [null];

        if (activeSet1.size === 0 && activeSet2.size === 0 && activeSet3.size === 0) {
            alert('Please select at least one variant option chip above, or click "+ Add Custom Variant" to add a custom row directly.');
            return;
        }

        // Show bulk action bar
        if (bulkActionBar) {
            bulkActionBar.classList.remove('hidden');
            bulkActionBar.classList.add('flex');
        }

        // Remove empty state if present
        const emptyState = document.getElementById('tableEmptyState');
        if (emptyState) {
            const trParent = emptyState.closest('tr');
            if (trParent) trParent.remove();
        }

        // Check duplicates logic
        const existingRows = Array.from(tableBody.querySelectorAll('tr'));
        const existingCombinations = existingRows.map(tr => {
            const inputs = tr.querySelectorAll('input[type="hidden"], input[name="variant_size[]"], input[name="variant_color[]"], input[name="variant_flavor[]"], input[name="variant_weight[]"]');
            if (inputs.length >= 2) return (inputs[0].value || '').trim().toUpperCase() + '|' + (inputs[1].value || '').trim().toUpperCase();
            return null;
        }).filter(Boolean);

        let addedCount = 0;

        arr1.forEach(v1 => {
            arr2.forEach(v2 => {
                arr3.forEach(v3 => {
                    const v1Val = (v1 || '').trim().toUpperCase();
                    const v2Val = (v2 ? (typeof v2 === 'object' ? v2.name : v2) : '').trim().toUpperCase();
                    const combo = v1Val + '|' + v2Val;
                    
                    if (!existingCombinations.includes(combo) || combo === '|') {
                        const newRow = createVariantRow(v1, v2, v3, false);
                        tableBody.appendChild(newRow);
                        calculateVarProfit(newRow.querySelector('.var-buy'));
                        existingCombinations.push(combo);
                        addedCount++;
                    }
                });
            });
        });
        
        if (addedCount > 0) {
            syncMobileCards();
            updateTotalQty();
        } else {
            alert('Selected variants already exist in the table.');
        }
    });

    // Bulk Apply Logic
    const applyBulkBtn = document.getElementById('applyBulkBtn');
    if (applyBulkBtn) {
        applyBulkBtn.addEventListener('click', () => {
            const bBuy = document.getElementById('bulkBuyPrice').value;
            const bSell = document.getElementById('bulkSellPrice').value;
            const bQty = document.getElementById('bulkQty').value;
            
            const rows = document.querySelectorAll('#variantTableBody tr');
            rows.forEach(tr => {
                const buyInp = tr.querySelector('.var-buy');
                const sellInp = tr.querySelector('.var-sell');
                const qtyInp = tr.querySelector('.variant-qty-input');
                if (bBuy !== '' && buyInp) buyInp.value = bBuy;
                if (bSell !== '' && sellInp) sellInp.value = bSell;
                if (bQty !== '' && qtyInp) qtyInp.value = bQty;
                
                if (buyInp && (bBuy !== '' || bSell !== '')) calculateVarProfit(buyInp);
            });
            updateTotalQty();
        });
    }

    // Auto-initialize variant chips and counters on page load
    if (categorySelect && categorySelect.value) {
        renderCategoryUI(categorySelect.value, true);
        updateTotalQty();
    }

    function updateTotalQty() {
        const rows = document.querySelectorAll('#variantTableBody tr');
        let totalQty = 0;
        let totalBuy = 0;
        let totalSell = 0;
        let variantCount = 0;

        rows.forEach(tr => {
            if (tr.querySelector('td[colspan="9"]')) return; // empty state
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
        if (buy > 0 && sell > 0 && sell < buy) {
            profitEl.textContent = '-Rs. ' + Math.abs(profit).toFixed(2);
            profitEl.classList.remove('text-[#0066FF]', 'text-gray-400', 'text-green-500');
            profitEl.classList.add('text-red-500');
        } else if (buy > 0 || sell > 0) {
            profitEl.textContent = '+Rs. ' + profit.toFixed(2);
            profitEl.classList.remove('text-red-500', 'text-gray-400', 'text-[#0066FF]');
            profitEl.classList.add('text-green-500');
        } else {
            profitEl.textContent = '-';
            profitEl.classList.remove('text-red-500', 'text-green-500', 'text-[#0066FF]');
            profitEl.classList.add('text-gray-400');
        }
        updateTotalQty();
    }

    function applyBasePricesToVariants() {
        const baseBuy = parseFloat(document.getElementById('cost_price').value) || 0;
        const baseSell = parseFloat(document.getElementById('selling_price').value) || 0;
        
        if (baseBuy <= 0 && baseSell <= 0) return;
        
        const rows = document.querySelectorAll('#variantTableBody tr');
        rows.forEach(tr => {
            const buyInput = tr.querySelector('.var-buy');
            const sellInput = tr.querySelector('.var-sell');
            if (buyInput && sellInput) {
                if (baseBuy > 0) buyInput.value = baseBuy;
                if (baseSell > 0) sellInput.value = baseSell;
                calculateVarProfit(buyInput);
            }
        });
    }

    // HOT DEALS CALCULATION & TOGGLE
    function calculateHotDealDiscount() {
        const origInput = document.getElementById('hot_deal_original_price');
        const saleInput = document.getElementById('hot_deal_sale_price');
        const badge = document.getElementById('hot_deal_badge_preview');
        const msg = document.getElementById('hot_deal_validation_msg');
        const chk = document.getElementById('request_hot_deal');

        if (!origInput || !saleInput || !badge) return;

        const orig = parseFloat(origInput.value) || 0;
        const sale = parseFloat(saleInput.value) || 0;

        if (orig <= 0 || sale <= 0 || sale >= orig) {
            badge.textContent = '0%';
            badge.className = 'bg-gray-700 text-gray-400 font-black px-4 py-2 rounded-xl text-lg shadow-lg transform -rotate-3 transition-transform duration-300 flex items-center justify-center';
            if (msg) msg.innerHTML = '<span class="text-amber-400 font-bold"><i class="fas fa-info-circle me-1"></i> Sale price must be lower than original price.</span>';
            if (chk && !chk.disabled) chk.dataset.discountValid = '0';
            return;
        }

        const discount = Math.round(((orig - sale) / orig) * 100);
        badge.textContent = '-' + discount + '%';

        if (discount < 15) {
            badge.className = 'bg-rose-500 text-white font-black px-4 py-2 rounded-xl text-lg shadow-lg transform -rotate-3 transition-transform duration-300 flex items-center justify-center';
            if (msg) msg.innerHTML = '<span class="text-rose-400 font-bold"><i class="fas fa-exclamation-triangle me-1"></i> Current discount is ' + discount + '%. Minimum 15% discount is required for Hot Deals.</span>';
            if (chk && !chk.disabled) chk.dataset.discountValid = '0';
        } else {
            badge.className = 'bg-[#CCFF00] text-black font-black px-4 py-2 rounded-xl text-lg shadow-lg transform -rotate-3 transition-transform duration-300 flex items-center justify-center';
            if (msg) msg.innerHTML = '<span class="text-emerald-400 font-bold"><i class="fas fa-check-circle me-1"></i> Great deal! ' + discount + '% discount qualifies for homepage Hot Deals.</span>';
            if (chk && !chk.disabled) chk.dataset.discountValid = '1';
        }
    }

    function toggleHotDealFields() {
        const chk = document.getElementById('request_hot_deal');
        const extra = document.getElementById('hot_deal_extra_fields');
        if (!chk || !extra) return;

        if (chk.checked) {
            calculateHotDealDiscount();
            if (chk.dataset.discountValid === '0') {
                alert('Minimum 15% discount is required to request a Hot Deal.');
                chk.checked = false;
                extra.classList.add('hidden');
                return;
            }
            extra.classList.remove('hidden');
            const reasonEl = document.getElementById('hot_deal_reason');
            if (reasonEl) reasonEl.setAttribute('required', 'required');
        } else {
            extra.classList.add('hidden');
            const reasonEl = document.getElementById('hot_deal_reason');
            if (reasonEl) reasonEl.removeAttribute('required');
        }
    }

    // Initialize discount on page load
    document.addEventListener('DOMContentLoaded', function() {
        calculateHotDealDiscount();
    });
</script>

<?php include('../include/footer.php'); ?>
