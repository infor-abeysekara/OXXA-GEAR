<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle product actions
if(isset($_POST['action']) && isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    $action = $_POST['action'];
    
    if($action == 'approve') {
        $update_query = "UPDATE production SET approve = 1 WHERE pid = ?";
    } elseif($action == 'suspend') {
        $update_query = "UPDATE production SET approve = 0 WHERE pid = ?";
    } elseif($action == 'delete') {
        $delete_sizes_query = "DELETE FROM productsize WHERE pid = ?";
        $delete_product_query = "DELETE FROM production WHERE pid = ?";
        
        // Delete product sizes first
        $stmt1 = $conn->prepare($delete_sizes_query);
        $stmt1->bind_param("s", $product_id);
        $stmt1->execute();
        
        // Then delete product
        $stmt2 = $conn->prepare($delete_product_query);
        $stmt2->bind_param("s", $product_id);
        
        if($stmt2->execute()) {
            $success_message = "Product deleted successfully!";
        } else {
            $error_message = "Failed to delete product.";
        }
    }
    
    if(isset($update_query)) {
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $product_id);
        
        if($stmt->execute()) {
            $success_message = ucfirst($action) . " action completed successfully!";
        } else {
            $error_message = "Failed to " . $action . " product.";
        }
    }
}

// Get all products
$products_query = "SELECT p.*, u.firstname, u.lastname FROM production p 
                  JOIN users u ON p.user_id = u.user_id 
                  ORDER BY p.Add_date DESC";
$products_result = mysqli_query($conn, $products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Panel</title>
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
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-users.php">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="manage-products.php">
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
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold text-dark">Manage Products</h2>
                    <p class="text-muted">Review and manage all products on the platform</p>
                </div>
            </div>

            <?php if(isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i>All Products</h5>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($products_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Product Details</th>
                                        <th>Seller</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($products_result)): ?>
                                    <tr>
                                        <td>
                                            <?php if(!empty($row['image'])): ?>
                                                <img src="../image/<?php echo $row['image']; ?>" alt="Product" class="product-image">
                                            <?php else: ?>
                                                <div class="bg-secondary d-flex align-items-center justify-content-center product-image">
                                                    <i class="fas fa-box text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $row['pname']; ?></strong><br>
                                                <small class="text-muted">Brand: <?php echo $row['brand']; ?></small><br>
                                                <small class="text-muted">ID: <?php echo $row['pid']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo $row['firstname'] . ' ' . $row['lastname']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $row['categories']; ?></span>
                                        </td>
                                        <td>
                                            <strong>Rs. <?php echo number_format($row['price'], 2); ?></strong><br>
                                            <small class="text-muted">Qty: <?php echo $row['qty']; ?></small>
                                        </td>
                                        <td>
                                            <?php if($row['approve'] == 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['pid']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button><br>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $row['pid']; ?>">
                                                <?php if($row['approve'] == 1): ?>
                                                    <button type="submit" name="action" value="suspend" class="btn btn-sm btn-warning mb-1" onclick="return confirm('Suspend this product?')">
                                                        <i class="fas fa-pause"></i> Suspend
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success mb-1" onclick="return confirm('Approve this product?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                <?php endif; ?>
                                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product? This action cannot be undone!')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['pid']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Product Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <?php if(!empty($row['image'])): ?>
                                                                <img src="../image/<?php echo $row['image']; ?>" alt="Product" class="img-fluid rounded">
                                                            <?php else: ?>
                                                                <div class="bg-secondary d-flex align-items-center justify-content-center rounded" style="height: 200px;">
                                                                    <i class="fas fa-box fa-3x text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <h6>Product Information</h6>
                                                            <p><strong>Name:</strong> <?php echo $row['pname']; ?></p>
                                                            <p><strong>Brand:</strong> <?php echo $row['brand']; ?></p>
                                                            <p><strong>Category:</strong> <?php echo $row['categories']; ?></p>
                                                            <p><strong>Price:</strong> Rs. <?php echo number_format($row['price'], 2); ?></p>
                                                            <p><strong>Quantity:</strong> <?php echo $row['qty']; ?></p>
                                                            <p><strong>Added Date:</strong> <?php echo date('M d, Y', strtotime($row['Add_date'])); ?></p>
                                                            
                                                            <h6>Seller Information</h6>
                                                            <p><strong>Seller:</strong> <?php echo $row['firstname'] . ' ' . $row['lastname']; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Description</h6>
                                                            <p><?php echo nl2br(htmlspecialchars($row['discription'])); ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Product Sizes -->
                                                    <?php
                                                    $sizes_query = "SELECT * FROM productsize WHERE pid = '{$row['pid']}'";
                                                    $sizes_result = mysqli_query($conn, $sizes_query);
                                                    if(mysqli_num_rows($sizes_result) > 0):
                                                    ?>
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Available Sizes</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Size</th>
                                                                            <th>Price</th>
                                                                            <th>Quantity</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php while($size = mysqli_fetch_assoc($sizes_result)): ?>
                                                                        <tr>
                                                                            <td><?php echo $size['size']; ?></td>
                                                                            <td>Rs. <?php echo number_format($size['price'], 2); ?></td>
                                                                            <td><?php echo $size['qty']; ?></td>
                                                                        </tr>
                                                                        <?php endwhile; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No products found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>