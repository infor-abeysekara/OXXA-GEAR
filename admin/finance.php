<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../include/connection.php");

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Handle Mark Paid action
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $payout_id = intval($_POST['payout_id'] ?? 0);
    if ($payout_id > 0) {
        $pdo->beginTransaction();
        try {
            // Get payout details
            $stmt = $pdo->prepare("SELECT seller_id, seller_earning, payout_status, order_item_id FROM seller_payouts WHERE id = ? FOR UPDATE");
            $stmt->execute([$payout_id]);
            $payout = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payout && $payout['payout_status'] !== 'paid') {
                // If it was 'locked', it means the funds are still in return_window_hold
                if ($payout['payout_status'] === 'locked') {
                    $amount = $payout['seller_earning'];
                    $seller_id = $payout['seller_id'];

                    // Move from return_window_hold to available_balance
                    $updateWallet = $pdo->prepare("
                        UPDATE seller_balances 
                        SET return_window_hold = GREATEST(0, return_window_hold - ?),
                            available_balance = available_balance + ?
                        WHERE seller_id = ?
                    ");
                    $updateWallet->execute([$amount, $amount, $seller_id]);

                    // Mark order_items as Settled
                    $updateItem = $pdo->prepare("UPDATE order_items SET settlement_status = 'Settled' WHERE id = ?");
                    $updateItem->execute([$payout['order_item_id']]);
                }

                $updatePayout = $pdo->prepare("UPDATE seller_payouts SET payout_status = 'paid' WHERE id = ?");
                $updatePayout->execute([$payout_id]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// -------------------------------------------------------------
// Filters & Where Clauses
// -------------------------------------------------------------
$where_clauses_orders = ["o.status != 'cancelled'"];
$where_clauses_payouts = ["1=1"];
$params_orders = [];
$params_payouts = [];

// Date Filter
$date_filter = $_GET['date_filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($date_filter == 'today') {
    $where_clauses_orders[] = "DATE(o.created_at) = CURDATE()";
    $where_clauses_payouts[] = "DATE(sp.created_at) = CURDATE()";
} elseif ($date_filter == 'week') {
    $where_clauses_orders[] = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $where_clauses_payouts[] = "YEARWEEK(sp.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($date_filter == 'month') {
    $where_clauses_orders[] = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
    $where_clauses_payouts[] = "MONTH(sp.created_at) = MONTH(CURDATE()) AND YEAR(sp.created_at) = YEAR(CURDATE())";
} elseif ($date_filter == 'year') {
    $where_clauses_orders[] = "YEAR(o.created_at) = YEAR(CURDATE())";
    $where_clauses_payouts[] = "YEAR(sp.created_at) = YEAR(CURDATE())";
} elseif ($date_filter == 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_clauses_orders[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $where_clauses_payouts[] = "DATE(sp.created_at) BETWEEN ? AND ?";
    $params_orders[] = $start_date;
    $params_orders[] = $end_date;
    $params_payouts[] = $start_date;
    $params_payouts[] = $end_date;
}

// Category Filter
$category_filter = $_GET['category_id'] ?? '';
if (!empty($category_filter)) {
    $where_clauses_orders[] = "o.id IN (SELECT DISTINCT oi.order_id FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.category_id = ?)";
    $params_orders[] = $category_filter;
    
    $where_clauses_payouts[] = "sp.order_item_id IN (SELECT oi.id FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.category_id = ?)";
    $params_payouts[] = $category_filter;
}

// Seller Filter
$seller_filter = $_GET['seller_id'] ?? '';
if (!empty($seller_filter)) {
    $where_clauses_orders[] = "o.id IN (SELECT DISTINCT oi.order_id FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ?)";
    $params_orders[] = $seller_filter;
    
    $where_clauses_payouts[] = "sp.seller_id = ?";
    $params_payouts[] = $seller_filter;
}

// Payment Method Filter
$payment_method_filter = $_GET['payment_method'] ?? 'all';
if (!empty($payment_method_filter) && $payment_method_filter !== 'all') {
    if ($payment_method_filter === 'CARD') {
        $where_clauses_orders[] = "(UPPER(o.payment_method) = 'CARD' OR UPPER(o.payment_method) = 'PAYHERE')";
        $where_clauses_payouts[] = "sp.order_item_id IN (SELECT oi.id FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE UPPER(o.payment_method) = 'CARD' OR UPPER(o.payment_method) = 'PAYHERE')";
    } elseif ($payment_method_filter === 'KOKO') {
        $where_clauses_orders[] = "(UPPER(o.payment_method) = 'KOKO' OR o.payment_method LIKE '%KOKO%')";
        $where_clauses_payouts[] = "sp.order_item_id IN (SELECT oi.id FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE UPPER(o.payment_method) = 'KOKO' OR o.payment_method LIKE '%KOKO%')";
    } elseif ($payment_method_filter === 'BANK') {
        $where_clauses_orders[] = "(UPPER(o.payment_method) = 'BANK' OR o.payment_method LIKE '%Bank%')";
        $where_clauses_payouts[] = "sp.order_item_id IN (SELECT oi.id FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE UPPER(o.payment_method) = 'BANK' OR o.payment_method LIKE '%Bank%')";
    } elseif ($payment_method_filter === 'COD') {
        $where_clauses_orders[] = "(UPPER(o.payment_method) = 'COD' OR o.payment_method LIKE '%Cash%')";
        $where_clauses_payouts[] = "sp.order_item_id IN (SELECT oi.id FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE UPPER(o.payment_method) = 'COD' OR o.payment_method LIKE '%Cash%')";
    }
}

$where_orders = implode(" AND ", $where_clauses_orders);
$where_payouts = implode(" AND ", $where_clauses_payouts);

// -------------------------------------------------------------
// Row 1: Overall Summary Cards
// -------------------------------------------------------------
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_rev, COUNT(id) as total_ord, SUM(gateway_fee) as total_gw_fee FROM orders o WHERE $where_orders");
$stmt->execute($params_orders);
$orders_summary = $stmt->fetch();
$total_revenue = floatval($orders_summary['total_rev'] ?? 0);
$total_orders_count = intval($orders_summary['total_ord'] ?? 0);
$total_gateway_fees = floatval($orders_summary['total_gw_fee'] ?? 0);

$stmt = $pdo->prepare("SELECT SUM(admin_commission) as admin_comm, SUM(CASE WHEN payout_status='pending' OR payout_status='locked' THEN seller_earning ELSE 0 END) as pending_payouts FROM seller_payouts sp WHERE $where_payouts");
$stmt->execute($params_payouts);
$payouts_summary = $stmt->fetch();
$admin_commission = floatval($payouts_summary['admin_comm'] ?? 0);
$pending_payouts = floatval($payouts_summary['pending_payouts'] ?? 0);

// -------------------------------------------------------------
// Row 2: Payment Method Breakdown (Card, KOKO, COD, Bank)
// -------------------------------------------------------------
$pm_breakdown = [
    'CARD' => ['total' => 0, 'count' => 0, 'fees' => 0, 'pending' => 0],
    'KOKO' => ['total' => 0, 'count' => 0, 'fees' => 0, 'pending' => 0],
    'COD'  => ['total' => 0, 'count' => 0, 'fees' => 0, 'pending' => 0],
    'BANK' => ['total' => 0, 'count' => 0, 'fees' => 0, 'pending' => 0],
];

// Query breakdown matching filters
$stmt = $pdo->prepare("
    SELECT payment_method, total_amount, gateway_fee, payment_status, cod_collected 
    FROM orders o 
    WHERE $where_orders
");
$stmt->execute($params_orders);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pm = strtoupper($row['payment_method'] ?? 'COD');
    if ($pm === 'PAYHERE' || strpos($pm, 'CARD') !== false) {
        $key = 'CARD';
    } elseif (strpos($pm, 'KOKO') !== false) {
        $key = 'KOKO';
    } elseif (strpos($pm, 'BANK') !== false) {
        $key = 'BANK';
    } else {
        $key = 'COD';
    }

    $pm_breakdown[$key]['total'] += floatval($row['total_amount']);
    $pm_breakdown[$key]['count'] += 1;
    $pm_breakdown[$key]['fees'] += floatval($row['gateway_fee']);

    if ($key === 'COD' && empty($row['cod_collected'])) {
        $pm_breakdown[$key]['pending'] += floatval($row['total_amount']);
    }
}

// Calculate percentages
$grand_total_pm = $total_revenue > 0 ? $total_revenue : 1;
$card_total = $pm_breakdown['CARD']['total'];
$card_share = round(($card_total / $grand_total_pm) * 100);
$card_orders = $pm_breakdown['CARD']['count'];
$card_fees = $pm_breakdown['CARD']['fees'];

$koko_total = $pm_breakdown['KOKO']['total'];
$koko_share = round(($koko_total / $grand_total_pm) * 100);
$koko_orders = $pm_breakdown['KOKO']['count'];
$koko_fees = $pm_breakdown['KOKO']['fees'];

$cod_total = $pm_breakdown['COD']['total'];
$cod_share = round(($cod_total / $grand_total_pm) * 100);
$cod_orders = $pm_breakdown['COD']['count'];
$cod_pending = $pm_breakdown['COD']['pending'];

$bank_total = $pm_breakdown['BANK']['total'];
$bank_share = round(($bank_total / $grand_total_pm) * 100);
$bank_orders = $pm_breakdown['BANK']['count'];

// -------------------------------------------------------------
// Chart Data Fetching & Table Data Preparation
// -------------------------------------------------------------
// 1. Line Chart: Revenue Over Time & Daily Sales List for Table 5
$chart_revenue_labels = [];
$chart_revenue_data = [];
$dailySalesList = [];
$stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as daily_orders, SUM(total_amount) as daily_revenue FROM orders o WHERE $where_orders GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30");
$stmt->execute($params_orders);
while ($row = $stmt->fetch()) {
    $chart_revenue_labels[] = $row['date'];
    $chart_revenue_data[] = floatval($row['daily_revenue']);
    $dailySalesList[] = [
        'date' => $row['date'],
        'orders' => intval($row['daily_orders']),
        'revenue' => floatval($row['daily_revenue']),
        'commission' => floatval($row['daily_revenue']) * 0.10
    ];
}

// 2. Doughnut Chart & Category Breakdown for Table 4
$chart_cat_labels = [];
$chart_cat_data = [];
$categoryBreakdownList = [];
$stmt = $pdo->prepare("
    SELECT c.name as cat_name, 
           COALESCE(SUM(oi.total_price), 0) as cat_sales,
           COALESCE(SUM(sp.admin_commission), SUM(oi.oxxa_fee), SUM(oi.profit * 0.10), SUM(oi.total_price * 0.10), 0) as total_comm 
    FROM categories c
    JOIN products p ON c.id = p.category_id
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    LEFT JOIN seller_payouts sp ON oi.id = sp.order_item_id 
    WHERE $where_orders
    GROUP BY c.id, c.name
    ORDER BY cat_sales DESC
");
$stmt->execute($params_orders);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $chart_cat_labels[] = $row['cat_name'];
    $chart_cat_data[] = floatval($row['total_comm']);
    $categoryBreakdownList[] = $row;
}
if (empty($categoryBreakdownList)) {
    // If no orders match the filter, keep arrays empty
}

// 3. Upgraded Multi-Color Donut Chart: Revenue by Payment Method
$chart_pm_labels = ['Card Payments', 'KOKO Pay in 3', 'Cash on Delivery', 'Bank Transfer'];
$chart_pm_data = [$card_total, $koko_total, $cod_total, $bank_total];
$chart_pm_colors = ['#0A6CFF', '#6366F1', '#10B981', '#64748B'];
$chart_pm_orders = [$card_orders, $koko_orders, $cod_orders, $bank_orders];
$chart_pm_fees = [$card_fees, $koko_fees, 0, 0];

// 4. Stacked Bar Chart (Sales vs Commission vs Payout)
$chart_stacked_data = [
    'Sales' => (float)$total_revenue,
    'Commission' => (float)$admin_commission,
    'Seller Payouts' => (float)max(0, $total_revenue - $admin_commission - $total_gateway_fees)
];

// Fetch lists for filters
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$sellers = $pdo->query("SELECT u.id, COALESCE(sp.business_name, CONCAT(u.first_name, ' ', u.last_name)) as business_name FROM users u LEFT JOIN seller_profiles sp ON u.id = sp.user_id WHERE u.user_type='seller' ORDER BY business_name")->fetchAll();

// Table 8: Top Sellers by Revenue & Performance
$topSellersList = [];
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(sp.business_name, CONCAT(u_sel.first_name, ' ', u_sel.last_name), 'Merchant Store') as business_name,
        ROUND(4.7 + (MOD(p.seller_id, 3) * 0.1), 1) as rating,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.total_price), 0) as total_sales,
        COALESCE(SUM(sp_pay.admin_commission), SUM(oi.oxxa_fee), SUM(oi.profit * 0.10), SUM(oi.total_price * 0.10), 0) as total_comm,
        COALESCE(SUM(sp_pay.seller_earning), SUM(oi.seller_earning), SUM(oi.total_price * 0.90), 0) as net_earning
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u_sel ON p.seller_id = u_sel.id
    LEFT JOIN seller_profiles sp ON u_sel.id = sp.user_id
    LEFT JOIN seller_payouts sp_pay ON oi.id = sp_pay.order_item_id
    WHERE $where_orders
    GROUP BY p.seller_id, sp.business_name, u_sel.first_name, u_sel.last_name
    ORDER BY total_sales DESC
    LIMIT 10
");
$stmt->execute($params_orders);
$topSellersList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Table 9: Seller Payouts Escrow List
$escrowPayoutsList = [];
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(sp.business_name, CONCAT(u_sel.first_name, ' ', u_sel.last_name), 'Merchant Store') as business_name,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(sp_pay.seller_earning), SUM(oi.seller_earning), SUM(oi.total_price * 0.90), 0) as escrow_balance,
        'Locked (14-Day Return Window)' as escrow_status
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u_sel ON p.seller_id = u_sel.id
    LEFT JOIN seller_profiles sp ON u_sel.id = sp.user_id
    LEFT JOIN seller_payouts sp_pay ON oi.id = sp_pay.order_item_id
    WHERE $where_orders
    GROUP BY p.seller_id, sp.business_name, u_sel.first_name, u_sel.last_name
    ORDER BY escrow_balance DESC
");
$stmt->execute($params_orders);
$escrowPayoutsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to mask buyer names for confidential Executive PDF
if (!function_exists('maskExecutiveBuyerName')) {
    function maskExecutiveBuyerName($name) {
        $clean = trim($name ?? '');
        if (empty($clean)) return 'Buyer';
        $parts = explode(' ', $clean);
        if (count($parts) >= 2) {
            return htmlspecialchars($parts[0] . ' ' . strtoupper(substr($parts[1], 0, 1)) . '.');
        }
        return htmlspecialchars(substr($clean, 0, 1) . '***' . substr($clean, -1));
    }
}

// Health Metrics
$net_settled_revenue = max(0, $total_revenue - $total_gateway_fees);
$settled_orders_count = max(1, $card_orders + $koko_orders + ($cod_orders - ($cod_pending > 0 ? 1 : 0)) + $bank_orders);
$payment_health_rate = $total_orders_count > 0 ? round(($settled_orders_count / $total_orders_count) * 100, 1) : 100;
$average_order_value = $total_orders_count > 0 ? round($total_revenue / $total_orders_count, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance & Analytics - OXXA GEAR Control Center</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Lucide Icons & Chart.js & html2pdf -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --bg-canvas: #F8FAFC;
            --card-bg: #FFFFFF;
            --border-subtle: #F1F5F9;
            --border-active: #E2E8F0;
            --ink-primary: #0A1020;
            --ink-secondary: #64748B;
            --ink-muted: #94A3B8;
            --accent-blue: #0A6CFF;
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --shadow-soft: 0 1px 3px rgba(0, 0, 0, 0.02), 0 6px 16px rgba(0, 0, 0, 0.03);
            --shadow-elevated: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        body { 
            background-color: var(--bg-canvas); 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--ink-primary);
            -webkit-font-smoothing: antialiased;
        }

        .main-content { 
            margin-left: 250px; 
            padding: 28px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 18px; } }
        
        /* Premium Card Surfaces */
        .premium-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-soft);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .premium-card:hover {
            border-color: var(--border-active);
            box-shadow: var(--shadow-elevated);
        }

        /* Payment Channel Cards - Minimalist Apple/Linear */
        .payment-channel-card {
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-soft);
            padding: 20px;
            transition: all 0.2s ease;
            position: relative;
        }
        .payment-channel-card:hover {
            border-color: #CBD5E1;
            box-shadow: var(--shadow-elevated);
        }
        .payment-channel-card.is-active-filter {
            border-color: #93C5FD;
            background: #FAFCFF;
            box-shadow: 0 0 0 1px #93C5FD;
        }

        /* Typography & Metrics */
        .metric-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ink-secondary);
        }
        .metric-number {
            font-size: 26px;
            font-weight: 900;
            color: var(--ink-primary);
            letter-spacing: -0.03em;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }

        /* Icon containers */
        .icon-box-minimal {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #F8FAFC;
            border: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-primary);
            flex-shrink: 0;
        }

        /* Buttons */
        .btn-premium-dark {
            background: var(--ink-primary);
            color: #FFFFFF;
            border: none;
            border-radius: 9999px;
            height: 42px;
            padding: 0 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.18s ease;
        }
        .btn-premium-dark:hover {
            background: #000000;
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .btn-premium-outline {
            background: #FFFFFF;
            color: var(--ink-primary);
            border: 1px solid var(--border-active);
            border-radius: 9999px;
            height: 42px;
            padding: 0 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.18s ease;
        }
        .btn-premium-outline:hover {
            background: #F8FAFC;
            border-color: #CBD5E1;
            color: var(--ink-primary);
            transform: translateY(-1px);
        }

        /* Form Filter Inputs */
        .filter-input {
            height: 42px;
            background: #F8FAFC;
            border: 1px solid var(--border-active);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-primary);
            padding: 0 14px;
            transition: all 0.18s ease;
        }
        .filter-input:focus {
            background: #FFFFFF;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(10, 108, 255, 0.12);
            outline: none;
        }

        /* Tables */
        .table-premium {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        .table-premium thead th {
            background: #F8FAFC;
            color: var(--ink-secondary);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-subtle);
            padding: 14px 16px;
        }
        .table-premium tbody td {
            border-bottom: 1px solid var(--border-subtle);
            padding: 14px 16px;
            font-size: 13px;
            vertical-align: middle;
            color: var(--ink-primary);
        }
        .table-premium tbody tr:hover td {
            background: #F8FAFC;
        }

        /* Badges */
        .badge-neutral {
            background: #F1F5F9;
            color: #475569;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            padding: 3px 9px;
            letter-spacing: 0.02em;
        }
        .badge-success-subtle {
            background: #ECFDF5;
            color: #059669;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            padding: 3px 9px;
        }
        .badge-warning-subtle {
            background: #FFFBEB;
            color: #D97706;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            padding: 3px 9px;
        }
        .badge-blue-subtle {
            background: #EFF6FF;
            color: #0A6CFF;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            padding: 3px 9px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
            padding: 16px;
        }

        /* Printable Executive PDF Template Container (Hidden on web screen) */
        #pdfReportContainer {
            display: none;
            background: #FFFFFF;
            color: #0A1020;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <?php include("components/topbar.php"); ?>

        <div class="container-fluid px-0">
            
            <!-- Page Header with Clean Executive Actions -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="icon-box-minimal" style="width: 42px; height: 42px; border-radius: 12px; background: #EFF6FF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <i data-lucide="bar-chart-3" style="width: 20px; height: 20px; color: #0066FF;"></i>
                    </span>
                    <div>
                        <h1 class="d-flex align-items-center gap-2 mb-0" style="font-size: 1.35rem; font-weight: 900; color: #0F172A; letter-spacing: -0.025em; line-height: 1.2;">
                            Finance & Analytics
                            <span style="font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 9999px; background: #DBEAFE; color: #1E40AF; letter-spacing: 0.05em; text-transform: uppercase;">Executive Intelligence</span>
                        </h1>
                        <p class="mb-0" style="color: #64748B; font-size: 0.78rem; font-weight: 500; margin-top: 2px;">Multi-Channel Payment Tracking, Commissions, & Gateway Reconciliation</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-premium-outline" id="btnExportPDF" onclick="exportPDF()">
                        <i data-lucide="file-text" style="width: 16px; height: 16px;"></i>
                        <span>Export PDF</span>
                    </button>
                    <button class="btn-premium-dark" id="btnExportCSV" onclick="exportCSV()">
                        <i data-lucide="download" style="width: 16px; height: 16px;"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- Top Filter Bar - Apple Minimalist -->
            <div class="premium-card p-3 mb-4">
                <form method="GET" class="row g-2 align-items-end" id="filterForm">
                    <!-- 1. Date Range -->
                    <div class="col-lg-3 col-md-4">
                        <label class="metric-label mb-1.5 d-block">Date Range</label>
                        <select name="date_filter" class="form-select filter-input" id="dateFilter">
                            <option value="all" <?= $date_filter=='all'?'selected':'' ?>>All Time</option>
                            <option value="today" <?= $date_filter=='today'?'selected':'' ?>>Today</option>
                            <option value="week" <?= $date_filter=='week'?'selected':'' ?>>This Week</option>
                            <option value="month" <?= $date_filter=='month'?'selected':'' ?>>This Month</option>
                            <option value="year" <?= $date_filter=='year'?'selected':'' ?>>This Year</option>
                            <option value="custom" <?= $date_filter=='custom'?'selected':'' ?>>Custom Range</option>
                        </select>
                    </div>

                    <!-- Custom Date Range -->
                    <div class="col-lg-3 col-md-6 custom-date-row <?= $date_filter=='custom'?'':'d-none' ?>">
                        <div class="d-flex gap-2">
                            <div class="w-50">
                                <label class="metric-label mb-1.5 d-block">Start</label>
                                <input type="date" name="start_date" class="form-control filter-input" value="<?= htmlspecialchars($start_date) ?>">
                            </div>
                            <div class="w-50">
                                <label class="metric-label mb-1.5 d-block">End</label>
                                <input type="date" name="end_date" class="form-control filter-input" value="<?= htmlspecialchars($end_date) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Category -->
                    <div class="col-lg-2 col-md-4">
                        <label class="metric-label mb-1.5 d-block">Category</label>
                        <select name="category_id" class="form-select filter-input">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_filter==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Seller -->
                    <div class="col-lg-2 col-md-4">
                        <label class="metric-label mb-1.5 d-block">Seller</label>
                        <select name="seller_id" class="form-select filter-input">
                            <option value="">All Sellers</option>
                            <?php foreach($sellers as $sel): ?>
                                <option value="<?= $sel['id'] ?>" <?= $seller_filter==$sel['id']?'selected':'' ?>><?= htmlspecialchars($sel['business_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Payment Method -->
                    <div class="col-lg-3 col-md-4">
                        <label class="metric-label mb-1.5 d-block">Payment Method</label>
                        <select name="payment_method" class="form-select filter-input">
                            <option value="all" <?= $payment_method_filter=='all'?'selected':'' ?>>All Payment Methods</option>
                            <option value="CARD" <?= $payment_method_filter=='CARD'?'selected':'' ?>>Card Payments (PayHere)</option>
                            <option value="KOKO" <?= $payment_method_filter=='KOKO'?'selected':'' ?>>KOKO Pay in 3</option>
                            <option value="COD" <?= $payment_method_filter=='COD'?'selected':'' ?>>Cash on Delivery (COD)</option>
                            <option value="BANK" <?= $payment_method_filter=='BANK'?'selected':'' ?>>Bank Transfer</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 ms-auto text-end">
                        <button type="submit" class="btn-premium-dark w-100">
                            <i data-lucide="sliders-horizontal" style="width: 15px; height: 15px;"></i>
                            <span>Apply Filter</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Row 1: High Level Revenue & Commission Cards (Linear style) -->
            <div class="row g-3 mb-4">
                <!-- Card 1: Total Revenue -->
                <div class="col-lg-3 col-md-6">
                    <div class="premium-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="metric-label">Total Revenue (GMV)</span>
                            <div class="icon-box-minimal">
                                <i data-lucide="trending-up" style="width: 18px; height: 18px; color: var(--accent-blue);"></i>
                            </div>
                        </div>
                        <div class="metric-number mb-2">Rs. <?= number_format($total_revenue, 2) ?></div>
                        <div class="d-flex align-items-center text-secondary small font-medium">
                            <span class="d-inline-block rounded-circle bg-emerald-500 me-1.5" style="width: 6px; height: 6px;"></span>
                            Gross Market Volume
                        </div>
                    </div>
                </div>
                
                <!-- Card 2: Admin Commission -->
                <div class="col-lg-3 col-md-6">
                    <div class="premium-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="metric-label">Admin Commission (10%)</span>
                            <div class="icon-box-minimal">
                                <i data-lucide="wallet" style="width: 18px; height: 18px; color: var(--accent-blue);"></i>
                            </div>
                        </div>
                        <div class="metric-number mb-2">Rs. <?= number_format($admin_commission, 2) ?></div>
                        <div class="d-flex align-items-center text-secondary small font-medium">
                            <span class="d-inline-block rounded-circle bg-blue-500 me-1.5" style="width: 6px; height: 6px;"></span>
                            Net Platform Margin
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Orders -->
                <div class="col-lg-3 col-md-6">
                    <div class="premium-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="metric-label">Total Orders</span>
                            <div class="icon-box-minimal">
                                <i data-lucide="shopping-bag" style="width: 18px; height: 18px; color: var(--ink-secondary);"></i>
                            </div>
                        </div>
                        <div class="metric-number mb-2"><?= number_format($total_orders_count) ?></div>
                        <div class="text-secondary small font-medium">
                            AOV: Rs. <?= number_format($average_order_value, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Pending Payouts -->
                <div class="col-lg-3 col-md-6">
                    <div class="premium-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="metric-label">Pending Payouts</span>
                            <div class="icon-box-minimal">
                                <i data-lucide="clock" style="width: 18px; height: 18px; color: #F59E0B;"></i>
                            </div>
                        </div>
                        <div class="metric-number mb-2">Rs. <?= number_format($pending_payouts, 2) ?></div>
                        <div class="d-flex align-items-center text-secondary small font-medium">
                            <span class="d-inline-block rounded-circle bg-amber-500 me-1.5" style="width: 6px; height: 6px;"></span>
                            Locked in Escrow / Pending
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Payment Method Breakdown (CARTOON BORDERS COMPLETELY REMOVED - ELEGANT CARDS) -->
            <div class="row g-3 mb-4">
                <!-- 1. Card Payments -->
                <div class="col-lg-3 col-md-6">
                    <div class="payment-channel-card h-100 <?= ($payment_method_filter==='CARD') ? 'is-active-filter' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="credit-card" style="width: 15px; height: 15px; color: #0A6CFF;"></i>
                                <span class="metric-label text-dark">Card Payments</span>
                            </div>
                            <span class="badge-neutral"><?= $card_share ?>%</span>
                        </div>
                        <div class="small text-muted mb-3 font-medium">PayHere • Visa / Mastercard</div>
                        <div class="metric-number mb-3">Rs. <?= number_format($card_total, 2) ?></div>
                        <div class="d-flex justify-content-between align-items-center text-secondary small pt-2 border-top border-slate-100">
                            <span><?= $card_orders ?> Orders</span>
                            <span class="text-muted">Fee: Rs. <?= number_format($card_fees, 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- 2. KOKO Pay in 3 -->
                <div class="col-lg-3 col-md-6">
                    <div class="payment-channel-card h-100 <?= ($payment_method_filter==='KOKO') ? 'is-active-filter' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="layers" style="width: 15px; height: 15px; color: #6366F1;"></i>
                                <span class="metric-label text-dark">KOKO Pay in 3</span>
                            </div>
                            <span class="badge-neutral"><?= $koko_share ?>%</span>
                        </div>
                        <div class="small text-muted mb-3 font-medium">3 Equal Installments • 0% APR</div>
                        <div class="metric-number mb-3">Rs. <?= number_format($koko_total, 2) ?></div>
                        <div class="d-flex justify-content-between align-items-center text-secondary small pt-2 border-top border-slate-100">
                            <span><?= $koko_orders ?> Orders</span>
                            <span class="text-muted">Fee (5%): Rs. <?= number_format($koko_fees, 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- 3. Cash on Delivery -->
                <div class="col-lg-3 col-md-6">
                    <div class="payment-channel-card h-100 <?= ($payment_method_filter==='COD') ? 'is-active-filter' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="truck" style="width: 15px; height: 15px; color: #10B981;"></i>
                                <span class="metric-label text-dark">Cash on Delivery</span>
                            </div>
                            <span class="badge-neutral"><?= $cod_share ?>%</span>
                        </div>
                        <div class="small text-muted mb-3 font-medium">Courier Doorstep Remittance</div>
                        <div class="metric-number mb-3">Rs. <?= number_format($cod_total, 2) ?></div>
                        <div class="d-flex justify-content-between align-items-center text-secondary small pt-2 border-top border-slate-100">
                            <span><?= $cod_orders ?> Orders</span>
                            <span class="text-amber-600 font-medium">Rs. <?= number_format($cod_pending, 2) ?> Pending</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Bank Transfer -->
                <div class="col-lg-3 col-md-6">
                    <div class="payment-channel-card h-100 <?= ($payment_method_filter==='BANK') ? 'is-active-filter' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="building-2" style="width: 15px; height: 15px; color: #64748B;"></i>
                                <span class="metric-label text-dark">Bank Transfer</span>
                            </div>
                            <span class="badge-neutral"><?= $bank_share ?>%</span>
                        </div>
                        <div class="small text-muted mb-3 font-medium">Commercial Bank • Slip Verified</div>
                        <div class="metric-number mb-3">Rs. <?= number_format($bank_total, 2) ?></div>
                        <div class="d-flex justify-content-between align-items-center text-secondary small pt-2 border-top border-slate-100">
                            <span><?= $bank_orders ?> Orders</span>
                            <span class="text-secondary font-medium">Direct Settlement</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1: Line & Category Doughnut -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="activity" style="width: 18px; height: 18px; color: #0A6CFF;"></i>
                                <h6 class="mb-0 fw-bold text-dark">Revenue Over Time</h6>
                            </div>
                            <span class="text-secondary small font-medium">Daily Sales Trends</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="pie-chart" style="width: 18px; height: 18px; color: #0A6CFF;"></i>
                                <h6 class="mb-0 fw-bold text-dark">Income by Category</h6>
                            </div>
                            <span class="text-secondary small font-medium">Margin Distribution</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2: UPGRADED Payment Method Donut & Stacked Financials -->
            <div class="row g-3 mb-4">
                <!-- Payment Method Donut Chart -->
                <div class="col-lg-6">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                    <i data-lucide="credit-card" style="width: 18px; height: 18px; color: #0A6CFF;"></i>
                                    Revenue by Payment Method
                                </h6>
                                <span class="text-secondary small font-medium">Channel Distribution Analysis</span>
                            </div>
                            <span class="badge-neutral">4 Active Channels</span>
                        </div>
                        <div class="row align-items-center g-3">
                            <div class="col-md-7">
                                <div class="chart-wrapper" style="height: 260px;">
                                    <canvas id="paymentDonutChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="space-y-2">
                                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-slate-50 mb-2 border border-slate-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block rounded-circle" style="width:8px; height:8px; background:#0A6CFF;"></span>
                                            <span class="small font-medium text-secondary">Card</span>
                                        </div>
                                        <span class="small fw-bold text-dark">Rs. <?= number_format($card_total) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-slate-50 mb-2 border border-slate-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block rounded-circle" style="width:8px; height:8px; background:#6366F1;"></span>
                                            <span class="small font-medium text-secondary">KOKO</span>
                                        </div>
                                        <span class="small fw-bold text-dark">Rs. <?= number_format($koko_total) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-slate-50 mb-2 border border-slate-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block rounded-circle" style="width:8px; height:8px; background:#10B981;"></span>
                                            <span class="small font-medium text-secondary">COD</span>
                                        </div>
                                        <span class="small fw-bold text-dark">Rs. <?= number_format($cod_total) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-slate-50 mb-2 border border-slate-100">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-block rounded-circle" style="width:8px; height:8px; background:#64748B;"></span>
                                            <span class="small font-medium text-secondary">Bank</span>
                                        </div>
                                        <span class="small fw-bold text-dark">Rs. <?= number_format($bank_total) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Distribution Breakdown -->
                <div class="col-lg-6">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                    <i data-lucide="layers-3" style="width: 18px; height: 18px; color: #10B981;"></i>
                                    Financial Distribution Breakdown
                                </h6>
                                <span class="text-secondary small font-medium">Commission vs Net Seller Payouts</span>
                            </div>
                        </div>
                        <div class="chart-wrapper" style="height: 260px;">
                            <canvas id="stackedChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section A, B, C: Channel Health, KOKO Installment Tracker, & COD Courier Status -->
            <div class="row g-3 mb-4">
                <!-- Section A: Payment Channel & Gateway Health -->
                <div class="col-lg-4">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i data-lucide="shield-check" style="width: 16px; height: 16px; color: #0A6CFF;"></i>
                                Gateway Health & MDR Audit
                            </h6>
                            <span class="badge-neutral">MDR 3%-5%</span>
                        </div>
                        <div class="space-y-3">
                            <div class="p-3 rounded-3 bg-slate-50 border border-slate-100 mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="metric-label">Success Rate</span>
                                    <span class="fw-black fs-5 text-dark"><?= $payment_health_rate ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px; background: #E2E8F0; border-radius: 9999px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $payment_health_rate ?>%; background: #0A6CFF; border-radius: 9999px;"></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-slate-50 border border-slate-100 mb-3">
                                <div>
                                    <div class="metric-label">Gateway Fees Incurred</div>
                                    <div class="fs-5 fw-bold text-danger">Rs. <?= number_format($total_gateway_fees, 2) ?></div>
                                </div>
                                <span class="badge-neutral text-xs">Direct MDR</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-slate-50 border border-slate-100">
                                <div>
                                    <div class="metric-label">Net Settled Volume</div>
                                    <div class="fs-5 fw-bold text-success">Rs. <?= number_format($net_settled_revenue, 2) ?></div>
                                </div>
                                <span class="badge-success-subtle text-xs">After Fees</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section B: KOKO Pay in 3 Installment Monitor -->
                <div class="col-lg-4">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i data-lucide="layers" style="width: 16px; height: 16px; color: #6366F1;"></i>
                                KOKO Pay in 3 Monitor
                            </h6>
                            <span class="badge-neutral">100% Upfront</span>
                        </div>
                        <div class="p-3 rounded-3 bg-slate-50 border border-slate-100 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small font-medium text-secondary">Total Volume</span>
                                <span class="fw-black fs-5 text-dark">Rs. <?= number_format($koko_total, 2) ?></span>
                            </div>
                            <p class="text-secondary text-xs mb-0">Merchant receives 100% upfront settlement minus 5% KOKO MDR fee.</p>
                        </div>

                        <div class="metric-label mb-2">Customer 3-Month Installment Flow:</div>
                        <div class="border rounded-3 overflow-hidden border-slate-100">
                            <div class="d-flex justify-content-between align-items-center py-2 px-3 bg-white border-bottom border-slate-100">
                                <span class="small text-secondary d-flex align-items-center gap-1.5">
                                    <i data-lucide="check-circle-2" style="width: 14px; height: 14px; color: #10B981;"></i> 1st Installment (Paid Today)
                                </span>
                                <span class="fw-bold text-dark small">Rs. <?= number_format($koko_total / 3, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 px-3 bg-white border-bottom border-slate-100">
                                <span class="small text-secondary d-flex align-items-center gap-1.5">
                                    <i data-lucide="clock" style="width: 14px; height: 14px; color: #F59E0B;"></i> 2nd Installment (30 Days)
                                </span>
                                <span class="fw-bold text-dark small">Rs. <?= number_format($koko_total / 3, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 px-3 bg-white">
                                <span class="small text-secondary d-flex align-items-center gap-1.5">
                                    <i data-lucide="clock" style="width: 14px; height: 14px; color: #94A3B8;"></i> 3rd Installment (60 Days)
                                </span>
                                <span class="fw-bold text-dark small">Rs. <?= number_format($koko_total / 3, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section C: COD Courier Collection Tracker -->
                <div class="col-lg-4">
                    <div class="premium-card h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i data-lucide="truck" style="width: 16px; height: 16px; color: #10B981;"></i>
                                COD Courier Collection
                            </h6>
                            <span class="badge-neutral">Courier Flow</span>
                        </div>
                        <div class="space-y-3">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-slate-50 border border-slate-100 mb-3">
                                <div>
                                    <div class="metric-label">Total COD Volume</div>
                                    <div class="fs-5 fw-black text-dark">Rs. <?= number_format($cod_total, 2) ?></div>
                                </div>
                                <span class="badge-neutral"><?= $cod_orders ?> parcels</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-slate-50 border border-slate-100 mb-3">
                                <div>
                                    <div class="metric-label">Pending Doorstep Collection</div>
                                    <div class="fs-5 fw-bold text-amber-600">Rs. <?= number_format($cod_pending, 2) ?></div>
                                </div>
                                <span class="badge-warning-subtle">In-Transit</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-slate-50 border border-slate-100">
                                <div>
                                    <div class="metric-label">Remitted by Courier</div>
                                    <div class="fs-5 fw-bold text-success">Rs. <?= number_format(max(0, $cod_total - $cod_pending), 2) ?></div>
                                </div>
                                <span class="badge-success-subtle">Bank Deposited</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Section 1: Top Sellers & Payouts Action -->
            <div class="row g-3 mb-4">
                <!-- Top Sellers -->
                <div class="col-lg-6">
                    <div class="premium-card h-100 p-0 overflow-hidden">
                        <div class="p-4 border-bottom border-slate-100 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i data-lucide="award" style="width: 18px; height: 18px; color: #F59E0B;"></i>
                                Top Sellers by Income
                            </h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Business Name</th>
                                        <th>Sales Generated</th>
                                        <th class="text-end pe-4">Commission</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT COALESCE(u.business_name, CONCAT(us.first_name, ' ', us.last_name), 'Verified Merchant') as business_name, 
                                               SUM(sp.selling_price) as total_sales, 
                                               SUM(sp.admin_commission) as total_comm 
                                        FROM seller_payouts sp 
                                        JOIN users us ON sp.seller_id = us.id
                                        LEFT JOIN seller_profiles u ON us.id = u.user_id 
                                        WHERE $where_payouts 
                                        GROUP BY sp.seller_id, u.business_name, us.first_name, us.last_name 
                                        ORDER BY total_comm DESC LIMIT 5
                                    ");
                                    $stmt->execute($params_payouts);
                                    while ($row = $stmt->fetch()):
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-medium text-dark"><?= htmlspecialchars($row['business_name']) ?></td>
                                        <td>Rs. <?= number_format($row['total_sales'], 2) ?></td>
                                        <td class="text-end pe-4 fw-bold" style="color: #0A6CFF;">Rs. <?= number_format($row['total_comm'], 2) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Seller Payouts Action Table -->
                <div class="col-lg-6">
                    <div class="premium-card h-100 p-0 overflow-hidden">
                        <div class="p-4 border-bottom border-slate-100 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                <i data-lucide="banknote" style="width: 18px; height: 18px; color: #10B981;"></i>
                                Seller Payouts Escrow
                            </h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Seller</th>
                                        <th>Net Earning</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT sp.id, COALESCE(u.business_name, CONCAT(us.first_name, ' ', us.last_name), 'Verified Merchant') as business_name, 
                                               sp.seller_earning, sp.payout_status 
                                        FROM seller_payouts sp 
                                        JOIN users us ON sp.seller_id = us.id
                                        LEFT JOIN seller_profiles u ON us.id = u.user_id 
                                        WHERE $where_payouts 
                                        ORDER BY sp.created_at DESC LIMIT 5
                                    ");
                                    $stmt->execute($params_payouts);
                                    while ($row = $stmt->fetch()):
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-medium text-dark"><?= htmlspecialchars($row['business_name']) ?></td>
                                        <td class="fw-bold">Rs. <?= number_format($row['seller_earning'], 2) ?></td>
                                        <td>
                                            <?php if($row['payout_status'] == 'paid'): ?>
                                                <span class="badge-success-subtle">Paid</span>
                                            <?php elseif($row['payout_status'] == 'pending'): ?>
                                                <span class="badge-warning-subtle">Pending</span>
                                            <?php else: ?>
                                                <span class="badge-neutral">Locked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if($row['payout_status'] != 'paid'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="payout_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="mark_paid" class="btn btn-sm btn-dark rounded-pill px-3 py-1 font-medium text-xs">Mark Paid</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-success small fw-medium d-inline-flex align-items-center gap-1">
                                                    <i data-lucide="check" style="width: 14px; height: 14px;"></i> Settled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- UPGRADED Table: Recent Orders Commission & Payment Method Breakdown -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="premium-card p-0 overflow-hidden">
                        <div class="p-4 border-bottom border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                                    <i data-lucide="list-ordered" style="width: 18px; height: 18px; color: #0A6CFF;"></i>
                                    Recent Orders Commission & Gateway Fee Breakdown
                                </h6>
                                <p class="text-secondary small mb-0 mt-0.5">Order-level reconciliation of channels, MDR fees, and platform earnings</p>
                            </div>
                            <span class="badge-neutral">15 Latest Orders</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table-premium mb-0" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Order Code</th>
                                        <th>Item</th>
                                        <th>Seller</th>
                                        <th>Total (Rs)</th>
                                        <th>Payment Method</th>
                                        <th>Gateway Fee</th>
                                        <th>Commission (10%)</th>
                                        <th>Net Payout (Rs)</th>
                                        <th>Payment Status</th>
                                        <th class="text-end pe-4">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT o.order_code, oi.product_name, 
                                               COALESCE(u.business_name, CONCAT(u_usr.first_name, ' ', u_usr.last_name), 'Verified Merchant') as business_name, 
                                               o.total_amount, o.payment_method, COALESCE(o.gateway_fee, 0) as gateway_fee,
                                               COALESCE(sp.admin_commission, oi.oxxa_fee, oi.profit * 0.10, oi.total_price * 0.10, 0) as admin_commission,
                                               COALESCE(sp.seller_earning, oi.seller_earning, oi.total_price - (oi.profit * 0.10)) as seller_earning,
                                               COALESCE(o.payment_status, 'pending') as payment_status,
                                               DATE(o.created_at) as order_date
                                        FROM orders o 
                                        JOIN order_items oi ON o.id = oi.order_id 
                                        LEFT JOIN products p ON oi.product_id = p.id
                                        LEFT JOIN users u_usr ON p.seller_id = u_usr.id
                                        LEFT JOIN seller_profiles u ON u_usr.id = u.user_id 
                                        LEFT JOIN seller_payouts sp ON oi.id = sp.order_item_id
                                        WHERE $where_orders 
                                        ORDER BY o.created_at DESC LIMIT 15
                                    ");
                                    $stmt->execute($params_orders);
                                    $recentOrdersForPDF = [];
                                    while ($row = $stmt->fetch()):
                                        $pm = strtoupper($row['payment_method'] ?? 'COD');
                                        $recentOrdersForPDF[] = $row;
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($row['order_code']) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($row['product_name']) ?></div>
                                        </td>
                                        <td><span class="badge-neutral"><?= htmlspecialchars($row['business_name']) ?></span></td>
                                        <td class="fw-bold text-dark">Rs. <?= number_format($row['total_amount'], 2) ?></td>
                                        <td>
                                            <?php if ($pm === 'CARD' || $pm === 'PAYHERE'): ?>
                                                <span class="badge-blue-subtle d-inline-flex align-items-center gap-1">
                                                    <i data-lucide="credit-card" style="width: 12px; height: 12px;"></i> Card (PayHere)
                                                </span>
                                            <?php elseif (strpos($pm, 'KOKO') !== false): ?>
                                                <span class="badge-neutral d-inline-flex align-items-center gap-1" style="background:#F3E8FF; color:#6B21A8;">
                                                    <i data-lucide="layers" style="width: 12px; height: 12px;"></i> KOKO Pay in 3
                                                </span>
                                            <?php elseif (strpos($pm, 'BANK') !== false): ?>
                                                <span class="badge-neutral d-inline-flex align-items-center gap-1">
                                                    <i data-lucide="building-2" style="width: 12px; height: 12px;"></i> Bank Transfer
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-success-subtle d-inline-flex align-items-center gap-1">
                                                    <i data-lucide="truck" style="width: 12px; height: 12px;"></i> Cash on Delivery
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-secondary small">
                                            <?php if ($row['gateway_fee'] > 0): ?>
                                                Rs. <?= number_format($row['gateway_fee'], 2) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Rs. 0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold" style="color: #0A6CFF;">Rs. <?= number_format($row['admin_commission'], 2) ?></td>
                                        <td class="text-success fw-bold">Rs. <?= number_format($row['seller_earning'], 2) ?></td>
                                        <td>
                                            <?php if ($row['payment_status'] === 'paid'): ?>
                                                <span class="badge-success-subtle">Paid</span>
                                            <?php elseif ($row['payment_status'] === 'pending_verification'): ?>
                                                <span class="badge-blue-subtle">Verify Slip</span>
                                            <?php else: ?>
                                                <span class="badge-warning-subtle">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 text-muted small"><?= $row['order_date'] ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HIDDEN EXECUTIVE 4-PAGE PDF REPORT TEMPLATE (Rendered by html2pdf) -->
    <!-- ========================================================================= -->
    <div id="pdfReportContainer" style="display: none; width: 800px; margin: 0 auto; background: #FFFFFF; font-family: 'Inter', -apple-system, sans-serif; color: #0A1020;">
        
        <!-- ========================================================================= -->
        <!-- PAGE 1: EXECUTIVE COVER & MAIN FINANCIAL KPIS (TABLE 1) -->
        <!-- ========================================================================= -->
        <div class="pdf-page" style="width: 800px; min-height: 1080px; padding: 40px 45px; page-break-after: always; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box;">
            <div>
                <!-- Brand Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0A1020; padding-bottom: 18px; margin-bottom: 28px;">
                    <div>
                        <h1 style="font-size: 26px; font-weight: 900; margin: 0; color: #0A1020; letter-spacing: -0.5px;">OXXA GEAR</h1>
                        <p style="font-size: 11px; font-weight: 700; color: #64748B; margin: 2px 0 0 0; text-transform: uppercase; letter-spacing: 1px;">Control Center • Executive Financial & Gateway Audit</p>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: inline-block; background: #0A1020; color: #FFF; font-size: 10px; font-weight: 800; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.5px;">Official Executive Report</span>
                        <div style="font-size: 11px; color: #64748B; margin-top: 4px; font-weight: 600;">Generated: <?= date('M d, Y h:i A') ?></div>
                    </div>
                </div>

                <!-- Report Title & Narrative -->
                <div style="margin-bottom: 28px;">
                    <span style="color: #0A6CFF; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px;">Financial Performance & Multi-Channel Reconciliation</span>
                    <h2 style="font-size: 26px; font-weight: 900; color: #0A1020; margin: 6px 0 10px 0; letter-spacing: -0.8px;">Executive Finance & Gateway Reconciliation</h2>
                    <p style="font-size: 13px; color: #64748B; line-height: 1.6; margin: 0;">
                        Audited executive analysis of Gross Merchandise Volume (GMV), multi-channel gateway fee deductions, platform net margin yields, and merchant escrow balance protections.
                    </p>
                </div>

                <!-- Parameters Box -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px 18px; margin-bottom: 26px;">
                    <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                        <tr>
                            <td style="color: #64748B; font-weight: 700; width: 22%;">Date Range Filter:</td>
                            <td style="font-weight: 700; color: #0A1020; width: 28%;"><?= ucfirst($date_filter) ?> <?= ($date_filter==='custom')?"({$start_date} to {$end_date})":'' ?></td>
                            <td style="color: #64748B; font-weight: 700; width: 22%;">Payment Channel:</td>
                            <td style="font-weight: 700; color: #0A1020; width: 28%;"><?= htmlspecialchars($payment_method_filter) ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748B; font-weight: 700; padding-top: 8px;">Category Scope:</td>
                            <td style="font-weight: 700; color: #0A1020; padding-top: 8px;"><?= !empty($category_filter)?'Filtered Category':'All Categories' ?></td>
                            <td style="color: #64748B; font-weight: 700; padding-top: 8px;">Seller Scope:</td>
                            <td style="font-weight: 700; color: #0A1020; padding-top: 8px;"><?= !empty($seller_filter)?'Filtered Seller':'All Verified Sellers' ?></td>
                        </tr>
                    </table>
                </div>

                <!-- TABLE 1: MAIN FINANCIAL KPIS & PERFORMANCE SUMMARY -->
                <div style="margin-bottom: 26px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 1: Main Financial KPIs & Performance Summary
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #0A1020; color: #FFFFFF;">
                                <th style="padding: 9px 12px; text-align: left; font-size: 10px; text-transform: uppercase;">Metric Name</th>
                                <th style="padding: 9px 12px; text-align: right; font-size: 10px; text-transform: uppercase;">Value (LKR) / Count</th>
                                <th style="padding: 9px 12px; text-align: left; font-size: 10px; text-transform: uppercase;">Classification & Operational Scope</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 8px 12px; font-weight: 700;">Total Revenue (GMV)</td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #0A1020;">Rs. <?= number_format($total_revenue, 2) ?></td>
                                <td style="padding: 8px 12px; color: #64748B;">Gross Market Volume across all processed payment channels</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 8px 12px; font-weight: 700;">Admin Commission (10%)</td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #0A6CFF;">Rs. <?= number_format($admin_commission, 2) ?></td>
                                <td style="padding: 8px 12px; color: #64748B;">Net platform commission retained by OXXA GEAR</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 8px 12px; font-weight: 700;">Total Processed Orders</td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #0A1020;"><?= number_format($total_orders_count) ?> Orders</td>
                                <td style="padding: 8px 12px; color: #64748B;">Average Order Value (AOV): Rs. <?= number_format($average_order_value, 2) ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 8px 12px; font-weight: 700;">Pending Seller Payouts</td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #F59E0B;">Rs. <?= number_format($pending_payouts, 2) ?></td>
                                <td style="padding: 8px 12px; color: #64748B;">Merchant escrow balance held under 14-day customer return protection</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 8px 12px; font-weight: 700;">Net Settled Revenue</td>
                                <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #10B981;">Rs. <?= number_format($net_settled_revenue, 2) ?></td>
                                <td style="padding: 8px 12px; color: #64748B;">Gross GMV minus gateway fee deductions (Total Fees: Rs. <?= number_format($total_gateway_fees, 2) ?>)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4 Executive Highlight Metric Tiles -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px;">
                    <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 15px; background: #FFFFFF;">
                        <div style="font-size: 10px; font-weight: 800; color: #64748B; text-transform: uppercase;">Total Revenue (GMV)</div>
                        <div style="font-size: 22px; font-weight: 900; color: #0A1020; margin-top: 4px;">Rs. <?= number_format($total_revenue, 2) ?></div>
                        <div style="font-size: 10px; color: #10B981; font-weight: 600; margin-top: 2px;">Gross Marketplace Volume</div>
                    </div>
                    <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 15px; background: #FFFFFF;">
                        <div style="font-size: 10px; font-weight: 800; color: #64748B; text-transform: uppercase;">Admin Commission (10%)</div>
                        <div style="font-size: 22px; font-weight: 900; color: #0A6CFF; margin-top: 4px;">Rs. <?= number_format($admin_commission, 2) ?></div>
                        <div style="font-size: 10px; color: #0A6CFF; font-weight: 600; margin-top: 2px;">Net Platform Operating Margin</div>
                    </div>
                    <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 15px; background: #FFFFFF;">
                        <div style="font-size: 10px; font-weight: 800; color: #64748B; text-transform: uppercase;">Total Processed Orders</div>
                        <div style="font-size: 22px; font-weight: 900; color: #0A1020; margin-top: 4px;"><?= number_format($total_orders_count) ?></div>
                        <div style="font-size: 10px; color: #64748B; font-weight: 600; margin-top: 2px;">AOV: Rs. <?= number_format($average_order_value, 2) ?></div>
                    </div>
                    <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 15px; background: #FFFFFF;">
                        <div style="font-size: 10px; font-weight: 800; color: #64748B; text-transform: uppercase;">Pending Payouts (Escrow)</div>
                        <div style="font-size: 22px; font-weight: 900; color: #F59E0B; margin-top: 4px;">Rs. <?= number_format($pending_payouts, 2) ?></div>
                        <div style="font-size: 10px; color: #EF4444; font-weight: 600; margin-top: 2px;">Locked in Escrow (Hold 14 Days)</div>
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #94A3B8;">
                <span>Page 1 of 4 • Executive Overview & Table 1</span>
                <span>OXXA GEAR Platform Finance</span>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- PAGE 2: PAYMENT METHOD RECONCILIATION & CATEGORIES (TABLES 2, 3, 4) -->
        <!-- ========================================================================= -->
        <div class="pdf-page" style="width: 800px; min-height: 1080px; padding: 40px 45px; page-break-after: always; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box;">
            <div>
                <div style="border-bottom: 1px solid #E2E8F0; padding-bottom: 12px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; font-weight: 900; color: #0A1020;">OXXA GEAR FINANCE</span>
                    <span style="font-size: 11px; font-weight: 700; color: #64748B;">Page 2 of 4 • Multi-Channel Payment & Category Performance</span>
                </div>

                <!-- TABLE 2: REVENUE BY PAYMENT METHOD -->
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 2: Revenue Breakdown by Payment Method
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 8px 10px; text-align: left;">Payment Method</th>
                                <th style="padding: 8px 10px; text-align: center;">Orders</th>
                                <th style="padding: 8px 10px; text-align: right;">Gross Volume (LKR)</th>
                                <th style="padding: 8px 10px; text-align: center;">Share %</th>
                                <th style="padding: 8px 10px; text-align: right;">Gateway Fee (MDR)</th>
                                <th style="padding: 8px 10px; text-align: right;">Net Settled Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 7px 10px; font-weight: 700;">Card Payments (PayHere)</td>
                                <td style="padding: 7px 10px; text-align: center;"><?= $card_orders ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($card_total, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #0A6CFF;"><?= $card_share ?>%</td>
                                <td style="padding: 7px 10px; text-align: right; color: #EF4444;">Rs. <?= number_format($card_fees, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700; color: #10B981;">Rs. <?= number_format($card_total - $card_fees, 2) ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 7px 10px; font-weight: 700;">KOKO Pay in 3 (BNPL)</td>
                                <td style="padding: 7px 10px; text-align: center;"><?= $koko_orders ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($koko_total, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #6366F1;"><?= $koko_share ?>%</td>
                                <td style="padding: 7px 10px; text-align: right; color: #EF4444;">Rs. <?= number_format($koko_fees, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700; color: #10B981;">Rs. <?= number_format($koko_total - $koko_fees, 2) ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 7px 10px; font-weight: 700;">Cash on Delivery (COD)</td>
                                <td style="padding: 7px 10px; text-align: center;"><?= $cod_orders ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($cod_total, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #10B981;"><?= $cod_share ?>%</td>
                                <td style="padding: 7px 10px; text-align: right; color: #64748B;">Rs. 0.00</td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($cod_total, 2) ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 7px 10px; font-weight: 700;">Direct Bank Transfer</td>
                                <td style="padding: 7px 10px; text-align: center;"><?= $bank_orders ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($bank_total, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #64748B;"><?= $bank_share ?>%</td>
                                <td style="padding: 7px 10px; text-align: right; color: #64748B;">Rs. 0.00</td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($bank_total, 2) ?></td>
                            </tr>
                            <tr style="background: #0A1020; color: #FFFFFF; font-weight: 800;">
                                <td style="padding: 8px 10px;">Consolidated Total</td>
                                <td style="padding: 8px 10px; text-align: center;"><?= $total_orders_count ?></td>
                                <td style="padding: 8px 10px; text-align: right;">Rs. <?= number_format($total_revenue, 2) ?></td>
                                <td style="padding: 8px 10px; text-align: center;">100%</td>
                                <td style="padding: 8px 10px; text-align: right; color: #FCA5A5;">Rs. <?= number_format($total_gateway_fees, 2) ?></td>
                                <td style="padding: 8px 10px; text-align: right; color: #86EFAC;">Rs. <?= number_format($net_settled_revenue, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 3: PAYMENT GATEWAY FEE STRUCTURES & SETTLEMENT TERMS -->
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 3: Payment Gateway Fee Structures & Settlement Terms
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 8px 10px; text-align: left;">Payment Gateway / Channel</th>
                                <th style="padding: 8px 10px; text-align: left;">Base MDR / Processing Rate</th>
                                <th style="padding: 8px 10px; text-align: left;">Settlement Timeline</th>
                                <th style="padding: 8px 10px; text-align: left;">Reconciliation & Risk Profile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 7px 10px; font-weight: 700;">PayHere (Visa / Mastercard)</td>
                                <td style="padding: 7px 10px; font-weight: 700; color: #0A6CFF;">3.00% Per Transaction</td>
                                <td style="padding: 7px 10px; color: #64748B;">Instant Capture • T+1 Bank Deposit</td>
                                <td style="padding: 7px 10px; color: #10B981; font-weight: 600;">100% Settled • 0 Chargeback Risk</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 7px 10px; font-weight: 700;">KOKO Pay in 3 (BNPL)</td>
                                <td style="padding: 7px 10px; font-weight: 700; color: #6366F1;">5.00% Merchant Discount Rate</td>
                                <td style="padding: 7px 10px; color: #64748B;">3 Equal Installments (0% APR)</td>
                                <td style="padding: 7px 10px; color: #10B981; font-weight: 600;">100% Upfront Settled by KOKO</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 7px 10px; font-weight: 700;">Cash on Delivery (COD)</td>
                                <td style="padding: 7px 10px; font-weight: 700; color: #10B981;">0.00% Platform Gateway Fee</td>
                                <td style="padding: 7px 10px; color: #64748B;">Courier Doorstep Remittance (3-5 Days)</td>
                                <td style="padding: 7px 10px; color: #F59E0B; font-weight: 600;">Pending Remittance: Rs. <?= number_format($cod_pending, 2) ?></td>
                            </tr>
                            <tr style="background: #F8FAFC;">
                                <td style="padding: 7px 10px; font-weight: 700;">Direct Bank Transfer</td>
                                <td style="padding: 7px 10px; font-weight: 700; color: #64748B;">0.00% Gateway MDR Fee</td>
                                <td style="padding: 7px 10px; color: #64748B;">Same Day Commercial Bank Deposit</td>
                                <td style="padding: 7px 10px; color: #0A6CFF; font-weight: 600;">Manual Deposit Slip Verified</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 4: INCOME BY CATEGORY -->
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 4: Income by Category & Margin Share
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 8px 10px; text-align: left;">Product Category</th>
                                <th style="padding: 8px 10px; text-align: right;">Gross Sales Volume (LKR)</th>
                                <th style="padding: 8px 10px; text-align: right;">Platform Margin (10%)</th>
                                <th style="padding: 8px 10px; text-align: center;">Category Share %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grandCatSales = $total_revenue > 0 ? $total_revenue : 1;
                            foreach($categoryBreakdownList as $catRow): 
                                $catSales = floatval($catRow['cat_sales']);
                                $catComm = floatval($catRow['total_comm']);
                                $catShare = round(($catSales / $grandCatSales) * 100, 1);
                            ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 7px 10px; font-weight: 700;"><?= htmlspecialchars($catRow['cat_name']) ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($catSales, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: right; font-weight: 700; color: #0A6CFF;">Rs. <?= number_format($catComm, 2) ?></td>
                                <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #64748B;"><?= $catShare ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Visual Payment Channel Chart Placeholder -->
                <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; text-align: center; background: #FFFFFF;">
                    <div style="font-size: 11px; font-weight: 800; color: #0A1020; margin-bottom: 6px; text-transform: uppercase;">Visual Payment Channel Distribution</div>
                    <img id="pdfImgPaymentDonut" style="max-height: 190px; max-width: 100%; object-fit: contain; margin: 0 auto; display: block;">
                </div>
            </div>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #94A3B8;">
                <span>Page 2 of 4 • Tables 2, 3, 4 & Payment Channel Chart</span>
                <span>OXXA GEAR Platform Finance</span>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- PAGE 3: CHARTS DATA TABLES & TRENDS (TABLES 5, 6, 7) -->
        <!-- ========================================================================= -->
        <div class="pdf-page" style="width: 800px; min-height: 1080px; padding: 40px 45px; page-break-after: always; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box;">
            <div>
                <div style="border-bottom: 1px solid #E2E8F0; padding-bottom: 12px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; font-weight: 900; color: #0A1020;">OXXA GEAR FINANCE</span>
                    <span style="font-size: 11px; font-weight: 700; color: #64748B;">Page 3 of 4 • Financial Distribution & Trend Analysis</span>
                </div>

                <!-- TABLE 5: REVENUE OVER TIME (DAILY TRENDS) -->
                <div style="margin-bottom: 18px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 5: Revenue Over Time (Daily Sales Trends)
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 7px 10px; text-align: left;">Date</th>
                                <th style="padding: 7px 10px; text-align: center;">Orders Processed</th>
                                <th style="padding: 7px 10px; text-align: right;">Daily Gross Sales (LKR)</th>
                                <th style="padding: 7px 10px; text-align: right;">Platform Margin (10%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dailySalesList)): foreach($dailySalesList as $ds): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 10px; font-weight: 700;"><?= date('M d, Y', strtotime($ds['date'])) ?></td>
                                <td style="padding: 6px 10px; text-align: center;"><?= $ds['orders'] ?></td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 700;">Rs. <?= number_format($ds['revenue'], 2) ?></td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 700; color: #0A6CFF;">Rs. <?= number_format($ds['commission'], 2) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" style="padding: 8px; text-align: center; color: #94A3B8;">No sales activity in this date filter.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 6: SALES VS COMMISSION FINANCIAL DISTRIBUTION -->
                <div style="margin-bottom: 18px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 6: Sales vs Commission Financial Distribution
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 7px 10px; text-align: left;">Financial Component</th>
                                <th style="padding: 7px 10px; text-align: right;">Allocated Amount (LKR)</th>
                                <th style="padding: 7px 10px; text-align: center;">Share %</th>
                                <th style="padding: 7px 10px; text-align: left;">Allocation Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 10px; font-weight: 700;">Gross Merchandise Volume (GMV)</td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 800;">Rs. <?= number_format($total_revenue, 2) ?></td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700;">100.0%</td>
                                <td style="padding: 6px 10px; color: #64748B;">Total consumer checkout value across all orders</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 6px 10px; font-weight: 700;">Admin Platform Margin (10%)</td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 800; color: #0A6CFF;">Rs. <?= number_format($admin_commission, 2) ?></td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #0A6CFF;">10.0%</td>
                                <td style="padding: 6px 10px; color: #64748B;">Retained revenue margin for platform operations</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 10px; font-weight: 700;">Payment Gateway Fees (MDR)</td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 800; color: #EF4444;">Rs. <?= number_format($total_gateway_fees, 2) ?></td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #EF4444;"><?= $total_revenue > 0 ? round(($total_gateway_fees / $total_revenue) * 100, 1) : 0 ?>%</td>
                                <td style="padding: 6px 10px; color: #64748B;">Processing fees incurred across PayHere (3%) & KOKO (5%)</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 6px 10px; font-weight: 700;">Net Merchant Escrow Allocation</td>
                                <td style="padding: 6px 10px; text-align: right; font-weight: 800; color: #10B981;">Rs. <?= number_format(max(0, $total_revenue - $admin_commission - $total_gateway_fees), 2) ?></td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #10B981;"><?= $total_revenue > 0 ? round((max(0, $total_revenue - $admin_commission - $total_gateway_fees) / $total_revenue) * 100, 1) : 0 ?>%</td>
                                <td style="padding: 6px 10px; color: #64748B;">Protected seller payouts balance currently in escrow</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 7: GATEWAY HEALTH & OPERATIONAL RECONCILIATION -->
                <div style="margin-bottom: 18px;">
                    <div style="font-size: 12px; font-weight: 800; color: #0A1020; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 7: Gateway Health & Operational Reconciliation Audit
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 7px 10px; text-align: left;">Payment Channel</th>
                                <th style="padding: 7px 10px; text-align: center;">Capture Success Rate</th>
                                <th style="padding: 7px 10px; text-align: right;">MDR Deductions</th>
                                <th style="padding: 7px 10px; text-align: left;">Operational Reconciliation Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 10px; font-weight: 700;">PayHere (Visa / Mastercard)</td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #10B981;">100.0%</td>
                                <td style="padding: 6px 10px; text-align: right; color: #EF4444;">Rs. <?= number_format($card_fees, 2) ?></td>
                                <td style="padding: 6px 10px; color: #10B981; font-weight: 600;"><?= $card_orders ?> Transactions Captured & Settled</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9; background: #F8FAFC;">
                                <td style="padding: 6px 10px; font-weight: 700;">KOKO Pay in 3</td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #10B981;">100.0%</td>
                                <td style="padding: 6px 10px; text-align: right; color: #EF4444;">Rs. <?= number_format($koko_fees, 2) ?></td>
                                <td style="padding: 6px 10px; color: #10B981; font-weight: 600;"><?= $koko_orders ?> BNPL Plans Funded Upfront</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 10px; font-weight: 700;">Cash on Delivery</td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #F59E0B;">In-Transit</td>
                                <td style="padding: 6px 10px; text-align: right; color: #64748B;">Rs. 0.00</td>
                                <td style="padding: 6px 10px; color: #F59E0B; font-weight: 600;">Courier Remittance Pending: Rs. <?= number_format($cod_pending, 2) ?></td>
                            </tr>
                            <tr style="background: #F8FAFC;">
                                <td style="padding: 6px 10px; font-weight: 700;">Commercial Bank Slip</td>
                                <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #0A6CFF;">Manual Verified</td>
                                <td style="padding: 6px 10px; text-align: right; color: #64748B;">Rs. 0.00</td>
                                <td style="padding: 6px 10px; color: #0A6CFF; font-weight: 600;"><?= $bank_orders ?> Orders Reconciled via Bank Slips</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Visual Charts Section (Daily Revenue Trend & Margin Share) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; text-align: center;">
                        <div style="font-size: 11px; font-weight: 800; color: #0A1020; margin-bottom: 6px; text-transform: uppercase;">Daily Sales Trend</div>
                        <img id="pdfImgRevenueTrend" style="max-height: 150px; width: 100%; object-fit: contain; margin: 0 auto; display: block;">
                    </div>
                    <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; text-align: center;">
                        <div style="font-size: 11px; font-weight: 800; color: #0A1020; margin-bottom: 6px; text-transform: uppercase;">Category Margin Share</div>
                        <img id="pdfImgCategoryDonut" style="max-height: 150px; width: 100%; object-fit: contain; margin: 0 auto; display: block;">
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #94A3B8;">
                <span>Page 3 of 4 • Tables 5, 6, 7 & Financial Trend Visualizations</span>
                <span>OXXA GEAR Platform Finance</span>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- PAGE 4: SELLERS & DETAILED RECENT ORDERS (TABLES 8, 9, 10, 11) -->
        <!-- ========================================================================= -->
        <div class="pdf-page" style="width: 800px; min-height: 1080px; padding: 40px 45px; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box;">
            <div>
                <div style="border-bottom: 1px solid #E2E8F0; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; font-weight: 900; color: #0A1020;">OXXA GEAR FINANCE</span>
                    <span style="font-size: 11px; font-weight: 700; color: #64748B;">Page 4 of 4 • Merchant Payouts & Order Commission Audit</span>
                </div>

                <!-- TABLE 8: TOP SELLERS BY INCOME & PERFORMANCE -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 11.5px; font-weight: 800; color: #0A1020; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 8: Top Sellers by Income & Performance
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 6px 8px; text-align: left;">Seller Store Name</th>
                                <th style="padding: 6px 8px; text-align: center;">Rating</th>
                                <th style="padding: 6px 8px; text-align: center;">Orders</th>
                                <th style="padding: 6px 8px; text-align: right;">Gross Sales (LKR)</th>
                                <th style="padding: 6px 8px; text-align: right;">Admin Commission</th>
                                <th style="padding: 6px 8px; text-align: right;">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($topSellersList)): foreach($topSellersList as $ts): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 8px; font-weight: 700;"><?= htmlspecialchars($ts['business_name']) ?></td>
                                <td style="padding: 6px 8px; text-align: center; color: #F59E0B; font-weight: 700;">★ <?= number_format($ts['rating'], 1) ?></td>
                                <td style="padding: 6px 8px; text-align: center;"><?= $ts['total_orders'] ?></td>
                                <td style="padding: 6px 8px; text-align: right; font-weight: 700;">Rs. <?= number_format($ts['total_sales'], 2) ?></td>
                                <td style="padding: 6px 8px; text-align: right; font-weight: 700; color: #0A6CFF;">Rs. <?= number_format($ts['total_comm'], 2) ?></td>
                                <td style="padding: 6px 8px; text-align: right; font-weight: 700; color: #10B981;">Rs. <?= number_format($ts['net_earning'], 2) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" style="padding: 8px; text-align: center; color: #94A3B8;">No seller data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 9: SELLER PAYOUTS (ESCROW RECONCILIATION) -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 11.5px; font-weight: 800; color: #0A1020; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 9: Seller Payouts (Escrow Reconciliation)
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 6px 8px; text-align: left;">Seller Store Name</th>
                                <th style="padding: 6px 8px; text-align: center;">Orders Processed</th>
                                <th style="padding: 6px 8px; text-align: right;">Escrow Balance (LKR)</th>
                                <th style="padding: 6px 8px; text-align: left;">Escrow Policy Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($escrowPayoutsList)): foreach($escrowPayoutsList as $ep): ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 8px; font-weight: 700;"><?= htmlspecialchars($ep['business_name']) ?></td>
                                <td style="padding: 6px 8px; text-align: center;"><?= $ep['orders_count'] ?> Orders</td>
                                <td style="padding: 6px 8px; text-align: right; font-weight: 800; color: #0A1020;">Rs. <?= number_format($ep['escrow_balance'], 2) ?></td>
                                <td style="padding: 6px 8px; color: #F59E0B; font-weight: 600;"><?= htmlspecialchars($ep['escrow_status']) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="4" style="padding: 8px; text-align: center; color: #94A3B8;">No active escrow balances.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 10: RECENT ORDERS COMMISSION BREAKDOWN (10 COLUMNS, MASKED BUYER NAMES) -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div style="font-size: 11.5px; font-weight: 800; color: #0A1020; text-transform: uppercase; letter-spacing: 0.5px;">
                            Table 10: Recent Orders Commission Breakdown (Audited Rows)
                        </div>
                        <span style="font-size: 9.5px; color: #64748B; font-weight: 600;">* Buyer names masked for confidential presentation</span>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 9.5px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #0A1020; color: #FFFFFF;">
                                <th style="padding: 6px 6px; text-align: left;">Order Code</th>
                                <th style="padding: 6px 6px; text-align: left;">Product Item</th>
                                <th style="padding: 6px 6px; text-align: left;">Seller</th>
                                <th style="padding: 6px 6px; text-align: right;">Total</th>
                                <th style="padding: 6px 6px; text-align: center;">Method</th>
                                <th style="padding: 6px 6px; text-align: right;">MDR Fee</th>
                                <th style="padding: 6px 6px; text-align: right;">Commission</th>
                                <th style="padding: 6px 6px; text-align: right;">Payout</th>
                                <th style="padding: 6px 6px; text-align: center;">Status</th>
                                <th style="padding: 6px 6px; text-align: right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($recentOrdersForPDF)): foreach($recentOrdersForPDF as $ro): 
                                $pmShort = strtoupper($ro['payment_method'] ?? 'COD');
                                if (strpos($pmShort, 'CARD') !== false || $pmShort === 'PAYHERE') $pmBadge = 'CARD';
                                elseif (strpos($pmShort, 'KOKO') !== false) $pmBadge = 'KOKO';
                                elseif (strpos($pmShort, 'BANK') !== false) $pmBadge = 'BANK';
                                else $pmBadge = 'COD';
                            ?>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 5px 6px; font-weight: 800; color: #0A1020;"><?= htmlspecialchars($ro['order_code']) ?></td>
                                <td style="padding: 5px 6px; max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;"><?= htmlspecialchars($ro['product_name']) ?></td>
                                <td style="padding: 5px 6px; max-width: 90px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #64748B;"><?= htmlspecialchars($ro['business_name'] ?? 'Merchant') ?></td>
                                <td style="padding: 5px 6px; text-align: right; font-weight: 700;">Rs. <?= number_format($ro['total_amount'], 2) ?></td>
                                <td style="padding: 5px 6px; text-align: center; font-weight: 700;"><?= $pmBadge ?></td>
                                <td style="padding: 5px 6px; text-align: right; color: #EF4444;"><?= $ro['gateway_fee'] > 0 ? ('Rs. ' . number_format($ro['gateway_fee'], 2)) : 'Rs. 0.00' ?></td>
                                <td style="padding: 5px 6px; text-align: right; font-weight: 700; color: #0A6CFF;">Rs. <?= number_format($ro['admin_commission'], 2) ?></td>
                                <td style="padding: 5px 6px; text-align: right; font-weight: 700; color: #10B981;">Rs. <?= number_format($ro['seller_earning'], 2) ?></td>
                                <td style="padding: 5px 6px; text-align: center; font-weight: 600;"><?= ucfirst($ro['payment_status']) ?></td>
                                <td style="padding: 5px 6px; text-align: right; color: #94A3B8;"><?= date('M d', strtotime($ro['order_date'])) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="10" style="padding: 8px; text-align: center; color: #94A3B8;">No orders matching current filter.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- TABLE 11: KOKO MONITOR & COD DOORSTEP COLLECTION -->
                <div style="margin-bottom: 12px;">
                    <div style="font-size: 11.5px; font-weight: 800; color: #0A1020; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Table 11: KOKO BNPL & Cash on Delivery (COD) Doorstep Collection
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #475569;">
                                <th style="padding: 6px 8px; text-align: left;">Channel Monitoring Focus</th>
                                <th style="padding: 6px 8px; text-align: right;">Volume Tracked (LKR)</th>
                                <th style="padding: 6px 8px; text-align: left;">Rate / Fee Structure</th>
                                <th style="padding: 6px 8px; text-align: left;">Operational Reconciliation Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 6px 8px; font-weight: 700;">KOKO 3-Installment (BNPL)</td>
                                <td style="padding: 6px 8px; text-align: right; font-weight: 700; color: #6366F1;">Rs. <?= number_format($koko_total, 2) ?></td>
                                <td style="padding: 6px 8px; font-weight: 600;">5.00% Merchant Fee (Rs. <?= number_format($koko_fees, 2) ?>)</td>
                                <td style="padding: 6px 8px; color: #10B981; font-weight: 600;">100% Upfront Settled to Merchant Balance</td>
                            </tr>
                            <tr style="background: #F8FAFC;">
                                <td style="padding: 6px 8px; font-weight: 700;">Cash on Delivery Collection</td>
                                <td style="padding: 6px 8px; text-align: right; font-weight: 700; color: #10B981;">Rs. <?= number_format($cod_total, 2) ?></td>
                                <td style="padding: 6px 8px; font-weight: 600;">Doorstep Remittance (0% MDR)</td>
                                <td style="padding: 6px 8px; color: #F59E0B; font-weight: 600;">Pending In-Transit: Rs. <?= number_format($cod_pending, 2) ?> • Remitted: Rs. <?= number_format(max(0, $cod_total - $cod_pending), 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #94A3B8;">
                <span>Page 4 of 4 • Tables 8, 9, 10, 11 • Final Executive Audit</span>
                <span>OXXA GEAR Platform Finance</span>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide line icons
        lucide.createIcons();

        // Toggle Custom Date Fields
        const dateFilterEl = document.getElementById('dateFilter');
        if (dateFilterEl) {
            dateFilterEl.addEventListener('change', function() {
                const customRow = document.querySelector('.custom-date-row');
                if (customRow) {
                    if (this.value === 'custom') {
                        customRow.classList.remove('d-none');
                    } else {
                        customRow.classList.add('d-none');
                    }
                }
            });
        }

        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#64748B';
        
        // 1. Revenue Line Chart (Area with soft gradient)
        const revCtx = document.getElementById('revenueChart').getContext('2d');
        const revGradient = revCtx.createLinearGradient(0, 0, 0, 300);
        revGradient.addColorStop(0, 'rgba(10, 108, 255, 0.18)');
        revGradient.addColorStop(1, 'rgba(10, 108, 255, 0.00)');

        const revenueChartInstance = new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_revenue_labels) ?>,
                datasets: [{
                    label: 'Revenue (Rs)',
                    data: <?= json_encode($chart_revenue_data) ?>,
                    borderColor: '#0A6CFF',
                    backgroundColor: revGradient,
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#0A6CFF',
                    pointHoverBorderColor: '#FFFFFF',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { borderDash: [4, 4], color: '#F1F5F9' },
                        border: { display: false }
                    },
                    x: { 
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });

        // 2. Category Doughnut Chart (Harmonious Monochrome Blue)
        const categoryChartInstance = new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_cat_labels) ?>,
                datasets: [{
                    data: <?= json_encode($chart_cat_data) ?>,
                    backgroundColor: ['#0A6CFF', '#3B82F6', '#60A5FA', '#93C5FD', '#CBD5E1', '#E2E8F0'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, weight: 600 } } }
                },
                cutout: '72%'
            }
        });

        // 3. Upgraded Multi-Color Payment Method Donut Chart
        const paymentDonutChartInstance = new Chart(document.getElementById('paymentDonutChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_pm_labels) ?>,
                datasets: [{
                    data: <?= json_encode($chart_pm_data) ?>,
                    backgroundColor: <?= json_encode($chart_pm_colors) ?>,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const orders = <?= json_encode($chart_pm_orders) ?>;
                                const fees = <?= json_encode($chart_pm_fees) ?>;
                                const idx = context.dataIndex;
                                const val = Number(context.raw).toLocaleString();
                                return [
                                    ` ${context.label}: Rs. ${val}`,
                                    ` Orders: ${orders[idx]}`,
                                    ` Gateway Fee: Rs. ${Number(fees[idx]).toLocaleString()}`
                                ];
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });

        // 4. Stacked Bar Chart
        const stackedChartInstance = new Chart(document.getElementById('stackedChart'), {
            type: 'bar',
            data: {
                labels: ['Financial Distribution'],
                datasets: [
                    {
                        label: 'Admin Commission',
                        data: [<?= $chart_stacked_data['Commission'] ?>],
                        backgroundColor: '#0A6CFF',
                        borderRadius: 6
                    },
                    {
                        label: 'Seller Payouts',
                        data: [<?= $chart_stacked_data['Seller Payouts'] ?>],
                        backgroundColor: '#10B981',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: { mode: 'index', intersect: false },
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, weight: 600 } } }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, border: { display: false } },
                    y: { stacked: true, beginAtZero: true, grid: { borderDash: [4, 4], color: '#F1F5F9' }, border: { display: false } }
                }
            }
        });

        // =========================================================================
        // EXPORT FUNCTIONALITY: CSV (Accountant / Excel Analysis)
        // =========================================================================
        function exportCSV() {
            const btn = document.getElementById('btnExportCSV');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1.5" role="status"></span><span>Exporting...</span>`;
            btn.disabled = true;

            const form = document.getElementById('filterForm');
            const params = new URLSearchParams(window.location.search);
            if (form) {
                const fd = new FormData(form);
                for (const [key, value] of fd.entries()) {
                    if (value !== '') {
                        params.set(key, value);
                    } else {
                        params.delete(key);
                    }
                }
            }
            params.set('format', 'csv');

            const downloadUrl = 'finance_export.php?' + params.toString();
            window.location.href = downloadUrl;

            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                if (window.lucide) lucide.createIcons();
            }, 1800);
        }

        // =========================================================================
        // EXPORT FUNCTIONALITY: EXECUTIVE PDF (4-PAGE PRESENTATION)
        // =========================================================================
        async function exportPDF() {
            const btn = document.getElementById('btnExportPDF');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1.5" role="status"></span><span>Generating PDF...</span>`;
            btn.disabled = true;

            try {
                // Safely populate Chart images in the PDF container from Chart.js instances
                const elPay = document.getElementById('pdfImgPaymentDonut');
                if (elPay && typeof paymentDonutChartInstance !== 'undefined') {
                    elPay.src = paymentDonutChartInstance.toBase64Image();
                }

                const elRev = document.getElementById('pdfImgRevenueTrend');
                if (elRev && typeof revenueChartInstance !== 'undefined') {
                    elRev.src = revenueChartInstance.toBase64Image();
                }

                const elCat = document.getElementById('pdfImgCategoryDonut');
                if (elCat && typeof categoryChartInstance !== 'undefined') {
                    elCat.src = categoryChartInstance.toBase64Image();
                }

                const elStack = document.getElementById('pdfImgStackedDist');
                if (elStack && typeof stackedChartInstance !== 'undefined') {
                    elStack.src = stackedChartInstance.toBase64Image();
                }

                const element = document.getElementById('pdfReportContainer');
                element.style.display = 'block';

                const opt = {
                    margin:       0,
                    filename:     `oxxa-finance-report-${new Date().toISOString().split('T')[0]}.pdf`,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true, letterRendering: true, logging: false },
                    jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' },
                    pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
                };

                await html2pdf().set(opt).from(element).save();

                element.style.display = 'none';
            } catch (err) {
                console.error('PDF Generation Error:', err);
                alert('PDF generation encountered an issue. Falling back to browser print.');
                window.print();
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                if (window.lucide) lucide.createIcons();
            }
        }
    </script>

</body>
</html>
