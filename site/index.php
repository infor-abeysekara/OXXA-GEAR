<?php
include(__DIR__ . '/../include/header.php');
?>

<!-- Hero Section (Mobile Slider, Desktop Video) -->
<section class="relative h-[56.25vw] sm:h-[80vh] min-h-[250px] sm:min-h-[600px] mb-8 sm:mb-12 shadow-xl bg-navy group">
    <!-- Desktop Video Background -->
    <video autoplay muted loop playsinline preload="auto" poster="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" class="hidden sm:block absolute inset-0 w-full h-full object-cover">
        <source src="https://res.cloudinary.com/dhxfrmepy/video/upload/v1/videoplayback_zs3dor.mp4" type="video/mp4">
    </video>
    
    <!-- Mobile Image Slider (CSS Snap) -->
    <div class="sm:hidden absolute inset-0 w-full h-full flex overflow-x-auto snap-x snap-mandatory hide-scrollbar">
        <img src="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" alt="Slide 1" class="w-full h-full object-cover object-top flex-shrink-0 snap-center">
        <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" alt="Slide 2" class="w-full h-full object-cover object-top flex-shrink-0 snap-center">
        <img src="https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" alt="Slide 3" class="w-full h-full object-cover object-top flex-shrink-0 snap-center">
    </div>
    
    <!-- Slider Dots (Mobile only) -->
    <div class="sm:hidden absolute bottom-4 left-0 right-0 flex justify-center gap-2 z-20">
        <div class="w-2 h-2 rounded-full bg-primary"></div>
        <div class="w-2 h-2 rounded-full bg-white/50"></div>
        <div class="w-2 h-2 rounded-full bg-white/50"></div>
    </div>
    
    <!-- Black Overlay -->
    <div class="absolute inset-0 bg-black/50 sm:bg-black/65 z-10"></div>
    
    <!-- Hero Content -->
    <div class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center px-4 max-w-4xl mx-auto">
        <h1 class="text-[32px] leading-tight sm:text-6xl lg:text-7xl font-extrabold text-white uppercase tracking-tight mb-2 sm:mb-6" style="text-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            <span class="block text-primary mb-1 sm:mb-2 text-[10px] sm:text-2xl tracking-widest font-bold">PERFORMANCE STARTS HERE</span>
            Gear Up. Train Hard.<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400 text-[24px] sm:text-6xl">Perform Better.</span>
        </h1>
        
        <p class="hidden sm:block text-gray-300 text-lg md:text-xl mb-10 max-w-2xl mx-auto font-medium">
            Discover premium gear for cricket, football, gym, running & every sport. Push your limits with OXXA GEAR.
        </p>
        
        <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 w-[90%] sm:w-full mx-auto sm:px-0">
            <a href="products.php" class="w-full sm:w-auto bg-primary hover:bg-primary-hover text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold uppercase tracking-wider transition-all shadow-[0_0_20px_rgba(22,119,255,0.4)] text-sm sm:text-base">
                Shop Sports Gear
            </a>
            <a href="#categories" class="hidden sm:block w-full sm:w-auto bg-white/10 hover:bg-white/20 text-white backdrop-blur-md border border-white/20 px-8 py-4 rounded-xl font-bold uppercase tracking-wider transition-all">
                Explore Collection
            </a>
        </div>
    </div>
