<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Get statistics
$total_users_query = "SELECT COUNT(*) as total FROM users WHERE type != 'admin'";
$total_users_result = mysqli_query($conn, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$total_buyers_query = "SELECT COUNT(*) as total FROM users WHERE type = 'buyer'";
$total_buyers_result = mysqli_query($conn, $total_buyers_query);
$total_buyers = mysqli_fetch_assoc($total_buyers_result)['total'];

$total_sellers_query = "SELECT COUNT(*) as total FROM users WHERE type = 'seller'";
$total_sellers_result = mysqli_query($conn, $total_sellers_query);
$total_sellers = mysqli_fetch_assoc($total_sellers_result)['total'];

$total_products_query = "SELECT COUNT(*) as total FROM production";
$total_products_result = mysqli_query($conn, $total_products_query);
$total_products = mysqli_fetch_assoc($total_products_result)['total'];

$active_products_query = "SELECT COUNT(*) as total FROM production WHERE approve = 1";
$active_products_result = mysqli_query($conn, $active_products_query);
$active_products = mysqli_fetch_assoc($active_products_result)['total'];

// Get pending business registrations
$pending_business_query = "SELECT COUNT(*) as total FROM businessregistration WHERE approve = 0";
$pending_business_result = mysqli_query($conn, $pending_business_query);
$pending_business = mysqli_fetch_assoc($pending_business_result)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OXXA GEAR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #17a2b8, #6f42c1);
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-4">
            <div class="text-center text-white mb-4">
                <i class="fas fa-shield-alt fa-2x mb-2"></i>
                <h5>Admin Panel</h5>
                <small><?php echo $_SESSION['firstname'] . ' ' . $_SESSION['lastname']; ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-users.php">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-products.php">
                        <i class="fas fa-box me-2"></i>Manage Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="business-registrations.php">
                        <i class="fas fa-building me-2"></i>Business Registrations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-coupons.php">
                        <i class="fas fa-tags me-2"></i>Manage Coupons
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-warning" href="../index.php" target="_blank">
                        <i class="fas fa-globe me-2"></i>View Website
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="include/admin-logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold text-dark">Dashboard Overview</h2>
                    <p class="text-muted">Welcome back, <?php echo $_SESSION['firstname']; ?>! Here's what's happening with your platform.</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-5">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card stat-card border-0">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-users fa-2x mb-3"></i>
                            <h4 class="fw-bold"><?php echo $total_users; ?></h4>
                            <p class="mb-0 small">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card stat-card success border-0">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-shopping-bag fa-2x mb-3"></i>
                            <h4 class="fw-bold"><?php echo $total_buyers; ?></h4>
                            <p class="mb-0 small">Total Buyers</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card stat-card info border-0">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-store fa-2x mb-3"></i>
                            <h4 class="fw-bold"><?php echo $total_sellers; ?></h4>
                            <p class="mb-0 small">Total Sellers</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card stat-card warning border-0">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-box fa-2x mb-3"></i>
                            <h4 class="fw-bold"><?php echo $total_products; ?></h4>
                            <p class="mb-0 small">Total Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card stat-card success border-0">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-check-circle fa-2x mb-3"></i>
                            <h4 class="fw-bold"><?php echo $active_products; ?></h4>
                            <p class="mb-0 small">Active Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card stat-card danger border-0">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-building fa-2x mb-3"></i>
                            <h4 class="fw-bold"><?php echo $pending_business; ?></h4>
                            <p class="mb-0 small">Pending Business</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Business Registrations</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $pending_query = "SELECT u.*, br.bname, br.btype FROM users u 
                                            JOIN businessregistration br ON u.user_id = br.user_id 
                                            WHERE br.approve = 0 
                                            ORDER BY br.id DESC LIMIT 5";
                            $pending_result = mysqli_query($conn, $pending_query);
                            
                            if(mysqli_num_rows($pending_result) > 0):
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Business</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = mysqli_fetch_assoc($pending_result)): ?>
                                            <tr>
                                                <td><?php echo $row['firstname'] . ' ' . $row['lastname']; ?></td>
                                                <td><?php echo $row['bname']; ?></td>
                                                <td>
                                                    <a href="business-registrations.php" class="btn btn-sm btn-primary">Review</a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No pending business registrations.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Recent Products</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recent_products_query = "SELECT p.*, u.firstname, u.lastname FROM production p 
                                                    JOIN users u ON p.user_id = u.user_id 
                                                    ORDER BY p.Add_date DESC LIMIT 5";
                            $recent_products_result = mysqli_query($conn, $recent_products_query);
                            
                            if(mysqli_num_rows($recent_products_result) > 0):
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Seller</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = mysqli_fetch_assoc($recent_products_result)): ?>
                                            <tr>
                                                <td><?php echo $row['pname']; ?></td>
                                                <td><?php echo $row['firstname'] . ' ' . $row['lastname']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['approve'] == 1 ? 'success' : 'warning'; ?>">
                                                        <?php echo $row['approve'] == 1 ? 'Active' : 'Pending'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No recent products.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <a href="manage-users.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-users me-2"></i>Manage Users
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="manage-products.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-box me-2"></i>Manage Products
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="business-registrations.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-building me-2"></i>Business Registrations
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