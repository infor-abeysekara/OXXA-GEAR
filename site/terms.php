<?php
session_start();
include('../include/connection.php');
include('../include/header.php');
?>

<div class="bg-[#0f0f0f] text-white min-h-screen py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Section -->
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-black font-space tracking-tight text-white mb-6 uppercase">Terms & Conditions</h1>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">
                Please read these terms and conditions carefully before using the OXXA GEAR website and services.
            </p>
        </div>

        <!-- Content Section -->
        <div class="bg-[#1a1a1a] p-8 md:p-12 rounded-2xl border border-[#333333] shadow-2xl space-y-10">
            
            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">1. Introduction</h2>
                <p class="text-gray-400 leading-relaxed">
                    By accessing and using this website, you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to abide by these terms, please do not use this website.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">2. Use of the Site</h2>
                <p class="text-gray-400 leading-relaxed mb-4">
                    You may use our site only for lawful purposes. You may not use our site:
                </p>
                <ul class="list-disc list-inside text-gray-400 space-y-2 ml-4">
                    <li>In any way that breaches any applicable local, national, or international law or regulation.</li>
                    <li>In any way that is unlawful or fraudulent, or has any unlawful or fraudulent purpose or effect.</li>
                    <li>To transmit, or procure the sending of, any unsolicited or unauthorized advertising or promotional material.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">3. Intellectual Property Rights</h2>
                <p class="text-gray-400 leading-relaxed">
                    All content on this website, including but not limited to text, graphics, logos, images, audio clips, and software, is the property of OXXA GEAR or its content suppliers and is protected by international copyright laws.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">4. Product Information and Pricing</h2>
                <p class="text-gray-400 leading-relaxed">
                    We make every effort to display as accurately as possible the colors, features, specifications, and details of the products available on the site. However, we do not guarantee that the colors, features, specifications, and details will be accurate, complete, reliable, current, or free of other errors. Prices are subject to change without notice.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">5. Limitation of Liability</h2>
                <p class="text-gray-400 leading-relaxed">
                    OXXA GEAR shall not be liable for any direct, indirect, incidental, special, or consequential damages resulting from the use or inability to use our website or products, including but not limited to damages for loss of profits, data, or other intangibles.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold font-space text-white mb-4 uppercase">6. Governing Law</h2>
                <p class="text-gray-400 leading-relaxed">
                    These Terms & Conditions and any separate agreements whereby we provide you services shall be governed by and construed in accordance with the laws of Sri Lanka.
                </p>
            </section>

            <section class="border-t border-[#333333] pt-8 mt-8 text-sm text-gray-500">
                <p>Last updated: <?php echo date('F Y'); ?></p>
                <p class="mt-2">Contact us: <a href="mailto:legal@oxxagear.lk" class="text-primary hover:underline">legal@oxxagear.lk</a></p>
            </section>

        </div>
    </div>
</div>

<?php include('../include/footer.php'); ?>