</section>    <!-- Featured Brands Section (Infinite Marquee) -->
    <section class="mb-16 py-16 overflow-hidden bg-[#FAFAFA]">
        <div class="text-center mb-10 px-4">
            <h2 class="text-3xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-2">
                AUTHENTIC BRANDS. <span class="text-primary">GUARANTEED.</span>
            </h2>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full mb-4"></div>
            <p class="text-center text-gray-500 max-w-2xl mx-auto mt-4 mb-8 text-[15px] leading-relaxed">At OXXA GEAR, we only stock 100% authentic gear from world-leading brands. From gym essentials to pro-level nutrition, every product is sourced directly from official distributors. No fakes. Ever.</p>
        </div>
        
        <style>
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-container {
            position: relative;
            width: 100%;
            overflow: hidden;
            align-items: center;
        }
        .marquee-container::before,
        .marquee-container::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 80px;
            z-index: 2;
            pointer-events: none;
        }
        .marquee-container::before {
            left: 0;
            background: linear-gradient(to right, rgba(250,250,250,1), rgba(250,250,250,0)); /* Matches #FAFAFA */
        }
        .marquee-container::after {
            right: 0;
            background: linear-gradient(to left, rgba(250,250,250,1), rgba(250,250,250,0));
        }
        .marquee-track {
            display: flex;
            gap: 1.5rem;
            width: max-content;
            animation: scroll 60s linear infinite;
            padding-left: 1.5rem;
        }
        .marquee-track:hover {
            animation-play-state: paused;
        }
        .brand-card {
            width: 200px;
            height: 110px;
            background-color: #ffffff;
            border: 1px solid #EEEEEE;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
            overflow: hidden;
        }
        .brand-card:hover {
            border-color: #0066FF;
            box-shadow: 0 4px 12px rgba(0, 102, 255, 0.1);
        }
        .brand-card img {
            height: 70px;
            max-width: 170px;
            object-fit: contain;
            mix-blend-mode: multiply;
            filter: grayscale(100%) opacity(50%);
            transition: transform 0.3s ease, filter 0.3s ease, opacity 0.3s ease;
        }
        .brand-card:hover img {
            transform: scale(1.1);
            filter: grayscale(0%) opacity(100%);
        }
        
        /* Custom scaling for heavily padded images */
        .brand-card img.scale-up-1 { transform: scale(1.25); }
        .brand-card:hover img.scale-up-1 { transform: scale(1.35); }
        
        .brand-card img.scale-up-2 { transform: scale(1.7); }
        .brand-card:hover img.scale-up-2 { transform: scale(1.85); }
        
        /* Mobile Specific */
        @media (max-width: 768px) {
            .brand-card {
                width: 140px;
                height: 80px;
            }
            .brand-card img {
                height: 50px;
                max-width: 110px;
            }
        }

        .hide-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        </style>

        <!-- Desktop Marquee -->
        <div class="marquee-container container mx-auto hidden md:flex">
            <div class="marquee-track">
                <?php
                // Fetch active brands for carousel
                $carouselBrandStmt = $pdo->query("SELECT id, name, logo_image FROM brands WHERE is_active = 1 ORDER BY name ASC");
                $carouselBrands = $carouselBrandStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Display sets for seamless loop
                for ($i = 0; $i < 2; $i++) {
                    foreach ($carouselBrands as $b) {
                        $imageSrc = !empty($b['logo_image']) ? $base_path . 'assets/uploads/brands/' . htmlspecialchars($b['logo_image']) : 'https://via.placeholder.com/130x50?text=' . urlencode($b['name']);
                        echo '<a href="shop.php?brand[]=' . $b['id'] . '" class="brand-card" title="' . htmlspecialchars($b['name']) . '">';
                        echo '<img src="' . $imageSrc . '" alt="' . htmlspecialchars($b['name']) . '">';
                        echo '</a>';
                    }
                }
                ?>
            </div>
        </div>

        <!-- Mobile Horizontal Scroll (Snap) -->
        <div class="md:hidden flex overflow-x-auto snap-x snap-mandatory hide-scrollbar gap-4 px-4 w-full pb-2">
            <?php
            foreach ($carouselBrands as $b) {
                $imageSrc = !empty($b['logo_image']) ? $base_path . 'assets/uploads/brands/' . htmlspecialchars($b['logo_image']) : 'https://via.placeholder.com/130x50?text=' . urlencode($b['name']);
                echo '<a href="shop.php?brand[]=' . $b['id'] . '" class="brand-card shrink-0 snap-start" title="' . htmlspecialchars($b['name']) . '">';
                echo '<img src="' . $imageSrc . '" alt="' . htmlspecialchars($b['name']) . '">';
                echo '</a>';
            }
            ?>
        </div>
    </section>

