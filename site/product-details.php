<?php
$page_title = 'Product Details - OXXA GEAR';
include('../include/header.php');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    echo "<div class='min-h-[60vh] flex items-center justify-center'><div class='text-center'><h2 class='text-2xl font-black mb-4'>Product Not Found</h2><a href='shop.php' class='text-primary hover:underline font-bold'>Back to Shop</a></div></div>";
    include('../include/footer.php');
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, b.name as brand_name, b.logo_image as brand_logo
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       LEFT JOIN brands b ON p.brand_id = b.id
                       WHERE p.id = ? AND p.is_approved = 1 AND p.status = 'active'");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='min-h-[60vh] flex items-center justify-center'><div class='text-center'><h2 class='text-2xl font-black mb-4'>Product Not Found</h2><a href='shop.php' class='text-primary hover:underline font-bold'>Back to Shop</a></div></div>";
    include('../include/footer.php');
    exit;
}

// Fetch Images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$imgStmt->execute([$product_id]);
$images = $imgStmt->fetchAll();

// Fetch Variants
$varStmt = $pdo->prepare("SELECT cs.id, pc.color_name as color, cs.size, cs.selling_price as price, cs.qty, pc.thumbnail_path
                          FROM color_sizes cs 
                          JOIN product_colors pc ON cs.color_id = pc.id 
                          WHERE pc.product_id = ? 
                          ORDER BY cs.selling_price ASC");
$varStmt->execute([$product_id]);
$variants = $varStmt->fetchAll();

// Get lowest price, highest price, and total stock
$lowestPrice = $product['base_price'];
$highestPrice = $product['base_price'];
$totalStock = $product['total_qty'];

$isHotDealProduct = ($product['is_hot_deal'] == 1 && $product['hot_deal_status'] === 'approved' && 
                     (empty($product['hot_deal_expiry']) || strtotime($product['hot_deal_expiry']) >= time()));

if ($isHotDealProduct && !empty($product['sale_price']) && (float)$product['sale_price'] > 0) {
    $hotDealSalePrice = (float)$product['sale_price'];
    $hotDealOrigPrice = (float)($product['original_price'] > 0 ? $product['original_price'] : $product['base_price']);
    $hotDealDiscount = (int)($product['discount_percent'] > 0 ? $product['discount_percent'] : round((($hotDealOrigPrice - $hotDealSalePrice) / $hotDealOrigPrice) * 100));
    $hotDealDaysLeft = !empty($product['hot_deal_expiry']) ? ceil((strtotime($product['hot_deal_expiry']) - time()) / 86400) : null;
} else {
    $isHotDealProduct = false;
}

if (!empty($variants)) {
    $prices = [];
    $totalStock = 0;
    foreach($variants as $v) {
        $p = ($v['price'] > 0) ? $v['price'] : $product['base_price'];
        $prices[] = $p;
        $totalStock += $v['qty'];
    }
    if (!empty($prices)) {
        $lowestPrice = min($prices);
        $highestPrice = max($prices);
    }
}

// Group variants by color
$variantsByColor = [];
$uniqueColors = [];
$allSizes = [];
$variantsJsonData = [];
if (!empty($variants)) {
    foreach($variants as $v) {
        $c = !empty($v['color']) ? trim($v['color']) : 'Default';
        $variantsByColor[$c][] = $v;
        if (!in_array($c, $uniqueColors)) {
            $uniqueColors[] = $c;
        }
        if (!empty($v['size']) && !in_array($v['size'], $allSizes)) {
            $allSizes[] = $v['size'];
        }
        
        $vPrice = ($v['price'] > 0) ? $v['price'] : $product['base_price'];
        $variantsJsonData[] = [
            'id' => $v['id'],
            'color' => $c,
            'size' => $v['size'],
            'price' => $vPrice,
            'qty' => $v['qty']
        ];
    }
}
$isTwoStep = count($uniqueColors) > 0 && ($uniqueColors[0] !== 'Default' || count($uniqueColors) > 1);

// Removed Seller Fetch (We show Sold by OXXA GEAR)

// Fetch Brand
$brandName = $product['brand_name'] ?? ''; 
$brandLogo = $product['brand_logo'] ?? ''; 

// Fetch Related Products
$relStmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND is_approved = 1 AND status = 'active' ORDER BY RAND() LIMIT 4");
$relStmt->execute([$product['category_id'], $product_id]);
$relatedProducts = $relStmt->fetchAll();

// Fetch Reviews & Stats
$revStmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name, u.profile_image, 
        (SELECT size FROM order_items oi WHERE oi.order_id = r.order_id AND oi.product_id = r.product_id LIMIT 1) as purchased_variant,
        (SELECT reply_text FROM review_replies rep WHERE rep.review_id = r.id LIMIT 1) as seller_reply,
        sp.business_name as seller_name
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN seller_profiles sp ON r.seller_id = sp.user_id
        WHERE r.product_id = ? AND r.status != 'hidden' ORDER BY r.created_at DESC");
$revStmt->execute([$product_id]);
$productReviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

$totalReviews = count($productReviews);
$avgRating = 0;
$ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$fitCounts = ['Runs Small' => 0, 'True to Size' => 0, 'Runs Large' => 0];

if ($totalReviews > 0) {
    $sumRating = 0;
    foreach ($productReviews as $pr) {
        $sumRating += $pr['rating'];
        $ratingCounts[$pr['rating']]++;
        if(isset($fitCounts[$pr['fit_feedback']])) {
            $fitCounts[$pr['fit_feedback']]++;
        }
    }
    $avgRating = round($sumRating / $totalReviews, 1);
}
$trueToSizePct = $totalReviews > 0 ? round(($fitCounts['True to Size'] / $totalReviews) * 100) : 0;

// Check if user can review (logged in + verified purchase)
$canReview = false;
$purchasedOrderId = null;
if (isset($_SESSION['userid'])) {
    $checkStmt = $pdo->prepare("
        SELECT o.id FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered' 
        LIMIT 1
    ");
    $checkStmt->execute([$_SESSION['userid'], $product_id]);
    $purchasedOrderId = $checkStmt->fetchColumn();
    
    if ($purchasedOrderId) {
        // Check if already reviewed
        $revCheckStmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
        $revCheckStmt->execute([$_SESSION['userid'], $product_id]);
        if (!$revCheckStmt->fetchColumn()) {
            $canReview = true;
        }
    }
}
?>

<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

<div class="bg-white min-h-screen pb-24 md:pb-12">
    <!-- Breadcrumb (Hidden on Mobile) -->
    <div class="hidden md:block max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center text-xs font-bold text-gray-400 uppercase tracking-wider">
            <a href="index.php" class="hover:text-primary transition-colors">Home</a>
            <span class="mx-2">/</span>
            <a href="shop.php?category=<?php echo htmlspecialchars($product['category_id']); ?>" class="hover:text-primary transition-colors"><?php echo htmlspecialchars($product['category_name'] ?? 'Shop'); ?></a>
            <span class="mx-2">/</span>
            <span class="text-navy truncate max-w-[200px]"><?php echo htmlspecialchars($product['name']); ?></span>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-0 md:px-6 lg:px-8 flex flex-col md:flex-row gap-0 md:gap-12 lg:gap-16">
        
        <!-- Mobile Image Carousel (Swiper) -->
        <div class="w-full md:w-1/2">
            <div class="relative bg-[#F8F9FA] md:bg-transparent md:rounded-[2rem] overflow-hidden group">
            
            <!-- Mobile Back Button overlay -->
            <button onclick="history.back()" class="md:hidden absolute top-4 left-4 z-10 w-10 h-10 bg-white/80 backdrop-blur-md rounded-full flex items-center justify-center text-navy shadow-sm">
                <i class="fas fa-chevron-left"></i>
            </button>
            <!-- Mobile Wishlist Button overlay -->
            <button class="md:hidden absolute top-4 right-4 z-10 w-10 h-10 bg-white/80 backdrop-blur-md rounded-full flex items-center justify-center text-gray-400 shadow-sm">
                <i class="far fa-heart"></i>
            </button>

            <!-- Swiper -->
            <div class="swiper productSwiper w-full aspect-square md:aspect-auto md:h-auto object-cover bg-[#F8F9FA] md:rounded-[2rem]">
                <div class="swiper-wrapper">
                    <?php if(!empty($images)): ?>
                        <?php foreach($images as $img): ?>
                        <div class="swiper-slide flex items-center justify-center p-4 md:p-8">
                            <img src="../assets/uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-contain mix-blend-multiply drop-shadow-sm cursor-zoom-in" onclick="openZoom(this.src)">
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide flex items-center justify-center bg-gray-100">
                            <i class="fas fa-image text-gray-300 text-5xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination !bottom-4"></div>
            </div>
            
            <?php if($totalReviews > 0): ?>
            <!-- Trust Badge for Reviews -->
            <div class="mt-4 flex justify-center">
                <div class="inline-flex items-center gap-2 bg-gray-50 border border-gray-100 rounded-full px-4 py-2">
                    <span class="text-sm font-black text-navy"><i class="fas fa-star text-yellow-400 me-1"></i> <?php echo $avgRating; ?> <span class="text-gray-400 font-bold">(<?php echo $totalReviews; ?>)</span></span>
                    <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wide"><?php echo $trueToSizePct; ?>% said True to Size</span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Desktop Thumbnails -->
            <?php if(count($images) > 1): ?>
            <div class="hidden md:flex gap-4 mt-6 overflow-x-auto pb-4 custom-scrollbar">
                <?php foreach($images as $index => $img): ?>
                <button onclick="productSwiper.slideTo(<?php echo $index; ?>)" onmouseenter="productSwiper.slideTo(<?php echo $index; ?>)" class="w-20 h-20 rounded-xl bg-[#F8F9FA] border-2 border-transparent hover:border-primary focus:border-primary transition-colors flex-shrink-0 flex items-center justify-center overflow-hidden">
                    <img src="../assets/uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" class="w-full h-full object-contain mix-blend-multiply p-2">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            </div>

            <!-- Reviews Section -->
            <div id="reviewsSection" class="mt-12 mb-8 scroll-mt-24">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-navy uppercase tracking-wide">Customer Reviews</h3>
                    <?php if($canReview): ?>
                        <button onclick="openReviewModal()" class="bg-[#0066FF] hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-bold text-sm shadow-sm transition-colors">Write a Review</button>
                    <?php endif; ?>
                </div>
                <?php if ($totalReviews > 0): ?>
                    <div class="flex items-center gap-6 mb-8">
                        <div class="text-5xl font-black text-navy"><?php echo $avgRating; ?></div>
                        <div>
                            <div class="flex text-yellow-400 mb-1">
                                <?php 
                                $fullStars = floor($avgRating);
                                $halfStar = ($avgRating - $fullStars) >= 0.5;
                                $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                
                                for($i=0; $i<$fullStars; $i++) echo '<i class="fas fa-star"></i> ';
                                if($halfStar) echo '<i class="fas fa-star-half-alt"></i> ';
                                for($i=0; $i<$emptyStars; $i++) echo '<i class="far fa-star"></i> ';
                                ?>
                            </div>
                            <div class="text-sm font-bold text-gray-400">Based on <?php echo $totalReviews; ?> reviews</div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <?php foreach($productReviews as $rev): ?>
                        <div class="bg-gray-50 rounded-2xl p-6">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="font-black text-navy uppercase tracking-wide mb-1">
                                        <?php echo $rev['is_anonymous'] ? 'Anonymous' : htmlspecialchars($rev['first_name'] . ' ' . substr($rev['last_name'], 0, 1) . '.'); ?>
                                        <i class="fas fa-check-circle text-primary text-xs ml-1" title="Verified Buyer"></i>
                                    </div>
                                    <div class="flex text-yellow-400 text-xs">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $rev['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span class="text-xs font-bold text-gray-400"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                            </div>
                            <?php if(!empty($rev['title'])): ?>
                                <h4 class="font-bold text-navy mb-1"><?php echo htmlspecialchars($rev['title']); ?></h4>
                            <?php endif; ?>
                            <p class="text-sm font-medium text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                            
                            <?php
                            // Fetch images for this review
                            $revImgStmt = $pdo->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                            $revImgStmt->execute([$rev['id']]);
                            $revImages = $revImgStmt->fetchAll(PDO::FETCH_COLUMN);
                            if(count($revImages) > 0):
                            ?>
                                <div class="flex gap-2 mb-3">
                                    <?php foreach($revImages as $rImg): ?>
                                        <img src="../assets/uploads/reviews/<?php echo htmlspecialchars($rImg); ?>" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-xs font-bold text-gray-400 flex flex-wrap gap-4">
                                <?php if($rev['purchased_variant']): ?>
                                <span>Purchased: <span class="text-navy"><?php echo htmlspecialchars($rev['purchased_variant']); ?></span></span>
                                <?php endif; ?>
                                <span>Fit: <span class="text-navy"><?php echo htmlspecialchars($rev['fit_feedback']); ?></span></span>
                            </div>

                            <?php if(!empty($rev['seller_reply'])): ?>
                                <div class="mt-4 p-4 bg-blue-50 border border-blue-100 rounded-xl relative">
                                    <div class="absolute -top-3 left-4 bg-[#0066FF] text-white text-[10px] font-black px-2 py-0.5 rounded shadow-sm uppercase tracking-wider flex items-center gap-1">
                                        <i class="fas fa-check-circle"></i> Verified Seller
                                    </div>
                                    <div class="font-bold text-navy text-xs uppercase tracking-wide mb-1 mt-1"><i class="fas fa-reply text-[#0066FF] me-1"></i> Response from <?php echo htmlspecialchars($rev['seller_name'] ?? 'Seller'); ?></div>
                                    <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($rev['seller_reply'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 bg-gray-50 rounded-2xl">
                        <i class="far fa-star text-4xl text-gray-300 mb-3"></i>
                        <h4 class="font-bold text-navy">No reviews yet</h4>
                        <p class="text-sm text-gray-500 mt-1">Be the first to review this product after purchase!</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Product Details -->
        <div class="w-full md:w-1/2 p-5 md:p-0 pt-6 md:pt-4">
            
            <!-- Brand & Badges -->
            <div class="flex items-center justify-between mb-3">
                <?php if(!empty($brandName)): ?>
                    <div class="flex items-center gap-3">
                        <?php if(!empty($brandLogo)): ?>
                            <img src="../assets/uploads/brands/<?php echo htmlspecialchars($brandLogo); ?>" alt="<?php echo htmlspecialchars($brandName); ?>" class="h-10 w-auto object-contain">
                        <?php endif; ?>
                        <span class="text-base font-black text-black uppercase tracking-widest"><?php echo htmlspecialchars($brandName); ?></span>
                    </div>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                
                <!-- Desktop Wishlist -->
                <button class="hidden md:flex items-center gap-2 text-gray-400 hover:text-red-500 font-bold text-sm transition-colors uppercase tracking-wide">
                    <i class="far fa-heart text-lg"></i> Add to Wishlist
                </button>
            </div>

            <!-- Title -->
            <h1 class="text-2xl md:text-4xl font-black text-navy uppercase tracking-wide leading-tight mb-4"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <!-- Ratings (Dynamic) -->
            <div class="flex items-center gap-4 mb-6">
                <?php if ($totalReviews > 0): ?>
                    <div class="flex items-center text-yellow-400 text-sm">
                        <?php 
                        $fullStars = floor($avgRating);
                        $halfStar = ($avgRating - $fullStars) >= 0.5;
                        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                        
                        for($i=0; $i<$fullStars; $i++) echo '<i class="fas fa-star"></i> ';
                        if($halfStar) echo '<i class="fas fa-star-half-alt"></i> ';
                        for($i=0; $i<$emptyStars; $i++) echo '<i class="far fa-star"></i> ';
                        ?>
                    </div>
                    <a href="#reviewsSection" class="text-sm font-bold text-gray-500 hover:text-navy cursor-pointer transition-colors border-b border-gray-300 hover:border-navy"><?php echo $totalReviews; ?> Reviews</a>
                <?php else: ?>
                    <div class="flex items-center text-gray-300 text-sm">
                        <i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i>
                    </div>
                    <span class="text-sm font-bold text-gray-400">No reviews yet</span>
                <?php endif; ?>
            </div>

            <!-- Price -->
            <?php if ($isHotDealProduct): ?>
                <div class="mb-6 p-4 rounded-2xl bg-[#0A1222] border border-[#CCFF00]/40 shadow-lg text-white">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-[#CCFF00] text-black font-black text-xs uppercase tracking-wider">
                            <i class="fas fa-bolt animate-pulse"></i> -<?php echo $hotDealDiscount; ?>% HOT DEAL
                        </span>
                        <?php if($hotDealDaysLeft !== null): ?>
                            <span class="text-xs text-[#CCFF00] font-bold flex items-center gap-1">
                                <i class="fas fa-clock text-[10px]"></i>
                                <?php echo $hotDealDaysLeft > 1 ? "Ends in {$hotDealDaysLeft} days" : ($hotDealDaysLeft == 1 ? "Ends tomorrow" : "Ends today!"); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl md:text-4xl font-black text-[#CCFF00] tracking-tight" id="displayPrice">
                            Rs. <?php echo number_format($hotDealSalePrice, 0); ?>
                        </span>
                        <span class="text-lg font-bold text-gray-400 line-through">
                            Rs. <?php echo number_format($hotDealOrigPrice, 0); ?>
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-gray-400 flex items-center gap-2 border-t border-white/10 pt-2">
                        <span>Pay in 3 installments of Rs. <?php echo number_format($hotDealSalePrice / 3, 0); ?> with</span>
                        <img src="../image/KOKO_logo.png" class="h-3.5 w-auto rounded" alt="KOKO">
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-6 flex flex-col gap-1">
                    <div class="flex items-end gap-3">
                        <span class="text-3xl md:text-4xl font-black text-navy tracking-tight" id="displayPrice">
                            <?php if ($lowestPrice != $highestPrice): ?>
                                Rs. <?php echo number_format($lowestPrice, 0); ?> - <?php echo number_format($highestPrice, 0); ?>
                            <?php else: ?>
                                Rs. <?php echo number_format($lowestPrice, 0); ?>
                            <?php endif; ?>
                        </span>
                        <?php if(!empty($product['cost_price']) && $product['cost_price'] > $lowestPrice): ?>
                        <span class="text-lg font-bold text-gray-400 line-through mb-1">Rs. <?php echo number_format($product['cost_price'], 0); ?></span>
                        <?php endif; ?>
                    </div>
                    <!-- KOKO Pay -->
                    <div class="text-sm font-bold text-gray-500 flex items-center gap-2">
                        <span id="kokoText">Pay in 3 installments of Rs. <?php echo number_format($lowestPrice / 3, 0); ?> with</span>
                        <img src="../image/KOKO_logo.png" class="h-4 w-auto rounded" alt="KOKO">
                    </div>
                </div>
            <?php endif; ?>



            <!-- Variants Selection -->
            <?php if(!empty($variants)): ?>
            <div class="mb-8" id="variantsWrapper">
                <?php if($isTwoStep): ?>
                    <!-- Step 1: Colors -->
                    <div class="mb-6">
                        <div class="flex justify-between items-end mb-3">
                            <h3 class="text-sm font-black text-navy uppercase tracking-wide">Color: <span id="selectedColorLabel" class="text-gray-500 font-bold">Select Color</span></h3>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach($uniqueColors as $color): 
                                // Basic mapping for common colors to hex (can be expanded)
                                $hexMap = [
                                    'GREEN' => '#22c55e', 'PINK' => '#ec4899', 'BLACK' => '#000000', 'WHITE' => '#ffffff',
                                    'RED' => '#ef4444', 'BLUE' => '#3b82f6', 'YELLOW' => '#eab308', 'GRAY' => '#6b7280',
                                    'NAVY' => '#1e3a8a', 'ORANGE' => '#f97316', 'PURPLE' => '#a855f7'
                                ];
                                $upperColor = strtoupper($color);
                                $isStandardColor = isset($hexMap[$upperColor]);
                                $bgStyle = $isStandardColor ? "background-color: {$hexMap[$upperColor]};" : "";
                            ?>
                                <button type="button" 
                                        onclick="selectColor('<?php echo htmlspecialchars($color, ENT_QUOTES); ?>')"
                                        class="color-btn relative rounded-full border-2 border-gray-200 hover:border-navy transition-all duration-200 flex items-center justify-center overflow-hidden <?php echo $isStandardColor ? 'w-10 h-10' : 'px-4 py-2 text-sm font-bold bg-white text-navy'; ?>"
                                        data-color="<?php echo htmlspecialchars($color, ENT_QUOTES); ?>"
                                        style="<?php echo $bgStyle; ?>"
                                        title="<?php echo htmlspecialchars($color); ?>">
                                    <?php if(!$isStandardColor): ?>
                                        <?php echo htmlspecialchars($color); ?>
                                    <?php endif; ?>
                                    <i class="fas fa-check absolute text-white text-xs opacity-0 pointer-events-none transition-opacity duration-200 <?php echo $upperColor == 'WHITE' ? 'text-black' : ''; ?>"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Step 2: Sizes -->
                    <div class="mb-6 hidden" id="sizeContainerWrapper">
                        <div class="flex justify-between items-end mb-3">
                            <h3 class="text-sm font-black text-navy uppercase tracking-wide">Size: <span id="selectedSizeLabel" class="text-gray-500 font-bold">Select Size</span></h3>
                            <button type="button" onclick="document.getElementById('sizeGuideModal').classList.remove('hidden'); document.getElementById('sizeGuideModal').classList.add('flex')" class="text-xs font-bold text-gray-400 hover:text-primary underline"><i class="fas fa-ruler me-1"></i>Size Guide</button>
                        </div>
                        <div class="flex flex-wrap gap-2" id="sizeContainer">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Standard (1-Step) Variant Selector -->
                    <div class="flex justify-between items-end mb-3">
                        <h3 class="text-sm font-black text-navy uppercase tracking-wide">Select Variant</h3>
                    </div>
                    <div class="flex flex-wrap gap-3" id="variantContainer">
                        <?php 
                        foreach($variants as $index => $v): 
                            $labelParts = [];
                            if (!empty($v['size'])) $labelParts[] = htmlspecialchars($v['size']);
                            if (!empty($v['color'])) $labelParts[] = htmlspecialchars($v['color']);
                            if (!empty($v['flavor'])) $labelParts[] = htmlspecialchars($v['flavor']);
                            if (!empty($v['weight'])) $labelParts[] = htmlspecialchars($v['weight']);
                            $label = !empty($labelParts) ? implode(' - ', $labelParts) : 'Standard';
                            $vPrice = ($v['price'] > 0) ? $v['price'] : $product['base_price'];
                        ?>
                            <button type="button" 
                                    onclick="selectStandardVariant(this, <?php echo $v['id']; ?>, <?php echo $vPrice; ?>, <?php echo $v['qty']; ?>, '<?php echo $label; ?>')"
                                    class="variant-btn relative px-6 py-3 rounded-xl border-2 font-bold text-sm uppercase tracking-wider transition-all duration-200 <?php echo $v['qty'] <= 0 ? 'opacity-50 border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50' : 'border-gray-200 text-gray-600 hover:border-navy hover:text-navy cursor-pointer bg-white'; ?>">
                                <?php echo $label; ?>
                                <?php if($v['qty'] <= 0): ?>
                                    <svg class="absolute inset-0 w-full h-full text-gray-300" preserveAspectRatio="none" viewBox="0 0 100 100"><line x1="0" y1="100" x2="100" y2="0" stroke="currentColor" stroke-width="2"></line></svg>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <p id="stockMessage" class="mt-3 text-xs font-bold hidden flex items-center gap-1"></p>
            </div>
            <?php endif; ?>

            <!-- Description Accordion (Mobile friendly) -->
            <div class="mb-8 border-t border-b border-gray-100">
                <details class="group py-4" open>
                    <summary class="flex justify-between items-center font-black text-sm uppercase tracking-wide cursor-pointer list-none text-navy">
                        Product Details
                        <span class="transition group-open:rotate-180">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="text-gray-600 text-sm font-medium mt-4 leading-relaxed whitespace-pre-line prose prose-sm">
                        <?php echo htmlspecialchars($product['description']); ?>
                    </div>
                </details>
                <details class="group py-4 border-t border-gray-100">
                    <summary class="flex justify-between items-center font-black text-sm uppercase tracking-wide cursor-pointer list-none text-navy">
                        Shipping & Returns
                        <span class="transition group-open:rotate-180">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="text-gray-600 text-sm font-medium mt-4 leading-relaxed">
                        <ul class="list-disc pl-5 space-y-2">
                            <li>Islandwide Delivery available (2-4 working days)</li>
                            <li>Free shipping on orders over Rs. 15,000</li>
                            <li>14-day return policy for unused items in original packaging</li>
                        </ul>
                    </div>
                </details>
            </div>

            <!-- Desktop Add to Cart (Hidden on Mobile) -->
            <div class="hidden md:flex flex-col gap-4">
                <div class="flex gap-4 h-14">
                    <!-- Quantity Selector -->
                    <div class="flex items-center bg-gray-50 rounded-xl border border-gray-200 px-2 flex-shrink-0">
                        <button type="button" onclick="updateQty(-1)" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-navy transition-colors font-bold"><i class="fas fa-minus text-xs"></i></button>
                        <input type="number" id="qtyInputDesktop" value="1" min="1" max="<?php echo $totalStock; ?>" class="w-12 h-10 bg-transparent text-center text-navy font-black focus:outline-none" readonly>
                        <button type="button" onclick="updateQty(1)" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-navy transition-colors font-bold"><i class="fas fa-plus text-xs"></i></button>
                    </div>
                    
                    <button id="addToCartDesktopBtn" onclick="addToCart(this)" class="flex-1 bg-navy hover:bg-black text-white rounded-xl font-black uppercase tracking-widest text-sm shadow-xl shadow-black/10 transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-shopping-bag"></i> <span>Add to Cart</span>
                </button>
            </div>
            <button id="buyNowDesktopBtn" onclick="buyNow(this)" class="w-full h-14 bg-primary hover:bg-blue-700 text-white rounded-xl font-black uppercase tracking-widest text-sm shadow-xl shadow-blue-500/30 transition-all flex items-center justify-center">
                <span>Buy It Now</span>
            </button>
            
            <!-- Trust Badges -->
            <div class="flex items-center justify-center gap-4 md:gap-6 mt-8 py-6 border-y border-gray-100 text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-wider text-center">
                <div class="flex flex-col items-center gap-2"><i class="fas fa-truck text-navy text-xl"></i> <span>Islandwide<br>Delivery</span></div>
                <div class="w-px h-10 bg-gray-200"></div>
                <div class="flex flex-col items-center gap-2"><i class="fas fa-check-circle text-navy text-xl"></i> <span>100%<br>Authentic</span></div>
                <div class="w-px h-10 bg-gray-200"></div>
                <div class="flex flex-col items-center gap-2"><i class="fas fa-undo text-navy text-xl"></i> <span>14-Day<br>Returns</span></div>
            </div>
        </div>
    </div>
    <!-- Related Products -->
    <?php if(!empty($relatedProducts)): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 border-t border-gray-100">
        <h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-8">You May Also Like</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">
            <?php foreach($relatedProducts as $relProduct): 
                // Fetch primary image for related
                $relImgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC LIMIT 1");
                $relImgStmt->execute([$relProduct['id']]);
                $relImg = $relImgStmt->fetchColumn();
            ?>
            <a href="product-details.php?id=<?php echo $relProduct['id']; ?>" class="group block">
                <div class="aspect-square bg-[#F8F9FA] rounded-2xl mb-4 overflow-hidden p-4 md:p-6 flex items-center justify-center">
                    <?php if($relImg): ?>
                        <img src="../assets/uploads/products/<?php echo htmlspecialchars($relImg); ?>" class="w-full h-full object-contain mix-blend-multiply group-hover:scale-110 transition-transform duration-500">
                    <?php else: ?>
                        <i class="fas fa-image text-4xl text-gray-300"></i>
                    <?php endif; ?>
                </div>
                <h3 class="text-sm font-black text-navy uppercase tracking-wide truncate"><?php echo htmlspecialchars($relProduct['name']); ?></h3>
                <div class="text-sm font-bold text-gray-500 mt-1">Rs. <?php echo number_format($relProduct['base_price'], 0); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Size Guide Modal -->
<div id="sizeGuideModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="document.getElementById('sizeGuideModal').classList.add('hidden'); document.getElementById('sizeGuideModal').classList.remove('flex');"></div>
    <div class="bg-white rounded-[2rem] w-full max-w-2xl relative z-10 overflow-hidden shadow-2xl">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 class="text-xl font-black text-navy uppercase tracking-widest">Size Guide</h3>
            <button onclick="document.getElementById('sizeGuideModal').classList.add('hidden'); document.getElementById('sizeGuideModal').classList.remove('flex');" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 md:p-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm">
                <table class="w-full text-sm text-center">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50/80 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 font-black tracking-widest text-navy w-1/6">US</th>
                            <th class="px-4 py-3 font-bold w-1/6">UK</th>
                            <th class="px-4 py-3 font-bold w-1/6">EU</th>
                            <th class="px-4 py-3 font-bold w-1/6">CM</th>
                            <th class="px-4 py-3 font-bold w-1/6">CN</th>
                            <th class="px-4 py-3 font-bold w-1/6">JP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-white hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">5</td><td class="px-4 py-2 text-gray-600 font-medium">4</td><td class="px-4 py-2 text-gray-600 font-medium">37.5</td><td class="px-4 py-2 text-gray-600 font-medium">23.5</td><td class="px-4 py-2 text-gray-600 font-medium">235</td><td class="px-4 py-2 text-gray-600 font-medium">23.5</td></tr>
                        <tr class="bg-gray-50/30 hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">5.5</td><td class="px-4 py-2 text-gray-600 font-medium">4.5</td><td class="px-4 py-2 text-gray-600 font-medium">38</td><td class="px-4 py-2 text-gray-600 font-medium">24</td><td class="px-4 py-2 text-gray-600 font-medium">240</td><td class="px-4 py-2 text-gray-600 font-medium">24</td></tr>
                        <tr class="bg-white hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">6</td><td class="px-4 py-2 text-gray-600 font-medium">5</td><td class="px-4 py-2 text-gray-600 font-medium">38.5</td><td class="px-4 py-2 text-gray-600 font-medium">24</td><td class="px-4 py-2 text-gray-600 font-medium">240</td><td class="px-4 py-2 text-gray-600 font-medium">24</td></tr>
                        <tr class="bg-gray-50/30 hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">6.5</td><td class="px-4 py-2 text-gray-600 font-medium">5.5</td><td class="px-4 py-2 text-gray-600 font-medium">39</td><td class="px-4 py-2 text-gray-600 font-medium">24.5</td><td class="px-4 py-2 text-gray-600 font-medium">245</td><td class="px-4 py-2 text-gray-600 font-medium">24.5</td></tr>
                        <tr class="bg-white hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">7</td><td class="px-4 py-2 text-gray-600 font-medium">6</td><td class="px-4 py-2 text-gray-600 font-medium">40</td><td class="px-4 py-2 text-gray-600 font-medium">25</td><td class="px-4 py-2 text-gray-600 font-medium">250</td><td class="px-4 py-2 text-gray-600 font-medium">25</td></tr>
                        <tr class="bg-gray-50/30 hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">7.5</td><td class="px-4 py-2 text-gray-600 font-medium">6.5</td><td class="px-4 py-2 text-gray-600 font-medium">40.5</td><td class="px-4 py-2 text-gray-600 font-medium">25.5</td><td class="px-4 py-2 text-gray-600 font-medium">255</td><td class="px-4 py-2 text-gray-600 font-medium">25.5</td></tr>
                        <tr class="bg-white hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">8</td><td class="px-4 py-2 text-gray-600 font-medium">7</td><td class="px-4 py-2 text-gray-600 font-medium">41</td><td class="px-4 py-2 text-gray-600 font-medium">26</td><td class="px-4 py-2 text-gray-600 font-medium">260</td><td class="px-4 py-2 text-gray-600 font-medium">26</td></tr>
                        <tr class="bg-gray-50/30 hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">8.5</td><td class="px-4 py-2 text-gray-600 font-medium">7.5</td><td class="px-4 py-2 text-gray-600 font-medium">42</td><td class="px-4 py-2 text-gray-600 font-medium">26.5</td><td class="px-4 py-2 text-gray-600 font-medium">265</td><td class="px-4 py-2 text-gray-600 font-medium">26.5</td></tr>
                        <tr class="bg-white hover:bg-gray-50/80 transition-colors"><td class="px-4 py-2 font-black text-navy">9</td><td class="px-4 py-2 text-gray-600 font-medium">8</td><td class="px-4 py-2 text-gray-600 font-medium">42.5</td><td class="px-4 py-2 text-gray-600 font-medium">27</td><td class="px-4 py-2 text-gray-600 font-medium">270</td><td class="px-4 py-2 text-gray-600 font-medium">27</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- Fullscreen Image Modal -->
<div id="imageZoomModal" class="fixed inset-0 z-[100] hidden bg-black/90 items-center justify-center backdrop-blur-sm">
    <button onclick="document.getElementById('imageZoomModal').classList.add('hidden'); document.getElementById('imageZoomModal').classList.remove('flex');" class="absolute top-6 right-6 w-12 h-12 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-white transition-colors"><i class="fas fa-times text-xl"></i></button>
    <div class="w-full max-w-5xl p-4">
        <img id="zoomedImage" src="" class="w-full h-auto max-h-[90vh] object-contain">
    </div>
</div>

<?php if($canReview): ?>
<!-- Write Review Modal -->
<div id="reviewModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeReviewModal()"></div>
    <div class="bg-white rounded-[2rem] w-full max-w-xl relative z-10 overflow-hidden shadow-2xl">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 class="text-xl font-black text-navy uppercase tracking-widest">Write a Review</h3>
            <button onclick="closeReviewModal()" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <form id="reviewForm" onsubmit="submitReview(event)">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <input type="hidden" name="order_id" value="<?php echo $purchasedOrderId; ?>">
                
                <div class="mb-4 text-center">
                    <p class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-2">Overall Rating</p>
                    <div class="flex justify-center text-gray-300 text-3xl cursor-pointer" id="starRating">
                        <i class="fas fa-star hover:text-yellow-400" data-val="1"></i>
                        <i class="fas fa-star hover:text-yellow-400" data-val="2"></i>
                        <i class="fas fa-star hover:text-yellow-400" data-val="3"></i>
                        <i class="fas fa-star hover:text-yellow-400" data-val="4"></i>
                        <i class="fas fa-star hover:text-yellow-400" data-val="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Title (Optional)</label>
                    <input type="text" name="title" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#0066FF] focus:ring focus:ring-blue-200" placeholder="Summary of your review">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Review</label>
                    <textarea name="comment" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#0066FF] focus:ring focus:ring-blue-200" rows="4" required placeholder="What did you like or dislike?"></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">How did it fit?</label>
                    <select name="fit_feedback" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#0066FF] focus:ring focus:ring-blue-200">
                        <option value="True to Size">True to Size</option>
                        <option value="Runs Small">Runs Small</option>
                        <option value="Runs Large">Runs Large</option>
                    </select>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center text-sm text-gray-600 font-bold">
                        <input type="checkbox" name="is_anonymous" value="1" class="rounded text-[#0066FF] focus:ring-[#0066FF] mr-2"> Post anonymously
                    </label>
                    <button type="submit" class="bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-2 rounded-xl font-bold uppercase tracking-widest shadow-md transition-colors" id="submitReviewBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Mobile Sticky Bottom Bar -->
<div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-4 z-50 shadow-[0_-4px_20px_rgba(0,0,0,0.05)] flex items-center gap-4">
    <div class="flex-1 min-w-0">
        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest truncate mb-0.5"><?php echo htmlspecialchars($product['name']); ?></div>
        <div class="text-lg font-black text-navy truncate" id="mobileStickyPrice">Rs. <?php echo number_format($lowestPrice, 0); ?></div>
    </div>
    <button id="addToCartMobileBtn" onclick="addToCart(this)" class="bg-navy hover:bg-black text-white px-6 py-3 rounded-xl font-black uppercase tracking-widest text-sm shadow-lg shadow-black/10 shrink-0 flex items-center gap-2"><span>Add to Cart</span></button>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
    // Initialize Swiper
    const productSwiper = new Swiper('.productSwiper', {
        loop: true,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        }
    });

    let variantsData = <?php echo json_encode($variantsJsonData ?? []); ?>;
    let selectedColor = null;
    let selectedVariantId = null;
    let selectedSize = 'Standard';
    let currentPrice = <?php echo $lowestPrice; ?>;
    let maxQty = <?php echo $totalStock; ?>;
    let currentQty = 1;

    function selectColor(color) {
        selectedColor = color;
        document.getElementById('selectedColorLabel').innerText = color;
        document.getElementById('selectedColorLabel').classList.remove('text-gray-500');
        document.getElementById('selectedColorLabel').classList.add('text-navy');
        
        // Update Color Buttons UI
        document.querySelectorAll('.color-btn').forEach(btn => {
            const check = btn.querySelector('.fa-check');
            if (btn.getAttribute('data-color') === color) {
                btn.classList.add('ring-2', 'ring-navy', 'ring-offset-2');
                if(check) check.classList.remove('opacity-0');
            } else {
                btn.classList.remove('ring-2', 'ring-navy', 'ring-offset-2');
                if(check) check.classList.add('opacity-0');
            }
        });

        // Populate Sizes
        const sizeContainer = document.getElementById('sizeContainer');
        if(!sizeContainer) return;
        
        sizeContainer.innerHTML = '';
        document.getElementById('sizeContainerWrapper').classList.remove('hidden');
        
        let colorVariants = variantsData.filter(v => v.color === color);
        
        colorVariants.forEach(v => {
            const isOutOfStock = v.qty <= 0;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `size-btn relative px-5 py-2.5 rounded-xl border-2 font-bold text-sm uppercase tracking-wider transition-all duration-200 ${isOutOfStock ? 'opacity-50 border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50 line-through' : 'border-gray-200 text-gray-600 hover:border-navy hover:text-navy cursor-pointer bg-white'}`;
            btn.innerText = v.size || 'STD';
            if(!isOutOfStock) {
                btn.onclick = () => selectSize(v.id, v.size, v.price, v.qty, btn);
            }
            sizeContainer.appendChild(btn);
        });

        // Reset size selection
        selectedSize = null;
        selectedVariantId = null;
        document.getElementById('selectedSizeLabel').innerText = 'Select Size';
        document.getElementById('selectedSizeLabel').classList.remove('text-navy');
        document.getElementById('selectedSizeLabel').classList.add('text-gray-500');
        document.getElementById('stockMessage').classList.add('hidden');
        
        // Auto-select first available size
        const firstAvailable = sizeContainer.querySelector('.size-btn:not(.cursor-not-allowed)');
        if(firstAvailable) {
            firstAvailable.click();
        }
    }

    function selectSize(id, size, price, qty, btnElement) {
        selectedVariantId = id;
        selectedSize = size;
        currentPrice = price;
        maxQty = qty;
        
        document.getElementById('selectedSizeLabel').innerText = size;
        document.getElementById('selectedSizeLabel').classList.remove('text-gray-500');
        document.getElementById('selectedSizeLabel').classList.add('text-navy');

        // Update Size Buttons UI
        document.querySelectorAll('.size-btn').forEach(b => {
            if(!b.classList.contains('cursor-not-allowed')) {
                b.classList.remove('border-navy', 'text-navy', 'bg-navy', 'text-white');
                b.classList.add('border-gray-200', 'text-gray-600', 'bg-white');
            }
        });
        btnElement.classList.remove('border-gray-200', 'text-gray-600', 'bg-white');
        btnElement.classList.add('border-navy', 'text-white', 'bg-navy');

        updatePricingUI(price, qty);
    }

    function selectStandardVariant(btn, id, price, qty, label) {
        if (qty <= 0) return;
        selectedVariantId = id;
        selectedSize = label; 
        currentPrice = price;
        maxQty = qty;

        document.querySelectorAll('.variant-btn').forEach(b => {
            if(!b.classList.contains('cursor-not-allowed')) {
                b.classList.remove('border-navy', 'text-white', 'bg-navy');
                b.classList.add('border-gray-200', 'text-gray-600', 'bg-white');
            }
        });
        btn.classList.remove('border-gray-200', 'text-gray-600', 'bg-white');
        btn.classList.add('border-navy', 'text-white', 'bg-navy');

        updatePricingUI(price, qty);
    }

    function updatePricingUI(price, qty) {
        if(currentQty > maxQty) {
            currentQty = maxQty;
            updateQtyInputs();
        }

        const formattedPrice = Number(price).toLocaleString('en-US', {minimumFractionDigits: 0});
        document.getElementById('displayPrice').innerText = 'Rs. ' + formattedPrice;
        if(document.getElementById('mobileStickyPrice')) {
            document.getElementById('mobileStickyPrice').innerText = 'Rs. ' + formattedPrice;
        }
        
        const kokoAmount = Math.round(price / 3).toLocaleString('en-US');
        document.getElementById('kokoText').innerText = `Pay in 3 installments of Rs. ${kokoAmount} with`;

        const stockMsg = document.getElementById('stockMessage');
        if(qty < 5) {
            stockMsg.innerHTML = `<i class="fas fa-exclamation-circle text-orange-500"></i> <span class="text-orange-500">Only ${qty} left in this size</span>`;
            stockMsg.classList.remove('hidden');
        } else {
            stockMsg.innerHTML = `<i class="fas fa-check-circle text-green-500"></i> <span class="text-green-500">In Stock</span>`;
            stockMsg.classList.remove('hidden');
        }
    }

    function updateQty(change) {
        let newQty = currentQty + change;
        if(newQty >= 1 && newQty <= maxQty) {
            currentQty = newQty;
            updateQtyInputs();
        } else if (newQty > maxQty) {
            Swal.fire({
                icon: 'warning',
                title: 'Stock Limit Reached',
                text: `Only ${maxQty} items available in this size.`,
                confirmButtonColor: '#0066FF'
            });
        }
    }

    function updateQtyInputs() {
        if(document.getElementById('qtyInputDesktop')) document.getElementById('qtyInputDesktop').value = currentQty;
        if(document.getElementById('qtyInputMobile')) document.getElementById('qtyInputMobile').value = currentQty;
    }

    // Auto-select first available variant on load
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($isTwoStep): ?>
            const firstColorBtn = document.querySelector('.color-btn');
            if(firstColorBtn) firstColorBtn.click();
        <?php else: ?>
            const firstAvailableBtn = document.querySelector('.variant-btn:not(.cursor-not-allowed)');
            if(firstAvailableBtn) firstAvailableBtn.click();
        <?php endif; ?>
        
        // Adjust footer padding to account for sticky bottom bar on mobile
        if(window.innerWidth < 768) {
            const footer = document.querySelector('footer');
            if(footer) footer.style.paddingBottom = '80px';
        }
    });

    function validateSelection() {
        <?php if(!empty($variants)): ?>
        <?php if($isTwoStep): ?>
        if(!selectedColor) {
            const container = document.getElementById('selectedColorLabel').parentElement.nextElementSibling;
            container.classList.add('animate-[shake_0.5s_ease-in-out]');
            container.querySelectorAll('button').forEach(b => b.classList.add('border-red-500'));
            setTimeout(() => {
                container.classList.remove('animate-[shake_0.5s_ease-in-out]');
                container.querySelectorAll('button').forEach(b => b.classList.remove('border-red-500'));
            }, 500);
            return 'Please select a color';
        }
        <?php endif; ?>
        if(!selectedSize && selectedSize !== 'Standard') {
            const container = document.getElementById('sizeContainer') || document.getElementById('variantContainer');
            container.classList.add('animate-[shake_0.5s_ease-in-out]');
            container.querySelectorAll('button:not(.cursor-not-allowed)').forEach(b => b.classList.add('border-red-500'));
            setTimeout(() => {
                container.classList.remove('animate-[shake_0.5s_ease-in-out]');
                container.querySelectorAll('button:not(.cursor-not-allowed)').forEach(b => b.classList.remove('border-red-500'));
            }, 500);
            return 'Please select a variant';
        }
        <?php endif; ?>
        return null;
    }

    function addToCart(btn) {
        const error = validateSelection();
        if (error) {
            Swal.fire({
                icon: 'warning',
                title: error,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            return;
        }

        const productId = <?php echo $product_id; ?>;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>ADDING...</span>';
        btn.disabled = true;

        if (typeof CartManager !== 'undefined' && CartManager.addToCart) {
            CartManager.addToCart(productId, selectedVariantId || null, currentQty, btn).finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
            return;
        }
    }

    function buyNow(btn) {
        const error = validateSelection();
        if (error) {
            Swal.fire({
                icon: 'warning',
                title: error,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            return;
        }
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>REDIRECTING...</span>';
        btn.disabled = true;
        
        window.location.href = `checkout.php?buy_now=${selectedVariantId || '0'}&qty=${currentQty}`;
    }
    // --- Review Form Logic ---
    function openReviewModal() {
        document.getElementById('reviewModal').classList.remove('hidden');
        document.getElementById('reviewModal').classList.add('flex');
    }
    
    function closeReviewModal() {
        document.getElementById('reviewModal').classList.add('hidden');
        document.getElementById('reviewModal').classList.remove('flex');
    }

    // Star Rating UI
    const stars = document.querySelectorAll('#starRating .fa-star');
    const ratingInput = document.getElementById('ratingInput');
    
    if(stars.length > 0) {
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const val = this.getAttribute('data-val');
                ratingInput.value = val;
                stars.forEach(s => {
                    if(s.getAttribute('data-val') <= val) {
                        s.classList.remove('text-gray-300');
                        s.classList.add('text-yellow-400');
                    } else {
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });
            });
        });
    }

    function submitReview(e) {
        e.preventDefault();
        
        if(!ratingInput.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Please select a rating',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            return;
        }
        
        const btn = document.getElementById('submitReviewBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        const formData = new FormData(e.target);
        
        fetch('../Backend/submit-review.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Review submitted successfully!',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                setTimeout(() => window.location.reload(), 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: data.message || 'Error submitting review',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                btn.disabled = false;
                btn.innerHTML = 'Submit';
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Server error',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            btn.disabled = false;
            btn.innerHTML = 'Submit';
        });
    }

</script>

<style>
    /* Swiper Pagination Styling */
    .productSwiper .swiper-pagination-bullet {
        background: #D1D5DB;
        opacity: 1;
        width: 6px;
        height: 6px;
        transition: all 0.3s ease;
    }
    .productSwiper .swiper-pagination-bullet-active {
        background: #0066FF;
        width: 24px;
        border-radius: 4px;
    }
    /* Safe area padding for bottom bar on iPhones */
    .pb-safe {
        padding-bottom: env(safe-area-inset-bottom, 1rem) !important;
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    .animate-\\[shake_0\\.5s_ease-in-out\\] {
        animation: shake 0.5s ease-in-out;
    }
</style>

<?php include('../include/footer.php'); ?>
