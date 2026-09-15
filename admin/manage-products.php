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
        $update_query = "UPDATE products SET is_approved = 1 WHERE id = ?";
    } elseif($action == 'suspend') {
        $update_query = "UPDATE products SET is_approved = 0 WHERE id = ?";
    } elseif($action == 'delete') {
        $delete_variants_query = "DELETE FROM product_variants WHERE product_id = ?";
        $delete_images_query = "DELETE FROM product_images WHERE product_id = ?";
        $delete_product_query = "DELETE FROM products WHERE id = ?";
        
        // Delete product variants first
        $stmt1 = $conn->prepare($delete_variants_query);
        $stmt1->bind_param("i", $product_id);
        $stmt1->execute();
        
        // Delete product images
        $stmt1b = $conn->prepare($delete_images_query);
        $stmt1b->bind_param("i", $product_id);
        $stmt1b->execute();
        
        // Then delete product
        $stmt2 = $conn->prepare($delete_product_query);
        $stmt2->bind_param("i", $product_id);
        
        if($stmt2->execute()) {
            $success_message = "Product deleted successfully!";
        } else {
            $error_message = "Failed to delete product.";
        }
    }
    
    if(isset($update_query)) {
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $product_id);
        
        if($stmt->execute()) {
            $success_message = ucfirst($action) . " action completed successfully!";
        } else {
            $error_message = "Failed to " . $action . " product.";
        }
    }
}

// Get all products
$products_query = "SELECT p.*, u.first_name, u.last_name, u.email, sp.business_name, sp.personal_phone as phone_number, b.name as brand,
                  (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as product_image
                  FROM products p 
                  JOIN users u ON p.seller_id = u.id 
                  LEFT JOIN seller_profiles sp ON u.id = sp.user_id
                  LEFT JOIN brands b ON p.brand_id = b.id
                  ORDER BY p.created_at DESC";
$products_result = mysqli_query($conn, $products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include("components/sidebar.php"); ?>
    <!-- Main Content -->
    <div class="main-content">
        <?php include("components/topbar.php"); ?>
        
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
                                            <?php if(!empty($row['product_image'])): ?>
                                                <img src="../assets/uploads/products/<?php echo $row['product_image']; ?>" alt="Product" class="product-image">
                                            <?php else: ?>
                                                <div class="bg-secondary d-flex align-items-center justify-content-center product-image">
                                                    <i class="fas fa-box text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                                <small class="text-muted">Brand: <?php echo htmlspecialchars($row['brand'] ?? 'Unknown'); ?></small><br>
                                                <small class="text-muted">ID: <?php echo $row['id']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $row['category_id']; ?></span>
                                        </td>
                                        <td>
                                            <strong>Rs. <?php echo number_format($row['base_price'], 2); ?></strong><br>
                                            <small class="text-muted">Qty: <?php echo $row['total_qty']; ?></small>
                                        </td>
                                        <td>
                                            <?php if($row['is_approved'] == 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button><br>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                                <?php if($row['is_approved'] == 1): ?>
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
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Product Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-12 mb-4">
                                                            <?php
                                                            $images_query = "SELECT image_path FROM product_images WHERE product_id = '{$row['id']}' ORDER BY is_primary DESC, sort_order ASC";
                                                            $images_result = mysqli_query($conn, $images_query);
                                                            if($images_result && mysqli_num_rows($images_result) > 0):
                                                            ?>
                                                                <div class="d-flex overflow-auto gap-3 pb-2" style="white-space: nowrap;">
                                                                    <?php while($img = mysqli_fetch_assoc($images_result)): ?>
                                                                        <img src="../assets/uploads/products/<?php echo htmlspecialchars($img['image_path']); ?>" alt="Product" class="rounded border shadow-sm" style="height: 150px; width: 150px; object-fit: cover; flex-shrink: 0;">
                                                                    <?php endwhile; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="bg-secondary d-flex align-items-center justify-content-center rounded" style="height: 150px; width: 150px;">
                                                                    <i class="fas fa-box fa-3x text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Product Information</h6>
                                                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($row['name']); ?></p>
                                                            <p class="mb-1"><strong>Brand:</strong> <?php echo htmlspecialchars($row['brand'] ?? 'Unknown'); ?></p>
                                                            <p class="mb-1"><strong>Category:</strong> <?php echo $row['category_id']; ?></p>
                                                            <p class="mb-1"><strong>Price:</strong> Rs. <?php echo number_format($row['base_price'], 2); ?></p>
                                                            <p class="mb-1"><strong>Quantity:</strong> <?php echo $row['total_qty']; ?></p>
                                                            <p class="mb-1"><strong>Added Date:</strong> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Seller Information</h6>
                                                            <p class="mb-1"><strong>Seller Name:</strong> <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
                                                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>Business Name:</strong> <?php echo htmlspecialchars($row['business_name'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>Phone Number:</strong> <?php echo htmlspecialchars($row['phone_number'] ?? 'N/A'); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Description</h6>
                                                            <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Product Variants -->
                                                    <?php
                                                    $variants_query = "SELECT * FROM product_variants WHERE product_id = '{$row['id']}'";
                                                    $variants_result = mysqli_query($conn, $variants_query);
                                                    if($variants_result && mysqli_num_rows($variants_result) > 0):
                                                    ?>
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Available Variants</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Details</th>
                                                                            <th>Quantity</th>
                                                                            <th>SKU</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php while($variant = mysqli_fetch_assoc($variants_result)): 
                                                                            $details = [];
                                                                            if (!empty($variant['size'])) $details[] = "Size: " . $variant['size'];
                                                                            if (!empty($variant['color'])) $details[] = "Color: " . $variant['color'];
                                                                            if (!empty($variant['flavor'])) $details[] = "Flavor: " . $variant['flavor'];
                                                                            if (!empty($variant['weight'])) $details[] = "Weight: " . $variant['weight'];
                                                                            if (!empty($variant['fit_type'])) $details[] = "Fit: " . $variant['fit_type'];
                                                                            $details_str = !empty($details) ? implode(', ', $details) : '-';
                                                                        ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($details_str); ?></td>
                                                                            <td><?php echo $variant['qty']; ?></td>
                                                                            <td><?php echo htmlspecialchars($variant['sku']); ?></td>
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