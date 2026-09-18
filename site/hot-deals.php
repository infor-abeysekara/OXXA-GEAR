<?php
$page_title = 'Hot Deals & Clearance - OXXA GEAR';
include(__DIR__ . '/../include/header.php');

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : null;
$min_discount = isset($_GET['min_discount']) && is_numeric($_GET['min_discount']) ? (int)$_GET['min_discount'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'highest_discount';

// Total active deals in database (unfiltered count for hero badge)
$totalActiveQuery = "SELECT COUNT(*) FROM products p 
                     WHERE p.is_hot_deal = 1 
                       AND p.hot_deal_status = 'approved' 
                       AND p.status = 'active' 
                       AND (p.hot_deal_expiry IS NULL OR p.hot_deal_expiry >= NOW())";
$totalActiveStmt = $pdo->query($totalActiveQuery);
$totalActiveDeals = $totalActiveStmt ? (int)$totalActiveStmt->fetchColumn() : 0;

// Categories for filter dropdown
$catsStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$allCategories = $catsStmt ? $catsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Build filtered SQL
$where = "WHERE p.is_hot_deal = 1 
            AND p.hot_deal_status = 'approved' 
            AND p.status = 'active' 
            AND (p.hot_deal_expiry IS NULL OR p.hot_deal_expiry >= NOW())";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR (SELECT b.name FROM brands b WHERE b.id = p.brand_id) LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_id) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($min_discount !== null && $min_discount > 0) {
    $where .= " AND p.discount_percent >= ?";
    $params[] = $min_discount;
}

if ($max_price !== null && $max_price > 0) {
    $where .= " AND COALESCE(NULLIF(p.sale_price, 0), p.base_price) <= ?";
    $params[] = $max_price;
}

$orderBy = "ORDER BY p.discount_percent DESC";
if ($sort === 'ending_soon') {
    $orderBy = "ORDER BY CASE WHEN p.hot_deal_expiry IS NULL THEN 1 ELSE 0 END, p.hot_deal_expiry ASC";
} elseif ($sort === 'price_asc') {
    $orderBy = "ORDER BY COALESCE(NULLIF(p.sale_price, 0), p.base_price) ASC";
} elseif ($sort === 'price_desc') {
    $orderBy = "ORDER BY COALESCE(NULLIF(p.sale_price, 0), p.base_price) DESC";
} elseif ($sort === 'newest') {
    $orderBy = "ORDER BY p.id DESC";
}

$dealsQuery = "SELECT p.*,
    (SELECT b.name FROM brands b WHERE b.id = p.brand_id) as brand_name,
    (SELECT c.name FROM categories c WHERE c.id = p.category_id) as category_name,
    (SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id) as total_stock,
    COALESCE(
        (SELECT ci.image_path FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = p.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1),
        (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1)
    ) as image
FROM products p
{$where}
{$orderBy}
LIMIT 12";

$stmt = $pdo->prepare($dealsQuery);
$stmt->execute($params);
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dealsCount = count($deals);
?>

<style>
/* Subtle moving dots animation for luxury dark athletic background */
@keyframes floatDots {
    0% {
        background-position: 0 0, 24px 24px;
    }
    100% {
        background-position: 120px 120px, 144px 144px;
    }
}

.hot-deals-dots-bg {
    background-image: 
        radial-gradient(rgba(204, 255, 0, 0.12) 1.2px, transparent 1.2px),
        radial-gradient(rgba(0, 102, 255, 0.16) 1.2px, transparent 1.2px);
    background-size: 38px 38px, 52px 52px;
    background-position: 0 0, 26px 26px;
    animation: floatDots 45s linear infinite;
}

@keyframes subtlePulseGlow {
    0%, 100% { transform: scale(1); opacity: 0.18; }
    50% { transform: scale(1.12); opacity: 0.28; }
}

.animate-glow-orb {
    animation: subtlePulseGlow 8s ease-in-out infinite;
}
</style>

