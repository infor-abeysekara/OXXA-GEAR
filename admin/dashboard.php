<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Get statistics
$total_users_query = "SELECT COUNT(*) as total FROM users WHERE user_type != 'admin'";
$total_users_result = mysqli_query($conn, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$total_products_query = "SELECT COUNT(*) as total FROM products";
$total_products_result = mysqli_query($conn, $total_products_query);
$total_products = mysqli_fetch_assoc($total_products_result)['total'];

$total_orders_query = "SELECT COUNT(*) as total FROM orders";
$total_orders_result = mysqli_query($conn, $total_orders_query);
$total_orders = mysqli_fetch_assoc($total_orders_result)['total'];

// Get pending business verifications
$pending_business_query = "SELECT COUNT(*) as total FROM seller_profiles WHERE is_approved = 0";
$pending_business_result = mysqli_query($conn, $pending_business_query);
$pending_business = mysqli_fetch_assoc($pending_business_result)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OXXA GEAR</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .stat-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #EFF6FF;
            color: #0066FF;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
        }
        .stat-label {
            font-size: 14px;
            color: #6B7280;
            font-weight: 500;
        }
        .section-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .section-header {
            background: #fff;
            border-bottom: 1px solid #E5E7EB;
            border-left: 4px solid #0066FF;
            padding: 15px 20px;
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>
    <!-- Main Content -->
    <div class="main-content">
        <?php include("components/topbar.php"); ?>
        
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold text-dark">Dashboard Overview</h2>
                    <p class="text-muted">Welcome back, <?php echo $_SESSION['first_name'] ?? $_SESSION['firstname'] ?? 'Admin'; ?>! Here's what's happening with your platform.</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-5">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Total Users</span>
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-users fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $total_users; ?></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Total Products</span>
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-box fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $total_products; ?></div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Total Orders</span>
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-shopping-bag fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $total_orders; ?></div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Pending Approvals</span>
                            <div class="stat-icon-wrapper position-relative">
                                <i class="fas fa-building fs-5"></i>
                                <?php if($pending_business > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                                        <span class="visually-hidden">New alerts</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $pending_business; ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-clock me-2 text-primary"></i>Pending Business Registrations</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $pending_query = "SELECT u.*, sp.business_name, sp.business_type FROM users u 
                                            JOIN seller_profiles sp ON u.id = sp.user_id 
                                            WHERE sp.is_approved = 0 
                                            ORDER BY sp.id DESC LIMIT 5";
                            $pending_result = mysqli_query($conn, $pending_query);
                            
                            if($pending_result && mysqli_num_rows($pending_result) > 0):
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Name</th>
                                                <th>Business</th>
                                                <th class="text-end pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = mysqli_fetch_assoc($pending_result)): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <?php if(!empty($row['profile_image'])): ?>
                                                            <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($row['profile_image']); ?>" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover;">
                                                        <?php else: ?>
                                                            <div class="rounded-circle me-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 14px;">
                                                                <?php echo strtoupper(substr($row['first_name'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <span class="fw-bold d-block" style="color: #111827;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                                            <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="text-muted fw-medium"><?php echo htmlspecialchars($row['business_name']); ?></span></td>
                                                <td class="text-end pe-4">
                                                    <a href="business-registrations.php" class="btn btn-sm text-white px-3" style="background-color: #0066FF; border-radius: 6px;">Review</a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted mb-0">No pending business registrations.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-box me-2 text-primary"></i>Recent Products</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $recent_products_query = "SELECT p.*, u.first_name, u.last_name FROM products p 
                                                    JOIN users u ON p.seller_id = u.id 
                                                    ORDER BY p.created_at DESC LIMIT 5";
                            $recent_products_result = mysqli_query($conn, $recent_products_query);
                            
                            if($recent_products_result && mysqli_num_rows($recent_products_result) > 0):
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Product</th>
                                                <th>Seller</th>
                                                <th class="text-end pe-4">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = mysqli_fetch_assoc($recent_products_result)): ?>
                                            <tr>
                                                <td class="ps-4 fw-medium" style="color: #111827;"><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td class="text-end pe-4">
                                                    <?php if($row['is_approved'] == 1): ?>
                                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted mb-0">No recent products.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="section-card mb-4">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-bolt me-2 text-primary"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <a href="manage-users.php" class="btn btn-white w-100 py-3 border text-start d-flex align-items-center" style="border-radius: 12px; transition: all 0.2s;">
                                        <div class="stat-icon-wrapper me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <span class="fw-bold" style="color: #111827;">Manage Users</span>
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="manage-products.php" class="btn btn-white w-100 py-3 border text-start d-flex align-items-center" style="border-radius: 12px; transition: all 0.2s;">
                                        <div class="stat-icon-wrapper me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-box"></i>
                                        </div>
                                        <span class="fw-bold" style="color: #111827;">Manage Products</span>
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="business-registrations.php" class="btn btn-white w-100 py-3 border text-start d-flex align-items-center" style="border-radius: 12px; transition: all 0.2s;">
                                        <div class="stat-icon-wrapper me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <span class="fw-bold" style="color: #111827;">Review Registrations</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>