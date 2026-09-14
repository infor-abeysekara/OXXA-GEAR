<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle user actions
if(isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if($action == 'approve') {
        $update_query = "UPDATE users SET is_approved = 1 WHERE id = ?";
    } elseif($action == 'suspend') {
        $update_query = "UPDATE users SET is_approved = 0 WHERE id = ?";
    } elseif($action == 'delete') {
        $update_query = "DELETE FROM users WHERE id = ?";
    }
    
    if(isset($update_query)) {
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $user_id);
        
        if($stmt->execute()) {
            $success_message = ucfirst($action) . " action completed successfully!";
        } else {
            $error_message = "Failed to " . $action . " user.";
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_type = isset($_GET['type']) ? $_GET['type'] : '';

// Build search conditions
$search_conditions = [];
$search_params = [];
$param_types = '';

if (!empty($search)) {
    $search_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if (!empty($user_type)) {
    $search_conditions[] = "u.user_type = ?";
    $search_params[] = $user_type;
    $param_types .= 's';
}

$where_clause = !empty($search_conditions) ? 'WHERE ' . implode(' AND ', $search_conditions) : 'WHERE 1=1';

// Get sellers
$sellers_query = "SELECT u.*, sp.business_name, sp.is_approved as business_approved 
                 FROM users u 
                 LEFT JOIN seller_profiles sp ON u.id = sp.user_id 
                 $where_clause " . (!empty($user_type) ? "" : "AND u.user_type = 'seller'") . "
                 ORDER BY u.id DESC";

if (!empty($user_type) && $user_type !== 'seller') {
    $sellers_query = "SELECT u.*, sp.business_name, sp.is_approved as business_approved 
                     FROM users u 
                     LEFT JOIN seller_profiles sp ON u.id = sp.user_id 
                     WHERE 1=0"; // No results for sellers when filtering by customer
}

$sellers_stmt = $conn->prepare($sellers_query);
if (!empty($search_params) && (empty($user_type) || $user_type === 'seller')) {
    $sellers_stmt->bind_param($param_types, ...$search_params);
}
$sellers_stmt->execute();
$sellers_result = $sellers_stmt->get_result();

// Get buyers (customers)
$buyers_query = "SELECT * FROM users u 
                $where_clause " . (!empty($user_type) ? "" : "AND u.user_type = 'customer'") . "
                ORDER BY u.id DESC";

if (!empty($user_type) && $user_type !== 'customer') {
    $buyers_query = "SELECT * FROM users WHERE 1=0"; // No results for customers when filtering by seller
}

$buyers_stmt = $conn->prepare($buyers_query);
if (!empty($search_params) && (empty($user_type) || $user_type === 'customer')) {
    $buyers_stmt->bind_param($param_types, ...$search_params);
}
$buyers_stmt->execute();
$buyers_result = $buyers_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .user-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        .user-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            margin: 0 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .user-tabs .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: transparent;
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
                    <h2 class="fw-bold text-dark">Manage Users</h2>
                    <p class="text-muted">Search and manage sellers and buyers on the platform</p>
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

            <!-- Search Container -->
            <div class="search-container">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label fw-semibold">Search Users</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by name, username, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="type" class="form-label fw-semibold">Filter by Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Users</option>
                            <option value="seller" <?php echo $user_type === 'seller' ? 'selected' : ''; ?>>Sellers Only</option>
                            <option value="customer" <?php echo $user_type === 'customer' ? 'selected' : ''; ?>>Buyers Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($user_type)): ?>
                    <div class="mt-3">
                        <a href="manage-users.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-2"></i>Clear Filters
                        </a>
                        <span class="text-muted ms-3">
                            Showing results for: 
                            <?php if (!empty($search)): ?>
                                <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                            <?php endif; ?>
                            <?php if (!empty($user_type)): ?>
                                <strong><?php echo ucfirst($user_type); ?>s</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Tabs -->
            <ul class="nav nav-tabs user-tabs" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sellers-tab" data-bs-toggle="tab" 
                            data-bs-target="#sellers" type="button" role="tab">
                        <i class="fas fa-store me-2"></i>Sellers 
                        <span class="badge bg-primary ms-2"><?php echo $sellers_result->num_rows; ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="buyers-tab" data-bs-toggle="tab" 
                            data-bs-target="#buyers" type="button" role="tab">
                        <i class="fas fa-shopping-bag me-2"></i>Buyers 
                        <span class="badge bg-success ms-2"><?php echo $buyers_result->num_rows; ?></span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="userTabsContent">
                <!-- Sellers Tab -->
                <div class="tab-pane fade show active" id="sellers" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-store me-2"></i>Sellers Management</h5>
                        </div>
                        <div class="card-body">
                            <?php if($sellers_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Business</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = $sellers_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if(!empty($row['profile_image'])): ?>
                                                            <img src="../assets/uploads/profiles/<?php echo $row['profile_image']; ?>" alt="Profile" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                                                            <small class="text-muted">@<?php echo $row['username']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo $row['email']; ?></td>
                                                <td>
                                                    <?php if($row['business_name']): ?>
                                                        <?php echo htmlspecialchars($row['business_name']); ?>
                                                        <?php if($row['business_approved'] == 1): ?>
                                                            <span class="badge bg-success ms-1">Approved</span>
                                                        <?php elseif($row['business_approved'] == 0): ?>
                                                            <span class="badge bg-warning ms-1">Pending</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger ms-1">Rejected</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Registered</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($row['is_approved'] == 1): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Suspended</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                        <?php if($row['is_approved'] == 1): ?>
                                                            <button type="submit" name="action" value="suspend" class="btn btn-sm btn-warning" onclick="return confirm('Suspend this seller?')">
                                                                <i class="fas fa-pause"></i> Suspend
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Activate this seller?')">
                                                                <i class="fas fa-play"></i> Activate
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this seller? This action cannot be undone!')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No sellers found</h5>
                                    <p class="text-muted">
                                        <?php if (!empty($search) || !empty($user_type)): ?>
                                            Try adjusting your search criteria.
                                        <?php else: ?>
                                            No sellers have registered yet.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Buyers Tab -->
                <div class="tab-pane fade" id="buyers" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Buyers Management</h5>
                        </div>
                        <div class="card-body">
                            <?php if($buyers_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Registration</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = $buyers_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if(!empty($row['profile_image'])): ?>
                                                            <img src="../assets/uploads/profiles/<?php echo $row['profile_image']; ?>" alt="Profile" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong><br>
                                                            <small class="text-muted">@<?php echo $row['username']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo $row['email']; ?></td>
                                                <td>
                                                    <?php 
                                                    if(isset($row['created_at']) && !empty($row['created_at'])) {
                                                        echo date('M d, Y', strtotime($row['created_at']));
                                                    } else {
                                                        echo '<span class="text-muted">Not Available</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if($row['is_approved'] == 1): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Suspended</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                        <?php if($row['is_approved'] == 1): ?>
                                                            <button type="submit" name="action" value="suspend" class="btn btn-sm btn-warning" onclick="return confirm('Suspend this buyer?')">
                                                                <i class="fas fa-pause"></i> Suspend
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Activate this buyer?')">
                                                                <i class="fas fa-play"></i> Activate
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this buyer? This action cannot be undone!')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No buyers found</h5>
                                    <p class="text-muted">
                                        <?php if (!empty($search) || !empty($user_type)): ?>
                                            Try adjusting your search criteria.
                                        <?php else: ?>
                                            No buyers have registered yet.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>