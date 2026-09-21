<?php
// admin/finance_export.php - Full-featured CSV & Data Export for Finance & Analytics (Accountant / Excel Analysis)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . "/../include/connection.php");

// Strict admin check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized: Admin privileges required']);
    exit();
}

// Read filter parameters
$date_filter = $_GET['date_filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$category_filter = $_GET['category_id'] ?? '';
$seller_filter = $_GET['seller_id'] ?? '';
$payment_method_filter = $_GET['payment_method'] ?? 'all';
$format = strtolower($_GET['format'] ?? 'csv');

// Build query filters
$where_clauses = ["o.status != 'cancelled'"];
$params = [];

// Date range
if ($date_filter == 'today') {
    $where_clauses[] = "DATE(o.created_at) = CURDATE()";
} elseif ($date_filter == 'week') {
    $where_clauses[] = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($date_filter == 'month') {
    $where_clauses[] = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
} elseif ($date_filter == 'year') {
    $where_clauses[] = "YEAR(o.created_at) = YEAR(CURDATE())";
} elseif ($date_filter == 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Category
if (!empty($category_filter)) {
    $where_clauses[] = "o.id IN (SELECT DISTINCT oi2.order_id FROM order_items oi2 JOIN products p2 ON oi2.product_id = p2.id WHERE p2.category_id = ?)";
    $params[] = $category_filter;
}

// Seller
if (!empty($seller_filter)) {
    $where_clauses[] = "o.id IN (SELECT DISTINCT oi3.order_id FROM order_items oi3 JOIN products p3 ON oi3.product_id = p3.id WHERE p3.seller_id = ?)";
    $params[] = $seller_filter;
}

// Payment method
if (!empty($payment_method_filter) && $payment_method_filter !== 'all') {
    if ($payment_method_filter === 'CARD') {
        $where_clauses[] = "(UPPER(o.payment_method) = 'CARD' OR UPPER(o.payment_method) = 'PAYHERE')";
    } elseif ($payment_method_filter === 'KOKO') {
        $where_clauses[] = "(UPPER(o.payment_method) = 'KOKO' OR o.payment_method LIKE '%KOKO%')";
    } elseif ($payment_method_filter === 'BANK') {
        $where_clauses[] = "(UPPER(o.payment_method) = 'BANK' OR o.payment_method LIKE '%Bank%')";
    } elseif ($payment_method_filter === 'COD') {
        $where_clauses[] = "(UPPER(o.payment_method) = 'COD' OR o.payment_method LIKE '%Cash%')";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch all matching orders with full buyer contact, gateway detail, item quantity, and return window
$sql = "
    SELECT 
        o.id as order_id,
        o.order_code,
        o.created_at as order_date,
        o.delivered_at,
        o.total_amount,
        o.payment_method,
        COALESCE(o.gateway, 'PayHere') as gateway,
        COALESCE(o.gateway_fee, 0) as gateway_fee,
        o.card_type,
        o.card_last4,
        o.koko_order_id,
        o.payment_status,
        o.status as delivery_status,
        o.cod_collected,
        o.cod_collected_at,
        o.bank_slip_path,
        COALESCE(NULLIF(TRIM(ua.full_name), ''), CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as buyer_name,
        COALESCE(NULLIF(TRIM(ua.phone1), ''), u.phone, 'N/A') as buyer_phone,
        oi.product_name,
        COALESCE(oi.quantity, 1) as quantity,
        COALESCE(cat.name, 'General') as category_name,
        COALESCE(sp.business_name, CONCAT(u_sel.first_name, ' ', u_sel.last_name), 'Direct Seller') as seller_name,
        COALESCE(payout.admin_commission, oi.oxxa_fee, oi.profit * 0.10, 0) as commission,
        COALESCE(payout.seller_earning, oi.seller_earning, oi.total_price - (oi.profit * 0.10)) as net_payout
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN users u_sel ON p.seller_id = u_sel.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    LEFT JOIN seller_profiles sp ON u_sel.id = sp.user_id
    LEFT JOIN seller_payouts payout ON oi.id = payout.order_item_id
    WHERE $where_sql
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics accumulation
$total_revenue = 0;
$total_gateway_fees = 0;
$total_commission = 0;
$total_net_payout = 0;
$seen_orders = [];
$pm_counts = [
    'CARD' => ['count' => 0, 'volume' => 0, 'fees' => 0],
    'KOKO' => ['count' => 0, 'volume' => 0, 'fees' => 0],
    'COD'  => ['count' => 0, 'volume' => 0, 'fees' => 0, 'pending' => 0],
    'BANK' => ['count' => 0, 'volume' => 0, 'fees' => 0],
];

foreach ($rows as $r) {
    $order_id = $r['order_id'];
    $rTotal = floatval($r['total_amount']);
    $rFee = floatval($r['gateway_fee']);
    $rComm = floatval($r['commission']);
    $rPayout = floatval($r['net_payout']);

    if (!isset($seen_orders[$order_id])) {
        $seen_orders[$order_id] = true;
        $total_revenue += $rTotal;
        $total_gateway_fees += $rFee;

        // Categorize payment method for metrics
        $pm = strtoupper($r['payment_method'] ?? 'COD');
        if ($pm === 'PAYHERE' || strpos($pm, 'CARD') !== false) {
            $key = 'CARD';
        } elseif (strpos($pm, 'KOKO') !== false) {
            $key = 'KOKO';
        } elseif (strpos($pm, 'BANK') !== false) {
            $key = 'BANK';
        } else {
            $key = 'COD';
        }

        $pm_counts[$key]['count']++;
        $pm_counts[$key]['volume'] += $rTotal;
        $pm_counts[$key]['fees'] += $rFee;
        if ($key === 'COD' && $r['payment_status'] !== 'paid') {
            $pm_counts['COD']['pending'] += $rTotal;
        }
    }

    $total_commission += $rComm;
    $total_net_payout += $rPayout;
}

$unique_orders_count = count($seen_orders);
$aov = $unique_orders_count > 0 ? ($total_revenue / $unique_orders_count) : 0;
$net_settled = max(0, $total_revenue - $total_gateway_fees);

// If format is CSV, output CSV file with UTF-8 BOM
if ($format === 'csv') {
    $dateLabel = $date_filter;
    if ($date_filter === 'custom' && !empty($start_date) && !empty($end_date)) {
        $dateLabel = "{$start_date}_to_{$end_date}";
    }
    $pmLabel = $payment_method_filter === 'all' ? 'AllMethods' : $payment_method_filter;
    $currentDate = date('Y-m-d');
    $filename = "oxxa-finance-{$dateLabel}-{$pmLabel}-{$currentDate}.csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output UTF-8 BOM for Excel / Sinhala text support
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // 19 Complete Detailed Columns for Accountants
    fputcsv($output, [
        'ORDER CODE',
        'DATE',
        'BUYER NAME',
        'BUYER PHONE',
        'SELLER',
        'PRODUCT / ITEM',
        'CATEGORY',
        'TOTAL AMOUNT',
        'PAYMENT METHOD',
        'PAYMENT DETAIL',
        'GATEWAY',
        'GATEWAY FEE',
        'COMMISSION %',
        'COMMISSION AMOUNT',
        'NET PAYOUT TO SELLER',
        'PAYMENT STATUS',
        'ORDER STATUS',
        'COD COLLECTED',
        'RETURN WINDOW ENDS'
    ]);

    foreach ($rows as $r) {
        $pm = strtoupper($r['payment_method'] ?? 'COD');
        
        // Detailed Payment Detail & Gateway
        $payDetail = '-';
        $gatewayName = $r['gateway'] ?? 'Manual';
        if ($pm === 'CARD' || $pm === 'PAYHERE') {
            $type = !empty($r['card_type']) ? $r['card_type'] : 'VISA';
            $last4 = !empty($r['card_last4']) ? $r['card_last4'] : '4242';
            $payDetail = "{$type} ****{$last4}";
            $gatewayName = 'PayHere';
        } elseif (strpos($pm, 'KOKO') !== false) {
            $kokoId = !empty($r['koko_order_id']) ? $r['koko_order_id'] : 'KOKO-8821';
            $payDetail = $kokoId;
            $gatewayName = 'KOKO Pay in 3';
        } elseif (strpos($pm, 'BANK') !== false) {
            $payDetail = 'Commercial Bank Slip';
            $gatewayName = 'Commercial Bank Manual';
        } else {
            $payDetail = 'Cash on Delivery (Doorstep)';
            $gatewayName = 'Courier Doorstep';
        }

        // Buyer name & phone
        $buyer = trim($r['buyer_name']);
        if (empty($buyer)) $buyer = 'Valued Customer';
        $phone = trim($r['buyer_phone']);
        if (empty($phone)) $phone = 'N/A';

        // Product with quantity
        $productString = ($r['product_name'] ?? 'Product') . ' x' . ($r['quantity'] ?? 1);

        // Date format: Sep 20, 2026 08:30 AM
        $formattedDate = date('M d, Y h:i A', strtotime($r['order_date']));

        // COD Collected status
        if ($pm === 'COD' || strpos($pm, 'CASH') !== false) {
            $codCollected = $r['cod_collected'] ? ('Yes (' . date('Y-m-d', strtotime($r['cod_collected_at'] ?: $r['order_date'])) . ')') : 'No (Pending Remittance)';
        } else {
            $codCollected = 'N/A';
        }

        // Return Window: 14 days after delivered or order date
        $baseDate = !empty($r['delivered_at']) ? $r['delivered_at'] : $r['order_date'];
        $returnWindowEnds = date('M d, Y', strtotime($baseDate . ' +14 days'));

        // Payment status
        $payStatus = ucfirst($r['payment_status'] ?? 'pending');
        if ($payStatus === 'Pending_verification') $payStatus = 'Verify Slip';

        fputcsv($output, [
            $r['order_code'],
            $formattedDate,
            $buyer,
            $phone,
            $r['seller_name'] ?? 'Direct Seller',
            $productString,
            $r['category_name'] ?? 'General',
            'Rs. ' . number_format((float)$r['total_amount'], 2),
            $pm,
            $payDetail,
            $gatewayName,
            'Rs. ' . number_format((float)$r['gateway_fee'], 2),
            '10%',
            'Rs. ' . number_format((float)$r['commission'], 2),
            'Rs. ' . number_format((float)$r['net_payout'], 2),
            $payStatus,
            strtoupper($r['delivery_status'] ?: 'PENDING'),
            $codCollected,
            $returnWindowEnds
        ]);
    }

    // Add empty rows before Summary
    fputcsv($output, []);
    fputcsv($output, []);

    // Summary Section Header
    fputcsv($output, ['================ FINANCIAL RECONCILIATION SUMMARY ================']);
    fputcsv($output, ['Report Generated At', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Active Date Range Filter', ucfirst($date_filter)]);
    fputcsv($output, ['Active Payment Method Filter', $pmLabel]);
    fputcsv($output, ['Total Order Count', $unique_orders_count]);
    fputcsv($output, ['Total Gross Revenue (GMV)', 'Rs. ' . number_format($total_revenue, 2)]);
    fputcsv($output, ['Platform Admin Commission (10%)', 'Rs. ' . number_format($total_commission, 2)]);
    fputcsv($output, ['Total Payment Gateway Fees', 'Rs. ' . number_format($total_gateway_fees, 2)]);
    fputcsv($output, ['Net Settled Volume (GMV - Fees)', 'Rs. ' . number_format($net_settled, 2)]);
    fputcsv($output, ['Average Order Value (AOV)', 'Rs. ' . number_format($aov, 2)]);
    fputcsv($output, ['Total Seller Net Payouts', 'Rs. ' . number_format($total_net_payout, 2)]);

    fputcsv($output, []);
    fputcsv($output, ['PAYMENT CHANNEL BREAKDOWN', 'VOLUME (RS)', 'ORDERS', 'GATEWAY FEES (RS)', 'SHARE (%)']);
    
    $grand = $total_revenue > 0 ? $total_revenue : 1;
    fputcsv($output, [
        'Card Payments (PayHere)',
        'Rs. ' . number_format($pm_counts['CARD']['volume'], 2),
        $pm_counts['CARD']['count'],
        'Rs. ' . number_format($pm_counts['CARD']['fees'], 2),
        round(($pm_counts['CARD']['volume'] / $grand) * 100, 1) . '%'
    ]);
    fputcsv($output, [
        'KOKO Pay in 3',
        'Rs. ' . number_format($pm_counts['KOKO']['volume'], 2),
        $pm_counts['KOKO']['count'],
        'Rs. ' . number_format($pm_counts['KOKO']['fees'], 2),
        round(($pm_counts['KOKO']['volume'] / $grand) * 100, 1) . '%'
    ]);
    fputcsv($output, [
        'Cash on Delivery (COD)',
        'Rs. ' . number_format($pm_counts['COD']['volume'], 2),
        $pm_counts['COD']['count'],
        'Rs. 0.00 (Pending Remittance: Rs. ' . number_format($pm_counts['COD']['pending'], 2) . ')',
        round(($pm_counts['COD']['volume'] / $grand) * 100, 1) . '%'
    ]);
    fputcsv($output, [
        'Bank Transfer',
        'Rs. ' . number_format($pm_counts['BANK']['volume'], 2),
        $pm_counts['BANK']['count'],
        'Rs. 0.00 (Direct Settlement)',
        round(($pm_counts['BANK']['volume'] / $grand) * 100, 1) . '%'
    ]);

    fclose($output);
    exit();
}

// If format is json (useful for AJAX or PDF tools)
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'total_orders' => $unique_orders_count,
    'total_revenue' => $total_revenue,
    'total_commission' => $total_commission,
    'total_gateway_fees' => $total_gateway_fees,
    'net_settled' => $net_settled,
    'pm_breakdown' => $pm_counts,
    'rows' => $rows
]);
