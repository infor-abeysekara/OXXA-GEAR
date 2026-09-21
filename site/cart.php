<?php
$page_title = 'Shopping Cart - OXXA GEAR';
include('../include/header.php');

if (!isset($_SESSION['userid'])) {
    echo "<div class='min-h-[60vh] flex flex-col items-center justify-center'><i class='fas fa-lock text-6xl text-gray-200 mb-4'></i><h2 class='text-2xl font-black mb-2'>Please Login</h2><p class='text-gray-500 mb-6'>You must be logged in to view your cart.</p><button onclick='openAuthModal(\"login\")' class='bg-navy text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest'>Login Now</button></div>";
    include('../include/footer.php');
    exit;
}

$user_id = $_SESSION['userid'];

// Fetch cart items
$query = "
    SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.base_price, 
           p.is_hot_deal, p.sale_price, p.original_price, p.discount_percent, p.hot_deal_status, p.hot_deal_expiry,
           cs.id as variant_id, cs.size, pc.color_name as color, cs.selling_price as variant_price, cs.qty as stock,
           COALESCE(
               (SELECT ci.image_path FROM color_images ci WHERE ci.color_id = pc.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1),
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1)
           ) as image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN color_sizes cs ON c.variant_id = cs.id
    LEFT JOIN product_colors pc ON cs.color_id = pc.id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$subtotal = 0;
while ($row = $result->fetch_assoc()) {
    $basePrice = (!empty($row['variant_price']) && $row['variant_price'] > 0) ? (float)$row['variant_price'] : (float)$row['base_price'];
    $isHotDeal = ($row['is_hot_deal'] == 1 && $row['hot_deal_status'] === 'approved' && 
                  (empty($row['hot_deal_expiry']) || strtotime($row['hot_deal_expiry']) >= time()));
    
    if ($isHotDeal && !empty($row['sale_price']) && (float)$row['sale_price'] > 0) {
        $price = (float)$row['sale_price'];
        $row['is_hot_deal'] = true;
        $row['original_price'] = (float)($row['original_price'] > 0 ? $row['original_price'] : $basePrice);
    } else {
        $price = $basePrice;
        $row['is_hot_deal'] = false;
        $row['original_price'] = $basePrice;
    }

    $row['calculated_price'] = $price;
    $row['item_total'] = $price * $row['quantity'];
    $row['variant_label'] = ($row['size'] || $row['color']) ? trim($row['size'] . ' ' . $row['color']) : 'Standard';
    $subtotal += $row['item_total'];
    $cart_items[] = $row;
}
?>

