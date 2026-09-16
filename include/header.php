<?php
// FIXED: Only start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/connection.php');
include_once(__DIR__ . '/functions.php');

// Get notifications count if user is logged in
$notificationCount = 0;
if (isset($_SESSION['userid'])) {
    $notificationCount = getUnreadNotificationsCount($conn, $_SESSION['userid']);
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if(isset($is_404) && $is_404): ?>
  <base href="/OXXA GEAR/site/">
  <?php endif; ?>
  <title><?php echo isset($page_title) ? $page_title : 'OXXA GEAR - Your Ultimate Sports Destination'; ?></title>
  <meta name="description" content="OXXA GEAR is the ultimate destination for premium sports gear in Sri Lanka. Shop authentic equipment, footwear, and nutrition for cricket, football, gym, running & every sport.">

  <!-- Favicon -->
  <?php
  $current_dir = dirname($_SERVER['PHP_SELF']);
  $base_path = (strpos($current_dir, '/site') !== false) ? '../' : '';
  ?>
  <link rel="icon" type="image/png" href="<?php echo $base_path; ?>image/oxxa_gear_logo.png">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- SweetAlert2 for beautiful toasts -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

  <!-- External CSS Files -->
  <?php
  $current_dir = dirname($_SERVER['PHP_SELF']);
  $css_path = '';
  
  // Determine the correct CSS path based on current directory
  if (strpos($current_dir, '/site') !== false) {
    $css_path = '../CSS/';
  } else {
    $css_path = 'CSS/';
  }
  ?>
  <link rel="stylesheet" href="<?php echo $css_path; ?>main.css?v=<?php echo time(); ?>">
  <link rel="icon" type="image/png" href="<?php echo $base_path; ?>image/oxxa_gear_logo.png">
  <style>
    /* Bulletproof right alignment for profile dropdown on hover before Popper JS runs */
    .dropdown-menu.dropdown-menu-end {
      right: 0 !important;
      left: auto !important;
    }
  </style>
  <?php
  // Load page-specific CSS
  $current_page = basename($_SERVER['PHP_SELF'], '.php');
  if ($current_page == 'index' || $current_page == 'site') {
    echo '<link rel="stylesheet" href="' . $css_path . 'home.css">';
  } elseif ($current_page == 'products') {
    echo '<link rel="stylesheet" href="' . $css_path . 'products.css">';
  }
  ?>

  <script>
    // Dark mode configuration for Tailwind
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#0066FF', // OXXA Blue
              hover: '#0052CC',
            },
            navy: {
              DEFAULT: '#0A0A0A', // Jet Black
              light: '#1A1A1A',
            },
            danger: {
              DEFAULT: '#d32f2f',
            },
            lime: {
              DEFAULT: '#B8F34A', // Performance Lime (Accent)
            },
            offwhite: {
              DEFAULT: '#FFFFFF', // Pure White
            },
            slate: {
              DEFAULT: '#667085', // Secondary Text
            }
          },
          fontFamily: {
             sans: ['Inter', 'sans-serif'],
             space: ['"Space Grotesk"', 'sans-serif'],
          }
        }
      }
    }
  </script>
</head>

