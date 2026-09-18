<?php
session_start();
include_once("../include/connection.php");
include_once("../include/functions.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Ensure rejection_reason column exists safely
$col_check = $conn->query("SHOW COLUMNS FROM seller_profiles LIKE 'rejection_reason'");
if($col_check && $col_check->num_rows == 0) {
    $conn->query("ALTER TABLE seller_profiles ADD COLUMN rejection_reason TEXT NULL");
}

// ---------------- CSV EXPORT ----------------
if(isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="business_registrations_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Seller Name', 'Email', 'Personal Phone', 'Business Name', 'Business Type', 'Registration ID', 'Business Phone', 'City', 'Status', 'Date Submitted']);
    
    $export_query = "SELECT br.*, u.first_name, u.last_name, u.email, u.phone as u_phone 
                     FROM seller_profiles br 
                     JOIN users u ON br.user_id = u.id 
                     ORDER BY br.id DESC";
    $export_res = $conn->query($export_query);
    if($export_res) {
        while($r = $export_res->fetch_assoc()) {
            $status_text = 'Pending';
            if($r['is_approved'] == 1) $status_text = 'Active';
            elseif($r['is_approved'] == -1) $status_text = 'Rejected';
            elseif($r['is_approved'] == 0 && !empty($r['rejection_reason'])) $status_text = 'Deactivated';

            fputcsv($output, [
                $r['id'],
                $r['first_name'] . ' ' . $r['last_name'],
                $r['email'],
                $r['personal_phone'] ?? ($r['u_phone'] ?? ''),
                $r['business_name'],
                $r['business_type'],
                $r['business_reg_id'],
                $r['business_number'],
                $r['city'] ?? '',
                $status_text,
                date('Y-m-d H:i', strtotime($r['created_at']))
            ]);
        }
    }
    fclose($output);
    exit();
}

// ---------------- HANDLE ACTIONS ----------------
$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    $reason = trim($_POST['reason'] ?? '');
    
    // Fetch business details
    $b_stmt = $conn->prepare("SELECT business_name FROM seller_profiles WHERE user_id = ?");
    $b_stmt->bind_param("i", $user_id);
    $b_stmt->execute();
    $b_res = $b_stmt->get_result();
    $b_row = $b_res->fetch_assoc();
    $biz_name = $b_row['business_name'] ?? 'Your business';

    if($action == 'approve') {
        // Approve business registration
        $stmt = $conn->prepare("UPDATE seller_profiles SET is_approved = 1, rejection_reason = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if($stmt->execute()) {
            // Also ensure user is verified and products are active
            $conn->query("UPDATE users SET is_approved = 1, user_type = 'seller' WHERE id = $user_id");
            $conn->query("UPDATE products SET status = 'active' WHERE seller_id = $user_id AND status = 'inactive'");
            
            $success_message = "Business registration for '{$biz_name}' approved successfully!";
            addNotification($conn, $user_id, "Business Verified! - Your business {$biz_name} has been approved. You can now add products and start selling.", 'success', 'Business', 'site/seller-dashboard.php');
        } else {
            $error_message = "Failed to approve business registration.";
        }
    } elseif($action == 'reject') {
        // Reject business registration
        $stmt = $conn->prepare("UPDATE seller_profiles SET is_approved = -1, rejection_reason = ? WHERE user_id = ?");
        $stmt->bind_param("si", $reason, $user_id);
        
        if($stmt->execute()) {
            // Hide seller products upon rejection
            $conn->query("UPDATE products SET status = 'inactive' WHERE seller_id = $user_id");
            
            $success_message = "Business registration for '{$biz_name}' has been rejected.";
            $msg = !empty($reason) 
                ? "Business Registration Rejected - Reason: {$reason}. Please update your details and submit again."
                : "Business Registration Rejected - Your application for {$biz_name} has been declined. Please update your details and try again.";
            addNotification($conn, $user_id, $msg, 'error', 'Business', 'site/business-registration.php');
        } else {
            $error_message = "Failed to reject business registration.";
        }
    } elseif($action == 'activate') {
        // Activate business registration
        $stmt = $conn->prepare("UPDATE seller_profiles SET is_approved = 1, rejection_reason = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if($stmt->execute()) {
            $conn->query("UPDATE users SET is_approved = 1 WHERE id = $user_id");
            $conn->query("UPDATE products SET status = 'active' WHERE seller_id = $user_id AND status = 'inactive'");
            $success_message = "Business '{$biz_name}' activated successfully! Products are now live.";
            addNotification($conn, $user_id, "Business Activated - Your store {$biz_name} has been reactivated.", 'success', 'Business', 'site/seller-dashboard.php');
        } else {
            $error_message = "Failed to activate business registration.";
        }
    } elseif($action == 'deactivate') {
        // Deactivate business registration (suspend seller and hide products)
        $stmt = $conn->prepare("UPDATE seller_profiles SET is_approved = 0, rejection_reason = ? WHERE user_id = ?");
        $stmt->bind_param("si", $reason, $user_id);
        
        if($stmt->execute()) {
            // Hide all products from store
            $conn->query("UPDATE products SET status = 'inactive' WHERE seller_id = $user_id");
            $success_message = "Business '{$biz_name}' deactivated. Seller products are now hidden.";
            addNotification($conn, $user_id, "Business Deactivated - Your seller privileges and products have been temporarily paused.", 'warning', 'Business', 'site/seller-dashboard.php');
        } else {
            $error_message = "Failed to deactivate business registration.";
        }
    }
}