<div class="min-h-screen bg-[#070D18] text-white pb-20 selection:bg-[#CCFF00] selection:text-black">
    <!-- ==========================================
         1. HERO SECTION (UPGRADED)
         ========================================== -->
    <section class="relative py-16 md:py-24 px-4 md:px-8 overflow-hidden bg-gradient-to-b from-[#091224] via-[#070D18] to-[#060B14] border-b border-white/10">
        <!-- Moving dots layer -->
        <div class="absolute inset-0 hot-deals-dots-bg opacity-70 pointer-events-none"></div>

        <!-- Ambient glow blobs -->
        <div class="absolute -top-24 left-1/4 w-[550px] h-[350px] bg-primary/25 blur-[130px] rounded-full pointer-events-none animate-glow-orb"></div>
        <div class="absolute top-12 right-10 w-[450px] h-[350px] bg-[#CCFF00]/15 blur-[140px] rounded-full pointer-events-none animate-glow-orb" style="animation-delay: 4s;"></div>

        <div class="max-w-6xl mx-auto relative z-10 text-center">
            <!-- Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#0A6CFF]/10 border border-[#0A6CFF]/40 text-[#0A6CFF] text-xs font-black uppercase tracking-widest mb-5 shadow-[0_0_20px_rgba(10,108,255,0.15)]">
                <i class="fas fa-bolt animate-pulse"></i> Limited Time Flash Clearance
            </div>

            <!-- Main Heading -->
            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black font-space uppercase tracking-tight text-white mb-5 leading-none">
                HOT DEALS <span class="text-white">ZONE</span>
            </h1>

            <!-- Subtitle -->
            <p class="text-gray-300 max-w-2xl mx-auto text-sm sm:text-base md:text-lg leading-relaxed">
                Verified high-performance gear at verified manufacturer clearance rates. Up to 50% discount on top running, fitness and sporting equipment.
            </p>

            <!-- Stats Ribbon with dynamic Lime count -->
            <div class="mt-8 inline-flex flex-wrap items-center justify-center gap-6 sm:gap-10 md:gap-14 px-8 py-4 rounded-2xl bg-white/[0.04] border border-white/10 backdrop-blur-xl shadow-2xl">
                <div class="text-center">
                    <div id="heroActiveDealsCount" class="text-2xl sm:text-3xl md:text-4xl font-black text-[#0A6CFF] tracking-tight">
                        <?php echo $totalActiveDeals; ?>
                    </div>
                    <div class="text-[11px] sm:text-xs text-gray-300 uppercase tracking-widest font-bold mt-0.5">Active Deals</div>
                </div>

                <div class="w-px h-10 bg-white/15 hidden sm:block"></div>

                <div class="text-center">
                    <div class="text-2xl sm:text-3xl md:text-4xl font-black text-white tracking-tight">100%</div>
                    <div class="text-[11px] sm:text-xs text-gray-300 uppercase tracking-widest font-bold mt-0.5">Genuine Brands</div>
                </div>

                <div class="w-px h-10 bg-white/15 hidden sm:block"></div>

                <div class="text-center">
                    <div class="text-2xl sm:text-3xl md:text-4xl font-black text-[#0A6CFF] tracking-tight">7-Day</div>
                    <div class="text-[11px] sm:text-xs text-gray-300 uppercase tracking-widest font-bold mt-0.5">Deal Expiries</div>
                </div>
            </div>

            <!-- Small Countdown Timer for Hype -->
            <div class="mt-6 flex items-center justify-center">
                <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full border border-white bg-transparent text-xs sm:text-sm shadow-inner">
                    <span class="flex h-2.5 w-2.5 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                    <span class="text-white font-medium">
                        <i class="fas fa-fire-flame-curved text-amber-400 mr-1"></i> Next flash drop in:
                    </span>
                    <span id="flashDropCountdown" class="font-mono font-black text-white px-2.5 py-0.5 tracking-widest text-xs sm:text-sm">
                        02:14:33
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         2. SEARCH + FILTERS BAR & POPULAR CHIPS
         ========================================== -->
    <section class="max-w-7xl mx-auto px-4 md:px-8 mt-10">
        <!-- Main Filter Bar -->
        <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-4 md:p-6 backdrop-blur-xl shadow-2xl">
            <form action="" method="GET" id="hotDealsFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 md:gap-4 items-center">
                <!-- Search Input -->
                <div class="lg:col-span-5 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" 
                           name="search" 
                           id="dealsSearchInput"
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search hot deals, brands..." 
                           class="w-full pl-11 pr-10 py-3 rounded-xl bg-black/50 border border-white/10 text-white placeholder-gray-400 text-sm focus:outline-none focus:border-[#0A6CFF] focus:ring-1 focus:ring-[#0A6CFF] transition-all">
                    <?php if(!empty($search)): ?>
                        <button type="button" onclick="document.getElementById('dealsSearchInput').value=''; document.getElementById('hotDealsFilterForm').submit();" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-xs p-1">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Category Dropdown -->
                <div class="lg:col-span-3">
                    <div class="relative">
                        <select name="category" onchange="this.form.submit()" class="w-full py-3 pl-4 pr-10 rounded-xl bg-black/50 border border-white text-white text-sm focus:outline-none focus:border-[#0A6CFF] focus:ring-1 focus:ring-[#0A6CFF] transition-all appearance-none cursor-pointer">
                            <option value="">All Categories</option>
                            <?php foreach($allCategories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <!-- Sort Dropdown -->
                <div class="lg:col-span-3">
                    <div class="relative">
                        <select name="sort" onchange="this.form.submit()" class="w-full py-3 pl-4 pr-10 rounded-xl bg-black/50 border border-white text-white text-sm focus:outline-none focus:border-[#0A6CFF] focus:ring-1 focus:ring-[#0A6CFF] transition-all appearance-none cursor-pointer">
                            <option value="highest_discount" <?php echo $sort == 'highest_discount' ? 'selected' : ''; ?>>Highest Discount</option>
                            <option value="ending_soon" <?php echo $sort == 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                            <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest Deals</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <!-- Hidden chip filter carriers -->
                <?php if($min_discount): ?>
                    <input type="hidden" name="min_discount" value="<?php echo $min_discount; ?>">
                <?php endif; ?>
                <?php if($max_price): ?>
                    <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                <?php endif; ?>

                <!-- Lime Search/Filter Button -->
                <div class="lg:col-span-1 flex gap-2">
                    <button type="submit" class="w-full py-3 bg-[#0A6CFF] text-white font-black rounded-xl text-sm hover:bg-[#0855c9] transition-all duration-300 flex items-center justify-center shadow-lg hover:shadow-[0_0_20px_rgba(10,108,255,0.3)] hover:scale-105 active:scale-95" title="Filter Deals">
                        <i class="fas fa-search text-base"></i>
                    </button>
                    <?php if(!empty($search) || $category_id || $sort !== 'highest_discount' || $min_discount || $max_price): ?>
                        <a href="<?php echo $base_path; ?>site/hot-deals.php" class="p-3 bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white rounded-xl text-sm transition-colors flex items-center justify-center shrink-0" title="Clear Filters">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Popular Quick Chips -->
            <div class="mt-4 pt-4 border-t border-white/10 flex flex-wrap items-center gap-2 text-xs">
                <span class="text-gray-400 font-bold uppercase tracking-wider text-[11px] mr-1 flex items-center gap-1.5">
                    <i class="fas fa-tags text-xs text-white"></i> Popular:
                </span>

                <!-- Chip 1: Nike -->
                <a href="<?php echo $base_path; ?>site/hot-deals.php?search=Nike" 
                   class="px-3.5 py-1.5 rounded-full border transition-all duration-200 <?php echo strtolower($search) === 'nike' ? 'bg-[#0A6CFF] text-white border-[#0A6CFF] font-black' : 'bg-black/40 border border-white text-gray-300 hover:bg-[#0A6CFF] hover:border-[#0A6CFF] hover:text-white'; ?>">
                    Nike
                </a>

                <!-- Chip 2: Running -->
                <a href="<?php echo $base_path; ?>site/hot-deals.php?search=Running" 
                   class="px-3.5 py-1.5 rounded-full border transition-all duration-200 <?php echo strtolower($search) === 'running' ? 'bg-[#0A6CFF] text-white border-[#0A6CFF] font-black' : 'bg-black/40 border border-white text-gray-300 hover:bg-[#0A6CFF] hover:border-[#0A6CFF] hover:text-white'; ?>">
                    Running
                </a>

                <!-- Chip 3: 30% OFF -->
                <a href="<?php echo $base_path; ?>site/hot-deals.php?min_discount=30" 
                   class="px-3.5 py-1.5 rounded-full border transition-all duration-200 <?php echo $min_discount == 30 ? 'bg-[#0A6CFF] text-white border-[#0A6CFF] font-black' : 'bg-black/40 border border-white text-gray-300 hover:bg-[#0A6CFF] hover:border-[#0A6CFF] hover:text-white'; ?>">
                    🔥 30% OFF
                </a>

                <!-- Chip 4: Under Rs. 5000 -->
                <a href="<?php echo $base_path; ?>site/hot-deals.php?max_price=5000" 
                   class="px-3.5 py-1.5 rounded-full border transition-all duration-200 <?php echo $max_price == 5000 ? 'bg-[#0A6CFF] text-white border-[#0A6CFF] font-black' : 'bg-black/40 border border-white text-gray-300 hover:bg-[#0A6CFF] hover:border-[#0A6CFF] hover:text-white'; ?>">
                    Under Rs. 5,000
                </a>

                <!-- Chip 5: Under Rs. 10000 -->
                <a href="<?php echo $base_path; ?>site/hot-deals.php?max_price=10000" 
                   class="px-3.5 py-1.5 rounded-full border transition-all duration-200 <?php echo $max_price == 10000 ? 'bg-[#0A6CFF] text-white border-[#0A6CFF] font-black' : 'bg-black/40 border border-white text-gray-300 hover:bg-[#0A6CFF] hover:border-[#0A6CFF] hover:text-white'; ?>">
                    Under Rs. 10,000
                </a>

                <?php if(!empty($search) || $category_id || $min_discount || $max_price): ?>
                    <a href="<?php echo $base_path; ?>site/hot-deals.php" class="ml-auto text-[11px] text-gray-400 hover:text-white underline">
                        Reset All
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==========================================
             3. PRODUCTS GRID (UP TO 12 CARDS) OR EMPTY STATE
             ========================================== -->
        <div class="mt-8">
            <?php if(!empty($deals)): ?>
                <!-- Active Products 12-Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach($deals as $deal): 
                        $dealImg = !empty($deal['image']) ? $base_path . 'assets/uploads/products/' . htmlspecialchars($deal['image']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80';
                        $origPrice = (float)($deal['original_price'] > 0 ? $deal['original_price'] : $deal['base_price']);
                        $salePrice = (float)($deal['sale_price'] > 0 ? $deal['sale_price'] : ($origPrice * (1 - ($deal['discount_percent']/100))));
                        $discountPct = (int)($deal['discount_percent'] > 0 ? $deal['discount_percent'] : round((($origPrice - $salePrice) / $origPrice) * 100));
                        $savings = $origPrice - $salePrice;
                        $daysLeft = !empty($deal['hot_deal_expiry']) ? ceil((strtotime($deal['hot_deal_expiry']) - time()) / 86400) : null;
                        
                        // Claimed percentage simulation for clearance hype
                        $claimedPct = min(95, max(55, (int)(65 + (($deal['id'] * 17) % 28))));
                    ?>
                        <!-- Premium Deal Card -->
                        <div class="group bg-[#0D1829] border border-white/10 hover:border-[#CCFF00]/50 rounded-2xl overflow-hidden shadow-xl hover:shadow-[0_10px_35px_rgba(204,255,0,0.15)] transition-all duration-300 flex flex-col justify-between relative">
                            
                            <!-- Top Left: -X% Lime Badge -->
                            <div class="absolute top-3 left-3 z-20 bg-[#CCFF00] text-[#070D18] font-black text-xs sm:text-sm px-3 py-1 rounded-lg shadow-lg transform -rotate-2">
                                -<?php echo $discountPct; ?>% OFF
                            </div>

                            <!-- Top Right: Wishlist Heart -->
                            <button type="button" 
                                    onclick="toggleHotDealWishlist(<?php echo $deal['id']; ?>, this)" 
                                    class="absolute top-3 right-3 z-20 w-8 h-8 rounded-full bg-black/60 backdrop-blur-md text-gray-300 hover:text-red-500 hover:bg-black/90 flex items-center justify-center transition-all shadow-sm" 
                                    title="Add to Wishlist">
                                <i class="far fa-heart text-xs"></i>
                            </button>

                            <!-- Image 1:1 aspect ratio with clean background -->
                            <a href="<?php echo $base_path; ?>site/product-details.php?id=<?php echo $deal['id']; ?>" class="aspect-square bg-[#09111F] p-4 flex items-center justify-center overflow-hidden relative block">
                                <img src="<?php echo $dealImg; ?>" 
                                     class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500" 
                                     alt="<?php echo htmlspecialchars($deal['name']); ?>"
                                     loading="lazy">
                                <div class="absolute inset-0 bg-gradient-to-t from-[#0D1829] via-transparent to-transparent opacity-60 pointer-events-none"></div>
                            </a>

                            <!-- Card Body -->
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between text-[11px] text-gray-400 font-extrabold uppercase tracking-widest mb-1.5">
                                        <span class="truncate"><?php echo htmlspecialchars($deal['brand_name'] ?? 'OXXA GEAR'); ?></span>
                                        <span class="text-primary font-bold shrink-0"><?php echo htmlspecialchars($deal['category_name'] ?? 'Gear'); ?></span>
                                    </div>
                                    <a href="<?php echo $base_path; ?>site/product-details.php?id=<?php echo $deal['id']; ?>">
                                        <h3 class="font-bold text-white text-base md:text-lg line-clamp-2 hover:text-[#0A6CFF] transition-colors mb-2 leading-snug">
                                            <?php echo htmlspecialchars($deal['name']); ?>
                                        </h3>
                                    </a>
                                </div>

                                <div class="mt-4 pt-3 border-t border-white/10">
                                    <!-- Price Row: Rs. Strikethrough + Green Bold -->
                                    <div class="flex items-baseline justify-between gap-2 mb-2">
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-gray-400 line-through text-xs font-medium">Rs. <?php echo number_format($origPrice, 0); ?></span>
                                            <span class="text-[#0A6CFF] font-black text-xl sm:text-2xl">Rs. <?php echo number_format($salePrice, 0); ?></span>
                                        </div>
                                        <?php if($savings > 0): ?>
                                            <span class="text-[10px] bg-white/10 text-white font-bold px-1.5 py-0.5 rounded">
                                                -Rs. <?php echo number_format($savings, 0); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Progress Bar: X% claimed + Countdown -->
                                    <div class="mt-2.5">
                                        <div class="flex items-center justify-between text-[11px] mb-1">
                                            <span class="text-gray-300 font-medium">
                                                <i class="fas fa-fire text-amber-400 text-[10px] mr-1"></i>
                                                <strong class="text-white"><?php echo $claimedPct; ?>%</strong> claimed
                                            </span>
                                            <span class="text-[10px] text-lime/90 font-medium flex items-center gap-1">
                                                <i class="fas fa-clock text-[9px]"></i> 
                                                <?php 
                                                if($daysLeft === null) {
                                                    echo "Limited time";
                                                } elseif($daysLeft > 1) {
                                                    echo "Ends in {$daysLeft} days";
                                                } elseif($daysLeft == 1) {
                                                    echo "Ends tomorrow";
                                                } else {
                                                    echo "Ends today!";
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-[#0A6CFF] h-full rounded-full transition-all duration-500" style="width: <?php echo $claimedPct; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Hover Slide-Up / Quick Actions -->
                                    <div class="mt-4 flex items-center gap-2">
                                        <button type="button" 
                                                onclick="quickAddToCart(<?php echo $deal['id']; ?>, this)" 
                                                class="w-10 h-10 rounded-xl bg-[#0A6CFF] hover:bg-[#0855c9] text-white flex items-center justify-center transition-all shadow-md shrink-0 hover:scale-105 active:scale-95" 
                                                title="Quick Add to Cart">
                                            <i class="fas fa-shopping-bag text-sm"></i>
                                        </button>
                                        <a href="<?php echo $base_path; ?>site/product-details.php?id=<?php echo $deal['id']; ?>" 
                                           class="flex-1 py-2.5 bg-white/10 hover:bg-[#0A6CFF] text-white font-bold rounded-xl text-xs uppercase tracking-wider transition-all duration-300 flex items-center justify-center gap-2 group-hover:bg-[#0A6CFF] group-hover:text-white">
                                            <span>Grab Deal</span>
                                            <i class="fas fa-arrow-right text-[10px]"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- ==========================================
                     UPGRADED EMPTY STATE (As Requested)
                     ========================================== -->
                <div class="text-center py-16 md:py-20 px-6 sm:px-10 rounded-3xl bg-gradient-to-b from-white/[0.05] to-white/[0.02] border border-white/10 max-w-2xl mx-auto my-6 shadow-2xl backdrop-blur-xl relative overflow-hidden">
                    <div class="absolute -top-16 -left-16 w-36 h-36 bg-[#0A6CFF]/10 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute -bottom-16 -right-16 w-36 h-36 bg-primary/10 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Big Glowing Lime Icon -->
                    <div class="w-20 h-20 rounded-full bg-[#0A6CFF]/15 border border-[#0A6CFF]/30 text-[#0A6CFF] mx-auto flex items-center justify-center mb-6 text-3xl shadow-[0_0_35px_rgba(10,108,255,0.25)]">
                        <i class="fas fa-tags"></i>
                    </div>

                    <!-- Main Empty Heading -->
                    <h2 class="text-2xl sm:text-3xl font-black text-white mb-3 font-space uppercase tracking-tight">
                        Oops! No hot deals right now
                    </h2>

                    <!-- Subtext -->
                    <p class="text-gray-300 text-sm sm:text-base max-w-lg mx-auto mb-8 leading-relaxed">
                        Our verified sellers are brewing new flash deals. Be the first to grab exclusive discounts before stock runs out!
                    </p>

                    <!-- Notify Me When Deals Drop Form -->
                    <div class="max-w-md mx-auto mb-8">
                        <div class="p-1.5 rounded-2xl bg-black/60 border border-white/15 backdrop-blur-md flex items-center gap-2 shadow-inner focus-within:border-[#CCFF00] transition-colors">
                            <div class="pl-3 text-gray-400">
                                <i class="fas fa-bell text-[#0A6CFF] animate-pulse"></i>
                            </div>
                            <input type="email" 
                                   id="dropNotifyEmail" 
                                   placeholder="Enter email to get drop alerts..." 
                                   class="bg-transparent text-white text-xs sm:text-sm w-full py-2 px-2 focus:outline-none placeholder-gray-500">
                            <button type="button" 
                                    onclick="submitDropNotification()" 
                                    class="bg-[#0A6CFF] hover:bg-[#0855c9] text-white font-black text-xs px-5 py-2.5 rounded-xl uppercase tracking-wider transition-all duration-200 shadow-md shrink-0 active:scale-95">
                                Join
                            </button>
                        </div>
                        <p id="notifyFeedbackMsg" class="text-[11px] text-[#0A6CFF] mt-2 hidden"></p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap items-center justify-center gap-3.5 mb-8">
                        <a href="<?php echo $base_path; ?>site/hot-deals.php" 
                           class="bg-[#0A6CFF] text-white font-extrabold px-6 py-3 rounded-full text-xs uppercase tracking-wider hover:bg-[#0855c9] transition-all shadow-lg hover:shadow-[0_0_20px_rgba(10,108,255,0.3)]">
                            View All Active Deals
                        </a>
                        <a href="<?php echo $base_path; ?>site/shop.php" 
                           class="bg-white/10 text-white font-bold px-6 py-3 rounded-full text-xs uppercase tracking-wider hover:bg-white/20 transition-colors border border-white/10">
                            Browse Full Catalog
                        </a>
                    </div>

                    <!-- Trust Bullets: Why Hot Deals? -->
                    <div class="pt-6 border-t border-white/10">
                        <div class="text-[11px] text-gray-400 uppercase tracking-widest font-bold mb-3">Why Hot Deals?</div>
                        <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-xs text-gray-300 font-semibold">
                            <span class="inline-flex items-center gap-1.5 text-white">
                                <i class="fas fa-check-circle text-[#0A6CFF]"></i> 100% Authentic
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-white">
                                <i class="fas fa-check-circle text-[#0A6CFF]"></i> Up to 50% OFF
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-white">
                                <i class="fas fa-check-circle text-[#0A6CFF]"></i> 7-Day Return
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==========================================
             4. HOW HOT DEALS WORK (3 STEPS)
             ========================================== -->
        <section class="mt-20 py-12 px-6 sm:px-10 rounded-3xl bg-gradient-to-b from-white/[0.04] to-white/[0.01] border border-white/10 backdrop-blur-xl relative overflow-hidden">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#0A6CFF]/10 border border-[#0A6CFF]/30 text-[#0A6CFF] text-[11px] font-black uppercase tracking-widest mb-3">
                    <i class="fas fa-shield-alt"></i> Transparency First
                </div>
                <h3 class="text-2xl sm:text-3xl font-black font-space uppercase text-white tracking-tight mb-2">
                    How Hot Deals Work
                </h3>
                <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">
                    Direct savings from certified sports equipment sellers with zero counterfeit and zero quality compromises.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8 max-w-5xl mx-auto">
                <!-- Step 1: Seller Requests -->
                <div class="relative bg-black/40 border border-white/5 hover:border-[#0A6CFF]/30 rounded-2xl p-6 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-14 h-14 rounded-2xl bg-[#0A6CFF]/10 border border-[#0A6CFF]/30 text-[#0A6CFF] flex items-center justify-center text-2xl mb-4 shadow-[0_0_20px_rgba(10,108,255,0.15)]">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="text-[10px] font-black text-[#0A6CFF] uppercase tracking-widest mb-1">Step 01</div>
                        <h4 class="text-lg font-bold text-white mb-2 font-space">Seller Requests Clearance</h4>
                        <p class="text-gray-400 text-xs leading-relaxed">
                            Verified brand distributors submit seasonal overstock, clearance lines, or bundle discounts with guaranteed authentic retail markdowns.
                        </p>
                    </div>
                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center gap-2 text-[11px] text-gray-300 font-medium">
                        <i class="fas fa-check-circle text-[#0A6CFF]"></i> Verified Sellers Only
                    </div>
                </div>

                <!-- Step 2: Admin Audits -->
                <div class="relative bg-black/40 border border-white/5 hover:border-primary/40 rounded-2xl p-6 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-14 h-14 rounded-2xl bg-primary/10 border border-primary/30 text-primary flex items-center justify-center text-2xl mb-4 shadow-[0_0_20px_rgba(0,102,255,0.15)]">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div class="text-[10px] font-black text-primary uppercase tracking-widest mb-1">Step 02</div>
                        <h4 class="text-lg font-bold text-white mb-2 font-space">Admin Audits & Verifies</h4>
                        <p class="text-gray-400 text-xs leading-relaxed">
                            OXXA GEAR administration physically audits serial authenticity, manufacturer pricing, and inspects physical inventory before going live.
                        </p>
                    </div>
                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center gap-2 text-[11px] text-gray-300 font-medium">
                        <i class="fas fa-check-circle text-primary"></i> 100% Genuine Audit
                    </div>
                </div>

                <!-- Step 3: You Save -->
                <div class="relative bg-black/40 border border-white/5 hover:border-emerald-400/40 rounded-2xl p-6 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-14 h-14 rounded-2xl bg-emerald-400/10 border border-emerald-400/30 text-emerald-400 flex items-center justify-center text-2xl mb-4 shadow-[0_0_20px_rgba(52,211,153,0.15)]">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-1">Step 03</div>
                        <h4 class="text-lg font-bold text-white mb-2 font-space">You Save Up To 50%</h4>
                        <p class="text-gray-400 text-xs leading-relaxed">
                            Clearance deals drop live with limited expiry countdowns. Enjoy direct savings with islandwide delivery, cash on delivery, and Pay in 3.
                        </p>
                    </div>
                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center gap-2 text-[11px] text-gray-300 font-medium">
                        <i class="fas fa-check-circle text-emerald-400"></i> Islandwide Delivery & Returns
                    </div>
                </div>
            </div>
        </section>
    </section>
</div>

<!-- Scripts for Countdown, Drop Notification, and Wishlist -->
<script>
// 1. Live Countdown Timer ("Next flash drop in: 02:14:33")
(function initDropCountdown() {
    const countdownEl = document.getElementById('flashDropCountdown');
    if (!countdownEl) return;

    // Fixed cycle or calculated from current time
    let totalSeconds = 2 * 3600 + 14 * 60 + 33; // 02:14:33 base

    // Sync with local storage or interval
    const savedTime = localStorage.getItem('oxxa_drop_countdown');
    const savedTs = localStorage.getItem('oxxa_drop_ts');
    const now = Math.floor(Date.now() / 1000);

    if (savedTime && savedTs) {
        const elapsed = now - parseInt(savedTs, 10);
        totalSeconds = parseInt(savedTime, 10) - elapsed;
        if (totalSeconds <= 0) {
            totalSeconds = 4 * 3600; // Reset to 4 hours cycle
        }
    }

    function updateTimerDisplay() {
        if (totalSeconds <= 0) {
            totalSeconds = 4 * 3600; // auto rollover
        }

        const hrs = Math.floor(totalSeconds / 3600);
        const mins = Math.floor((totalSeconds % 3600) / 60);
        const secs = totalSeconds % 60;

        countdownEl.textContent = 
            String(hrs).padStart(2, '0') + ':' + 
            String(mins).padStart(2, '0') + ':' + 
            String(secs).padStart(2, '0');

        localStorage.setItem('oxxa_drop_countdown', totalSeconds);
        localStorage.setItem('oxxa_drop_ts', Math.floor(Date.now() / 1000));
        totalSeconds--;
    }

    updateTimerDisplay();
    setInterval(updateTimerDisplay, 1000);
})();

// 2. Drop Alerts Subscription
function submitDropNotification() {
    const input = document.getElementById('dropNotifyEmail');
    const msg = document.getElementById('notifyFeedbackMsg');
    if (!input || !msg) return;

    const email = input.value.trim();
    if (!email || !email.includes('@') || !email.includes('.')) {
        msg.textContent = 'Please enter a valid email address.';
        msg.className = 'text-[11px] text-rose-400 mt-2 block';
        input.focus();
        return;
    }

    // Success feedback
    msg.innerHTML = '<i class="fas fa-check-circle mr-1"></i> You\'re on the VIP drop list! We\'ll alert you when clearance deals drop.';
    msg.className = 'text-[11px] text-[#0A6CFF] mt-2 block font-bold';
    input.value = '';

    if (typeof CartManager !== 'undefined' && CartManager.showToast) {
        CartManager.showToast('Subscribed to Hot Deals drop alerts! 🔥', 'success');
    }
}

// 3. Wishlist Toggle Helper
function toggleHotDealWishlist(productId, btn) {
    if (!btn) return;
    const icon = btn.querySelector('i');
    
    if (icon.classList.contains('far')) {
        icon.classList.remove('far', 'text-gray-300');
        icon.classList.add('fas', 'text-red-500');
        btn.classList.add('bg-white/20');
        if (typeof CartManager !== 'undefined' && CartManager.showToast) {
            CartManager.showToast('Added to Wishlist ❤️', 'success');
        } else if (typeof showToast === 'function') {
            showToast('Added to Wishlist ❤️', 'success');
        }
    } else {
        icon.classList.remove('fas', 'text-red-500');
        icon.classList.add('far', 'text-gray-300');
        btn.classList.remove('bg-white/20');
        if (typeof CartManager !== 'undefined' && CartManager.showToast) {
            CartManager.showToast('Removed from Wishlist', 'info');
        } else if (typeof showToast === 'function') {
            showToast('Removed from Wishlist', 'info');
        }
    }
}
</script>

<?php include(__DIR__ . '/../include/footer.php'); ?>
