<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid'])) {
    header("Location: index.php?open=login");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: my-orders.php");
    exit();
}

$order_id = $_GET['id'];
$userid = $_SESSION['userid'];

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $userid]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: my-orders.php");
    exit();
}

// Fetch order items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order_id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// User info
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userid]);
$user = $userStmt->fetch();

include("../include/header.php");

// Determine Timeline Progress
$status = strtolower($order['status']);
$currentStatusIndex = 0; // Default: Ordered

if ($status === 'cancelled') {
    $currentStatusIndex = -1;
} elseif (in_array($status, ['completed', 'delivered', 'return_window'])) {
    $currentStatusIndex = 4; // Delivered
} elseif (in_array($status, ['out_for_delivery', 'shipped'])) {
    $currentStatusIndex = 3; // Shipped
} elseif (in_array($status, ['packed', 'received_at_center', 'handover_to_center'])) {
    $currentStatusIndex = 2; // At Center / Packed
} elseif (in_array($status, ['confirmed', 'accepted'])) {
    $currentStatusIndex = 1; // Accepted
} else {
    $currentStatusIndex = 0; // Pending
}
?>

<div class="bg-gray-50 min-h-screen py-10 mt-20">
    <div class="container mx-auto px-4 max-w-6xl">
        <div class="flex items-center text-sm text-gray-500 mb-6">
            <a href="index.php" class="hover:text-[#0066FF] transition-colors"><i class="fas fa-home me-2"></i>Home</a>
            <i class="fas fa-chevron-right text-xs mx-3 text-gray-300"></i>
            <a href="my-orders.php" class="hover:text-[#0066FF] transition-colors">My Orders</a>
            <i class="fas fa-chevron-right text-xs mx-3 text-gray-300"></i>
            <span class="text-navy font-bold">Order #<?php echo htmlspecialchars($order['order_code']); ?></span>
        </div>

        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="p-8 border-b border-gray-100">
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-navy uppercase tracking-wide">Order Details</h2>
                        <p class="text-gray-500 mt-1">Placed on <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <?php if($order['status'] == 'shipped' && !empty($order['tracking_number'])): ?>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-center md:text-right">
                        <p class="text-[10px] font-bold text-[#0066FF] uppercase tracking-widest mb-1">Track on <?php echo htmlspecialchars($order['courier_company']); ?></p>
                        <p class="text-lg font-black text-navy uppercase"><?php echo htmlspecialchars($order['tracking_number']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="p-8 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-navy uppercase tracking-wide mb-8">Order Status</h3>
                
                <?php if($order['status'] == 'cancelled'): ?>
                    <div class="bg-red-50 text-red-500 p-4 rounded-xl font-bold border border-red-100 text-center">
                        <i class="fas fa-times-circle me-2"></i> This order has been cancelled.
                    </div>
                <?php else: ?>
                    <div class="relative flex justify-between items-center w-full max-w-4xl mx-auto px-4 md:px-0">
                        <!-- Progress Line -->
                        <div class="absolute left-4 right-4 md:left-0 md:right-0 top-1/2 -translate-y-1/2 h-1 bg-gray-200 rounded-full z-0"></div>
                        <div class="absolute left-4 md:left-0 top-1/2 -translate-y-1/2 h-1 bg-[#0066FF] rounded-full z-0 transition-all duration-1000" style="width: <?php echo ($currentStatusIndex >= 4 ? 'calc(100% - 2rem)' : ($currentStatusIndex * 25) . '%'); ?>; md:width: <?php echo ($currentStatusIndex >= 4 ? '100' : ($currentStatusIndex * 25)); ?>%;"></div>
                        
                        <!-- Step 1: Ordered -->
                        <div class="relative z-10 flex flex-col items-center bg-gray-50 md:bg-transparent">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors <?php echo $currentStatusIndex >= 0 ? 'bg-[#0066FF] text-white shadow-lg shadow-blue-500/30' : 'bg-white border-2 border-gray-200 text-gray-400'; ?>">
                                <i class="fas fa-clipboard-list text-xs md:text-sm"></i>
                            </div>
                            <p class="text-[9px] md:text-xs font-bold mt-2 md:mt-3 uppercase tracking-wide text-center <?php echo $currentStatusIndex >= 0 ? 'text-navy' : 'text-gray-400'; ?>">Ordered</p>
                        </div>
                        
                        <!-- Step 2: Accepted -->
                        <div class="relative z-10 flex flex-col items-center bg-gray-50 md:bg-transparent">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors <?php echo $currentStatusIndex >= 1 ? 'bg-[#0066FF] text-white shadow-lg shadow-blue-500/30' : 'bg-white border-2 border-gray-200 text-gray-400'; ?>">
                                <i class="fas fa-thumbs-up text-xs md:text-sm"></i>
                            </div>
                            <p class="text-[9px] md:text-xs font-bold mt-2 md:mt-3 uppercase tracking-wide text-center <?php echo $currentStatusIndex >= 1 ? 'text-navy' : 'text-gray-400'; ?>">Accepted</p>
                        </div>

                        <!-- Step 3: At Center -->
                        <div class="relative z-10 flex flex-col items-center bg-gray-50 md:bg-transparent">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors <?php echo $currentStatusIndex >= 2 ? 'bg-[#0066FF] text-white shadow-lg shadow-blue-500/30' : 'bg-white border-2 border-gray-200 text-gray-400'; ?>">
                                <i class="fas fa-building-circle-check text-xs md:text-sm"></i>
                            </div>
                            <p class="text-[9px] md:text-xs font-bold mt-2 md:mt-3 uppercase tracking-wide text-center <?php echo $currentStatusIndex >= 2 ? 'text-navy' : 'text-gray-400'; ?>">At Center</p>
                        </div>
                        
                        <!-- Step 4: Shipped -->
                        <div class="relative z-10 flex flex-col items-center bg-gray-50 md:bg-transparent">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors <?php echo $currentStatusIndex >= 3 ? 'bg-[#0066FF] text-white shadow-lg shadow-blue-500/30' : 'bg-white border-2 border-gray-200 text-gray-400'; ?>">
                                <i class="fas fa-truck-fast text-xs md:text-sm"></i>
                            </div>
                            <p class="text-[9px] md:text-xs font-bold mt-2 md:mt-3 uppercase tracking-wide text-center <?php echo $currentStatusIndex >= 3 ? 'text-navy' : 'text-gray-400'; ?>">Shipped</p>
                        </div>
                        
                        <!-- Step 5: Delivered -->
                        <div class="relative z-10 flex flex-col items-center bg-gray-50 md:bg-transparent">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors <?php echo $currentStatusIndex >= 4 ? 'bg-green-500 text-white shadow-lg shadow-green-500/30' : 'bg-white border-2 border-gray-200 text-gray-400'; ?>">
                                <i class="fas fa-check-circle text-xs md:text-sm"></i>
                            </div>
                            <p class="text-[9px] md:text-xs font-bold mt-2 md:mt-3 uppercase tracking-wide text-center <?php echo $currentStatusIndex >= 4 ? 'text-green-500' : 'text-gray-400'; ?>">Delivered</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <div class="p-8">
                <h3 class="font-bold text-navy uppercase tracking-wide mb-6">Items in your order</h3>
                <div class="space-y-4">
                    <?php foreach($items as $item): ?>
                        <div class="border border-gray-100 rounded-xl p-5 flex flex-col md:flex-row justify-between gap-4 bg-gray-50/30">
                            <div class="flex items-center gap-4">
                                <?php if(!empty($item['product_image'])): ?>
                                    <img src="../assets/uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" class="w-20 h-20 rounded-lg object-cover bg-white border border-gray-200">
                                <?php else: ?>
                                    <div class="w-20 h-20 rounded-lg bg-white border border-gray-200 flex items-center justify-center"><i class="fas fa-box text-gray-300 text-xl"></i></div>
                                <?php endif; ?>
                                <div>
                                    <a href="product-details.php?id=<?php echo $item['product_id']; ?>" class="font-black text-navy text-lg hover:text-[#0066FF] transition-colors block mb-1">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Variant: <?php echo htmlspecialchars($item['size']); ?></p>
                                    <p class="text-sm text-gray-600 font-bold">Qty: <?php echo $item['quantity']; ?> x Rs. <?php echo number_format($item['unit_price'], 2); ?></p>
                                </div>
                            </div>
                            
                            <!-- Review Button (Only if delivered) -->
                            <div class="flex items-center justify-end md:border-l border-gray-100 md:pl-6 pt-4 md:pt-0 border-t md:border-t-0 mt-2 md:mt-0">
                                <?php 
                                // Check if user already reviewed this item
                                $revCheck = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? AND product_id = ?");
                                $revCheck->execute([$order_id, $item['product_id']]);
                                $hasReviewed = $revCheck->fetch();

                                if ($currentStatusIndex >= 3 && !$hasReviewed): 
                                ?>
                                    <button onclick="openReviewModal(<?php echo $order_id; ?>, <?php echo $item['product_id']; ?>)" class="px-6 py-2 bg-black text-white font-bold rounded-lg uppercase tracking-wide text-xs hover:bg-[#0066FF] transition-colors">
                                        Write a Review
                                    </button>
                                <?php elseif ($hasReviewed): ?>
                                    <span class="px-4 py-2 bg-green-50 text-green-600 font-bold rounded-lg uppercase tracking-wide text-xs border border-green-100">
                                        <i class="fas fa-check-circle me-1"></i> Reviewed
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include("components/review-modal.php"); ?>

<script>
function openReviewModal(order_id, product_id) {
    document.getElementById('review_order_id').value = order_id;
    document.getElementById('review_product_id').value = product_id;
    const modal = document.getElementById('reviewModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

<?php include("../include/footer.php"); ?>
