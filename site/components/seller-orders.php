<?php
// Pagination and Filters Setup for Orders
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'ord.created_at';
$dir = $_GET['dir'] ?? 'DESC';

$allowed_sorts = ['ord.created_at', 'ord.order_code', 'product_name', 'ord.status'];
$allowed_dirs = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sorts)) $sort = 'ord.created_at';
if (!in_array(strtoupper($dir), $allowed_dirs)) $dir = 'DESC';

// Build Query
$params = [$_SESSION['userid']];
$where = "WHERE p.seller_id = ?";

if (!empty($search)) {
    $where .= " AND (ord.order_code LIKE ? OR u.first_name LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== 'all') {
    $where .= " AND ord.status = ?";
    $params[] = $status;
}

// Count total
$countQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM order_items o
    JOIN orders ord ON o.order_id = ord.id
    JOIN products p ON o.product_id = p.id
    JOIN users u ON ord.user_id = u.id
    $where
");
$countQuery->execute($params);
$total_orders = $countQuery->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Address formatting helper
if (!function_exists('cleanAddress')) {
    function cleanAddress($ord) {
        $parts = [];
        // Split line1 if it already contains commas
        if(!empty($ord['address_line1'])) {
            $line1Parts = array_map('trim', explode(',', $ord['address_line1']));
            $parts = array_merge($parts, $line1Parts);
        }
        if(!empty($ord['address_line2'])) $parts[] = $ord['address_line2'];
        if(!empty($ord['city'])) $parts[] = $ord['city'];
        if(!empty($ord['province'])) $parts[] = $ord['province'];
        
        // Remove empty strings and trim
        $parts = array_filter(array_map('trim', $parts));
        
        // array_unique preserves keys, re-index it and join
        return implode(', ', array_unique($parts));
    }
}

// Fetch orders
$sql = "
    SELECT o.*, ord.id as order_id, ord.order_code, ord.status, ord.tracking_number, ord.courier_company, ord.created_at as order_date, ord.payment_method, ord.payment_status,
           u.first_name, u.last_name, u.email as buyer_email,
           ua.full_name as shipping_name, ua.address_line1, ua.address_line2, ua.city, ua.province, ua.postal_code, ua.phone1, ua.phone2,
           b.name as brand_name,
           pc.color_name,
           p.name as product_name, p.total_qty as current_stock, cs.sku,
           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as product_image
    FROM order_items o
    JOIN orders ord ON o.order_id = ord.id
    JOIN products p ON o.product_id = p.id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN color_sizes cs ON o.variant_id = cs.id
    LEFT JOIN product_colors pc ON cs.color_id = pc.id
    JOIN users u ON ord.user_id = u.id
    LEFT JOIN user_addresses ua ON ord.shipping_address_id = ua.id
    $where
    ORDER BY $sort $dir
    LIMIT ? OFFSET ?
";
$orderStmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $param) {
    $orderStmt->bindValue($paramIndex++, $param);
}
$orderStmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$orderStmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$orderStmt->execute();
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

// Normalize status if webhook missed
foreach ($orders as &$ord_ref) {
    if (strtoupper($ord_ref['status']) === 'PENDING_PAYMENT' && strtoupper($ord_ref['payment_status'] ?? '') === 'PAID') {
        $ord_ref['status'] = 'PAID';
    }
}
unset($ord_ref);

// Helper function to build sort links
if (!function_exists('sortLink')) {
    function sortLink($column, $label, $current_sort, $current_dir, $search, $status) {
        $new_dir = ($current_sort === $column && $current_dir === 'ASC') ? 'DESC' : 'ASC';
        $icon = '';
        if ($current_sort === $column) {
            $icon = $current_dir === 'ASC' ? '<i class="fas fa-sort-up ml-1"></i>' : '<i class="fas fa-sort-down ml-1"></i>';
        } else {
            $icon = '<i class="fas fa-sort text-gray-300 ml-1"></i>';
        }
        $searchQuery = $search !== null ? "&search=".urlencode($search) : "";
        $url = "?tab=orders$searchQuery&status=".urlencode($status)."&sort=".urlencode($column)."&dir=$new_dir";
        return "<a href=\"$url\" class=\"hover:text-black transition-colors\">$label $icon</a>";
    }
}
?>

<!-- Load Export Utilities -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="js/export-utils.js"></script>

