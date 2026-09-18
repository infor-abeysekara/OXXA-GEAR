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

// Fetch existing variants for this product
$varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$varStmt->execute([$product_id]);
$existingVariants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch product images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
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

            <!-- HOT DEALS Promotion Section -->
            <?php
            $origPriceValue = !empty($product['original_price']) && $product['original_price'] > 0 ? (float)$product['original_price'] : (float)$product['base_price'];
            $salePriceValue = !empty($product['sale_price']) && $product['sale_price'] > 0 ? (float)$product['sale_price'] : ($origPriceValue > 0 ? round($origPriceValue * 0.75, 2) : '');
            $currentDiscountPct = ($origPriceValue > 0 && $salePriceValue > 0 && $salePriceValue < $origPriceValue) ? round((($origPriceValue - $salePriceValue) / $origPriceValue) * 100) : 0;
            $isHotDealActive = ($product['is_hot_deal'] == 1 && $product['hot_deal_status'] === 'approved');
            $isHotDealPending = ($product['hot_deal_status'] === 'pending');
            $isHotDealRejected = ($product['hot_deal_status'] === 'rejected');
            $isHotDealExpired = ($product['hot_deal_status'] === 'expired');
            $stockCount = (int)$product['total_qty'];
            ?>
            <div class="bg-gradient-to-br from-[#0B0F19] to-[#161F30] text-white rounded-2xl shadow-xl p-8 relative overflow-hidden border border-white/10 mb-8">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-[#CCFF00] rounded-full blur-[100px] opacity-10 pointer-events-none"></div>
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-5 border-b border-white/10 relative z-10">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-[#CCFF00]/20 text-[#CCFF00] flex items-center justify-center font-black text-xl shadow-inner">
                                <i class="fas fa-bolt"></i>
                            </span>
                            <div>
                                <h2 class="text-xl font-black text-white uppercase tracking-wider">Hot Deals Promotion</h2>
                                <p class="text-xs text-gray-400 mt-0.5">Feature your product on the OXXA GEAR homepage with an exclusive countdown discount badge.</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <?php if ($isHotDealActive): ?>
                            <span class="inline-flex items-center gap-2 bg-[#CCFF00] text-black font-black text-xs px-4 py-2 rounded-full uppercase tracking-wider shadow-lg">
                                <span class="w-2 h-2 rounded-full bg-black animate-ping"></span> Live in Hot Deals
                            </span>
                        <?php elseif ($isHotDealPending): ?>
                            <span class="inline-flex items-center gap-2 bg-yellow-400 text-black font-black text-xs px-4 py-2 rounded-full uppercase tracking-wider shadow-lg">
                                <i class="fas fa-clock"></i> Pending Admin Approval
                            </span>
                        <?php elseif ($isHotDealRejected): ?>
                            <span class="inline-flex items-center gap-2 bg-rose-500 text-white font-black text-xs px-4 py-2 rounded-full uppercase tracking-wider shadow-lg">
                                <i class="fas fa-times-circle"></i> Request Rejected
                            </span>
                        <?php elseif ($isHotDealExpired): ?>
                            <span class="inline-flex items-center gap-2 bg-gray-600 text-gray-200 font-black text-xs px-4 py-2 rounded-full uppercase tracking-wider shadow-lg">
                                <i class="fas fa-history"></i> Deal Expired
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Context Alerts -->
                <?php if ($isHotDealActive): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 mb-6 relative z-10 flex items-start gap-3">
                        <i class="fas fa-check-circle text-emerald-400 text-xl mt-0.5"></i>
                        <div class="text-sm">
                            <p class="text-white font-bold">This product is currently featured in Hot Deals on the homepage!</p>
                            <p class="text-emerald-300/80 text-xs mt-1">Sale Price: <strong>Rs. <?= number_format($product['sale_price'], 2) ?></strong> (<?= $product['discount_percent'] ?>% OFF) &bull; Deal ends on: <strong><?= !empty($product['hot_deal_expiry']) ? date('M d, Y h:i A', strtotime($product['hot_deal_expiry'])) : 'No expiry' ?></strong></p>
                        </div>
                    </div>
                <?php elseif ($isHotDealPending): ?>
                    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-6 relative z-10 flex items-start gap-3">
                        <i class="fas fa-hourglass-half text-yellow-400 text-xl mt-0.5"></i>
                        <div class="text-sm">
                            <p class="text-white font-bold">Your Hot Deal request is under review by our admin team.</p>
                            <p class="text-yellow-200/80 text-xs mt-1">Requested Sale Price: <strong>Rs. <?= number_format($product['sale_price'], 2) ?></strong> (<?= $product['discount_percent'] ?>% OFF) &bull; Reason: "<?= htmlspecialchars($product['hot_deal_request_reason'] ?? 'Clearance') ?>"</p>
                        </div>
                    </div>
                <?php elseif ($isHotDealRejected): ?>
                    <div class="bg-rose-500/10 border border-rose-500/30 rounded-xl p-4 mb-6 relative z-10 flex items-start gap-3">
                        <i class="fas fa-exclamation-circle text-rose-400 text-xl mt-0.5"></i>
                        <div class="text-sm">
                            <p class="text-white font-bold">Your previous Hot Deal request was rejected.</p>
                            <p class="text-rose-300 text-xs mt-1">Reason: "<?= htmlspecialchars($latestHotDealRequest['reject_reason'] ?? 'Requirements not met') ?>"</p>
                            <?php if ($hasCooldown): ?>
                                <p class="text-rose-400 font-bold text-xs mt-2"><i class="fas fa-ban me-1"></i> Anti-Spam Cooldown: You can submit another Hot Deal request for this product in <?= $daysLeftCooldown ?> day(s).</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Price Fields Row with Live Auto Badge Preview -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center relative z-10 mb-6">
                    <div class="col-span-1 md:col-span-5">
                        <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                            Original Price (Rs.) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">Rs.</span>
                            <input type="number" step="0.01" min="1" id="hot_deal_original_price" name="hot_deal_original_price" value="<?= $origPriceValue ?>" class="w-full bg-white/5 border border-white/20 text-white rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#CCFF00] focus:ring-1 focus:ring-[#CCFF00] transition-all font-bold text-lg" oninput="calculateHotDealDiscount()">
                        </div>
                    </div>

                    <div class="col-span-1 md:col-span-5">
                        <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                            Sale Price (Rs.) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-bold">Rs.</span>
                            <input type="number" step="0.01" min="1" id="hot_deal_sale_price" name="hot_deal_sale_price" value="<?= $salePriceValue ?>" class="w-full bg-white/5 border border-white/20 text-white rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-[#CCFF00] focus:ring-1 focus:ring-[#CCFF00] transition-all font-bold text-lg text-[#CCFF00]" oninput="calculateHotDealDiscount()">
                        </div>
                    </div>

                    <div class="col-span-1 md:col-span-2 flex flex-col items-center justify-center pt-2 md:pt-6">
                        <span class="text-[10px] uppercase font-bold text-gray-400 mb-1">Discount Preview</span>
                        <div id="hot_deal_badge_preview" class="bg-[#CCFF00] text-black font-black px-4 py-2 rounded-xl text-lg shadow-lg transform -rotate-3 transition-transform duration-300 flex items-center justify-center">
                            -<?= $currentDiscountPct ?>%
                        </div>
                    </div>
                </div>

                <!-- Live Rule Feedback -->
                <div id="hot_deal_validation_msg" class="mb-6 relative z-10 text-xs">
                    <!-- Dynamic feedback from JS -->
                </div>

                <!-- Checkbox & Expansion -->
                <?php 
                $canRequest = (!$hasCooldown && $sellerActiveDealsCount < 2 && !$isHotDealPending && !$isHotDealActive);
                ?>
                <div class="border-t border-white/10 pt-6 relative z-10">
                    <label class="flex items-start sm:items-center gap-3 cursor-pointer group select-none">
                        <input type="checkbox" name="request_hot_deal" id="request_hot_deal" value="1" <?= (!$canRequest) ? 'disabled' : '' ?> onchange="toggleHotDealFields()" class="w-5 h-5 rounded border-white/30 text-[#CCFF00] focus:ring-[#CCFF00] bg-white/10 cursor-pointer mt-0.5 sm:mt-0">
                        <div>
                            <span class="font-black text-white text-base tracking-wide group-hover:text-[#CCFF00] transition-colors">Request to show in HOT DEALS on homepage</span>
                            <p class="text-xs text-gray-400">Products are subject to admin clearance review before going live.</p>
                        </div>
                    </label>

                    <?php if ($sellerActiveDealsCount >= 2 && !$isHotDealActive): ?>
                        <div class="mt-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>You already have 2 active Hot Deals running (Anti-Spam Limit). Deactivate an existing deal to submit a new one.</span>
                        </div>
                    <?php endif; ?>

                    <!-- Expandable Form Fields -->
                    <div id="hot_deal_extra_fields" class="hidden mt-6 pt-6 border-t border-white/10 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                                    <i class="fas fa-calendar-alt text-[#CCFF00] me-1"></i> Hot Deal Valid Until * (Max 7 Days)
                                </label>
                                <input type="date" name="hot_deal_expiry" id="hot_deal_expiry" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+7 days')) ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" class="w-full bg-white/5 border border-white/20 text-white rounded-xl py-3 px-4 focus:outline-none focus:border-[#CCFF00] focus:ring-1 focus:ring-[#CCFF00] transition-all font-bold">
                                <p class="text-[11px] text-gray-400 mt-1">Deals automatically expire after this date to keep homepage deals fresh.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                                    <i class="fas fa-fire text-[#CCFF00] me-1"></i> Why Hot? (Clearance Reason) *
                                </label>
                                <textarea name="hot_deal_reason" id="hot_deal_reason" rows="2" placeholder="e.g. Clearance stock, End of season promo, Flash discount..." class="w-full bg-white/5 border border-white/20 text-white rounded-xl py-2 px-4 focus:outline-none focus:border-[#CCFF00] focus:ring-1 focus:ring-[#CCFF00] transition-all text-sm"></textarea>
                                <p class="text-[11px] text-gray-400 mt-1">This explanation helps administrators verify and quickly approve your deal.</p>
                            </div>
                        </div>

                        <!-- Anti-Spam Badges Reminder -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                            <div class="bg-white/5 border border-white/10 rounded-xl p-3 text-center">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block">Min Discount</span>
                                <span class="text-sm font-black text-[#CCFF00]">15% OFF</span>
                            </div>
                            <div class="bg-white/5 border border-white/10 rounded-xl p-3 text-center">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block">Min Stock</span>
                                <span class="text-sm font-black text-white">10 Units</span>
                            </div>
                            <div class="bg-white/5 border border-white/10 rounded-xl p-3 text-center">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block">Max Duration</span>
                                <span class="text-sm font-black text-white">7 Days</span>
                            </div>
                            <div class="bg-white/5 border border-white/10 rounded-xl p-3 text-center">
                                <span class="text-[10px] uppercase font-bold text-gray-400 block">Max Active Deals</span>
                                <span class="text-sm font-black text-white">2 per Seller</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Images & Variants -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8">
                <h2 class="text-lg font-black text-navy uppercase tracking-wide mb-6 pb-2 border-b border-gray-100"><i class="fas fa-images text-purple-500 me-2"></i> Media & Inventory</h2>
                
                <!-- 1. Product Images -->
                <div class="mb-10">
                    <label class="block text-sm font-bold text-navy mb-2 uppercase tracking-wide">Product Images * <span class="text-xs text-gray-400 font-normal normal-case ml-2">(Min 4, Max 10 images, 1MB each. First image is primary)</span></label>
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
                <div class="mt-8">
                    <div class="flex justify-between items-end mb-4">
                        <label class="block text-sm font-bold text-navy uppercase tracking-wide">Variant Table & Inventory</label>
                        <button type="button" id="generateVariantsBtn" class="bg-navy hover:bg-gray-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors shadow-sm hidden">
                            <i class="fas fa-magic me-1"></i> Generate Table
                        </button>
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
                                        $v1 = $v['size'] ?? $v['flavor'] ?? '';
                                        $v2 = $v['color'] ?? $v['weight'] ?? '';
                                        $vFit = $v['fit_type'] ?? '';
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
                                                <input type="text" name="variant_sku[]" value="<?= htmlspecialchars($v['sku']) ?>" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none uppercase pr-6">
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
                        <button type="button" id="addManualRowBtn" class="text-sm font-bold text-[#0066FF] hover:underline hidden"><i class="fas fa-plus me-1"></i> Add Manual Row</button>
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
        if (currentConfig.col1) th += `<th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider">${currentConfig.col1}</th>`;
        if (currentConfig.col2) th += `<th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider">${currentConfig.col2}</th>`;
        if (currentConfig.hasFit) th += `<th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider">Fit Type</th>`;
        th += `<th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-28">Buy Price</th>
               <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-28">Sell Price</th>
               <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-24">Profit</th>
               <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-20">Qty</th>
               <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-24">SKU</th>
               <th class="p-3 text-[10px] font-bold text-gray-500 uppercase tracking-wider w-8 text-center"></th>`;
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

    function createVariantRow(v1, v2, v3, isMobile = false) {
        const sku = generateSKU(v1, v2, v3);
        
        // --- Desktop Row ---
        if (!isMobile) {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-[#f0f7ff] transition-colors h-[60px] even:bg-[#fafafa]";
            let html = '';
            
            // Size (Variant 1) - Chip
            if (currentConfig.col1) {
                html += `<td class="p-4 border-b border-gray-100">
                    <div class="inline-block bg-blue-50 text-[#0066FF] border border-[#0066FF]/20 px-3 py-1.5 rounded-lg text-xs font-black min-w-[80px] text-center">
                        ${v1||'-'}
                        <input type="hidden" name="${currentConfig.dbCol1}" value="${v1||''}">
                    </div>
                </td>`;
            }

            // Color (Variant 2) - Circle + Name
            if (currentConfig.col2) {
                if (currentConfig.col2 === 'Color') {
                    const colorName = v2 ? v2.name : '-';
                    const colorHex = v2 ? v2.hex : 'transparent';
                    html += `<td class="p-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            ${v2 ? `<span class="w-5 h-5 rounded-full border border-gray-200 block shrink-0 shadow-sm" style="background-color: ${colorHex};"></span>` : ''}
                            <span class="text-xs font-bold text-navy">${colorName}</span>
                            <input type="hidden" name="${currentConfig.dbCol2}" value="${colorName}">
                        </div>
                    </td>`;
                } else {
                    html += `<td class="p-4 border-b border-gray-100">
                        <span class="text-xs font-bold text-navy">${v2||'-'}</span>
                        <input type="hidden" name="${currentConfig.dbCol2}" value="${v2||''}">
                    </td>`;
                }
            }

            // Fit Type
            if (currentConfig.hasFit) {
                html += `<td class="p-4 border-b border-gray-100">
                    <select name="variant_fit[]" class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 px-2 text-xs font-bold focus:border-[#0066FF] outline-none">
                        <option value="Regular" ${v3==='Regular'?'selected':''}>Regular</option>
                        <option value="Slim" ${v3==='Slim'?'selected':''}>Slim</option>
                        <option value="Oversized" ${v3==='Oversized'?'selected':''}>Oversized</option>
                        <option value="${v3||''}" ${v3 && v3!=='Regular' && v3!=='Slim' && v3!=='Oversized' ? 'selected':''} class="hidden">${v3||''}</option>
                    </select>
                </td>`;
            }

            // Buy Price
            html += `<td class="p-4 border-b border-gray-100">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                    <input type="number" step="0.01" min="0" name="variant_cost_price[]" value="" placeholder="0.00" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-buy" oninput="calculateVarProfit(this)">
                </div>
            </td>`;

            // Sell Price
            html += `<td class="p-4 border-b border-gray-100">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 font-bold text-xs">Rs.</span>
                    <input type="number" step="0.01" min="0" name="variant_price[]" value="" placeholder="0.00" required class="w-full bg-white border border-gray-200 text-navy rounded-lg py-1.5 pl-7 pr-2 text-xs font-bold focus:border-[#0066FF] outline-none var-sell" oninput="calculateVarProfit(this)">
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
                    <input type="number" name="variant_qty[]" value="0" min="0" required class="w-full text-center py-1 text-xs font-bold focus:outline-none variant-qty-input" oninput="updateTotalQty()">
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
            return tr;
        } 
        
        // --- Mobile Card ---
        else {
            const div = document.createElement('div');
            div.className = "bg-white border border-gray-200 rounded-xl p-4 shadow-sm relative";
            let html = `<button type="button" class="absolute top-4 right-4 w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors" onclick="if(confirm('Remove this variant?')) { this.closest('.bg-white').remove(); /* Need proper sync to desktop table here ideally, but for now we rely on desktop sync generating this */ }"><i class="fas fa-trash-alt"></i></button>`;
            
            html += `<div class="flex flex-wrap items-center gap-3 mb-4 pr-10">`;
            if (currentConfig.col1) {
                html += `<div class="bg-blue-50 text-[#0066FF] px-2 py-1 rounded text-xs font-black">${v1||'-'}</div>`;
            }
            if (currentConfig.col2 && currentConfig.col2 === 'Color') {
                const colorName = v2 ? v2.name : '-';
                const colorHex = v2 ? v2.hex : 'transparent';
                html += `<div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full border border-gray-200" style="background-color: ${colorHex};"></span><span class="text-xs font-bold text-navy">${colorName}</span></div>`;
            }
            if (currentConfig.hasFit) {
                html += `<div class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">${v3||'Regular'}</div>`;
            }
            html += `</div>`;

            html += `<div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Buy Price (Rs)</label>
                    <input type="number" step="0.01" value="" placeholder="0.00" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold" readonly>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Sell Price (Rs)</label>
                    <input type="number" step="0.01" value="" placeholder="0.00" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold" readonly>
                </div>
            </div>`;

            html += `<div class="flex justify-between items-center bg-gray-50 p-2 rounded-lg mb-3">
                <span class="text-[10px] font-bold text-gray-500 uppercase">Profit</span>
                <span class="text-xs font-black text-gray-400">-</span>
            </div>`;

            html += `<div class="grid grid-cols-2 gap-3 items-end">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Qty</label>
                    <input type="number" value="0" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-1.5 px-3 text-xs font-bold" readonly>
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

    // Function to keep mobile cards synchronized with desktop table data (one-way sync for simplicity, actual submission relies on desktop table form fields)
    // In a production app, the form fields would be in the mobile cards too, but here we just hide the table visually. 
    // The form still submits the table's hidden inputs.
    function syncMobileCards() {
        // Not fully implemented for two-way edit in this prototype. 
        // We will just show a message on mobile.
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
            alert('Please select at least one variant option to generate the table.');
            return;
        }

        // Show bulk action bar
        document.getElementById('bulkActionBar').classList.remove('hidden');
        document.getElementById('bulkActionBar').classList.add('flex');

        // Check duplicates logic
        const existingRows = Array.from(tableBody.querySelectorAll('tr'));
        const existingCombinations = existingRows.map(tr => {
            const inputs = tr.querySelectorAll('input[type="hidden"]');
            if(inputs.length >= 2) return inputs[0].value + '|' + inputs[1].value;
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
                    const combo = v1Val + '|' + v2Val;
                    
                    if (!existingCombinations.includes(combo) || combo === '|') {
                        tableBody.appendChild(createVariantRow(v1, v2, v3, false));
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
    document.getElementById('applyBulkBtn').addEventListener('click', () => {
        const bBuy = document.getElementById('bulkBuyPrice').value;
        const bSell = document.getElementById('bulkSellPrice').value;
        const bQty = document.getElementById('bulkQty').value;
        
        const rows = document.querySelectorAll('#variantTableBody tr');
        rows.forEach(tr => {
            if(bBuy !== '') tr.querySelector('.var-buy').value = bBuy;
            if(bSell !== '') tr.querySelector('.var-sell').value = bSell;
            if(bQty !== '') tr.querySelector('.variant-qty-input').value = bQty;
            
            if(bBuy !== '' || bSell !== '') calculateVarProfit(tr.querySelector('.var-buy'));
        });
        updateTotalQty();
    });

    addManualBtn.addEventListener('click', () => {
        if (tableBody.querySelector('td[colspan="9"]')) tableBody.innerHTML = '';
        tableBody.appendChild(createVariantRow('', '', ''));
        syncMobileCards();
    });

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
