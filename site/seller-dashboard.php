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

// 2. My Earnings & Orders (Requires querying seller_payouts)
$earningStmt = $pdo->prepare("SELECT COUNT(DISTINCT order_item_id) as total_orders, SUM(seller_earning) as total_earned FROM seller_payouts WHERE seller_id = ?");
$earningStmt->execute([$_SESSION['userid']]);
$earningData = $earningStmt->fetch(PDO::FETCH_ASSOC);
$total_orders = $earningData['total_orders'] ?? 0;
$total_earned = $earningData['total_earned'] ?? 0.00;

?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Seller Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <?php if (!empty($business['logo_path'])): ?>
                    <img src="../assets/uploads/<?= htmlspecialchars($business['logo_path']) ?>" class="w-16 h-16 rounded-full object-cover ring-4 ring-white shadow-sm">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full bg-[#0066FF] text-white flex items-center justify-center text-2xl font-black shadow-sm">
                        <?= strtoupper(substr($business['business_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-2xl font-black text-navy uppercase tracking-wide"><?= htmlspecialchars($business['business_name']) ?></h1>
                    <p class="text-sm text-slate"><i class="fas fa-check-circle text-green-500 me-1"></i> Verified Seller</p>
                </div>
            </div>
            
            <a href="seller-add-product.php" class="bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30 flex items-center">
                <i class="fas fa-plus me-2"></i> Add Product
            </a>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Navigation -->
            <div class="w-full lg:w-64 shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <nav class="flex flex-col p-2 gap-1">
                        <a href="?tab=dashboard" class="flex items-center px-4 py-3 rounded-xl transition-colors <?= $tab == 'dashboard' ? 'bg-blue-50 text-[#0066FF] font-bold' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <i class="fas fa-chart-pie w-6"></i> Overview
                        </a>
                        <a href="?tab=products" class="flex items-center px-4 py-3 rounded-xl transition-colors <?= $tab == 'products' ? 'bg-blue-50 text-[#0066FF] font-bold' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <i class="fas fa-box w-6"></i> My Products
                        </a>
                        <a href="?tab=orders" class="flex items-center px-4 py-3 rounded-xl transition-colors <?= $tab == 'orders' ? 'bg-blue-50 text-[#0066FF] font-bold' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <i class="fas fa-shopping-cart w-6"></i> Orders
                        </a>
                        <a href="?tab=earnings" class="flex items-center px-4 py-3 rounded-xl transition-colors <?= $tab == 'earnings' ? 'bg-blue-50 text-[#0066FF] font-bold' : 'text-slate hover:bg-gray-50 hover:text-navy font-medium' ?>">
                            <i class="fas fa-wallet w-6"></i> Earnings & Payouts
                        </a>
                        <div class="h-px bg-gray-100 my-2 mx-4"></div>
                        <a href="business-registration.php" class="flex items-center px-4 py-3 rounded-xl transition-colors text-slate hover:bg-gray-50 hover:text-navy font-medium">
                            <i class="fas fa-id-card w-6"></i> Business Profile
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="flex-1 min-w-0">
                <?php if ($tab == 'dashboard'): ?>
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-50 rounded-full opacity-50 pointer-events-none"></div>
                            <p class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-1">Total Earned</p>
                            <h3 class="text-3xl font-black text-navy">Rs. <?= number_format($total_earned, 2) ?></h3>
                            <div class="mt-4 flex items-center text-xs font-bold text-[#0066FF] bg-blue-50 w-max px-3 py-1 rounded-full">
                                <i class="fas fa-wallet me-2"></i> Payouts Processing
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-purple-50 rounded-full opacity-50 pointer-events-none"></div>
                            <p class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-1">Products</p>
                            <h3 class="text-3xl font-black text-navy"><?= number_format($total_products) ?></h3>
                            <a href="?tab=products" class="mt-4 flex items-center text-xs font-bold text-purple-600 bg-purple-50 w-max px-3 py-1 rounded-full hover:bg-purple-100 transition-colors">
                                Manage Products <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-green-50 rounded-full opacity-50 pointer-events-none"></div>
                            <p class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-1">Total Orders</p>
                            <h3 class="text-3xl font-black text-navy"><?= number_format($total_orders) ?></h3>
                            <a href="?tab=orders" class="mt-4 flex items-center text-xs font-bold text-green-600 bg-green-50 w-max px-3 py-1 rounded-full hover:bg-green-100 transition-colors">
                                View Orders <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Recent Orders preview could go here -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h2 class="font-black text-navy uppercase tracking-wide">Quick Guide: The 10% Rule</h2>
                        </div>
                        <div class="p-6 text-slate text-sm leading-relaxed">
                            <p class="mb-3">Welcome to OXXA GEAR Seller Center! Our platform operates on a transparent <strong>10% Commission on Profit</strong> model.</p>
                            <ul class="list-disc pl-5 space-y-2 mb-4">
                                <li>When you add a product, you specify the <strong>Cost Price</strong> (hidden from buyers) and the <strong>Selling Price</strong>.</li>
                                <li>We automatically calculate your Profit: <code class="bg-gray-100 px-2 py-1 rounded">Profit = Selling Price - Cost Price</code>.</li>
                                <li>OXXA takes a small 10% cut of the <strong>Profit only</strong>.</li>
                                <li>You earn back your full Cost Price + 90% of the Profit!</li>
                            </ul>
                            <div class="bg-blue-50 text-[#0066FF] p-4 rounded-xl border border-blue-100 flex items-start">
                                <i class="fas fa-info-circle mt-0.5 me-3"></i>
                                <p><strong>Important:</strong> All new products require Admin Approval before they go live on the store. This ensures quality and authentic gear for our customers.</p>
                            </div>
                        </div>
                    </div>

                <?php elseif ($tab == 'products'): ?>
                    <!-- Products Tab will be fetched via AJAX or built inline -->
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