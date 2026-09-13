<?php
$page_title = 'My Orders - OXXA GEAR';
include('../include/header.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];

// Fetch orders for the user, grouped by orderid
// We will join with production table to get product details
$orders_query = "
    SELECT o.orderid, o.status, o.orderdate, o.payment_method, 
           SUM(o.price * o.qty) as total_amount, 
           COUNT(o.pid) as total_items
    FROM ordertable o
    WHERE o.user_id = ?
    GROUP BY o.orderid
    ORDER BY o.orderdate DESC
";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();

$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}

// Helper to determine active step in visual tracking
function getStatusStep($status) {
    switch (strtolower($status)) {
        case 'pending': return 1;
        case 'processing': return 2;
        case 'shipped': return 3;
        case 'delivered': return 4;
        case 'cancelled': return -1;
        default: return 1;
    }
}
?>

<div class="container my-10 max-w-7xl mx-auto px-4">
    <!-- Breadcrumbs -->
    <div class="mb-6 flex items-center text-sm font-medium text-slate">
        <a href="index.php" class="hover:text-primary transition-colors">Home</a>
        <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
        <a href="profile.php" class="hover:text-primary transition-colors">My Profile</a>
        <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
        <span class="text-navy">My Orders</span>
    </div>

    <div class="text-center mb-8">
        <h2 class="text-3xl md:text-4xl font-extrabold text-navy uppercase tracking-wider mb-2">
            Order <span class="text-primary">History</span>
        </h2>
        <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
        <p class="text-slate mt-4">Track, manage and review your recent purchases</p>
    </div>

    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3 mb-6 lg:mb-0">
            <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden sticky top-24">
                <div class="p-6 border-b border-gray-100 bg-navy text-white text-center">
                    <div class="w-20 h-20 bg-gray-600 rounded-full mx-auto mb-3 flex items-center justify-center text-3xl overflow-hidden border-2 border-white shadow-md">
                        <?php if(!empty($_SESSION['image'])): ?>
                            <img src="../image/profile/<?php echo htmlspecialchars($_SESSION['image']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user text-gray-300"></i>
                        <?php endif; ?>
                    </div>
                    <h5 class="font-bold text-lg m-0"><?php echo htmlspecialchars(substr($_SESSION['firstname'], 0, 15)); ?></h5>
                    <p class="text-xs text-gray-400 mt-1 uppercase tracking-widest"><?php echo ucfirst($_SESSION['type']); ?></p>
                </div>
                <div class="p-4">
                    <ul class="space-y-2 m-0 p-0 list-none font-medium">
                        <li>
                            <a href="profile.php" class="flex items-center text-slate hover:text-primary hover:bg-blue-50 px-4 py-3 rounded-xl transition-colors">
                                <i class="fas fa-user-circle w-6"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a href="my-orders.php" class="flex items-center text-primary bg-blue-50 px-4 py-3 rounded-xl transition-colors">
                                <i class="fas fa-shopping-bag w-6"></i> Order History
                            </a>
                        </li>
                        <li>
                            <a href="notifications.php" class="flex items-center text-slate hover:text-primary hover:bg-blue-50 px-4 py-3 rounded-xl transition-colors">
                                <i class="fas fa-heart w-6"></i> Wishlist
                            </a>
                        </li>
                        <li class="pt-2 mt-2 border-t border-gray-100">
                            <a href="../Backend/logout.php" class="flex items-center text-danger hover:bg-red-50 px-4 py-3 rounded-xl transition-colors">
                                <i class="fas fa-sign-out-alt w-6"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Orders Content -->
        <div class="col-lg-9">
            <?php if (empty($orders)): ?>
                <div class="bg-white rounded-[2rem] p-12 shadow-xl border border-gray-100 text-center">
                    <div class="w-32 h-32 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-300">
                        <i class="fas fa-box-open fa-3x"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy mb-3">No Orders Yet</h3>
                    <p class="text-slate mb-8 max-w-md mx-auto">Looks like you haven't made your first purchase yet. Start shopping our premium performance gear!</p>
                    <a href="products.php" class="inline-block bg-primary hover:bg-primary-hover text-white px-8 py-4 rounded-xl font-bold uppercase tracking-wide transition-all shadow-md">
                        Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-8">
                    <?php foreach ($orders as $order): 
                        $statusStep = getStatusStep($order['status']);
                        $isCancelled = ($statusStep === -1);
                    ?>
                        <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
                            <!-- Order Header -->
                            <div class="bg-navy p-6 flex flex-wrap justify-between items-center gap-4">
                                <div>
                                    <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Order Number</p>
                                    <h5 class="text-white font-bold text-lg font-mono">#<?php echo htmlspecialchars($order['orderid']); ?></h5>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Date Placed</p>
                                    <h5 class="text-white font-medium text-sm"><?php echo date('M d, Y • h:i A', strtotime($order['orderdate'])); ?></h5>
                                </div>
                                <div class="text-left md:text-right">
                                    <p class="text-gray-400 text-xs uppercase tracking-widest mb-1">Total Amount</p>
                                    <h5 class="text-primary font-bold text-lg">Rs. <?php echo number_format($order['total_amount'], 2); ?></h5>
                                </div>
                                <div>
                                    <button class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors border border-white/20" type="button" data-bs-toggle="collapse" data-bs-target="#order-<?php echo $order['orderid']; ?>">
                                        View Details <i class="fas fa-chevron-down ms-1 text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="p-6 md:p-8">
                                <!-- Visual Tracking UI -->
                                <div class="mb-8 relative">
                                    <?php if ($isCancelled): ?>
                                        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl font-medium flex items-center justify-center">
                                            <i class="fas fa-times-circle text-red-500 text-xl me-3"></i> 
                                            This order was cancelled.
                                        </div>
                                    <?php else: ?>
                                        <!-- Progress Bar Background -->
                                        <div class="absolute top-5 left-8 right-8 h-1 bg-gray-200 -z-10 rounded-full"></div>
                                        
                                        <!-- Active Progress Bar -->
                                        <div class="absolute top-5 left-8 h-1 bg-primary -z-10 rounded-full transition-all duration-1000" style="width: <?php echo ($statusStep - 1) * 33.33; ?>%"></div>
                                        
                                        <div class="flex justify-between items-center text-center px-4 relative z-0">
                                            <!-- Step 1: Pending -->
                                            <div class="flex flex-col items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold shadow-sm transition-colors mb-3 border-4 border-white
                                                    <?php echo $statusStep >= 1 ? 'bg-primary text-white' : 'bg-gray-200 text-gray-400'; ?>">
                                                    <i class="fas fa-clipboard-list"></i>
                                                </div>
                                                <span class="text-xs font-bold uppercase tracking-wider <?php echo $statusStep >= 1 ? 'text-navy' : 'text-gray-400'; ?>">Pending</span>
                                            </div>

                                            <!-- Step 2: Processing -->
                                            <div class="flex flex-col items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold shadow-sm transition-colors mb-3 border-4 border-white
                                                    <?php echo $statusStep >= 2 ? 'bg-primary text-white' : 'bg-gray-200 text-gray-400'; ?>">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <span class="text-xs font-bold uppercase tracking-wider <?php echo $statusStep >= 2 ? 'text-navy' : 'text-gray-400'; ?>">Processing</span>
                                            </div>

                                            <!-- Step 3: Shipped -->
                                            <div class="flex flex-col items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold shadow-sm transition-colors mb-3 border-4 border-white
                                                    <?php echo $statusStep >= 3 ? 'bg-primary text-white' : 'bg-gray-200 text-gray-400'; ?>">
                                                    <i class="fas fa-truck-fast"></i>
                                                </div>
                                                <span class="text-xs font-bold uppercase tracking-wider <?php echo $statusStep >= 3 ? 'text-navy' : 'text-gray-400'; ?>">Shipped</span>
                                            </div>

                                            <!-- Step 4: Delivered -->
                                            <div class="flex flex-col items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold shadow-sm transition-colors mb-3 border-4 border-white
                                                    <?php echo $statusStep >= 4 ? 'bg-lime text-navy' : 'bg-gray-200 text-gray-400'; ?>">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                                <span class="text-xs font-bold uppercase tracking-wider <?php echo $statusStep >= 4 ? 'text-navy' : 'text-gray-400'; ?>">Delivered</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Collapsible Order Details -->
                            <div class="collapse border-t border-gray-100" id="order-<?php echo $order['orderid']; ?>">
                                <div class="p-6 bg-gray-50">
                                    <h6 class="font-bold text-navy uppercase tracking-wide mb-4 text-sm flex items-center">
                                        <i class="fas fa-shopping-basket text-primary me-2"></i> Order Items (<?php echo $order['total_items']; ?>)
                                    </h6>
                                    
                                    <div class="space-y-4">
                                        <?php
                                        // Fetch items for this specific order
                                        $items_query = "
                                            <truncated in file content>
                                            SELECT o.*, p.pname, p.image, p.brand 
                                            FROM ordertable o
                                            JOIN production p ON o.pid = p.pid
                                            WHERE o.orderid = ?
                                        ";
                                        $items_stmt = $conn->prepare($items_query);
                                        $items_stmt->bind_param("s", $order['orderid']);
                                        $items_stmt->execute();
                                        $items_result = $items_stmt->get_result();
                                        
                                        while ($item = $items_result->fetch_assoc()):
                                        ?>
                                            <div class="flex items-center gap-4 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                                                <div class="shrink-0 w-16 h-16 bg-gray-50 rounded-lg p-1 border border-gray-100 flex items-center justify-center overflow-hidden">
                                                    <?php if (!empty($item['image'])): ?>
                                                        <img src="../image/<?php echo htmlspecialchars($item['image']); ?>" class="w-full h-full object-contain">
                                                    <?php else: ?>
                                                        <i class="fas fa-image text-gray-300"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow min-w-0">
                                                    <h6 class="font-bold text-navy text-sm mb-1 truncate"><?php echo htmlspecialchars($item['pname']); ?></h6>
                                                    <p class="text-xs text-slate mb-1">
                                                        <span class="uppercase tracking-wider"><?php echo htmlspecialchars($item['brand']); ?></span>
                                                        <?php if (!empty($item['size']) && $item['size'] != 'Standard'): ?>
                                                            <span class="mx-1">•</span> <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px]"><?php echo htmlspecialchars($item['size']); ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <div class="flex justify-between items-center mt-2">
                                                        <span class="text-xs font-medium text-slate">Qty: <?php echo $item['qty']; ?></span>
                                                        <span class="font-bold text-navy">Rs. <?php echo number_format($item['price'], 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    
                                    <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between items-center">
                                        <div class="text-sm">
                                            <span class="text-slate uppercase tracking-widest text-[10px] block mb-1">Payment Method</span>
                                            <span class="font-bold text-navy flex items-center">
                                                <?php if($order['payment_method'] === 'COD'): ?>
                                                    <i class="fas fa-money-bill-wave text-primary me-2"></i> Cash on Delivery
                                                <?php else: ?>
                                                    <i class="fas fa-credit-card text-primary me-2"></i> Online Payment
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($statusStep === 4): ?>
                                            <button class="bg-navy hover:bg-primary text-white px-5 py-2 rounded-lg font-medium transition-colors text-sm shadow-sm" onclick="showToast('Invoice downloading...', 'info')">
                                                <i class="fas fa-file-invoice me-1"></i> Invoice
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Collapse transitions */
    .collapse {
        transition: height 0.3s ease-in-out;
    }
</style>

<?php include('../include/footer.php'); ?>