<div class="bg-gray-50 min-h-screen py-10 md:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-black text-navy uppercase tracking-widest mb-8">Shopping Cart</h1>
        
        <?php if (empty($cart_items)): ?>
            <div class="bg-white rounded-[2rem] p-12 text-center shadow-sm">
                <div class="w-32 h-32 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shopping-cart text-5xl text-gray-300"></i>
                </div>
                <h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-2">Your Cart is Empty</h2>
                <p class="text-gray-500 mb-8 max-w-md mx-auto">Looks like you haven't added anything to your cart yet. Discover our premium collections and gear up.</p>
                <a href="shop.php" class="inline-flex bg-primary hover:bg-primary-hover text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest shadow-lg shadow-blue-500/30 transition-all">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Cart Items -->
                <div class="w-full lg:w-2/3">
                    <div class="bg-white rounded-[2rem] p-6 shadow-sm space-y-6">
                        <?php foreach($cart_items as $item): ?>
                            <div class="flex items-center gap-4 pb-6 border-b border-gray-100 last:border-0 last:pb-0 relative group">
                                <!-- Remove Button -->
                                <button onclick="updateCartPageItem(<?php echo $item['cart_id']; ?>, 0)" class="absolute top-0 right-0 w-8 h-8 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-full transition-colors z-10">
                                    <i class="fas fa-times"></i>
                                </button>
                                
                                <div class="w-24 h-24 sm:w-32 sm:h-32 bg-[#F8F9FA] rounded-xl flex-shrink-0 flex items-center justify-center p-2">
                                    <img src="<?php echo $item['image'] ? '../assets/uploads/products/' . $item['image'] : '../image/placeholder.png'; ?>" class="w-full h-full object-contain mix-blend-multiply">
                                </div>
                                
                                <div class="flex-grow min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="text-sm sm:text-base font-black text-navy uppercase tracking-wide truncate pr-8"><a href="product-details.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                                    </div>
                                    <p class="text-xs sm:text-sm font-bold text-gray-500 mb-2"><?php echo htmlspecialchars($item['variant_label']); ?></p>
                                    
                                    <div class="flex flex-wrap items-center justify-between gap-4 mt-4">
                                        <div class="flex items-baseline gap-2">
                                            <?php if(!empty($item['is_hot_deal'])): ?>
                                                <span class="text-xs text-gray-400 line-through">Rs. <?php echo number_format($item['original_price'], 0); ?></span>
                                                <span class="text-lg font-black text-emerald-600">Rs. <?php echo number_format($item['calculated_price'], 0); ?></span>
                                                <span class="bg-red-50 text-red-600 text-[10px] font-black px-1.5 py-0.5 rounded uppercase tracking-wider">🔥 -<?php echo $item['discount_percent']; ?>%</span>
                                            <?php else: ?>
                                                <div class="text-lg font-black text-navy">Rs. <?php echo number_format($item['calculated_price'], 0); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden bg-gray-50 h-10">
                                            <button onclick="updateCartPageItem(<?php echo $item['cart_id']; ?>, <?php echo $item['quantity'] - 1; ?>)" class="w-10 h-full flex items-center justify-center text-gray-500 hover:text-navy hover:bg-gray-100 transition-colors"><i class="fas fa-minus text-xs"></i></button>
                                            <span class="w-12 h-full flex items-center justify-center font-black text-navy text-sm border-x border-gray-200 bg-white"><?php echo $item['quantity']; ?></span>
                                            <button onclick="updateCartPageItem(<?php echo $item['cart_id']; ?>, <?php echo $item['quantity'] + 1; ?>)" class="w-10 h-full flex items-center justify-center text-gray-500 hover:text-navy hover:bg-gray-100 transition-colors"><i class="fas fa-plus text-xs"></i></button>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right mt-2 w-full text-xs font-bold text-primary">
                                        Total: Rs. <?php echo number_format($item['item_total'], 0); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="w-full lg:w-1/3">
                    <div class="bg-white rounded-[2rem] p-6 shadow-sm sticky top-24">
                        <h3 class="text-xl font-black text-navy uppercase tracking-widest mb-6">Order Summary</h3>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between items-center text-sm font-bold text-gray-500">
                                <span>Subtotal</span>
                                <span>Rs. <?php echo number_format($subtotal, 0); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-bold text-gray-500">
                                <span>Delivery</span>
                                <span>Calculated at checkout</span>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-100 pt-4 mb-8">
                            <div class="flex justify-between items-end">
                                <span class="font-black text-navy uppercase tracking-widest">Total</span>
                                <span class="text-3xl font-black text-navy">Rs. <?php echo number_format($subtotal, 0); ?></span>
                            </div>
                            <!-- KOKO Split -->
                            <div class="flex items-center justify-end gap-2 mt-2">
                                <span class="text-xs font-bold text-gray-400">Pay in 3 of Rs. <?php echo number_format($subtotal / 3, 0); ?> with</span>
                                <img src="../image/KOKO_logo.png" class="h-3 w-auto opacity-70" alt="KOKO">
                            </div>
                        </div>
                        
                        <a href="checkout.php" class="block w-full bg-primary hover:bg-blue-700 text-white text-center rounded-xl font-black uppercase tracking-widest py-4 shadow-xl shadow-blue-500/30 transition-all mb-4">
                            Proceed to Checkout
                        </a>
                        
                        <div class="flex items-center justify-center gap-3 opacity-50">
                            <i class="fab fa-cc-visa text-2xl"></i>
                            <i class="fab fa-cc-mastercard text-2xl"></i>
                            <span class="font-bold text-xs tracking-widest border border-gray-400 px-1 rounded">COD</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateCartPageItem(cartId, newQty) {
    if (newQty === 0) {
        Swal.fire({
            title: 'Remove Item?',
            text: "Are you sure you want to remove this item?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff3333',
            cancelButtonColor: '#0A0A0A',
            confirmButtonText: 'Yes, remove it'
        }).then((result) => {
            if (result.isConfirmed) {
                processUpdate(cartId, 0);
            }
        });
    } else {
        processUpdate(cartId, newQty);
    }
}

function processUpdate(cartId, newQty) {
    fetch('../Backend/update_cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: newQty === 0 ? 'remove' : 'update',
            cart_id: cartId,
            qty: newQty
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            showToast(data.message || 'Failed to update', 'warning');
        }
    })
    .catch(err => showToast('Network Error', 'error'));
}
</script>

<?php include('../include/footer.php'); ?>
