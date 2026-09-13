<?php
include(__DIR__ . '/../include/header.php');
?>

<!-- Hero Section -->
<section class="relative h-[80vh] min-h-[600px] flex items-center justify-center overflow-hidden mb-12 shadow-xl bg-navy">
    <!-- Background Video / Image -->
    <video autoplay muted loop playsinline preload="auto" poster="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" class="hidden md:block absolute inset-0 w-full h-full object-cover">
        <source src="https://res.cloudinary.com/dhxfrmepy/video/upload/v1/videoplayback_zs3dor.mp4" type="video/mp4">
    </video>
    <img src="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" alt="OXXA GEAR Performance" class="block md:hidden absolute inset-0 w-full h-full object-cover object-top">
    
    <!-- Black Overlay -->
    <div class="absolute inset-0" style="background: rgba(0,0,0,0.65);"></div>
    
    <!-- Hero Content -->
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
        <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-white uppercase tracking-tight mb-6" style="text-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            <span class="block text-primary mb-2 text-xl md:text-2xl tracking-widest font-bold">PERFORMANCE STARTS HERE</span>
            Gear Up. Train Hard.<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">Perform Better.</span>
        </h1>
        
        <p class="text-gray-300 text-lg md:text-xl mb-10 max-w-2xl mx-auto font-medium">
            Discover premium gear for cricket, football, gym, running & every sport. Push your limits with OXXA GEAR.
        </p>
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="products.php" class="w-full sm:w-auto bg-primary hover:bg-primary-hover text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wider transition-all shadow-[0_0_20px_rgba(22,119,255,0.4)] hover:shadow-[0_0_30px_rgba(22,119,255,0.6)] hover:-translate-y-1">
                Shop Sports Gear
            </a>
            <a href="#categories" class="w-full sm:w-auto bg-white/10 hover:bg-white/20 text-white backdrop-blur-md border border-white/20 px-8 py-4 rounded-xl font-bold uppercase tracking-wider transition-all hover:-translate-y-1">
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
            display: flex;
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
            animation: scroll 35s linear infinite;
            padding-left: 1.5rem;
        }
        .marquee-track:hover {
            animation-play-state: paused;
        }
        .brand-card {
            width: 160px;
            height: 90px;
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
            height: 50px;
            max-width: 130px;
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
                width: 120px;
                height: 70px;
            }
            .brand-card img {
                height: 40px;
                max-width: 100px;
            }
        }
        </style>

        <div class="marquee-container container mx-auto">
            <div class="marquee-track">
                <!-- Set 1 -->
                <a href="products.php?brand=under-armour" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Under-Armour-Logo-2005-present.png" alt="Under Armour"></a>
                <a href="products.php?brand=puma" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Puma-Logo.png" alt="Puma"></a>
                <a href="products.php?brand=asics" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Asics-Logo.png" alt="Asics"></a>
                <a href="products.php?brand=new-balance" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/New-Balance-Logo-1972-2006.png" alt="New Balance"></a>
                <a href="products.php?brand=reebok" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Reebok-Logo.png" alt="Reebok"></a>
                <a href="products.php?brand=lululemon" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Lululemon-Symbol.png" alt="Lululemon" class="scale-up-1"></a>
                <a href="products.php?brand=gymshark" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Gymshark-Old-Logo.png" alt="Gymshark" class="scale-up-1"></a>
                <a href="products.php?brand=on-running" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/on-running-logo-png_seeklogo-510256.png" alt="ON Running"></a>
                <a href="products.php?brand=optimum-nutrition" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/optimum-nutrition-logo-png_seeklogo-195136.png" alt="Optimum Nutrition"></a>
                <a href="products.php?brand=muscletech" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/logo_circle_menu_banner_ph_muscle_tech.jpg" alt="MuscleTech" class="scale-up-2"></a>
                <a href="products.php?brand=gnc" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/GNC-Logo.jpg" alt="GNC" class="scale-up-1"></a>
                
                <!-- Set 2 (Duplicated for seamless loop) -->
                <a href="products.php?brand=under-armour" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Under-Armour-Logo-2005-present.png" alt="Under Armour"></a>
                <a href="products.php?brand=puma" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Puma-Logo.png" alt="Puma"></a>
                <a href="products.php?brand=asics" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Asics-Logo.png" alt="Asics"></a>
                <a href="products.php?brand=new-balance" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/New-Balance-Logo-1972-2006.png" alt="New Balance"></a>
                <a href="products.php?brand=reebok" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Reebok-Logo.png" alt="Reebok"></a>
                <a href="products.php?brand=lululemon" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Lululemon-Symbol.png" alt="Lululemon" class="scale-up-1"></a>
                <a href="products.php?brand=gymshark" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/Gymshark-Old-Logo.png" alt="Gymshark" class="scale-up-1"></a>
                <a href="products.php?brand=on-running" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/on-running-logo-png_seeklogo-510256.png" alt="ON Running"></a>
                <a href="products.php?brand=optimum-nutrition" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/optimum-nutrition-logo-png_seeklogo-195136.png" alt="Optimum Nutrition"></a>
                <a href="products.php?brand=muscletech" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/logo_circle_menu_banner_ph_muscle_tech.jpg" alt="MuscleTech" class="scale-up-2"></a>
                <a href="products.php?brand=gnc" class="brand-card"><img src="<?php echo $base_path; ?>image/shop_by_brands/GNC-Logo.jpg" alt="GNC" class="scale-up-1"></a>
            </div>
        </div>
    </section>

