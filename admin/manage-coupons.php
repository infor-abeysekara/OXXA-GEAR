<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle coupon actions
if(isset($_POST['action'])) {
    if($_POST['action'] == 'add' && isset($_POST['coupon_code'])) {
        $coupon_code = strtoupper(trim($_POST['coupon_code']));
        $discount_type = $_POST['discount_type'];
        $discount_value = $_POST['discount_value'];
        $min_amount = $_POST['min_amount'];
        $max_uses = $_POST['max_uses'];
        $expiry_date = $_POST['expiry_date'];
        $description = $_POST['description'];
        
        // Check if coupon already exists
        $check_query = "SELECT * FROM coupons WHERE coupon_code = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $coupon_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error_message = "Coupon code already exists!";
        } else {
            $insert_query = "INSERT INTO coupons (coupon_code, discount_type, discount_value, min_amount, max_uses, expiry_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssddiis", $coupon_code, $discount_type, $discount_value, $min_amount, $max_uses, $expiry_date, $description);
            
            if($stmt->execute()) {
                $success_message = "Coupon created successfully!";
            } else {
                $error_message = "Failed to create coupon.";
            }
        }
    } elseif($_POST['action'] == 'toggle_status' && isset($_POST['coupon_id'])) {
        $coupon_id = $_POST['coupon_id'];
        $new_status = $_POST['current_status'] == 1 ? 0 : 1;
        
        $update_query = "UPDATE coupons SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $new_status, $coupon_id);
        
        if($stmt->execute()) {
            $success_message = $new_status ? "Coupon activated successfully!" : "Coupon deactivated successfully!";
        } else {
            $error_message = "Failed to update coupon status.";
        }
    } elseif($_POST['action'] == 'delete' && isset($_POST['coupon_id'])) {
        $coupon_id = $_POST['coupon_id'];
        
        $delete_query = "DELETE FROM coupons WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $coupon_id);
        
        if($stmt->execute()) {
            $success_message = "Coupon deleted successfully!";
        } else {
            $error_message = "Failed to delete coupon.";
        }
    }
}

// Create coupons table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_amount DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT 0,
    used_count INT DEFAULT 0,
    expiry_date DATE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_table_query);

// Get all coupons - Fixed: Use created_at instead of created_date
$coupons_query = "SELECT * FROM coupons ORDER BY created_at DESC";
$coupons_result = mysqli_query($conn, $coupons_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Coupons - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .coupon-card {
            border-left: 4px solid #667eea;
            transition: transform 0.2s ease;
        }
        .coupon-card:hover {
            transform: translateY(-2px);
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
                    <h2 class="fw-bold text-dark">Manage Coupons</h2>
                    <p class="text-muted">Create and manage discount coupons for customers</p>
                </div>
            </div>

            <?php if(isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add New Coupon -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Create New Coupon</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coupon Code</label>
                                <input type="text" name="coupon_code" class="form-control" required placeholder="e.g., SAVE20">
                                <small class="text-muted">Use uppercase letters and numbers only</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Discount Type</label>
                                <select name="discount_type" class="form-select" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (Rs.)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Discount Value</label>
                                <input type="number" name="discount_value" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Minimum Order Amount (Rs.)</label>
                                <input type="number" name="min_amount" class="form-control" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maximum Uses (0 = Unlimited)</label>
                                <input type="number" name="max_uses" class="form-control" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the coupon"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create Coupon
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Coupons -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Existing Coupons</h5>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($coupons_result) > 0): ?>
                        <div class="row">
                            <?php while($coupon = mysqli_fetch_assoc($coupons_result)): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card coupon-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title fw-bold text-primary"><?php echo $coupon['coupon_code']; ?></h6>
                                                <?php if($coupon['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <?php if($coupon['discount_type'] == 'percentage'): ?>
                                                    <span class="h5 text-success"><?php echo $coupon['discount_value']; ?>% OFF</span>
                                                <?php else: ?>
                                                    <span class="h5 text-success">Rs. <?php echo number_format($coupon['discount_value'], 2); ?> OFF</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if($coupon['min_amount'] > 0): ?>
                                                <p class="small text-muted mb-1">
                                                    <i class="fas fa-shopping-cart me-1"></i>Min order: Rs. <?php echo number_format($coupon['min_amount'], 2); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if($coupon['max_uses'] > 0): ?>
                                                <p class="small text-muted mb-1">
                                                    <i class="fas fa-users me-1"></i>Uses: <?php echo $coupon['used_count']; ?>/<?php echo $coupon['max_uses']; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if($coupon['expiry_date']): ?>
                                                <p class="small text-muted mb-1">
                                                    <i class="fas fa-calendar me-1"></i>Expires: <?php echo date('M d, Y', strtotime($coupon['expiry_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if($coupon['description']): ?>
                                                <p class="small text-muted mb-2"><?php echo $coupon['description']; ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="mt-auto">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $coupon['is_active']; ?>">
                                                    <?php if($coupon['is_active']): ?>
                                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this coupon?')">
                                                            <i class="fas fa-pause"></i> Deactivate
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Activate this coupon?')">
                                                            <i class="fas fa-play"></i> Activate
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this coupon? This action cannot be undone!')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No coupons created yet. Create your first coupon above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <?php
            $total_coupons = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM coupons"));
            $active_coupons = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM coupons WHERE is_active = 1"));
            $expired_coupons = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM coupons WHERE expiry_date < CURDATE()"));
            ?>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-tags fa-2x text-primary mb-2"></i>
                            <h4 class="fw-bold"><?php echo $total_coupons; ?></h4>
                            <p class="text-muted mb-0">Total Coupons</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="fw-bold"><?php echo $active_coupons; ?></h4>
                            <p class="text-muted mb-0">Active Coupons</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="fw-bold"><?php echo $expired_coupons; ?></h4>
                            <p class="text-muted mb-0">Expired Coupons</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>