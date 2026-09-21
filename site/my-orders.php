<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid'])) {
    header("Location: index.php?open=login");
    exit();
}

$userid = $_SESSION['userid'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userid]);
$user = $stmt->fetch();

// Fetch orders
$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM orders WHERE user_id = ?";
$params = [$userid];

if ($filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter;
} else {
    $query .= " AND status != 'pending_payment'";
}

$query .= " ORDER BY created_at DESC";
$ordersStmt = $pdo->prepare($query);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

include("../include/header.php");
?>

<div class="bg-gray-50 min-h-screen py-10 mt-20">
    <div class="container mx-auto px-4 max-w-6xl">
        
        <div class="flex items-center text-sm text-gray-500 mb-6">
            <a href="index.php" class="hover:text-[#0066FF] transition-colors"><i class="fas fa-home me-2"></i>Home</a>
            <i class="fas fa-chevron-right text-xs mx-3 text-gray-300"></i>
            <span class="text-navy font-bold">My Orders</span>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full lg:w-[260px] flex-shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <div class="p-6 text-center border-b border-gray-50 flex flex-col items-center">
                        <?php if (!empty($user['profile_image']) && file_exists("../assets/uploads/profiles/" . $user['profile_image'])): ?>
                            <img src="../assets/uploads/profiles/<?php echo $user['profile_image']; ?>" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-xl mb-4">
                        <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-black text-white flex items-center justify-center text-3xl font-black ring-4 ring-white shadow-xl mb-4">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="font-black text-navy text-lg uppercase tracking-wide"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <div class="p-2 lg:p-3 flex overflow-x-auto lg:flex-col gap-3 lg:gap-1 hide-scrollbar">
                        <a href="profile.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-user-circle w-6 text-lg"></i> My Profile
                        </a>
                        <a href="address-book.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-address-book w-6 text-lg"></i> Address Book
                        </a>
                        <a href="my-orders.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-shopping-bag w-6 text-lg"></i> My Orders
                        </a>
                        <a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-heart w-6 text-lg"></i> Wishlist
                        </a>
                        <a href="reviews.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-star w-6 text-lg"></i> My Reviews
                        </a>
                        <a href="returns.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-undo-alt w-6 text-lg"></i> My Returns
                        </a>
                        <a href="coupons.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-ticket-alt w-6 text-lg"></i> My Coupons
                        </a>
                        <a href="recently-viewed.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap lg:border-b border-gray-100 lg:pb-4 lg:mb-1">
                            <i class="far fa-eye w-6 text-lg"></i> Recently Viewed
                        </a>
                        <?php if($user['user_type'] == 'seller'): ?>
                        <a href="seller-dashboard.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap lg:mt-2 lg:border-t border-gray-100 lg:pt-3">
                            <i class="fas fa-store w-6 text-lg"></i> Seller Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Main Content -->
            <div class="flex-1">
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden p-8">
                    <h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-6">My Orders</h2>
                    
                    <!-- Filters -->
                    <div class="flex flex-wrap gap-2 mb-8 border-b border-gray-100 pb-4">
                        <a href="?filter=all" class="px-5 py-2 rounded-full text-sm font-bold uppercase transition-colors <?php echo $filter == 'all' ? 'bg-navy text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'; ?>">All Orders</a>
                        <a href="?filter=pending" class="px-5 py-2 rounded-full text-sm font-bold uppercase transition-colors <?php echo $filter == 'pending' ? 'bg-orange-50 text-orange-500 border border-orange-100' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'; ?>">Pending</a>
                        <a href="?filter=confirmed" class="px-5 py-2 rounded-full text-sm font-bold uppercase transition-colors <?php echo $filter == 'confirmed' ? 'bg-blue-50 text-[#0066FF] border border-blue-100' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'; ?>">Confirmed</a>
                        <a href="?filter=delivered" class="px-5 py-2 rounded-full text-sm font-bold uppercase transition-colors <?php echo $filter == 'delivered' ? 'bg-lime/20 text-lime border border-lime/30' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'; ?>">Delivered</a>
                        <a href="?filter=cancelled" class="px-5 py-2 rounded-full text-sm font-bold uppercase transition-colors <?php echo $filter == 'cancelled' ? 'bg-red-50 text-red-500 border border-red-100' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'; ?>">Cancelled</a>
                    </div>
                    
                    <?php if (count($orders) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($orders as $order): ?>
                                <?php
                                    $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? LIMIT 1");
                                    $itemsStmt->execute([$order['id']]);
                                    $firstItem = $itemsStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    $countStmt = $pdo->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = ?");
                                    $countStmt->execute([$order['id']]);
                                    $totalItems = $countStmt->fetchColumn() ?: 0;

                                    $status = $order['status'];
                                    $statusClass = '';
                                    switch($status) {
                                        case 'pending': $statusClass = 'bg-orange-50 text-orange-500 border-orange-100'; break;
                                        case 'pending_payment': $statusClass = 'bg-yellow-50 text-yellow-600 border-yellow-200'; $status = 'Payment Pending'; break;
                                        case 'confirmed': $statusClass = 'bg-blue-50 text-[#0066FF] border-blue-100'; break;
                                        case 'shipped': $statusClass = 'bg-purple-50 text-purple-600 border-purple-100'; break;
                                        case 'delivered': $statusClass = 'bg-lime/20 text-lime border-lime/30'; break;
                                        case 'cancelled': $statusClass = 'bg-red-50 text-red-500 border-red-100'; break;
                                        default: $statusClass = 'bg-gray-50 text-gray-500 border-gray-100';
                                    }
                                ?>
                                <div class="border border-gray-100 rounded-xl p-5 hover:border-blue-100 transition-colors bg-gray-50/30">
                                    <div class="flex flex-col md:flex-row justify-between gap-4">
                                        <div class="flex items-center gap-4">
                                            <?php if($firstItem && !empty($firstItem['product_image'])): ?>
                                                <img src="../assets/uploads/products/<?php echo htmlspecialchars($firstItem['product_image']); ?>" class="w-20 h-20 rounded-lg object-cover bg-white border border-gray-200">
                                            <?php else: ?>
                                                <div class="w-20 h-20 rounded-lg bg-white border border-gray-200 flex items-center justify-center"><i class="fas fa-box text-gray-300 text-xl"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="font-black text-navy">Order #<?php echo htmlspecialchars($order['order_code']); ?></span>
                                                    <span class="px-2 py-0.5 text-[10px] font-black uppercase rounded-md border <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                </div>
                                                <?php if($firstItem): ?>
                                                    <p class="text-sm text-gray-600 font-medium line-clamp-1 mb-1"><?php echo htmlspecialchars($firstItem['product_name']); ?></p>
                                                    <p class="text-xs text-gray-400"><?php echo $totalItems > 1 ? '+' . ($totalItems - 1) . ' more items' : 'Qty: ' . $firstItem['quantity']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex flex-row md:flex-col justify-between items-end gap-2 border-t md:border-t-0 md:border-l border-gray-100 pt-3 md:pt-0 md:pl-4 mt-2 md:mt-0">
                                            <div class="text-right">
                                                <p class="text-xs text-gray-400 font-bold uppercase mb-1">Total Amount</p>
                                                <p class="font-black text-lg text-[#0066FF]">Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                                            </div>
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="text-xs font-bold bg-white border border-gray-200 text-navy px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors uppercase tracking-wide">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-search text-gray-300 text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-navy mb-2">No orders found</h4>
                            <p class="text-gray-500 mb-6">We couldn't find any orders matching this filter.</p>
                            <?php if($filter != 'all'): ?>
                                <a href="?filter=all" class="inline-block px-6 py-2 bg-gray-100 text-navy font-bold rounded-full text-sm uppercase tracking-wide hover:bg-gray-200 transition-colors">Clear Filters</a>
                            <?php else: ?>
                                <a href="products.php" class="inline-block px-8 py-3 bg-[#0066FF] text-white font-bold rounded-full uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">Start Shopping</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../include/footer.php"); ?>
