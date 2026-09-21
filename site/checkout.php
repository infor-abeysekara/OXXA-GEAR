<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

// Get user details
$user_id = $_SESSION['userid'];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
// Get user addresses
$addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default_shipping DESC, id DESC");
$addrStmt->execute([$user_id]);
$addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart items via PDO

$cartStmt = $pdo->prepare("
    SELECT c.product_id, c.variant_id, c.quantity as qty,
           p.name as pname, p.base_price, p.seller_id,
           COALESCE(sp.business_name, CONCAT(u.first_name, ' ', u.last_name), 'OXXA Official Store') as seller_name,
           p.is_hot_deal, p.sale_price, p.original_price, p.hot_deal_status, p.hot_deal_expiry,
           p.is_free_shipping, p.shipping_cost,
           cs.size, cs.selling_price as variant_price,
           COALESCE(
               (SELECT ci.image_path FROM color_images ci WHERE ci.color_id = cs.color_id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1),
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1)
           ) as image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
    LEFT JOIN users u ON u.id = p.seller_id
    LEFT JOIN color_sizes cs ON c.variant_id = cs.id
    WHERE c.user_id = ?
");
$cartStmt->execute([$user_id]);
$cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize price and group by seller
$sellerGroups = [];
foreach ($cartItems as &$item) {
    $basePrice = (!empty($item['variant_price']) && $item['variant_price'] > 0) ? (float)$item['variant_price'] : (float)$item['base_price'];
    $isHotDeal = ($item['is_hot_deal'] == 1 && $item['hot_deal_status'] === 'approved' && 
                  (empty($item['hot_deal_expiry']) || strtotime($item['hot_deal_expiry']) >= time()));
    
    if ($isHotDeal && !empty($item['sale_price']) && (float)$item['sale_price'] > 0) {
        $item['price'] = (float)$item['sale_price'];
        $item['is_hot_deal'] = true;
    } else {
        $item['price'] = $basePrice;
        $item['is_hot_deal'] = false;
    }

    $sId = $item['seller_id'] ?: 0;
    if (!isset($sellerGroups[$sId])) {
        $sellerGroups[$sId] = [
            'seller_name' => $item['seller_name'] ?: 'OXXA Official Store',
            'items' => []
        ];
    }
    $sellerGroups[$sId]['items'][] = $item;
}

// If cart is empty, redirect to shop page
if (empty($cartItems)) {
    header('Location: shop.php');
    exit;
}

// Calculate totals
$subtotal = 0;
$allFreeShipping = true;
foreach ($cartItems as $item) {
    $subtotal += ($item['price'] * $item['qty']);
    if (empty($item['is_free_shipping'])) {
        $allFreeShipping = false;
    }
}

$isFreeShipping = ($allFreeShipping && count($cartItems) > 0);
$deliveryFee = ($subtotal > 0 && !$isFreeShipping) ? 300.00 : 0.00;

$discount = 0;
$couponCode = '';
$total = $subtotal + $deliveryFee;

// Get Sri Lankan provinces
$provinces = getSriLankanProvinces();
?>

