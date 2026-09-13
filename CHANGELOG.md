# Changelog

All notable changes to the Lumnix Sports (formerly Nutrition.lk) platform will be documented in this file.

## [2026-09-12] - Performance Premium UI/UX Upgrade

### Added
- **Global UI Libraries**: Added SweetAlert2 and Animate.css to `include/header.php` for better global interactions.
- **Cart Drawer**: Implemented a slide-in Cart Drawer and overlay in `include/footer.php` to prevent navigation interruptions.
- **Visual Order Tracking**: Built a brand new `site/my-orders.php` featuring a 4-step progress UI (Pending, Processing, Shipped, Delivered) using Tailwind CSS.
- **Video Hero Section**: Upgraded the main landing page (`site/index.php`) hero section with an immersive, autoplaying Cloudinary video background for desktop users.
- **Infinite Brand Marquee**: Replaced the static shop-by-brand grid with a seamless, infinite horizontal scrolling carousel using local, high-quality brand logos.

### Changed
- **Brand Name Update**: Changed the platform name globally from "Lumnix Sports" and "Nutrition.lk" to "OXXA GEAR".
- **Brand Logo Update**: Updated the platform logo to use the new OXXA GEAR image provided.
- **Theme Foundation**: Updated `CSS/main.css` to use the new "Performance Premium" color palette (Navy + Electric Blue + Lime).
- **Homepage Structure**: Restructured `site/index.php` into modern conversion-oriented sections (Hero, Categories, Trending) without breaking backend integrations.
- **Header Navigation**: Redesigned `include/header.php` to feature direct top-level category links in the navbar, replacing the old dropdown system.
- **Premium Search Bar**: Upgraded the search bar in the header to a "Click-to-Expand" pill-shaped input perfectly centered on the screen, which smoothly hides the navigation links when active.
- **Authentication Pages**: Completely redesigned `login.php` and `register.php` (Sign Up) into a modern, split-screen premium layout, purging all legacy colors to match OXXA GEAR branding.
- **Checkout Page**: Completely redesigned `site/checkout.php` with a responsive 2-column layout using Tailwind CSS while maintaining the existing AJAX logic (`apply-coupon.php`, `process-order.php`).
- **User Profile**: Transformed `site/profile.php` from standard Bootstrap to a custom Tailwind UI featuring hover states and clear visual hierarchies.
- **Seller Dashboard**: Overhauled `site/seller-dashboard.php` UI, changing the old Bootstrap dashboard into a premium admin panel with gradient analytics cards and elegant data tables.

### Fixed
- **Checkout JS Error**: Fixed an incomplete javascript `catch` block on `site/checkout.php` during the UI transition.
- **Brand Logos CSS**: Fixed black box issues on JPG logos by applying `mix-blend-mode: multiply`, prevented overflow, and added custom scaling classes to balance small logos in the carousel.
