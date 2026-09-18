<?php
session_start();
include_once("../include/connection.php");
include_once("../include/functions.php");

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
        $update_query = "UPDATE products SET is_approved = 1, status = 'active' WHERE id = ?";
    } elseif($action == 'reject') {
        $update_query = "UPDATE products SET is_approved = 0, status = 'suspended' WHERE id = ?";
    } elseif($action == 'suspend') {
        $update_query = "UPDATE products SET is_approved = 0, status = 'suspended' WHERE id = ?";
    } elseif($action == 'restore') {
        $update_query = "UPDATE products SET is_approved = 1, status = 'active' WHERE id = ?";
    } elseif($action == 'delete') {
        $delete_sizes_query = "DELETE cs FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = ?";
        $delete_images_query = "DELETE ci FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = ?";
        $delete_colors_query = "DELETE FROM product_colors WHERE product_id = ?";
        $delete_product_query = "DELETE FROM products WHERE id = ?";
        
        $stmt1 = $conn->prepare($delete_sizes_query); $stmt1->bind_param("i", $product_id); $stmt1->execute();
        $stmt1b = $conn->prepare($delete_images_query); $stmt1b->bind_param("i", $product_id); $stmt1b->execute();
        $stmt1c = $conn->prepare($delete_colors_query); $stmt1c->bind_param("i", $product_id); $stmt1c->execute();
        $stmt2 = $conn->prepare($delete_product_query); $stmt2->bind_param("i", $product_id);
        
        if($stmt2->execute()) {
            $success_message = "Product deleted successfully!";
        } else {
            $error_message = "Failed to delete product.";
        }
    } elseif($action == 'approve_hot_deal') {
        $update_hd = "UPDATE products SET is_hot_deal = 1, hot_deal_status = 'approved' WHERE id = ?";
        $stmt = $conn->prepare($update_hd);
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $stmt_hdr = $conn->prepare("UPDATE hot_deal_requests SET status = 'approved', reviewed_by_admin = ?, reviewed_at = NOW() WHERE product_id = ? AND status = 'pending'");
            $stmt_hdr->bind_param("ii", $admin_id, $product_id);
            $stmt_hdr->execute();

            $p_query = "SELECT p.name, u.id as seller_user_id, u.user_code FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?";
            $p_stmt = $conn->prepare($p_query);
            $p_stmt->bind_param("i", $product_id);
            $p_stmt->execute();
            if ($p_row = $p_stmt->get_result()->fetch_assoc()) {
                addNotification($conn, $p_row['seller_user_id'], "🔥 Hot Deal Live! - Your product {$p_row['name']} is now featured in Hot Deals on the homepage.", 'success', 'HotDeals', 'site/product-details.php?id=' . $product_id);
            }
            $success_message = "Hot deal approved! Product is now LIVE on the homepage Hot Deals section.";
        }
    } elseif($action == 'reject_hot_deal') {
        $reject_reason = trim($_POST['reject_reason'] ?? 'Discount requirements not met');
        $update_hd = "UPDATE products SET is_hot_deal = 0, hot_deal_status = 'rejected', hot_deal_request_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($update_hd);
        $stmt->bind_param("si", $reject_reason, $product_id);
        if ($stmt->execute()) {
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $stmt_hdr = $conn->prepare("UPDATE hot_deal_requests SET status = 'rejected', reject_reason = ?, reviewed_by_admin = ?, reviewed_at = NOW() WHERE product_id = ? AND status = 'pending'");
            $stmt_hdr->bind_param("sii", $reject_reason, $admin_id, $product_id);
            $stmt_hdr->execute();

            $p_query = "SELECT p.name, u.id as seller_user_id FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?";
            $p_stmt = $conn->prepare($p_query);
            $p_stmt->bind_param("i", $product_id);
            $p_stmt->execute();
            if ($p_row = $p_stmt->get_result()->fetch_assoc()) {
                addNotification($conn, $p_row['seller_user_id'], "Hot Deal Request Rejected - {$p_row['name']}: {$reject_reason}", 'warning', 'HotDeals', 'site/seller-edit-product.php?id=' . $product_id);
            }
            $success_message = "Hot deal request rejected.";
        }
    } elseif($action == 'deactivate_hot_deal') {
        $update_hd = "UPDATE products SET is_hot_deal = 0, hot_deal_status = 'expired' WHERE id = ?";
        $stmt = $conn->prepare($update_hd);
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $success_message = "Hot deal deactivated from homepage.";
        }
    } elseif($action == 'update_hot_deal_expiry') {
        $new_expiry = trim($_POST['new_expiry'] ?? '') . ' 23:59:59';
        $update_exp = "UPDATE products SET hot_deal_expiry = ? WHERE id = ?";
        $stmt = $conn->prepare($update_exp);
        $stmt->bind_param("si", $new_expiry, $product_id);
        if ($stmt->execute()) {
            $success_message = "Hot deal expiry updated successfully.";
        }
    }
    
    if(isset($update_query)) {
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $product_id);
        
        if($stmt->execute()) {
            $success_message = ucfirst($action) . " action completed successfully!";
            
            // Notifications...
            $p_query = "SELECT p.name, u.user_code FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?";
            $p_stmt = $conn->prepare($p_query);
            $p_stmt->bind_param("i", $product_id);
            $p_stmt->execute();
            $p_res = $p_stmt->get_result();
            if ($p_row = $p_res->fetch_assoc()) {
                if ($action == 'approve') {
                    addNotification($conn, $p_row['user_code'], "Product Approved! - Your product {$p_row['name']} has been approved and is now live on the store.", 'info', 'Business', 'site/product-details.php?id=' . $product_id);
                } elseif ($action == 'suspend' || $action == 'reject') {
                    addNotification($conn, $p_row['user_code'], "Product Suspended - Your product {$p_row['name']} has been suspended from the store.", 'warning', 'Business', 'site/seller-dashboard.php');
                }
            }
        } else {
            $error_message = "Failed to " . $action . " product.";
        }
    }
}