<?php
// Set page title for header
$page_title = "Secure Checkout - OXXA GEAR";
include('../include/header.php');
?>

    <div class="my-10 max-w-7xl mx-auto px-4">
        <!-- Breadcrumbs -->
        <div class="mb-6 flex items-center text-sm font-medium text-slate">
            <a href="index.php" class="hover:text-primary transition-colors">Home</a>
            <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
            <a href="products.php" class="hover:text-primary transition-colors">Shop</a>
            <i class="fas fa-chevron-right mx-2 text-[10px]"></i>
            <span class="text-navy">Checkout</span>
        </div>

        <div class="text-center mb-8">
            <h2 class="text-3xl md:text-4xl font-extrabold text-navy uppercase tracking-wider mb-2">
                Secure <span class="text-primary">Checkout</span>
            </h2>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
            <p class="text-slate mt-4">Complete your order details below</p>
        </div>

        <!-- Mobile Order Summary Collapsible (Hidden on Desktop) -->
        <div class="lg:hidden mb-6">
            <button onclick="document.getElementById('mobileOrderSummary').classList.toggle('hidden'); document.getElementById('mobileSummaryChevron').classList.toggle('rotate-180')" class="w-full flex justify-between items-center p-4 bg-white rounded-2xl shadow-sm border border-gray-100 font-bold text-navy">
                <span><i class="fas fa-shopping-bag text-primary me-2"></i> Order Summary (<?php echo count($cartItems); ?>) - Rs. <?php echo number_format($total, 2); ?></span>
                <i id="mobileSummaryChevron" class="fas fa-chevron-down transition-transform duration-300 text-gray-400"></i>
            </button>
            <div id="mobileOrderSummary" class="hidden mt-4">
                <div class="bg-white rounded-2xl p-4 shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-gray-100">
                    <?php foreach ($sellerGroups as $group): ?>
                        <div class="border border-gray-100 rounded-xl p-3 bg-white shadow-sm mb-3">
                            <div class="flex items-center justify-between pb-2 mb-3 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-store text-primary text-[10px]"></i>
                                    <span class="text-[11px] font-black text-navy uppercase tracking-wider"><?php echo htmlspecialchars($group['seller_name']); ?></span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <?php foreach ($group['items'] as $item): ?>
                                <div class="flex items-center gap-3 bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                    <div class="shrink-0 w-14 h-14 bg-white rounded-xl p-1 flex items-center justify-center overflow-hidden">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($base_path . 'assets/uploads/products/' . $item['image']); ?>" alt="<?php echo htmlspecialchars($item['pname']); ?>" class="w-full h-full object-contain mix-blend-multiply">
                                        <?php else: ?>
                                            <i class="fas fa-image text-gray-300 text-xl"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <h6 class="font-bold text-navy text-[11px] mb-1 truncate"><?php echo htmlspecialchars($item['pname']); ?></h6>
                                        <div class="flex items-center gap-2 mb-1">
                                            <?php if ($item['size'] != 'Standard'): ?>
                                                <span class="bg-black text-white text-[9px] px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($item['size']); ?></span>
                                            <?php endif; ?>
                                            <span class="text-[9px] font-bold text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full">Qty: <?php echo $item['qty']; ?></span>
                                        </div>
                                        <div class="font-black text-primary text-[11px]">Rs. <?php echo number_format($item['price'] * $item['qty'], 2); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <form id="checkoutForm">
            <div class="grid lg:grid-cols-12 gap-8 pb-36 lg:pb-12">
                <!-- Left Column - Shipping & Payment (60%) -->
                <div class="lg:col-span-7 flex flex-col gap-6">
                    <input type="hidden" name="address_id" id="address_id" value="">
                    <!-- Delivery Details -->
                    <div class="bg-white rounded-2xl p-6 md:p-8 shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-gray-100">
                        <h4 class="font-black text-navy text-lg uppercase tracking-wide flex items-center mb-6 pb-4 border-b border-gray-100">
                            <i class="fas fa-truck-fast text-[#0A6CFF] me-3 text-xl"></i> Shipping Address
                        </h4>
                        
                        <?php if (!empty($addresses)): ?>
                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-3">
                                    <label class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest">Select from Address Book</label>
                                    <a href="address-book.php?add=1&return=checkout" class="text-[11px] font-bold text-[#0A6CFF] hover:underline flex items-center gap-1">
                                        <i class="fas fa-plus text-[9px]"></i> Manage Addresses
                                    </a>
                                </div>
                                <div class="flex gap-4 overflow-x-auto pb-4 hide-scrollbar snap-x">
                                    <?php foreach ($addresses as $addr): ?>
                                        <div onclick='selectAddress(this, <?php echo json_encode($addr, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="address-card shrink-0 w-64 border-2 rounded-xl p-4 cursor-pointer transition-all snap-start <?php echo $addr['is_default_shipping'] ? 'border-blue-600 bg-blue-50/50' : 'border-gray-200 hover:border-gray-300 bg-white'; ?>" data-id="<?php echo $addr['id']; ?>">
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-8 h-8 rounded-full bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-500 text-xs">
                                                        <?php if ($addr['label'] == 'Home'): ?>
                                                            <i class="fas fa-home"></i>
                                                        <?php elseif ($addr['label'] == 'Office'): ?>
                                                            <i class="fas fa-building"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-map-marker-alt"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="font-bold text-navy text-sm uppercase tracking-wide"><?php echo htmlspecialchars($addr['label']); ?></span>
                                                </div>
                                                <?php if ($addr['is_default_shipping']): ?>
                                                    <span class="bg-[#0A6CFF] text-white text-[9px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">Default</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs font-medium text-gray-600 truncate mb-1"><i class="fas fa-user w-4 text-gray-400"></i> <?php echo htmlspecialchars($addr['full_name']); ?></p>
                                            <p class="text-xs text-gray-500 truncate"><i class="fas fa-map-pin w-4 text-gray-400"></i> <?php echo htmlspecialchars($addr['address_line1']); ?>, <?php echo htmlspecialchars($addr['city']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                    <!-- Add New Address Card -->
                                    <a href="address-book.php?add=1&return=checkout" class="shrink-0 w-48 border-2 border-dashed border-gray-200 hover:border-[#0A6CFF] hover:bg-blue-50/30 rounded-xl p-4 cursor-pointer transition-all snap-start flex flex-col items-center justify-center gap-2 text-gray-400 hover:text-[#0A6CFF] group">
                                        <div class="w-10 h-10 rounded-full border-2 border-dashed border-gray-200 group-hover:border-[#0A6CFF] flex items-center justify-center transition-colors">
                                            <i class="fas fa-plus text-sm"></i>
                                        </div>
                                        <span class="text-[11px] font-bold uppercase tracking-wide">Add Address</span>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- No addresses: show Add Address prompt -->
                            <div class="mb-6">
                                <a href="address-book.php?add=1&return=checkout" class="flex items-center gap-3 p-4 border-2 border-dashed border-gray-200 hover:border-[#0A6CFF] hover:bg-blue-50/30 rounded-xl cursor-pointer transition-all group text-gray-400 hover:text-[#0A6CFF]">
                                    <div class="w-10 h-10 rounded-full border-2 border-dashed border-gray-200 group-hover:border-[#0A6CFF] flex items-center justify-center shrink-0 transition-colors">
                                        <i class="fas fa-plus text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold uppercase tracking-wide">Add to Address Book</p>
                                        <p class="text-xs text-gray-400">Save addresses for faster checkout</p>
                                    </div>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2 relative">
                                <label for="customerName" class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full h-12 bg-gray-50 border border-gray-200 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition rounded-xl font-medium px-4 pr-10 outline-none" id="customerName" name="customerName" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" required>
                                <i class="fas fa-user absolute right-4 top-9 text-gray-400 pointer-events-none"></i>
                            </div>
                            
                            <div class="md:col-span-2 relative">
                                <label for="address" class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest mb-1.5">Street Address <span class="text-red-500">*</span></label>
                                <textarea class="w-full min-h-[72px] bg-gray-50 border border-gray-200 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition rounded-xl font-medium px-4 py-3 pr-10 outline-none resize-none" id="address" name="address" rows="3" placeholder="House number, street name, city" required></textarea>
                                <i class="fas fa-map-marker-alt absolute right-4 top-9 text-gray-400 pointer-events-none"></i>
                            </div>
                            
                            <div class="relative">
                                <label for="province" class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest mb-1.5">Province <span class="text-red-500">*</span></label>
                                <select class="w-full h-12 bg-gray-50 border border-gray-200 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition rounded-xl font-medium px-4 pr-10 outline-none appearance-none cursor-pointer" id="province" name="province" required>
                                    <option value="">Select Province</option>
                                    <?php foreach ($provinces as $prov): ?>
                                        <option value="<?php echo $prov; ?>"><?php echo $prov; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-9 text-gray-400 pointer-events-none"></i>
                            </div>

                            <div class="relative">
                                <label for="postalCode" class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest mb-1.5">Postal Code <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full h-12 bg-gray-50 border border-gray-200 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition rounded-xl font-medium px-4 pr-10 outline-none" id="postalCode" name="postalCode" placeholder="e.g., 10400" required>
                                <i class="fas fa-envelope absolute right-4 top-9 text-gray-400 pointer-events-none"></i>
                            </div>
                            
                            <div class="relative">
                                <label for="contact1" class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest mb-1.5">Mobile Number <span class="text-red-500">*</span></label>
                                <input type="tel" class="w-full h-12 bg-gray-50 border border-gray-200 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition rounded-xl font-medium px-4 pr-10 outline-none" id="contact1" name="contact1" placeholder="e.g., 0771234567" required>
                                <i class="fas fa-phone absolute right-4 top-9 text-gray-400 pointer-events-none"></i>
                            </div>
                            
                            <div class="relative">
                                <label for="contact2" class="block font-bold text-gray-600 text-[11px] uppercase tracking-widest mb-1.5">Alternate Number</label>
                                <input type="tel" class="w-full h-12 bg-gray-50 border border-gray-200 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition rounded-xl font-medium px-4 pr-10 outline-none" id="contact2" name="contact2" placeholder="Optional">
                                <i class="fas fa-phone absolute right-4 top-9 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                        
                    <!-- Shipping Speed -->
                    <div class="bg-white rounded-2xl p-6 md:p-8 shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-gray-100">
                        <h5 class="font-black text-navy text-lg uppercase tracking-wide mb-4">Shipping Speed</h5>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <label class="payment-option relative rounded-xl p-4 border-2 cursor-pointer transition-all flex items-center gap-3">
                                <input type="radio" name="deliveryMethod" value="Speed Post" class="peer sr-only" onchange="updateOptionStyles()" checked>
                                <div class="w-5 h-5 rounded-full border-2 border-gray-300 peer-checked:border-blue-600 peer-checked:bg-blue-600 flex items-center justify-center transition-all shrink-0">
                                    <div class="w-2 h-2 rounded-full bg-white scale-0 peer-checked:scale-100 transition-transform"></div>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="font-bold text-sm text-navy peer-checked:text-blue-700 transition-colors">Standard Delivery</div>
                                    <div class="text-xs text-gray-500 mt-0.5">3-5 business days</div>
                                </div>
                                <i class="fas fa-truck w-6 h-6 flex items-center justify-center text-gray-400 peer-checked:text-blue-600 shrink-0 text-xl transition-colors"></i>
                            </label>
                            
                            <label class="payment-option relative rounded-xl p-4 border-2 cursor-pointer transition-all flex items-center gap-3">
                                <input type="radio" name="deliveryMethod" value="Courier" class="peer sr-only" onchange="updateOptionStyles()">
                                <div class="w-5 h-5 rounded-full border-2 border-gray-300 peer-checked:border-blue-600 peer-checked:bg-blue-600 flex items-center justify-center transition-all shrink-0">
                                    <div class="w-2 h-2 rounded-full bg-white scale-0 peer-checked:scale-100 transition-transform"></div>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="font-bold text-sm text-navy peer-checked:text-blue-700 transition-colors">Express Courier</div>
                                    <div class="text-xs text-gray-500 mt-0.5">1-2 business days</div>
                                </div>
                                <i class="fas fa-bolt w-6 h-6 flex items-center justify-center text-gray-400 peer-checked:text-blue-600 shrink-0 text-xl transition-colors"></i>
                            </label>
                        </div>
                    </div>

                    <!-- Payment Details (4 Master Payment Channels) -->
                    <div class="bg-white rounded-2xl p-5 sm:p-6 md:p-8 shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-5 pb-3 border-b border-gray-100 gap-2">
                            <div>
                                <h5 class="font-black text-navy text-base sm:text-lg uppercase tracking-wide">Select Payment Method</h5>
                                <p class="text-xs text-gray-500 mt-0.5">Choose your preferred payment channel for checkout</p>
                            </div>
                            <span class="self-start sm:self-auto text-[11px] font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full flex items-center gap-1.5 border border-blue-100">
                                <i class="fas fa-shield-halved"></i> 100% Secure
                            </span>
                        </div>

                        <div class="space-y-3.5">
                            <!-- 1. Cash on Delivery (COD) -->
                            <div class="payment-method-card border-2 rounded-2xl p-3.5 sm:p-4 md:p-5 cursor-pointer transition-all border-blue-600 bg-blue-50/40" id="card-COD" onclick="selectPaymentMethod('COD')">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <input type="radio" name="paymentMethod" value="COD" class="sr-only" checked>
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-blue-600 bg-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                            <div class="w-2 h-2 rounded-full bg-white"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                                <span class="font-bold text-xs sm:text-sm text-navy uppercase tracking-wide">Cash on Delivery</span>
                                                <span class="bg-emerald-100 text-emerald-700 text-[9px] font-bold px-1.5 py-0.5 rounded-full">Popular</span>
                                            </div>
                                            <p class="text-[11px] sm:text-xs text-gray-500 leading-snug">Pay with cash when package arrives at your doorstep</p>
                                        </div>
                                    </div>
                                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 text-base border border-emerald-100">
                                        <i class="fas fa-hand-holding-dollar"></i>
                                    </div>
                                </div>
                                <div class="mt-2.5 pt-2.5 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                                    <span><i class="fas fa-truck-ramp-box text-gray-400 me-1"></i> Courier Handover</span>
                                    <span class="font-bold text-gray-700">No Extra Fee</span>
                                </div>
                            </div>

                            <!-- 2. Credit / Debit Card (PayHere) -->
                            <div class="payment-method-card border-2 rounded-2xl p-3.5 sm:p-4 md:p-5 cursor-pointer transition-all border-gray-200 bg-white hover:border-gray-300" id="card-CARD" onclick="selectPaymentMethod('CARD')">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <input type="radio" name="paymentMethod" value="CARD" class="sr-only">
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center shrink-0 mt-0.5">
                                            <div class="w-2 h-2 rounded-full bg-white hidden"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                                <span class="font-bold text-xs sm:text-sm text-navy uppercase tracking-wide">Credit / Debit Card</span>
                                                <span class="bg-blue-100 text-blue-700 text-[9px] font-bold px-1.5 py-0.5 rounded-full">Instant</span>
                                            </div>
                                            <p class="text-[11px] sm:text-xs text-gray-500 leading-snug">Visa, MasterCard, and American Express</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="px-1.5 py-0.5 bg-gray-100 text-[#1434CB] rounded text-[10px] font-black border border-gray-200">VISA</span>
                                        <span class="px-1.5 py-0.5 bg-gray-100 text-[#EB001B] rounded text-[10px] font-black border border-gray-200">MC</span>
                                    </div>
                                </div>

                                <!-- Card Input Fields placeholder removed to avoid confusion -->
                                <div class="payment-extra-content hidden mt-3.5 pt-3.5 border-t border-gray-100 space-y-3" id="cardExtraFields">
                                    <div class="bg-blue-50/50 rounded-xl p-4 border border-blue-100 text-center">
                                        <i class="fas fa-shield-alt text-blue-500 text-2xl mb-2"></i>
                                        <p class="text-xs font-bold text-navy">You will be redirected to PayHere's secure payment modal to enter your card details.</p>
                                        <p class="text-[10px] text-gray-500 mt-1">This ensures your payment information is 100% secure and PCI compliant.</p>
                                    </div>
                                    <div class="text-[10px] text-gray-400 flex flex-wrap items-center justify-between pt-1 gap-1">
                                        <span><i class="fas fa-lock text-green-500 me-1"></i> PayHere 256-bit SSL Certified</span>
                                        <span class="text-blue-600 font-bold">+ 3.0% Gateway Fee</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. KOKO Pay in 3 (BNPL) -->
                            <div class="payment-method-card border-2 rounded-2xl p-3.5 sm:p-4 md:p-5 cursor-pointer transition-all border-gray-200 bg-white hover:border-purple-300" id="card-KOKO" onclick="selectPaymentMethod('KOKO')">
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <input type="radio" name="paymentMethod" value="KOKO" class="sr-only">
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center shrink-0 mt-0.5">
                                            <div class="w-2 h-2 rounded-full bg-white hidden"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                                <span class="font-bold text-xs sm:text-sm text-navy uppercase tracking-wide">KOKO - Pay in 3</span>
                                                <span class="bg-purple-100 text-purple-700 text-[9px] font-bold px-1.5 py-0.5 rounded-full">0% Interest</span>
                                            </div>
                                            <p class="text-[11px] sm:text-xs text-gray-500 leading-snug">Split into 3 equal monthly payments</p>
                                        </div>
                                    </div>
                                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0 font-bold text-xs border border-purple-200">
                                        KOKO
                                    </div>
                                </div>

                                <!-- KOKO 3-Box Timeline (Accordion) -->
                                <div class="payment-extra-content hidden mt-3.5 pt-3.5 border-t border-purple-100" id="kokoExtraFields">
                                    <div class="bg-purple-50/60 rounded-xl p-3 border border-purple-100 mb-2.5">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[11px] font-black text-purple-900 uppercase tracking-wider">Installment Plan</span>
                                            <span class="text-[9px] font-bold bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full">0% APR</span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-1.5 sm:gap-2 text-center mt-2">
                                            <div class="bg-white rounded-lg p-2 border border-purple-200 shadow-sm">
                                                <span class="block text-[8px] font-bold uppercase text-purple-600 tracking-wider">1st (Today)</span>
                                                <div class="text-[11px] sm:text-xs font-black text-navy mt-0.5" id="kokoBox1">Rs. 0.00</div>
                                                <span class="text-[8px] text-emerald-600 font-bold block mt-0.5"><i class="fas fa-check-circle"></i> Pay Now</span>
                                            </div>
                                            <div class="bg-white rounded-lg p-2 border border-purple-200 shadow-sm">
                                                <span class="block text-[8px] font-bold uppercase text-gray-500 tracking-wider">2nd (30d)</span>
                                                <div class="text-[11px] sm:text-xs font-black text-navy mt-0.5" id="kokoBox2">Rs. 0.00</div>
                                                <span class="text-[8px] text-gray-400 font-medium block mt-0.5">Month 1</span>
                                            </div>
                                            <div class="bg-white rounded-lg p-2 border border-purple-200 shadow-sm">
                                                <span class="block text-[8px] font-bold uppercase text-gray-500 tracking-wider">3rd (60d)</span>
                                                <div class="text-[11px] sm:text-xs font-black text-navy mt-0.5" id="kokoBox3">Rs. 0.00</div>
                                                <span class="text-[8px] text-gray-400 font-medium block mt-0.5">Month 2</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-500 flex flex-wrap items-center justify-between gap-1">
                                        <span><i class="fas fa-check text-purple-600 me-1"></i> No interest. Easy automated reminders.</span>
                                        <span class="text-purple-700 font-bold">+ 5.0% Fee</span>
                                    </p>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>


                <!-- Right Column - Order Summary Sticky (40%) -->
                <div class="lg:col-span-5">
                    <div class="lg:sticky lg:top-24 space-y-4">
                        
                        <!-- Order Summary Card (Desktop Only) -->
                        <div class="hidden lg:block bg-white rounded-2xl p-6 shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-gray-100">
                            <h4 class="font-black text-navy text-lg uppercase tracking-wide flex items-center mb-4 pb-3 border-b border-gray-100">
                                <i class="fas fa-shopping-bag text-[#0A6CFF] me-3"></i> Order Summary
                            </h4>
                            <div class="space-y-3 max-h-[420px] overflow-y-auto pr-2 custom-scrollbar">
                                <?php foreach ($sellerGroups as $group): ?>
                                    <div class="border border-gray-100 rounded-xl p-4 bg-white shadow-sm mb-3">
                                        <div class="flex items-center justify-between pb-2 mb-3 border-b border-gray-100">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-store text-[#0A6CFF] text-xs"></i>
                                                <span class="text-xs font-black text-navy uppercase tracking-wider"><?php echo htmlspecialchars($group['seller_name']); ?></span>
                                            </div>
                                            <span class="text-[10px] font-bold text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100">
                                                <?php echo count($group['items']); ?> <?php echo count($group['items']) === 1 ? 'item' : 'items'; ?>
                                            </span>
                                        </div>
                                        <div class="space-y-3">
                                            <?php foreach ($group['items'] as $item): ?>
                                            <div class="flex items-center gap-3.5 bg-gray-50 p-3 rounded-xl border border-gray-100">
                                                <div class="shrink-0 w-16 h-16 bg-white rounded-xl p-1 border border-gray-100 flex items-center justify-center overflow-hidden">
                                                    <?php if (!empty($item['image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($base_path . 'assets/uploads/products/' . $item['image']); ?>" alt="<?php echo htmlspecialchars($item['pname']); ?>" class="w-full h-full object-contain mix-blend-multiply">
                                                    <?php else: ?>
                                                        <i class="fas fa-image text-gray-300 text-xl"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow min-w-0">
                                                    <h6 class="font-bold text-navy text-xs mb-1 truncate"><?php echo htmlspecialchars($item['pname']); ?></h6>
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <?php if ($item['size'] != 'Standard'): ?>
                                                            <span class="bg-black text-white text-[10px] px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($item['size'] ?? ''); ?></span>
                                                        <?php endif; ?>
                                                        <span class="text-[10px] font-bold text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full">Qty: <?php echo $item['qty']; ?></span>
                                                    </div>
                                                    <div class="font-black text-[#0A6CFF] text-xs">Rs. <?php echo number_format($item['price'] * $item['qty'], 2); ?></div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Promo Code -->
                        <div class="bg-[#EFF6FF] rounded-2xl p-6 border border-blue-100 shadow-[0_4px_20px_rgba(0,0,0,0.05)]">
                            <h5 class="font-black text-navy text-sm uppercase tracking-wide flex items-center mb-4">
                                <i class="fas fa-tag text-[#0A6CFF] me-2"></i> Got a Promo Code?
                            </h5>
                            <div class="flex flex-col lg:flex-row gap-3">
                                <input type="text" id="couponCode" class="w-full h-12 uppercase tracking-widest font-bold bg-white border border-blue-200 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 shadow-sm rounded-xl px-4 outline-none transition" placeholder="ENTER CODE">
                                <button id="applyCouponBtn" class="w-full lg:w-auto h-12 bg-[#0A1020] hover:bg-[#0A6CFF] text-white lg:px-8 rounded-xl font-bold uppercase tracking-wide transition-colors shrink-0 shadow-md" type="button" onclick="applyCoupon()">
                                    Apply
                                </button>
                            </div>
                            <div id="couponMessage" class="mt-2 text-xs font-bold"></div>
                        </div>

                        <!-- Total Card -->
                        <div class="bg-[#0A1020] rounded-2xl p-6 md:p-8 text-white shadow-[0_4px_20px_rgba(0,0,0,0.15)]">
                            <div class="space-y-4 mb-6 text-gray-300 font-medium text-sm">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span id="subtotal" class="text-white font-bold">Rs. <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="flex justify-between text-[#CCFF00] hidden" id="discountRow">
                                    <span>Discount</span>
                                    <span id="discount" class="font-bold">- Rs. 0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>Delivery Fee</span>
                                    <span id="deliveryFee" class="text-white font-bold">
                                        <?php if ($deliveryFee == 0): ?>
                                            <span class="text-[#CCFF00] text-sm">FREE</span> 
                                            <span class="text-gray-500 line-through text-xs ml-1 font-medium">Rs. 300</span>
                                        <?php else: ?>
                                            Rs. <?php echo number_format($deliveryFee, 2); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center text-sm hidden" id="gatewayFeeRow">
                                    <span id="gatewayFeeLabel" class="text-gray-300">Gateway Fee</span>
                                    <span id="gatewayFee" class="text-white font-bold">+ Rs. 0.00</span>
                                </div>
                            </div>
                            <div class="border-t border-white/10 pt-6">
                                <div class="flex justify-between items-end mb-1">
                                    <span class="text-sm font-bold uppercase tracking-widest text-gray-400">Total</span>
                                    <span id="finalTotal" class="text-2xl md:text-3xl font-black text-white tracking-tight">Rs. <?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Sticky Mobile Footer / Desktop Button -->
                        <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-100 z-50 lg:static lg:p-0 lg:border-t-0 lg:bg-transparent shadow-[0_-4px_20px_rgba(0,0,0,0.05)] lg:shadow-none flex flex-row items-center justify-between lg:block gap-4">
                            <div class="lg:hidden flex flex-col justify-center pl-2">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Total</span>
                                <span id="mobileFinalTotal" class="text-lg font-black text-navy leading-none">Rs. <?php echo number_format($total, 2); ?></span>
                            </div>
                            <button type="submit" class="flex-grow lg:w-full bg-[#0A6CFF] hover:bg-blue-700 text-white h-14 rounded-full font-black text-sm uppercase tracking-wider transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 flex items-center justify-center gap-2" id="placeOrderBtn">
                                <i class="fas fa-lock text-[10px]"></i> <span>Place Order Now</span>
                                <span class="loading-spinner hidden" id="spinner">
                                    <i class="fas fa-circle-notch fa-spin"></i>
                                </span>
                            </button>
                        </div>
                        <p class="text-center text-xs text-gray-400 mt-6 font-medium hidden lg:block"><i class="fas fa-shield-alt text-gray-400 me-1"></i> Your personal information is secure and encrypted.</p>

                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- JavaScript logic -->
    <script>
        let currentDiscount = 0;
        let appliedCouponCode = '';
        let subtotalAmount = <?php echo $subtotal; ?>;
        let deliveryFeeAmount = <?php echo $deliveryFee; ?>;
        let currentPaymentMethod = 'COD';

        // Custom styling for selected payment/delivery options
        function updateOptionStyles() {
            document.querySelectorAll('.payment-option').forEach(el => {
                const radio = el.querySelector('input[type="radio"]');
                if(radio && radio.checked) {
                    el.classList.add('border-blue-600', 'bg-blue-50/50');
                    el.classList.remove('border-gray-200', 'hover:border-gray-300');
                } else if (radio) {
                    el.classList.remove('border-blue-600', 'bg-blue-50/50');
                    el.classList.add('border-gray-200', 'hover:border-gray-300');
                }
            });
        }

        function selectDeliveryMethod(method) {
            document.querySelectorAll('input[name="deliveryMethod"]').forEach(radio => {
                radio.checked = radio.value === method;
            });
            updateOptionStyles();
        }

        function selectPaymentMethod(method) {
            currentPaymentMethod = method;

            // Update radio buttons
            document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                radio.checked = (radio.value === method);
            });

            // Update 4 master payment cards styling
            const methods = ['COD', 'CARD', 'KOKO', 'BANK'];
            methods.forEach(m => {
                const card = document.getElementById(`card-${m}`);
                if (!card) return;
                const radioDot = card.querySelector('.radio-indicator');
                const innerDot = radioDot ? radioDot.querySelector('div') : null;

                if (m === method) {
                    if (m === 'KOKO') {
                        card.className = 'payment-method-card border-2 rounded-2xl p-4 md:p-5 cursor-pointer transition-all border-purple-600 bg-purple-50/40 shadow-sm';
                        if (radioDot) {
                            radioDot.className = 'radio-indicator w-5 h-5 rounded-full border-2 border-purple-600 bg-purple-600 flex items-center justify-center shrink-0';
                        }
                    } else {
                        card.className = 'payment-method-card border-2 rounded-2xl p-4 md:p-5 cursor-pointer transition-all border-blue-600 bg-blue-50/40 shadow-sm';
                        if (radioDot) {
                            radioDot.className = 'radio-indicator w-5 h-5 rounded-full border-2 border-blue-600 bg-blue-600 flex items-center justify-center shrink-0';
                        }
                    }
                    if (innerDot) innerDot.classList.remove('hidden');
                } else {
                    card.className = 'payment-method-card border-2 rounded-2xl p-4 md:p-5 cursor-pointer transition-all border-gray-200 bg-white hover:border-gray-300';
                    if (radioDot) {
                        radioDot.className = 'radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center shrink-0';
                    }
                    if (innerDot) innerDot.classList.add('hidden');
                }
            });

            // Accordion sections
            const cardExtra = document.getElementById('cardExtraFields');
            const kokoExtra = document.getElementById('kokoExtraFields');
            const bankExtra = document.getElementById('bankExtraFields');

            if (cardExtra) cardExtra.classList.toggle('hidden', method !== 'CARD');
            if (kokoExtra) kokoExtra.classList.toggle('hidden', method !== 'KOKO');
            if (bankExtra) bankExtra.classList.toggle('hidden', method !== 'BANK');

            updateTotals();
        }

        function formatCardNumber(input) {
            let val = input.value.replace(/\D/g, '').substring(0, 16);
            let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
            input.value = formatted;
            document.getElementById('previewCardNum').textContent = formatted || '•••• •••• •••• 4242';
        }

        function formatCardExp(input) {
            let val = input.value.replace(/\D/g, '').substring(0, 4);
            if (val.length >= 2) {
                input.value = val.substring(0, 2) + '/' + val.substring(2);
            } else {
                input.value = val;
            }
            document.getElementById('previewCardExp').textContent = input.value || '12/28';
        }

        function previewBankSlip(input) {
            if (input.files && input.files[0]) {
                document.getElementById('bankSlipLabel').innerHTML = `<span class="text-green-600 font-bold"><i class="fas fa-file-check me-1"></i> ${input.files[0].name}</span>`;
            }
        }

        function applyCoupon() {
            const couponInput = document.getElementById('couponCode');
            const couponCode = couponInput.value.trim().toUpperCase();
            
            if (!couponCode) {
                showCouponMessage('Please enter a coupon code', 'error');
                return;
            }

            showCouponMessage('Validating coupon...', 'info');

            fetch('../Backend/apply-coupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `coupon_code=${encodeURIComponent(couponCode)}&order_amount=${subtotalAmount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentDiscount = parseFloat(data.discount_amount);
                    appliedCouponCode = couponCode;
                    
                    showCouponMessage(`Coupon applied! You saved Rs. ${currentDiscount.toFixed(2)}`, 'success');
                    updateTotals();
                    
                    couponInput.disabled = true;
                    const btn = document.getElementById('applyCouponBtn');
                    if (btn) {
                        btn.innerHTML = 'Remove';
                        btn.classList.remove('bg-[#0A1020]', 'hover:bg-[#0A6CFF]');
                        btn.classList.add('bg-red-600', 'hover:bg-red-700');
                        btn.onclick = removeCoupon;
                    }
                } else {
                    showCouponMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showCouponMessage('Error verifying coupon. Please try again.', 'error');
            });
        }

        function removeCoupon() {
            currentDiscount = 0;
            appliedCouponCode = '';
            
            const input = document.getElementById('couponCode');
            input.value = '';
            input.disabled = false;
            
            const btn = document.getElementById('applyCouponBtn');
            if (btn) {
                btn.innerHTML = 'Apply';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-[#0A1020]', 'hover:bg-[#0A6CFF]');
                btn.onclick = applyCoupon;
            }
            
            showCouponMessage('', '');
            updateTotals();
        }

        function showCouponMessage(message, type) {
            const messageDiv = document.getElementById('couponMessage');
            if (message) {
                let colorClass = type === 'success' ? 'text-green-600 font-bold' : (type === 'error' ? 'text-red-600 font-bold' : 'text-blue-600 font-bold');
                let iconClass = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-spinner fa-spin');
                messageDiv.innerHTML = `<span class="${colorClass} text-sm flex items-center mt-1"><i class="fas ${iconClass} me-1.5"></i> ${message}</span>`;
            } else {
                messageDiv.innerHTML = '';
            }
        }

        function updateTotals() {
            const discountRow = document.getElementById('discountRow');
            const discountSpan = document.getElementById('discount');
            const finalTotalSpan = document.getElementById('finalTotal');
            const mobileFinalTotalSpan = document.getElementById('mobileFinalTotal');
            const gatewayFeeRow = document.getElementById('gatewayFeeRow');
            const gatewayFeeSpan = document.getElementById('gatewayFee');
            const gatewayFeeLabel = document.getElementById('gatewayFeeLabel');
            
            if (currentDiscount > 0) {
                discountRow.classList.remove('hidden');
                discountSpan.textContent = `- Rs. ${currentDiscount.toFixed(2)}`;
            } else {
                discountRow.classList.add('hidden');
            }

            const netOrderBase = Math.max(0, subtotalAmount - currentDiscount + deliveryFeeAmount);

            // Dynamic Gateway Fee
            let gatewayFee = 0.00;
            if (currentPaymentMethod === 'CARD') {
                gatewayFee = netOrderBase * 0.03; // 3% PayHere fee
                if (gatewayFeeRow) {
                    gatewayFeeRow.classList.remove('hidden');
                    if (gatewayFeeLabel) gatewayFeeLabel.textContent = 'Gateway Fee (PayHere 3%)';
                    if (gatewayFeeSpan) gatewayFeeSpan.textContent = `+ Rs. ${gatewayFee.toFixed(2)}`;
                }
            } else if (currentPaymentMethod === 'KOKO') {
                gatewayFee = netOrderBase * 0.05; // 5% KOKO fee
                if (gatewayFeeRow) {
                    gatewayFeeRow.classList.remove('hidden');
                    if (gatewayFeeLabel) gatewayFeeLabel.textContent = 'KOKO Fee (5%)';
                    if (gatewayFeeSpan) gatewayFeeSpan.textContent = `+ Rs. ${gatewayFee.toFixed(2)}`;
                }
            } else {
                if (gatewayFeeRow) gatewayFeeRow.classList.add('hidden');
            }
            
            const finalTotal = netOrderBase + gatewayFee;
            finalTotalSpan.textContent = `Rs. ${finalTotal.toFixed(2)}`;
            if (mobileFinalTotalSpan) mobileFinalTotalSpan.textContent = `Rs. ${finalTotal.toFixed(2)}`;

            // Update KOKO 3-Box timeline breakdown
            const installmentAmount = (finalTotal / 3).toFixed(2);
            const k1 = document.getElementById('kokoBox1');
            const k2 = document.getElementById('kokoBox2');
            const k3 = document.getElementById('kokoBox3');
            if (k1) k1.textContent = `Rs. ${installmentAmount}`;
            if (k2) k2.textContent = `Rs. ${installmentAmount}`;
            if (k3) k3.textContent = `Rs. ${installmentAmount}`;
        }

        function selectAddress(element, address) {
            document.querySelectorAll('.address-card').forEach(card => {
                card.classList.remove('border-blue-600', 'bg-blue-50/50');
                card.classList.add('border-gray-200', 'hover:border-gray-300', 'bg-white');
            });
            element.classList.remove('border-gray-200', 'hover:border-gray-300', 'bg-white');
            element.classList.add('border-blue-600', 'bg-blue-50/50');

            document.getElementById('customerName').value = address.full_name;
            document.getElementById('contact1').value = address.phone1;
            
            let fullAddress = address.address_line1;
            if (address.address_line2) fullAddress += ', ' + address.address_line2;
            fullAddress += ', ' + address.city;
            document.getElementById('address').value = fullAddress;
            
            if (address.postal_code) document.getElementById('postalCode').value = address.postal_code;
            if (address.province) document.getElementById('province').value = address.province;
            document.getElementById('address_id').value = address.id;
        }

        function goToHome() {
            window.location.href = 'index.php';
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateOptionStyles();
            selectPaymentMethod('COD');
            
            // Auto-select default address if available
            const defaultAddressCard = document.querySelector('.address-card.border-blue-600');
            if (defaultAddressCard) {
                defaultAddressCard.click();
            } else {
                const firstCard = document.querySelector('.address-card');
                if (firstCard) firstCard.click();
            }

            const couponInput = document.getElementById('couponCode');
            if (couponInput) {
                couponInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyCoupon();
                    }
                });
            }
        });

        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('coupon_code', appliedCouponCode);
            formData.append('discount_amount', currentDiscount);
            formData.append('action', 'place_order');
            
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            const spinner = document.getElementById('spinner');
            
            placeOrderBtn.disabled = true;
            spinner.classList.remove('hidden');
            
            fetch('../Backend/process-order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.paymentMethod === 'CARD' && data.payhereConfig) {
                        // Handle PayHere Payment
                        payhere.onCompleted = function onCompleted(orderId) {
                            placeOrderBtn.disabled = false;
                            spinner.classList.add('hidden');
                            data.status = 'Paid'; // Optimistically show paid
                            openOrderSuccessModal(data);
                        };

                        payhere.onDismissed = function onDismissed() {
                            placeOrderBtn.disabled = false;
                            spinner.classList.add('hidden');
                            if(typeof showToast === 'function') showToast('Payment popup closed. You can try again or choose another payment method.', 'warning'); else alert('Payment popup closed. You can try again or choose another payment method.');
                            // Removed redirect to my-orders.php so the cart stays intact
                        };

                        payhere.onError = function onError(error) {
                            placeOrderBtn.disabled = false;
                            spinner.classList.add('hidden');
                            if(typeof showToast === 'function') showToast('Payment error: ' + error, 'error'); else alert('Payment error: ' + error);
                            window.location.href = 'my-orders.php';
                        };

                        payhere.startPayment(data.payhereConfig);
                    } else {
                        // Normal COD/KOKO flow
                        placeOrderBtn.disabled = false;
                        spinner.classList.add('hidden');
                        openOrderSuccessModal(data);
                    }
                } else {
                    placeOrderBtn.disabled = false;
                    spinner.classList.add('hidden');
                    if(typeof showToast === 'function') showToast(data.message, 'error'); else alert(data.message);
                }
            })
            .catch(error => {
                placeOrderBtn.disabled = false;
                spinner.classList.add('hidden');
                if(typeof showToast === 'function') showToast('An error occurred during order submission.', 'error'); else alert('Submission Error');
            });
        });
    </script>
    <script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>
    
    <!-- Order Success Modal -->
    <div id="orderSuccessModal" class="fixed inset-0 z-50 hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-navy/80 backdrop-blur-sm transition-opacity opacity-0" id="osmBackdrop"></div>
        
        <!-- Modal Content -->
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-[2rem] p-8 md:p-12 shadow-2xl border border-gray-100 max-w-2xl w-full transform scale-95 opacity-0 transition-all duration-300 relative" id="osmContent">
                <button type="button" onclick="closeOrderSuccessModal()" class="absolute top-6 right-6 w-10 h-10 bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
                <div class="w-24 h-24 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6 relative">
                    <i class="fas fa-check text-5xl text-green-500 z-10 relative animate-[ping_1s_ease-out_infinite_reverse]"></i>
                    <div class="absolute inset-0 bg-green-100 rounded-full animate-ping opacity-75"></div>
                </div>
                
                <div class="text-center">
                    <h2 class="text-3xl md:text-4xl font-black text-navy uppercase tracking-widest mb-4">Order Successful!</h2>
                    <p class="text-gray-500 mb-2">Thank you for your purchase. Your order has been placed.</p>
                    <p class="text-lg font-bold text-navy mb-8">Order ID: <span class="text-primary" id="osmOrderId">ORD-XXXX</span></p>
                </div>
                
                <div class="bg-gray-50 rounded-2xl p-6 mb-8 text-left border border-gray-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Date</p>
                            <p class="font-bold text-navy" id="osmDate">Jan 01, 2026</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Total Amount</p>
                            <p class="font-bold text-navy" id="osmTotal">Rs. 0.00</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Payment Method</p>
                            <p class="font-bold text-navy" id="osmPayment">COD</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Status</p>
                            <p class="font-bold text-orange-500 capitalize" id="osmStatus">Pending</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="my-orders.php" class="bg-navy hover:bg-gray-800 text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest text-center transition-colors shadow-lg">View My Orders</a>
                    <a href="shop.php" class="bg-primary hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-bold uppercase tracking-widest text-center transition-colors shadow-lg shadow-blue-500/30">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openOrderSuccessModal(data) {
            const modal = document.getElementById('orderSuccessModal');
            const backdrop = document.getElementById('osmBackdrop');
            const content = document.getElementById('osmContent');
            
            document.getElementById('osmOrderId').textContent = data.orderCode;
            document.getElementById('osmDate').textContent = data.date;
            document.getElementById('osmTotal').textContent = 'Rs. ' + data.totalAmount;
            document.getElementById('osmPayment').textContent = data.paymentMethod;
            document.getElementById('osmStatus').textContent = data.status;
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }
        
        function closeOrderSuccessModal() {
            const modal = document.getElementById('orderSuccessModal');
            const backdrop = document.getElementById('osmBackdrop');
            const content = document.getElementById('osmContent');
            
            backdrop.classList.add('opacity-0');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                window.location.href = 'shop.php';
            }, 300);
        }
    </script>

<?php include('../include/footer.php'); ?>