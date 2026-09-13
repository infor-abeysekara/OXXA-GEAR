  </main> <!-- This closes the <main> tag opened in header.php -->

  <!-- Professional Footer -->
  <footer class="bg-[#0A0A0A] text-white border-t border-[#222222] mt-10">
    
    <!-- Top Trust Bar -->
    <div class="border-b border-[#222222]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center divide-x divide-[#222222]">
                <div class="flex items-center justify-center gap-3">
                    <i class="fas fa-truck-fast text-primary text-xl"></i>
                    <span class="font-space uppercase tracking-wide text-sm font-bold">Islandwide Delivery</span>
                </div>
                <div class="flex items-center justify-center gap-3">
                    <i class="fas fa-shield-check text-primary text-xl"></i>
                    <span class="font-space uppercase tracking-wide text-sm font-bold">100% Authentic</span>
                </div>
                <div class="flex items-center justify-center gap-3">
                    <i class="fas fa-credit-card text-primary text-xl"></i>
                    <span class="font-space uppercase tracking-wide text-sm font-bold">KOKO Pay in 3</span>
                </div>
                <div class="flex items-center justify-center gap-3">
                    <i class="fas fa-undo-alt text-primary text-xl"></i>
                    <span class="font-space uppercase tracking-wide text-sm font-bold">14-Day Returns</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
        
        <!-- Col 1 - Brand Info -->
        <div class="space-y-6">
          <div class="flex items-center space-x-3">
            <?php
            $current_dir = dirname($_SERVER['PHP_SELF']);
            $base_path = (strpos($current_dir, '/site') !== false) ? '../' : '';
            ?>
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center space-x-2 group">
              <img src="<?php echo $base_path; ?>image/oxxa_gear_logo.png" alt="OXXA GEAR" class="h-12 w-auto object-contain filter brightness-0 invert transform group-hover:scale-105 transition-transform duration-300">
            </a>
          </div>
          <p class="text-gray-400 text-sm leading-loose">
            OXXA GEAR - Sri Lanka's ultimate destination for premium sports wear, footwear, fitness gear and nutrition. Gear Up. Train Hard.
          </p>
          <div class="flex space-x-4 pt-2">
            <a href="#" class="w-10 h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary hover:scale-110 transition-all duration-300">
              <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary hover:scale-110 transition-all duration-300">
              <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary hover:scale-110 transition-all duration-300">
              <i class="fab fa-twitter"></i>
            </a>
            <a href="#" class="w-10 h-10 rounded-full border border-gray-700 flex items-center justify-center text-gray-400 hover:text-white hover:border-primary hover:bg-primary hover:scale-110 transition-all duration-300">
              <i class="fab fa-youtube"></i>
            </a>
          </div>
        </div>

        <!-- Col 2 - Quick Links -->
        <div class="space-y-6">
          <h4 class="text-lg font-bold font-space text-white tracking-wide uppercase">Quick Links</h4>
          <ul class="space-y-3">
            <?php
            $current_dir = dirname($_SERVER['PHP_SELF']);
            $base_path = '';
            if (strpos($current_dir, '/site') !== false) {
              $base_path = '../';
            }
            ?>
            <li><a href="<?php echo $base_path; ?>index.php" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">Home</a></li>
            <li><a href="<?php echo $base_path; ?>site/products.php" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">Products</a></li>
            <li><a href="<?php echo $base_path; ?>site/my-orders.php" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">Track Order</a></li>
            <li><a href="#" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">Shipping Info</a></li>
            <li><a href="#" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">FAQs</a></li>
            <li><a href="<?php echo $base_path; ?>index.php#contact" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">Contact Us</a></li>
          </ul>
        </div>

        <!-- Col 3 - Categories -->
        <div class="space-y-6">
          <h4 class="text-lg font-bold font-space text-white tracking-wide uppercase">Categories</h4>
          <ul class="space-y-3">
            <li><a href="<?php echo $base_path; ?>site/products.php?category=Sports Wear" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">SPORTS WEAR</a></li>
            <li><a href="<?php echo $base_path; ?>site/products.php?category=Footwear" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">FOOTWEAR</a></li>
            <li><a href="<?php echo $base_path; ?>site/products.php?category=Fitness & Gym" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">FITNESS & GYM</a></li>
            <li><a href="<?php echo $base_path; ?>site/products.php?category=Nutrition" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">NUTRITION</a></li>
            <li><a href="<?php echo $base_path; ?>site/products.php?category=Accessories" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">ACCESSORIES</a></li>
            <li><a href="<?php echo $base_path; ?>site/products.php?category=Equipment" class="text-gray-400 hover:text-primary hover:translate-x-1 inline-block transition-all text-sm">EQUIPMENT</a></li>
          </ul>
        </div>

        <!-- Col 4 - Newsletter & Contact -->
        <div class="space-y-6">
          <h4 class="text-lg font-bold font-space text-white tracking-wide uppercase">Stay In The Game</h4>
          <form class="flex border border-gray-700 rounded-full overflow-hidden focus-within:border-primary transition-colors">
            <input type="email" placeholder="Enter your email" class="w-full bg-transparent px-4 py-2 text-sm text-white focus:outline-none placeholder-gray-500" required>
            <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-5 py-2 text-sm font-bold uppercase transition-colors">JOIN</button>
          </form>
          
          <div class="space-y-3 pt-4 border-t border-[#222222]">
            <div class="flex items-start space-x-3 group cursor-default">
              <i class="fas fa-map-marker-alt text-primary mt-1 text-sm group-hover:scale-110 transition-transform"></i>
              <p class="text-gray-400 text-sm">123 Main Street,<br>Colombo 07, Sri Lanka</p>
            </div>
            <div class="flex items-center space-x-3 group cursor-default">
              <i class="fas fa-phone-alt text-primary text-sm group-hover:scale-110 transition-transform"></i>
              <p class="text-gray-400 text-sm">+94 77 123 4567</p>
            </div>
            <div class="flex items-center space-x-3 group cursor-pointer">
              <i class="fas fa-envelope text-primary text-sm group-hover:scale-110 transition-transform"></i>
              <a href="mailto:support@oxxagear.lk" class="text-gray-400 hover:text-white transition-colors text-sm">support@oxxagear.lk</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div class="border-t border-[#222222] bg-[#0A0A0A]">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
          <div class="text-gray-500 text-xs font-space tracking-wide">
            &copy; <?php echo date("Y"); ?> OXXA GEAR. All rights reserved.
          </div>
          
          <!-- Payment Badges (SVG or Icon versions) -->
          <div class="flex items-center gap-4 opacity-70 hover:opacity-100 transition-opacity duration-300">
            <i class="fab fa-cc-visa text-3xl text-white"></i>
            <i class="fab fa-cc-mastercard text-3xl text-white"></i>
            <div class="h-6 flex items-center bg-white rounded px-2">
                <span class="font-black text-black tracking-tighter text-xs">Koko</span>
            </div>
            <div class="h-6 flex items-center border border-gray-600 rounded px-2">
                <span class="font-bold text-gray-300 tracking-widest text-xs">COD</span>
            </div>
          </div>

          <div class="flex space-x-6">
            <a href="#" class="text-gray-500 hover:text-primary transition-colors text-xs font-space uppercase">Privacy</a>
            <a href="#" class="text-gray-500 hover:text-primary transition-colors text-xs font-space uppercase">Terms</a>
            <a href="#" class="text-gray-500 hover:text-primary transition-colors text-xs font-space uppercase">Returns</a>
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

  <!-- Slide-in Cart Drawer -->
  <div id="cartSidebar" class="fixed inset-y-0 right-0 w-full md:w-[400px] bg-white shadow-2xl z-[1050] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    <!-- Header -->
    <div class="px-6 py-4 border-b flex justify-between items-center bg-navy text-white">
      <h3 class="font-bold text-lg uppercase tracking-wide flex items-center">
        <i class="fas fa-shopping-bag me-3 text-primary"></i> Your Cart
      </h3>
      <button onclick="toggleCartSidebar()" class="text-gray-300 hover:text-white transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    
    <!-- Body -->
    <div class="flex-grow overflow-y-auto p-6" id="cartSidebarItems">
      <!-- Example Item -->
      <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100 relative">
        <button class="absolute top-0 right-0 text-gray-400 hover:text-danger"><i class="fas fa-trash-alt"></i></button>
        <img src="https://images.unsplash.com/photo-1593477004927-89c6dda7c4c9?ixlib=rb-4.0.3&w=100&q=80" alt="Product" class="w-20 h-20 object-cover rounded-lg bg-gray-50 border border-gray-100">
        <div class="flex-grow">
          <h4 class="font-bold text-navy text-sm mb-1 leading-tight">Whey Protein Isolate</h4>
          <p class="text-slate text-xs mb-2">Vanilla / 2lbs</p>
          <div class="flex justify-between items-center">
            <span class="font-bold text-primary">Rs. 4,500</span>
            <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden h-8">
              <button class="px-2 text-gray-500 hover:bg-gray-100 transition-colors">-</button>
              <input type="text" value="1" class="w-8 text-center text-sm font-medium border-x border-gray-200 focus:outline-none" readonly>
              <button class="px-2 text-gray-500 hover:bg-gray-100 transition-colors">+</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="border-t bg-gray-50 p-6">
      <div class="flex justify-between items-center mb-4 text-navy">
        <span class="font-medium">Subtotal</span>
        <span class="font-extrabold text-xl">Rs. 4,500</span>
      </div>
      <a href="<?php echo $base_path; ?>site/checkout.php" class="block w-full bg-primary hover:bg-primary-hover text-white text-center font-bold py-3.5 rounded-xl uppercase tracking-wide transition-colors shadow-md">
        Proceed to Checkout
      </a>
      <button onclick="toggleCartSidebar()" class="block w-full text-center text-slate font-medium mt-3 hover:text-navy transition-colors text-sm">
        Continue Shopping
      </button>
    </div>
  </div>

  <!-- Cart Sidebar Overlay -->
  <div id="cartOverlay" class="fixed inset-0 bg-navy/60 backdrop-blur-sm z-[1040] hidden opacity-0 transition-opacity duration-300" onclick="toggleCartSidebar()"></div>

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

    // 3. Cart Drawer Toggle
    function toggleCartSidebar() {
      const sidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('cartOverlay');
      const isClosed = sidebar.classList.contains('translate-x-full');

      if (isClosed) {
        // Open
        overlay.classList.remove('hidden');
        // Small delay to allow display:block to apply before animating opacity
        setTimeout(() => {
          overlay.classList.remove('opacity-0');
          overlay.classList.add('opacity-100');
          sidebar.classList.remove('translate-x-full');
        }, 10);
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
      } else {
        // Close
        sidebar.classList.add('translate-x-full');
        overlay.classList.remove('opacity-100');
        overlay.classList.add('opacity-0');
        setTimeout(() => {
          overlay.classList.add('hidden');
        }, 300);
        document.body.style.overflow = '';
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

</body>
</html>