// -- STATS QUERIES --
$stats = [];
// Total Products
$res = mysqli_query($conn, "SELECT COUNT(*) as count FROM products");
$stats['total_products'] = mysqli_fetch_assoc($res)['count'];

// Total Variants
$res = mysqli_query($conn, "SELECT COUNT(*) as count FROM color_sizes");
$stats['total_variants'] = mysqli_fetch_assoc($res)['count'];

// Low Stock Alerts (qty < 10)
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT pc.product_id) as count FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE cs.qty < 10");
$stats['low_stock'] = mysqli_fetch_assoc($res)['count'];

// Active Sellers
$res = mysqli_query($conn, "SELECT COUNT(DISTINCT p.seller_id) as count FROM products p JOIN users u ON p.seller_id = u.id WHERE p.status = 'active'");
$stats['active_sellers'] = mysqli_fetch_assoc($res)['count'];

// Pending Approval Count
$res = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE is_approved = 0 AND status != 'suspended'");
$stats['pending_count'] = mysqli_fetch_assoc($res)['count'];

// Pending Hot Deal Requests Count
$res = mysqli_query($conn, "SELECT COUNT(*) as count FROM hot_deal_requests WHERE status = 'pending'");
$stats['hot_deal_requests_count'] = $res ? (int)mysqli_fetch_assoc($res)['count'] : 0;

// Active Hot Deals Count
$res = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE is_hot_deal = 1 AND hot_deal_status = 'approved' AND status = 'active' AND (hot_deal_expiry IS NULL OR hot_deal_expiry >= CURDATE())");
$stats['active_hot_deals_count'] = $res ? (int)mysqli_fetch_assoc($res)['count'] : 0;


// -- TAB LOGIC & PAGINATION --
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = ["1=1"];

if($tab == 'pending') {
    $where_clauses[] = "p.is_approved = 0 AND p.status != 'suspended'";
} elseif($tab == 'approved') {
    $where_clauses[] = "p.is_approved = 1 AND p.status = 'active'";
} elseif($tab == 'suspended') {
    $where_clauses[] = "p.status = 'suspended'";
} elseif($tab == 'low_stock') {
    $where_clauses[] = "EXISTS (SELECT 1 FROM product_colors pc JOIN color_sizes cs ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.qty < 10)";
}

if(!empty($search)) {
    $where_clauses[] = "(p.name LIKE '%".mysqli_real_escape_string($conn, $search)."%' OR b.name LIKE '%".mysqli_real_escape_string($conn, $search)."%' OR u.first_name LIKE '%".mysqli_real_escape_string($conn, $search)."%')";
}

$where_sql = implode(" AND ", $where_clauses);

// Tab specific queries for hot deals
$hdr_result = null;
$hdr_rows = [];
$active_hd_result = null;
$active_hd_rows = [];

if($tab == 'hot_deal_requests') {
    $hdr_query = "
        SELECT hdr.*, p.name as product_name, p.product_code, p.base_price, p.cost_price, p.total_qty, p.status as product_status,
               u.first_name, u.last_name, u.email, u.profile_image,
               sp.business_name, sp.is_approved as seller_verified,
               b.name as brand, c.name as category_name,
               (SELECT ci.image_path FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = p.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1) as product_image,
               (SELECT COUNT(p2.id) FROM products p2 WHERE p2.seller_id = u.id) as seller_total_products
        FROM hot_deal_requests hdr
        JOIN products p ON hdr.product_id = p.id
        JOIN users u ON hdr.seller_id = u.id
        LEFT JOIN seller_profiles sp ON u.id = sp.user_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE hdr.status = 'pending'
        ORDER BY hdr.requested_at DESC
    ";
    $hdr_result = mysqli_query($conn, $hdr_query);
} elseif($tab == 'active_hot_deals') {
    $active_hd_query = "
        SELECT p.*, 
               u.first_name, u.last_name, u.email, u.profile_image, 
               sp.business_name, sp.is_approved as seller_verified,
               b.name as brand, c.name as category_name,
               (SELECT ci.image_path FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = p.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1) as product_image,
               (SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id) as current_stock
        FROM products p 
        JOIN users u ON p.seller_id = u.id 
        LEFT JOIN seller_profiles sp ON u.id = sp.user_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_hot_deal = 1 AND p.hot_deal_status = 'approved' AND p.status = 'active'
        ORDER BY p.discount_percent DESC
    ";
    $active_hd_result = mysqli_query($conn, $active_hd_query);
}

// Total for pagination
$total_query = "SELECT COUNT(*) as total FROM products p LEFT JOIN brands b ON p.brand_id = b.id LEFT JOIN users u ON p.seller_id = u.id WHERE $where_sql";
$total_result = mysqli_query($conn, $total_query);
$total_products = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_products / $limit);