<!-- Quick Categories Section -->
<section id="categories" class="container mb-12 sm:mb-16 mt-4 sm:mt-0">
    <div class="text-left sm:text-center mb-6 sm:mb-10 px-4 sm:px-0">
        <h2 class="text-2xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-2 sm:mb-4">
            Shop By <span class="text-primary">Category</span>
        </h2>
        <div class="w-16 sm:w-24 h-1 bg-primary sm:mx-auto rounded-full"></div>
    </div>
    
    <!-- Desktop & Mobile Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 px-4 sm:px-0 pb-4 sm:pb-0">
        <?php
        $catStmt = $pdo->query("SELECT * FROM categories ORDER BY FIELD(name, 'Sports Wear', 'Footwear', 'Fitness & Gym', 'Nutrition', 'Accessories', 'Equipment')");
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categories as $cat) {
            $catImage = !empty($cat['image']) ? $base_path . 'assets/uploads/categories/' . htmlspecialchars($cat['image']) : 'https://via.placeholder.com/400x500?text=' . urlencode($cat['name']);
            ?>
            <a href="shop.php?category=<?php echo htmlspecialchars($cat['slug']); ?>" class="group relative rounded-2xl overflow-hidden h-28 sm:h-auto sm:aspect-[4/5] shadow-md hover:shadow-xl transition-all">
                <img src="<?php echo $catImage; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                <div class="absolute inset-0 bg-black/40 sm:bg-gradient-to-t sm:from-black/90 sm:via-black/20 sm:to-transparent sm:opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center sm:justify-end p-2 sm:p-4 pb-2 sm:pb-6 text-center">
                    <h3 class="text-white font-space font-bold text-[12px] sm:text-lg md:text-xl uppercase tracking-widest sm:mb-2 translate-y-0 sm:translate-y-4 sm:group-hover:translate-y-0 transition-transform text-shadow-md leading-tight"><?php echo htmlspecialchars($cat['name']); ?></h3>
                    <span class="hidden sm:block text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
                </div>
            </a>
            <?php
        }
        ?>
    </div>
</section>

