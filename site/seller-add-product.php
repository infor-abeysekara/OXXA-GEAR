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
            
            <!-- CARD 1: PRODUCT DETAILS -->
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

            <!-- CARD 2: BASE PRICING (BULK APPLY) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-tags text-green-500 me-2"></i> Base Pricing (Bulk Apply)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Base Cost Price (Rs) *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                            <input type="number" id="baseBuyPrice" class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-9 pr-4 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none transition-all" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Base Selling Price (Rs) *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-sm">Rs.</span>
                            <input type="number" id="baseSellPrice" class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-9 pr-4 text-sm font-bold text-navy focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] outline-none transition-all" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div></div>
                    <div>
                        <button type="button" id="applyBaseBtn" class="w-full bg-black hover:bg-gray-800 text-white px-6 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors shadow-sm flex items-center justify-center gap-2 h-[46px]">
                            <i class="fas fa-check-double"></i> Apply All
                        </button>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-3 font-medium"><i class="fas fa-info-circle me-1"></i> Entering a price here and clicking <strong>"Apply All"</strong> will automatically set the buy/sell price for all generated variants. You can override individual variant prices in the table below.</p>
            </div>

            <!-- CARD 3: PRODUCT IMAGES (GLOBAL) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-images text-pink-500 me-2"></i> Product Images</h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Main Images (4 to 10 photos) *</label>
                    <div class="global-preview-container grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-4">
                        <!-- Preview Images will appear here -->
                    </div>
                    
                    <label class="border-2 border-dashed border-gray-300 rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer hover:bg-gray-50 hover:border-[#0066FF] transition-all group global-file-label">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3 group-hover:bg-blue-50 transition-colors">
                            <i class="fas fa-cloud-upload-alt text-gray-400 text-xl group-hover:text-[#0066FF]"></i>
                        </div>
                        <span class="text-sm font-bold text-navy">Drag & drop or click to upload</span>
                        <span class="text-xs text-gray-400 mt-1">Upload 4-10 images. First image will be primary.</span>
                        <input type="file" name="product_images[]" multiple required accept="image/*" class="global-file-input hidden">
                    </label>
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
                    <!-- Color blocks will be appended here via JS -->
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

            <!-- PENDING APPROVAL WARNING -->
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
<script src="../assets/js/seller-add-product-colors.js?v=<?= time() ?>_2"></script>
<?php include('../include/footer.php'); ?>