// Main Query with complex aggregations
$products_query = "
    SELECT p.*, 
           u.first_name, u.last_name, u.email, u.profile_image, 
           sp.business_name, sp.is_approved as seller_verified,
           b.name as brand, c.name as category_name,
           (SELECT ci.image_path FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = p.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1) as product_image,
           (SELECT COUNT(cs.id) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id) as variants_count,
           (SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id) as total_qty,
           (SELECT COUNT(cs.id) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id AND cs.qty > 0) as variants_in_stock,
           (SELECT MIN(IF(cs.selling_price > 0, cs.selling_price, p.base_price)) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id) as min_price,
           (SELECT MAX(IF(cs.selling_price > 0, cs.selling_price, p.base_price)) FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = p.id) as max_price
    FROM products p 
    JOIN users u ON p.seller_id = u.id 
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F8FAFF; font-family: 'Inter', sans-serif; color: #2d3436; }
        .main-content { margin-left: 250px; padding: 30px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 15px; } }

        /* Header Area */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .page-title { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 5px; }
        .page-subtitle { color: #64748b; font-size: 15px; }
        
        /* Buttons */
        .btn-purple { background: #6c5ce7; color: white; border-radius: 8px; font-weight: 600; padding: 10px 24px; transition: all 0.2s; border: none; }
        .btn-purple:hover { background: #5b4bc4; color: white; }
        .btn-action-outline { border: 1px solid #cbd5e1; background: white; color: #334155; border-radius: 8px; font-weight: 600; padding: 10px 20px; transition: all 0.2s; }
        .btn-action-outline:hover { background: #f8fafc; color: #0f172a; }

        /* Stat Cards */
        .stat-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; height: 100%; display: flex; flex-direction: column; }
        .stat-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-icon.purple { background: #f3f0ff; color: #7c3aed; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.orange { background: #fff7ed; color: #ea580c; }
        .stat-icon.green { background: #f0fdf4; color: #16a34a; }
        .stat-title { font-size: 14px; color: #64748b; font-weight: 600; }
        .stat-value { font-size: 36px; font-weight: 700; color: #0f172a; margin-bottom: 8px; line-height: 1; }
        .stat-trend { font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .stat-trend.positive { color: #10b981; }
        .stat-trend.warning { color: #ea580c; }

        /* Tabs */
        .custom-tabs-container { margin-top: 40px; margin-bottom: 20px; }
        .custom-tabs { display: flex; gap: 30px; border-bottom: 1px solid #e2e8f0; }
        .custom-tab { padding: 12px 0; color: #64748b; font-weight: 600; font-size: 15px; text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s; position: relative; top: 1px; }
        .custom-tab:hover { color: #0f172a; }
        .custom-tab.active { color: #6c5ce7; border-bottom-color: #6c5ce7; }
        
        /* Table Card */
        .premium-table-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: visible; }
        
        /* Filter Bar */
        .filter-bar { padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 15px; align-items: center; justify-content: space-between; }
        .search-box { position: relative; flex-grow: 1; max-width: 400px; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-box input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border 0.2s; }
        .search-box input:focus { border-color: #6c5ce7; }
        
        .filter-actions { display: flex; gap: 10px; }
        .btn-filter { border: 1px solid #cbd5e1; background: white; color: #334155; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Table Styles */
        .premium-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .premium-table th { background: #f8fafc; color: #64748b; font-weight: 600; font-size: 12px; text-transform: uppercase; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; letter-spacing: 0.5px; }
        .premium-table td { padding: 20px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
        .premium-table tr:hover td { background: #f8fafc; }
        .premium-table tr:last-child td { border-bottom: none; }

        .prod-img { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; border: 1px solid #e2e8f0; }
        .prod-name { font-weight: 700; color: #0f172a; font-size: 15px; margin-bottom: 2px; }
        .prod-meta { font-size: 13px; color: #64748b; }
        
        .seller-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; }
        .seller-name { font-weight: 600; color: #334155; font-size: 14px; }
        .seller-meta { font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 4px; }

        .category-pill { background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }

        .inv-summary { font-weight: 700; color: #0f172a; font-size: 14px; margin-bottom: 8px; }
        .progress-bar-container { width: 100%; height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 4px; }
        .progress-bar-fill.good { background: #10b981; }
        .progress-bar-fill.low { background: #ea580c; }
        .inv-meta { font-size: 12px; font-weight: 500; margin-top: 6px; }
        .inv-meta.good { color: #10b981; }
        .inv-meta.low { color: #ea580c; }

        .price-range { font-weight: 700; color: #0f172a; font-size: 15px; margin-bottom: 2px; }
        .price-meta { font-size: 12px; color: #64748b; }

        .status-pill { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .status-pill.active { background: #f0fdf4; color: #16a34a; }
        .status-pill.pending { background: #eff6ff; color: #3b82f6; }
        .status-pill.suspended { background: #fef2f2; color: #dc2626; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-dot.active { background: #16a34a; }
        .status-dot.pending { background: #3b82f6; }
        .status-dot.suspended { background: #dc2626; }

        /* Action Dropdown */
        .actions-cell { display: flex; align-items: center; gap: 8px; }
        .btn-manage-variants { background: #4f46e5; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: background 0.2s; }
        .btn-manage-variants:hover { background: #4338ca; }
        
        .action-dropdown .btn-dots { background: white; border: 1px solid #cbd5e1; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s; }
        .action-dropdown .btn-dots:hover { background: #f1f5f9; color: #0f172a; }
        .dropdown-menu { border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 12px; padding: 8px; }
        .dropdown-item { border-radius: 8px; padding: 8px 16px; font-weight: 500; font-size: 14px; color: #334155; display: flex; align-items: center; gap: 8px; }
        .dropdown-item:hover { background: #f1f5f9; }
        .dropdown-item.text-danger:hover { background: #fef2f2; }
    </style>
</head>
<body>
    <?php include("components/sidebar.php"); ?>
    
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Manage Products</h1>
                <p class="page-subtitle mb-0">Manage your product catalog, approvals and inventory across all sellers</p>
            </div>
            <div class="d-flex gap-3">
                <button class="btn-action-outline"><i class="fas fa-download me-2"></i> Export</button>
                <a href="#" class="btn-purple text-decoration-none"><i class="fas fa-plus me-2"></i> Add Product</a>
            </div>
        </div>

        <?php if(isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon purple"><i class="fas fa-box"></i></div>
                        <div class="stat-title">Total Products</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-trend positive"><i class="fas fa-arrow-up"></i> Active globally</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon blue"><i class="fas fa-layer-group"></i></div>
                        <div class="stat-title">Total Variants</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_variants']); ?></div>
                    <div class="stat-trend positive"><i class="fas fa-arrow-up"></i> Across all colors</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-title">Low Stock Alerts</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['low_stock']); ?></div>
                    <div class="stat-trend warning"><i class="fas fa-exclamation-circle"></i> Requires attention</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon green"><i class="fas fa-users"></i></div>
                        <div class="stat-title">Active Sellers</div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active_sellers']); ?></div>
                    <div class="stat-trend positive"><i class="fas fa-check-circle"></i> Verified partners</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="custom-tabs-container">
            <div class="custom-tabs">
                <a href="?tab=all" class="custom-tab <?php echo $tab == 'all' ? 'active' : ''; ?>">All Products</a>
                <a href="?tab=pending" class="custom-tab <?php echo $tab == 'pending' ? 'active' : ''; ?>">Pending Approval <?php if($stats['pending_count'] > 0) echo "<span class='badge bg-primary ms-1 rounded-pill'>{$stats['pending_count']}</span>"; ?></a>
                <a href="?tab=hot_deal_requests" class="custom-tab <?php echo $tab == 'hot_deal_requests' ? 'active' : ''; ?>">
                    <i class="fas fa-bolt text-warning me-1"></i> Hot Deal Requests 
                    <?php if($stats['hot_deal_requests_count'] > 0): ?>
                        <span class="badge bg-danger ms-1 rounded-pill animate-pulse"><?php echo $stats['hot_deal_requests_count']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=active_hot_deals" class="custom-tab <?php echo $tab == 'active_hot_deals' ? 'active' : ''; ?>">
                    <i class="fas fa-fire text-danger me-1"></i> Active Hot Deals 
                    <?php if($stats['active_hot_deals_count'] > 0): ?>
                        <span class="badge bg-success ms-1 rounded-pill"><?php echo $stats['active_hot_deals_count']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=approved" class="custom-tab <?php echo $tab == 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?tab=suspended" class="custom-tab <?php echo $tab == 'suspended' ? 'active' : ''; ?>">Suspended</a>
                <a href="?tab=low_stock" class="custom-tab <?php echo $tab == 'low_stock' ? 'active' : ''; ?>">Low Stock</a>
            </div>
        </div>

        <!-- Table Card -->
        <div class="premium-table-card">
            <div class="filter-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <form action="" method="GET">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                        <input type="text" name="search" placeholder="Search products, sellers, or ID..." value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="filter-actions">
                    <button class="btn-filter"><i class="fas fa-sliders-h"></i> Filters</button>
                    <button class="btn-filter"><i class="fas fa-check-square"></i> Bulk Actions <i class="fas fa-chevron-down ms-1 text-muted" style="font-size: 10px;"></i></button>
                </div>
            </div>

            <div class="table-responsive">
                <?php if($tab == 'hot_deal_requests'): ?>
                    <!-- HOT DEAL REQUESTS TABLE -->
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Seller</th>
                                <th>Discount & Pricing</th>
                                <th>Stock</th>
                                <th>Clearance Reason</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($hdr_result && mysqli_num_rows($hdr_result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($hdr_result)): 
                                    $orig_price = (float)$row['original_price'];
                                    $sale_price = (float)$row['sale_price'];
                                    $base_price = (float)$row['base_price'];
                                    $is_inflated = ($orig_price > ($base_price * 1.20));
                                    $markup_pct = ($base_price > 0) ? round((($orig_price - $base_price) / $base_price) * 100) : 0;
                                ?>
                                <tr>
                                    <!-- PRODUCT -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($row['product_image'])): ?>
                                                <img src="../assets/uploads/products/<?php echo htmlspecialchars($row['product_image']); ?>" class="prod-img" alt="Product">
                                            <?php else: ?>
                                                <div class="prod-img bg-light d-flex align-items-center justify-content-center text-muted"><i class="fas fa-bolt text-warning fa-lg"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="prod-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                                <div class="prod-meta">SKU: <?php echo htmlspecialchars($row['product_code']); ?> &bull; <?php echo htmlspecialchars($row['brand'] ?? 'Brand'); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- SELLER -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($row['profile_image'])): ?>
                                                <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($row['profile_image']); ?>" class="seller-avatar" alt="Avatar">
                                            <?php else: ?>
                                                <div class="seller-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-6">
                                                    <?php echo strtoupper(substr($row['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="seller-name"><?php echo htmlspecialchars($row['business_name'] ?: $row['first_name'].' '.$row['last_name']); ?></div>
                                                <div class="seller-meta">
                                                    <?php echo htmlspecialchars($row['email']); ?>
                                                    <?php if($row['seller_verified'] == 1): ?>
                                                        <i class="fas fa-check-circle text-success" title="Verified Seller"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- DISCOUNT & PRICING -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge px-2.5 py-1 text-dark fw-bold rounded-3" style="background-color: #CCFF00; font-size: 13px;">
                                                -<?php echo $row['requested_discount']; ?>%
                                            </span>
                                            <?php if($is_inflated): ?>
                                                <span class="badge bg-warning text-dark" title="Price inflated by +<?php echo $markup_pct; ?>% over base price">
                                                    <i class="fas fa-exclamation-triangle"></i> Fake Warning
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted text-decoration-line-through small">Rs. <?php echo number_format($orig_price, 2); ?></span>
                                            <span class="fw-bold text-success">Rs. <?php echo number_format($sale_price, 2); ?></span>
                                        </div>
                                    </td>

                                    <!-- STOCK -->
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?php echo number_format($row['total_qty']); ?> units</div>
                                        <div class="small <?php echo ($row['total_qty'] < 10) ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                            <?php echo ($row['total_qty'] < 10) ? '⚠️ Under 10 min' : 'Adequate stock'; ?>
                                        </div>
                                    </td>

                                    <!-- REASON -->
                                    <td>
                                        <div class="p-2 rounded-2 bg-light border text-dark small" style="max-width: 220px;">
                                            <i class="fas fa-quote-left text-muted me-1"></i> <?php echo htmlspecialchars($row['reason'] ?: 'Clearance stock'); ?>
                                        </div>
                                    </td>

                                    <!-- REQUESTED -->
                                    <td>
                                        <div class="small text-dark fw-medium"><?php echo date('M d, Y', strtotime($row['requested_at'])); ?></div>
                                        <div class="text-muted" style="font-size: 11px;"><?php echo date('h:i A', strtotime($row['requested_at'])); ?></div>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <button class="btn btn-sm btn-outline-primary fw-bold px-2.5 py-1.5 rounded-2" data-bs-toggle="modal" data-bs-target="#viewHdrModal_<?php echo $row['id']; ?>" title="View & Fake Price Check">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                                                <input type="hidden" name="action" value="approve_hot_deal">
                                                <button type="submit" class="btn btn-sm btn-success fw-bold px-2.5 py-1.5 rounded-2" title="Approve and feature on homepage">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                            </form>

                                            <button class="btn btn-sm btn-outline-danger fw-bold px-2.5 py-1.5 rounded-2" data-bs-toggle="modal" data-bs-target="#rejectHdrModal_<?php echo $row['id']; ?>" title="Reject Request">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted mb-3"><i class="fas fa-bolt fa-3x text-warning"></i></div>
                                        <h5 class="fw-bold text-dark">No Pending Hot Deal Requests</h5>
                                        <p class="text-muted mb-0">When sellers submit clearance deals, they will appear here for review.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php elseif($tab == 'active_hot_deals'): ?>
                    <!-- ACTIVE HOT DEALS TABLE -->
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Seller</th>
                                <th>Active Deal Pricing</th>
                                <th>Stock</th>
                                <th>Expiry Countdown</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($active_hd_result && mysqli_num_rows($active_hd_result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($active_hd_result)): 
                                    $orig_price = (float)$row['original_price'];
                                    $sale_price = (float)$row['sale_price'];
                                    $days_left = !empty($row['hot_deal_expiry']) ? ceil((strtotime($row['hot_deal_expiry']) - time()) / 86400) : null;
                                ?>
                                <tr>
                                    <!-- PRODUCT -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($row['product_image'])): ?>
                                                <img src="../assets/uploads/products/<?php echo htmlspecialchars($row['product_image']); ?>" class="prod-img" alt="Product">
                                            <?php else: ?>
                                                <div class="prod-img bg-light d-flex align-items-center justify-content-center text-muted"><i class="fas fa-fire text-danger fa-lg"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="prod-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                                <div class="prod-meta">SKU: <?php echo htmlspecialchars($row['product_code']); ?> &bull; <?php echo htmlspecialchars($row['brand'] ?? 'Brand'); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- SELLER -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div>
                                                <div class="seller-name"><?php echo htmlspecialchars($row['business_name'] ?: $row['first_name'].' '.$row['last_name']); ?></div>
                                                <div class="seller-meta"><?php echo htmlspecialchars($row['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- ACTIVE DEAL PRICING -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge px-2.5 py-1 text-dark fw-bold rounded-3" style="background-color: #CCFF00; font-size: 13px;">
                                                -<?php echo $row['discount_percent']; ?>% LIVE
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted text-decoration-line-through small">Rs. <?php echo number_format($orig_price, 2); ?></span>
                                            <span class="fw-bold text-success">Rs. <?php echo number_format($sale_price, 2); ?></span>
                                        </div>
                                    </td>

                                    <!-- STOCK -->
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo number_format($row['current_stock'] ?? $row['total_qty']); ?> units</div>
                                        <div class="small text-muted">Active stock</div>
                                    </td>

                                    <!-- EXPIRY -->
                                    <td>
                                        <div class="d-flex align-items-center gap-1.5 fw-bold text-dark">
                                            <i class="fas fa-clock text-warning"></i>
                                            <span>
                                                <?php 
                                                if($days_left === null) {
                                                    echo 'No expiry set';
                                                } elseif($days_left > 1) {
                                                    echo "Ends in {$days_left} days";
                                                } elseif($days_left == 1) {
                                                    echo "Ends tomorrow";
                                                } elseif($days_left == 0) {
                                                    echo "Ends today";
                                                } else {
                                                    echo "Expired";
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="text-muted" style="font-size: 11px;">
                                            <?php echo !empty($row['hot_deal_expiry']) ? date('M d, Y', strtotime($row['hot_deal_expiry'])) : ''; ?>
                                        </div>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <button class="btn btn-sm btn-outline-secondary fw-bold px-2 py-1 rounded-2" data-bs-toggle="modal" data-bs-target="#editExpiryModal_<?php echo $row['id']; ?>" title="Edit Expiry Date">
                                                <i class="fas fa-calendar-alt me-1"></i> Edit Expiry
                                            </button>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this deal from the homepage?')">
                                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="deactivate_hot_deal">
                                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold px-2 py-1 rounded-2">
                                                    <i class="fas fa-ban me-1"></i> Deactivate
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted mb-3"><i class="fas fa-fire fa-3x text-muted"></i></div>
                                        <h5 class="fw-bold text-dark">No Active Hot Deals Currently Running</h5>
                                        <p class="text-muted mb-0">Approve pending Hot Deal requests to feature products on the homepage.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php else: ?>
                    <!-- CATALOG PRODUCTS TABLE -->
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Seller</th>
                                <th>Category</th>
                                <th>Inventory Summary</th>
                                <th>Price Range</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($products_result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($products_result)): 
                                    // Calc progress
                                    $totalVariants = (int)$row['variants_count'];
                                    $inStockVariants = (int)$row['variants_in_stock'];
                                    $progress = $totalVariants > 0 ? round(($inStockVariants / $totalVariants) * 100) : 0;
                                    $isLowStock = $progress < 30; // Threshold
                                    
                                    $minPrice = (float)$row['min_price'];
                                    $maxPrice = (float)$row['max_price'];
                                ?>
                                <tr>
                                    <!-- PRODUCT -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($row['product_image'])): ?>
                                                <img src="../assets/uploads/products/<?php echo htmlspecialchars($row['product_image']); ?>" class="prod-img" alt="Product">
                                            <?php else: ?>
                                                <div class="prod-img bg-light d-flex align-items-center justify-content-center text-muted"><i class="fas fa-box fa-lg"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="prod-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                                <div class="prod-meta">SKU: PRD-<?php echo $row['id']; ?> &bull; <?php echo htmlspecialchars($row['brand'] ?? 'Unbranded'); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- SELLER -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if(!empty($row['profile_image'])): ?>
                                                <img src="../assets/uploads/profiles/<?php echo htmlspecialchars($row['profile_image']); ?>" class="seller-avatar" alt="Avatar">
                                            <?php else: ?>
                                                <div class="seller-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-6">
                                                    <?php echo strtoupper(substr($row['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="seller-name"><?php echo htmlspecialchars($row['business_name'] ?: $row['first_name'].' '.$row['last_name']); ?></div>
                                                <div class="seller-meta">
                                                    <?php echo htmlspecialchars($row['email']); ?>
                                                    <?php if($row['seller_verified'] == 1): ?>
                                                        <i class="fas fa-check-circle text-success" title="Verified Seller"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td>
                                        <div class="category-pill">
                                            <i class="fas fa-tag text-muted"></i> <?php echo htmlspecialchars($row['category_name'] ?? 'General'); ?>
                                        </div>
                                    </td>

                                    <!-- INVENTORY -->
                                    <td>
                                        <div class="inv-summary"><?php echo number_format($totalVariants); ?> Variants <span class="text-muted fw-normal mx-1">|</span> <?php echo number_format((int)$row['total_qty']); ?> Qty</div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill <?php echo $isLowStock ? 'low' : 'good'; ?>" style="width: <?php echo max(5, $progress); ?>%;"></div>
                                        </div>
                                        <div class="inv-meta <?php echo $isLowStock ? 'low' : 'good'; ?>">
                                            <?php echo $progress; ?>% in stock <?php if($isLowStock) echo '&bull; Low stock'; ?>
                                        </div>
                                    </td>

                                    <!-- PRICE -->
                                    <td>
                                        <div class="price-range">
                                            <?php 
                                            if($minPrice == $maxPrice || $maxPrice == 0) {
                                                echo "Rs. " . number_format($minPrice > 0 ? $minPrice : $row['base_price'], 2);
                                            } else {
                                                echo "Rs. " . number_format($minPrice, 2) . " - " . number_format($maxPrice, 2);
                                            }
                                            ?>
                                        </div>
                                        <div class="price-meta">
                                            <?php echo ($minPrice != $maxPrice) ? '<span class="text-primary fw-medium">Different price per size</span>' : 'Fixed price'; ?>
                                        </div>
                                    </td>

                                    <!-- STATUS -->
                                    <td>
                                        <?php if($row['is_approved'] == 0): ?>
                                            <div class="status-pill pending"><span class="status-dot pending"></span> Pending</div>
                                        <?php elseif($row['status'] == 'suspended'): ?>
                                            <div class="status-pill suspended"><span class="status-dot suspended"></span> Suspended</div>
                                        <?php else: ?>
                                            <div class="status-pill active"><span class="status-dot active"></span> Active</div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="actions-cell">
                                            <button class="btn-manage-variants" onclick="openVariantsModal(<?php echo $row['id']; ?>)">
                                                Manage Variants <i class="fas fa-chevron-down ms-1" style="font-size: 10px;"></i>
                                            </button>
                                            
                                            <div class="dropdown action-dropdown">
                                                <button class="btn-dots" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <form method="POST">
                                                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                                        
                                                        <?php if($row['is_approved'] == 0): ?>
                                                            <li><button type="submit" name="action" value="approve" class="dropdown-item text-success"><i class="fas fa-check w-20px"></i> Approve Product</button></li>
                                                            <li><button type="submit" name="action" value="reject" class="dropdown-item text-warning"><i class="fas fa-times w-20px"></i> Reject Product</button></li>
                                                        <?php else: ?>
                                                            <?php if($row['status'] == 'suspended'): ?>
                                                                <li><button type="submit" name="action" value="restore" class="dropdown-item text-success"><i class="fas fa-undo w-20px"></i> Restore Product</button></li>
                                                            <?php else: ?>
                                                                <li><button type="submit" name="action" value="suspend" class="dropdown-item text-warning"><i class="fas fa-pause w-20px"></i> Suspend Product</button></li>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><button type="submit" name="action" value="delete" class="dropdown-item text-danger" onclick="return confirm('Delete this product permanently?')"><i class="fas fa-trash-alt w-20px"></i> Delete Product</button></li>
                                                    </form>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted mb-3"><i class="fas fa-box-open fa-3x"></i></div>
                                        <h5 class="fw-bold text-dark">No products found</h5>
                                        <p class="text-muted mb-0">Try adjusting your filters or search query.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            </div>
            
            <!-- Pagination Footer -->
            <?php if($total_pages > 1): ?>
            <div class="p-3 border-top d-flex justify-content-between align-items-center bg-white" style="border-radius: 0 0 16px 16px;">
                <div class="text-muted font-size-14">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_products); ?> of <?php echo $total_products; ?> products
                </div>
                <nav aria-label="Pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?tab=<?php echo htmlspecialchars($tab); ?>&search=<?php echo htmlspecialchars($search); ?>&page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        </li>
                        <li class="page-item active"><span class="page-link px-3" style="background: #4f46e5; border-color: #4f46e5;"><?php echo $page; ?></span></li>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?tab=<?php echo htmlspecialchars($tab); ?>&search=<?php echo htmlspecialchars($search); ?>&page=<?php echo $page + 1; ?>">Next <i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Variants Modal -->
    <div class="modal fade" id="variantsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
                <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                    <div>
                        <h4 class="modal-title fw-bold text-dark" id="modalProductName">Manage Variants</h4>
                        <p class="text-muted mb-0 font-size-14">Double click on any quantity or price cell to inline edit.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="variantsModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2 text-muted">Loading variant matrix...</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn-action-outline" data-bs-dismiss="modal">Close Window</button>
                    <!-- Apply All or Bulk Save could go here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Hot Deals Modals -->
    <?php if(!empty($hdr_rows)): ?>
        <?php foreach($hdr_rows as $row): 
            $orig_price = (float)$row['original_price'];
            $sale_price = (float)$row['sale_price'];
            $base_price = (float)$row['base_price'];
            $is_inflated = ($orig_price > ($base_price * 1.20));
            $markup_pct = ($base_price > 0) ? round((($orig_price - $base_price) / $base_price) * 100) : 0;
            $modal_id = "viewHdrModal_" . $row['id'];
            $reject_modal_id = "rejectHdrModal_" . $row['id'];
        ?>
        <!-- View & Fake Price Check Modal -->
        <div class="modal fade" id="<?php echo $modal_id; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);">
                    <div class="modal-header border-bottom px-4 pt-4 pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge px-3 py-1.5 rounded-pill text-dark fw-bold" style="background-color: #CCFF00; font-size: 13px;">
                                <i class="fas fa-bolt me-1"></i> Hot Deal Request
                            </span>
                            <span class="text-muted small">Submitted <?php echo date('M d, Y h:i A', strtotime($row['requested_at'])); ?></span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Product and Seller info row -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-5 text-center">
                                <div class="bg-light p-3 rounded-4 border position-relative">
                                    <?php if(!empty($row['product_image'])): ?>
                                        <img src="../assets/uploads/products/<?php echo htmlspecialchars($row['product_image']); ?>" class="img-fluid rounded-3" style="max-height: 200px; object-fit: contain;" alt="Product">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-white rounded-3" style="height: 180px;">
                                            <i class="fas fa-box text-muted fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span class="position-absolute top-2 start-2 badge text-dark fw-bold" style="background: #CCFF00; font-size: 14px;">
                                        -<?php echo $row['requested_discount']; ?>%
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark mt-3 mb-1"><?php echo htmlspecialchars($row['product_name']); ?></h5>
                                <div class="text-muted small">SKU: <?php echo htmlspecialchars($row['product_code']); ?> &bull; <?php echo htmlspecialchars($row['brand'] ?? 'Unbranded'); ?></div>
                            </div>
                            <div class="col-md-7">
                                <!-- Price terms card -->
                                <div class="p-3 bg-light rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <span class="text-muted small text-uppercase fw-bold">Seller's Original Price</span>
                                        <span class="text-muted text-decoration-line-through fw-bold">Rs. <?php echo number_format($orig_price, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <span class="text-dark small text-uppercase fw-bold">Catalog Base Price</span>
                                        <span class="fw-bold text-dark">Rs. <?php echo number_format($base_price, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <span class="text-dark small text-uppercase fw-bold">Requested Sale Price</span>
                                        <span class="fw-bold text-success fs-5">Rs. <?php echo number_format($sale_price, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-dark small text-uppercase fw-bold">Total Stock Available</span>
                                        <span class="fw-bold <?php echo ($row['total_qty'] < 10) ? 'text-danger' : 'text-primary'; ?>">
                                            <?php echo number_format($row['total_qty']); ?> units <?php if($row['total_qty'] < 10) echo '(Low Stock Warning)'; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Seller History -->
                                <div class="p-3 bg-white rounded-3 border">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="seller-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['business_name'] ?: $row['first_name'].' '.$row['last_name']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($row['email']); ?> &bull; Total Products: <?php echo $row['seller_total_products']; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seller Clearance Reason -->
                        <div class="mb-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Seller's Clearance Reason</label>
                            <div class="p-3 rounded-3 bg-light border-start border-4 border-primary">
                                <i class="fas fa-quote-left text-muted me-1"></i>
                                <span class="text-dark fw-medium">"<?php echo htmlspecialchars($row['reason'] ?: 'Clearance sale'); ?>"</span>
                            </div>
                        </div>

                        <!-- FAKE PRICE CHECK ALERT -->
                        <div class="mb-2">
                            <?php if($is_inflated): ?>
                                <div class="alert alert-warning border border-warning d-flex align-items-start gap-3 p-3 rounded-3 shadow-xs">
                                    <i class="fas fa-exclamation-triangle text-warning fs-3 mt-1"></i>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">⚠️ Potential Fake Discount Detected!</h6>
                                        <p class="mb-2 small text-dark">
                                            The seller listed an Original Price of <strong>Rs. <?php echo number_format($orig_price, 2); ?></strong>, but this product's regular catalog Base Price is <strong>Rs. <?php echo number_format($base_price, 2); ?></strong>.
                                            This represents an artificial price inflation of <span class="badge bg-danger text-white">+<?php echo $markup_pct; ?>%</span>.
                                        </p>
                                        <div class="small text-muted fst-italic">
                                            Recommendation: Check whether the seller artificially bumped the original price to make the -<?php echo $row['requested_discount']; ?>% deal appear larger than it actually is.
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success border border-success d-flex align-items-center gap-3 p-3 rounded-3 shadow-xs">
                                    <i class="fas fa-check-circle text-success fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">✅ Price Integrity Verified</h6>
                                        <div class="small text-muted">Original price Rs. <?php echo number_format($orig_price, 2); ?> matches regular catalog pricing (No artificial inflation detected).</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer border-top px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary fw-bold rounded-2 px-3" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-danger fw-bold rounded-2 px-3" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#<?php echo $reject_modal_id; ?>">
                            <i class="fas fa-times me-1"></i> Reject Deal...
                        </button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                            <input type="hidden" name="action" value="approve_hot_deal">
                            <button type="submit" class="btn btn-success fw-bold rounded-2 px-4">
                                <i class="fas fa-check me-1"></i> Approve & Put Live on Homepage
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="<?php echo $reject_modal_id; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);">
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                        <input type="hidden" name="action" value="reject_hot_deal">
                        <div class="modal-header border-bottom px-4 pt-4 pb-3">
                            <h5 class="modal-title fw-bold text-danger">
                                <i class="fas fa-times-circle me-1"></i> Reject Hot Deal Request
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="text-muted small mb-3">
                                Rejecting this request for <strong><?php echo htmlspecialchars($row['product_name']); ?></strong> will notify the seller. Please provide a constructive reason:
                            </p>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Rejection Reason *</label>
                                <textarea name="reject_reason" id="reject_reason_<?= $row['id'] ?>" rows="3" class="form-control" required><?php echo $is_inflated ? 'Original price appears artificially inflated compared to regular catalog price.' : 'Discount too low, need 15% min'; ?></textarea>
                            </div>

                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <span class="small text-muted w-100 mb-1">Quick presets:</span>
                                <button type="button" class="btn btn-sm btn-light border small text-muted" onclick="document.getElementById('reject_reason_<?= $row['id'] ?>').value = 'Discount too low, need 15% min';">Discount Too Low</button>
                                <button type="button" class="btn btn-sm btn-light border small text-muted" onclick="document.getElementById('reject_reason_<?= $row['id'] ?>').value = 'Original price artificially inflated over regular catalog price.';">Inflated Price</button>
                                <button type="button" class="btn btn-sm btn-light border small text-muted" onclick="document.getElementById('reject_reason_<?= $row['id'] ?>').value = 'Insufficient stock (minimum 10 units required for homepage feature).';">Low Stock</button>
                            </div>
                        </div>
                        <div class="modal-footer border-top px-4 pb-4">
                            <button type="button" class="btn btn-outline-secondary fw-bold rounded-2 px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger fw-bold rounded-2 px-4">
                                Confirm Rejection & Notify Seller
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Active Deals Expiry Edit Modals -->
    <?php if(!empty($active_hd_rows)): ?>
        <?php foreach($active_hd_rows as $row): 
            $modal_id = "editExpiryModal_" . $row['id'];
            $current_expiry = !empty($row['hot_deal_expiry']) ? date('Y-m-d', strtotime($row['hot_deal_expiry'])) : date('Y-m-d', strtotime('+3 days'));
        ?>
        <div class="modal fade" id="<?php echo $modal_id; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);">
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="action" value="update_hot_deal_expiry">
                        <div class="modal-header border-bottom px-4 pt-4 pb-3">
                            <h5 class="modal-title fw-bold text-dark">
                                <i class="fas fa-calendar-alt text-primary me-2"></i> Update Hot Deal Expiry
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="text-muted small mb-3">Adjust homepage feature expiration for <strong><?php echo htmlspecialchars($row['name']); ?></strong>.</p>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">New Expiration Date</label>
                                <input type="date" name="new_expiry" value="<?php echo $current_expiry; ?>" min="<?php echo date('Y-m-d'); ?>" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer border-top px-4 pb-4">
                            <button type="button" class="btn btn-outline-secondary fw-bold rounded-2 px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary fw-bold rounded-2 px-4">Save Expiry Date</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        .w-20px { width: 20px; text-align: center; }
        .font-size-14 { font-size: 14px; }
        /* Pagination custom */
        .page-link { color: #475569; font-weight: 500; border-color: #e2e8f0; }
        .page-link:hover { background: #f1f5f9; color: #0f172a; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openVariantsModal(productId) {
        const modal = new bootstrap.Modal(document.getElementById('variantsModal'));
        modal.show();
        
        const body = document.getElementById('variantsModalBody');
        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color: #6c5ce7;"></div><div class="mt-2 text-muted">Loading matrix...</div></div>';
        
        // Fetch matrix via AJAX
        fetch(`ajax_get_variant_matrix.php?id=${productId}`)
            .then(res => res.text())
            .then(html => {
                body.innerHTML = html;
                attachInlineEditListeners();
            })
            .catch(err => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load variants.</div>`;
            });
    }

    function attachInlineEditListeners() {
        const editableCells = document.querySelectorAll('.editable-qty, .editable-price');
        
        editableCells.forEach(cell => {
            cell.addEventListener('dblclick', function() {
                if(this.querySelector('input')) return;
                
                const currentVal = this.innerText.replace('Rs.', '').replace(/,/g, '').trim();
                const variantId = this.getAttribute('data-id');
                const field = this.classList.contains('editable-price') ? 'price' : 'qty';
                
                const input = document.createElement('input');
                input.type = 'number';
                input.value = currentVal;
                input.className = 'form-control form-control-sm text-center';
                input.style.width = field === 'price' ? '100px' : '70px';
                input.style.margin = '0 auto';
                
                this.innerHTML = '';
                this.appendChild(input);
                input.focus();
                
                const save = () => {
                    const newVal = input.value;
                    // Update UI optimistically
                    if (field === 'price') {
                        cell.innerHTML = 'Rs. ' + parseFloat(newVal).toLocaleString('en-US', {minimumFractionDigits: 2});
                    } else {
                        cell.innerHTML = newVal;
                        cell.className = 'editable-qty ' + (newVal == 0 ? 'text-danger fw-bold' : (newVal < 5 ? 'text-warning fw-bold' : 'text-success fw-bold'));
                    }
                    
                    // AJAX Save
                    const formData = new FormData();
                    formData.append('variant_id', variantId);
                    formData.append('field', field);
                    formData.append('value', newVal);
                    formData.append('action', 'update_matrix_field');
                    
                    fetch('update_variant_ajax.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => { if(!d.success) alert(d.message); })
                        .catch(e => console.error(e));
                };
                
                input.addEventListener('blur', save);
                input.addEventListener('keypress', e => { if (e.key === 'Enter') { input.blur(); } });
            });
        });
    }
    </script>
</body>
</html>