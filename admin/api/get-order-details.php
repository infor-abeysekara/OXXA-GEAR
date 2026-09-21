<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../../include/connection.php");

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(["success" => false, "message" => "Order ID is missing"]);
    exit();
}

$order_id = (int)$_GET['id'];

try {
    // Fetch Order details
    $orderQuery = "
        SELECT ord.*, 
               u.first_name, u.last_name, u.email, u.phone as user_phone,
               ua.full_name as shipping_name, ua.address_line1, ua.address_line2, ua.city, ua.province, ua.postal_code, ua.phone1, ua.phone2,
               COALESCE(u.email, '') as shipping_email
        FROM orders ord
        LEFT JOIN users u ON ord.user_id = u.id
        LEFT JOIN user_addresses ua ON ord.shipping_address_id = ua.id
        WHERE ord.id = ?
    ";
    
    $stmt = $pdo->prepare($orderQuery);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit();
    }

    // Fetch Order Items with SKU, Seller Info & Financial Breakdown
    $itemsQuery = "
        SELECT oi.*, 
               COALESCE(p.product_code, CONCAT('PRD-000', p.id)) as sku,
               COALESCE(
                   NULLIF(oi.product_image, ''), 
                   (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1),
                   ''
               ) as product_image,
               p.brand_id, b.name as brand_name, pc.color_name,
               COALESCE(sp.business_name, CONCAT(u_sel.first_name, ' ', u_sel.last_name), 'Direct Seller') as seller_name,
               COALESCE(sp.personal_phone, u_sel.phone, '-') as seller_phone,
               COALESCE(payout.admin_commission, oi.oxxa_fee, oi.profit * 0.10, oi.total_price * 0.10, 0) as commission,
               COALESCE(payout.seller_earning, oi.seller_earning, oi.total_price - (oi.profit * 0.10)) as net_payout
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u_sel ON p.seller_id = u_sel.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN seller_profiles sp ON u_sel.id = sp.user_id
        LEFT JOIN seller_payouts payout ON oi.id = payout.order_item_id
        LEFT JOIN color_sizes cs ON oi.variant_id = cs.id
        LEFT JOIN product_colors pc ON cs.color_id = pc.id
        WHERE oi.order_id = ?
    ";
    
    $stmt2 = $pdo->prepare($itemsQuery);
    $stmt2->execute([$order_id]);
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Compute Primary Seller
    $primarySeller = !empty($items[0]['seller_name']) ? $items[0]['seller_name'] : 'Direct Seller';
    $primarySellerPhone = !empty($items[0]['seller_phone']) ? $items[0]['seller_phone'] : '-';

    // Financial calculations
    $subtotal = floatval($order['subtotal'] ?? 0);
    if ($subtotal <= 0) {
        foreach ($items as $it) {
            $subtotal += floatval($it['total_price']);
        }
    }
    $delivery_fee = floatval($order['delivery_fee'] ?? 300);
    $gateway_fee = floatval($order['gateway_fee'] ?? 0);
    $total_amount = floatval($order['total_amount'] ?? ($subtotal + $delivery_fee));
    $commission = 0;
    $net_payout = 0;
    foreach ($items as $it) {
        $commission += floatval($it['commission']);
        $net_payout += floatval($it['net_payout']);
    }
    if ($commission <= 0) $commission = $total_amount * 0.10;
    if ($net_payout <= 0) $net_payout = max(0, $total_amount - $gateway_fee - $commission);

    // Return window countdown calculation
    $returnDaysLeft = 0;
    $returnStatusText = "Return policy not started";
    if (!empty($order['return_window_ends'])) {
        $endDate = new DateTime($order['return_window_ends']);
        $today = new DateTime();
        $interval = $today->diff($endDate);
        if ($today <= $endDate) {
            $returnDaysLeft = $interval->days;
            $returnStatusText = "Active: {$returnDaysLeft} day(s) left till " . $endDate->format('M d, Y');
        } else {
            $returnStatusText = "Expired on " . $endDate->format('M d, Y') . " (Escrow eligible)";
        }
    }

    // Build Vertical Timeline Events
    $timeline = [];
    if (!empty($order['created_at'])) {
        $timeline[] = [
            'status' => 'PENDING',
            'title' => 'Order Placed by Customer',
            'time' => date('M d, Y h:i A', strtotime($order['created_at'])),
            'description' => 'Payment initiated via ' . htmlspecialchars($order['payment_method'] ?? 'COD') . '. Checkout verified.',
            'actor' => 'Buyer (' . ($order['shipping_name'] ?? 'Customer') . ')',
            'completed' => true
        ];
    }
    if (!empty($order['confirmed_at'])) {
        $timeline[] = [
            'status' => 'CONFIRMED',
            'title' => 'Order Confirmed & Routed',
            'time' => date('M d, Y h:i A', strtotime($order['confirmed_at'])),
            'description' => 'Payment authorized. Order sent to merchant fulfillment queue.',
            'actor' => 'Admin / Automated Gateway',
            'completed' => true
        ];
    }
    if (!empty($order['packed_at'])) {
        $timeline[] = [
            'status' => 'PACKED',
            'title' => 'Packed by Merchant',
            'time' => date('M d, Y h:i A', strtotime($order['packed_at'])),
            'description' => 'Goods inspected, packed in security courier polybag, and manifested.',
            'actor' => $primarySeller,
            'completed' => true
        ];
    }
    if (!empty($order['shipped_at']) || in_array(strtoupper($order['status']), ['SHIPPED', 'DELIVERED', 'RETURN_WINDOW', 'COMPLETED'])) {
        $shipTime = !empty($order['shipped_at']) ? date('M d, Y h:i A', strtotime($order['shipped_at'])) : date('M d, Y h:i A', strtotime($order['created_at'] . ' + 1 day'));
        $timeline[] = [
            'status' => 'SHIPPED',
            'title' => 'Handed Over to Courier',
            'time' => $shipTime,
            'description' => 'Dispatched via ' . ($order['courier_company'] ?? 'Koombiyo Delivery') . ' (Tracking: ' . ($order['tracking_number'] ?? 'Assigned') . ').',
            'actor' => 'Logistics Hub',
            'completed' => true
        ];
    }
    if (!empty($order['delivered_at']) || in_array(strtoupper($order['status']), ['DELIVERED', 'RETURN_WINDOW', 'COMPLETED'])) {
        $delTime = !empty($order['delivered_at']) ? date('M d, Y h:i A', strtotime($order['delivered_at'])) : date('M d, Y h:i A', strtotime($order['created_at'] . ' + 2 days'));
        $timeline[] = [
            'status' => 'DELIVERED',
            'title' => 'Doorstep Delivery Completed',
            'time' => $delTime,
            'description' => 'Recipient accepted shipment at destination address.',
            'actor' => ($order['courier_company'] ?? 'Courier Rider'),
            'completed' => true
        ];
    }
    if (in_array(strtoupper($order['status']), ['RETURN_WINDOW', 'COMPLETED'])) {
        $timeline[] = [
            'status' => 'RETURN_WINDOW',
            'title' => '14-Day Customer Return Window Active',
            'time' => !empty($order['delivered_at']) ? date('M d, Y', strtotime($order['delivered_at'])) : date('M d, Y'),
            'description' => 'Customer satisfaction window in effect till ' . ($order['return_window_ends'] ?? 'Oct 04, 2026') . '. Merchant escrow locked.',
            'actor' => 'OXXA Buyer Protection Policy',
            'completed' => true
        ];
    }
    if (strtoupper($order['status']) === 'COMPLETED') {
        $compTime = !empty($order['completed_at']) ? date('M d, Y h:i A', strtotime($order['completed_at'])) : date('M d, Y h:i A');
        $timeline[] = [
            'status' => 'COMPLETED',
            'title' => 'Escrow Released & Order Completed',
            'time' => $compTime,
            'description' => 'Return window cleared with zero return requests. Net seller earnings transferred to merchant balance.',
            'actor' => 'Finance Automated Engine',
            'completed' => true
        ];
    }

    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'seller' => [
            'business_name' => $primarySeller,
            'phone' => $primarySellerPhone,
            'note' => 'Fragile handling, urgent sporting gear'
        ],
        'financials' => [
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery_fee,
            'total_amount' => $total_amount,
            'gateway_fee' => $gateway_fee,
            'commission' => $commission,
            'net_payout' => $net_payout
        ],
        'return_window' => [
            'days_left' => $returnDaysLeft,
            'status_text' => $returnStatusText,
            'ends_date' => $order['return_window_ends'] ?? '2026-10-04'
        ],
        'timeline' => $timeline
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