<!-- Quick Categories Section -->
<section id="categories" class="container mb-16">
    <div class="text-center mb-10">
        <h2 class="text-3xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-4">
            Shop By <span class="text-primary">Category</span>
        </h2>
        <div class="w-24 h-1 bg-primary mx-auto rounded-full"></div>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Sports Wear -->
        <a href="products.php?category=Sports+Wear" class="group relative rounded-2xl overflow-hidden aspect-[4/5] shadow-md hover:shadow-xl transition-all">
            <img src="https://images.unsplash.com/photo-1518611012118-696072aa579a?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Sports Wear" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-end p-4 pb-6">
                <h3 class="text-white font-space font-bold text-lg md:text-xl uppercase tracking-widest mb-2 translate-y-4 group-hover:translate-y-0 transition-transform">Sports Wear</h3>
                <span class="text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
            </div>
        </a>
        
        <!-- Footwear -->
        <a href="products.php?category=Footwear" class="group relative rounded-2xl overflow-hidden aspect-[4/5] shadow-md hover:shadow-xl transition-all">
            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Footwear" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-end p-4 pb-6">
                <h3 class="text-white font-space font-bold text-lg md:text-xl uppercase tracking-widest mb-2 translate-y-4 group-hover:translate-y-0 transition-transform">Footwear</h3>
                <span class="text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
            </div>
        </a>
        
        <!-- Fitness & Gym -->
        <a href="products.php?category=Fitness+Gym" class="group relative rounded-2xl overflow-hidden aspect-[4/5] shadow-md hover:shadow-xl transition-all">
            <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Fitness & Gym" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-end p-4 pb-6 text-center">
                <h3 class="text-white font-space font-bold text-lg md:text-xl uppercase tracking-widest mb-2 translate-y-4 group-hover:translate-y-0 transition-transform">Fitness & Gym</h3>
                <span class="text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
            </div>
        </a>
        
        <!-- Nutrition -->
        <a href="products.php?category=Nutrition" class="group relative rounded-2xl overflow-hidden aspect-[4/5] shadow-md hover:shadow-xl transition-all">
            <img src="https://images.unsplash.com/photo-1517649763962-0c623066013b?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Nutrition" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-end p-4 pb-6">
                <h3 class="text-white font-space font-bold text-lg md:text-xl uppercase tracking-widest mb-2 translate-y-4 group-hover:translate-y-0 transition-transform">Nutrition</h3>
                <span class="text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
            </div>
        </a>
        
        <!-- Accessories -->
        <a href="products.php?category=Accessories" class="group relative rounded-2xl overflow-hidden aspect-[4/5] shadow-md hover:shadow-xl transition-all">
            <img src="https://images.unsplash.com/photo-1576678927484-cc907957088c?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Accessories" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-end p-4 pb-6">
                <h3 class="text-white font-space font-bold text-lg md:text-xl uppercase tracking-widest mb-2 translate-y-4 group-hover:translate-y-0 transition-transform">Accessories</h3>
                <span class="text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
            </div>
        </a>
        
        <!-- Equipment -->
        <a href="products.php?category=Equipment" class="group relative rounded-2xl overflow-hidden aspect-[4/5] shadow-md hover:shadow-xl transition-all">
            <img src="https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Equipment" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-end p-4 pb-6">
                <h3 class="text-white font-space font-bold text-lg md:text-xl uppercase tracking-widest mb-2 translate-y-4 group-hover:translate-y-0 transition-transform">Equipment</h3>
                <span class="text-primary text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">SHOP NOW &rarr;</span>
            </div>
        </a>
    </div>
