# Changelog

All notable changes to the Lumnix Sports (formerly Nutrition.lk) platform will be documented in this file.

## [2026-09-13] - Business Registration & Address Book Overhaul

### Added
- **Address Book System**: Developed a complete Address Book feature (`site/address-book.php`) enabling users to manage multiple delivery and billing addresses. Implemented `Backend/address-backend.php` to handle CRUD operations and default address auto-assignment.
- **Auto-Populating Fields**: Designed the "Add New Address" modal to automatically fetch and insert the user's Full Name and Phone Number from their profile, improving UX while remaining editable.

### Changed
- **Unified Authentication**: Removed the isolated admin login portal and consolidated all user sign-ins into `site/login.php`. Admin accounts are now automatically routed to the admin dashboard based on their user type.
- **Session Key Standardization**: Standardized legacy `firstname`/`lastname` session keys to `first_name`/`last_name` globally across the admin panel to resolve undefined array key warnings.
- **Business Registration Schema Expansion**: Safely altered the `seller_profiles` database table, expanding it from 7 columns to 30+ columns to securely store comprehensive seller data.
- **Business Registration UI Revamp**: Completely rebuilt `site/business-registration.php` into a detailed 7-section form (Business Info, Owner Info, Address, Documents, Bank Details, Selling Info, Declaration). Added Javascript logic to dynamically display filenames upon selection for file inputs.
- **Business Registration Backend Rewrite**: Built `Backend/process-business-registration.php` to handle complex payload insertions, unique Business Registration Number verification, and secure uploads of up to 5 documents with format and size constraints.

### Fixed
- **Admin User Filter Crash**: Fixed a fatal SQL syntax error in `admin/manage-users.php` where appending a `user_type` filter on an empty search caused the query to fail.
- **Profile Image Display**: Fixed the profile image source pathing in `site/profile.php` and the account sidebars to correctly fetch images from `assets/uploads/profiles/`.
- **Admin Document 404s**: Fixed broken links in `admin/business-registrations.php` where clicking "View Certificate" or viewing the Business Logo pointed to incorrect legacy folders, resulting in 404 errors.
- **Admin Modal Warning**: Removed the redundant "PENDING approval" warning banner from the admin panel's business registration view modal to declutter the UI.


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
