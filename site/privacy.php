<?php
session_start();
include('../include/connection.php');
include('../include/header.php');
?>

<div class="bg-[#0f0f0f] text-white min-h-screen py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Section -->
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-black font-space tracking-tight text-white mb-6 uppercase">Privacy Policy</h1>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">
                At OXXA GEAR, we are committed to protecting your privacy. This policy explains how we collect, use, and safeguard your personal information.
            </p>
        </div>

        <!-- Content Section -->
        <div class="bg-[#1a1a1a] p-8 md:p-12 rounded-2xl border border-[#333333] shadow-2xl space-y-10">
            
            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">1. Information We Collect</h2>
                <p class="text-gray-400 leading-relaxed mb-4">
                    We collect information you provide directly to us when you create an account, make a purchase, or communicate with us. This may include:
                </p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 ml-4">
                    <li>Name and contact details (email, phone number)</li>
                    <li>Shipping and billing addresses</li>
                    <li>Payment information (processed securely via our payment gateways)</li>
                    <li>Order history and preferences</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">2. How We Use Your Information</h2>
                <p class="text-gray-400 leading-relaxed mb-4">
                    Your information is used to provide, maintain, and improve our services, including:
                </p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 ml-4">
                    <li>Processing your orders and sending order updates</li>
                    <li>Personalizing your shopping experience</li>
                    <li>Responding to customer service requests</li>
                    <li>Sending marketing communications (you can opt-out at any time)</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">3. Data Security</h2>
                <p class="text-gray-400 leading-relaxed">
                    We implement industry-standard security measures to protect your personal data from unauthorized access, disclosure, or destruction. However, please be aware that no method of transmission over the internet or electronic storage is 100% secure.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">4. Sharing Your Information</h2>
                <p class="text-gray-400 leading-relaxed">
                    We do not sell, trade, or rent your personal information to third parties. We may share your data with trusted service providers who assist us in operating our website, conducting our business, or serving our users, so long as those parties agree to keep this information confidential.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">5. Cookies and Tracking Technologies</h2>
                <p class="text-gray-400 leading-relaxed">
                    Our website uses cookies to enhance your browsing experience, analyze site traffic, and understand where our audience comes from. You can control cookie preferences through your browser settings.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">6. Your Rights</h2>
                <p class="text-gray-400 leading-relaxed">
                    You have the right to access, update, or delete your personal information. If you wish to exercise these rights or have any questions about our privacy practices, please contact our support team.
                </p>
            </section>

            <section class="border-t border-[#333333] pt-8 mt-8 text-sm text-gray-500">
                <p>Last updated: <?php echo date('F Y'); ?></p>
                <p class="mt-2">Contact us: <a href="mailto:support@oxxagear.lk" class="text-primary hover:underline">support@oxxagear.lk</a></p>
            </section>

        </div>
    </div>
</div>

<?php include('../include/footer.php'); ?>
