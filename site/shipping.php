<?php
session_start();
include('../include/connection.php');
include('../include/header.php');
?>

<div class="bg-[#0f0f0f] text-white min-h-screen py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Section -->
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-black font-space tracking-tight text-white mb-6 uppercase">Shipping Information</h1>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">
                Everything you need to know about how we deliver your gear. Fast, reliable, and secure.
            </p>
        </div>

        <!-- Content Section -->
        <div class="bg-[#1a1a1a] p-8 md:p-12 rounded-2xl border border-[#333333] shadow-2xl space-y-10">
            
            <section>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-blue-500/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-truck-fast text-blue-500 text-xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold font-space text-white uppercase">Delivery Times</h2>
                </div>
                <p class="text-gray-400 leading-relaxed mb-4">
                    We strive to get your gear to you as quickly as possible. Estimated delivery times are as follows:
                </p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 ml-4">
                    <li><strong class="text-white">Colombo & Suburbs:</strong> 1-3 Business Days</li>
                    <li><strong class="text-white">Outstation (Major Cities):</strong> 3-5 Business Days</li>
                    <li><strong class="text-white">Remote Areas:</strong> 5-7 Business Days</li>
                </ul>
            </section>

            <section>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-green-500/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-green-500 text-xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold font-space text-white uppercase">Shipping Rates</h2>
                </div>
                <p class="text-gray-400 leading-relaxed">
                    Our shipping rates are calculated dynamically based on your location and the weight of your order. You can view the exact shipping cost during checkout before finalizing your purchase. 
                    <br><br>
                    <span class="text-primary font-bold">Free Shipping</span> may be available for orders over a certain threshold during promotional periods. Keep an eye out for special offers!
                </p>
            </section>

            <section>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-yellow-500/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-map-location-dot text-yellow-500 text-xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold font-space text-white uppercase">Order Tracking</h2>
                </div>
                <p class="text-gray-400 leading-relaxed">
                    Once your order is dispatched, you will receive a confirmation email containing a tracking number and a link to track your package. You can also view the status of your order directly from the "Track Order" section in your account dashboard.
                </p>
            </section>

            <section>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-red-500/10 rounded-full flex items-center justify-center">
                        <i class="fas fa-box-open text-red-500 text-xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold font-space text-white uppercase">Damaged or Missing Items</h2>
                </div>
                <p class="text-gray-400 leading-relaxed">
                    We take great care in packaging our products securely. However, if your order arrives damaged or if items are missing, please contact our customer support team within 48 hours of delivery. Please retain all packaging materials and take photographs of the damage, as this will help us process your claim quickly.
                </p>
            </section>

            <section class="border-t border-[#333333] pt-8 mt-8 text-sm text-gray-500 text-center">
                <p>Need more help with shipping?</p>
                <p class="mt-2">Contact our logistics team at <a href="mailto:support@oxxagear.lk" class="text-primary hover:underline">support@oxxagear.lk</a></p>
            </section>

        </div>
    </div>
</div>

<?php include('../include/footer.php'); ?>
