  </main> <!-- This closes the <main> tag opened in header.php -->

  <!-- Professional Footer -->
  <footer class="bg-[#0A0A0A] text-white border-t border-[#222222] mt-10">
    
    <!-- Top Trust Bar -->
    <div class="border-b border-[#222222] bg-[#0f0f0f]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-y-4 gap-x-2 text-center md:divide-x divide-[#222222]">
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-3">
                    <i class="fas fa-truck-fast text-primary text-lg sm:text-xl"></i>
                    <span class="font-space uppercase tracking-wider text-[10px] sm:text-sm font-bold text-gray-300">Islandwide Delivery</span>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-3 border-l border-[#222222] md:border-l-0">
                    <i class="fas fa-shield-check text-primary text-lg sm:text-xl"></i>
                    <span class="font-space uppercase tracking-wider text-[10px] sm:text-sm font-bold text-gray-300">100% Authentic</span>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-3 pt-4 sm:pt-0 border-t border-[#222222] md:border-t-0">
                    <img src="<?php echo $base_path; ?>image/KOKO_logo.png" class="h-3 sm:h-4 w-auto brightness-0 invert opacity-70" alt="KOKO">
                    <span class="font-space uppercase tracking-wider text-[10px] sm:text-sm font-bold text-gray-300">Pay in 3</span>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-3 pt-4 sm:pt-0 border-t border-[#222222] md:border-t-0 border-l border-[#222222] md:border-l-0">
                    <i class="fas fa-undo-alt text-primary text-lg sm:text-xl"></i>
                    <span class="font-space uppercase tracking-wider text-[10px] sm:text-sm font-bold text-gray-300">14-Day Returns</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
        
        <!-- Col 1 - Brand Info -->
        <div class="space-y-4 sm:space-y-6 text-left flex flex-col items-start">
          <div class="flex items-start space-x-3">
            <?php
            $current_dir = dirname($_SERVER['PHP_SELF']);
            $base_path = (strpos($current_dir, '/site') !== false) ? '../' : '';
            ?>
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center space-x-2 group">
              <img src="<?php echo $base_path; ?>image/oxxa_gear_logo.png" alt="OXXA GEAR" class="h-8 sm:h-12 w-auto object-contain filter brightness-0 invert transform group-hover:scale-105 transition-transform duration-300">
            </a>
          </div>
          <p class="text-gray-400 text-xs sm:text-sm leading-relaxed sm:leading-loose max-w-xs m-0">
            OXXA GEAR - Sri Lanka's ultimate destination for premium sports wear, footwear, fitness gear and nutrition. Gear Up. Train Hard.
          </p>
          <div class="flex space-x-3 pt-2 justify-start">
            <a href="#" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary transition-all duration-300">
              <i class="fab fa-facebook-f text-sm sm:text-base"></i>
            </a>
            <a href="#" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary transition-all duration-300">
              <i class="fab fa-instagram text-sm sm:text-base"></i>
            </a>
            <a href="#" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary transition-all duration-300">
              <i class="fab fa-twitter text-sm sm:text-base"></i>
            </a>
            <a href="#" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary transition-all duration-300">
              <i class="fab fa-youtube text-sm sm:text-base"></i>
            </a>
          </div>
        </div>

        <!-- Col 2 - Quick Links -->
        <div class="space-y-0 sm:space-y-4 text-left flex flex-col items-start w-full border-b border-[#222222] sm:border-0">
          <div class="footer-accordion-header flex justify-between items-center w-full cursor-pointer sm:cursor-default py-4 sm:py-0" data-target="footerQuickLinks">
            <h4 class="text-sm sm:text-lg font-bold font-space text-white tracking-widest uppercase m-0 pointer-events-none">Quick Links</h4>
            <i class="fas fa-chevron-down sm:hidden text-white transition-transform duration-300 pointer-events-none"></i>
          </div>
          <ul id="footerQuickLinks" class="hidden sm:block space-y-3 pb-4 sm:pb-0 w-full">
            <?php
            $current_dir = dirname($_SERVER['PHP_SELF']);
            $base_path = '';
            if (strpos($current_dir, '/site') !== false) {
              $base_path = '../';
            }
            ?>
            <li><a href="<?php echo $base_path; ?>index.php" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">Home</a></li>
            <li><a href="<?php echo $base_path; ?>site/shop.php" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">Products</a></li>
            <li><a href="<?php echo $base_path; ?>site/my-orders.php" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">Track Order</a></li>
            <li><a href="<?php echo $base_path; ?>site/shipping.php" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">Shipping Info</a></li>
            <li><a href="<?php echo $base_path; ?>site/faqs.php" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">FAQs</a></li>
            <li><a href="<?php echo $base_path; ?>index.php#contact" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">Contact Us</a></li>
          </ul>
        </div>

        <!-- Col 3 - Categories -->
        <div class="space-y-0 sm:space-y-4 text-left flex flex-col items-start w-full border-b border-[#222222] sm:border-0">
          <div class="footer-accordion-header flex justify-between items-center w-full cursor-pointer sm:cursor-default py-4 sm:py-0" data-target="footerCategories">
            <h4 class="text-sm sm:text-lg font-bold font-space text-white tracking-widest uppercase m-0 pointer-events-none">Categories</h4>
            <i class="fas fa-chevron-down sm:hidden text-white transition-transform duration-300 pointer-events-none"></i>
          </div>
          <ul id="footerCategories" class="hidden sm:block space-y-3 pb-4 sm:pb-0 w-full">
            <li><a href="<?php echo $base_path; ?>site/shop.php?category=Sports+Wear" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">SPORTS WEAR</a></li>
            <li><a href="<?php echo $base_path; ?>site/shop.php?category=Footwear" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">FOOTWEAR</a></li>
            <li><a href="<?php echo $base_path; ?>site/shop.php?category=Fitness+%26+Gym" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">FITNESS & GYM</a></li>
            <li><a href="<?php echo $base_path; ?>site/shop.php?category=Nutrition" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">NUTRITION</a></li>
            <li><a href="<?php echo $base_path; ?>site/shop.php?category=Accessories" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">ACCESSORIES</a></li>
            <li><a href="<?php echo $base_path; ?>site/shop.php?category=Equipment" class="text-gray-400 hover:text-primary transition-all text-sm block py-1">EQUIPMENT</a></li>
          </ul>
        </div>

        <!-- Col 4 - Newsletter & Contact -->
        <div class="space-y-4 sm:space-y-6 text-left border-t border-[#222222] sm:border-0 pt-6 sm:pt-0 flex flex-col items-start w-full">
          <h4 class="text-sm sm:text-lg font-bold font-space text-white tracking-widest uppercase">Stay In The Game</h4>
          <form class="flex border border-gray-700 rounded-full overflow-hidden focus-within:border-primary transition-colors w-full max-w-sm m-0">
            <input type="email" placeholder="Enter your email" class="w-full bg-transparent px-4 py-2 sm:py-3 text-xs sm:text-sm text-white focus:outline-none placeholder-gray-500" required>
            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-4 sm:px-5 py-2 sm:py-3 text-xs sm:text-sm font-bold uppercase transition-colors">JOIN</button>
          </form>
          
          <div class="space-y-2 sm:space-y-3 pt-2 sm:pt-4 flex flex-col items-start w-full max-w-sm m-0">
            <div class="flex items-start space-x-3 group cursor-default text-left">
              <i class="fas fa-map-marker-alt text-primary mt-1 text-xs sm:text-sm"></i>
              <p class="text-gray-400 text-xs sm:text-sm">123 Main Street,<br>Colombo 07, Sri Lanka</p>
            </div>
            <div class="flex items-center space-x-3 group cursor-default text-left">
              <i class="fas fa-phone-alt text-primary text-xs sm:text-sm"></i>
              <p class="text-gray-400 text-xs sm:text-sm">+94 77 123 4567</p>
            </div>
            <div class="flex items-center space-x-3 group cursor-pointer text-left">
              <i class="fas fa-envelope text-primary text-xs sm:text-sm"></i>
              <a href="mailto:support@oxxagear.lk" class="text-gray-400 hover:text-white transition-colors text-xs sm:text-sm">support@oxxagear.lk</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="border-t border-[#222222] bg-[#0A0A0A]">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div class="flex flex-col-reverse md:flex-row justify-between items-center gap-4">
          <div class="text-gray-500 text-[10px] sm:text-xs font-space tracking-wide text-center">
            &copy; <?php echo date("Y"); ?> OXXA GEAR. All rights reserved.
          </div>
          
          <!-- Payment Badges -->
          <div class="flex items-center justify-center gap-3 opacity-70 hover:opacity-100 transition-opacity duration-300">
            <i class="fab fa-cc-visa text-2xl sm:text-3xl text-white"></i>
            <i class="fab fa-cc-mastercard text-2xl sm:text-3xl text-white"></i>
            <div class="h-5 sm:h-6 flex items-center bg-white rounded px-1.5 sm:px-2">
                <img src="<?php echo $base_path; ?>image/KOKO_logo.png" class="h-2.5 sm:h-3 w-auto" alt="KOKO">
            </div>
            <div class="h-5 sm:h-6 flex items-center border border-gray-600 rounded px-1.5 sm:px-2">
                <span class="font-bold text-gray-300 tracking-widest text-[10px] sm:text-xs">COD</span>
            </div>
          </div>

          <div class="flex justify-center space-x-4 sm:space-x-6">
            <a href="<?php echo $base_path; ?>site/privacy.php" class="text-gray-500 hover:text-primary transition-colors text-[10px] sm:text-xs font-space uppercase">Privacy</a>
            <a href="<?php echo $base_path; ?>site/terms.php" class="text-gray-500 hover:text-primary transition-colors text-[10px] sm:text-xs font-space uppercase">Terms</a>
            <a href="<?php echo $base_path; ?>site/returns.php" class="text-gray-500 hover:text-primary transition-colors text-[10px] sm:text-xs font-space uppercase">Returns</a>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    /* Footer specific styles */
    footer {
      background: linear-gradient(135deg, #1f2937 0%, #374151 50%, #1f2937 100%);
    }
    
    footer .grid > div {
      animation: fadeInUp 0.6s ease-out;
    }
    
    footer .grid > div:nth-child(2) {
      animation-delay: 0.1s;
    }
    
    footer .grid > div:nth-child(3) {
      animation-delay: 0.2s;
    }
    
    footer .grid > div:nth-child(4) {
      animation-delay: 0.3s;
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Hover effects for social icons */
    footer .fab:hover {
      transform: scale(1.2);
      transition: transform 0.2s ease;
    }
  </style>
  <!-- Premium Slide-in Cart Drawer & Quick Add Modal -->
  <?php include(__DIR__ . '/../site/components/cart-drawer.php'); ?>
  <?php include(__DIR__ . '/../site/components/quick-add-modal.php'); ?>
  <script src="<?php echo $base_path; ?>site/js/cart-manager.js?v=<?php echo filemtime(__DIR__ . '/../site/js/cart-manager.js'); ?>"></script>

  <!-- Global Custom JS Functions -->
  <script>
    // 1. Toast Notification System
    function showToast(message, type = 'success') {
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
          popup: 'rounded-xl shadow-lg border border-gray-100'
        },
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });

      Toast.fire({
        icon: type,
        title: message
      });
    }

    // 2. Add to Cart Animation
    function addToCartAnimation(buttonElement) {
      // Small pop animation on the button
      buttonElement.classList.add('animate__animated', 'animate__heartBeat');
      setTimeout(() => {
        buttonElement.classList.remove('animate__animated', 'animate__heartBeat');
      }, 1000);

      // Show success toast
      showToast('Added to Cart Successfully', 'success');

      // Optionally increment cart badge (demo purpose)
      const badge = document.getElementById('cartBadge');
      if (badge) {
        let count = parseInt(badge.innerText) || 0;
        badge.innerText = count + 1;
        badge.classList.add('animate__animated', 'animate__rubberBand');
        setTimeout(() => badge.classList.remove('animate__animated', 'animate__rubberBand'), 1000);
      }
    }

    // 3. Footer Accordion for Mobile
    document.addEventListener('DOMContentLoaded', () => {
        const accordionHeaders = document.querySelectorAll('.footer-accordion-header');
        accordionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    const targetId = this.getAttribute('data-target');
                    const targetEl = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    if (targetEl) {
                        targetEl.classList.toggle('hidden');
                    }
                    if (icon) {
                        icon.classList.toggle('rotate-180');
                    }
                }
            });
        });
    });

    // 3. Cart Drawer Toggle & Legacy Aliases
    function toggleCartSidebar() {
      if (typeof CartManager !== 'undefined' && CartManager.openDrawer) {
        const drawer = document.getElementById('cartDrawer');
        if (drawer && drawer.classList.contains('translate-x-0')) {
          CartManager.closeDrawer();
        } else {
          CartManager.openDrawer();
        }
      }
    }

    // 4. Auth Modal Logic
    function openAuthModal(type = 'login') {
        const modal = document.getElementById('authModal');
        const overlay = document.getElementById('authOverlay');
        
        overlay.classList.remove('hidden');
        modal.classList.remove('hidden');
        
        setTimeout(() => { 
            overlay.classList.add('opacity-100'); 
            modal.classList.remove('-translate-y-full'); 
            document.body.style.overflow = 'hidden'; 
        }, 10);
        
        showView(type);
    }

    function closeAuthModal() {
        const modal = document.getElementById('authModal');
        const overlay = document.getElementById('authOverlay');
        
        modal.classList.add('-translate-y-full');
        overlay.classList.remove('opacity-100');
        
        setTimeout(() => { 
            overlay.classList.add('hidden'); 
            modal.classList.add('hidden'); 
            document.body.style.overflow = ''; 
        }, 500); // 500ms matches transition duration
    }

    function showView(type) {
        document.getElementById('loginView').classList.toggle('hidden', type !== 'login');
        document.getElementById('registerView').classList.toggle('hidden', type !== 'register');
    }

    function togglePasswordVisibility(inputId, btnElement) {
        const input = document.getElementById(inputId);
        const icon = btnElement.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function previewModalImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('modalPhotoPreview');
                preview.innerHTML = '<img src="' + e.target.result + '" class="w-full h-full object-cover rounded-full ring-4 ring-blue-100 border-2 border-white">';
                preview.classList.remove('border-dashed');
                preview.classList.add('border-transparent');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Auto-open modal based on URL parameters
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('open') === 'login') {
            openAuthModal('login');
        } else if (urlParams.get('open') === 'register') {
            openAuthModal('register');
        }
    });

    // Close on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeAuthModal();
        }
    });
  </script>

  <!-- Global Auth Modals component -->
  <?php include(__DIR__ . '/../site/components/auth-modals.php'); ?>

  <!-- Bottom Navigation (Mobile Only - Hidden on Checkout for focused order conversion) -->
  <?php 
  $current_script = basename($_SERVER['PHP_SELF']); 
  if ($current_script !== 'checkout.php'): 
  ?>
  <nav class="lg:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-100 z-[80] pb-safe shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
    <div class="flex justify-around items-center h-[60px] px-2">
      <a href="<?php echo $base_path; ?>index.php" class="flex flex-col items-center justify-center w-full h-full space-y-1 <?php echo $current_script == 'index.php' ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
        <i class="fas fa-home text-lg mb-0.5"></i>
        <span class="text-[10px] font-bold">Home</span>
      </a>
      <a href="<?php echo $base_path; ?>site/shop.php" class="flex flex-col items-center justify-center w-full h-full space-y-1 <?php echo $current_script == 'shop.php' ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
        <i class="fas fa-th-large text-lg mb-0.5"></i>
        <span class="text-[10px] font-bold">Categories</span>
      </a>
      <a href="<?php echo $base_path; ?>site/hot-deals.php" class="flex flex-col items-center justify-center w-full h-full space-y-1 <?php echo $current_script == 'hot-deals.php' ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
        <i class="fas fa-fire text-lg mb-0.5"></i>
        <span class="text-[10px] font-bold">Hot Deals</span>
      </a>
      <button onclick="openCartDrawer()" class="flex flex-col items-center justify-center w-full h-full space-y-1 text-gray-400 hover:text-gray-600 relative" aria-label="Cart">
        <i class="fas fa-shopping-bag text-lg mb-0.5"></i>
        <span class="text-[10px] font-bold">Cart</span>
        <span class="cart-badge absolute top-1 right-3 text-white text-[9px] rounded-full w-3.5 h-3.5 flex items-center justify-center font-bold bg-primary border border-white <?php echo (isset($cartCount) && $cartCount > 0) ? '' : 'hidden'; ?>" id="mobileCartBadge">
          <?php echo (isset($cartCount) && $cartCount > 9) ? '9+' : ($cartCount ?? 0); ?>
        </span>
      </button>
      <a href="<?php echo isset($_SESSION['userid']) ? $base_path . 'site/profile.php' : 'javascript:openAuthModal(\'login\')'; ?>" class="flex flex-col items-center justify-center w-full h-full space-y-1 <?php echo in_array($current_script, ['profile.php', 'settings.php', 'my-orders.php', 'address-book.php']) ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
        <i class="fas fa-user text-lg mb-0.5"></i>
        <span class="text-[10px] font-bold">Account</span>
      </a>
    </div>
  </nav>
  <?php endif; ?>

</body>
</html>