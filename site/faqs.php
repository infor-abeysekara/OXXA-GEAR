<?php
session_start();
include('../include/connection.php');
include('../include/header.php');
?>

<div class="bg-[#0f0f0f] text-white min-h-screen py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Section -->
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-black font-space tracking-tight text-white mb-6 uppercase">Frequently Asked Questions</h1>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">
                Got a question? We're here to help. If you don't see your question here, feel free to reach out to our support team.
            </p>
        </div>

        <!-- FAQs Section -->
        <div class="space-y-6">
            
            <!-- FAQ Item 1 -->
            <div class="bg-[#1a1a1a] rounded-xl border border-[#333333] overflow-hidden transition-all duration-300 hover:border-primary faq-item">
                <button class="w-full px-6 py-5 flex items-center justify-between focus:outline-none" onclick="toggleFaq(this)">
                    <h3 class="text-lg font-bold font-space uppercase text-left">How long does delivery take?</h3>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300 faq-icon"></i>
                </button>
                <div class="px-6 pb-5 text-gray-400 leading-relaxed hidden faq-content">
                    Delivery usually takes 1-3 business days within Colombo and suburbs, and 3-7 business days for outstation areas.
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="bg-[#1a1a1a] rounded-xl border border-[#333333] overflow-hidden transition-all duration-300 hover:border-primary faq-item">
                <button class="w-full px-6 py-5 flex items-center justify-between focus:outline-none" onclick="toggleFaq(this)">
                    <h3 class="text-lg font-bold font-space uppercase text-left">What payment methods do you accept?</h3>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300 faq-icon"></i>
                </button>
                <div class="px-6 pb-5 text-gray-400 leading-relaxed hidden faq-content">
                    We accept all major credit and debit cards (Visa, Mastercard), KOKO Pay in 3 installments, and Cash on Delivery (COD).
                </div>
            </div>

            <!-- FAQ Item 3 -->
            <div class="bg-[#1a1a1a] rounded-xl border border-[#333333] overflow-hidden transition-all duration-300 hover:border-primary faq-item">
                <button class="w-full px-6 py-5 flex items-center justify-between focus:outline-none" onclick="toggleFaq(this)">
                    <h3 class="text-lg font-bold font-space uppercase text-left">Can I return or exchange an item?</h3>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300 faq-icon"></i>
                </button>
                <div class="px-6 pb-5 text-gray-400 leading-relaxed hidden faq-content">
                    Yes, we offer a 14-day return and exchange policy for unused items in their original packaging. Please check our Returns Policy for full details.
                </div>
            </div>

            <!-- FAQ Item 4 -->
            <div class="bg-[#1a1a1a] rounded-xl border border-[#333333] overflow-hidden transition-all duration-300 hover:border-primary faq-item">
                <button class="w-full px-6 py-5 flex items-center justify-between focus:outline-none" onclick="toggleFaq(this)">
                    <h3 class="text-lg font-bold font-space uppercase text-left">Are your products authentic?</h3>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300 faq-icon"></i>
                </button>
                <div class="px-6 pb-5 text-gray-400 leading-relaxed hidden faq-content">
                    Absolutely. We guarantee that 100% of the products sold on OXXA GEAR are authentic and sourced directly from official distributors or manufacturers.
                </div>
            </div>
            
            <!-- FAQ Item 5 -->
            <div class="bg-[#1a1a1a] rounded-xl border border-[#333333] overflow-hidden transition-all duration-300 hover:border-primary faq-item">
                <button class="w-full px-6 py-5 flex items-center justify-between focus:outline-none" onclick="toggleFaq(this)">
                    <h3 class="text-lg font-bold font-space uppercase text-left">How do I track my order?</h3>
                    <i class="fas fa-chevron-down text-gray-500 transition-transform duration-300 faq-icon"></i>
                </button>
                <div class="px-6 pb-5 text-gray-400 leading-relaxed hidden faq-content">
                    Once your order is shipped, you will receive an email with a tracking number. You can also log into your account and view the tracking status under the "My Orders" section.
                </div>
            </div>

        </div>
        
        <div class="mt-16 text-center">
            <p class="text-gray-400 mb-4">Still have questions?</p>
            <a href="<?php echo $base_path ?? '../'; ?>index.php#contact" class="inline-block bg-primary hover:bg-primary-hover text-white font-bold py-3 px-8 rounded-full uppercase tracking-wide transition-colors">Contact Support</a>
        </div>

    </div>
</div>

<script>
    function toggleFaq(button) {
        const content = button.nextElementSibling;
        const icon = button.querySelector('.faq-icon');
        
        // Close all other FAQs
        document.querySelectorAll('.faq-content').forEach(el => {
            if (el !== content && !el.classList.contains('hidden')) {
                el.classList.add('hidden');
                el.previousElementSibling.querySelector('.faq-icon').classList.remove('rotate-180', 'text-primary');
            }
        });

        // Toggle current FAQ
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180', 'text-primary');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180', 'text-primary');
        }
    }
</script>

<?php include('../include/footer.php'); ?>
