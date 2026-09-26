<?php
// admin/manage-orders.php - Enterprise Order Management System (Shopify Plus / Amazon Seller Central Grade)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../include/connection.php");


// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// -------------------------------------------------------------
// 1. STATS CALCULATION (Independent of active filters)
// -------------------------------------------------------------
$statsQuery = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'PACKED' THEN 1 ELSE 0 END) as packed_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'SHIPPED' THEN 1 ELSE 0 END) as shipped_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'OUT_FOR_DELIVERY' THEN 1 ELSE 0 END) as out_for_delivery_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'DELIVERED' THEN 1 ELSE 0 END) as delivered_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'RETURN_WINDOW' THEN 1 ELSE 0 END) as return_window_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN UPPER(TRIM(status)) = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(CASE WHEN UPPER(TRIM(status)) IN ('FAILED', 'FAILED_PAYMENT') THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE 
            WHEN (UPPER(payment_method) = 'COD' OR UPPER(payment_method) LIKE '%COD%') 
                 AND COALESCE(cod_collected, 0) = 0 
                 AND UPPER(TRIM(status)) != 'CANCELLED' 
            THEN total_amount ELSE 0 
        END) as cod_pending_amount
    FROM orders
";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt ? $statsStmt->fetch(PDO::FETCH_ASSOC) : [];

$totalOrders        = (int)($stats['total_orders'] ?? 0);
$pendingOrders      = (int)($stats['pending_count'] ?? 0);
$confirmedOrders    = (int)($stats['confirmed_count'] ?? 0);
$packedOrders       = (int)($stats['packed_count'] ?? 0);
$shippedOrders      = (int)($stats['shipped_count'] ?? 0);
$outDeliveryOrders  = (int)($stats['out_for_delivery_count'] ?? 0);
$deliveredOrders    = (int)($stats['delivered_count'] ?? 0);
$returnWindowOrders = (int)($stats['return_window_count'] ?? 0);
$completedOrders    = (int)($stats['completed_count'] ?? 0);
$cancelledOrders    = (int)($stats['cancelled_count'] ?? 0);
$failedOrders       = (int)($stats['failed_count'] ?? 0);
$codPendingAmount   = (float)($stats['cod_pending_amount'] ?? 0);

// Fetch distinct sellers for the dropdown filter
$sellersQuery = "
    SELECT DISTINCT COALESCE(sp.business_name, CONCAT(u.first_name, ' ', u.last_name)) as business_name
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    WHERE u.user_type = 'seller' AND (sp.business_name IS NOT NULL OR u.first_name IS NOT NULL)
    ORDER BY business_name ASC
";
$sellersList = $pdo->query($sellersQuery)->fetchAll(PDO::FETCH_COLUMN);

// Helper to resolve product image paths correctly from assets/uploads/products
function getOrderProductImg($path) {
    if (empty($path)) return '';
    $path = trim($path);
    if (strpos($path, 'http') === 0) return $path;
    $clean = ltrim($path, '/');
    if (strpos($clean, 'assets/') === 0 || strpos($clean, 'image/') === 0) {
        return '../' . $clean;
    }
    return '../assets/uploads/products/' . $clean;
}

// -------------------------------------------------------------
// 2. PARSE FILTER PARAMETERS
// -------------------------------------------------------------
$searchParam        = trim($_GET['search'] ?? '');
$statusParam        = strtoupper(trim($_GET['status'] ?? ''));
$paymentMethodParam = strtoupper(trim($_GET['payment_method'] ?? ''));
$paymentStatusParam = strtoupper(trim($_GET['payment_status'] ?? ''));
$sellerParam        = trim($_GET['seller'] ?? '');
$dateFromParam      = trim($_GET['date_from'] ?? '');
$dateToParam        = trim($_GET['date_to'] ?? '');
$returnWindowParam  = trim($_GET['return_window'] ?? '');

$where = ["1=1"];
$params = [];

// Search filter
if ($searchParam !== '') {
    $where[] = "(
        ord.order_code LIKE ? OR 
        u.first_name LIKE ? OR 
        u.last_name LIKE ? OR 
        ua.full_name LIKE ? OR 
        COALESCE(ord.buyer_phone, ua.phone1, u.phone) LIKE ? OR 
        ord.tracking_number LIKE ? OR 
        ord.courier_company LIKE ? OR
        items_data.product_names LIKE ?
    )";
    $like = "%$searchParam%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
    }
}

// Order Status filter
if ($statusParam !== '' && $statusParam !== 'ALL') {
    if ($statusParam === 'COD_PENDING') {
        $where[] = "(UPPER(ord.payment_method) = 'COD' AND COALESCE(ord.cod_collected, 0) = 0)";
    } else {
        $where[] = "UPPER(TRIM(ord.status)) = ?";
        $params[] = $statusParam;
    }
}

// Payment Method filter (Only Card, KOKO, COD)
if ($paymentMethodParam !== '' && $paymentMethodParam !== 'ALL') {
    if ($paymentMethodParam === 'CARD') {
        $where[] = "(UPPER(ord.payment_method) = 'CARD' OR UPPER(ord.payment_method) = 'PAYHERE')";
    } elseif ($paymentMethodParam === 'KOKO') {
        $where[] = "UPPER(ord.payment_method) LIKE '%KOKO%'";
    } elseif ($paymentMethodParam === 'COD') {
        $where[] = "UPPER(ord.payment_method) = 'COD'";
    }
}

// Payment Status filter
if ($paymentStatusParam !== '' && $paymentStatusParam !== 'ALL') {
    if ($paymentStatusParam === 'PAID') {
        $where[] = "(UPPER(ord.payment_status) = 'PAID' OR UPPER(ord.status) IN ('CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'RETURN_WINDOW', 'COMPLETED'))";
    } elseif ($paymentStatusParam === 'PENDING_COLLECTION') {
        $where[] = "(UPPER(ord.payment_method) = 'COD' AND COALESCE(ord.cod_collected, 0) = 0)";
    } elseif ($paymentStatusParam === 'REFUND_PENDING') {
        $where[] = "UPPER(ord.payment_status) = 'REFUND_PENDING'";
    }
}


// Seller filter
if ($sellerParam !== '' && $sellerParam !== 'ALL') {
    $where[] = "items_data.seller_name LIKE ?";
    $params[] = "%$sellerParam%";
}

// Date Range filter
if ($dateFromParam !== '') {
    $where[] = "DATE(ord.created_at) >= ?";
    $params[] = $dateFromParam;
}
if ($dateToParam !== '') {
    $where[] = "DATE(ord.created_at) <= ?";
    $params[] = $dateToParam;
}

// Return Window filter
if ($returnWindowParam === 'active') {
    $where[] = "(ord.status = 'RETURN_WINDOW' OR (ord.return_window_ends IS NOT NULL AND ord.return_window_ends >= CURDATE()))";
} elseif ($returnWindowParam === 'expiring') {
    $where[] = "(ord.return_window_ends IS NOT NULL AND ord.return_window_ends >= CURDATE() AND DATEDIFF(ord.return_window_ends, CURDATE()) <= 3)";
} elseif ($returnWindowParam === 'expired') {
    $where[] = "(ord.return_window_ends IS NOT NULL AND ord.return_window_ends < CURDATE())";
}

$whereSQL = implode(" AND ", $where);

// -------------------------------------------------------------
// 3. FETCH FILTERED ORDERS
// -------------------------------------------------------------
$ordersQuery = "
    SELECT 
        ord.id, ord.order_code, ord.created_at, ord.total_amount, ord.subtotal, ord.delivery_fee,
        ord.status, ord.tracking_number, ord.courier_company,
        ord.payment_method, ord.payment_status, ord.cod_collected,
        ord.bank_slip_path, ord.return_window_ends, ord.internal_notes, ord.buyer_phone,
        ord.shipped_at, ord.packed_at, ord.delivered_at, ord.completed_at,
        u.first_name, u.last_name, u.email as user_email, u.phone as account_phone, u.profile_image,
        ua.full_name as shipping_name, ua.address_line1, ua.address_line2, ua.city, ua.province, ua.postal_code, ua.phone1,
        COALESCE(items_data.item_count, 1) as item_count,
        COALESCE(items_data.primary_product_name, 'Fitness Gear Item') as primary_product_name,
        COALESCE(items_data.primary_sku, CONCAT('SKU-', ord.id)) as primary_sku,
        COALESCE(items_data.primary_image, '') as primary_image,
        COALESCE(items_data.seller_name, 'Direct Seller') as seller_name,
        COALESCE(items_data.commission, ord.total_amount * 0.10) as commission,
        COALESCE(items_data.seller_earning, ord.total_amount * 0.90) as seller_earning
    FROM orders ord
    JOIN users u ON ord.user_id = u.id
    LEFT JOIN user_addresses ua ON ord.shipping_address_id = ua.id
    LEFT JOIN (
        SELECT 
            oi.order_id,
            COUNT(oi.id) as item_count,
            GROUP_CONCAT(DISTINCT COALESCE(p.name, 'Item') SEPARATOR ', ') as product_names,
            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(p.name, 'Product') ORDER BY oi.id ASC SEPARATOR '|||'), '|||', 1) as primary_product_name,
            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(p.product_code, CONCAT('PRD-00', p.id)) ORDER BY oi.id ASC SEPARATOR '|||'), '|||', 1) as primary_sku,
            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(
                NULLIF(oi.product_image, ''),
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1),
                ''
            ) ORDER BY oi.id ASC SEPARATOR '|||'), '|||', 1) as primary_image,
            COALESCE(GROUP_CONCAT(DISTINCT COALESCE(sp.business_name, CONCAT(u_sel.first_name, ' ', u_sel.last_name)) SEPARATOR ', '), 'Direct Seller') as seller_name,
            SUM(COALESCE(payout.admin_commission, oi.oxxa_fee, oi.profit * 0.10, oi.total_price * 0.10, 0)) as commission,
            SUM(COALESCE(payout.seller_earning, oi.seller_earning, oi.total_price - (oi.profit * 0.10))) as seller_earning
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u_sel ON p.seller_id = u_sel.id
        LEFT JOIN seller_profiles sp ON u_sel.id = sp.user_id
        LEFT JOIN seller_payouts payout ON oi.id = payout.order_item_id
        GROUP BY oi.order_id
    ) items_data ON ord.id = items_data.order_id
    WHERE $whereSQL
    ORDER BY ord.id DESC