<body class="bg-offwhite text-navy min-h-screen pb-20 lg:pb-0">

  <!-- Navigation Header -->
  <header class="fixed w-full top-0 z-50 bg-white/85 backdrop-blur-md shadow-sm border-b border-gray-100 transition-all duration-300">
    
    <!-- Top Announcement Bar -->
    <div class="bg-[#0A0A0A] text-white text-xs py-2.5 font-space tracking-widest uppercase font-bold">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-center items-center space-x-4 md:space-x-8">
        <span class="flex items-center"><i class="fas fa-truck-fast text-primary me-2 animate-bounce"></i> Islandwide Delivery</span>
        <span class="hidden sm:flex items-center"><img src="<?php echo $base_path; ?>image/KOKO_logo.png" class="h-3 w-auto me-2" alt="KOKO"> Pay in 3</span>
        <span class="flex items-center"><i class="fas fa-shield-check text-primary me-2"></i> 100% Authentic</span>
      </div>
    </div>

    <!-- Middle Bar -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-[80px] flex items-center justify-between relative">
        <!-- Logo -->
        <div class="flex-shrink-0 z-10">
          <?php
          $current_dir = dirname($_SERVER['PHP_SELF']);
          $base_path = (strpos($current_dir, '/site') !== false) ? '../' : '';
          ?>
          <a href="<?php echo $base_path; ?>index.php" class="flex items-center group">
            <img src="<?php echo $base_path; ?>image/oxxa_gear_logo.png" alt="OXXA GEAR" class="h-10 md:h-12 w-auto object-contain transform group-hover:scale-105 transition-transform duration-300">
          </a>
        </div>

        <!-- Navigation Links (Center) -->
        <nav id="desktopNav" class="hidden lg:flex flex-1 justify-center items-center space-x-4 xl:space-x-8 z-10 transition-all duration-[350ms] ease-in-out opacity-100">
          <a href="<?php echo $base_path; ?>site/shop.php?category=sports-wear" class="relative group">
            <span class="text-[13px] font-space font-bold uppercase tracking-[0.5px] text-black group-hover:text-primary transition-colors whitespace-nowrap">Sports Wear</span>
            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
          </a>
          <a href="<?php echo $base_path; ?>site/shop.php?category=footwear" class="relative group">
            <span class="text-[13px] font-space font-bold uppercase tracking-[0.5px] text-black group-hover:text-primary transition-colors whitespace-nowrap">Footwear</span>
            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
          </a>
          <a href="<?php echo $base_path; ?>site/shop.php?category=fitness-gym" class="relative group">
            <span class="text-[13px] font-space font-bold uppercase tracking-[0.5px] text-black group-hover:text-primary transition-colors whitespace-nowrap">Fitness & Gym</span>
            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
          </a>
          <a href="<?php echo $base_path; ?>site/shop.php?category=nutrition" class="relative group">
            <span class="text-[13px] font-space font-bold uppercase tracking-[0.5px] text-black group-hover:text-primary transition-colors whitespace-nowrap">Nutrition</span>
            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
          </a>
          <a href="<?php echo $base_path; ?>site/shop.php?category=accessories" class="relative group">
            <span class="text-[13px] font-space font-bold uppercase tracking-[0.5px] text-black group-hover:text-primary transition-colors whitespace-nowrap">Accessories</span>
            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
          </a>
          <a href="<?php echo $base_path; ?>site/shop.php?category=equipment" class="relative group">
            <span class="text-[13px] font-space font-bold uppercase tracking-[0.5px] text-black group-hover:text-primary transition-colors whitespace-nowrap">Equipment</span>
            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
          </a>
          
        </nav>
        
        <!-- Absolute Center Search Bar (Hidden by default) -->
        <div id="expandedSearchBar" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-0 opacity-0 pointer-events-none transition-all duration-[350ms] ease-in-out z-20 flex justify-center items-center">
            <form action="<?php echo $base_path; ?>site/shop.php" method="GET" class="w-full relative">
                <input type="text" id="desktopSearchInput" name="search" placeholder="Search for sports gear..." 
                       class="w-full bg-[#F5F5F5] text-black text-sm font-medium focus:outline-none transition-all duration-300"
                       style="height: 44px; border-radius: 9999px; border: 1.5px solid #0066FF; box-shadow: 0 0 0 4px rgba(0,102,255,0.1); padding: 0 40px 0 24px;">
                <button type="button" id="closeSearchBtn" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-black transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </form>
        </div>

        <!-- Right Side Actions -->
        <div class="flex items-center space-x-2 md:space-x-4 flex-shrink-0 z-10 relative">
          
          <!-- Search Toggle Button (Visible on all) -->
          <button id="searchToggleBtn" type="button" class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-black group z-20">
            <i class="fas fa-search text-lg group-hover:text-primary transition-colors"></i>
          </button>
          
          <?php if(isset($_SESSION['userid'])): ?>
            <!-- User Profile Dropdown (Hidden on mobile) -->
            <div class="relative dropdown hidden lg:block">
              <button class="flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors text-black relative group dropdown-toggle" 
                      data-bs-toggle="dropdown" aria-expanded="false" id="profileDropdown" style="padding: 4px 12px 4px 4px;">
                <div class="flex items-center gap-2">
                    <?php if (!empty($_SESSION['profile_image']) && file_exists(__DIR__ . '/../assets/uploads/profiles/' . $_SESSION['profile_image'])): ?>
                        <img src="<?php echo $base_path; ?>assets/uploads/profiles/<?php echo $_SESSION['profile_image']; ?>" class="w-8 h-8 rounded-full ring-2 ring-gray-100 object-cover">
                    <?php else: ?>
                        <div class="bg-black text-white w-8 h-8 rounded-full flex items-center justify-center font-black">
                            <?php echo strtoupper(substr($_SESSION['first_name'] ?? $_SESSION['firstname'] ?? '', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-[12px] font-black hidden sm:inline-block pr-1">HI, <?php echo strtoupper($_SESSION['first_name'] ?? $_SESSION['firstname'] ?? ''); ?></span>
                </div>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-xl border border-gray-100 mt-2 rounded-2xl py-2 min-w-[200px]" aria-labelledby="profileDropdown">
                <li>
                  <a href="<?php echo $base_path; ?>site/profile.php" class="dropdown-item hover:bg-gray-50 hover:text-primary px-4 py-2 font-medium text-sm transition-colors">
                    <i class="far fa-user-circle me-2 w-5 text-gray-400"></i>Profile
                  </a>
                </li>
                <li>
                  <a href="<?php echo $base_path; ?>site/my-orders.php" class="dropdown-item hover:bg-gray-50 hover:text-primary px-4 py-2 font-medium text-sm transition-colors">
                    <i class="fas fa-shopping-bag me-2 w-5 text-gray-400"></i>Orders
                  </a>
                </li>
                
                <?php if(isset($_SESSION['type']) && $_SESSION['type'] == 'seller'): ?>
                  <li><hr class="dropdown-divider border-gray-50 my-1"></li>
                  <li>
                    <a href="<?php echo $base_path; ?>site/business-registration.php" class="dropdown-item hover:bg-gray-50 hover:text-primary px-4 py-2 font-medium text-sm transition-colors">
                      <i class="far fa-building me-2 w-5 text-gray-400"></i>Business
                    </a>
                  </li>
                  <li>
                    <a href="<?php echo $base_path; ?>site/seller-dashboard.php" class="dropdown-item hover:bg-gray-50 hover:text-primary px-4 py-2 font-medium text-sm transition-colors">
                      <i class="fas fa-chart-line me-2 w-5 text-gray-400"></i>Dashboard
                    </a>
                  </li>
                  <li>
                    <a href="<?php echo $base_path; ?>site/seller-add-product.php" class="dropdown-item hover:bg-gray-50 hover:text-primary px-4 py-2 font-medium text-sm transition-colors">
                      <i class="fas fa-plus-circle me-2 w-5 text-gray-400"></i>Add Product
                    </a>
                  </li>
                <?php endif; ?>
                
                <li><hr class="dropdown-divider border-gray-50 my-1"></li>
                <li>
                  <a href="<?php echo $base_path; ?>Backend/logout.php" class="dropdown-item text-danger hover:bg-red-50 px-4 py-2 font-medium text-sm transition-colors">
                    <i class="fas fa-sign-out-alt me-2 w-5"></i>Log Out
                  </a>
                </li>
              </ul>
            </div>

            <!-- Wishlist (Hidden on mobile) -->
            <a href="<?php echo $base_path; ?>site/notifications.php" class="hidden lg:flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-black relative group">
              <i class="far fa-heart text-lg group-hover:text-primary transition-colors"></i>
              <?php if ($notificationCount > 0): ?>
                <span class="absolute top-0 right-0 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold bg-primary border-2 border-white">
                  <?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?>
                </span>
              <?php endif; ?>
            </a>

            <!-- Cart (Hidden on mobile, visible on desktop) -->
            <button onclick="toggleCartSidebar()" class="hidden lg:flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-black relative group">
              <i class="fas fa-shopping-bag text-lg group-hover:text-primary transition-colors"></i>
              <?php 
              $cartCount = 0;
              if (isset($_SESSION['userid'])) {
                  try {
                      $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
                      $stmt->execute([$_SESSION['userid']]);
                      $cartCount = $stmt->fetchColumn() ?: 0;
                  } catch (PDOException $e) {
                      $cartCount = 0;
                  }
              }
              ?>
              <span class="cart-badge absolute top-0 right-0 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold bg-primary border-2 border-white" id="cartBadge">
                <?php echo $cartCount; ?>
              </span>
            </button>

          <?php else: ?>
            <!-- Login (Hidden on mobile) -->
            <button onclick="openAuthModal('login')" class="hidden lg:flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-black relative group">
              <i class="far fa-user text-lg group-hover:text-primary transition-colors"></i>
            </button>
            <button onclick="openAuthModal('login')" class="hidden lg:flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-black relative group">
              <i class="far fa-heart text-lg group-hover:text-primary transition-colors"></i>
            </button>
            <!-- Cart (Hidden on mobile, visible on desktop) -->
            <button onclick="openAuthModal('login')" class="hidden lg:flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors text-black relative group">
              <i class="fas fa-shopping-bag text-lg group-hover:text-primary transition-colors"></i>
            </button>
          <?php endif; ?>
          
          <!-- Hamburger Menu (Mobile) -->
          <button class="lg:hidden w-10 h-10 flex items-center justify-center text-black hover:text-primary transition-colors" onclick="toggleMobileMenu()">
            <i class="fas fa-bars text-xl"></i>
          </button>
        </div>
    </div>

  </header>
  
  <!-- Mobile Menu Drawer Overlay -->
  <div id="mobileMenuOverlay" class="fixed inset-0 bg-black/50 opacity-0 invisible transition-all duration-300 z-[90]" onclick="toggleMobileMenu()"></div>

  <!-- Mobile Menu Drawer -->
  <div id="mobileMenuDrawer" class="fixed top-0 left-[-300px] w-[300px] h-[100dvh] bg-white z-[100] transition-all duration-300 shadow-2xl flex flex-col">
    <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0">
      <img src="<?php echo $base_path; ?>image/oxxa_gear_logo.png" alt="OXXA GEAR" class="h-8 w-auto">
      <button onclick="toggleMobileMenu()" class="text-gray-400 hover:text-black transition-colors w-11 h-11 flex items-center justify-center">
        <i class="fas fa-times text-2xl"></i>
      </button>
    </div>
    <div class="p-6 overflow-y-auto flex-grow flex flex-col gap-2 font-space font-bold uppercase tracking-wider text-sm">
      <div class="text-gray-400 text-xs mb-2 mt-2">Categories</div>
      <a href="<?php echo $base_path; ?>site/products.php?category=Sports+Wear" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="fas fa-tshirt w-6 text-center me-3"></i> Sports Wear</a>
      <a href="<?php echo $base_path; ?>site/products.php?category=Footwear" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="fas fa-shoe-prints w-6 text-center me-3"></i> Footwear</a>
      <a href="<?php echo $base_path; ?>site/products.php?category=Fitness+Gym" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="fas fa-dumbbell w-6 text-center me-3"></i> Fitness & Gym</a>
      <a href="<?php echo $base_path; ?>site/products.php?category=Nutrition" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="fas fa-prescription-bottle-alt w-6 text-center me-3"></i> Nutrition</a>
      
      <?php if(isset($_SESSION['userid']) && $_SESSION['type'] == 'seller'): ?>
        <hr class="border-gray-100 my-4">
        <div class="text-gray-400 text-xs mb-2">Seller Menu</div>
        <a href="<?php echo $base_path; ?>site/seller-dashboard.php" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="fas fa-chart-line w-6 text-center me-3"></i> Dashboard</a>
        <a href="<?php echo $base_path; ?>site/business-registration.php" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="far fa-building w-6 text-center me-3"></i> Business</a>
        <a href="<?php echo $base_path; ?>site/add-product.php" class="flex items-center text-black hover:text-primary transition-colors py-3"><i class="fas fa-plus-circle w-6 text-center me-3"></i> Add Product</a>
      <?php endif; ?>
    </div>
    
    <?php if(!isset($_SESSION['userid'])): ?>
    <div class="p-6 border-t border-gray-100 bg-gray-50 shrink-0">
        <button onclick="openAuthModal('login'); toggleMobileMenu();" class="flex items-center justify-center w-full h-12 bg-black text-white rounded-full hover:bg-primary transition-colors font-bold uppercase tracking-wide text-sm">
            <i class="far fa-user mr-2"></i> Login / Register
        </button>
    </div>
    <?php else: ?>
    <div class="p-6 border-t border-gray-100 bg-gray-50 shrink-0">
        <a href="<?php echo $base_path; ?>site/profile.php" class="flex items-center justify-center w-full h-12 bg-gray-200 text-black rounded-full hover:bg-gray-300 transition-colors font-bold uppercase tracking-wide text-sm mb-3">
            <i class="far fa-user-circle mr-2"></i> My Profile
        </a>
        <a href="<?php echo $base_path; ?>Backend/logout.php" class="flex items-center justify-center w-full h-12 bg-red-50 text-danger border border-red-100 rounded-full hover:bg-red-100 transition-colors font-bold uppercase tracking-wide text-sm">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
    </div>
    <?php endif; ?>
  </div>
  
  <!-- Spacer to prevent content from hiding under fixed header -->
  <div class="h-[120px] md:h-[80px]"></div>

  <!-- Mobile Bottom Navigation (5 Icons: Home, Categories, Wishlist, Profile, Cart) -->
  <div class="lg:hidden fixed bottom-0 left-0 w-full bg-white shadow-[0_-4px_10px_rgba(0,0,0,0.05)] z-[95] border-t border-gray-100 pb-safe">
      <div class="flex justify-around items-center h-16">
          <?php 
          $current_page = basename($_SERVER['PHP_SELF'], '.php');
          $is_shop = ($current_page == 'shop' || $current_page == 'products');
          ?>
          
          <!-- Home -->
          <a href="<?php echo $base_path; ?>index.php" class="flex flex-col items-center justify-center w-full h-full <?php echo ($current_page == 'index') ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
              <i class="fas fa-home text-xl mb-1"></i>
              <?php if($current_page == 'index'): ?><span class="w-1.5 h-1.5 rounded-full bg-primary mt-0.5"></span><?php endif; ?>
          </a>
          
          <!-- Categories / Shop -->
          <a href="<?php echo $base_path; ?>site/shop.php" class="flex flex-col items-center justify-center w-full h-full <?php echo $is_shop ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
              <i class="fas fa-border-all text-xl mb-1"></i>
              <?php if($is_shop): ?><span class="w-1.5 h-1.5 rounded-full bg-primary mt-0.5"></span><?php endif; ?>
          </a>
          
          <!-- Wishlist -->
          <a href="<?php echo isset($_SESSION['userid']) ? $base_path . 'site/notifications.php' : 'javascript:openAuthModal(\'login\')'; ?>" class="flex flex-col items-center justify-center w-full h-full <?php echo ($current_page == 'notifications') ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?> relative">
              <i class="far fa-heart text-xl mb-1"></i>
              <?php if ($notificationCount > 0): ?>
                <span class="absolute top-1 right-2 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold bg-primary border-2 border-white">
                  <?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?>
                </span>
              <?php endif; ?>
              <?php if($current_page == 'notifications'): ?><span class="w-1.5 h-1.5 rounded-full bg-primary mt-0.5"></span><?php endif; ?>
          </a>
          
          <!-- Profile -->
          <a href="<?php echo isset($_SESSION['userid']) ? $base_path . 'site/profile.php' : 'javascript:openAuthModal(\'login\')'; ?>" class="flex flex-col items-center justify-center w-full h-full <?php echo ($current_page == 'profile') ? 'text-primary' : 'text-gray-400 hover:text-gray-600'; ?>">
              <i class="far fa-user text-xl mb-1"></i>
              <?php if($current_page == 'profile'): ?><span class="w-1.5 h-1.5 rounded-full bg-primary mt-0.5"></span><?php endif; ?>
          </a>
          
          <!-- Cart -->
          <button onclick="<?php echo isset($_SESSION['userid']) ? 'toggleCartSidebar()' : 'openAuthModal(\'login\')'; ?>" class="flex flex-col items-center justify-center w-full h-full text-gray-400 hover:text-gray-600 relative">
              <i class="fas fa-shopping-bag text-xl mb-1"></i>
              <span class="cart-badge absolute top-1 right-2 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold bg-primary border-2 border-white" id="mobileCartBadge">
                <?php echo $cartCount ?? 0; ?>
              </span>
          </button>
      </div>
  </div>

  <!-- Cart Overlay -->
  <div class="cart-overlay" id="cartOverlay" onclick="toggleCartSidebar()"></div>

  <!-- Cart Sidebar -->
  <div class="cart-sidebar" id="cartSidebar">
    <div class="p-4 border-b border-gray-700">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold text-white">Shopping Cart</h3>
        <button onclick="toggleCartSidebar()" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
    </div>
    <div id="cartContent" class="p-4">
      <!-- Cart content will be loaded here -->
      <div class="text-center py-8">
        <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
        <p class="text-gray-400 mt-2">Loading cart...</p>
      </div>
    </div>
  </div>

  <!-- Content Spacer for Fixed Header -->
  <div class="h-16"></div>
  <!-- Main Content Container -->
  <main class="flex-grow-1">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        
        // Premium Search Interaction
        const searchToggleBtn = document.getElementById('searchToggleBtn');
        const closeSearchBtn = document.getElementById('closeSearchBtn');
        const expandedSearchBar = document.getElementById('expandedSearchBar');
        const desktopNav = document.getElementById('desktopNav');
        const desktopSearchInput = document.getElementById('desktopSearchInput');
        let isSearchOpen = false;

        function closeSearch() {
            isSearchOpen = false;
            // Show nav
            if (desktopNav) {
                desktopNav.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
                desktopNav.classList.add('opacity-100', 'translate-y-0');
            }
            // Hide search
            expandedSearchBar.classList.add('w-0', 'opacity-0', 'pointer-events-none');
            expandedSearchBar.classList.remove('w-full', 'bg-white', 'md:bg-transparent', 'px-4', 'md:w-[600px]', 'md:max-w-[45vw]', 'opacity-100');
            
            // Show the original search icon
            searchToggleBtn.classList.remove('hidden');
        }

        if (searchToggleBtn) {
            searchToggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                isSearchOpen = true;
                
                // Hide nav
                if (desktopNav) {
                    desktopNav.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
                    desktopNav.classList.remove('opacity-100', 'translate-y-0');
                }
                // Show search
                expandedSearchBar.classList.remove('w-0', 'opacity-0', 'pointer-events-none');
                expandedSearchBar.classList.add('w-full', 'bg-white', 'md:bg-transparent', 'px-4', 'md:w-[600px]', 'md:max-w-[45vw]', 'opacity-100');
                
                // Hide the original search icon to prevent clicking again
                searchToggleBtn.classList.add('hidden');
                
                // Focus input
                setTimeout(() => desktopSearchInput.focus(), 300);
            });
        }
        
        if (closeSearchBtn) {
            closeSearchBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                closeSearch();
            });
        }

        // Close search when clicking outside
        document.addEventListener('click', function(e) {
            if (isSearchOpen && !expandedSearchBar.contains(e.target) && !searchToggleBtn.contains(e.target)) {
                closeSearch();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isSearchOpen) {
                closeSearch();
            }
        });

        // Load cart content on page load
        loadCartContent();
      });

      // Mobile Menu Drawer Functions
      function toggleMobileMenu() {
        const drawer = document.getElementById('mobileMenuDrawer');
        const overlay = document.getElementById('mobileMenuOverlay');
        
        if (drawer.style.left === '0px') {
            drawer.style.left = '-300px';
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
            setTimeout(() => { overlay.classList.add('invisible'); }, 300);
        } else {
            overlay.classList.remove('invisible');
            setTimeout(() => { overlay.classList.remove('opacity-0'); overlay.classList.add('opacity-100'); }, 10);
            drawer.style.left = '0px';
        }
      }

      // Cart sidebar functions
      function toggleCartSidebar() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('cartOverlay');
        
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
        
        if (sidebar.classList.contains('open')) {
          loadCartContent();
        }
      }

      function loadCartContent() {
        <?php if(isset($_SESSION['userid'])): ?>
        fetch('<?php echo $base_path; ?>site/get_cart_content.php')
          .then(response => response.text())
          .then(data => {
            document.getElementById('cartContent').innerHTML = data;
          })
          .catch(error => {
            console.error('Error loading cart:', error);
            document.getElementById('cartContent').innerHTML = 
              '<div class="text-center py-8"><p class="text-red-400">Error loading cart</p></div>';
          });
        <?php else: ?>
        document.getElementById('cartContent').innerHTML = 
          '<div class="text-center py-8"><p class="text-gray-400">Please login to view cart</p></div>';
        <?php endif; ?>
      }

      function updateCartBadge(count) {
        document.getElementById('cartBadge').textContent = count;
      }
    </script>

    <style>
    /* Cart sidebar styles */
    .cart-sidebar {
      position: fixed;
      top: 0;
      right: -400px;
      width: 400px;
      height: 100vh;
      background: #1f2937;
      border-left: 1px solid #374151;
      transition: right 0.3s ease-in-out;
      z-index: 1000;
      overflow-y: auto;
    }
    
    .cart-sidebar.open {
      right: 0;
    }
    
    .cart-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.5);
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease-in-out;
      z-index: 999;
    }
    
    .cart-overlay.open {
      opacity: 1;
      visibility: visible;
    }
    
    @media (max-width: 400px) {
      .cart-sidebar {
        width: 100vw;
        right: -100vw;
      }
    }
    </style>