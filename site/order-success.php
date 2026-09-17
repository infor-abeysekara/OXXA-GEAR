<?php
$page_title = 'Order Placed - OXXA GEAR';
include('../include/header.php');

if (!isset($_SESSION['userid'])) {
    echo "<div class='min-h-[60vh] flex flex-col items-center justify-center'><h2 class='text-2xl font-black mb-2'>Please Login</h2></div>";
    include('../include/footer.php');
    exit;
}

$user_id = $_SESSION['userid'];
$order_code = $_GET['id'] ?? '';

if (empty($order_code)) {
    echo "<div class='min-h-[60vh] flex flex-col items-center justify-center'><h2 class='text-2xl font-black text-red-500 mb-2'>Invalid Order ID</h2></div>";
    include('../include/footer.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND user_id = ?");
$stmt->execute([$order_code, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<div class='min-h-[60vh] flex flex-col items-center justify-center'><h2 class='text-2xl font-black text-red-500 mb-2'>Order Not Found</h2></div>";
    include('../include/footer.php');
    exit;
}
?>

<div class="bg-gray-50 min-h-screen py-10 md:py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="bg-white rounded-[2rem] p-8 md:p-12 shadow-xl border border-gray-100">
            <div class="w-24 h-24 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6 relative">
                <i class="fas fa-check text-5xl text-green-500 z-10 relative animate-[ping_1s_ease-out_infinite_reverse]"></i>
                <div class="absolute inset-0 bg-green-100 rounded-full animate-ping opacity-75"></div>
            </div>
            
            <h1 class="text-3xl md:text-4xl font-black text-navy uppercase tracking-widest mb-4">Order Successful!</h1>
            <p class="text-gray-500 mb-2">Thank you for your purchase. Your order has been placed.</p>
            <p class="text-lg font-bold text-navy mb-8">Order ID: <span class="text-primary"><?php echo htmlspecialchars($order['order_code']); ?></span></p>
            
            <div class="bg-gray-50 rounded-2xl p-6 mb-8 text-left border border-gray-200">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Date</p>
                        <p class="font-bold text-navy"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Total Amount</p>
                        <p class="font-bold text-navy">Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Payment Method</p>
                        <p class="font-bold text-navy"><?php echo htmlspecialchars($order['payment_method']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Status</p>
                        <p class="font-bold text-orange-500 capitalize"><?php echo htmlspecialchars($order['status']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="my-orders.php" class="bg-navy hover:bg-gray-800 text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest transition-colors shadow-lg">View My Orders</a>
                <a href="shop.php" class="bg-primary hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest transition-colors shadow-lg shadow-blue-500/30">Continue Shopping</a>
            </div>
        </div>
    </div>
</div>

<?php include('../include/footer.php'); ?>
