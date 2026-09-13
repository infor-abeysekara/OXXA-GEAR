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

// Get cart items
// Get cart items via PDO
$cartStmt = $pdo->prepare("
    SELECT c.product_id, c.variant_id, c.quantity as qty,
           p.name as pname, p.base_price, p.brand,
           v.size, v.price as variant_price,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_variants v ON c.variant_id = v.id
    WHERE c.user_id = ?
");
$cartStmt->execute([$user_id]);
$cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize price
foreach ($cartItems as &$item) {
    $item['price'] = (!empty($item['variant_price']) && $item['variant_price'] > 0) ? $item['variant_price'] : $item['base_price'];
}

// If cart is empty, redirect to products page
if (empty($cartItems)) {
    header('Location: products.php');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += ($item['price'] * $item['qty']);
}

$deliveryFee = 450.00;
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

    <div class="container my-10 max-w-7xl mx-auto px-4">
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

        <div class="row g-8">
            <!-- Left Column - Order Summary & Coupon -->
            <div class="col-lg-5 order-lg-2">
                <!-- Order Summary -->
                <div class="bg-white rounded-[2rem] p-6 md:p-8 shadow-xl border border-gray-100 mb-6">
                    <h4 class="font-extrabold text-navy text-xl uppercase tracking-wide flex items-center mb-6 pb-4 border-b border-gray-100">
                        <i class="fas fa-shopping-bag text-primary me-3 text-2xl"></i> Order Summary
                    </h4>
                    
                    <div class="space-y-4 mb-6 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100 transition-all hover:shadow-md">
                            <div class="shrink-0 w-16 h-16 bg-white rounded-lg p-1 border border-gray-200 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($base_path . $item['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['pname']); ?>" 
                                         class="w-full h-full object-contain">
                                <?php else: ?>
                                    <i class="fas fa-image text-gray-300 text-xl"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow min-w-0">
                                <h6 class="font-bold text-navy text-sm mb-1 truncate"><?php echo htmlspecialchars($item['pname']); ?></h6>
                                <p class="text-xs text-slate mb-1">
                                    <span class="uppercase tracking-wider"><?php echo htmlspecialchars($item['brand']); ?></span>
                                    <?php if ($item['size'] != 'Standard'): ?>
                                        <span class="mx-1">•</span> <span class="bg-navy text-white px-2 py-0.5 rounded text-[10px]"><?php echo htmlspecialchars($item['size']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs font-medium text-slate bg-gray-200 px-2 py-1 rounded">Qty: <?php echo $item['qty']; ?></span>
                                    <span class="font-extrabold text-primary">Rs. <?php echo number_format($item['price'] * $item['qty'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Coupon Section -->
                <div class="bg-blue-50 rounded-[2rem] p-6 md:p-8 shadow-md border border-blue-100 mb-6 relative overflow-hidden">
                    <div class="absolute -right-6 -top-6 text-primary opacity-10">
                        <i class="fas fa-ticket-alt fa-5x transform rotate-12"></i>
                    </div>
                    <h5 class="font-extrabold text-navy text-lg uppercase tracking-wide flex items-center mb-4 relative z-10">
                        <i class="fas fa-tag text-primary me-2"></i> Got a Promo Code?
                    </h5>
                    <div class="flex gap-2 relative z-10">
                        <input type="text" id="couponCode" class="form-control uppercase tracking-widest font-bold bg-white border-white focus:ring-2 focus:ring-primary shadow-sm rounded-xl py-3" placeholder="ENTER CODE">
                        <button class="bg-navy hover:bg-primary text-white px-6 py-3 rounded-xl font-bold uppercase tracking-wide transition-colors shadow-sm" type="button" onclick="applyCoupon()">
                            Apply
                        </button>
                    </div>
                    <div id="couponMessage" class="mt-3 relative z-10"></div>
                </div>

                <!-- Total Summary -->
                <div class="bg-navy rounded-[2rem] p-6 md:p-8 shadow-xl text-white relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                    
                    <div class="space-y-3 relative z-10 mb-6 text-gray-300 font-medium">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span id="subtotal" class="text-white">Rs. <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-lime hidden" id="discountRow">
                            <span>Discount</span>
                            <span id="discount">- Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Delivery Fee</span>
                            <span id="deliveryFee" class="text-white">Rs. <?php echo number_format($deliveryFee, 2); ?></span>
                        </div>
                    </div>
                    <div class="border-t border-gray-600 pt-4 relative z-10">
                        <div class="flex justify-between items-end">
                            <span class="text-lg font-bold uppercase tracking-widest text-gray-300">Total</span>
                            <span id="finalTotal" class="text-3xl font-black text-white tracking-tight">Rs. <?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Delivery & Payment Details -->
            <div class="col-lg-7 order-lg-1">
                <form id="checkoutForm">
                    <!-- Delivery Details -->
                    <div class="bg-white rounded-[2rem] p-6 md:p-8 shadow-xl border border-gray-100 mb-8">
                        <h4 class="font-extrabold text-navy text-xl uppercase tracking-wide flex items-center mb-6 pb-4 border-b border-gray-100">
                            <i class="fas fa-truck-fast text-primary me-3 text-2xl"></i> Shipping Address
                        </h4>
                        
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label for="customerName" class="form-label font-bold text-gray-700 text-sm uppercase tracking-wide">Full Name *</label>
                                <input type="text" class="form-control bg-gray-50 border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary py-3 rounded-xl font-medium" id="customerName" name="customerName" 
                                       value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="address" class="form-label font-bold text-gray-700 text-sm uppercase tracking-wide">Street Address *</label>
                                <textarea class="form-control bg-gray-50 border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary py-3 rounded-xl font-medium" id="address" name="address" rows="3" 
                                          placeholder="House number, street name, city" required></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="province" class="form-label font-bold text-gray-700 text-sm uppercase tracking-wide">Province *</label>
                                <select class="form-select bg-gray-50 border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary py-3 rounded-xl font-medium" id="province" name="province" required>
                                    <option value="">Select Province</option>
                                    <?php foreach ($provinces as $prov): ?>
                                        <option value="<?php echo $prov; ?>"><?php echo $prov; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="postalCode" class="form-label font-bold text-gray-700 text-sm uppercase tracking-wide">Postal Code *</label>
                                <input type="text" class="form-control bg-gray-50 border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary py-3 rounded-xl font-medium" id="postalCode" name="postalCode" 
                                       placeholder="e.g., 10400" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="contact1" class="form-label font-bold text-gray-700 text-sm uppercase tracking-wide">Mobile Number *</label>
                                <input type="tel" class="form-control bg-gray-50 border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary py-3 rounded-xl font-medium" id="contact1" name="contact1" 
                                       placeholder="e.g., 0771234567" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="contact2" class="form-label font-bold text-gray-700 text-sm uppercase tracking-wide">Alternate Number</label>
                                <input type="tel" class="form-control bg-gray-50 border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary py-3 rounded-xl font-medium" id="contact2" name="contact2" 
                                       placeholder="Optional">
                            </div>
                        </div>
                    </div>
                        
                    <!-- Delivery & Payment Method Group -->
                    <div class="row g-8 mb-8">
                        <!-- Delivery Method -->
                        <div class="col-md-6">
                            <div class="bg-white rounded-[2rem] p-6 shadow-xl border border-gray-100 h-full">
                                <h5 class="font-extrabold text-navy text-lg uppercase tracking-wide mb-4">
                                    Shipping Speed
                                </h5>
                                <div class="space-y-3">
                                    <div class="payment-option border-2 border-gray-100 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-blue-50 transition-all" onclick="selectDeliveryMethod('Speed Post')">
                                        <div class="flex items-center gap-3">
                                            <input type="radio" name="deliveryMethod" value="Speed Post" id="speedPost" class="w-5 h-5 text-primary focus:ring-primary accent-primary" checked>
                                            <label for="speedPost" class="mb-0 font-bold text-navy cursor-pointer flex-grow">
                                                Standard Delivery
                                                <span class="block text-xs font-normal text-slate mt-1">3-5 business days</span>
                                            </label>
                                            <i class="fas fa-truck text-2xl text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="payment-option border-2 border-gray-100 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-blue-50 transition-all" onclick="selectDeliveryMethod('Courier')">
                                        <div class="flex items-center gap-3">
                                            <input type="radio" name="deliveryMethod" value="Courier" id="courier" class="w-5 h-5 text-primary focus:ring-primary accent-primary">
                                            <label for="courier" class="mb-0 font-bold text-navy cursor-pointer flex-grow">
                                                Express Courier
                                                <span class="block text-xs font-normal text-slate mt-1">1-2 business days</span>
                                            </label>
                                            <i class="fas fa-bolt text-2xl text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-md-6">
                            <div class="bg-white rounded-[2rem] p-6 shadow-xl border border-gray-100 h-full">
                                <h5 class="font-extrabold text-navy text-lg uppercase tracking-wide mb-4">
                                    Payment Details
                                </h5>
                                <div class="space-y-3">
                                    <div class="payment-option border-2 border-gray-100 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-blue-50 transition-all" onclick="selectPaymentMethod('COD')">
                                        <div class="flex items-center gap-3">
                                            <input type="radio" name="paymentMethod" value="COD" id="cod" class="w-5 h-5 text-primary focus:ring-primary accent-primary" checked>
                                            <label for="cod" class="mb-0 font-bold text-navy cursor-pointer flex-grow">
                                                Cash on Delivery
                                                <span class="block text-xs font-normal text-slate mt-1">Pay at your doorstep</span>
                                            </label>
                                            <i class="fas fa-money-bill-wave text-2xl text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="payment-option border-2 border-gray-100 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-blue-50 transition-all" onclick="selectPaymentMethod('PayHere')">
                                        <div class="flex items-center gap-3">
                                            <input type="radio" name="paymentMethod" value="PayHere" id="payhere" class="w-5 h-5 text-primary focus:ring-primary accent-primary">
                                            <label for="payhere" class="mb-0 font-bold text-navy cursor-pointer flex-grow">
                                                Pay Online
                                                <span class="block text-xs font-normal text-slate mt-1">Secure via PayHere</span>
                                            </label>
                                            <i class="fas fa-credit-card text-2xl text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Place Order Button -->
                    <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-4 rounded-2xl font-extrabold text-xl uppercase tracking-widest transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 flex items-center justify-center gap-3" id="placeOrderBtn">
                        <i class="fas fa-lock"></i> Place Order Now
                        <span class="loading-spinner hidden" id="spinner">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </span>
                    </button>
                    <p class="text-center text-sm text-slate mt-4"><i class="fas fa-shield-alt text-primary me-1"></i> Your personal information is secure and encrypted.</p>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript logic -->
    <script>
        let currentDiscount = 0;
        let appliedCouponCode = '';
        let subtotalAmount = <?php echo $subtotal; ?>;
        let deliveryFeeAmount = <?php echo $deliveryFee; ?>;

        // Custom styling for selected payment/delivery options
        function updateOptionStyles() {
            document.querySelectorAll('.payment-option').forEach(el => {
                const radio = el.querySelector('input[type="radio"]');
                if(radio.checked) {
                    el.classList.add('border-primary', 'bg-blue-50');
                    el.classList.remove('border-gray-100');
                    el.querySelector('i:last-child').classList.remove('text-gray-300');
                    el.querySelector('i:last-child').classList.add('text-primary');
                } else {
                    el.classList.remove('border-primary', 'bg-blue-50');
                    el.classList.add('border-gray-100');
                    el.querySelector('i:last-child').classList.add('text-gray-300');
                    el.querySelector('i:last-child').classList.remove('text-primary');
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
            document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                radio.checked = radio.value === method;
            });
            updateOptionStyles();
        }

        function applyCoupon() {
            const couponCode = document.getElementById('couponCode').value.trim().toUpperCase();
            
            if (!couponCode) {
                showCouponMessage('Please enter a coupon code', 'error');
                return;
            }

            showCouponMessage('Checking coupon...', 'info');

            fetch('../Backend/apply-coupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `coupon_code=${couponCode}&order_amount=${subtotalAmount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentDiscount = parseFloat(data.discount_amount);
                    appliedCouponCode = couponCode;
                    
                    showCouponMessage(`Coupon applied! You saved Rs. ${currentDiscount.toFixed(2)}`, 'success');
                    updateTotals();
                    
                    document.getElementById('couponCode').disabled = true;
                    const btn = document.querySelector('button[onclick="applyCoupon()"]');
                    btn.innerHTML = 'Remove';
                    btn.classList.replace('bg-navy', 'bg-danger');
                    btn.classList.replace('hover:bg-primary', 'hover:bg-red-700');
                    btn.setAttribute('onclick', 'removeCoupon()');
                } else {
                    showCouponMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showCouponMessage('Error checking coupon. Please try again.', 'error');
            });
        }

        function removeCoupon() {
            currentDiscount = 0;
            appliedCouponCode = '';
            
            const input = document.getElementById('couponCode');
            input.value = '';
            input.disabled = false;
            
            const btn = document.querySelector('button[onclick="removeCoupon()"]');
            btn.innerHTML = 'Apply';
            btn.classList.replace('bg-danger', 'bg-navy');
            btn.classList.replace('hover:bg-red-700', 'hover:bg-primary');
            btn.setAttribute('onclick', 'applyCoupon()');
            
            showCouponMessage('', '');
            updateTotals();
        }

        function showCouponMessage(message, type) {
            const messageDiv = document.getElementById('couponMessage');
            if (message) {
                let colorClass = type === 'success' ? 'text-lime font-bold' : (type === 'error' ? 'text-danger font-bold' : 'text-primary font-bold');
                let iconClass = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-spinner fa-spin');
                messageDiv.innerHTML = `<span class="${colorClass} text-sm"><i class="fas ${iconClass} me-1"></i> ${message}</span>`;
            } else {
                messageDiv.innerHTML = '';
            }
        }

        function updateTotals() {
            const discountRow = document.getElementById('discountRow');
            const discountSpan = document.getElementById('discount');
            const finalTotalSpan = document.getElementById('finalTotal');
            
            if (currentDiscount > 0) {
                discountRow.classList.remove('hidden');
                discountSpan.textContent = `- Rs. ${currentDiscount.toFixed(2)}`;
            } else {
                discountRow.classList.add('hidden');
            }
            
            const finalTotal = subtotalAmount - currentDiscount + deliveryFeeAmount;
            finalTotalSpan.textContent = `Rs. ${finalTotal.toFixed(2)}`;
        }

        function goToHome() {
            window.location.href = 'index.php';
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateOptionStyles();
        });

        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('coupon_code', appliedCouponCode);
            formData.append('discount_amount', currentDiscount);
            formData.append('final_total', subtotalAmount - currentDiscount + deliveryFeeAmount);
            
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            const spinner = document.getElementById('spinner');
            
            placeOrderBtn.disabled = true;
            spinner.classList.remove('hidden');
            
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            
            if (paymentMethod === 'PayHere') {
                formData.append('action', 'prepare_payment');
                
                fetch('../Backend/process-order.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = `../PayHere/index.php?order_id=${data.order_id}&amount=${data.amount}`;
                    } else {
                        if(typeof showToast === 'function') showToast(data.message, 'error'); else alert(data.message);
                        placeOrderBtn.disabled = false;
                        spinner.classList.add('hidden');
                    }
                })
                .catch(error => {
                    if(typeof showToast === 'function') showToast('An error occurred.', 'error'); else alert('Error');
                    placeOrderBtn.disabled = false;
                    spinner.classList.add('hidden');
                });
            } else {
                formData.append('action', 'place_order');
                
                fetch('../Backend/process-order.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Order Placed!',
                            text: 'Thank you for your purchase.',
                            icon: 'success',
                            confirmButtonColor: '#1677FF',
                            confirmButtonText: 'Go to Home'
                        }).then(() => {
                            goToHome();
                        });
                        
                        setTimeout(goToHome, 3000);
                    } else {
                        if(typeof showToast === 'function') showToast(data.message, 'error'); else alert(data.message);
                        placeOrderBtn.disabled = false;
                        spinner.classList.add('hidden');
                    }
                })
                .catch(error => {
                    if(typeof showToast === 'function') showToast('An error occurred.', 'error'); else alert('Error');
                    placeOrderBtn.disabled = false;
                    spinner.classList.add('hidden');
                });
            }
        });
    </script>

<?php include('../include/footer.php'); ?>