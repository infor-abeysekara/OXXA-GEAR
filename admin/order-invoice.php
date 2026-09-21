<?php
// admin/order-invoice.php - Enterprise Printable Invoice & Packaging Slip
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../include/connection.php");

// Check admin authorization
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Unauthorized access.");
}

$idParam = $_GET['id'] ?? ($_GET['ids'] ?? '');
if (empty($idParam)) {
    die("No order specified.");
}

$orderIds = array_filter(array_map('intval', explode(',', $idParam)));
if (empty($orderIds)) {
    die("Invalid order ID(s).");
}

$placeholders = implode(',', array_fill(0, count($orderIds), '?'));

// Fetch all specified orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           u.first_name, u.last_name, u.email as user_email, u.phone as user_phone,
           ua.full_name as shipping_name, ua.address_line1, ua.address_line2, ua.city, ua.province, ua.postal_code, ua.phone1 as ship_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    WHERE o.id IN ($placeholders)
    ORDER BY o.id DESC
");
$stmt->execute($orderIds);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die("Orders not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commercial Invoice - OXXA GEAR Control Center</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #E2E8F0; color: #0A1020; font-size: 12px; }
        .invoice-sheet {
            background: #FFFFFF;
            width: 800px;
            margin: 24px auto;
            padding: 48px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            page-break-after: always;
        }
        @media print {
            body { background: #FFFFFF; }
            .invoice-sheet { width: 100%; margin: 0; padding: 24px; box-shadow: none; }
            .no-print { display: none !important; }
        }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0A1020; padding-bottom: 20px; margin-bottom: 28px; }
        .brand h1 { font-size: 26px; font-weight: 900; letter-spacing: -0.5px; color: #0A1020; }
        .brand p { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748B; }
        .doc-type { text-align: right; }
        .doc-type h2 { font-size: 20px; font-weight: 900; color: #0A6CFF; text-transform: uppercase; }
        .doc-type p { font-size: 11px; font-weight: 600; color: #64748B; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        .info-card { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; font-size: 11px; line-height: 1.6; }
        .info-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B; margin-bottom: 6px; }

        table.items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 11px; }
        table.items-table th { background: #0A1020; color: #FFFFFF; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.items-table td { padding: 12px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        
        .totals-grid { display: flex; justify-content: flex-end; margin-bottom: 30px; }
        .totals-table { width: 320px; font-size: 11px; }
        .totals-table tr td { padding: 6px 0; }
        .totals-table tr td:last-child { text-align: right; font-weight: 700; }
        .totals-table tr.total-row td { border-top: 2px solid #0A1020; padding-top: 10px; font-size: 14px; font-weight: 900; color: #0A1020; }

        .barcode-box { text-align: center; border-top: 1px dashed #CBD5E1; padding-top: 18px; margin-top: 24px; }
        .barcode { font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 800; letter-spacing: 4px; padding: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; display: inline-block; border-radius: 4px; }
        
        .toolbar { position: fixed; bottom: 24px; right: 24px; background: #0A1020; padding: 12px 20px; border-radius: 9999px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; gap: 12px; align-items: center; }
        .toolbar button { background: #0A6CFF; color: white; border: none; padding: 8px 18px; border-radius: 9999px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .toolbar button:hover { background: #0052cc; }
        .toolbar a { color: #94A3B8; text-decoration: none; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <span style="color: #FFF; font-weight: 700; font-size: 12px;"><?= count($orders) ?> Invoice(s) Ready</span>
        <button onclick="window.print()">Print Invoice(s)</button>
        <a href="manage-orders.php">Close</a>
    </div>

    <?php foreach ($orders as $ord): 
        // Fetch Items for this specific order
        $stmtItems = $pdo->prepare("
            SELECT oi.*, COALESCE(p.product_code, CONCAT('PRD-000', p.id)) as sku,
                   COALESCE(sp.business_name, CONCAT(u_sel.first_name, ' ', u_sel.last_name), 'Direct Seller') as seller_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u_sel ON p.seller_id = u_sel.id
            LEFT JOIN seller_profiles sp ON u_sel.id = sp.user_id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$ord['id']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $buyerName = !empty($ord['shipping_name']) ? $ord['shipping_name'] : trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
        if (empty($buyerName)) $buyerName = 'Valued Customer';
        $buyerPhone = !empty($ord['ship_phone']) ? $ord['ship_phone'] : ($ord['user_phone'] ?? 'N/A');
        $addressParts = array_filter([$ord['address_line1'], $ord['address_line2'], $ord['city'], $ord['province']]);
        $addressStr = !empty($addressParts) ? implode(', ', $addressParts) : 'Customer Delivery Address';
    ?>
    <div class="invoice-sheet">
        <!-- Header -->
        <div class="header">
            <div class="brand">
                <h1>OXXA GEAR</h1>
                <p>Performance Sports Gear & Nutrition • eCommerce Platform</p>
                <div style="margin-top: 6px; font-size: 10px; color: #64748B;">VAT / Reg No: PV-00294819 | www.oxxagear.lk</div>
            </div>
            <div class="doc-type">
                <h2>Tax Invoice</h2>
                <p>Order Ref: <strong><?= htmlspecialchars($ord['order_code']) ?></strong></p>
                <p>Date: <?= date('M d, Y h:i A', strtotime($ord['created_at'])) ?></p>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-title">Billed & Shipped To:</div>
                <div style="font-weight: 800; font-size: 13px; color: #0A1020;"><?= htmlspecialchars($buyerName) ?></div>
                <div>Phone: <?= htmlspecialchars($buyerPhone) ?></div>
                <div>Email: <?= htmlspecialchars($ord['user_email'] ?? 'customer@oxxagear.lk') ?></div>
                <div style="margin-top: 4px; color: #475569;"><?= htmlspecialchars($addressStr) ?></div>
            </div>
            <div class="info-card">
                <div class="info-title">Fulfillment & Payment:</div>
                <div>Payment Method: <strong><?= htmlspecialchars($ord['payment_method']) ?></strong></div>
                <div>Payment Status: <span style="font-weight: 700; color: <?= $ord['payment_status'] === 'paid' ? '#10B981' : '#F59E0B' ?>;"><?= strtoupper($ord['payment_status'] ?? 'PENDING') ?></span></div>
                <div>Courier: <strong><?= htmlspecialchars(!empty($ord['courier_company']) ? $ord['courier_company'] : 'Standard Courier') ?></strong></div>
                <div>Tracking: <strong><?= htmlspecialchars(!empty($ord['tracking_number']) ? $ord['tracking_number'] : 'Pending Dispatch') ?></strong></div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">SKU</th>
                    <th style="width: 45%;">Item Description</th>
                    <th style="width: 15%; text-align: center;">Size / Spec</th>
                    <th style="width: 10%; text-align: center;">Qty</th>
                    <th style="width: 15%; text-align: right;">Total (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #0A6CFF;"><?= htmlspecialchars($it['sku']) ?></td>
                    <td>
                        <div style="font-weight: 700; color: #0A1020;"><?= htmlspecialchars($it['product_name']) ?></div>
                        <div style="font-size: 10px; color: #64748B;">Seller: <?= htmlspecialchars($it['seller_name']) ?></div>
                    </td>
                    <td style="text-align: center; color: #64748B; font-weight: 600;"><?= htmlspecialchars($it['size'] ?? 'Standard') ?></td>
                    <td style="text-align: center; font-weight: 800;"><?= $it['quantity'] ?></td>
                    <td style="text-align: right; font-weight: 800;">Rs. <?= number_format($it['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Table -->
        <div class="totals-grid">
            <table class="totals-table">
                <tr>
                    <td style="color: #64748B;">Item Subtotal:</td>
                    <td>Rs. <?= number_format($ord['subtotal'] > 0 ? $ord['subtotal'] : $ord['total_amount'], 2) ?></td>
                </tr>
                <tr>
                    <td style="color: #64748B;">Standard Delivery Fee:</td>
                    <td>Rs. <?= number_format($ord['delivery_fee'] ?? 0, 2) ?></td>
                </tr>
                <?php if (!empty($ord['coupon_discount']) && $ord['coupon_discount'] > 0): ?>
                <tr>
                    <td style="color: #10B981;">Coupon Discount (<?= htmlspecialchars($ord['coupon_code'] ?? 'DISCOUNT') ?>):</td>
                    <td style="color: #10B981;">- Rs. <?= number_format($ord['coupon_discount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>Final Payable Total:</td>
                    <td>Rs. <?= number_format($ord['total_amount'], 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Barcode & Protection Notice -->
        <div class="barcode-box">
            <div class="barcode">*<?= htmlspecialchars($ord['order_code']) ?>*</div>
            <p style="font-size: 10px; color: #64748B; margin-top: 8px;">
                14-Day Buyer Protection: Inquiries or returns must be initiated within 14 days of delivery receipt. Thank you for shopping with OXXA GEAR!
            </p>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        // Auto-print if query parameter auto_print=1
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            window.print();
        }
    </script>
</body>
</html>
