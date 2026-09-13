<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle approval/rejection/activation/deactivation actions
if(isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if($action == 'approve') {
        // Approve business registration
        $update_query = "UPDATE seller_profiles SET is_approved = 1 WHERE user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $user_id);
        
        if($stmt->execute()) {
            $success_message = "Business registration approved successfully!";
        } else {
            $error_message = "Failed to approve business registration.";
        }
    } elseif($action == 'reject') {
        // Reject business registration
        $update_query = "UPDATE seller_profiles SET is_approved = -1 WHERE user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $user_id);
        
        if($stmt->execute()) {
            $success_message = "Business registration rejected successfully!";
        } else {
            $error_message = "Failed to reject business registration.";
        }
    } elseif($action == 'activate') {
        // Activate business registration (set to approved)
        $update_query = "UPDATE seller_profiles SET is_approved = 1 WHERE user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $user_id);
        
        if($stmt->execute()) {
            $success_message = "Business registration activated successfully!";
        } else {
            $error_message = "Failed to activate business registration.";
        }
    } elseif($action == 'deactivate') {
        // Deactivate business registration (set to suspended)
        $update_query = "UPDATE seller_profiles SET is_approved = 0 WHERE user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("s", $user_id);
        
        if($stmt->execute()) {
            $success_message = "Business registration deactivated successfully!";
        } else {
            $error_message = "Failed to deactivate business registration.";
        }
    }
}

// Get all business registrations
$registrations_query = "SELECT br.*, u.first_name, u.last_name, u.email, u.username 
                       FROM seller_profiles br 
                       JOIN users u ON br.user_id = u.id 
                       ORDER BY br.id DESC";
$registrations_result = mysqli_query($conn, $registrations_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registrations - Admin Panel</title>
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
                <small><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></small>
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
                    <a class="nav-link" href="manage-products.php">
                        <i class="fas fa-box me-2"></i>Manage Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="business-registrations.php">
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
                    <h2 class="fw-bold text-dark">Business Registrations Management</h2>
                    <p class="text-muted">Manage seller business registration requests - approve, reject, activate or deactivate</p>
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

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Business Registration Requests</h5>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($registrations_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Seller</th>
                                        <th>Business Name</th>
                                        <th>Business Type</th>
                                        <th>Registration ID</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($registrations_result)): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['business_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['business_type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['business_reg_id']); ?></td>
                                        <td>
                                            <?php if($row['is_approved'] == 1): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                            <?php elseif($row['is_approved'] == -1): ?>
                                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['user_id']; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <?php if($row['is_approved'] == 0): ?>
                                                    <!-- Pending - Show Approve/Reject -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Approve this business registration?')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" onclick="return confirm('Reject this business registration?')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif($row['is_approved'] == 1): ?>
                                                    <!-- Active - Show Deactivate -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                        <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this business registration? The seller will not be able to sell products.')">
                                                            <i class="fas fa-pause"></i> Deactivate
                                                        </button>
                                                    </form>
                                                <?php elseif($row['is_approved'] == -1): ?>
                                                    <!-- Rejected - Show Activate -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                        <button type="submit" name="action" value="activate" class="btn btn-sm btn-success" onclick="return confirm('Activate this business registration?')">
                                                            <i class="fas fa-play"></i> Activate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['user_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Business Registration Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6><i class="fas fa-user me-2"></i>Seller Information</h6>
                                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
                                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($row['email']); ?></p>
                                                            <p><strong>Username:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6><i class="fas fa-building me-2"></i>Business Information</h6>
                                                            <p><strong>Business Name:</strong> <?php echo htmlspecialchars($row['business_name']); ?></p>
                                                            <p><strong>Business Type:</strong> <?php echo htmlspecialchars($row['business_type']); ?></p>
                                                            <p><strong>Registration ID:</strong> <?php echo htmlspecialchars($row['business_reg_id']); ?></p>
                                                            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($row['business_number']); ?></p>
                                                            <p><strong>Registration Date:</strong> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6><i class="fas fa-info-circle me-2"></i>Current Status</h6>
                                                            <?php if($row['is_approved'] == 1): ?>
                                                                <div class="alert alert-success">
                                                                    <i class="fas fa-check-circle me-2"></i>This business registration is currently <strong>ACTIVE</strong>. The seller can add and sell products.
                                                                </div>
                                                            <?php elseif($row['is_approved'] == -1): ?>
                                                                <div class="alert alert-danger">
                                                                    <i class="fas fa-times-circle me-2"></i>This business registration is <strong>REJECTED</strong>. The seller cannot sell products.
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="alert alert-warning">
                                                                    <i class="fas fa-clock me-2"></i>This business registration is <strong>PENDING</strong> approval. The seller cannot sell products yet.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if(!empty($row['certificate_path'])): ?>
                                                        <div class="mt-3">
                                                            <h6><i class="fas fa-file-pdf me-2"></i>Business Certificate</h6>
                                                            <a href="../assets/uploads/seller_docs/<?php echo $row['certificate_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                                                <i class="fas fa-download me-2"></i>View Certificate
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if(!empty($row['logo_path'])): ?>
                                                        <div class="mt-3">
                                                            <h6><i class="fas fa-image me-2"></i>Business Logo</h6>
                                                            <img src="../assets/uploads/seller_docs/<?php echo $row['logo_path']; ?>" alt="Business Logo" class="img-fluid rounded" style="max-width: 200px; max-height: 200px;">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No business registrations found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mt-4">
                <?php
                // Get statistics
                $pending_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM seller_profiles WHERE is_approved = 0"));
                $active_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM seller_profiles WHERE is_approved = 1"));
                $rejected_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM seller_profiles WHERE is_approved = -1"));
                ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="fw-bold"><?php echo $pending_count; ?></h4>
                            <p class="text-muted mb-0">Pending Approvals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="fw-bold"><?php echo $active_count; ?></h4>
                            <p class="text-muted mb-0">Active Businesses</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                            <h4 class="fw-bold"><?php echo $rejected_count; ?></h4>
                            <p class="text-muted mb-0">Rejected/Deactivated</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>