<div class="bg-white rounded-2xl shadow-sm border border-[#F1F5F9] overflow-hidden mb-8">
    <div class="p-6 border-b border-[#F1F5F9] bg-[#F8FAFC]">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <h2 class="font-black text-navy uppercase tracking-wide">My Orders</h2>
            
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-2 flex-1 lg:max-w-2xl lg:justify-end">
                <input type="hidden" name="tab" value="orders">
                
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Order ID, Buyer, or Product..." 
                           class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                </div>
                
                <select name="status" onchange="this.form.submit()" class="bg-white border border-gray-200 text-sm font-bold text-navy rounded-xl px-4 py-2 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all cursor-pointer">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="handover_to_center" <?= $status === 'handover_to_center' ? 'selected' : '' ?>>Handed to Center</option>
                    <option value="received_at_center" <?= $status === 'received_at_center' ? 'selected' : '' ?>>Received at Center</option>
                    <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="out_for_delivery" <?= $status === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                    <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                
                <button type="submit" class="hidden sm:block bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-xl text-sm font-bold transition-colors">
                    Filter
                </button>
            </form>

            <!-- Export Buttons -->
            <div class="flex gap-2 border-l border-gray-200 pl-4 ml-2">
                <button type="button" onclick="openExportModal('orders', 'Orders', 'csv', [{value: 'pending', label: 'Pending'}, {value: 'confirmed', label: 'Confirmed'}, {value: 'ready_to_delivery', label: 'Ready to Delivery'}, {value: 'accepted', label: 'Accepted'}, {value: 'shipped', label: 'Shipped'}, {value: 'delivered', label: 'Delivered'}, {value: 'completed', label: 'Completed'}, {value: 'cancelled', label: 'Cancelled'}])" class="h-10 px-4 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl text-sm font-bold text-gray-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-file-csv text-gray-400"></i> Export CSV
                </button>
                <button type="button" onclick="openExportModal('orders', 'Orders', 'pdf', [{value: 'pending', label: 'Pending'}, {value: 'confirmed', label: 'Confirmed'}, {value: 'ready_to_delivery', label: 'Ready to Delivery'}, {value: 'accepted', label: 'Accepted'}, {value: 'shipped', label: 'Shipped'}, {value: 'delivered', label: 'Delivered'}, {value: 'completed', label: 'Completed'}, {value: 'cancelled', label: 'Cancelled'}])" class="h-10 px-4 bg-black text-white hover:bg-gray-800 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-lg shadow-black/10">
                    <i class="fas fa-file-pdf text-red-400"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
    
    <div class="px-6 py-3 bg-white border-b border-[#F1F5F9] text-xs font-bold text-gray-400">
        Showing <?= min($offset + 1, $total_orders) ?>-<?= min($offset + $limit, $total_orders) ?> of <?= $total_orders ?> orders
    </div>

    <!-- Desktop Table View Premium Design -->
    <div class="hidden lg:block overflow-x-auto p-4">
        <table class="w-full text-left border-collapse rounded-2xl overflow-hidden border border-[#F1F5F9]" style="border-spacing: 0;">
            <thead>
                <tr>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 px-4 border-b border-[#F1F5F9]">Date</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Order ID & Payment</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Product & Variant</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">Qty & Stock</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Total & Earn</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Buyer</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">Status</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">SLA</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php if (count($orders) > 0): foreach ($orders as $ord): ?>
                <tr class="hover:bg-slate-50/70 transition-colors group h-[72px]">
                    <td class="p-3 px-4 border-t border-[#F1F5F9] whitespace-nowrap">
                        <p class="font-medium text-slate-800 text-sm"><?= date('M d, Y', strtotime($ord['order_date'])) ?></p>
                        <p class="text-[11px] text-slate-500 font-medium"><?= date('h:i A', strtotime($ord['order_date'])) ?></p>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] whitespace-nowrap">
                        <span class="font-bold text-[#0066FF] cursor-pointer hover:underline text-sm"><?= htmlspecialchars($ord['order_code']) ?></span>
                        <div class="mt-1">
                            <?php if (strtoupper($ord['payment_status'] ?? '') === 'PAID'): ?>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                    <i class="fas fa-credit-card text-green-500"></i> <?= htmlspecialchars($ord['payment_method'] ?? 'CARD') ?> PAID
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    <i class="fas fa-clock text-slate-400"></i> <?= htmlspecialchars($ord['payment_method'] ?? 'CARD') ?> PENDING
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] min-w-[250px]">
                        <div class="flex items-center gap-3">
                            <img src="../assets/uploads/products/<?= htmlspecialchars($ord['product_image'] ?? 'no-image.jpg') ?>" class="w-10 h-10 rounded-lg object-cover border border-slate-200" onerror="this.src='../image/placeholder.png'">
                            <div>
                                <p class="font-semibold text-slate-800 text-sm line-clamp-2 leading-tight" title="<?= htmlspecialchars($ord['product_name']) ?>"><?= htmlspecialchars($ord['product_name']) ?></p>
                                <p class="text-[11px] text-slate-500 mt-0.5">Size: <?= htmlspecialchars($ord['size']) ?> | Color: <?= htmlspecialchars($ord['color_name'] ?? 'N/A') ?> | SKU: <?= htmlspecialchars($ord['sku'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] text-center">
                        <p class="font-bold text-slate-800 text-sm"><?= $ord['quantity'] ?></p>
                        <p class="text-[10px] font-medium <?= ($ord['current_stock'] > 5) ? 'text-green-600' : 'text-red-500' ?> mt-0.5">Stock: <?= $ord['current_stock'] ?? 0 ?></p>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] whitespace-nowrap">
                        <p class="font-bold text-slate-800 text-sm">Rs. <?= number_format($ord['total_price'], 2) ?></p>
                        <p class="text-[11px] font-medium text-emerald-600 mt-0.5">Earn Rs. <?= number_format($ord['seller_earning'] * $ord['quantity'], 2) ?></p>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9]">
                        <p class="font-semibold text-slate-800 text-sm flex items-center gap-1">
                            <?= htmlspecialchars($ord['shipping_name'] ?? ($ord['first_name'] . ' ' . $ord['last_name'])) ?>
                        </p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-medium truncate max-w-[80px]" title="<?= htmlspecialchars($ord['city'] ?? '') ?>"><?= htmlspecialchars($ord['city'] ?? 'N/A') ?></span>
                            <a href="tel:<?= htmlspecialchars($ord['phone1'] ?? '') ?>" class="text-blue-500 hover:text-blue-700" title="Call Buyer"><i class="fas fa-phone text-[10px]"></i></a>
                        </div>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] text-center whitespace-nowrap">
                        <?php 
                            $statusMap = [
                                'PENDING_PAYMENT' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'dot' => 'bg-slate-400', 'label' => 'Awaiting Payment'],
                                'PAID' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'dot' => 'bg-yellow-500 animate-pulse', 'label' => 'Paid - Action Req'],
                                'PENDING' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'dot' => 'bg-yellow-500 animate-pulse', 'label' => 'Pending'],
                                'ACCEPTED' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500', 'label' => 'Confirmed'],
                                'PACKED' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'dot' => 'bg-indigo-500', 'label' => 'Packed'],
                                'SHIPPED' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'dot' => 'bg-purple-500', 'label' => 'Shipped'],
                                'DELIVERED' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Delivered'],
                                'COMPLETED' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'dot' => 'bg-slate-400', 'label' => 'Completed'],
                                'CANCELLED' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'dot' => 'bg-red-500', 'label' => 'Cancelled']
                            ];
                            $s = strtoupper($ord['status']);
                            if ($s === 'PENDING_PAYMENT' && strtoupper($ord['payment_status'] ?? '') === 'PAID') {
                                // Fallback if webhook missed
                                $s = 'PAID';
                            }
                            $st = $statusMap[$s] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'label' => $s];
                        ?>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold <?= $st['bg'] ?> <?= $st['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $st['dot'] ?>"></span>
                            <?= $st['label'] ?>
                        </div>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] text-center whitespace-nowrap">
                        <?php if (in_array($s, ['PAID', 'PENDING'])): ?>
                            <span class="text-xs font-bold text-red-500"><i class="far fa-clock"></i> 23h left</span>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 border-t border-[#F1F5F9] text-center whitespace-nowrap">
                        <div class="flex items-center justify-center gap-2">
                            <?php 
                                $orderJson = htmlspecialchars(json_encode([
                                    "order_id" => $ord["order_code"],
                                    "date" => date("M d, Y h:i A", strtotime($ord["order_date"])),
                                    "status" => $s,
                                    "tracking_number" => $ord["tracking_number"] ?? "",
                                    "courier_company" => $ord["courier_company"] ?? "",
                                    "product_name" => $ord["product_name"],
                                    "brand_name" => $ord["brand_name"] ?? "",
                                    "color_name" => $ord["color_name"] ?? "",
                                    "product_image" => $ord["product_image"] ? "../assets/uploads/products/" . $ord["product_image"] : "../image/no-image.jpg",
                                    "size" => $ord["size"],
                                    "sku" => $ord["sku"] ?? "",
                                    "current_stock" => $ord["current_stock"] ?? 0,
                                    "qty" => $ord["quantity"],
                                    "price" => $ord["unit_price"],
                                    "total" => $ord["total_price"],
                                    "subtotal" => $ord["total_price"] - ($ord["delivery_fee"] ?? 0),
                                    "delivery" => $ord["delivery_fee"] ?? 0,
                                    "commission" => $ord["admin_commission"] ?? 0,
                                    "gateway" => $ord["gateway_fee"] ?? 0,
                                    "net" => $ord["seller_earning"] * $ord["quantity"],
                                    "buyer_name" => $ord["shipping_name"] ?? ($ord["first_name"] . " " . $ord["last_name"]),
                                    "address" => cleanAddress($ord),
                                    "phone" => $ord["phone1"] ?? "",
                                    "email" => $ord["buyer_email"] ?? "",
                                    "payment_method" => $ord["payment_method"] ?? "CARD",
                                    "payment_status" => $ord["payment_status"] ?? ""
                                ], JSON_HEX_APOS | JSON_HEX_QUOT));
                            ?>
                            <?php if (in_array($s, ['PAID', 'PENDING'])): ?>
                                <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-[#0A1020] hover:bg-black text-white text-xs font-bold rounded-full transition-colors shadow-sm">Accept</button>
                            <?php elseif ($s === 'ACCEPTED'): ?>
                                <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-full transition-colors shadow-sm">Mark Packed</button>
                            <?php elseif ($s === 'PACKED' || $s === 'HANDOVER_TO_CENTER' || $s === 'RECEIVED_AT_CENTER'): ?>
                                <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-full transition-colors shadow-sm">Ship</button>
                            <?php elseif ($s === 'PENDING_PAYMENT'): ?>
                                <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-full transition-colors">Review</button>
                            <?php else: ?>
                                <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>)' class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-full transition-colors">View</button>
                            <?php endif; ?>
                            
                            <button class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors flex items-center justify-center" onclick='openOrderDetailsDrawer(<?= $orderJson ?>)'>
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="7" class="p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-4xl"><i class="fas fa-shopping-bag"></i></div>
                        <h3 class="text-lg font-black text-navy mb-1">No Orders Found</h3>
                        <p class="text-gray-500">There are no orders matching your criteria.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="lg:hidden space-y-3 p-4 bg-gray-50">
        <?php if (count($orders) > 0): foreach ($orders as $ord): ?>
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col gap-3">
            <div class="flex justify-between items-start">
                <div>
                    <span class="font-black text-[#0066FF] bg-blue-50 px-2 py-1 rounded-md text-xs tracking-wider inline-block mb-1"><?= htmlspecialchars($ord['order_code']) ?></span>
                    <p class="text-xs text-gray-500 font-bold"><?= date('M d, Y h:i A', strtotime($ord['order_date'])) ?></p>
                </div>
                <div>
                    <?php if (strtoupper($ord['status']) == 'PENDING_PAYMENT'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 uppercase tracking-wide"><i class="fas fa-circle-notch fa-spin mr-1 text-slate-400"></i> Awaiting Payment</span>
                    <?php elseif (in_array(strtoupper($ord['status']), ['PENDING', 'PAID'])): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wide">Paid - Action Req</span>
                    <?php elseif (strtoupper($ord['status']) == 'ACCEPTED'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-100 text-purple-800 uppercase tracking-wide">Accepted</span>
                    <?php elseif (strtoupper($ord['status']) == 'HANDOVER_TO_CENTER'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-orange-100 text-orange-800 uppercase tracking-wide">Handed to Center</span>
                    <?php elseif (strtoupper($ord['status']) == 'RECEIVED_AT_CENTER'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100 text-indigo-800 uppercase tracking-wide">At Center</span>
                    <?php elseif (strtoupper($ord['status']) == 'SHIPPED'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wide">Shipped</span>
                    <?php elseif (strtoupper($ord['status']) == 'OUT_FOR_DELIVERY'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-100 text-teal-800 uppercase tracking-wide">Out for Delivery</span>
                    <?php elseif (in_array(strtoupper($ord['status']), ['DELIVERED', 'COMPLETED'])): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wide"><?= htmlspecialchars($ord['status']) ?></span>
                    <?php elseif (strtoupper($ord['status']) == 'CANCELLED'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wide">Cancelled</span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-100 text-gray-800 uppercase tracking-wide"><?= htmlspecialchars($ord['status']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex gap-4">
                <img src="<?= htmlspecialchars($ord['product_image'] ? "../assets/uploads/products/" . $ord['product_image'] : "../image/no-image.jpg") ?>" class="w-16 h-16 rounded-xl object-cover bg-gray-50 border border-gray-100 shrink-0">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-navy text-sm leading-tight line-clamp-2"><?= htmlspecialchars($ord['product_name']) ?></p>
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mt-1">Size: <?= htmlspecialchars($ord['size']) ?> | Qty: <?= $ord['quantity'] ?></p>
                    <p class="text-[11px] font-bold text-gray-500 mt-1">Buyer: <?= htmlspecialchars($ord['shipping_name'] ?? ($ord['first_name'] . ' ' . $ord['last_name'])) ?></p>
                </div>
            </div>
            
            <?php if(!empty($ord['tracking_number'])): ?>
                <div class="bg-gray-50 p-2 rounded-lg border border-gray-100">
                    <p class="text-[10px] text-gray-500 font-bold uppercase">Tracking: <?= htmlspecialchars($ord['courier_company']) ?> - <?= htmlspecialchars($ord['tracking_number']) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-50">
                <?php 
                    $orderJson = htmlspecialchars(json_encode([
                        "order_id" => $ord["order_code"],
                        "date" => date("M d, Y h:i A", strtotime($ord["order_date"])),
                        "status" => strtoupper($ord['status']),
                        "tracking_number" => $ord["tracking_number"] ?? "",
                        "courier_company" => $ord["courier_company"] ?? "",
                        "product_name" => $ord["product_name"],
                        "brand_name" => $ord["brand_name"] ?? "",
                        "color_name" => $ord["color_name"] ?? "",
                        "product_image" => $ord["product_image"] ? "../assets/uploads/products/" . $ord["product_image"] : "../image/no-image.jpg",
                        "size" => $ord["size"],
                        "sku" => $ord["sku"] ?? "",
                        "current_stock" => $ord["current_stock"] ?? 0,
                        "qty" => $ord["quantity"],
                        "price" => $ord["unit_price"],
                        "total" => $ord["total_price"],
                        "subtotal" => $ord["total_price"] - ($ord["delivery_fee"] ?? 0),
                        "delivery" => $ord["delivery_fee"] ?? 0,
                        "commission" => $ord["admin_commission"] ?? 0,
                        "gateway" => $ord["gateway_fee"] ?? 0,
                        "net" => $ord["seller_earning"] * $ord["quantity"],
                        "buyer_name" => $ord["shipping_name"] ?? ($ord["first_name"] . " " . $ord["last_name"]),
                        "address" => cleanAddress($ord),
                        "phone" => $ord["phone1"] ?? "",
                        "email" => $ord["buyer_email"] ?? "",
                        "payment_method" => $ord["payment_method"] ?? "CARD",
                        "payment_status" => $ord["payment_status"] ?? ""
                    ], JSON_HEX_APOS | JSON_HEX_QUOT));
                    $s = strtoupper($ord['status']);
                ?>
                <?php if (in_array($s, ['PAID', 'PENDING'])): ?>
                    <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-[#0A1020] hover:bg-black text-white text-xs font-bold rounded-xl transition-colors shadow-sm flex items-center gap-1">Accept</button>
                <?php elseif ($s === 'ACCEPTED'): ?>
                    <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm flex items-center gap-1">Mark Packed</button>
                <?php elseif ($s === 'PACKED' || $s === 'HANDOVER_TO_CENTER' || $s === 'RECEIVED_AT_CENTER'): ?>
                    <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm flex items-center gap-1">Ship</button>
                <?php elseif ($s === 'PENDING_PAYMENT'): ?>
                    <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>, "actions")' class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors flex items-center gap-1">Review</button>
                <?php else: ?>
                    <button onclick='openOrderDetailsDrawer(<?= $orderJson ?>)' class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors flex items-center gap-1"><i class="fas fa-eye"></i> View</button>
                <?php endif; ?>
                
                <button class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors flex items-center justify-center" onclick='openOrderDetailsDrawer(<?= $orderJson ?>)'>
                    <i class="fas fa-ellipsis-v text-xs"></i>
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="text-center p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-2xl"><i class="fas fa-shopping-bag"></i></div>
            <h3 class="text-lg font-black text-navy mb-1">No Orders</h3>
            <p class="text-sm text-gray-500 mb-4">You haven't received any orders yet.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-[#F1F5F9] bg-white flex justify-center">
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?tab=orders&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-left text-xs"></i></a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?tab=orders&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $i ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors <?= $i === $page ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?tab=orders&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-right text-xs"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Enterprise Order Details Drawer -->
<div id="orderDetailsDrawerOverlay" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0" onclick="closeOrderDetailsDrawer()"></div>
<div id="orderDetailsDrawer" class="fixed inset-y-0 right-0 z-[101] w-full max-w-[700px] bg-[#F8FAFC] shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col">
    
    <!-- Header -->
    <div class="px-6 py-5 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <h3 class="text-xl font-black text-slate-800 tracking-tight" id="drawer_order_id"></h3>
                <span id="drawer_status_badge" class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider"></span>
            </div>
            <p class="text-[13px] font-semibold text-slate-500" id="drawer_date_payment"></p>
        </div>
        <button onclick="closeOrderDetailsDrawer()" class="w-10 h-10 rounded-full bg-slate-50 text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition-colors">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- Tabs Header -->
    <div class="px-6 bg-white border-b border-slate-100 shrink-0 flex gap-6">
        <button onclick="switchTab('details')" class="drawer-tab pb-3 pt-4 text-sm font-bold border-b-2 transition-colors border-[#0A1020] text-[#0A1020]" id="tab_btn_details">Details</button>
        <button onclick="switchTab('timeline')" class="drawer-tab pb-3 pt-4 text-sm font-bold border-b-2 transition-colors border-transparent text-slate-500 hover:text-slate-800" id="tab_btn_timeline">Timeline</button>
        <button onclick="switchTab('actions')" class="drawer-tab pb-3 pt-4 text-sm font-bold border-b-2 transition-colors border-transparent text-slate-500 hover:text-slate-800" id="tab_btn_actions">Actions</button>
        <button onclick="switchTab('earnings')" class="drawer-tab pb-3 pt-4 text-sm font-bold border-b-2 transition-colors border-transparent text-slate-500 hover:text-slate-800" id="tab_btn_earnings">Earnings</button>
    </div>

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-y-auto p-6" id="drawer_content">
        
        <!-- Tab 1: Details -->
        <div id="tab_content_details" class="drawer-panel space-y-6 block">
            <!-- Product Card -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex flex-col sm:flex-row gap-5">
                <img id="drawer_product_image" src="" class="w-24 h-24 rounded-xl object-cover bg-slate-50 border border-slate-100">
                <div class="flex-1">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1" id="drawer_brand_name"></p>
                    <h4 class="font-bold text-slate-800 text-[15px] leading-snug mb-2" id="drawer_product_name"></h4>
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500 mb-3">
                        <span class="bg-slate-50 px-2 py-1 rounded border border-slate-100">Size: <span id="drawer_size" class="text-slate-700"></span></span>
                        <span class="bg-slate-50 px-2 py-1 rounded border border-slate-100" id="drawer_color_wrap">Color: <span id="drawer_color" class="text-slate-700"></span></span>
                        <span class="bg-slate-50 px-2 py-1 rounded border border-slate-100">SKU: <span id="drawer_sku" class="text-slate-700"></span></span>
                        <span class="bg-slate-50 px-2 py-1 rounded border border-slate-100">Qty: <span id="drawer_qty" class="text-slate-700"></span></span>
                    </div>
                    <p class="text-[11px] font-bold text-emerald-600" id="drawer_stock"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Buyer Card -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5"><i class="fas fa-user"></i> Buyer Information</h5>
                    <p class="font-bold text-slate-800 text-sm mb-2" id="drawer_buyer_name"></p>
                    <div class="space-y-2 text-xs font-medium text-slate-600">
                        <p class="flex items-center gap-2"><i class="fas fa-phone w-3 text-slate-400"></i> <a id="drawer_phone_link" href="#" class="hover:text-[#0066FF] transition-colors"><span id="drawer_phone"></span></a></p>
                        <p class="flex items-center gap-2"><i class="fas fa-envelope w-3 text-slate-400"></i> <span id="drawer_email"></span></p>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex gap-2">
                        <a id="drawer_whatsapp" href="#" target="_blank" class="flex-1 py-1.5 bg-green-50 text-green-700 hover:bg-green-100 text-[11px] font-bold rounded-lg text-center transition-colors"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                        <a id="drawer_call" href="#" class="flex-1 py-1.5 bg-slate-50 text-slate-700 hover:bg-slate-100 text-[11px] font-bold rounded-lg text-center transition-colors"><i class="fas fa-phone"></i> Call</a>
                    </div>
                </div>

                <!-- Shipping Card -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex flex-col">
                    <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5"><i class="fas fa-truck"></i> Shipping Address</h5>
                    <p class="text-[13px] font-semibold text-slate-600 leading-relaxed flex-1" id="drawer_address"></p>
                    <div class="mt-4 pt-4 border-t border-slate-50">
                        <a id="drawer_map_link" href="#" target="_blank" class="block w-full py-1.5 bg-blue-50 text-[#0066FF] hover:bg-blue-100 text-[11px] font-bold rounded-lg text-center transition-colors"><i class="fas fa-map-marked-alt"></i> View on Map</a>
                    </div>
                </div>
            </div>
            
            <!-- Delivery Courier Info -->
            <div id="drawer_courier_info" class="hidden bg-purple-50 rounded-2xl p-5 border border-purple-100 shadow-sm">
                 <h5 class="text-[10px] font-black text-purple-600 uppercase tracking-widest mb-3 flex items-center gap-1.5"><i class="fas fa-box"></i> Shipping Details</h5>
                 <div class="grid grid-cols-2 gap-4">
                     <div>
                         <p class="text-[10px] text-purple-500 font-bold mb-0.5">Courier</p>
                         <p class="text-sm font-bold text-purple-900" id="drawer_courier_name"></p>
                     </div>
                     <div>
                         <p class="text-[10px] text-purple-500 font-bold mb-0.5">Tracking No</p>
                         <p class="text-sm font-bold text-purple-900" id="drawer_tracking_no"></p>
                     </div>
                 </div>
            </div>
        </div>

        <!-- Tab 2: Timeline -->
        <div id="tab_content_timeline" class="drawer-panel hidden">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <div class="relative border-l-2 border-slate-100 ml-3 space-y-8 py-2" id="drawer_timeline_container">
                    <!-- Dynamic Timeline -->
                </div>
            </div>
        </div>

        <!-- Tab 3: Actions -->
        <div id="tab_content_actions" class="drawer-panel hidden">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm" id="action_container">
                <!-- Dynamic Actions Form -->
            </div>
        </div>

        <!-- Tab 4: Earnings -->
        <div id="tab_content_earnings" class="drawer-panel hidden space-y-4">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Price Breakdown</h5>
                
                <div class="space-y-3 text-[13px] font-semibold text-slate-600">
                    <div class="flex justify-between items-center">
                        <p>Subtotal (Qty x Price)</p>
                        <p class="text-slate-800" id="drawer_earn_subtotal"></p>
                    </div>
                    
                    <div class="flex justify-between items-center text-red-500">
                        <p>Commission (OXXA Fee)</p>
                        <p id="drawer_earn_commission"></p>
                    </div>
                    
                    <div class="flex justify-between items-center text-red-500">
                        <p>Payment Gateway Fee</p>
                        <p id="drawer_earn_gateway"></p>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                    <p class="text-sm font-black text-slate-800 uppercase tracking-wide">Net Payout</p>
                    <p class="text-xl font-black text-emerald-600" id="drawer_earn_net"></p>
                </div>
                
                <div class="mt-4 p-3 bg-slate-50 rounded-xl text-center">
                    <p class="text-[11px] font-bold text-slate-500" id="drawer_earn_hold_text">Funds will be held until the 14-day return window completes.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
let currentOrder = null;

function openOrderDetailsDrawer(order, defaultTab = 'details') {
    currentOrder = order;
    
    // Header
    document.getElementById('drawer_order_id').textContent = order.order_id;
    document.getElementById('drawer_date_payment').innerHTML = `${order.date} &bull; ${order.payment_method === 'CARD' ? '💳 Card' : '🚚 COD'} Payment`;
    
    // Status Badge
    const badgeContainer = document.getElementById('drawer_status_badge');
    const s = order.status.toUpperCase();
    let bg, text;
    if(s==='PENDING_PAYMENT') { bg='bg-slate-100'; text='text-slate-600'; }
    else if(s==='PAID' || s==='PENDING') { bg='bg-yellow-100'; text='text-yellow-700'; }
    else if(s==='ACCEPTED') { bg='bg-blue-100'; text='text-blue-700'; }
    else if(s==='PACKED') { bg='bg-indigo-100'; text='text-indigo-700'; }
    else if(s==='SHIPPED') { bg='bg-purple-100'; text='text-purple-700'; }
    else if(s==='DELIVERED') { bg='bg-emerald-100'; text='text-emerald-700'; }
    else if(s==='CANCELLED') { bg='bg-red-100'; text='text-red-700'; }
    else { bg='bg-gray-100'; text='text-gray-700'; }
    
    badgeContainer.className = `px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider ${bg} ${text}`;
    badgeContainer.textContent = s.replace('_', ' ');

    // Details Tab
    document.getElementById('drawer_product_name').textContent = order.product_name;
    document.getElementById('drawer_brand_name').textContent = order.brand_name || 'Generic';
    document.getElementById('drawer_product_image').src = order.product_image;
    document.getElementById('drawer_size').textContent = order.size;
    document.getElementById('drawer_sku').textContent = order.sku || 'N/A';
    document.getElementById('drawer_qty').textContent = order.qty;
    
    if (order.color_name) {
        document.getElementById('drawer_color_wrap').style.display = 'inline';
        document.getElementById('drawer_color').textContent = order.color_name;
    } else {
        document.getElementById('drawer_color_wrap').style.display = 'none';
    }
    
    const stockEl = document.getElementById('drawer_stock');
    stockEl.innerHTML = `Your Stock: ${order.current_stock} <i class="fas fa-check-circle ml-0.5"></i>`;
    stockEl.className = order.current_stock > 5 ? 'text-[11px] font-bold text-emerald-600 mt-2 block' : 'text-[11px] font-bold text-red-500 mt-2 block';

    // Buyer & Shipping
    document.getElementById('drawer_buyer_name').textContent = order.buyer_name;
    document.getElementById('drawer_address').textContent = order.address;
    document.getElementById('drawer_phone').textContent = order.phone;
    document.getElementById('drawer_email').textContent = order.email || 'No email provided';
    
    const p1 = order.phone.replace(/\\s+/g, '');
    document.getElementById('drawer_phone_link').href = `tel:${p1}`;
    document.getElementById('drawer_call').href = `tel:${p1}`;
    document.getElementById('drawer_whatsapp').href = `https://wa.me/94${p1.substring(1)}`;
    document.getElementById('drawer_map_link').href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.address)}`;

    // Courier Info
    const courierBox = document.getElementById('drawer_courier_info');
    if (order.tracking_number && order.courier_company) {
        courierBox.classList.remove('hidden');
        document.getElementById('drawer_courier_name').textContent = order.courier_company;
        document.getElementById('drawer_tracking_no').textContent = order.tracking_number;
    } else {
        courierBox.classList.add('hidden');
    }

    // Earnings Tab
    document.getElementById('drawer_earn_subtotal').textContent = `Rs. ${parseFloat(order.subtotal).toLocaleString('en-US', {minimumFractionDigits:2})}`;
    document.getElementById('drawer_earn_commission').textContent = `- Rs. ${parseFloat(order.commission).toLocaleString('en-US', {minimumFractionDigits:2})}`;
    document.getElementById('drawer_earn_gateway').textContent = `- Rs. ${parseFloat(order.gateway).toLocaleString('en-US', {minimumFractionDigits:2})}`;
    document.getElementById('drawer_earn_net').textContent = `Rs. ${parseFloat(order.net).toLocaleString('en-US', {minimumFractionDigits:2})}`;

    // Action Form Builder
    buildActions(order);
    
    // Timeline Builder
    buildTimeline(order);

    // Switch to Details
    switchTab(defaultTab);

    // Show Drawer
    const overlay = document.getElementById('orderDetailsDrawerOverlay');
    const drawer = document.getElementById('orderDetailsDrawer');
    
    overlay.classList.remove('hidden');
    // slight delay for transition
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        drawer.classList.remove('translate-x-full');
    }, 10);
}

function closeOrderDetailsDrawer() {
    const overlay = document.getElementById('orderDetailsDrawerOverlay');
    const drawer = document.getElementById('orderDetailsDrawer');
    
    overlay.classList.add('opacity-0');
    drawer.classList.add('translate-x-full');
    
    setTimeout(() => {
        overlay.classList.add('hidden');
    }, 300);
}

function switchTab(tabId) {
    const tabs = ['details', 'timeline', 'actions', 'earnings'];
    tabs.forEach(t => {
        const content = document.getElementById(`tab_content_${t}`);
        const btn = document.getElementById(`tab_btn_${t}`);
        if(t === tabId) {
            content.classList.remove('hidden');
            content.classList.add('block');
            btn.classList.remove('border-transparent', 'text-slate-500');
            btn.classList.add('border-[#0A1020]', 'text-[#0A1020]');
        } else {
            content.classList.remove('block');
            content.classList.add('hidden');
            btn.classList.remove('border-[#0A1020]', 'text-[#0A1020]');
            btn.classList.add('border-transparent', 'text-slate-500');
        }
    });
}

function buildActions(order) {
    const container = document.getElementById('action_container');
    const s = order.status.toUpperCase();
    
    if (s === 'PENDING_PAYMENT') {
        container.innerHTML = `
            <div class="text-center py-6">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400 text-2xl"><i class="fas fa-hourglass-half"></i></div>
                <h4 class="text-lg font-black text-slate-800 mb-2">Awaiting Payment</h4>
                <p class="text-sm font-medium text-slate-500 max-w-[250px] mx-auto mb-6">The buyer has not completed the payment yet. Do not pack or ship this item.</p>
                <div class="flex flex-col gap-2 max-w-[250px] mx-auto">
                    <button type="button" onclick="document.getElementById('pending_payment_reject_box').classList.toggle('hidden')" class="px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold rounded-xl transition-colors">Reject Order</button>
                    <form id="drawerActionFormForce" class="w-full">
                        <input type="hidden" name="order_id" value="${order.order_id}">
                        <input type="hidden" name="new_status" value="accepted">
                        <button type="submit" class="w-full px-5 py-2 border border-slate-200 text-slate-500 hover:bg-slate-50 text-[10px] font-bold rounded-xl transition-colors" title="Use this if payment was received outside the system or for local testing">Force Accept (Manual)</button>
                    </form>
                </div>
            </div>
            
            <div id="pending_payment_reject_box" class="hidden mb-2 border-t border-slate-100 pt-6">
                <form id="drawerActionForm">
                    <input type="hidden" name="order_id" value="${order.order_id}">
                    <input type="hidden" name="new_status" value="cancelled">
                    <label class="block text-xs font-bold text-slate-700 mb-2">Rejection Reason *</label>
                    <select name="cancellation_reason_type" class="w-full p-3 rounded-xl border border-slate-200 bg-white text-sm font-medium mb-3 focus:outline-none focus:border-[#0A1020]">
                        <option value="Out of Stock">Out of Stock</option>
                        <option value="Pricing Error">Pricing Error</option>
                        <option value="Cannot Deliver to Area">Cannot Deliver to Area</option>
                        <option value="Other">Other</option>
                    </select>
                    <textarea name="cancellation_reason" rows="2" placeholder="Additional details..." class="w-full p-3 rounded-xl border border-slate-200 text-sm font-medium focus:outline-none focus:border-[#0A1020] mb-4"></textarea>
                    
                    <button type="submit" class="w-full h-12 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl shadow-lg shadow-red-200 transition-colors">Confirm Rejection</button>
                </form>
            </div>
        `;
    } else if (s === 'PAID' || s === 'PENDING') {
        container.innerHTML = `
            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4">Accept or Reject Order</h4>
            <form id="drawerActionForm">
                <input type="hidden" name="order_id" value="${order.order_id}">
                <div class="space-y-4 mb-6">
                    <label class="flex items-start p-4 border-2 border-[#0A1020] bg-slate-50 rounded-xl cursor-pointer">
                        <input type="radio" name="new_status" value="accepted" class="mt-0.5 w-4 h-4 text-[#0066FF]" checked onchange="toggleRejectReason()">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-slate-800">Accept Order</span>
                            <span class="block text-xs font-medium text-slate-500 mt-1">Stock will be verified and you can pack the item.</span>
                        </div>
                    </label>
                    <label class="flex items-start p-4 border border-slate-200 hover:bg-slate-50 rounded-xl cursor-pointer transition-colors" id="reject_label">
                        <input type="radio" name="new_status" value="cancelled" class="mt-0.5 w-4 h-4 text-[#0066FF]" onchange="toggleRejectReason()">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-red-600">Reject Order</span>
                            <span class="block text-xs font-medium text-slate-500 mt-1">Auto-refund buyer if paid by card.</span>
                        </div>
                    </label>
                </div>
                
                <div id="reject_reason_box" class="hidden mb-6">
                    <label class="block text-xs font-bold text-slate-700 mb-2">Rejection Reason *</label>
                    <select name="cancellation_reason_type" class="w-full p-3 rounded-xl border border-slate-200 bg-white text-sm font-medium mb-3 focus:outline-none focus:border-[#0A1020] focus:ring-1 focus:ring-[#0A1020]">
                        <option value="Out of Stock">Out of Stock</option>
                        <option value="Pricing Error">Pricing Error</option>
                        <option value="Cannot Deliver to Area">Cannot Deliver to Area</option>
                        <option value="Other">Other</option>
                    </select>
                    <textarea name="cancellation_reason" rows="2" placeholder="Additional details..." class="w-full p-3 rounded-xl border border-slate-200 text-sm font-medium focus:outline-none focus:border-[#0A1020] focus:ring-1 focus:ring-[#0A1020]"></textarea>
                </div>
                
                <button type="submit" class="w-full h-12 bg-[#0A1020] text-white font-bold rounded-xl shadow-lg shadow-slate-200 hover:bg-black transition-colors">Submit Decision</button>
            </form>
        `;
    } else if (s === 'ACCEPTED') {
        container.innerHTML = `
            <div class="text-center py-6 mb-6 border-b border-slate-100">
                <h4 class="text-lg font-black text-slate-800 mb-2">Order Confirmed</h4>
                <p class="text-sm font-medium text-slate-500">Please pack the item securely.</p>
            </div>
            <form id="drawerActionForm">
                <input type="hidden" name="order_id" value="${order.order_id}">
                <input type="hidden" name="new_status" value="handover_to_center">
                <button type="submit" class="w-full h-12 bg-blue-600 text-white font-bold rounded-xl shadow-lg shadow-blue-200 hover:bg-blue-700 transition-colors">Mark as Packed / Handed Over</button>
            </form>
        `;
    } else if (s === 'PACKED' || s === 'HANDOVER_TO_CENTER' || s === 'RECEIVED_AT_CENTER') {
        container.innerHTML = `
            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-4">Shipping Details</h4>
            <form id="drawerShippingForm">
                <input type="hidden" name="order_id" value="${order.order_id}">
                <input type="hidden" name="new_status" value="shipped">
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Courier Company</label>
                        <select name="courier_company" required class="w-full p-3 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                            <option value="Koombiyo">Koombiyo Delivery</option>
                            <option value="Pronto">Pronto</option>
                            <option value="Domex">Domex</option>
                            <option value="Self Delivery">Self Delivery (In-house riders)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Tracking Number</label>
                        <input type="text" name="tracking_number" required placeholder="e.g. SPX-123456" class="w-full p-3 rounded-xl border border-slate-200 text-sm font-medium focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500">
                    </div>
                </div>
                
                <button type="submit" class="w-full h-12 bg-purple-600 text-white font-bold rounded-xl shadow-lg shadow-purple-200 hover:bg-purple-700 transition-colors">Dispatch Order</button>
            </form>
        `;
    } else {
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400 text-2xl"><i class="fas fa-lock"></i></div>
                <h4 class="text-lg font-black text-slate-800 mb-2">Status Locked</h4>
                <p class="text-sm font-medium text-slate-500 max-w-[250px] mx-auto">This order is in <b>${s}</b> state. Further updates will be handled by the admin team.</p>
            </div>
        `;
    }
    
    bindActionForms();
}

function toggleRejectReason() {
    const radio = document.querySelector('input[name="new_status"]:checked');
    const rejectBox = document.getElementById('reject_reason_box');
    const rejectLabel = document.getElementById('reject_label');
    
    // Reset borders
    document.querySelectorAll('input[name="new_status"]').forEach(r => {
        r.closest('label').classList.remove('border-[#0A1020]', 'bg-slate-50', 'border-red-500', 'bg-red-50');
        r.closest('label').classList.add('border-slate-200');
    });
    
    if (radio && radio.value === 'cancelled') {
        rejectBox.classList.remove('hidden');
        rejectLabel.classList.add('border-red-500', 'bg-red-50');
        rejectLabel.classList.remove('border-slate-200');
    } else if (radio) {
        rejectBox.classList.add('hidden');
        radio.closest('label').classList.add('border-[#0A1020]', 'bg-slate-50');
        radio.closest('label').classList.remove('border-slate-200');
    }
}

function bindActionForms() {
    const actionForm = document.getElementById('drawerActionForm');
    if (actionForm) {
        actionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../Backend/seller-order-action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        });
    }

    const forceActionForm = document.getElementById('drawerActionFormForce');
    if (forceActionForm) {
        forceActionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Force Accept?',
                text: "Only use this if you verified payment outside the system (e.g. testing). Continue?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0A1020',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, force accept'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(this);
                    fetch('../Backend/seller-order-action.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire('Success', data.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        });
    }
    
    const shippingForm = document.getElementById('drawerShippingForm');
    if (shippingForm) {
        shippingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../Backend/seller-order-action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        });
    }
}

function buildTimeline(order) {
    const container = document.getElementById('drawer_timeline_container');
    const s = order.status.toUpperCase();
    
    let html = `
        <div class="relative">
            <span class="absolute -left-[21px] top-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-white ring-4 ring-blue-50"></span>
            <p class="text-xs font-bold text-slate-800">Order Placed</p>
            <p class="text-[10px] font-semibold text-slate-500 mt-0.5">${order.date}</p>
        </div>
    `;
    
    if (s !== 'PENDING_PAYMENT') {
        html += `
            <div class="relative">
                <span class="absolute -left-[21px] top-1 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white ring-4 ring-emerald-50"></span>
                <p class="text-xs font-bold text-slate-800">Payment Success - ${order.payment_method}</p>
                <p class="text-[10px] font-semibold text-slate-500 mt-0.5">Paid Confirmed</p>
            </div>
        `;
    }
    
    if (['ACCEPTED', 'PACKED', 'HANDOVER_TO_CENTER', 'RECEIVED_AT_CENTER', 'SHIPPED', 'DELIVERED', 'COMPLETED'].includes(s)) {
        html += `
            <div class="relative">
                <span class="absolute -left-[21px] top-1 w-3 h-3 bg-slate-800 rounded-full border-2 border-white ring-4 ring-slate-100"></span>
                <p class="text-xs font-bold text-slate-800">Order Confirmed by Seller</p>
                <p class="text-[10px] font-semibold text-slate-500 mt-0.5">Accepted</p>
            </div>
        `;
    }
    
    if (['SHIPPED', 'DELIVERED', 'COMPLETED'].includes(s)) {
        html += `
            <div class="relative">
                <span class="absolute -left-[21px] top-1 w-3 h-3 bg-purple-500 rounded-full border-2 border-white ring-4 ring-purple-50"></span>
                <p class="text-xs font-bold text-slate-800">Dispatched</p>
                <p class="text-[10px] font-semibold text-slate-500 mt-0.5">${order.courier_company || 'Assigned Courier'}</p>
            </div>
        `;
    }
    
    if (s === 'DELIVERED' || s === 'COMPLETED') {
        html += `
            <div class="relative">
                <span class="absolute -left-[21px] top-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white ring-4 ring-green-50"></span>
                <p class="text-xs font-bold text-slate-800">Delivered</p>
                <p class="text-[10px] font-semibold text-slate-500 mt-0.5">Successfully handed over to buyer</p>
            </div>
        `;
    }
    
    if (s === 'CANCELLED') {
        html += `
            <div class="relative">
                <span class="absolute -left-[21px] top-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white ring-4 ring-red-50"></span>
                <p class="text-xs font-bold text-slate-800">Cancelled / Rejected</p>
                <p class="text-[10px] font-semibold text-slate-500 mt-0.5">Order was cancelled.</p>
            </div>
        `;
    }
    
    container.innerHTML = html;
}
</script>
