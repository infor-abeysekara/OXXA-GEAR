<?php
session_start();
$page_title = 'Seller Dashboard - OXXA GEAR';
include('../include/header.php');
include('../include/connection.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

// Check business verification status
$stmt = $pdo->prepare("SELECT * FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['userid']]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// If not approved yet, redirect to business registration page
if (!$business || $business['is_approved'] == 0) {
    header('Location: business-registration.php');
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// --- Dashboard Stats ---
// 1. Total Products
$prodStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
$prodStmt->execute([$_SESSION['userid']]);
$total_products = $prodStmt->fetchColumn();

// 2. My Earnings & Orders (Requires querying seller_payouts or wallets)
$earningStmt = $pdo->prepare("SELECT COUNT(DISTINCT order_id) as total_orders, SUM(seller_earning) as total_earned FROM order_items JOIN products ON order_items.product_id = products.id WHERE products.seller_id = ? AND settlement_status = 'Settled'");
$earningStmt->execute([$_SESSION['userid']]);
$earningData = $earningStmt->fetch(PDO::FETCH_ASSOC);
$total_orders = $earningData['total_orders'] ?? 0;
$total_earned = $earningData['total_earned'] ?? 0.00;

// 3. Pending Payouts (From Wallet)
$pendingStmt = $pdo->prepare("SELECT pending_balance FROM seller_wallets WHERE seller_id = ?");
$pendingStmt->execute([$_SESSION['userid']]);
$pending_balance = $pendingStmt->fetchColumn() ?: 0.00;

// 4. Low Stock Alert
$stockStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND total_qty < 5");
$stockStmt->execute([$_SESSION['userid']]);
$low_stock = $stockStmt->fetchColumn();

// Get joined date
$joinStmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
$joinStmt->execute([$_SESSION['userid']]);
$joined_date = $joinStmt->fetchColumn();
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Mobile Sidebar Toggle -->
        <div class="lg:hidden mb-4 flex justify-between items-center bg-white p-4 rounded-xl shadow-sm">
            <h2 class="font-black text-navy uppercase">Dashboard Menu</h2>
            <button onclick="document.getElementById('mobileMenu').classList.toggle('hidden')" class="text-navy">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>

        <!-- Seller Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <div class="relative">
                <?php 
                $logo_exists = !empty($business['logo_path']) && file_exists('../assets/uploads/' . $business['logo_path']);
                if ($logo_exists): 
                ?>
                    <img src="../assets/uploads/<?= htmlspecialchars($business['logo_path']) ?>" class="w-16 h-16 rounded-full object-cover ring-4 ring-white shadow-sm">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full bg-[#0066FF] text-white flex items-center justify-center text-2xl font-black shadow-sm ring-4 ring-white">
                        <?= strtoupper(substr($business['business_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['profile_image']) && file_exists('../assets/uploads/profiles/' . $_SESSION['profile_image'])): ?>
                    <img src="../assets/uploads/profiles/<?= htmlspecialchars($_SESSION['profile_image']) ?>" class="absolute bottom-0 right-0 w-6 h-6 rounded-full border-2 border-white object-cover" title="Profile Picture">
                <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-navy uppercase tracking-wide"><?= htmlspecialchars($business['business_name']) ?></h1>
                    <p class="text-sm text-slate mt-1 flex items-center flex-wrap gap-2">
                        <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold"><i class="fas fa-check-circle me-1"></i> Verified</span>
                        <span>Joined: <?= date('M Y', strtotime($joined_date)) ?></span>
                        <span class="text-gray-300">|</span>
                        <?php if ($total_products == 0): ?>
                            <span class="text-[#0066FF] font-bold">Complete your store to get views!</span>
                        <?php else: ?>
                            <span class="font-bold">10K+ Store Views</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <a href="seller-add-product.php" class="hidden md:flex bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 items-center">
                <i class="fas fa-plus me-2"></i> Add Product
            </a>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Navigation -->
            <div id="mobileMenu" class="hidden lg:block w-full lg:w-64 shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <nav class="flex flex-col p-2 gap-1">
                        <a href="?tab=dashboard" class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors <?= $tab == 'dashboard' ? 'bg-blue-50 text-[#0066FF] font-bold shadow-sm border border-blue-100' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <span><i class="fas fa-chart-pie w-6"></i> Overview</span>
                        </a>
                        <a href="?tab=products" class="flex items-center px-4 py-3 rounded-xl transition-colors <?= $tab == 'products' ? 'bg-blue-50 text-[#0066FF] font-bold shadow-sm border border-blue-100' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <i class="fas fa-box w-6"></i> My Products
                        </a>
                        <a href="?tab=orders" class="flex items-center px-4 py-3 rounded-xl transition-colors <?= $tab == 'orders' ? 'bg-blue-50 text-[#0066FF] font-bold shadow-sm border border-blue-100' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <i class="fas fa-shopping-cart w-6"></i> Orders
                        </a>
                        <a href="?tab=earnings" class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors <?= $tab == 'earnings' ? 'bg-blue-50 text-[#0066FF] font-bold shadow-sm border border-blue-100' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <span><i class="fas fa-wallet w-6"></i> Earnings</span>
                            <?php if($pending_balance > 0): ?>
                                <span class="bg-yellow-100 text-yellow-700 text-[10px] font-black px-2 py-0.5 rounded-full">Rs. <?= number_format($pending_balance) ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="h-px bg-gray-100 my-2 mx-4"></div>
                        <a href="business-registration.php" class="flex items-center px-4 py-3 rounded-xl transition-colors text-slate hover:bg-gray-50 hover:text-navy font-medium">
                            <i class="fas fa-id-card w-6"></i> Business Profile
                        </a>
                    </nav>
                    
                    <div class="p-4 bg-gray-50 border-t border-gray-100 mt-2">
                        <a href="#" class="flex items-center text-sm font-bold text-slate hover:text-[#0066FF]">
                            <i class="fas fa-life-ring w-6"></i> Help Center
                        </a>
                    </div>
                </div>
                
                <!-- Mobile Add Product Button -->
                <a href="seller-add-product.php" class="md:hidden mt-4 w-full bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex justify-center items-center">
                    <i class="fas fa-plus me-2"></i> Add Product
                </a>
            </div>

            <!-- Main Content Area -->
            <div class="flex-1 min-w-0">
                <?php if ($tab == 'dashboard'): ?>
                    
                    <!-- Stats Grid (5 Cards) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
                        
                        <!-- Total Earned -->
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between group hover:border-[#0066FF] transition-colors">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Total Earned</p>
                                <h3 class="text-xl font-black text-navy">Rs. <?= number_format($total_earned) ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>

                        <!-- Pending Payouts -->
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between group hover:border-[#0066FF] transition-colors">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Pending Payouts</p>
                                <h3 class="text-xl font-black text-[#0066FF]">Rs. <?= number_format($pending_balance) ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        
                        <!-- Products -->
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between group hover:border-[#0066FF] transition-colors">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Products</p>
                                <h3 class="text-xl font-black text-navy"><?= number_format($total_products) ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>

                        <!-- Total Orders -->
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between group hover:border-[#0066FF] transition-colors">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Orders</p>
                                <h3 class="text-xl font-black text-navy"><?= number_format($total_orders) ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>

                        <!-- Low Stock -->
                        <div class="bg-white p-5 rounded-2xl shadow-sm border <?= $low_stock > 0 ? 'border-red-200 bg-red-50' : 'border-gray-100' ?> flex items-center justify-between group hover:border-red-500 transition-colors">
                            <div>
                                <p class="text-[10px] font-bold <?= $low_stock > 0 ? 'text-red-500' : 'text-gray-400' ?> uppercase tracking-wide mb-1">Low Stock</p>
                                <h3 class="text-xl font-black <?= $low_stock > 0 ? 'text-red-600' : 'text-navy' ?>"><?= $low_stock ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl <?= $low_stock > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' ?> flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>

                    </div>

                    <?php if ($total_products == 0): ?>
                        <!-- Empty State Onboarding -->
                        <div class="bg-white rounded-2xl shadow-sm border border-[#0066FF]/20 p-10 text-center mb-8 relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50 rounded-full opacity-50 blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
                            <div class="w-48 h-48 mx-auto mb-6 relative z-10 flex items-center justify-center bg-blue-50/50 rounded-full">
                                <i class="fas fa-box-open text-7xl text-[#0066FF] opacity-80"></i>
                            </div>
                            
                            <h2 class="text-3xl font-black text-navy mb-3 relative z-10">Let's add your first product and start earning!</h2>
                            <p class="text-slate mb-8 max-w-md mx-auto relative z-10 text-lg">Your store is ready. Add your first piece of premium sports gear and start reaching thousands of customers across Sri Lanka.</p>
                            
                            <a href="seller-add-product.php" class="inline-flex bg-[#0066FF] hover:bg-blue-700 text-white px-10 py-5 rounded-xl font-black uppercase tracking-widest transition-all shadow-xl shadow-blue-500/30 items-center mb-12 hover:-translate-y-1 relative z-10">
                                <i class="fas fa-plus me-3 text-xl"></i> Add Your First Product
                            </a>

                            <!-- Onboarding Checklist -->
                            <div class="max-w-2xl mx-auto bg-gray-50 rounded-xl p-6 border border-gray-100 relative z-10">
                                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-sm font-bold">
                                    <div class="flex items-center text-green-500 bg-green-50 px-4 py-2 rounded-lg w-full sm:w-auto justify-center">
                                        <i class="fas fa-check-circle me-2 text-lg"></i> Business Verified
                                    </div>
                                    <div class="hidden sm:block w-8 h-px bg-gray-300"></div>
                                    <div class="flex items-center text-navy bg-white shadow-sm border border-gray-200 px-4 py-2 rounded-lg w-full sm:w-auto justify-center">
                                        <i class="far fa-circle me-2 text-lg text-gray-400"></i> Add First Product (2 mins)
                                    </div>
                                    <div class="hidden sm:block w-8 h-px bg-gray-300"></div>
                                    <div class="flex items-center text-gray-400 bg-white px-4 py-2 rounded-lg w-full sm:w-auto justify-center">
                                        <i class="far fa-circle me-2 text-lg"></i> Get First Order
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Collapsible Guide -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8" id="quickGuidePanel">
                        <button onclick="document.getElementById('guideContent').classList.toggle('hidden'); document.getElementById('guideIcon').classList.toggle('rotate-180')" class="w-full p-6 border-b border-gray-100 flex justify-between items-center hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-blue-50 text-[#0066FF] flex items-center justify-center me-4">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <h2 class="font-black text-navy uppercase tracking-wide text-left">Quick Guide: The 10% Profit Rule</h2>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" id="guideIcon"></i>
                        </button>
                        
                        <div class="<?= $total_products > 0 ? 'hidden' : 'block' ?>" id="guideContent">
                            <div class="p-6 text-slate text-sm leading-relaxed bg-gradient-to-b from-white to-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                                    <div>
                                        <p class="mb-4 text-base">Welcome to OXXA GEAR Seller Center! Our platform operates on a transparent <strong>10% Commission on Profit</strong> model to keep your base costs completely safe.</p>
                                        <ul class="space-y-3 mb-4">
                                            <li class="flex items-start"><i class="fas fa-check text-[#0066FF] mt-1 me-2"></i> <span><strong>Cost Price</strong> is hidden from buyers. It is your safe money.</span></li>
                                            <li class="flex items-start"><i class="fas fa-check text-[#0066FF] mt-1 me-2"></i> <span>We automatically calculate: <code class="bg-white px-2 py-0.5 rounded shadow-sm border border-gray-100 text-[#0066FF] font-bold">Profit = Selling Price - Cost Price</code></span></li>
                                            <li class="flex items-start"><i class="fas fa-check text-[#0066FF] mt-1 me-2"></i> <span>OXXA takes a small 10% cut of the <strong>Profit only</strong>.</span></li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Visual Example -->
                                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 text-center">Example Calculation</h4>
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-gray-500">You Buy (Cost)</span>
                                            <span class="font-bold text-navy">Rs. 1,000</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-100">
                                            <span class="text-gray-500">You Sell For</span>
                                            <span class="font-bold text-navy">Rs. 1,500</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[#0066FF] font-bold">Profit Margin</span>
                                            <span class="font-bold text-[#0066FF]">Rs. 500</span>
                                        </div>
                                        <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-100">
                                            <span class="text-gray-500 text-xs">OXXA Fee (10% of 500)</span>
                                            <span class="font-bold text-red-500">- Rs. 50</span>
                                        </div>
                                        <div class="flex justify-between items-center bg-blue-50 p-3 rounded-lg border border-blue-100">
                                            <span class="font-black text-[#0066FF]">YOU GET</span>
                                            <span class="font-black text-[#0066FF] text-lg">Rs. 1,450</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 text-center">
                                    <button onclick="document.getElementById('guideContent').classList.add('hidden'); document.getElementById('guideIcon').classList.remove('rotate-180')" class="text-xs font-bold text-gray-400 hover:text-[#0066FF] uppercase tracking-wide">
                                        Got it, hide this guide <i class="fas fa-times ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h2 class="font-black text-navy uppercase tracking-wide text-sm"><i class="fas fa-bolt text-yellow-500 me-2"></i> Recent Orders</h2>
                            </div>
                            <div class="p-8 text-center">
                                <?php if($total_orders == 0): ?>
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300 text-2xl">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                    <p class="text-gray-500 text-sm font-medium mb-1">No orders yet</p>
                                    <p class="text-gray-400 text-xs mb-4">Share your store link on social media to get your first sale!</p>
                                <?php else: ?>
                                    <p class="text-gray-500 text-sm">Loading recent orders...</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h2 class="font-black text-navy uppercase tracking-wide text-sm"><i class="fas fa-exclamation-circle text-red-500 me-2"></i> Needs Attention</h2>
                            </div>
                            <div class="p-8 text-center">
                                <?php if($low_stock == 0): ?>
                                    <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-3 text-green-400 text-2xl">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p class="text-gray-500 text-sm font-medium mb-1">All good!</p>
                                    <p class="text-gray-400 text-xs mb-4">Your inventory is fully stocked.</p>
                                <?php else: ?>
                                    <p class="text-red-500 text-sm font-bold"><i class="fas fa-exclamation-triangle me-2"></i> <?= $low_stock ?> products are low in stock.</p>
                                    <a href="?tab=products" class="text-[#0066FF] text-xs font-bold mt-2 inline-block hover:underline">Update Inventory &rarr;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($tab == 'products'): ?>
                    <?php include('components/seller-products-list.php'); ?>
                <?php elseif ($tab == 'earnings'): ?>
                    <?php include('components/seller-earnings.php'); ?>
                <?php elseif ($tab == 'orders'): ?>
                    <?php include('components/seller-orders.php'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include('../include/footer.php'); ?>