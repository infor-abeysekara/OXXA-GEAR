<!-- Slide-in Cart Drawer (400px Premium Right Drawer) -->
<div id="cartDrawerOverlay" class="fixed inset-0 bg-navy/60 backdrop-blur-sm z-[1040] hidden opacity-0 transition-opacity duration-300" onclick="closeCartDrawer()"></div>

<div id="cartDrawer" class="fixed inset-y-0 right-0 w-full sm:w-[420px] bg-white shadow-[-10px_0_40px_rgba(0,0,0,0.15)] z-[1050] transform translate-x-full transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] flex flex-col sm:rounded-l-[20px] overflow-hidden">
    
    <!-- Drawer Header -->
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold">
                <i class="fas fa-shopping-bag text-sm"></i>
            </div>
            <h3 class="font-black text-lg text-navy uppercase tracking-wide">
                Your Cart <span id="cartDrawerCountHeader" class="text-gray-400 font-bold text-sm">(0)</span>
            </h3>
        </div>
        <button onclick="closeCartDrawer()" class="w-8 h-8 rounded-full bg-gray-50 hover:bg-gray-100 text-gray-400 hover:text-navy flex items-center justify-center transition-colors">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- Free Shipping Progress Bar -->
    <div id="freeShippingBanner" class="px-6 py-2.5 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100 shrink-0">
        <div class="flex justify-between items-center text-xs font-bold mb-1.5">
            <span id="freeShippingText" class="text-primary flex items-center gap-1.5">
                <i class="fas fa-truck-fast"></i> Add Rs. 5,000 for FREE Shipping
            </span>
            <span id="freeShippingPercent" class="text-gray-500">0%</span>
        </div>
        <div class="w-full h-1.5 bg-blue-200/50 rounded-full overflow-hidden">
            <div id="freeShippingBar" class="h-full bg-primary rounded-full transition-all duration-500" style="width: 0%;"></div>
        </div>
    </div>

    <!-- Drawer Body (Items List) -->
    <div class="flex-grow overflow-y-auto px-6 py-4 space-y-6" id="cartDrawerBody">
        <!-- Spinner placeholder initially -->
        <div class="flex flex-col items-center justify-center h-64 text-gray-400">
            <i class="fas fa-spinner fa-spin text-2xl mb-2 text-primary"></i>
            <span class="text-xs font-bold uppercase tracking-wider">Loading your cart...</span>
        </div>
    </div>

    <!-- Drawer Empty State (Hidden by default) -->
    <div id="cartDrawerEmpty" class="hidden flex-grow flex flex-col items-center justify-center p-8 text-center">
        <div class="w-24 h-24 rounded-full bg-gray-50 flex items-center justify-center text-gray-300 text-4xl mb-4">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <h4 class="font-black text-xl text-navy uppercase tracking-wide mb-1">Your Cart is Empty</h4>
        <p class="text-gray-400 text-sm max-w-xs mb-6">Discover top sports gear, nutrition and clearance hot deals.</p>
        <a href="<?php echo isset($base_path) ? $base_path : ''; ?>site/shop.php" onclick="closeCartDrawer()" class="px-6 py-3 bg-navy hover:bg-primary text-white font-bold rounded-xl text-xs uppercase tracking-widest transition-all shadow-md">
            Start Shopping
        </a>
    </div>

    <!-- Drawer Footer -->
    <div class="border-t border-gray-100 bg-[#F9FAFB] p-5 shrink-0 space-y-4" id="cartDrawerFooter">
        
        <!-- Coupon Form -->
        <div class="bg-white p-2.5 rounded-xl border border-gray-200 shadow-sm">
            <div id="couponAppliedBadge" class="hidden flex items-center justify-between bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-1.5 rounded-lg text-xs font-bold mb-2">
                <span class="flex items-center gap-1.5">
                    <i class="fas fa-tag"></i> <span id="appliedCouponCode">SAVE20</span> applied
                </span>
                <button onclick="removeCartCoupon()" class="text-emerald-500 hover:text-red-500" title="Remove coupon">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="drawerCouponForm" onsubmit="applyCartCoupon(event)" class="flex gap-2">
                <input type="text" id="drawerCouponInput" placeholder="Promo code (e.g. SAVE20)" class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-navy uppercase font-bold focus:outline-none focus:border-primary">
                <button type="submit" id="drawerCouponBtn" class="px-4 py-2 bg-gray-900 hover:bg-primary text-white font-bold text-xs rounded-lg uppercase tracking-wider transition-colors">
                    Apply
                </button>
            </form>
        </div>

        <!-- Totals Breakdown -->
        <div class="space-y-2 text-xs font-medium text-gray-500">
            <div class="flex justify-between items-center">
                <span>Subtotal</span>
                <span id="drawerSubtotal" class="font-bold text-navy text-sm">Rs. 0</span>
            </div>
            <div class="flex justify-between items-center">
                <span>Delivery Fee</span>
                <span id="drawerShipping" class="font-bold text-navy">Rs. 0</span>
            </div>
            <div id="drawerDiscountRow" class="hidden flex justify-between items-center text-emerald-600 font-bold">
                <span>Coupon Savings</span>
                <span id="drawerDiscount">-Rs. 0</span>
            </div>
            <div class="pt-2 border-t border-gray-200 flex justify-between items-baseline text-navy">
                <span class="font-black text-sm uppercase tracking-wider">Estimated Total</span>
                <span id="drawerTotal" class="font-black text-xl text-primary">Rs. 0</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2 pt-1">
            <a href="<?php echo isset($base_path) ? $base_path : ''; ?>site/checkout.php" class="block w-full py-3.5 bg-primary hover:bg-primary-hover text-white text-center font-bold text-xs uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-500/25 flex items-center justify-center gap-2">
                <span>Proceed to Checkout</span>
                <i class="fas fa-arrow-right text-[11px]"></i>
            </a>
            <button type="button" onclick="closeCartDrawer()" class="block w-full py-2.5 text-center text-gray-500 hover:text-navy font-bold text-xs uppercase tracking-wider transition-colors">
                Continue Shopping
            </button>
        </div>

    </div>

</div>