";

$stmt = $pdo->prepare($ordersQuery);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$filteredCount = count($orders);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | OXXA GEAR Control Center</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    
    <!-- Fonts & Tailwind -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        navy: '#0F172A',
                        brand: '#0066FF',
                        oxxa: '#0A6CFF'
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --bg-canvas: #F8FAFC;
            --border-subtle: #E2E8F0;
            --text-main: #0F172A;
            --text-muted: #64748B;
        }
        body {
            background-color: var(--bg-canvas);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }
        .enterprise-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.02);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .enterprise-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            border-color: #CBD5E1;
        }
        .kpi-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            text-align: left;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            border-color: #94A3B8;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .kpi-card.active {
            border-color: #0066FF;
            background: #EFF6FF;
            box-shadow: 0 0 0 2px rgba(0, 102, 255, 0.2);
        }
        .badge-pulse {
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); }
            70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #F1F5F9;
        }
        ::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }
        /* Table sticky headers */
        .orders-table-wrapper {
            position: relative;
            max-height: calc(100vh - 360px);
            overflow-y: auto;
            overflow-x: auto;
        }
        .orders-table th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #F8FAFC;
        }
        /* Smooth Slide Drawer */
        #orderDrawer {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .drawer-tab-btn.active {
            color: #0066FF;
            border-bottom: 2px solid #0066FF;
            font-weight: 700;
        }
    </style>