<!-- Main Content -->
<div class="container mb-5">

    <!-- Trending Products -->
    <section class="mb-12 sm:mb-16 py-4 sm:py-8 px-4 sm:px-0">
        <div class="flex justify-between items-end mb-6 sm:mb-10">
            <div class="text-left sm:text-center sm:mx-auto">
                <h2 class="text-2xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-1 sm:mb-2">
                    Trending <span class="text-primary">Now</span>
                </h2>
                <p class="hidden sm:block text-slate mb-4">Top picks from OXXA GEAR</p>
                <div class="w-16 h-1 bg-primary sm:mx-auto rounded-full"></div>
            </div>
            <a href="shop.php" class="sm:hidden text-primary font-bold text-sm uppercase">See All</a>
        </div>
        
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-6">
            <?php
            $trendingQuery = "SELECT p.*, 
                COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled'), 0) as total_sold,
                COALESCE((SELECT MIN(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.qty > 0 AND cs.selling_price > 0), p.base_price) as lowest_price,
                (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as image,
                (SELECT name FROM categories c WHERE c.id = p.category_id) as category_name,
                (SELECT business_name FROM seller_profiles sp WHERE sp.user_id = p.seller_id) as seller_name,
                (SELECT logo_path FROM seller_profiles sp WHERE sp.user_id = p.seller_id) as seller_logo
            FROM products p 
            WHERE p.is_approved = 1 AND p.status = 'active'
            ORDER BY total_sold DESC 
            LIMIT 10";
            
            $trendingStmt = $pdo->query($trendingQuery);
            $trendingProducts = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);

            if(!empty($trendingProducts)):
                foreach($trendingProducts as $p):
                    $img = !empty($p['image']) ? $base_path . 'assets/uploads/products/' . htmlspecialchars($p['image']) : 'https://via.placeholder.com/400x400?text=No+Image';
            ?>
            <!-- Product Card -->
            <div class="group bg-white rounded-xl sm:rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg transition-all duration-300 relative flex flex-col overflow-hidden">
                <button class="absolute top-2 right-2 sm:top-3 sm:right-3 z-10 w-7 h-7 sm:w-8 sm:h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-danger hover:bg-white shadow-sm transition-all" onclick="CartManager.addToWishlist(<?php echo $p['id']; ?>)">
                    <i class="far fa-heart text-sm sm:text-base"></i>
                </button>
                <a href="product-details.php?id=<?php echo $p['id']; ?>" class="block relative aspect-square overflow-hidden bg-offwhite">
                    <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                </a>
                <div class="p-3 sm:p-5 flex-grow flex flex-col relative">
                    <div class="text-[9px] sm:text-[10px] text-slate font-bold uppercase tracking-widest mb-1 truncate"><?php echo htmlspecialchars($p['category_name'] ?? 'General'); ?></div>
                    <a href="product-details.php?id=<?php echo $p['id']; ?>"><h3 class="text-navy font-bold text-sm sm:text-lg mb-1 leading-tight hover:text-primary transition-colors line-clamp-2"><?php echo htmlspecialchars($p['name']); ?></h3></a>
                    
                    <div class="mt-auto pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <span class="text-navy font-black text-sm sm:text-lg">Rs. <?php echo number_format($p['lowest_price'], 0); ?></span>
                        </div>
                        <button type="button" onclick="quickAddToCart(<?php echo $p['id']; ?>, this)" class="w-11 h-11 sm:w-10 sm:h-10 rounded-full bg-navy hover:bg-primary text-white flex items-center justify-center transition-all shadow-sm hover:scale-105 shrink-0" title="Add to Cart">
                            <i class="fas fa-shopping-bag text-sm sm:text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php 
                endforeach;
            else:
            ?>
                <p class="text-gray-500 w-full text-center py-8 col-span-2 lg:col-span-5">No trending products available yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Hot Deals Section -->
    <?php /* ?>
    <section class="mb-16 py-12 px-4 rounded-[2rem] bg-[#F8FAFF] border border-gray-200 relative overflow-hidden shadow-sm">
        <div class="absolute inset-0 opacity-5 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary rounded-full blur-3xl opacity-10"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center mb-10">
            <div>
                <h2 class="text-3xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest flex items-center gap-3 mb-2">
                    <i class="fas fa-bolt text-[#D4FF00]"></i> HOT <span class="text-[#0A6CFF]">DEALS</span>
                </h2>
                <p class="text-gray-600">Exclusive limited-time discounts on premium performance gear</p>
            </div>
            <a href="<?php echo $base_path; ?>site/hot-deals.php" class="mt-4 md:mt-0 bg-transparent border border-[#0A6CFF] text-[#0A6CFF] hover:bg-[#0A6CFF] hover:text-white px-6 py-2 rounded-full font-bold uppercase tracking-wide transition-colors flex items-center gap-2">
                <span>View All Deals</span>
                <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 relative z-10">
            <?php
            $hotDealsQuery = "SELECT p.*, 
                (SELECT b.name FROM brands b WHERE b.id = p.brand_id) as brand_name,
                COALESCE(
                    (SELECT ci.image_path FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = p.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1),
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1)
                ) as image
            FROM products p 
            WHERE p.is_hot_deal = 1 
              AND p.hot_deal_status = 'approved' 
              AND p.status = 'active'
              AND (p.hot_deal_expiry IS NULL OR p.hot_deal_expiry >= NOW())
            ORDER BY p.discount_percent DESC 
            LIMIT 4";
            
            $hotDealsStmt = $pdo->query($hotDealsQuery);
            $hotDeals = $hotDealsStmt ? $hotDealsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            if(!empty($hotDeals)):
                foreach($hotDeals as $deal):
                    $dealImg = !empty($deal['image']) ? $base_path . 'assets/uploads/products/' . htmlspecialchars($deal['image']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80';
                    $origPrice = (float)($deal['original_price'] > 0 ? $deal['original_price'] : $deal['base_price']);
                    $salePrice = (float)($deal['sale_price'] > 0 ? $deal['sale_price'] : ($origPrice * (1 - ($deal['discount_percent']/100))));
                    $discountPct = (int)($deal['discount_percent'] > 0 ? $deal['discount_percent'] : round((($origPrice - $salePrice) / $origPrice) * 100));
                    
                    $daysLeft = !empty($deal['hot_deal_expiry']) ? ceil((strtotime($deal['hot_deal_expiry']) - time()) / 86400) : null;
            ?>
            <!-- Dynamic Deal Card -->
            <div class="group bg-white border border-gray-200 hover:border-[#0A6CFF]/30 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg relative transition-all duration-300 flex flex-col justify-between">
                <div class="absolute top-3 left-3 z-10 bg-[#D4FF00] text-black font-black text-sm px-2.5 py-1 rounded-md shadow-sm transform -rotate-3">
                    -<?php echo $discountPct; ?>%
                </div>
                
                <a href="<?php echo $base_path; ?>site/product-details.php?id=<?php echo $deal['id']; ?>" class="aspect-square bg-gray-50 p-4 overflow-hidden relative block">
                    <img src="<?php echo $dealImg; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 rounded-lg" alt="<?php echo htmlspecialchars($deal['name']); ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-100/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </a>
                
                <div class="p-5 flex-1 flex flex-col justify-between">
                    <div>
                        <?php if(!empty($deal['brand_name'])): ?>
                            <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1"><?php echo htmlspecialchars($deal['brand_name']); ?></div>
                        <?php endif; ?>
                        <a href="<?php echo $base_path; ?>site/product-details.php?id=<?php echo $deal['id']; ?>">
                            <h4 class="font-bold text-navy truncate text-lg hover:text-[#0A6CFF] transition-colors"><?php echo htmlspecialchars($deal['name']); ?></h4>
                        </a>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <div class="flex items-baseline gap-2">
                                <span class="text-gray-400 line-through text-xs font-medium">Rs. <?php echo number_format($origPrice, 0); ?></span>
                                <span class="text-[#0A6CFF] font-bold text-xl">Rs. <?php echo number_format($salePrice, 0); ?></span>
                            </div>
                            <button type="button" onclick="quickAddToCart(<?php echo $deal['id']; ?>, this)" class="w-9 h-9 rounded-full bg-[#0A6CFF] hover:bg-[#0855c9] text-white flex items-center justify-center transition-all shadow-md hover:scale-105" title="Add to Cart">
                                <i class="fas fa-shopping-bag text-xs"></i>
                            </button>
                        </div>
                        <?php if($daysLeft !== null): ?>
                            <div class="mt-2 text-[11px] text-[#0A6CFF] font-medium flex items-center gap-1.5">
                                <i class="fas fa-clock text-xs"></i>
                                <span><?php echo $daysLeft > 1 ? "Ends in {$daysLeft} days" : ($daysLeft == 1 ? "Ends tomorrow" : "Ends today!"); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php 
                endforeach;
            else:
            ?>
                <!-- Empty State if no active deals yet -->
                <div class="col-span-1 sm:col-span-2 md:col-span-4 text-center py-12 px-4 rounded-xl bg-white border border-gray-200">
                    <div class="w-16 h-16 rounded-full bg-[#0A6CFF]/10 text-[#0A6CFF] mx-auto flex items-center justify-center mb-4 text-2xl">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="text-xl font-bold text-navy mb-2">Exclusive Deals Dropping Soon!</h3>
                    <p class="text-gray-600 text-sm max-w-md mx-auto mb-6">Our verified sellers are preparing limited-time clearance deals with up to 50% discount. Check back frequently!</p>
                    <a href="<?php echo $base_path; ?>site/shop.php" class="inline-flex items-center gap-2 bg-[#0A6CFF] text-white px-4 py-3 rounded-full font-bold uppercase text-sm tracking-wider hover:bg-[#0855c9] transition-colors">
                        Browse All Products
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php */ ?>

    <!-- Shop By Goal -->
    <section class="mb-16 py-8">
        <div class="text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-2">
                Find Your <span class="text-primary">Performance</span>
            </h2>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Goal 1 -->
            <a href="products.php?goal=build-strength" class="relative rounded-2xl overflow-hidden h-36 sm:h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Build Strength" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-4 sm:p-6 flex flex-col justify-end text-left">
                    <h4 class="text-xl sm:text-2xl font-extrabold text-white uppercase tracking-widest mb-1">BUILD STRENGTH</h4>
                    <p class="text-gray-300 text-xs sm:text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Improve strength & muscle performance</p>
                </div>
            </a>
            
            <!-- Goal 2 -->
            <a href="products.php?goal=endurance" class="relative rounded-2xl overflow-hidden h-36 sm:h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Endurance" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-4 sm:p-6 flex flex-col justify-end text-left">
                    <h4 class="text-xl sm:text-2xl font-extrabold text-white uppercase tracking-widest mb-1">ENDURANCE</h4>
                    <p class="text-gray-300 text-xs sm:text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Train longer. Go further.</p>
                </div>
            </a>
            
            <!-- Goal 3 -->
            <a href="products.php?goal=fitness" class="relative rounded-2xl overflow-hidden h-36 sm:h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Fitness" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-4 sm:p-6 flex flex-col justify-end text-left">
                    <h4 class="text-xl sm:text-2xl font-extrabold text-white uppercase tracking-widest mb-1">FITNESS</h4>
                    <p class="text-gray-300 text-xs sm:text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Everything for your daily training.</p>
                </div>
            </a>
            
            <!-- Goal 4 -->
            <a href="products.php?goal=sports-performance" class="relative rounded-2xl overflow-hidden h-36 sm:h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1579952363873-27f3bade9f55?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Sports Performance" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-4 sm:p-6 flex flex-col justify-end text-left">
                    <h4 class="text-xl sm:text-2xl font-extrabold text-white uppercase tracking-widest mb-1">SPORTS</h4>
                    <p class="text-gray-300 text-xs sm:text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Gear built for champions.</p>
                </div>
            </a>
        </div>
    </section>

    <!-- The OXXA Standard / Our Story -->
    <section class="w-screen ml-[calc(-50vw+50%)] bg-[#080808] py-28 relative overflow-hidden mb-24">
        <!-- Grid pattern overlay 3% opacity -->
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(#ffffff 1px, transparent 1px), linear-gradient(90deg, #ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>
        
        <!-- Radial Blue Glows -->
        <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(circle at 80% 0%, rgba(0,102,255,0.15), transparent 60%);"></div>
        <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(circle at 20% 100%, rgba(0,102,255,0.15), transparent 60%);"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <!-- Main Content Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <!-- Left Content -->
                <div class="text-left">
                    <span class="inline-block text-primary font-space font-bold uppercase tracking-widest text-xs lg:text-sm mb-4 border border-primary/30 px-3 py-1 rounded-full bg-primary/10">OUR STORY // SINCE 2023</span>
                    <h2 class="font-black text-5xl lg:text-6xl leading-[1.1] lg:leading-[1.1] uppercase tracking-tight mb-8 font-space text-white">
                        WE DON'T JUST SELL PRODUCTS.<br>
                        <span class="text-primary mt-2 block drop-shadow-[0_0_20px_rgba(0,102,255,0.6)]">WE BUILD CHAMPIONS.</span>
                    </h2>
                    <p class="text-gray-400 max-w-lg leading-relaxed mb-10 text-[15px]">
                        Started in a small gym in Colombo 07, OXXA was born because we were tired of fake supplements and overpriced gear. We wanted one place where every Sri Lankan player could get 100% authentic, world-class gear without compromise.
                    </p>
                    <a href="#" class="inline-flex items-center justify-center px-8 py-4 border-2 border-white text-white rounded-full font-bold uppercase tracking-widest text-sm hover:bg-primary hover:border-primary transition-all duration-300">
                        OUR FULL STORY <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Right Content -->
                <div class="space-y-4">
                    <!-- Glass Card 1 -->
                    <div class="rounded-[20px] bg-white/[0.06] backdrop-blur-xl border border-white/[0.08] p-7 flex gap-5 items-start hover:border-[#0066FF]/50 hover:bg-white/[0.09] transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-full bg-[#0066FF]/15 border border-[#0066FF]/30 flex items-center justify-center flex-shrink-0 text-[#0066FF] text-xl group-hover:scale-110 transition-transform">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div class="pt-1">
                            <h4 class="font-bold text-xl uppercase tracking-wide mb-1 text-white">Authenticity Guaranteed</h4>
                            <p class="text-gray-400 text-sm leading-relaxed">Every product is sourced directly from official distributors.</p>
                        </div>
                    </div>

                    <!-- Glass Card 2 -->
                    <div class="rounded-[20px] bg-white/[0.06] backdrop-blur-xl border border-white/[0.08] p-7 flex gap-5 items-start hover:border-[#0066FF]/50 hover:bg-white/[0.09] transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-full bg-[#0066FF]/15 border border-[#0066FF]/30 flex items-center justify-center flex-shrink-0 text-[#0066FF] text-xl group-hover:scale-110 transition-transform">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <div class="pt-1">
                            <h4 class="font-bold text-xl uppercase tracking-wide mb-1 text-white">Field Tested</h4>
                            <p class="text-gray-400 text-sm leading-relaxed">Gear trusted by pros across every sport in Sri Lanka.</p>
                        </div>
                    </div>

                    <!-- Glass Card 3 -->
                    <div class="rounded-[20px] bg-white/[0.06] backdrop-blur-xl border border-white/[0.08] p-7 flex gap-5 items-start hover:border-[#0066FF]/50 hover:bg-white/[0.09] transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-full bg-[#0066FF]/15 border border-[#0066FF]/30 flex items-center justify-center flex-shrink-0 text-[#0066FF] text-xl group-hover:scale-110 transition-transform">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="pt-1">
                            <h4 class="font-bold text-xl uppercase tracking-wide mb-1 text-white">Built For Sri Lanka</h4>
                            <p class="text-gray-400 text-sm leading-relaxed">Designed for our climate, engineered for our community.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Stats Bar -->
            <div class="border-t border-white/10 mt-16 pt-10 grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-0 text-center relative z-10">
                <div class="relative">
                    <h5 class="text-5xl font-black text-white font-space mb-0">10K+</h5>
                    <div class="text-xs tracking-[0.2em] text-gray-500 mt-2 font-bold uppercase">MEMBERS</div>
                    <div class="hidden md:block w-2 h-2 rounded-full bg-[#0066FF] absolute top-1/2 -translate-y-1/2 -right-1"></div>
                </div>
                <div class="relative">
                    <h5 class="text-5xl font-black text-white font-space mb-0">50+</h5>
                    <div class="text-xs tracking-[0.2em] text-gray-500 mt-2 font-bold uppercase">BRANDS</div>
                    <div class="hidden md:block w-2 h-2 rounded-full bg-[#0066FF] absolute top-1/2 -translate-y-1/2 -right-1"></div>
                </div>
                <div class="relative">
                    <h5 class="text-5xl font-black text-white font-space mb-0">25K+</h5>
                    <div class="text-xs tracking-[0.2em] text-gray-500 mt-2 font-bold uppercase">ORDERS</div>
                    <div class="hidden md:block w-2 h-2 rounded-full bg-[#0066FF] absolute top-1/2 -translate-y-1/2 -right-1"></div>
                </div>
                <div class="relative">
                    <h5 class="text-5xl font-black text-white font-space mb-0">4.8<i class="fas fa-star text-yellow-400 text-[1.5rem] ml-1 align-baseline"></i></h5>
                    <div class="text-xs tracking-[0.2em] text-gray-500 mt-2 font-bold uppercase">RATING</div>
                </div>
            </div>
        </div>
    </section>







    <!-- Contact & Newsletter Hub -->
    <section class="mb-16 mx-4">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-0 rounded-[32px] overflow-hidden shadow-2xl">
            <!-- Left Panel (Dark) -->
            <div class="bg-[#111111] p-6 lg:p-12 relative flex flex-col justify-center text-white">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/black-scales.png')] opacity-20"></div>
                <div class="relative z-10">
                    <h2 class="text-4xl font-bold uppercase tracking-wide mb-4 font-space">
                        LET'S TALK <span class="text-primary">PERFORMANCE</span>
                    </h2>
                    <p class="text-gray-400 mb-10 text-[15px] leading-relaxed">Have a question about your order, product authenticity, or becoming a seller? Our team replies within 2 hours.</p>
                    
                    <div class="space-y-6 mb-10">
                        <div class="flex items-center gap-4 group cursor-default">
                            <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                                <i class="fas fa-map-marker-alt text-xl"></i>
                            </div>
                            <span class="text-gray-300 group-hover:text-white transition-colors">Colombo 07, Sri Lanka</span>
                        </div>
                        <div class="flex items-center gap-4 group cursor-default">
                            <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                                <i class="fas fa-phone-alt text-xl"></i>
                            </div>
                            <span class="text-gray-300 group-hover:text-white transition-colors">+94 77 123 4567</span>
                        </div>
                        <div class="flex items-center gap-4 group cursor-pointer">
                            <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-colors duration-300">
                                <i class="fas fa-envelope text-xl"></i>
                            </div>
                            <a href="mailto:support@oxxagear.lk" class="text-gray-300 group-hover:text-white transition-colors">support@oxxagear.lk</a>
                        </div>
                    </div>

                    <a href="https://wa.me/94771234567" target="_blank" class="inline-flex items-center justify-center gap-3 bg-[#25D366] hover:bg-[#20b858] text-white font-bold py-4 px-8 rounded-full transition-all duration-300 w-full sm:w-auto shadow-[0_0_15px_rgba(37,211,102,0.3)] hover:shadow-[0_0_25px_rgba(37,211,102,0.6)] hover:-translate-y-1">
                        <i class="fab fa-whatsapp text-2xl"></i> CHAT ON WHATSAPP
                    </a>
                </div>
            </div>

            <!-- Right Panel (White Form) -->
            <div class="bg-white p-6 lg:p-12">
                <form id="contactHubForm" class="space-y-6">
                    <div class="relative">
                        <input type="text" list="subjectOptions" id="subject" name="subject" class="block px-2.5 pb-2.5 pt-6 w-full text-sm text-gray-900 bg-transparent rounded-lg border-2 border-gray-200 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " required />
                        <datalist id="subjectOptions">
                            <option value="General Inquiry">
                            <option value="Order Support">
                            <option value="Become a Seller">
                        </datalist>
                        <label for="subject" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-3 scale-75 top-4 z-10 origin-[0] left-4 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-3 bg-white px-1">Subject</label>
                    </div>

                    <div class="relative">
                        <input type="text" id="name" name="name" class="block px-2.5 pb-2.5 pt-6 w-full text-sm text-gray-900 bg-transparent rounded-lg border-2 border-gray-200 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " required />
                        <label for="name" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-3 scale-75 top-4 z-10 origin-[0] left-4 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-3 bg-white px-1">Full Name</label>
                    </div>

                    <div class="relative">
                        <input type="email" id="email" name="email" class="block px-2.5 pb-2.5 pt-6 w-full text-sm text-gray-900 bg-transparent rounded-lg border-2 border-gray-200 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" placeholder=" " required />
                        <label for="email" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-3 scale-75 top-4 z-10 origin-[0] left-4 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-3 bg-white px-1">Email Address</label>
                    </div>

                    <div class="relative">
                        <textarea id="message" name="message" rows="4" class="block px-2.5 pb-2.5 pt-6 w-full text-sm text-gray-900 bg-transparent rounded-lg border-2 border-gray-200 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer resize-none" placeholder=" " required></textarea>
                        <label for="message" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-3 scale-75 top-4 z-10 origin-[0] left-4 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-3 bg-white px-1">How can we help?</label>
                    </div>

                    <div class="flex items-center">
                        <input id="subscribe" name="subscribe" type="checkbox" value="true" class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary focus:ring-2" checked>
                        <label for="subscribe" class="ml-2 text-sm font-medium text-gray-700">Subscribe to latest drops & deals (STAY IN THE GAME)</label>
                    </div>

                    <button type="submit" id="contactSubmitBtn" class="bg-black text-white rounded-full py-4 px-10 hover:bg-primary transition-all duration-300 w-full font-bold uppercase tracking-wide hover:shadow-[0_0_15px_rgba(0,102,255,0.5)]">
                        Send Message
                    </button>
                    
                    <div class="flex items-center justify-center gap-6 mt-6 text-xs text-gray-400 font-medium uppercase tracking-wide">
                        <span class="flex items-center gap-1"><i class="fas fa-clock text-primary"></i> Avg reply time: 2 hours</span>
                        <span class="flex items-center gap-1"><i class="fas fa-shield-check text-primary"></i> 100% Authentic Support</span>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
    document.getElementById('contactHubForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('contactSubmitBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SENDING...';
        btn.disabled = true;

        const formData = new FormData(this);
        formData.append('subscribe', document.getElementById('subscribe').checked);

        fetch('Backend/contact-backend.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if (data.success) {
                // If sweetalert is loaded, use it, otherwise fallback to alert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Message sent!',
                        text: "We'll get back to you in 2 hours.",
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    alert('Message sent! We\'ll get back to you in 2 hours.');
                }
                this.reset();
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Something went wrong.'
                    });
                } else {
                    alert('Error: ' + (data.message || 'Something went wrong.'));
                }
            }
        })
        .catch(error => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.error('Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Something went wrong while sending the message.'
                });
            } else {
                alert('Something went wrong while sending the message.');
            }
        });
    });
    </script>

</div>

<style>
/* Hide scrollbar for Chrome, Safari and Opera */
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.hide-scrollbar {
  -ms-overflow-style: none;  /* IE and Edge */
  scrollbar-width: none;  /* Firefox */
}
</style>

<?php
include(__DIR__ . '/../include/footer.php');
?>