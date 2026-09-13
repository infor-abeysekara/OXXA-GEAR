<?php
$page_title = 'Seller Dashboard - Nutrition.lk';
include('../include/header.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['userid'];

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_product':
            $productId = sanitizeInput($_POST['product_id'] ?? '');
            $price = sanitizeInput($_POST['price'] ?? '');
            $quantity = sanitizeInput($_POST['quantity'] ?? '');
            $size = sanitizeInput($_POST['size'] ?? '');
            
            if (empty($productId) || empty($price) || empty($quantity)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            // Update product size or create new size
            if (!empty($size)) {
                $checkSizeQuery = "SELECT * FROM productsize WHERE pid = ? AND size = ?";
                $checkSizeStmt = $conn->prepare($checkSizeQuery);
                $checkSizeStmt->bind_param("ss", $productId, $size);
                $checkSizeStmt->execute();
                $sizeExists = $checkSizeStmt->get_result()->num_rows > 0;
                
                if ($sizeExists) {
                    $updateSizeQuery = "UPDATE productsize SET price = ?, qty = ? WHERE pid = ? AND size = ?";
                    $updateSizeStmt = $conn->prepare($updateSizeQuery);
                    $updateSizeStmt->bind_param("ddss", $price, $quantity, $productId, $size);
                } else {
                    $insertSizeQuery = "INSERT INTO productsize (pid, size, price, qty) VALUES (?, ?, ?, ?)";
                    $updateSizeStmt = $conn->prepare($insertSizeQuery);
                    $updateSizeStmt->bind_param("ssdd", $productId, $size, $price, $quantity);
                }
            } else {
                // Update main product
                $updateQuery = "UPDATE production SET price = ?, qty = ? WHERE pid = ? AND user_id = ?";
                $updateSizeStmt = $conn->prepare($updateQuery);
                $updateSizeStmt->bind_param("ddss", $price, $quantity, $productId, $user_id);
            }
            
            if ($updateSizeStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update product']);
            }
            exit;
            
        case 'update_order_status':
            $orderId = sanitizeInput($_POST['order_id'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? '');
            
            if (empty($orderId) || !in_array($status, ['confirmed', 'rejected'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }
            
            try {
                // First, verify that this seller owns products in this order
                $verifyQuery = "SELECT ot.*, p.pname, p.user_id as seller_id, u.firstname, u.lastname, u.email
                                FROM ordertable ot 
                                JOIN production p ON ot.pid = p.pid 
                                JOIN users u ON ot.user_id = u.user_id
                                WHERE ot.orderid = ? AND p.user_id = ?";
                $verifyStmt = $conn->prepare($verifyQuery);
                $verifyStmt->bind_param("ss", $orderId, $user_id);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                
                if ($verifyResult->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
                    exit;
                }
                
                $orderData = $verifyResult->fetch_assoc();
                
                // Update order status in ordertable
                $updateQuery = "UPDATE ordertable SET status = ? WHERE orderid = ? AND pid IN (SELECT pid FROM production WHERE user_id = ?)";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("sss", $status, $orderId, $user_id);
                
                if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                    // Add notification for buyer
                    $message = $status === 'confirmed' ? 
                        "Your order #{$orderId} for {$orderData['pname']} has been confirmed by the seller." :
                        "Your order #{$orderId} for {$orderData['pname']} has been rejected by the seller.";
                    
                    addNotification($conn, $orderData['user_id'], $message, 'order');
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Order status updated successfully',
                        'new_status' => $status
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update order status or no changes made']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_status':
            $productId = sanitizeInput($_POST['product_id'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? '');
            
            if (empty($productId) || !in_array($status, ['active', 'suspended'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }
            
            $updateQuery = "UPDATE production SET status = ? WHERE pid = ? AND user_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("sss", $status, $productId, $user_id);
            
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            exit;
            
        case 'delete_product':
            $productId = sanitizeInput($_POST['product_id'] ?? '');
            
            if (empty($productId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }
            
            // Get product image for deletion
            $imageQuery = "SELECT image FROM production WHERE pid = ? AND user_id = ?";
            $imageStmt = $conn->prepare($imageQuery);
            $imageStmt->bind_param("ss", $productId, $user_id);
            $imageStmt->execute();
            $imageResult = $imageStmt->get_result();
            $product = $imageResult->fetch_assoc();
            
            // Delete from cart first (foreign key constraint)
            $deleteCartQuery = "DELETE FROM cart WHERE PID = ?";
            $deleteCartStmt = $conn->prepare($deleteCartQuery);
            $deleteCartStmt->bind_param("s", $productId);
            $deleteCartStmt->execute();
            
            // Delete product sizes
            $deleteSizesQuery = "DELETE FROM productsize WHERE pid = ?";
            $deleteSizesStmt = $conn->prepare($deleteSizesQuery);
            $deleteSizesStmt->bind_param("s", $productId);
            $deleteSizesStmt->execute();
            
            // Delete from database
            $deleteQuery = "DELETE FROM production WHERE pid = ? AND user_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("ss", $productId, $user_id);
            
            if ($deleteStmt->execute()) {
                // Delete image file if exists
                if (!empty($product['image']) && file_exists('../image/' . $product['image'])) {
                    unlink('../image/' . $product['image']);
                }
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
            }
            exit;
    }
}

// Get seller analytics
$analytics = getSellerAnalytics($conn, $user_id);

// Get seller's products with sizes
$productsQuery = "SELECT p.*, 
                         COALESCE(SUM(ps.qty), p.qty) as total_stock,
                         COALESCE(MIN(ps.price), p.price) as lowest_price
                  FROM production p 
                  LEFT JOIN productsize ps ON p.pid = ps.pid 
                  WHERE p.user_id = ? 
                  GROUP BY p.id 
                  ORDER BY p.Add_date DESC";
$productsStmt = $conn->prepare($productsQuery);
$productsStmt->bind_param("s", $user_id);
$productsStmt->execute();
$productsResult = $productsStmt->get_result();
$products = $productsResult->fetch_all(MYSQLI_ASSOC);

// Get orders for this seller
$ordersQuery = "SELECT ot.*, p.pname, p.image, u.firstname, u.lastname, u.email,
                       CASE WHEN ot.payment_method = 'COD' THEN 'Cash on Delivery' ELSE 'Online Payment' END as payment_display
                FROM ordertable ot 
                JOIN production p ON ot.pid = p.pid 
                JOIN users u ON ot.user_id = u.user_id
                WHERE p.user_id = ? 
                ORDER BY ot.orderdate DESC 
                LIMIT 50";
$ordersStmt = $conn->prepare($ordersQuery);
$ordersStmt->bind_param("s", $user_id);
$ordersStmt->execute();
$ordersResult = $ordersStmt->get_result();
$orders = $ordersResult->fetch_all(MYSQLI_ASSOC);

// Check business registration status
$business = getBusinessRegistration($conn, $user_id);
$canSell = $business && $business['approve'] == 1;
?>

<div class="container my-10 max-w-7xl mx-auto px-4">
    <!-- Page Header -->
    <div class="mb-8 bg-navy p-8 rounded-[2rem] shadow-xl relative overflow-hidden">
        <div class="absolute top-0 right-0 transform translate-x-1/4 -translate-y-1/4 w-64 h-64 bg-primary/20 rounded-full blur-3xl"></div>
        <div class="relative z-10">
            <h2 class="text-3xl font-extrabold text-white uppercase tracking-wider mb-2">
                <i class="fas fa-store text-primary me-2"></i> Seller <span class="text-primary">Dashboard</span>
            </h2>
            <p class="text-gray-400">Manage your products, orders, and track your earnings</p>
        </div>
    </div>

    <!-- Business Status Alert -->
    <?php if (!$canSell): ?>
        <div class="mb-8 bg-orange-50 border border-orange-200 p-6 rounded-xl shadow-sm">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-orange-500 text-2xl me-4 mt-1"></i>
                <div>
                    <h5 class="font-bold text-navy mb-1">Action Required</h5>
                    <?php if (!$business): ?>
                        <p class="text-slate mb-2">You need to register your business before you can manage products.</p>
                        <a href="business-registration.php" class="inline-block bg-primary hover:bg-primary-hover text-white px-5 py-2 rounded-lg font-medium text-sm transition-colors shadow-md">Register Now</a>
                    <?php else: ?>
                        <p class="text-slate">Your business registration is pending admin approval. You can add products but they won't be visible until approved.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-gray-100 flex items-center">
            <div class="w-14 h-14 bg-blue-50 text-primary rounded-full flex items-center justify-center text-2xl me-4 shrink-0">
                <i class="fas fa-box"></i>
            </div>
            <div>
                <h3 class="text-2xl font-black text-navy leading-none mb-1"><?php echo $analytics['total_products']; ?></h3>
                <p class="text-xs text-slate uppercase tracking-wider font-semibold">Total Products</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-gray-100 flex items-center">
            <div class="w-14 h-14 bg-lime/20 text-lime rounded-full flex items-center justify-center text-2xl me-4 shrink-0">
                <i class="fas fa-shopping-cart text-navy"></i>
            </div>
            <div>
                <h3 class="text-2xl font-black text-navy leading-none mb-1"><?php echo $analytics['total_orders']; ?></h3>
                <p class="text-xs text-slate uppercase tracking-wider font-semibold">Total Orders</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-gray-100 flex items-center">
            <div class="w-14 h-14 bg-blue-50 text-primary rounded-full flex items-center justify-center text-2xl me-4 shrink-0">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <h3 class="text-xl font-black text-navy leading-none mb-1"><?php echo formatCurrency($analytics['seller_earnings']); ?></h3>
                <p class="text-xs text-slate uppercase tracking-wider font-semibold">Your Earnings</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-[2rem] shadow-lg border border-gray-100 flex items-center">
            <div class="w-14 h-14 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center text-2xl me-4 shrink-0">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <h3 class="text-2xl font-black text-navy leading-none mb-1"><?php echo $analytics['pending_orders']; ?></h3>
                <p class="text-xs text-slate uppercase tracking-wider font-semibold">Pending Orders</p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mb-8">
        <a href="add-product.php" class="inline-block bg-lime hover:bg-[#a3db42] text-navy px-8 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-md">
            <i class="fas fa-plus me-2"></i> Add New Product
        </a>
    </div>

    <!-- Tabs Navigation -->
    <div class="mb-6 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px text-sm font-bold text-center" id="dashboardTabs" role="tablist">
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 border-primary text-primary hover:text-primary uppercase tracking-wider" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-selected="true">
                    <i class="fas fa-box me-2"></i>My Products
                </button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 border-transparent hover:text-navy hover:border-gray-300 text-slate uppercase tracking-wider transition-colors" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab" aria-selected="false">
                    <i class="fas fa-shopping-cart me-2"></i>Orders
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="dashboardTabContent">
        <!-- Products Tab -->
        <div class="tab-pane fade show active" id="products" role="tabpanel">
            <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
                <div class="bg-navy p-6 flex flex-wrap justify-between items-center gap-4">
                    <h4 class="text-white font-bold text-lg mb-0 m-0">Your Products</h4>
                    <select class="form-select bg-white/10 text-white border border-white/20 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary" id="statusFilter" onchange="filterProducts()">
                        <option value="" class="text-navy">All Status</option>
                        <option value="active" class="text-navy">Active</option>
                        <option value="suspended" class="text-navy">Suspended</option>
                    </select>
                </div>
                
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-widest text-slate">
                                <th class="p-4 font-bold">Product</th>
                                <th class="p-4 font-bold">Category</th>
                                <th class="p-4 font-bold">Price</th>
                                <th class="p-4 font-bold text-center">Stock</th>
                                <th class="p-4 font-bold text-center">Status</th>
                                <th class="p-4 font-bold text-center">Approval</th>
                                <th class="p-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-slate">
                                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                                            <i class="fas fa-box-open fa-2x"></i>
                                        </div>
                                        <p class="mb-4">No products found.</p>
                                        <a href="add-product.php" class="text-primary font-bold hover:underline">Add your first product</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr class="hover:bg-gray-50 transition-colors" data-status="<?php echo $product['status']; ?>" id="product_<?php echo $product['pid']; ?>">
                                        <td class="p-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-16 h-16 bg-white rounded-lg border border-gray-100 p-1 flex items-center justify-center overflow-hidden shrink-0">
                                                    <?php if (!empty($product['image'])): ?>
                                                        <img src="../image/<?php echo htmlspecialchars($product['image']); ?>" class="w-full h-full object-contain">
                                                    <?php else: ?>
                                                        <i class="fas fa-image text-gray-300"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="font-bold text-navy mb-1"><?php echo htmlspecialchars($product['pname']); ?></h6>
                                                    <span class="text-xs font-semibold text-slate uppercase tracking-wider"><?php echo htmlspecialchars($product['brand']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <span class="bg-blue-50 text-primary px-3 py-1 rounded-full text-xs font-bold"><?php echo htmlspecialchars($product['categories']); ?></span>
                                        </td>
                                        <td class="p-4 font-bold text-navy">
                                            <?php 
                                            $sizes = getProductSizes($conn, $product['pid']);
                                            if (!empty($sizes)) {
                                                $minPrice = min(array_column($sizes, 'price'));
                                                $maxPrice = max(array_column($sizes, 'price'));
                                                if ($minPrice == $maxPrice) {
                                                    echo formatCurrency($minPrice);
                                                } else {
                                                    echo formatCurrency($minPrice) . ' - ' . formatCurrency($maxPrice);
                                                }
                                            } else {
                                                echo formatCurrency($product['price']);
                                            }
                                            ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="inline-flex items-center justify-center px-3 py-1 rounded-lg text-xs font-bold <?php echo $product['total_stock'] <= 10 ? 'bg-orange-50 text-orange-600' : 'bg-gray-100 text-gray-700'; ?>">
                                                <?php echo $product['total_stock']; ?>
                                                <?php if ($product['total_stock'] <= 10): ?>
                                                    <i class="fas fa-exclamation-triangle ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?php echo $product['status'] === 'active' ? 'bg-lime/20 text-navy' : 'bg-red-50 text-red-600'; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <?php if ($product['approve'] == 1): ?>
                                                <span class="text-green-500 font-medium text-xs flex items-center justify-center gap-1">
                                                    <i class="fas fa-check-circle"></i> Approved
                                                </span>
                                            <?php else: ?>
                                                <span class="text-orange-500 font-medium text-xs flex items-center justify-center gap-1">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick="editProduct('<?php echo $product['pid']; ?>')" class="w-8 h-8 rounded-lg bg-blue-50 text-primary hover:bg-primary hover:text-white flex items-center justify-center transition-colors" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="viewProductSizes('<?php echo $product['pid']; ?>')" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 flex items-center justify-center transition-colors" title="Sizes">
                                                    <i class="fas fa-layer-group"></i>
                                                </button>
                                                <button onclick="toggleStatus('<?php echo $product['pid']; ?>', '<?php echo $product['status']; ?>')" class="w-8 h-8 rounded-lg bg-orange-50 text-orange-500 hover:bg-orange-500 hover:text-white flex items-center justify-center transition-colors" title="Toggle Status">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <button onclick="deleteProduct('<?php echo $product['pid']; ?>')" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Orders Tab -->
        <div class="tab-pane fade" id="orders" role="tabpanel">
            <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
                <div class="bg-navy p-6 flex flex-wrap justify-between items-center gap-4">
                    <h4 class="text-white font-bold text-lg mb-0 m-0">Recent Orders</h4>
                </div>
                
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-widest text-slate">
                                <th class="p-4 font-bold">Order ID</th>
                                <th class="p-4 font-bold">Product</th>
                                <th class="p-4 font-bold">Customer</th>
                                <th class="p-4 font-bold text-center">Payment</th>
                                <th class="p-4 font-bold text-right">Amount</th>
                                <th class="p-4 font-bold text-center">Status</th>
                                <th class="p-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-slate">
                                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                                            <i class="fas fa-shopping-cart fa-2x"></i>
                                        </div>
                                        <p>No orders yet</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="hover:bg-gray-50 transition-colors" id="order_<?php echo $order['orderid']; ?>">
                                        <td class="p-4">
                                            <strong class="text-navy font-mono">#<?php echo htmlspecialchars($order['orderid']); ?></strong>
                                            <span class="block text-xs text-slate mt-1"><?php echo date('M d, Y', strtotime($order['orderdate'])); ?></span>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 bg-white rounded border border-gray-100 p-1 flex items-center justify-center overflow-hidden shrink-0">
                                                    <?php if (!empty($order['image'])): ?>
                                                        <img src="../image/<?php echo htmlspecialchars($order['image']); ?>" class="w-full h-full object-contain">
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="font-bold text-navy text-xs mb-1 line-clamp-1"><?php echo htmlspecialchars($order['pname']); ?></h6>
                                                    <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded me-1">Size: <?php echo htmlspecialchars($order['size'] ?: 'Standard'); ?></span>
                                                    <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded">Qty: <?php echo $order['qty']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <div class="text-xs">
                                                <strong class="text-navy block mb-1"><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></strong>
                                                <?php if (!empty($order['buyer_contact1'])): ?>
                                                    <span class="block text-slate mb-0.5"><i class="fas fa-phone w-4"></i> <?php echo htmlspecialchars($order['buyer_contact1']); ?></span>
                                                <?php endif; ?>
                                                <span class="block text-slate"><i class="fas fa-envelope w-4"></i> <?php echo htmlspecialchars($order['email']); ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $order['payment_method'] === 'COD' ? 'bg-orange-50 text-orange-600' : 'bg-lime/20 text-navy'; ?>">
                                                <?php echo $order['payment_display']; ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <strong class="text-navy block">Rs. <?php echo number_format($order['price'] * $order['qty'], 2); ?></strong>
                                            <span class="block text-[10px] text-green-600 font-medium mt-1">Earn: Rs. <?php echo number_format(($order['price'] * $order['qty']) * 0.9, 2); ?></span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?php echo $order['status'] === 'confirmed' ? 'bg-lime/20 text-navy' : ($order['status'] === 'rejected' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-primary'); ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <div class="flex items-center justify-end gap-2">
                                                    <button onclick="updateOrderStatus('<?php echo $order['orderid']; ?>', 'confirmed')" class="w-8 h-8 rounded bg-lime hover:bg-[#a3db42] text-navy flex items-center justify-center transition-colors shadow-sm" title="Confirm Order">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button onclick="updateOrderStatus('<?php echo $order['orderid']; ?>', 'rejected')" class="w-8 h-8 rounded bg-red-50 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors shadow-sm" title="Reject Order">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate uppercase tracking-wider font-semibold"><?php echo ucfirst($order['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle Bootstrap tab switching for Tailwind elements
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                // Remove active classes from all tabs
                tabs.forEach(t => {
                    t.classList.remove('border-primary', 'text-primary');
                    t.classList.add('border-transparent', 'text-slate');
                });
                // Add active class to clicked tab
                this.classList.add('border-primary', 'text-primary');
                this.classList.remove('border-transparent', 'text-slate');
                
                // Content switching is handled by Bootstrap JS, but we ensure it works
                const target = document.querySelector(this.dataset.bsTarget);
                if (target) {
                    const panes = document.querySelectorAll('.tab-pane');
                    panes.forEach(p => {
                        p.classList.remove('show', 'active');
                    });
                    target.classList.add('show', 'active');
                }
            });
        });
    });
</script>

<!-- Product Update Modal -->
<div class="modal fade" id="productUpdateModal" tabindex="-1" aria-labelledby="productUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="productUpdateModalLabel">Update Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateProductForm">
                    <input type="hidden" id="update_product_id" name="product_id">
                    <div class="mb-3">
                        <label for="update_price" class="form-label">Price</label>
                        <input type="number" class="form-control" id="update_price" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="update_quantity" name="quantity" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_size" class="form-label">Size (Optional - leave empty for main product)</label>
                        <input type="text" class="form-control" id="update_size" name="size" placeholder="e.g., 1kg, 2.5kg, Large">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitProductUpdate()">Update Product</button>
            </div>
        </div>
    </div>
</div>

<!-- Product Sizes Modal -->
<div class="modal fade" id="productSizesModal" tabindex="-1" aria-labelledby="productSizesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="productSizesModalLabel">Product Sizes & Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sizesModalContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f172a 100%);
    min-height: 100vh;
    padding-top: 2rem;
}

.stat-card {
    background: rgba(55, 65, 81, 0.3);
    border-radius: 15px;
    padding: 2rem;
    border: 1px solid rgba(75, 85, 99, 0.3);
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.stat-icon {
    font-size: 2.5rem;
    color: #10b981;
    margin-right: 1.5rem;
}

.stat-info h3 {
    color: white;
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
}

.stat-info p {
    color: #9ca3af;
    margin: 0;
    font-size: 0.9rem;
}

.dashboard-tabs .nav-link {
    background: rgba(55, 65, 81, 0.3);
    border: 1px solid rgba(75, 85, 99, 0.3);
    color: #9ca3af;
    margin-right: 0.5rem;
    border-radius: 10px 10px 0 0;
}

.dashboard-tabs .nav-link.active {
    background: rgba(31, 41, 55, 0.8);
    color: #10b981;
    border-bottom-color: transparent;
}

.products-table-container, .orders-container {
    background: rgba(55, 65, 81, 0.3);
    border-radius: 15px;
    border: 1px solid rgba(75, 85, 99, 0.3);
    overflow: hidden;
    margin-top: -1px;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(75, 85, 99, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(31, 41, 55, 0.5);
}

.table-filters .form-select {
    background: rgba(31, 41, 55, 0.8);
    border: 1px solid rgba(75, 85, 99, 0.5);
    color: white;
    width: 200px;
}

.products-table, .orders-table {
    margin: 0;
    color: white;
}

.products-table th, .orders-table th {
    background: rgba(31, 41, 55, 0.5);
    border: none;
    color: #d1d5db;
    font-weight: 600;
    padding: 1rem 0.75rem;
}

.products-table td, .orders-table td {
    border: none;
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid rgba(75, 85, 99, 0.2);
}

.product-image-cell, .order-product-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image-cell img, .order-product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    color: #9ca3af;
    font-size: 1.5rem;
}

.product-name-cell strong {
    color: white;
    font-size: 1rem;
}

.price-cell {
    color: #10b981;
    font-weight: 600;
    font-size: 1.1rem;
}

.stock-cell {
    color: white;
    font-weight: 600;
}

.stock-cell.low-stock {
    color: #f59e0b;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-active {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-inactive {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.approval-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.approval-badge.approved {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.approval-badge.pending {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.action-buttons .btn {
    padding: 0.5rem;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.customer-details {
    max-width: 200px;
}

.customer-details small {
    font-size: 0.75rem;
    line-height: 1.2;
}

.form-control, .form-select {
    background: rgba(31, 41, 55, 0.8);
    border: 1px solid rgba(75, 85, 99, 0.5);
    color: white;
}

.form-control:focus, .form-select:focus {
    background: rgba(31, 41, 55, 1);
    border-color: #10b981;
    box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
    color: white;
}

@media (max-width: 768px) {
    .table-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .table-filters .form-select {
        width: 100%;
    }
    
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .customer-details {
        max-width: 150px;
    }
}
</style>

<script>
function filterProducts() {
    const filter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.products-table tbody tr[data-status]');
    
    rows.forEach(row => {
        if (filter === '' || row.dataset.status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function editProduct(productId) {
    document.getElementById('update_product_id').value = productId;
    new bootstrap.Modal(document.getElementById('productUpdateModal')).show();
}

function submitProductUpdate() {
    const form = document.getElementById('updateProductForm');
    const formData = new FormData(form);
    formData.append('action', 'update_product');
    
    fetch('seller-dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product updated successfully!');
            bootstrap.Modal.getInstance(document.getElementById('productUpdateModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating product');
    });
}

function updateOrderStatus(orderId, status) {
    const action = status === 'confirmed' ? 'confirm' : 'reject';
    
    if (confirm(`Are you sure you want to ${action} this order?`)) {
        fetch('seller-dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_order_status&order_id=${orderId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the status display
                const row = document.getElementById(`order_${orderId}`);
                const statusBadge = row.querySelector('.order-status-display');
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusBadge.className = `status-badge order-status-display ${status === 'confirmed' ? 'status-active' : 'status-inactive'}`;
                
                // Update actions column
                const actionsCell = row.querySelector('td:last-child');
                actionsCell.innerHTML = `<span class="text-muted">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                
                alert(`Order ${action}ed successfully! Customer has been notified.`);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating order status');
        });
    }
}

function toggleStatus(productId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'suspend';
    
    if (confirm(`Are you sure you want to ${action} this product?`)) {
        fetch('seller-dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_status&product_id=${productId}&status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the status display
                const row = document.getElementById(`product_${productId}`);
                const statusBadge = row.querySelector('.product-status-display');
                statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                statusBadge.className = `status-badge product-status-display ${newStatus === 'active' ? 'status-active' : 'status-inactive'}`;
                row.dataset.status = newStatus;
                
                alert('Status updated successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating status');
        });
    }
}

function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        fetch('seller-dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_product&product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the row from table
                document.getElementById(`product_${productId}`).remove();
                alert('Product deleted successfully!');
                location.reload(); // Refresh to update statistics
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting product');
        });
    }
}

function viewProductSizes(productId) {
    fetch(`../Backend/get-product-details.php?id=${productId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('sizesModalContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('productSizesModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading product details');
        });
}
</script>

<?php include("../include/footer.php"); ?>