</head>
<body class="overflow-x-hidden">

    <!-- Sidebar Inclusion -->
    <?php include("components/sidebar.php"); ?>

    <!-- Main Container (Respects sidebar margin: 250px) -->
    <main class="main-content flex flex-col min-h-screen bg-[#F8FAFC]">
        
        <!-- TOP STICKY HEADER -->
        <header class="bg-white border-b border-slate-200 px-8 py-5 sticky top-0 z-20 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg font-black shadow-sm">
                        <i class="fas fa-boxes-stacked"></i>
                    </span>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            Manage Orders
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 tracking-normal uppercase">Enterprise Hub</span>
                        </h1>
                        <p class="text-xs font-medium text-slate-500">Shopify Plus & Amazon Central grade fulfillment, status workflows & courier automation.</p>
                    </div>
                </div>
            </div>

            <!-- Top Actions -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="finance_export.php?type=csv" class="px-4 py-2 bg-white border border-slate-300 hover:border-slate-400 text-slate-700 text-xs font-bold rounded-lg shadow-sm transition flex items-center gap-2">
                    <i class="fas fa-file-csv text-emerald-600 text-sm"></i>
                    <span>Export All CSV</span>
                </a>
                <button onclick="printBulkInvoicesPrompt()" class="px-4 py-2 bg-white border border-slate-300 hover:border-slate-400 text-slate-700 text-xs font-bold rounded-lg shadow-sm transition flex items-center gap-2">
                    <i class="fas fa-print text-indigo-600 text-sm"></i>
                    <span>Print Invoices</span>
                </button>
                <button onclick="window.location.reload()" class="w-9 h-9 flex items-center justify-center bg-white border border-slate-300 hover:bg-slate-50 text-slate-600 text-xs font-bold rounded-lg shadow-sm transition" title="Refresh Orders">
                    <i class="fas fa-rotate"></i>
                </button>
            </div>
        </header>

        <!-- CONTENT BODY -->
        <div class="p-6 md:p-8 space-y-6 flex-1">

            <!-- 1. TOP KPI ROW - 11 ENTERPRISE METRIC CARDS -->
            <section class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-11 gap-3">
                
                <!-- 1. Total -->
                <div onclick="filterByStatus('ALL')" class="kpi-card <?= empty($statusParam) || $statusParam === 'ALL' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total</span>
                        <i class="fas fa-layer-group text-xs text-slate-400"></i>
                    </div>
                    <div class="text-xl font-black text-slate-900"><?= $totalOrders ?></div>
                    <div class="text-[10px] text-slate-500 font-semibold truncate">All Time Orders</div>
                </div>

                <!-- 2. Pending -->
                <div onclick="filterByStatus('PENDING')" class="kpi-card <?= $statusParam === 'PENDING' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600">Pending</span>
                        <?php if ($pendingOrders > 0): ?>
                            <span class="w-2 h-2 rounded-full bg-amber-500 badge-pulse"></span>
                        <?php else: ?>
                            <i class="fas fa-clock text-xs text-amber-400"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-xl font-black text-amber-600"><?= $pendingOrders ?></div>
                    <div class="text-[10px] text-amber-700 font-bold truncate">Need Action</div>
                </div>

                <!-- 3. Confirmed -->
                <div onclick="filterByStatus('CONFIRMED')" class="kpi-card <?= $statusParam === 'CONFIRMED' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-600">Confirmed</span>
                        <i class="fas fa-check text-xs text-indigo-400"></i>
                    </div>
                    <div class="text-xl font-black text-indigo-600"><?= $confirmedOrders ?></div>
                    <div class="text-[10px] text-indigo-700 font-bold truncate">Ready to Pack</div>
                </div>

                <!-- 4. Packed -->
                <div onclick="filterByStatus('PACKED')" class="kpi-card <?= $statusParam === 'PACKED' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600">Packed</span>
                        <i class="fas fa-box-open text-xs text-blue-400"></i>
                    </div>
                    <div class="text-xl font-black text-blue-600"><?= $packedOrders ?></div>
                    <div class="text-[10px] text-blue-700 font-bold truncate">Ready to Ship</div>
                </div>

                <!-- 5. Shipped -->
                <div onclick="filterByStatus('SHIPPED')" class="kpi-card <?= $statusParam === 'SHIPPED' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-cyan-600">Shipped</span>
                        <i class="fas fa-truck-fast text-xs text-cyan-400"></i>
                    </div>
                    <div class="text-xl font-black text-cyan-600"><?= $shippedOrders ?></div>
                    <div class="text-[10px] text-cyan-700 font-semibold truncate">In Transit</div>
                </div>

                <!-- 6. Out For Delivery -->
                <div onclick="filterByStatus('OUT_FOR_DELIVERY')" class="kpi-card <?= $statusParam === 'OUT_FOR_DELIVERY' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-teal-600">Out Delivery</span>
                        <i class="fas fa-motorcycle text-xs text-teal-400"></i>
                    </div>
                    <div class="text-xl font-black text-teal-600"><?= $outDeliveryOrders ?></div>
                    <div class="text-[10px] text-teal-700 font-semibold truncate">With Courier</div>
                </div>

                <!-- 7. Delivered -->
                <div onclick="filterByStatus('DELIVERED')" class="kpi-card <?= $statusParam === 'DELIVERED' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600">Delivered</span>
                        <i class="fas fa-clipboard-check text-xs text-emerald-400"></i>
                    </div>
                    <div class="text-xl font-black text-emerald-600"><?= $deliveredOrders ?></div>
                    <div class="text-[10px] text-emerald-700 font-bold truncate">Customer Received</div>
                </div>

                <!-- 8. Return Window -->
                <div onclick="filterByStatus('RETURN_WINDOW')" class="kpi-card <?= $statusParam === 'RETURN_WINDOW' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-600">Return Window</span>
                        <i class="fas fa-arrows-rotate text-xs text-purple-400"></i>
                    </div>
                    <div class="text-xl font-black text-purple-600"><?= $returnWindowOrders ?></div>
                    <div class="text-[10px] text-purple-700 font-bold truncate">14 Days Window</div>
                </div>

                <!-- 9. Completed -->
                <div onclick="filterByStatus('COMPLETED')" class="kpi-card <?= $statusParam === 'COMPLETED' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-green-700">Completed</span>
                        <i class="fas fa-shield-halved text-xs text-green-500"></i>
                    </div>
                    <div class="text-xl font-black text-green-700"><?= $completedOrders ?></div>
                    <div class="text-[10px] text-green-700 font-bold truncate">Escrow Released</div>
                </div>

                <!-- 10. Cancelled -->
                <div onclick="filterByStatus('CANCELLED')" class="kpi-card <?= $statusParam === 'CANCELLED' ? 'active' : '' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-500">Cancelled</span>
                        <i class="fas fa-ban text-xs text-rose-300"></i>
                    </div>
                    <div class="text-xl font-black text-rose-500"><?= $cancelledOrders ?></div>
                    <div class="text-[10px] text-rose-600 font-semibold truncate">Void / Refunded</div>
                </div>

                <!-- 11. COD Collection -->
                <div onclick="filterByStatus('COD_PENDING')" class="kpi-card <?= $statusParam === 'COD_PENDING' ? 'active' : '' ?> bg-amber-50/50 border-amber-200">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">COD Due</span>
                        <i class="fas fa-money-bill-wave text-xs text-amber-600"></i>
                    </div>
                    <div class="text-lg font-black text-amber-900 leading-tight">Rs. <?= number_format($codPendingAmount / 1000, 0) ?>k</div>
                    <div class="text-[10px] text-amber-800 font-bold truncate">Collection Pending</div>
                </div>

            </section>


            <!-- 2. ADVANCED 2-ROW FILTER BAR -->
            <section class="enterprise-card rounded-2xl p-5 space-y-4">
                <form id="ordersFilterForm" method="GET" action="manage-orders.php" class="space-y-4">
                    
                    <!-- Hidden status input controlled by KPI cards or dropdown -->
                    <input type="hidden" name="status" id="filter_status" value="<?= htmlspecialchars($statusParam) ?>">

                    <!-- ROW 1: Search, Date Range, Payment Method, Seller -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        
                        <!-- Search Box (Col 4) -->
                        <div class="md:col-span-4 relative">
                            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($searchParam) ?>" 
                                   placeholder="Search Order #, Buyer, Phone, Tracking, Item..." 
                                   class="w-full pl-9 pr-8 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-semibold text-slate-800 outline-none transition shadow-sm">
                            <?php if ($searchParam !== ''): ?>
                                <button type="button" onclick="document.querySelector('input[name=search]').value=''; document.getElementById('ordersFilterForm').submit();" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i class="fas fa-times-circle text-xs"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Date From (Col 2) -->
                        <div class="md:col-span-2">
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFromParam) ?>" 
                                   class="w-full px-3 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-semibold text-slate-800 outline-none transition shadow-sm"
                                   title="Date From">
                        </div>

                        <!-- Date To (Col 2) -->
                        <div class="md:col-span-2">
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateToParam) ?>" 
                                   class="w-full px-3 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-semibold text-slate-800 outline-none transition shadow-sm"
                                   title="Date To">
                        </div>

                        <!-- Payment Method (Col 2) -->
                        <div class="md:col-span-2">
                            <select name="payment_method" class="w-full px-3 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-bold text-slate-800 outline-none transition shadow-sm">
                                <option value="ALL">All Payment Types</option>
                                <option value="CARD" <?= $paymentMethodParam === 'CARD' ? 'selected' : '' ?>>Card (PayHere)</option>
                                <option value="KOKO" <?= $paymentMethodParam === 'KOKO' ? 'selected' : '' ?>>KOKO (Installment)</option>
                                <option value="COD" <?= $paymentMethodParam === 'COD' ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
                            </select>
                        </div>

                        <!-- Seller (Col 2) -->
                        <div class="md:col-span-2">
                            <select name="seller" class="w-full px-3 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-bold text-slate-800 outline-none transition shadow-sm truncate">
                                <option value="ALL">All Sellers / Stores</option>
                                <?php foreach ($sellersList as $sName): ?>
                                    <option value="<?= htmlspecialchars($sName) ?>" <?= $sellerParam === $sName ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <!-- ROW 2: Order Status Dropdown, Payment Status, Return Window, Apply & Reset Buttons -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center pt-1 border-t border-slate-100">
                        
                        <!-- Order Status Dropdown (Col 3) -->
                        <div class="md:col-span-3">
                            <select onchange="document.getElementById('filter_status').value = this.value; document.getElementById('ordersFilterForm').submit();" 
                                    class="w-full px-3 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-bold text-slate-800 outline-none transition shadow-sm">
                                <option value="ALL" <?= empty($statusParam) || $statusParam === 'ALL' ? 'selected' : '' ?>>Workflow Status: All</option>
                                <option value="PENDING" <?= $statusParam === 'PENDING' ? 'selected' : '' ?>>Pending (Need Action)</option>
                                <option value="CONFIRMED" <?= $statusParam === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed (Ready to Pack)</option>
                                <option value="PACKED" <?= $statusParam === 'PACKED' ? 'selected' : '' ?>>Packed (Ready to Ship)</option>
                                <option value="SHIPPED" <?= $statusParam === 'SHIPPED' ? 'selected' : '' ?>>Shipped (In Transit)</option>
                                <option value="OUT_FOR_DELIVERY" <?= $statusParam === 'OUT_FOR_DELIVERY' ? 'selected' : '' ?>>Out for Delivery</option>
                                <option value="DELIVERED" <?= $statusParam === 'DELIVERED' ? 'selected' : '' ?>>Delivered</option>
                                <option value="RETURN_WINDOW" <?= $statusParam === 'RETURN_WINDOW' ? 'selected' : '' ?>>Return Window (14 Days)</option>
                                <option value="COMPLETED" <?= $statusParam === 'COMPLETED' ? 'selected' : '' ?>>Completed (Escrow Released)</option>
                                <option value="CANCELLED" <?= $statusParam === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>

                        <!-- Payment Status Dropdown (Col 3) -->
                        <div class="md:col-span-3">
                            <select name="payment_status" class="w-full px-3 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-bold text-slate-800 outline-none transition shadow-sm">
                                <option value="ALL">Payment Status: All</option>
                                <option value="PAID" <?= $paymentStatusParam === 'PAID' ? 'selected' : '' ?>>Paid (Settled / Verified)</option>
                                <option value="PENDING_COLLECTION" <?= $paymentStatusParam === 'PENDING_COLLECTION' ? 'selected' : '' ?>>Pending COD Collection</option>
                                <option value="REFUND_PENDING" <?= $paymentStatusParam === 'REFUND_PENDING' ? 'selected' : '' ?>>Refund Pending</option>
                            </select>
                        </div>

                        <!-- Return Window Dropdown (Col 2) -->
                        <div class="md:col-span-2">
                            <select name="return_window" class="w-full px-3 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-[#0066FF] rounded-xl text-xs font-bold text-slate-800 outline-none transition shadow-sm">
                                <option value="">Return Window: All</option>
                                <option value="active" <?= $returnWindowParam === 'active' ? 'selected' : '' ?>>Active (Inside 14 Days)</option>
                                <option value="expiring" <?= $returnWindowParam === 'expiring' ? 'selected' : '' ?>>Expiring Soon (<= 3 Days)</option>
                                <option value="expired" <?= $returnWindowParam === 'expired' ? 'selected' : '' ?>>Expired / Escrow Ready</option>
                            </select>
                        </div>

                        <!-- Buttons Group (Col 4) -->
                        <div class="md:col-span-4 flex items-center justify-end gap-2">
                            <button type="submit" class="px-4 py-2 bg-[#0066FF] hover:bg-blue-700 text-white text-xs font-black rounded-xl shadow-sm transition flex items-center gap-1.5 uppercase tracking-wide">
                                <i class="fas fa-filter text-[10px]"></i>
                                <span>Filter</span>
                            </button>
                            <a href="manage-orders.php" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1">
                                <i class="fas fa-undo text-[10px]"></i>
                                <span>Reset</span>
                            </a>
                            <span class="text-xs font-semibold text-slate-400 pl-1">
                                Showing <b><?= $filteredCount ?></b> of <?= $totalOrders ?>
                            </span>
                        </div>

                    </div>

                </form>
            </section>


            <!-- 3. ENTERPRISE 12-COLUMN ORDERS TABLE -->
            <section class="enterprise-card rounded-2xl overflow-hidden border border-slate-200">
                <div class="orders-table-wrapper">
                    <table class="w-full text-left border-collapse orders-table text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 select-none">
                                <th class="p-4 w-10 text-center">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded border-slate-300 text-[#0066FF] focus:ring-0 cursor-pointer">
                                </th>
                                <th class="p-3.5 whitespace-nowrap">Order Code</th>
                                <th class="p-3.5 whitespace-nowrap">Date & Time</th>
                                <th class="p-3.5 whitespace-nowrap">Buyer & Contact</th>
                                <th class="p-3.5 whitespace-nowrap">Seller / Store</th>
                                <th class="p-3.5 whitespace-nowrap">Items (Qty)</th>
                                <th class="p-3.5 text-right whitespace-nowrap">Total</th>
                                <th class="p-3.5 text-center whitespace-nowrap">Payment Method</th>
                                <th class="p-3.5 text-center whitespace-nowrap">Payment Status</th>
                                <th class="p-3.5 text-center whitespace-nowrap">Order Status</th>
                                <th class="p-3.5 text-center whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <?php if ($filteredCount > 0): ?>
                                <?php foreach ($orders as $ord): 
                                    $buyerName = !empty($ord['shipping_name']) ? $ord['shipping_name'] : trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
                                    if (empty($buyerName)) $buyerName = 'Valued Customer';
                                    
                                    $buyerPhone = !empty($ord['buyer_phone']) ? $ord['buyer_phone'] : (!empty($ord['phone1']) ? $ord['phone1'] : (!empty($ord['account_phone']) ? $ord['account_phone'] : 'N/A'));
                                    $buyerCity = !empty($ord['city']) ? $ord['city'] : (!empty($ord['province']) ? $ord['province'] : 'Sri Lanka');

                                    $pm = strtoupper($ord['payment_method'] ?? 'COD');
                                    $st = strtoupper(trim($ord['status'] ?? 'PENDING'));

                                    // Buyer Avatar Initials
                                    $nameParts = explode(' ', trim($buyerName));
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

                                    // Return window calculations
                                    $returnDaysLeft = 0;
                                    if (!empty($ord['return_window_ends'])) {
                                        $end = new DateTime($ord['return_window_ends']);
                                        $today = new DateTime();
                                        if ($today <= $end) {
                                            $returnDaysLeft = (int)$today->diff($end)->format('%a');
                                        }
                                    }

                                    // Product image path fix using helper
                                    $imgSrc = getOrderProductImg($ord['primary_image']);
                                ?>
                                <tr class="hover:bg-blue-50/30 transition-colors group cursor-pointer" onclick="handleRowClick(event, <?= $ord['id'] ?>)">
                                    
                                    <!-- 1. Checkbox -->
                                    <td class="p-4 text-center select-none" onclick="event.stopPropagation()">
                                        <input type="checkbox" value="<?= $ord['id'] ?>" onchange="updateSelectedCount()" class="row-checkbox w-4 h-4 rounded border-slate-300 text-[#0066FF] focus:ring-0 cursor-pointer">
                                    </td>

                                    <!-- 2. Order Code -->
                                    <td class="p-3.5 whitespace-nowrap" onclick="event.stopPropagation()">
                                        <div class="flex items-center gap-1.5">
                                            <button onclick="openOrderDrawer(<?= $ord['id'] ?>)" class="font-extrabold text-[#0066FF] hover:text-blue-800 hover:underline tracking-tight">
                                                <?= htmlspecialchars($ord['order_code']) ?>
                                            </button>
                                            <button onclick="copyToClipboard('<?= htmlspecialchars($ord['order_code']) ?>', this)" class="text-slate-300 hover:text-slate-600 transition" title="Copy Order Code">
                                                <i class="far fa-copy text-[11px]"></i>
                                            </button>
                                        </div>
                                        <span class="text-[10px] text-slate-400 font-mono">ID #<?= $ord['id'] ?></span>
                                    </td>

                                    <!-- 3. Date & Time -->
                                    <td class="p-3.5 whitespace-nowrap">
                                        <div class="font-bold text-slate-900"><?= date('M d, Y', strtotime($ord['created_at'])) ?></div>
                                        <div class="text-[10px] text-slate-400 font-medium"><?= date('h:i A', strtotime($ord['created_at'])) ?></div>
                                    </td>

                                    <!-- 4. Buyer & Contact -->
                                    <td class="p-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center font-extrabold text-[10px] shrink-0">
                                                <?= $initials ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900 max-w-[130px] truncate" title="<?= htmlspecialchars($buyerName) ?>">
                                                    <?= htmlspecialchars($buyerName) ?>
                                                </div>
                                                <div class="flex items-center gap-1.5 text-[10px] text-slate-500">
                                                    <a href="tel:<?= htmlspecialchars($buyerPhone) ?>" onclick="event.stopPropagation()" class="hover:text-[#0066FF] font-medium">
                                                        <i class="fas fa-phone text-[9px] text-slate-400"></i> <?= htmlspecialchars($buyerPhone) ?>
                                                    </a>
                                                    <span class="text-slate-300">•</span>
                                                    <span class="text-slate-500"><?= htmlspecialchars($buyerCity) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 5. Seller -->
                                    <td class="p-3.5 whitespace-normal min-w-[180px]">
                                        <div class="inline-flex items-start gap-1.5 px-2 py-1.5 rounded-md bg-slate-50 border border-slate-200 text-[11px] font-bold text-slate-700 w-full" title="<?= htmlspecialchars($ord['seller_name']) ?>">
                                            <i class="fas fa-store text-[10px] text-slate-400 mt-0.5 shrink-0"></i>
                                            <span class="line-clamp-2 break-words leading-tight"><?= htmlspecialchars($ord['seller_name']) ?></span>
                                        </div>
                                    </td>

                                    <!-- 6. Items & Qty -->
                                    <td class="p-3.5 whitespace-normal min-w-[250px]">
                                        <div class="flex items-start gap-2.5">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>" onerror="this.src='../image/placeholder.png'" 
                                                 class="w-10 h-10 rounded-lg object-cover border border-slate-200 shadow-sm shrink-0">
                                            <div class="flex-1 min-w-0">
                                                <div class="font-bold text-slate-900 line-clamp-2 leading-snug" title="<?= htmlspecialchars($ord['primary_product_name']) ?>">
                                                    <?= htmlspecialchars($ord['primary_product_name']) ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400 flex items-center gap-1.5 mt-1">
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-mono leading-none"><?= htmlspecialchars($ord['primary_sku']) ?></span>
                                                    <span class="whitespace-nowrap">• <?= $ord['item_count'] ?> item<?= $ord['item_count'] > 1 ? 's' : '' ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 7. Total -->
                                    <td class="p-3.5 text-right whitespace-nowrap font-black text-slate-900">
                                        Rs. <?= number_format($ord['total_amount'], 2) ?>
                                    </td>

                                    <!-- 8. Payment Method -->
                                    <td class="p-3.5 text-center whitespace-nowrap">
                                        <?php if ($pm === 'CARD' || $pm === 'PAYHERE'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0066FF] border border-blue-200">
                                                <i class="fas fa-credit-card text-[9px]"></i> Card
                                            </span>
                                        <?php elseif (strpos($pm, 'KOKO') !== false): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                <i class="fas fa-layer-group text-[9px]"></i> KOKO
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                <i class="fas fa-money-bill-wave text-[9px]"></i> COD
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 9. Payment Status -->
                                    <td class="p-3.5 text-center whitespace-nowrap">
                                        <?php if ($pm === 'COD' && empty($ord['cod_collected'])): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                <i class="fas fa-clock text-[9px]"></i> COD Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i class="fas fa-circle-check text-[9px]"></i> Paid
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 10. Order Status Workflow Badge -->
                                    <td class="p-3.5 text-center whitespace-nowrap">
                                        <?php if ($st === 'PENDING'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-300 badge-pulse uppercase tracking-wider">
                                                <i class="fas fa-hourglass-half text-[9px]"></i> Pending
                                            </span>
                                        <?php elseif ($st === 'ACCEPTED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-100 text-purple-700 border border-purple-200 uppercase tracking-wider">
                                                <i class="fas fa-thumbs-up text-[9px]"></i> Accepted
                                            </span>
                                        <?php elseif ($st === 'CONFIRMED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200 uppercase tracking-wider">
                                                <i class="fas fa-check-circle text-[9px]"></i> Confirmed
                                            </span>
                                        <?php elseif ($st === 'HANDOVER_TO_CENTER'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-orange-100 text-orange-700 border border-orange-200 uppercase tracking-wider">
                                                <i class="fas fa-hand-holding-box text-[9px]"></i> Handed Over
                                            </span>
                                        <?php elseif ($st === 'RECEIVED_AT_CENTER'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-100 text-indigo-800 border border-indigo-300 uppercase tracking-wider">
                                                <i class="fas fa-building-circle-check text-[9px]"></i> At Center
                                            </span>
                                        <?php elseif ($st === 'PACKED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200 uppercase tracking-wider">
                                                <i class="fas fa-box text-[9px]"></i> Packed
                                            </span>
                                        <?php elseif ($st === 'SHIPPED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-cyan-50 text-cyan-700 border border-cyan-200 uppercase tracking-wider">
                                                <i class="fas fa-truck-fast text-[9px]"></i> Shipped
                                            </span>
                                        <?php elseif ($st === 'OUT_FOR_DELIVERY'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-teal-50 text-teal-700 border border-teal-200 uppercase tracking-wider">
                                                <i class="fas fa-motorcycle text-[9px]"></i> Out Delivery
                                            </span>
                                        <?php elseif ($st === 'DELIVERED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase tracking-wider">
                                                <i class="fas fa-clipboard-check text-[9px]"></i> Delivered
                                            </span>
                                        <?php elseif ($st === 'RETURN_WINDOW'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200 uppercase tracking-wider">
                                                <i class="fas fa-rotate-left text-[9px]"></i> Return Window (<?= $returnDaysLeft ?>d)
                                            </span>
                                        <?php elseif ($st === 'COMPLETED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-green-100 text-green-800 border border-green-300 uppercase tracking-wider">
                                                <i class="fas fa-shield-check text-[9px]"></i> Completed
                                            </span>
                                        <?php elseif ($st === 'CANCELLED'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-200 uppercase tracking-wider">
                                                <i class="fas fa-ban text-[9px]"></i> Cancelled
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-700 border border-slate-200 uppercase tracking-wider">
                                                <?= htmlspecialchars($st) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>



                                    <!-- 12. Actions -->
                                    <td class="p-3.5 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-center gap-1">
                                            
                                            <!-- View Drawer Button -->
                                            <button onclick="openOrderDrawer(<?= $ord['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-[#0066FF] flex items-center justify-center transition" title="Open Order Drawer">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>

                                            <!-- Print Invoice Button -->
                                            <a href="order-invoice.php?id=<?= $ord['id'] ?>" target="_blank" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 flex items-center justify-center transition" title="Print Commercial Tax Invoice">
                                                <i class="fas fa-print text-xs"></i>
                                            </a>

                                            <!-- Status Edit Modal -->
                                            <button onclick="openQuickStatusModal(<?= $ord['id'] ?>, '<?= htmlspecialchars($ord['order_code']) ?>', '<?= $st ?>', '<?= htmlspecialchars($ord['courier_company'] ?? '') ?>', '<?= htmlspecialchars($ord['tracking_number'] ?? '') ?>')" 
                                                    class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-purple-50 text-slate-600 hover:text-purple-600 flex items-center justify-center transition" title="Change Order Status / Courier">
                                                <i class="fas fa-pen text-xs"></i>
                                            </button>

                                            <!-- 3-Dots Quick Actions Menu -->
                                            <div class="relative inline-block text-left" id="actionMenu_<?= $ord['id'] ?>">
                                                <button onclick="toggleActionDropdown(<?= $ord['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition">
                                                    <i class="fas fa-ellipsis-vertical text-xs"></i>
                                                </button>
                                                <div id="dropdownMenu_<?= $ord['id'] ?>" class="hidden absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-xl shadow-xl z-30 py-1 text-left text-xs font-semibold">
                                                    <a href="javascript:void(0)" onclick="quickUpdateStatus(<?= $ord['id'] ?>, 'RECEIVED_AT_CENTER')" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700">
                                                        <i class="fas fa-building-circle-check text-indigo-500 w-4"></i> Receive at Center
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="quickUpdateStatus(<?= $ord['id'] ?>, 'PACKED')" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700">
                                                        <i class="fas fa-box text-blue-500 w-4"></i> Mark as Packed
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="quickUpdateStatus(<?= $ord['id'] ?>, 'SHIPPED')" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700">
                                                        <i class="fas fa-truck text-cyan-500 w-4"></i> Mark as Shipped
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="quickUpdateStatus(<?= $ord['id'] ?>, 'DELIVERED')" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700">
                                                        <i class="fas fa-check text-emerald-500 w-4"></i> Mark as Delivered
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="quickUpdateStatus(<?= $ord['id'] ?>, 'RETURN_WINDOW')" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700">
                                                        <i class="fas fa-arrows-rotate text-purple-500 w-4"></i> Start Return Window
                                                    </a>
                                                    <a href="javascript:void(0)" onclick="quickUpdateStatus(<?= $ord['id'] ?>, 'COMPLETED')" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-slate-700">
                                                        <i class="fas fa-shield-halved text-green-600 w-4"></i> Release Escrow
                                                    </a>
                                                    <?php if ($pm === 'COD'): ?>
                                                        <a href="javascript:void(0)" onclick="quickMarkCodCollected(<?= $ord['id'] ?>)" class="px-3 py-2 hover:bg-slate-50 flex items-center gap-2 text-amber-700 font-bold border-t border-slate-100">
                                                            <i class="fas fa-money-bill-wave text-amber-500 w-4"></i> Mark COD Collected
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="javascript:void(0)" onclick="promptCancelOrder(<?= $ord['id'] ?>)" class="px-3 py-2 hover:bg-rose-50 flex items-center gap-2 text-rose-600 border-t border-slate-100">
                                                        <i class="fas fa-ban w-4"></i> Cancel Order
                                                    </a>
                                                </div>
                                            </div>

                                        </div>
                                    </td>

                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="p-12 text-center text-slate-400">
                                        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mx-auto mb-3">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <h4 class="font-bold text-slate-700 text-sm">No matching orders found</h4>
                                        <p class="text-xs text-slate-400 mt-1">Try clearing your search query or selecting a different status filter.</p>
                                        <a href="manage-orders.php" class="inline-block mt-3 px-4 py-1.5 rounded-lg bg-[#0066FF] text-white text-xs font-bold">Reset Filters</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>


    <!-- 4. FLOATING STICKY BULK ACTIONS BAR (Slides up when >= 1 checkbox checked) -->
    <div id="bulkActionsBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white rounded-2xl shadow-2xl border border-slate-700 px-5 py-3 z-40 hidden items-center gap-4 transition-all duration-300">
        <div class="flex items-center gap-2 border-r border-slate-700 pr-4">
            <span class="w-6 h-6 rounded-full bg-[#0066FF] text-white text-xs font-black flex items-center justify-center" id="selectedCountBadge">0</span>
            <span class="text-xs font-bold text-slate-300">Orders Selected</span>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="executeBulkAction('mark_packed')" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 transition flex items-center gap-1.5">
                <i class="fas fa-box text-blue-400"></i>
                <span>Mark Packed</span>
            </button>

            <button onclick="promptBulkShip()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 transition flex items-center gap-1.5">
                <i class="fas fa-truck text-cyan-400"></i>
                <span>Mark Shipped</span>
            </button>

            <button onclick="executeBulkAction('mark_delivered')" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 transition flex items-center gap-1.5">
                <i class="fas fa-check-circle text-emerald-400"></i>
                <span>Mark Delivered</span>
            </button>

            <button onclick="executeBulkPrintInvoices()" class="px-3 py-1.5 rounded-xl bg-[#0066FF] hover:bg-blue-600 text-xs font-black text-white transition flex items-center gap-1.5 shadow-sm">
                <i class="fas fa-print"></i>
                <span>Print Invoices</span>
            </button>

            <button onclick="executeBulkExportCSV()" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-black text-white transition flex items-center gap-1.5">
                <i class="fas fa-file-csv"></i>
                <span>Export Selected</span>
            </button>
        </div>

        <button onclick="deselectAll()" class="text-xs text-slate-400 hover:text-white pl-2 border-l border-slate-700">
            <i class="fas fa-times"></i>
        </button>
    </div>


    <!-- 5. 600px RIGHT SLIDE-OUT VIEW DRAWER -->
    <div id="drawerOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden transition-opacity" onclick="closeOrderDrawer()"></div>
    
    <div id="orderDrawer" class="fixed top-0 right-0 w-full md:w-[600px] h-full bg-white z-50 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out border-l border-slate-200">
        
        <!-- Drawer Header -->
        <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50 shrink-0">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-black text-slate-900 uppercase tracking-tight" id="drawer_order_code">ORD-000</h3>
                    <span id="drawer_status_badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 text-slate-700"></span>
                </div>
                <p class="text-[11px] text-slate-400 font-medium" id="drawer_created_at">Placed on ...</p>
            </div>
            
            <div class="flex items-center gap-2">
                <a id="drawer_invoice_link" href="#" target="_blank" class="w-8 h-8 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 flex items-center justify-center text-xs shadow-sm transition" title="Print Invoice">
                    <i class="fas fa-print"></i>
                </a>
                <button onclick="closeOrderDrawer()" class="w-8 h-8 rounded-lg bg-white border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center text-xs shadow-sm transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Drawer Navigation Tabs -->
        <div class="px-6 border-b border-slate-200 flex items-center gap-6 text-xs font-semibold text-slate-500 bg-white shrink-0 overflow-x-auto">
            <button onclick="switchDrawerTab('details')" class="drawer-tab-btn py-3 active flex items-center gap-1.5" data-tab="details">
                <i class="fas fa-circle-info text-[11px]"></i> Details
            </button>
            <button onclick="switchDrawerTab('timeline')" class="drawer-tab-btn py-3 flex items-center gap-1.5" data-tab="timeline">
                <i class="fas fa-timeline text-[11px]"></i> Timeline
            </button>
            <button onclick="switchDrawerTab('payments')" class="drawer-tab-btn py-3 flex items-center gap-1.5" data-tab="payments">
                <i class="fas fa-credit-card text-[11px]"></i> Payments
            </button>
            <button onclick="switchDrawerTab('notes')" class="drawer-tab-btn py-3 flex items-center gap-1.5" data-tab="notes">
                <i class="fas fa-sticky-note text-[11px]"></i> Internal Notes
            </button>
            <button onclick="switchDrawerTab('activity')" class="drawer-tab-btn py-3 flex items-center gap-1.5" data-tab="activity">
                <i class="fas fa-history text-[11px]"></i> Activity Log
            </button>
        </div>

        <!-- Drawer Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/30 text-xs">
            
            <!-- TAB 1: DETAILS -->
            <div id="tab_details" class="space-y-6 drawer-tab-pane">
                
                <!-- Cancellation Reason (If Cancelled) -->
                <div id="drawer_cancellation_reason_box" class="hidden p-4 rounded-xl bg-red-50 border border-red-200">
                    <div class="flex items-center gap-2 font-bold text-red-900 mb-1">
                        <i class="fas fa-exclamation-triangle"></i> Seller Rejection Reason
                    </div>
                    <p class="text-sm text-red-700 font-medium" id="drawer_cancellation_reason_text"></p>
                </div>

                <!-- Customer & Shipping Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- Customer Card -->
                    <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Customer Info</span>
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-[#0066FF] flex items-center justify-center text-[10px]">
                                <i class="fas fa-user"></i>
                            </span>
                        </div>
                        <div class="font-extrabold text-slate-900 text-sm" id="drawer_buyer_name">-</div>
                        <div class="text-slate-600 font-medium" id="drawer_buyer_phone">-</div>
                        <div class="text-slate-400 text-[11px] truncate" id="drawer_buyer_email">-</div>
                    </div>

                    <!-- Shipping Address Card -->
                    <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Delivery Address</span>
                            <span class="w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-[10px]">
                                <i class="fas fa-location-dot"></i>
                            </span>
                        </div>
                        <p class="text-slate-700 leading-relaxed font-medium" id="drawer_delivery_address">-</p>
                        <div class="text-[11px] text-slate-500 font-bold" id="drawer_city_province">-</div>
                    </div>

                </div>

                <!-- Courier & Tracking Card -->
                <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Courier Logistics</span>
                        <div class="font-bold text-slate-900 text-sm" id="drawer_courier_name">Koombiyo Delivery</div>
                        <div class="text-slate-500 font-mono text-xs" id="drawer_tracking_number">KMB-123456</div>
                    </div>
                    <button onclick="openDrawerEditCourier()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                        <i class="fas fa-pen text-[10px]"></i> Edit Courier
                    </button>
                </div>

                <!-- Items Purchased -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Purchased Items</span>
                        <span class="px-2 py-0.5 rounded-full bg-blue-50 text-[#0066FF] font-bold text-[10px]" id="drawer_items_count">1 Item</span>
                    </div>
                    <div id="drawer_items_list" class="divide-y divide-slate-100 p-4 space-y-3">
                        <!-- Injected via JS -->
                    </div>
                </div>

                <!-- Financial Breakdown -->
                <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2.5">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1">Financial Reconciliation</span>
                    
                    <div class="flex justify-between text-slate-600">
                        <span>Items Subtotal</span>
                        <span class="font-bold text-slate-800" id="drawer_fin_subtotal">Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Delivery Fee</span>
                        <span class="font-bold text-slate-800" id="drawer_fin_delivery">Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Payment Gateway Fee</span>
                        <span class="font-bold text-rose-600" id="drawer_fin_gateway">- Rs. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>OXXA Platform Commission (10%)</span>
                        <span class="font-bold text-[#0066FF]" id="drawer_fin_commission">Rs. 0.00</span>
                    </div>
                    <div class="border-t border-slate-100 pt-2 flex justify-between text-slate-600">
                        <span>Net Seller Payout (Escrow)</span>
                        <span class="font-black text-emerald-600" id="drawer_fin_payout">Rs. 0.00</span>
                    </div>
                    <div class="border-t border-slate-200 pt-3 flex justify-between text-sm">
                        <span class="font-black text-slate-900">Total Customer Paid</span>
                        <span class="font-black text-slate-900 text-base" id="drawer_fin_total">Rs. 0.00</span>
                    </div>
                </div>

            </div>

            <!-- TAB 2: TIMELINE -->
            <div id="tab_timeline" class="space-y-4 drawer-tab-pane hidden">
                <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <h4 class="font-extrabold text-slate-900 text-sm mb-4">Milestone Progress Workflow</h4>
                    <div id="drawer_timeline_container" class="relative pl-6 border-l-2 border-slate-200 space-y-6">
                        <!-- Injected via JS -->
                    </div>
                </div>
            </div>

            <!-- TAB 3: PAYMENTS -->
            <div id="tab_payments" class="space-y-4 drawer-tab-pane hidden">
                <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Payment Gateway</span>
                            <div class="font-black text-slate-900 text-sm" id="drawer_pay_method">CARD (PayHere)</div>
                        </div>
                        <span id="drawer_pay_badge" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase">PAID</span>
                    </div>

                    <div id="drawer_bank_slip_box" class="hidden p-4 rounded-xl bg-amber-50 border border-amber-200 space-y-3">
                        <div class="flex items-center gap-2 font-bold text-amber-900">
                            <i class="fas fa-file-invoice"></i> Bank Deposit Slip Attached
                        </div>
                        <div class="border border-amber-300 rounded-lg overflow-hidden bg-white max-h-60 flex items-center justify-center p-2">
                            <img id="drawer_bank_slip_img" src="" alt="Bank Slip" class="max-h-56 object-contain">
                        </div>
                        <button onclick="verifyBankSlip()" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-xs transition">
                            <i class="fas fa-circle-check"></i> Verify & Confirm Order
                        </button>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Gateway Fee Deducted:</span>
                            <span class="font-bold text-slate-900" id="drawer_pay_fee">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Net Settlement Amount:</span>
                            <span class="font-bold text-emerald-600" id="drawer_pay_net">Rs. 0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: INTERNAL NOTES -->
            <div id="tab_notes" class="space-y-4 drawer-tab-pane hidden">
                <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <h4 class="font-extrabold text-slate-900 text-sm">Internal Staff & Operations Notes</h4>
                    <p class="text-slate-400 text-xs">Notes entered here are visible only to OXXA administrators.</p>
                    <textarea id="drawer_notes_input" rows="6" placeholder="Enter confidential customer/order notes here..." 
                              class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-[#0066FF] outline-none text-xs font-medium text-slate-800 leading-relaxed"></textarea>
                    <button onclick="saveInternalNotes()" class="px-4 py-2 bg-[#0066FF] hover:bg-blue-700 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 shadow-sm">
                        <i class="fas fa-save"></i> Save Notes
                    </button>
                </div>
            </div>

            <!-- TAB 5: ACTIVITY LOG -->
            <div id="tab_activity" class="space-y-4 drawer-tab-pane hidden">
                <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <h4 class="font-extrabold text-slate-900 text-sm mb-3">Order Audit Trail</h4>
                    <div id="drawer_activity_log" class="space-y-3">
                        <!-- Injected via JS -->
                    </div>
                </div>
            </div>

        </div>

    </div>


    <!-- 6. QUICK UPDATE ORDER STATUS MODAL -->
    <div id="quickStatusModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wide">
                    Update Order: <span id="quick_order_code_title" class="text-[#0066FF]"></span>
                </h3>
                <button onclick="closeQuickStatusModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="quickStatusForm" onsubmit="submitQuickStatus(event)" class="p-6 space-y-4">
                <input type="hidden" name="order_id" id="quick_order_id">

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-1.5">Order Status</label>
                    <select name="status" id="quick_status_select" onchange="toggleQuickCourierFields()" required 
                            class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl px-3 py-2.5 text-xs focus:border-[#0066FF] outline-none">
                        <option value="PENDING">Pending (Need Action)</option>
                        <option value="ACCEPTED">Accepted by Seller</option>
                        <option value="CONFIRMED">Confirmed (Ready to Pack)</option>
                        <option value="HANDOVER_TO_CENTER">Handed to Collecting Center</option>
                        <option value="RECEIVED_AT_CENTER">Received at Collecting Center</option>
                        <option value="PACKED">Packed (Ready to Ship)</option>
                        <option value="SHIPPED">Shipped (In Transit)</option>
                        <option value="OUT_FOR_DELIVERY">Out for Delivery</option>
                        <option value="DELIVERED">Delivered</option>
                        <option value="RETURN_WINDOW">Return Window (14 Days)</option>
                        <option value="COMPLETED">Completed (Escrow Released)</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>
                </div>

                <div id="quick_courier_section" class="space-y-3 p-4 bg-blue-50/50 rounded-xl border border-blue-100">
                    <span class="text-[10px] font-extrabold text-[#0066FF] uppercase tracking-wider block">Courier Logistics</span>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Courier Company</label>
                        <select name="courier_company" id="quick_courier_select" class="w-full bg-white border border-slate-200 font-bold text-slate-900 rounded-lg px-3 py-2 text-xs focus:border-[#0066FF] outline-none">
                            <option value="Koombiyo Delivery">Koombiyo Delivery</option>
                            <option value="Pronto Lanka">Pronto Lanka</option>
                            <option value="Domex">Domex</option>
                            <option value="Fardar Express">Fardar Express</option>
                            <option value="SpeedPost Courier">SpeedPost Courier</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Tracking Number</label>
                        <input type="text" name="tracking_number" id="quick_tracking_input" placeholder="e.g. KMB-992144" class="w-full bg-white border border-slate-200 font-bold text-slate-900 rounded-lg px-3 py-2 text-xs focus:border-[#0066FF] outline-none">
                    </div>
                </div>

                <button type="submit" id="quickSubmitBtn" class="w-full py-3 bg-[#0066FF] hover:bg-blue-700 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider transition shadow-sm">
                    Save Order Status
                </button>
            </form>
        </div>
    </div>


    <!-- JAVASCRIPT INTERACTIONS -->
    <script>
    let activeDrawerOrderId = null;

    // 1. KPI Filter Click
    function filterByStatus(status) {
        document.getElementById('filter_status').value = status;
        document.getElementById('ordersFilterForm').submit();
    }

    // 2. Clipboard copy
    function copyToClipboard(text, el) {
        navigator.clipboard.writeText(text).then(() => {
            const originalTitle = el.title;
            el.innerHTML = '<i class="fas fa-check text-emerald-500 text-[11px]"></i>';
            setTimeout(() => {
                el.innerHTML = '<i class="far fa-copy text-[11px]"></i>';
            }, 1500);
        });
    }

    // 3. Row Click
    function handleRowClick(e, orderId) {
        // Only trigger drawer if clicked outside inputs or buttons
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && !e.target.closest('button') && !e.target.closest('a')) {
            openOrderDrawer(orderId);
        }
    }

    // 4. Select All Checkbox
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const count = checkedBoxes.length;
        const bar = document.getElementById('bulkActionsBar');
        const badge = document.getElementById('selectedCountBadge');

        if (count > 0) {
            badge.textContent = count;
            bar.classList.remove('hidden');
            bar.classList.add('flex');
        } else {
            bar.classList.add('hidden');
            bar.classList.remove('flex');
        }
    }

    function deselectAll() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        const master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = false;
        updateSelectedCount();
    }

    function getSelectedOrderIds() {
        return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    }

    // 5. Bulk Actions Execution
    function executeBulkAction(action) {
        const ids = getSelectedOrderIds();
        if (ids.length === 0) return;

        Swal.fire({
            title: 'Confirm Bulk Action',
            text: `Apply this action to ${ids.length} selected order(s)?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed',
            confirmButtonColor: '#0066FF'
        }).then((res) => {
            if (res.isConfirmed) {
                Swal.showLoading();
                fetch('api/bulk-orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action, order_ids: ids })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Updated!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Operation failed', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'));
            }
        });
    }

    function promptBulkShip() {
        const ids = getSelectedOrderIds();
        if (ids.length === 0) return;

        Swal.fire({
            title: 'Bulk Dispatch & Ship',
            html: `
                <div class="text-left text-xs font-semibold space-y-3">
                    <label class="block font-bold text-slate-700">Courier Company:</label>
                    <select id="swal_courier" class="w-full p-2.5 border rounded-lg bg-slate-50 text-xs font-bold">
                        <option value="Koombiyo Delivery">Koombiyo Delivery</option>
                        <option value="Pronto Lanka">Pronto Lanka</option>
                        <option value="Domex">Domex</option>
                        <option value="Fardar Express">Fardar Express</option>
                    </select>
                    <label class="block font-bold text-slate-700 mt-2">Tracking Code Prefix:</label>
                    <input id="swal_prefix" value="KMB-" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Mark as Shipped',
            confirmButtonColor: '#0066FF',
            preConfirm: () => {
                return {
                    courier: document.getElementById('swal_courier').value,
                    prefix: document.getElementById('swal_prefix').value
                }
            }
        }).then(res => {
            if (res.isConfirmed) {
                Swal.showLoading();
                fetch('api/bulk-orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'mark_shipped',
                        order_ids: ids,
                        courier_company: res.value.courier,
                        tracking_prefix: res.value.prefix
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Shipped!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }

    function executeBulkPrintInvoices() {
        const ids = getSelectedOrderIds();
        if (ids.length === 0) return;
        window.open('order-invoice.php?ids=' + ids.join(','), '_blank');
    }

    function printBulkInvoicesPrompt() {
        const ids = getSelectedOrderIds();
        if (ids.length > 0) {
            window.open('order-invoice.php?ids=' + ids.join(','), '_blank');
        } else {
            window.open('order-invoice.php?ids=1,2,3,4,5,6,7,8', '_blank');
        }
    }

    function executeBulkExportCSV() {
        const ids = getSelectedOrderIds();
        if (ids.length === 0) return;
        window.location.href = 'finance_export.php?type=csv&ids=' + ids.join(',');
    }

    // 6. 3-Dots Dropdown Menu Toggle
    function toggleActionDropdown(orderId) {
        const menu = document.getElementById('dropdownMenu_' + orderId);
        const allMenus = document.querySelectorAll('[id^="dropdownMenu_"]');
        allMenus.forEach(m => {
            if (m !== menu) m.classList.add('hidden');
        });
        menu.classList.toggle('hidden');
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id^="actionMenu_"]')) {
            document.querySelectorAll('[id^="dropdownMenu_"]').forEach(m => m.classList.add('hidden'));
        }
    });

    // 7. Quick Single Status Updates
    function quickUpdateStatus(orderId, newStatus) {
        fetch('api/bulk-orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_' + newStatus.toLowerCase(),
                order_id: orderId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Status Updated',
                    text: data.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function quickMarkCodCollected(orderId) {
        fetch('api/bulk-orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_cod_collected',
                order_id: orderId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', data.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function promptCancelOrder(orderId) {
        Swal.fire({
            title: 'Cancel Order?',
            text: 'Are you sure you want to cancel and void this order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Cancel Order',
            confirmButtonColor: '#EF4444'
        }).then(res => {
            if (res.isConfirmed) {
                fetch('api/bulk-orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cancel', order_id: orderId })
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire('Cancelled', d.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', d.message, 'error');
                    }
                });
            }
        });
    }

    // 8. 600px Right Slide Drawer Logic
    function openOrderDrawer(orderId) {
        activeDrawerOrderId = orderId;
        const drawer = document.getElementById('orderDrawer');
        const overlay = document.getElementById('drawerOverlay');

        overlay.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');

        // Fetch details
        fetch('api/get-order-details.php?id=' + orderId)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                return;
            }

            const ord = res.order;
            const items = res.items || [];
            const fin = res.financials || {};

            // Header info
            document.getElementById('drawer_order_code').textContent = ord.order_code || 'ORD-' + ord.id;
            
            // Format Badge Color
            let badgeClass = 'bg-slate-100 text-slate-700';
            const st = (ord.status || '').toUpperCase();
            if (st === 'PENDING') badgeClass = 'bg-amber-100 text-amber-800';
            else if (st === 'ACCEPTED') badgeClass = 'bg-purple-100 text-purple-800';
            else if (st === 'HANDOVER_TO_CENTER') badgeClass = 'bg-orange-100 text-orange-800';
            else if (st === 'RECEIVED_AT_CENTER') badgeClass = 'bg-indigo-100 text-indigo-800';
            else if (st === 'SHIPPED' || st === 'PACKED' || st === 'CONFIRMED') badgeClass = 'bg-blue-100 text-blue-800';
            else if (st === 'OUT_FOR_DELIVERY') badgeClass = 'bg-teal-100 text-teal-800';
            else if (st === 'DELIVERED' || st === 'COMPLETED') badgeClass = 'bg-emerald-100 text-emerald-800';
            else if (st === 'CANCELLED') badgeClass = 'bg-rose-100 text-rose-800';

            const badgeEl = document.getElementById('drawer_status_badge');
            badgeEl.textContent = ord.status || 'PENDING';
            badgeEl.className = `px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider ${badgeClass}`;

            document.getElementById('drawer_created_at').textContent = 'Placed on ' + (ord.created_at || 'Recently');
            document.getElementById('drawer_invoice_link').href = 'order-invoice.php?id=' + ord.id;

            // Handle Cancellation Reason
            const cancelBox = document.getElementById('drawer_cancellation_reason_box');
            if (cancelBox) {
                if (st === 'CANCELLED' && ord.cancellation_reason) {
                    cancelBox.classList.remove('hidden');
                    document.getElementById('drawer_cancellation_reason_text').textContent = ord.cancellation_reason;
                } else {
                    cancelBox.classList.add('hidden');
                }
            }

            // Details tab
            const buyer = ord.shipping_name || ((ord.first_name || '') + ' ' + (ord.last_name || '')).trim() || 'Valued Buyer';
            document.getElementById('drawer_buyer_name').textContent = buyer;
            document.getElementById('drawer_buyer_phone').textContent = ord.phone1 || ord.buyer_phone || ord.user_phone || 'N/A';
            document.getElementById('drawer_buyer_email').textContent = ord.shipping_email || ord.email || 'N/A';

            const addr = [ord.address_line1, ord.address_line2].filter(Boolean).join(', ') || 'Address not recorded';
            document.getElementById('drawer_delivery_address').textContent = addr;
            document.getElementById('drawer_city_province').textContent = [ord.city, ord.province].filter(Boolean).join(', ') || '';

            document.getElementById('drawer_courier_name').textContent = ord.courier_company || 'Koombiyo Delivery';
            document.getElementById('drawer_tracking_number').textContent = ord.tracking_number || 'Tracking Pending';

            // Items
            document.getElementById('drawer_items_count').textContent = items.length + ' Item' + (items.length > 1 ? 's' : '');
            const itemsContainer = document.getElementById('drawer_items_list');
            itemsContainer.innerHTML = '';

            items.forEach(it => {
                let img = it.product_image || '';
                let imgSrc;
                if (!img) {
                    imgSrc = '../assets/uploads/products/placeholder.webp';
                } else if (img.startsWith('http')) {
                    imgSrc = img;
                } else if (img.startsWith('assets/') || img.startsWith('image/')) {
                    imgSrc = '../' + img;
                } else {
                    imgSrc = '../assets/uploads/products/' + img.replace(/^(\.\.\/|\.\/)+/, '');
                }
                itemsContainer.insertAdjacentHTML('beforeend', `
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3 last:border-0">
                        <div class="flex items-center gap-3">
                            <img src="${imgSrc}" onerror="this.onerror=null;this.src='../assets/uploads/products/placeholder.webp'" class="w-12 h-12 rounded-lg object-cover border border-slate-200 shadow-sm shrink-0">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">${it.brand_name || 'OXXA GEAR'}</span>
                                <h5 class="font-bold text-slate-900 text-xs">${it.product_name || 'Item'}</h5>
                                <div class="text-[10px] text-slate-500 font-medium">SKU: ${it.sku || 'PRD'} | Qty: <b>${it.quantity}</b></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-slate-900 text-xs">Rs. ${parseFloat(it.total_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                            <div class="text-[10px] text-slate-400">Rs. ${parseFloat(it.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})} each</div>
                        </div>
                    </div>
                `);
            });

            // Financials
            document.getElementById('drawer_fin_subtotal').textContent = 'Rs. ' + parseFloat(fin.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_fin_delivery').textContent = 'Rs. ' + parseFloat(fin.delivery_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_fin_gateway').textContent = '- Rs. ' + parseFloat(fin.gateway_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_fin_commission').textContent = 'Rs. ' + parseFloat(fin.commission || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_fin_payout').textContent = 'Rs. ' + parseFloat(fin.net_payout || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_fin_total').textContent = 'Rs. ' + parseFloat(fin.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});

            // Payments Tab
            document.getElementById('drawer_pay_method').textContent = (ord.payment_method || 'COD').toUpperCase();
            document.getElementById('drawer_pay_fee').textContent = 'Rs. ' + parseFloat(fin.gateway_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('drawer_pay_net').textContent = 'Rs. ' + parseFloat((fin.total_amount || 0) - (fin.gateway_fee || 0)).toLocaleString('en-US', {minimumFractionDigits: 2});

            const slipBox = document.getElementById('drawer_bank_slip_box');
            slipBox.classList.add('hidden');

            // Notes Tab
            document.getElementById('drawer_notes_input').value = ord.internal_notes || '';

            // Timeline Tab
            const timelineEvents = res.timeline || [];
            const timelineContainer = document.getElementById('drawer_timeline_container');
            timelineContainer.innerHTML = '';
            
            timelineEvents.forEach(ev => {
                const isPassed = ev.completed;
                const circleBg = isPassed ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500';
                timelineContainer.insertAdjacentHTML('beforeend', `
                    <div class="relative">
                        <span class="absolute -left-[31px] top-0.5 w-5 h-5 rounded-full ${circleBg} flex items-center justify-center text-[9px] shadow-sm">
                            <i class="fas ${isPassed ? 'fa-check' : 'fa-circle'}"></i>
                        </span>
                        <div class="font-bold text-slate-900 text-xs">${ev.title}</div>
                        <div class="text-[10px] text-slate-500">${ev.date || 'Pending'}</div>
                        <p class="text-[11px] text-slate-600 mt-0.5">${ev.description || ''}</p>
                    </div>
                `);
            });

            // Activity Log Tab
            const actContainer = document.getElementById('drawer_activity_log');
            actContainer.innerHTML = '';
            const logs = res.activity_log || [];
            if (logs.length > 0) {
                logs.forEach(l => {
                    actContainer.insertAdjacentHTML('beforeend', `
                        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                            <div class="flex justify-between items-center text-[10px] text-slate-400 mb-1">
                                <span class="font-bold text-slate-700">${l.action}</span>
                                <span>${l.timestamp}</span>
                            </div>
                            <p class="text-xs text-slate-600 font-medium">${l.details}</p>
                        </div>
                    `);
                });
            } else {
                actContainer.innerHTML = '<p class="text-slate-400 text-xs">No activity logged yet.</p>';
            }
        });
    }

    function closeOrderDrawer() {
        document.getElementById('orderDrawer').classList.add('translate-x-full');
        document.getElementById('drawerOverlay').classList.add('hidden');
        activeDrawerOrderId = null;
    }

    function switchDrawerTab(tabKey) {
        document.querySelectorAll('.drawer-tab-btn').forEach(btn => {
            if (btn.dataset.tab === tabKey) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        document.querySelectorAll('.drawer-tab-pane').forEach(pane => {
            pane.classList.add('hidden');
        });
        document.getElementById('tab_' + tabKey).classList.remove('hidden');
    }

    function saveInternalNotes() {
        if (!activeDrawerOrderId) return;
        const notes = document.getElementById('drawer_notes_input').value;

        fetch('api/bulk-orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_note',
                order_id: activeDrawerOrderId,
                note: notes
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Saved',
                    text: 'Internal notes saved successfully',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    function verifyBankSlip() {
        if (!activeDrawerOrderId) return;
        quickUpdateStatus(activeDrawerOrderId, 'CONFIRMED');
    }

    // 9. Quick Status Modal Logic
    function openQuickStatusModal(orderId, orderCode, currentStatus, courier, tracking) {
        document.getElementById('quick_order_id').value = orderId;
        document.getElementById('quick_order_code_title').textContent = orderCode;
        document.getElementById('quick_status_select').value = currentStatus;
        document.getElementById('quick_courier_select').value = courier || 'Koombiyo Delivery';
        document.getElementById('quick_tracking_input').value = tracking || '';

        toggleQuickCourierFields();
        document.getElementById('quickStatusModal').classList.remove('hidden');
        document.getElementById('quickStatusModal').classList.add('flex');
    }

    function closeQuickStatusModal() {
        document.getElementById('quickStatusModal').classList.add('hidden');
        document.getElementById('quickStatusModal').classList.remove('flex');
    }

    function toggleQuickCourierFields() {
        const st = document.getElementById('quick_status_select').value;
        const sec = document.getElementById('quick_courier_section');
        if (['PACKED', 'SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERED'].includes(st)) {
            sec.classList.remove('hidden');
        } else {
            sec.classList.add('hidden');
        }
    }

    function submitQuickStatus(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = document.getElementById('quickSubmitBtn');
        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('api/update-order-status.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                Swal.fire('Error', data.message || 'Update failed', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Order Status';
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Save Order Status';
            Swal.fire('Error', err.message, 'error');
        });
    }

    function openDrawerEditCourier() {
        if (!activeDrawerOrderId) return;
        const code = document.getElementById('drawer_order_code').textContent;
        const courier = document.getElementById('drawer_courier_name').textContent;
        const trk = document.getElementById('drawer_tracking_number').textContent;
        openQuickStatusModal(activeDrawerOrderId, code, 'SHIPPED', courier, trk);
    }
    </script>

</body>
</html>