</section>

<!-- Main Content -->
<div class="container mb-5">

    <!-- Trending Products -->
    <section class="mb-16 py-8">
        <div class="text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-2">
                Trending <span class="text-primary">Now</span>
            </h2>
            <p class="text-slate mb-4">Top picks from OXXA GEAR</p>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <!-- Product Card 1 -->
            <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(0,0,0,0.1)] transition-all duration-300 relative flex flex-col overflow-hidden">
                <button class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-danger hover:bg-white shadow-sm transition-all" onclick="showToast('Added to Wishlist ✓', 'success')">
                    <i class="far fa-heart"></i>
                </button>
                <a href="product-details.php" class="block relative aspect-square overflow-hidden bg-offwhite">
                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Nike Training Shoe" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-100 group-hover:opacity-0">
                    <img src="https://images.unsplash.com/photo-1608231387042-66d1773070a5?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Nike Training Shoe Alt" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-0 group-hover:opacity-100">
                    <div class="absolute inset-x-0 top-3 left-3 flex gap-1">
                        <span class="bg-primary text-white text-[10px] font-bold px-2 py-1 rounded-sm uppercase tracking-wider">Sale</span>
                    </div>
                </a>
                <div class="p-5 flex-grow flex flex-col relative">
                    <div class="text-[10px] text-slate font-bold uppercase tracking-widest mb-1">Footwear</div>
                    <a href="product-details.php"><h3 class="text-navy font-bold text-lg mb-1 leading-tight hover:text-primary transition-colors">Nike Pro Training Shoe</h3></a>
                    
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 text-xs flex gap-0.5"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
                        <span class="text-slate text-xs ml-2">(24)</span>
                    </div>
                    
                    <div class="mt-auto flex items-end justify-between group-hover:opacity-0 transition-opacity duration-300">
                        <div>
                            <span class="text-slate text-xs line-through block mb-0.5">Rs. 21,000</span>
                            <span class="text-navy font-extrabold text-lg">Rs. 18,500</span>
                        </div>
                        <button class="w-10 h-10 rounded-full bg-offwhite text-navy flex items-center justify-center transition-colors">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="absolute bottom-4 left-4 right-4 translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300">
                        <button onclick="addToCartAnimation(this)" class="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl uppercase tracking-widest text-xs shadow-lg shadow-primary/30 transition-all active:scale-95">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Card 2 -->
            <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(0,0,0,0.1)] transition-all duration-300 relative flex flex-col overflow-hidden">
                <button class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-danger hover:bg-white shadow-sm transition-all" onclick="showToast('Added to Wishlist ✓', 'success')">
                    <i class="far fa-heart"></i>
                </button>
                <a href="product-details.php" class="block relative aspect-square overflow-hidden bg-offwhite">
                    <img src="https://images.unsplash.com/photo-1556817411-31ae72fa3ea8?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Compression T-Shirt" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-100 group-hover:opacity-0">
                    <img src="https://images.unsplash.com/photo-1581655353564-df123a1eb820?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Compression T-Shirt Alt" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-0 group-hover:opacity-100">
                </a>
                <div class="p-5 flex-grow flex flex-col relative">
                    <div class="text-[10px] text-slate font-bold uppercase tracking-widest mb-1">Sports Wear</div>
                    <a href="product-details.php"><h3 class="text-navy font-bold text-lg mb-1 leading-tight hover:text-primary transition-colors">Elite Compression T-Shirt</h3></a>
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 text-xs flex gap-0.5"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <span class="text-slate text-xs ml-2">(42)</span>
                    </div>
                    
                    <div class="mt-auto flex items-end justify-between group-hover:opacity-0 transition-opacity duration-300">
                        <div>
                            <span class="text-navy font-extrabold text-lg">Rs. 4,500</span>
                        </div>
                        <button class="w-10 h-10 rounded-full bg-offwhite text-navy flex items-center justify-center transition-colors">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="absolute bottom-4 left-4 right-4 translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300">
                        <button onclick="addToCartAnimation(this)" class="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl uppercase tracking-widest text-xs shadow-lg shadow-primary/30 transition-all active:scale-95">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Card 3 -->
            <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(0,0,0,0.1)] transition-all duration-300 relative flex flex-col overflow-hidden">
                <button class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-danger hover:bg-white shadow-sm transition-all" onclick="showToast('Added to Wishlist ✓', 'success')">
                    <i class="far fa-heart"></i>
                </button>
                <a href="product-details.php" class="block relative aspect-square overflow-hidden bg-offwhite">
                    <img src="https://images.unsplash.com/photo-1593477004927-89c6dda7c4c9?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Whey Protein" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-100 group-hover:opacity-0">
                    <img src="https://images.unsplash.com/photo-1579722820308-d74e571900a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Whey Protein Alt" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-0 group-hover:opacity-100">
                </a>
                <div class="p-5 flex-grow flex flex-col relative">
                    <div class="text-[10px] text-slate font-bold uppercase tracking-widest mb-1">Nutrition</div>
                    <a href="product-details.php"><h3 class="text-navy font-bold text-lg mb-1 leading-tight hover:text-primary transition-colors">Gold Standard Whey Protein</h3></a>
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 text-xs flex gap-0.5"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
                        <span class="text-slate text-xs ml-2">(128)</span>
                    </div>
                    
                    <div class="mt-auto flex items-end justify-between group-hover:opacity-0 transition-opacity duration-300">
                        <div>
                            <span class="text-navy font-extrabold text-lg">Rs. 12,500</span>
                        </div>
                        <button class="w-10 h-10 rounded-full bg-offwhite text-navy flex items-center justify-center transition-colors">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="absolute bottom-4 left-4 right-4 translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300">
                        <button onclick="addToCartAnimation(this)" class="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl uppercase tracking-widest text-xs shadow-lg shadow-primary/30 transition-all active:scale-95">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Card 4 -->
            <div class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(0,0,0,0.1)] transition-all duration-300 relative flex flex-col overflow-hidden">
                <button class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-danger hover:bg-white shadow-sm transition-all" onclick="showToast('Added to Wishlist ✓', 'success')">
                    <i class="far fa-heart"></i>
                </button>
                <a href="product-details.php" class="block relative aspect-square overflow-hidden bg-offwhite">
                    <img src="https://images.unsplash.com/photo-1584735175315-9d582307137e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Gym Bag" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-100 group-hover:opacity-0">
                    <img src="https://images.unsplash.com/photo-1553062407-98eeb64c6a62?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Gym Bag Alt" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-0 group-hover:opacity-100">
                </a>
                <div class="p-5 flex-grow flex flex-col relative">
                    <div class="text-[10px] text-slate font-bold uppercase tracking-widest mb-1">Accessories</div>
                    <a href="product-details.php"><h3 class="text-navy font-bold text-lg mb-1 leading-tight hover:text-primary transition-colors">Pro Duffel Gym Bag</h3></a>
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 text-xs flex gap-0.5"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i></div>
                        <span class="text-slate text-xs ml-2">(18)</span>
                    </div>
                    
                    <div class="mt-auto flex items-end justify-between group-hover:opacity-0 transition-opacity duration-300">
                        <div>
                            <span class="text-navy font-extrabold text-lg">Rs. 5,800</span>
                        </div>
                        <button class="w-10 h-10 rounded-full bg-offwhite text-navy flex items-center justify-center transition-colors">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="absolute bottom-4 left-4 right-4 translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300">
                        <button onclick="addToCartAnimation(this)" class="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl uppercase tracking-widest text-xs shadow-lg shadow-primary/30 transition-all active:scale-95">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Hot Deals Section -->
    <section class="mb-16 py-12 px-4 rounded-[2rem] bg-navy relative overflow-hidden shadow-2xl">
        <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary rounded-full blur-3xl opacity-20"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center mb-10">
            <div>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white font-space uppercase tracking-widest flex items-center gap-3 mb-2">
                    <i class="fas fa-bolt text-lime"></i> Hot Deals
                </h2>
                <p class="text-gray-400">Up to 30% OFF on premium performance gear</p>
            </div>
            <a href="products.php?offer=sale" class="mt-4 md:mt-0 bg-transparent border border-lime text-lime hover:bg-lime hover:text-navy px-6 py-2 rounded-full font-bold uppercase tracking-wide transition-colors">
                View All Deals
            </a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 relative z-10">
            <!-- Deal 1 -->
            <div class="group bg-white/5 backdrop-blur-md border border-white/10 hover:border-white/30 hover:shadow-[0_0_20px_rgba(255,255,255,0.15)] rounded-2xl overflow-hidden shadow-lg relative transition-all duration-300">
                <div class="absolute top-3 left-3 z-10 bg-lime text-navy font-black text-sm px-2 py-1 rounded-md shadow-md transform -rotate-3">-25%</div>
                <div class="aspect-square bg-black/20 p-4 overflow-hidden relative">
                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Nike Shoe">
                    <div class="absolute inset-0 bg-gradient-to-t from-navy/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </div>
                <div class="p-5">
                    <h4 class="font-bold text-white truncate text-lg">Nike Air Zoom</h4>
                    <div class="flex items-end gap-2 mt-2">
                        <span class="text-gray-400 line-through text-xs">Rs. 25,000</span>
                        <span class="text-lime font-bold text-xl">Rs. 18,750</span>
                    </div>
                </div>
            </div>
            
            <!-- Deal 2 -->
            <div class="group bg-white/5 backdrop-blur-md border border-white/10 hover:border-white/30 hover:shadow-[0_0_20px_rgba(255,255,255,0.15)] rounded-2xl overflow-hidden shadow-lg relative transition-all duration-300">
                <div class="absolute top-3 left-3 z-10 bg-lime text-navy font-black text-sm px-2 py-1 rounded-md shadow-md transform -rotate-3">-15%</div>
                <div class="aspect-square bg-black/20 p-4 overflow-hidden relative">
                    <img src="https://images.unsplash.com/photo-1581404917879-53e19259f56e?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Smart Watch">
                    <div class="absolute inset-0 bg-gradient-to-t from-navy/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </div>
                <div class="p-5">
                    <h4 class="font-bold text-white truncate text-lg">Garmin Forerunner</h4>
                    <div class="flex items-end gap-2 mt-2">
                        <span class="text-gray-400 line-through text-xs">Rs. 45,000</span>
                        <span class="text-lime font-bold text-xl">Rs. 38,250</span>
                    </div>
                </div>
            </div>
            
            <!-- Deal 3 -->
            <div class="group bg-white/5 backdrop-blur-md border border-white/10 hover:border-white/30 hover:shadow-[0_0_20px_rgba(255,255,255,0.15)] rounded-2xl overflow-hidden shadow-lg relative transition-all duration-300">
                <div class="absolute top-3 left-3 z-10 bg-lime text-navy font-black text-sm px-2 py-1 rounded-md shadow-md transform -rotate-3">-30%</div>
                <div class="aspect-square bg-black/20 p-4 overflow-hidden relative">
                    <img src="https://images.unsplash.com/photo-1593477004927-89c6dda7c4c9?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Whey Isolate">
                    <div class="absolute inset-0 bg-gradient-to-t from-navy/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </div>
                <div class="p-5">
                    <h4 class="font-bold text-white truncate text-lg">Optimum Nutrition Isolate</h4>
                    <div class="flex items-end gap-2 mt-2">
                        <span class="text-gray-400 line-through text-xs">Rs. 18,000</span>
                        <span class="text-lime font-bold text-xl">Rs. 12,600</span>
                    </div>
                </div>
            </div>
            
            <!-- Deal 4 -->
            <div class="group bg-white/5 backdrop-blur-md border border-white/10 hover:border-white/30 hover:shadow-[0_0_20px_rgba(255,255,255,0.15)] rounded-2xl overflow-hidden shadow-lg relative transition-all duration-300">
                <div class="absolute top-3 left-3 z-10 bg-lime text-navy font-black text-sm px-2 py-1 rounded-md shadow-md transform -rotate-3">-20%</div>
                <div class="aspect-square bg-black/20 p-4 overflow-hidden relative">
                    <img src="https://images.unsplash.com/photo-1571744384915-d222d02a6f25?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Resistance Bands">
                    <div class="absolute inset-0 bg-gradient-to-t from-navy/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </div>
                <div class="p-5">
                    <h4 class="font-bold text-white truncate text-lg">Pro Resistance Band Set</h4>
                    <div class="flex items-end gap-2 mt-2">
                        <span class="text-gray-400 line-through text-xs">Rs. 4,500</span>
                        <span class="text-lime font-bold text-xl">Rs. 3,600</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Shop By Goal -->
    <section class="mb-16 py-8">
        <div class="text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-extrabold text-navy font-space uppercase tracking-widest mb-2">
                Find Your <span class="text-primary">Performance</span>
            </h2>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Goal 1 -->
            <a href="products.php?goal=build-strength" class="relative rounded-2xl overflow-hidden h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Build Strength" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-6 flex flex-col justify-end text-left">
                    <h4 class="text-2xl font-extrabold text-white uppercase tracking-widest mb-1">BUILD STRENGTH</h4>
                    <p class="text-gray-300 text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Improve strength & muscle performance</p>
                </div>
            </a>
            
            <!-- Goal 2 -->
            <a href="products.php?goal=endurance" class="relative rounded-2xl overflow-hidden h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Endurance" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-6 flex flex-col justify-end text-left">
                    <h4 class="text-2xl font-extrabold text-white uppercase tracking-widest mb-1">ENDURANCE</h4>
                    <p class="text-gray-300 text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Train longer. Go further.</p>
                </div>
            </a>
            
            <!-- Goal 3 -->
            <a href="products.php?goal=fitness" class="relative rounded-2xl overflow-hidden h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Fitness" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-6 flex flex-col justify-end text-left">
                    <h4 class="text-2xl font-extrabold text-white uppercase tracking-widest mb-1">FITNESS</h4>
                    <p class="text-gray-300 text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Everything for your daily training.</p>
                </div>
            </a>
            
            <!-- Goal 4 -->
            <a href="products.php?goal=sports-performance" class="relative rounded-2xl overflow-hidden h-72 cursor-pointer group shadow-md hover:shadow-xl transition-all">
                <img src="https://images.unsplash.com/photo-1579952363873-27f3bade9f55?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Sports Performance" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/50 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-300"></div>
                <div class="absolute inset-0 p-6 flex flex-col justify-end text-left">
                    <h4 class="text-2xl font-extrabold text-white uppercase tracking-widest mb-1">SPORTS</h4>
                    <p class="text-gray-300 text-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">Gear built for champions.</p>
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
            <div class="bg-[#111111] p-12 relative flex flex-col justify-center text-white">
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
            <div class="bg-white p-12">
                <form id="contactHubForm" class="space-y-6">
                    <div class="relative">
                        <select id="subject" name="subject" class="block px-2.5 pb-2.5 pt-6 w-full text-sm text-gray-900 bg-transparent rounded-lg border-2 border-gray-200 appearance-none focus:outline-none focus:ring-0 focus:border-primary peer" required>
                            <option value="" disabled selected></option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Order Support">Order Support</option>
                            <option value="Become a Seller">Become a Seller</option>
                        </select>
                        <label for="subject" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-3 scale-75 top-4 z-10 origin-[0] left-4 peer-focus:text-primary peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-3 bg-white px-1">Subject</label>
                        <i class="fas fa-chevron-down absolute right-4 top-5 text-gray-400 pointer-events-none"></i>
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