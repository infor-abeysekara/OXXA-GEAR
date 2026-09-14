<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['first_name'] ?? $_SESSION['firstname'] ?? 'Admin';
$admin_image = $_SESSION['profile_image'] ?? '';
?>
<style>
    .admin-sidebar {
        background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
        border-left: 4px solid #0066FF;
        min-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        width: 250px;
        z-index: 1000;
        box-shadow: 2px 0 15px rgba(0,0,0,0.5);
    }
    .admin-sidebar .nav-link {
        color: rgba(255, 255, 255, 0.6);
        border-radius: 10px;
        margin: 5px 15px;
        padding: 10px 15px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active {
        background: rgba(0, 102, 255, 0.1);
        color: white;
        border-left: 3px solid #0066FF;
    }
    .admin-sidebar .nav-link i {
        width: 25px;
    }
    @media (max-width: 768px) {
        .admin-sidebar {
            width: 100%;
            height: auto;
            position: relative;
            min-height: auto;
            border-left: none;
            border-top: 4px solid #0066FF;
        }
    }
    .sidebar-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        margin: 10px 15px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        cursor: pointer;
    }
    .sidebar-profile:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    .sidebar-profile img {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #0066FF;
    }
    .sidebar-profile .name {
        font-weight: 600;
        color: #fff;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-dropdown-menu {
        background: #1A1A1A !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 5px 20px rgba(0,0,0,0.5) !important;
        border-radius: 12px !important;
        padding: 10px !important;
        margin-top: 5px !important;
    }
    .sidebar-dropdown-menu .dropdown-item {
        color: rgba(255, 255, 255, 0.7);
        border-radius: 8px;
        padding: 8px 15px;
        font-size: 13px;
        transition: all 0.2s;
    }
    .sidebar-dropdown-menu .dropdown-item:hover {
        background: rgba(0, 102, 255, 0.2);
        color: #fff;
    }
    .sidebar-dropdown-menu .dropdown-item.text-danger:hover {
        background: rgba(220, 53, 69, 0.1);
        color: #ff6b6b !important;
    }
    .sidebar-dropdown-menu .dropdown-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
</style>

<nav class="admin-sidebar">
    <div class="p-4 text-center">
        <a href="dashboard.php" class="text-decoration-none">
            <img src="../image/oxxa_gear_logo.png" alt="OXXA GEAR Logo" class="img-fluid mb-2" style="width: 140px; filter: brightness(0) invert(1);">
            <h5 class="fw-bold text-white mb-0 mt-2">OXXA GEAR</h5>
            <small style="color: #0066FF; font-weight: 600; letter-spacing: 1px;">CONTROL CENTER</small>
        </a>
    </div>

    <!-- Admin Profile Dropdown -->
    <div class="dropdown">
        <div class="sidebar-profile" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if(!empty($admin_image)): ?>
                <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($admin_image); ?>" alt="Profile">
            <?php else: ?>
                <div style="width: 35px; height: 35px; border-radius: 50%; background: #0066FF; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; flex-shrink: 0;">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="name flex-grow-1">Hi, <?php echo htmlspecialchars($admin_name); ?></div>
            <i class="fas fa-chevron-down text-white-50" style="font-size: 10px;"></i>
        </div>
        <ul class="dropdown-menu sidebar-dropdown-menu w-75 mx-3">
            <li>
                <a class="dropdown-item" href="profile.php">
                    <i class="far fa-user-circle me-2"></i> My Profile
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-danger" href="include/admin-logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <ul class="nav flex-column mt-3">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'manage-users.php') ? 'active' : ''; ?>" href="manage-users.php">
                <i class="fas fa-users"></i> Manage Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'manage-products.php') ? 'active' : ''; ?>" href="manage-products.php">
                <i class="fas fa-box"></i> Manage Products
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'finance.php') ? 'active' : ''; ?>" href="finance.php">
                <i class="fas fa-chart-line"></i> Finance Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'business-registrations.php') ? 'active' : ''; ?>" href="business-registrations.php">
                <i class="fas fa-building"></i> Registrations
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'manage-coupons.php') ? 'active' : ''; ?>" href="manage-coupons.php">
                <i class="fas fa-tags"></i> Manage Coupons
            </a>
        </li>
        <li class="nav-item mt-2">
            <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-warning" href="../index.php" target="_blank">
                <i class="fas fa-globe"></i> View Website
            </a>
        </li>
    </ul>
</nav>