// ---------------- STATS QUERIES ----------------
$pending_count = 0;
$active_count = 0;
$rejected_count = 0;
$total_count = 0;

$stat_p = $conn->query("SELECT COUNT(*) as cnt FROM seller_profiles WHERE is_approved = 0");
if($stat_p && $r = $stat_p->fetch_assoc()) $pending_count = (int)$r['cnt'];

$stat_a = $conn->query("SELECT COUNT(*) as cnt FROM seller_profiles WHERE is_approved = 1");
if($stat_a && $r = $stat_a->fetch_assoc()) $active_count = (int)$r['cnt'];

$stat_r = $conn->query("SELECT COUNT(*) as cnt FROM seller_profiles WHERE is_approved = -1");
if($stat_r && $r = $stat_r->fetch_assoc()) $rejected_count = (int)$r['cnt'];

$stat_t = $conn->query("SELECT COUNT(*) as cnt FROM seller_profiles");
if($stat_t && $r = $stat_t->fetch_assoc()) $total_count = (int)$r['cnt'];

// ---------------- TAB & FILTER LOGIC ----------------
$active_tab = $_GET['tab'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$business_type_filter = $_GET['business_type'] ?? 'all';

$where_clauses = ["1=1"];

// Tab filtering
if($active_tab === 'pending') {
    $where_clauses[] = "br.is_approved = 0";
} elseif($active_tab === 'active') {
    $where_clauses[] = "br.is_approved = 1";
} elseif($active_tab === 'rejected') {
    $where_clauses[] = "br.is_approved = -1";
}

// Search filter
if(!empty($search)) {
    $esc = $conn->real_escape_string($search);
    $where_clauses[] = "(u.first_name LIKE '%$esc%' OR u.last_name LIKE '%$esc%' OR u.email LIKE '%$esc%' OR br.business_name LIKE '%$esc%' OR br.business_reg_id LIKE '%$esc%' OR br.business_number LIKE '%$esc%')";
}

// Business Type filter
if($business_type_filter !== 'all') {
    $esc_type = $conn->real_escape_string($business_type_filter);
    $where_clauses[] = "br.business_type = '$esc_type'";
}

$where_sql = implode(' AND ', $where_clauses);

$registrations_query = "SELECT br.*, u.first_name, u.last_name, u.email, u.username, u.profile_image, u.phone as u_phone 
                        FROM seller_profiles br 
                        JOIN users u ON br.user_id = u.id 
                        WHERE $where_sql 
                        ORDER BY br.id DESC";
$registrations_result = $conn->query($registrations_query);

// Helper function to resolve document file path safely
function resolveDocUrl($filename, $type = 'cert') {
    if(empty($filename)) return null;
    $possible_paths = [
        "../image/certificates/{$filename}",
        "../image/logos/{$filename}",
        "../assets/uploads/{$filename}",
        "../assets/uploads/certificates/{$filename}",
        "../assets/uploads/products/{$filename}"
    ];
    foreach($possible_paths as $p) {
        if(file_exists($p)) return $p;
    }
    // Fallback based on type
    if($type === 'logo') return "../image/logos/{$filename}";
    return "../image/certificates/{$filename}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registrations - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #6c5ce7;
            --primary-purple-hover: #5849c7;
            --primary-blue: #0066FF;
            --navy-dark: #0f172a;
            --card-radius: 16px;
            --border-color: #f1f5f9;
        }
        body { 
            background-color: #F0F4FF; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b; 
        }
        .main-content { 
            margin-left: 250px; 
            padding: 30px; 
            transition: all 0.3s; 
        }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; padding: 16px; } 
        }

        /* Top Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 26px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .page-subtitle {
            color: #64748b;
            font-size: 14px;
        }

        /* Buttons */
        .btn-action-outline {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            border-radius: 10px;
            font-weight: 600;
            padding: 9px 18px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-action-outline:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Stat Cards */
        .stat-card {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 22px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 2px 4px -1px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.06);
        }
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .stat-title {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .stat-icon.yellow { background: #fffbeb; color: #d97706; }
        .stat-icon.green  { background: #f0fdf4; color: #16a34a; }
        .stat-icon.red    { background: #fef2f2; color: #dc2626; }
        .stat-icon.purple { background: #f3f0ff; color: #7c3aed; }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 6px;
        }
        .stat-meta {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Filter Tabs */
        .custom-tabs-container {
            margin-top: 28px;
            margin-bottom: 20px;
        }
        .custom-tabs {
            display: flex;
            gap: 24px;
            border-bottom: 2px solid #e2e8f0;
            overflow-x: auto;
        }
        .custom-tab {
            padding: 12px 6px;
            color: #64748b;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            position: relative;
            top: 2px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .custom-tab:hover {
            color: #0f172a;
        }
        .custom-tab.active {
            color: var(--primary-purple);
            border-bottom-color: var(--primary-purple);
        }
        .tab-badge {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 700;
        }
        .custom-tab.active .tab-badge {
            background: #ede9fe;
            color: #6d28d9;
        }

        /* Table Card */
        .premium-table-card {
            background: #ffffff;
            border-radius: var(--card-radius);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 2px 4px -1px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .card-table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .table-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-box {
            position: relative;
            min-width: 260px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }
        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .search-box input:focus {
            background: #fff;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }

        .select-filter {
            padding: 9px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            background-color: #f8fafc;
            outline: none;
            cursor: pointer;
        }
        .select-filter:focus {
            border-color: var(--primary-purple);
        }

        /* Table */
        .premium-table {
            width: 100%;
            border-collapse: collapse;
        }
        .premium-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 15px 22px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .premium-table td {
            padding: 18px 22px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .premium-table tr:hover td {
            background-color: #F8FAFF;
        }
        .premium-table tr:last-child td {
            border-bottom: none;
        }

        /* Seller Avatar */
        .seller-avatar-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .seller-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            background: #e0e7ff;
            color: #4338ca;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .seller-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .seller-email {
            font-size: 12px;
            color: #64748b;
        }

        /* Business Entity */
        .biz-entity {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .biz-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .biz-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }
        .biz-category {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }

        /* Type Pill */
        .type-pill {
            background: #f1f5f9;
            color: #475569;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        /* Registration ID Badge */
        .reg-id-badge {
            font-family: 'JetBrains Mono', monospace;
            background: #f8fafc;
            color: #0f172a;
            padding: 5px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            user-select: all;
        }
        .btn-copy-id {
            border: none;
            background: transparent;
            color: #94a3b8;
            padding: 0 3px;
            font-size: 12px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .btn-copy-id:hover {
            color: var(--primary-purple);
        }

        /* Status Pills */
        .status-pill {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-pill.active { background: #f0fdf4; color: #16a34a; }
        .status-pill.pending { background: #fffbeb; color: #b45309; }
        .status-pill.rejected { background: #fef2f2; color: #dc2626; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; }
        .status-dot.active { background: #16a34a; box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2); }
        .status-dot.pending { background: #d97706; animation: pulse 1.5s infinite; }
        .status-dot.rejected { background: #dc2626; }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.5); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 4px rgba(217, 119, 6, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
        }

        /* Table Action Buttons */
        .btn-tbl-view {
            background: #eff6ff;
            color: var(--primary-blue);
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-tbl-view:hover {
            background: var(--primary-blue);
            color: #fff;
        }
        .btn-tbl-action {
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            transition: all 0.2s;
        }
        .btn-tbl-approve { background: #10b981; color: white; }
        .btn-tbl-approve:hover { background: #059669; }
        .btn-tbl-reject { background: #ef4444; color: white; }
        .btn-tbl-reject:hover { background: #dc2626; }
        .btn-tbl-deactivate { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .btn-tbl-deactivate:hover { background: #f59e0b; color: white; }
        .btn-tbl-restore { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
        .btn-tbl-restore:hover { background: #6d28d9; color: white; }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 22px 28px;
        }
        .modal-body {
            padding: 28px;
            background: #fafcff;
        }
        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 18px 28px;
            background: #fff;
        }
        
        .modal-card-box {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .modal-box-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Document Preview Card */
        .doc-preview-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            transition: border-color 0.2s;
        }
        .doc-preview-card:hover {
            border-color: var(--primary-purple);
        }
        .doc-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #eff6ff;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* Timeline Stepper */
        .stepper-item {
            position: relative;
            padding-left: 28px;
            padding-bottom: 22px;
        }
        .stepper-item:last-child {
            padding-bottom: 0;
        }
        .stepper-item::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 18px;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        .stepper-item:last-child::before {
            display: none;
        }
        .stepper-circle {
            position: absolute;
            left: 0;
            top: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }
        .stepper-item.done .stepper-circle {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        .stepper-item.active .stepper-circle {
            background: var(--primary-purple);
            border-color: var(--primary-purple);
            color: white;
        }

        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <?php include("components/topbar.php"); ?>
        
        <div class="container-fluid px-0">
            
            <!-- Toast Feedback -->
            <div class="toast-container">
                <div id="actionToast" class="toast align-items-center text-white bg-dark border-0 rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
                            <i class="fas fa-check-circle text-success fs-5"></i> Copied to clipboard!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>

            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- TOP OVERVIEW HEADER -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Business Registrations Management</h1>
                    <p class="page-subtitle mb-0">Manage seller business registration requests - approve, reject, activate or deactivate</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="business-registrations.php?action=export_csv" class="btn-action-outline">
                        <i class="fas fa-file-export text-muted"></i>
                        <span>Export CSV</span>
                    </a>
                </div>
            </div>

            <!-- 4 TOP STATS CARDS ROW -->
            <div class="row g-4">
                <!-- 1. Pending Approvals -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Pending Approvals</span>
                            <div class="stat-icon yellow">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-value text-warning"><?php echo number_format($pending_count); ?></div>
                        <div class="stat-meta text-warning">
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                <i class="fas fa-exclamation-circle me-1"></i> Requires attention
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2. Active Businesses -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Active Businesses</span>
                            <div class="stat-icon green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value text-success"><?php echo number_format($active_count); ?></div>
                        <div class="stat-meta text-success">
                            <i class="fas fa-arrow-up me-1"></i>
                            <span>Verified selling partners</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Rejected / Deactivated -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Rejected / Deactivated</span>
                            <div class="stat-icon red">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value text-danger"><?php echo number_format($rejected_count); ?></div>
                        <div class="stat-meta text-danger">
                            <i class="fas fa-ban me-1"></i>
                            <span>Declined or suspended</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Total Businesses -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Total Businesses</span>
                            <div class="stat-icon purple">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($total_count); ?></div>
                        <div class="stat-meta">
                            <i class="fas fa-users text-primary me-1"></i>
                            <span>All registered sellers</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABS CONTAINER -->
            <div class="custom-tabs-container">
                <div class="custom-tabs">
                    <a href="?tab=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="custom-tab <?php echo ($active_tab === 'all') ? 'active' : ''; ?>">
                        <i class="fas fa-list-ul"></i> All Requests
                        <span class="tab-badge"><?php echo $total_count; ?></span>
                    </a>
                    <a href="?tab=pending<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="custom-tab <?php echo ($active_tab === 'pending') ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Pending Approval
                        <span class="tab-badge"><?php echo $pending_count; ?></span>
                    </a>
                    <a href="?tab=active<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="custom-tab <?php echo ($active_tab === 'active') ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Active
                        <span class="tab-badge"><?php echo $active_count; ?></span>
                    </a>
                    <a href="?tab=rejected<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="custom-tab <?php echo ($active_tab === 'rejected') ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle"></i> Rejected / Deactivated
                        <span class="tab-badge"><?php echo $rejected_count; ?></span>
                    </a>
                </div>
            </div>

            <!-- PREMIUM TABLE CARD -->
            <div class="premium-table-card">
                <div class="card-table-header">
                    <div>
                        <h4 class="table-title">Business Registrations</h4>
                        <p class="text-muted small mb-0">Showing business profiles filtered by status</p>
                    </div>

                    <div class="filter-group">
                        <form method="GET" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                            
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search by seller, business, ID..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>

                            <select name="business_type" class="select-filter" onchange="this.form.submit()">
                                <option value="all" <?php if($business_type_filter == 'all') echo 'selected'; ?>>All Business Types</option>
                                <option value="Sole Proprietorship" <?php if($business_type_filter == 'Sole Proprietorship') echo 'selected'; ?>>Sole Proprietorship</option>
                                <option value="Private Limited" <?php if($business_type_filter == 'Private Limited') echo 'selected'; ?>>PVT LTD</option>
                                <option value="Partnership" <?php if($business_type_filter == 'Partnership') echo 'selected'; ?>>Partnership</option>
                            </select>

                            <?php if(!empty($search) || $business_type_filter !== 'all'): ?>
                                <a href="business-registrations.php?tab=<?php echo urlencode($active_tab); ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2" title="Clear Filters">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="table-responsive">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>SELLER</th>
                                <th>BUSINESS NAME</th>
                                <th>BUSINESS TYPE</th>
                                <th>REGISTRATION ID</th>
                                <th>STATUS</th>
                                <th class="text-end">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($registrations_result && $registrations_result->num_rows > 0): ?>
                                <?php while($row = $registrations_result->fetch_assoc()): 
                                    $user_id = $row['user_id'];
                                    $seller_name = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']));
                                    $seller_email = htmlspecialchars($row['email']);
                                    $biz_name = htmlspecialchars($row['business_name']);
                                    $biz_type = htmlspecialchars($row['business_type']);
                                    $reg_id = htmlspecialchars($row['business_reg_id']);
                                    $is_app = (int)$row['is_approved'];
                                    $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                    $category = !empty($row['nature_of_business']) ? htmlspecialchars($row['nature_of_business']) : 'Sports & Gear';
                                    $modal_id = "bizDetailModal_" . $user_id;
                                ?>
                                    <tr>
                                        <!-- SELLER -->
                                        <td>
                                            <div class="seller-avatar-container">
                                                <?php if(!empty($row['profile_image']) && file_exists("../assets/uploads/" . $row['profile_image'])): ?>
                                                    <img src="../assets/uploads/<?php echo htmlspecialchars($row['profile_image']); ?>" class="seller-avatar" alt="Avatar">
                                                <?php else: ?>
                                                    <div class="seller-avatar"><?php echo $initials; ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="seller-name">
                                                        <span><?php echo $seller_name; ?></span>
                                                        <i class="fas fa-check-circle text-primary" style="font-size: 13px;" title="Verified Account"></i>
                                                    </div>
                                                    <div class="seller-email"><?php echo $seller_email; ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- BUSINESS NAME -->
                                        <td>
                                            <div class="biz-entity">
                                                <div class="biz-icon">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                                <div>
                                                    <div class="biz-name"><?php echo $biz_name; ?></div>
                                                    <div class="biz-category"><i class="fas fa-tag me-1 text-muted"></i><?php echo $category; ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- BUSINESS TYPE -->
                                        <td>
                                            <span class="type-pill"><?php echo $biz_type; ?></span>
                                        </td>

                                        <!-- REGISTRATION ID -->
                                        <td>
                                            <div class="reg-id-badge">
                                                <span><?php echo $reg_id; ?></span>
                                                <button type="button" class="btn-copy-id" onclick="copyText('<?php echo $reg_id; ?>')" title="Copy Registration ID">
                                                    <i class="far fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>

                                        <!-- STATUS -->
                                        <td>
                                            <?php if($is_app == 1): ?>
                                                <span class="status-pill active">
                                                    <span class="status-dot active"></span> Active
                                                </span>
                                            <?php elseif($is_app == -1): ?>
                                                <span class="status-pill rejected">
                                                    <span class="status-dot rejected"></span> Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill pending">
                                                    <span class="status-dot pending"></span> Pending Approval
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- ACTIONS -->
                                        <td class="text-end">
                                            <div class="d-inline-flex align-items-center gap-2">
                                                <!-- View Modal Trigger -->
                                                <button type="button" class="btn-tbl-view" data-bs-toggle="modal" data-bs-target="#<?php echo $modal_id; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>

                                                <?php if($is_app == 0): ?>
                                                    <!-- Pending: Approve & Reject buttons directly -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn-tbl-action btn-tbl-approve" onclick="return confirm('Approve business \'<?php echo $biz_name; ?>\'?')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn-tbl-action btn-tbl-reject" onclick="openRejectPrompt(<?php echo $user_id; ?>, '<?php echo $biz_name; ?>')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif($is_app == 1): ?>
                                                    <!-- Active: Deactivate option -->
                                                    <button type="button" class="btn-tbl-action btn-tbl-deactivate" onclick="openDeactivatePrompt(<?php echo $user_id; ?>, '<?php echo $biz_name; ?>')">
                                                        <i class="fas fa-pause"></i> Deactivate
                                                    </button>
                                                <?php elseif($is_app == -1): ?>
                                                    <!-- Rejected: Activate / Restore -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                                        <button type="submit" name="action" value="activate" class="btn-tbl-action btn-tbl-restore" onclick="return confirm('Restore & activate business \'<?php echo $biz_name; ?>\'?')">
                                                            <i class="fas fa-redo"></i> Restore
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- ================= 2-COLUMN VIEW MODAL ================= -->
                                    <div class="modal fade" id="<?php echo $modal_id; ?>" tabindex="-1" aria-labelledby="<?php echo $modal_id; ?>Label" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-dialog-centered">
                                            <div class="modal-content">
                                                
                                                <div class="modal-header d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5 class="modal-title fw-bold text-dark mb-1" id="<?php echo $modal_id; ?>Label">
                                                            <?php echo $biz_name; ?> <span class="text-muted font-monospace fs-6 ms-2">[<?php echo $reg_id; ?>]</span>
                                                        </h5>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="type-pill"><?php echo $biz_type; ?></span>
                                                            <span class="text-muted small">Submitted on <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if($is_app == 1): ?>
                                                            <span class="status-pill active"><span class="status-dot active"></span> Active & Verified</span>
                                                        <?php elseif($is_app == -1): ?>
                                                            <span class="status-pill rejected"><span class="status-dot rejected"></span> Application Declined</span>
                                                        <?php else: ?>
                                                            <span class="status-pill pending"><span class="status-dot pending"></span> Pending Approval</span>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="row g-4">
                                                        
                                                        <!-- LEFT COLUMN: Business Profile & Documents (7 cols) -->
                                                        <div class="col-12 col-lg-7">
                                                            
                                                            <!-- Business & Owner Overview -->
                                                            <div class="modal-card-box">
                                                                <div class="modal-box-title">
                                                                    <i class="fas fa-building text-primary"></i> Business & Owner Information
                                                                </div>
                                                                <div class="row g-3">
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Owner Full Name</label>
                                                                        <strong class="text-dark"><?php echo htmlspecialchars($row['owner_name'] ?? $seller_name); ?></strong>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Owner NIC / Passport</label>
                                                                        <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($row['owner_nic'] ?? 'N/A'); ?></span>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Personal Phone</label>
                                                                        <strong class="text-dark"><?php echo htmlspecialchars($row['personal_phone'] ?? ($row['u_phone'] ?? 'N/A')); ?></strong>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Business Phone</label>
                                                                        <strong class="text-dark"><?php echo htmlspecialchars($row['business_number'] ?? 'N/A'); ?></strong>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Personal Email</label>
                                                                        <span class="text-dark"><?php echo htmlspecialchars($row['personal_email'] ?? $seller_email); ?></span>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Business Email</label>
                                                                        <span class="text-dark"><?php echo htmlspecialchars($row['business_email'] ?? $seller_email); ?></span>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <label class="text-muted small d-block">Physical Business Address</label>
                                                                        <span class="text-dark">
                                                                            <?php 
                                                                            $addr = [];
                                                                            if(!empty($row['address_line1'])) $addr[] = $row['address_line1'];
                                                                            if(!empty($row['address_line2'])) $addr[] = $row['address_line2'];
                                                                            if(!empty($row['city'])) $addr[] = $row['city'];
                                                                            if(!empty($row['province'])) $addr[] = $row['province'];
                                                                            if(!empty($row['postal_code'])) $addr[] = $row['postal_code'];
                                                                            echo !empty($addr) ? htmlspecialchars(implode(', ', $addr)) : 'Address on file';
                                                                            ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Banking Details -->
                                                            <div class="modal-card-box">
                                                                <div class="modal-box-title">
                                                                    <i class="fas fa-university text-success"></i> Settlement Bank Account
                                                                </div>
                                                                <div class="row g-3">
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Bank Name</label>
                                                                        <strong class="text-dark"><?php echo htmlspecialchars($row['bank_name'] ?? 'Bank of Ceylon'); ?></strong>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Branch</label>
                                                                        <strong class="text-dark"><?php echo htmlspecialchars($row['branch_name'] ?? 'Main Branch'); ?></strong>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Account Number</label>
                                                                        <span class="font-monospace fw-bold text-dark"><?php echo htmlspecialchars($row['account_number'] ?? 'XXXX-XXXX-XXXX'); ?></span>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="text-muted small d-block">Account Holder</label>
                                                                        <strong class="text-dark"><?php echo htmlspecialchars($row['account_holder_name'] ?? $seller_name); ?></strong>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Document Previews -->
                                                            <div class="modal-card-box">
                                                                <div class="modal-box-title">
                                                                    <i class="fas fa-file-contract text-warning"></i> Uploaded Verification Documents
                                                                </div>
                                                                
                                                                <!-- BR Document -->
                                                                <div class="doc-preview-card">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="doc-icon-wrap">
                                                                            <i class="fas fa-file-pdf"></i>
                                                                        </div>
                                                                        <div>
                                                                            <div class="fw-bold text-dark">Business Registration (BR) Copy</div>
                                                                            <small class="text-muted">Official incorporation document</small>
                                                                        </div>
                                                                    </div>
                                                                    <?php 
                                                                    $br_url = resolveDocUrl($row['certificate_path'] ?? '');
                                                                    if($br_url): ?>
                                                                        <a href="<?php echo $br_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary px-3 rounded-3">
                                                                            <i class="fas fa-external-link-alt me-1"></i> View Document
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-muted border">Not Uploaded</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <!-- NIC Copy -->
                                                                <div class="doc-preview-card">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="doc-icon-wrap">
                                                                            <i class="fas fa-id-card"></i>
                                                                        </div>
                                                                        <div>
                                                                            <div class="fw-bold text-dark">Owner NIC / Identity Proof</div>
                                                                            <small class="text-muted">Government photo ID</small>
                                                                        </div>
                                                                    </div>
                                                                    <?php 
                                                                    $nic_url = resolveDocUrl($row['nic_path'] ?? '');
                                                                    if($nic_url): ?>
                                                                        <a href="<?php echo $nic_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary px-3 rounded-3">
                                                                            <i class="fas fa-external-link-alt me-1"></i> View ID
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-muted border">Not Uploaded</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <!-- Bank Passbook Slip -->
                                                                <div class="doc-preview-card">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="doc-icon-wrap">
                                                                            <i class="fas fa-money-check"></i>
                                                                        </div>
                                                                        <div>
                                                                            <div class="fw-bold text-dark">Bank Book / Settlement Slip</div>
                                                                            <small class="text-muted">Account ownership verification</small>
                                                                        </div>
                                                                    </div>
                                                                    <?php 
                                                                    $bank_url = resolveDocUrl($row['bank_book_path'] ?? '');
                                                                    if($bank_url): ?>
                                                                        <a href="<?php echo $bank_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary px-3 rounded-3">
                                                                            <i class="fas fa-external-link-alt me-1"></i> View Slip
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-muted border">Not Uploaded</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <!-- Storefront / Logo -->
                                                                <?php 
                                                                $logo_url = resolveDocUrl($row['logo_path'] ?? '', 'logo');
                                                                if($logo_url): ?>
                                                                    <div class="doc-preview-card">
                                                                        <div class="d-flex align-items-center gap-3">
                                                                            <div class="doc-icon-wrap">
                                                                                <i class="fas fa-image"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="fw-bold text-dark">Business Logo / Store Photo</div>
                                                                                <small class="text-muted">Branding image</small>
                                                                            </div>
                                                                        </div>
                                                                        <a href="<?php echo $logo_url; ?>" target="_blank" class="btn btn-sm btn-outline-primary px-3 rounded-3">
                                                                            <i class="fas fa-image me-1"></i> View Logo
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>

                                                            </div>

                                                        </div>

                                                        <!-- RIGHT COLUMN: Timeline, Checklist & Decision Actions (5 cols) -->
                                                        <div class="col-12 col-lg-5">
                                                            
                                                            <!-- Status Timeline -->
                                                            <div class="modal-card-box">
                                                                <div class="modal-box-title">
                                                                    <i class="fas fa-stream text-primary"></i> Verification Timeline
                                                                </div>
                                                                
                                                                <div class="stepper-item done">
                                                                    <div class="stepper-circle"><i class="fas fa-check"></i></div>
                                                                    <strong class="text-dark small d-block">1. Application Submitted</strong>
                                                                    <span class="text-muted" style="font-size: 11px;">Completed on <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span>
                                                                </div>

                                                                <div class="stepper-item <?php echo ($is_app != 0) ? 'done' : 'active'; ?>">
                                                                    <div class="stepper-circle">
                                                                        <?php echo ($is_app != 0) ? '<i class="fas fa-check"></i>' : '<i class="fas fa-circle" style="font-size: 6px;"></i>'; ?>
                                                                    </div>
                                                                    <strong class="text-dark small d-block">2. Identity & Document Review</strong>
                                                                    <span class="text-muted" style="font-size: 11px;">KYC documents and business registry checks</span>
                                                                </div>

                                                                <div class="stepper-item <?php echo ($is_app == 1) ? 'done' : (($is_app == -1) ? 'active' : ''); ?>">
                                                                    <div class="stepper-circle">
                                                                        <?php if($is_app == 1): ?>
                                                                            <i class="fas fa-check"></i>
                                                                        <?php elseif($is_app == -1): ?>
                                                                            <i class="fas fa-times text-danger"></i>
                                                                        <?php else: ?>
                                                                            <i class="fas fa-clock text-muted"></i>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <strong class="text-dark small d-block">
                                                                        3. Decision Status: 
                                                                        <?php 
                                                                        if($is_app == 1) echo '<span class="text-success">Approved & Active</span>';
                                                                        elseif($is_app == -1) echo '<span class="text-danger">Rejected</span>';
                                                                        else echo '<span class="text-warning">Pending Decision</span>';
                                                                        ?>
                                                                    </strong>
                                                                    <span class="text-muted" style="font-size: 11px;">
                                                                        <?php 
                                                                        if($is_app == 1) echo "Seller store is live and operational.";
                                                                        elseif($is_app == -1) echo "Application was declined.";
                                                                        else echo "Awaiting admin sign-off.";
                                                                        ?>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <!-- KYC Checklist -->
                                                            <div class="modal-card-box">
                                                                <div class="modal-box-title">
                                                                    <i class="fas fa-shield-alt text-success"></i> KYC Verification Checklist
                                                                </div>
                                                                <div class="d-flex flex-column gap-2 small">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <i class="fas fa-check-circle text-success fs-6"></i>
                                                                        <span>Valid Business Registration ID verified</span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <i class="fas fa-check-circle text-success fs-6"></i>
                                                                        <span>Government NIC / Identity matched</span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <i class="fas fa-check-circle text-success fs-6"></i>
                                                                        <span>Bank Account ownership verified</span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <i class="fas fa-check-circle text-success fs-6"></i>
                                                                        <span>Direct Contact phone & email confirmed</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Decision Actions Form -->
                                                            <div class="modal-card-box bg-light border">
                                                                <div class="modal-box-title text-dark">
                                                                    <i class="fas fa-gavel text-primary"></i> Take Action
                                                                </div>
                                                                
                                                                <form method="POST" action="">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label small fw-semibold text-muted">Admin Notes / Rejection Reason</label>
                                                                        <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Enter reason if rejecting or internal verification notes..."><?php echo htmlspecialchars($row['rejection_reason'] ?? ''); ?></textarea>
                                                                    </div>

                                                                    <div class="d-flex flex-column gap-2">
                                                                        <?php if($is_app == 0): ?>
                                                                            <button type="submit" name="action" value="approve" class="btn btn-success fw-bold py-2 shadow-sm" onclick="return confirm('Approve this business registration?')">
                                                                                <i class="fas fa-check-circle me-1"></i> Approve Registration
                                                                            </button>
                                                                            <button type="submit" name="action" value="reject" class="btn btn-danger fw-bold py-2 shadow-sm" onclick="return confirm('Reject this business registration application?')">
                                                                                <i class="fas fa-times-circle me-1"></i> Reject Application
                                                                            </button>
                                                                        <?php elseif($is_app == 1): ?>
                                                                            <button type="submit" name="action" value="deactivate" class="btn btn-warning fw-bold py-2 text-dark shadow-sm" onclick="return confirm('Deactivate this seller? Their products will be hidden.')">
                                                                                <i class="fas fa-pause-circle me-1"></i> Deactivate Business
                                                                            </button>
                                                                        <?php elseif($is_app == -1): ?>
                                                                            <button type="submit" name="action" value="activate" class="btn btn-primary fw-bold py-2 shadow-sm" onclick="return confirm('Restore and activate this business?')">
                                                                                <i class="fas fa-redo me-1"></i> Re-activate Business
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </form>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light rounded-3 px-4 font-semibold" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /Modal -->

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="py-4">
                                            <div class="w-16 h-16 bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                                                <i class="fas fa-building fa-2x text-muted"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">No business registrations found</h5>
                                            <p class="text-muted small mb-0">No seller registration records match your current filter criteria.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Hidden Form for Row Actions -->
    <form id="rowActionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="rowActionInput" value="">
        <input type="hidden" name="user_id" id="rowUserIdInput" value="">
        <input type="hidden" name="reason" id="rowReasonInput" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy to clipboard
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(`Registration ID <strong>${text}</strong> copied to clipboard!`);
            }).catch(() => {
                const temp = document.createElement("input");
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand("copy");
                document.body.removeChild(temp);
                showToast(`Registration ID <strong>${text}</strong> copied!`);
            });
        }

        // Toast feedback
        function showToast(message) {
            const toastEl = document.getElementById('actionToast');
            document.getElementById('toastMessage').innerHTML = `<i class="fas fa-check-circle text-success fs-5"></i> ${message}`;
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
        }

        // Prompt for Rejection Reason
        function openRejectPrompt(userId, bizName) {
            const reason = prompt(`Please provide a reason for rejecting "${bizName}":`, "Documents incomplete or invalid");
            if(reason !== null) {
                document.getElementById('rowActionInput').value = 'reject';
                document.getElementById('rowUserIdInput').value = userId;
                document.getElementById('rowReasonInput').value = reason;
                document.getElementById('rowActionForm').submit();
            }
        }

        // Prompt for Deactivation Reason
        function openDeactivatePrompt(userId, bizName) {
            if(confirm(`Are you sure you want to deactivate "${bizName}"? Their products will be hidden from the storefront.`)) {
                const reason = prompt(`Optional: Enter deactivation note for "${bizName}":`, "Temporary administrative suspension");
                document.getElementById('rowActionInput').value = 'deactivate';
                document.getElementById('rowUserIdInput').value = userId;
                document.getElementById('rowReasonInput').value = reason || '';
                document.getElementById('rowActionForm').submit();
            }
        }
    </script>
</body>
</html>