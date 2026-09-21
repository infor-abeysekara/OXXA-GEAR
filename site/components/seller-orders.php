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
    $where .= " AND (ord.order_code LIKE ? OR u.first_name LIKE ? OR p.pname LIKE ?)";
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

// Fetch orders
$sql = "
    SELECT o.*, ord.id as order_id, ord.order_code, ord.status, ord.tracking_number, ord.courier_company, ord.created_at as order_date,
           u.first_name, u.last_name,
           ua.full_name as shipping_name, ua.address_line1, ua.city, ua.province, ua.postal_code, ua.phone1, ua.phone2,
           b.name as brand_name,
           pc.color_name,
           p.pname as product_name
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
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
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
                <button type="button" onclick="openExportModal('orders', 'Orders', 'excel', [{value: 'pending', label: 'Pending'}, {value: 'confirmed', label: 'Confirmed'}, {value: 'ready_to_delivery', label: 'Ready to Delivery'}, {value: 'accepted', label: 'Accepted'}, {value: 'shipped', label: 'Shipped'}, {value: 'delivered', label: 'Delivered'}, {value: 'completed', label: 'Completed'}, {value: 'cancelled', label: 'Cancelled'}])" class="h-10 px-4 bg-black text-white hover:bg-gray-800 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-lg shadow-black/10">
                    <i class="fas fa-file-excel text-gray-300"></i> Export Excel
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
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 px-4 border-b border-[#F1F5F9]">
                        <?= sortLink('ord.created_at', 'Date', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">
                        <?= sortLink('ord.order_code', 'Order ID', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">
                        <?= sortLink('product_name', 'Product', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">Qty</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Buyer</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">
                        <?= sortLink('ord.status', 'Status', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-right pr-4 border-b border-[#F1F5F9]">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php if (count($orders) > 0): foreach ($orders as $ord): ?>
                <tr class="hover:bg-[#F8FAFC] transition-colors group">
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <p class="font-bold text-navy"><?= date('M d, Y', strtotime($ord['order_date'])) ?></p>
                        <p class="text-[11px] text-gray-400 font-bold tracking-wide"><?= date('h:i A', strtotime($ord['order_date'])) ?></p>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <span class="font-black text-[#0066FF] bg-blue-50 px-2 py-1 rounded-md text-xs tracking-wider"><?= htmlspecialchars($ord['order_code']) ?></span>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <p class="font-bold text-navy line-clamp-1" title="<?= htmlspecialchars($ord['product_name']) ?>"><?= htmlspecialchars($ord['product_name']) ?></p>
                        <p class="text-[10px] text-gray-500 font-bold uppercase mt-0.5">Size: <?= htmlspecialchars($ord['size']) ?></p>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-center font-bold text-gray-700">
                        <?= $ord['quantity'] ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-gray-600 font-medium">
                        <?= htmlspecialchars($ord['shipping_name'] ?? ($ord['first_name'] . ' ' . $ord['last_name'])) ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-center">
                        <?php if (strtoupper($ord['status']) == 'PENDING'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wider">Pending</span>
                        <?php elseif (strtoupper($ord['status']) == 'ACCEPTED'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 uppercase tracking-wider">Accepted</span>
                        <?php elseif (strtoupper($ord['status']) == 'HANDOVER_TO_CENTER'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-orange-100 text-orange-800 uppercase tracking-wider">Handed to Center</span>
                        <?php elseif (strtoupper($ord['status']) == 'RECEIVED_AT_CENTER'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 uppercase tracking-wider">At Center</span>
                        <?php elseif (strtoupper($ord['status']) == 'SHIPPED'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">Shipped</span>
                        <?php elseif (strtoupper($ord['status']) == 'OUT_FOR_DELIVERY'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800 uppercase tracking-wider">Out for Delivery</span>
                        <?php elseif (in_array(strtoupper($ord['status']), ['DELIVERED', 'COMPLETED'])): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wider"><?= htmlspecialchars($ord['status']) ?></span>
                        <?php elseif (strtoupper($ord['status']) == 'CANCELLED'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wider">Cancelled</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-800 uppercase tracking-wider"><?= htmlspecialchars($ord['status']) ?></span>
                        <?php endif; ?>
                        
                        <?php if(!empty($ord['tracking_number'])): ?>
                            <div class="mt-1 text-[9px] font-bold text-gray-500 uppercase leading-tight line-clamp-1" title="<?= htmlspecialchars($ord['courier_company']) ?>: <?= htmlspecialchars($ord['tracking_number']) ?>">
                                <?= htmlspecialchars($ord['courier_company']) ?>: <?= htmlspecialchars($ord['tracking_number']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-right">
                        <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='openOrderDetailsModal(<?= htmlspecialchars(json_encode([
                                "order_id" => $ord["order_code"],
                                "date" => date("M d, Y h:i A", strtotime($ord["order_date"])),
                                "status" => $ord["status"],
                                "tracking_number" => $ord["tracking_number"] ?? "",
                                "courier_company" => $ord["courier_company"] ?? "",
                                "product_name" => $ord["product_name"],
                                "brand_name" => $ord["brand_name"] ?? "",
                                "color_name" => $ord["color_name"] ?? "",
                                "product_image" => $ord["product_image"],
                                "size" => $ord["size"],
                                "qty" => $ord["quantity"],
                                "price" => $ord["unit_price"],
                                "total" => $ord["total_price"],
                                "buyer_name" => $ord["shipping_name"] ?? ($ord["first_name"] . " " . $ord["last_name"]),
                                "address" => ($ord["address_line1"] ?? "") . ", " . ($ord["city"] ?? "") . ", " . ($ord["province"] ?? ""),
                                "phone" => $ord["phone1"] ?? ""
                            ], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)' class="w-8 h-8 rounded-full bg-white hover:bg-gray-100 text-gray-400 hover:text-navy transition-all shadow-sm flex items-center justify-center" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if(in_array(strtoupper($ord['status']), ['PENDING', 'ACCEPTED'])): ?>
                            <button onclick="openSellerStatusModal('<?= $ord['order_id'] ?>', '<?= strtoupper($ord['status']) ?>', '<?= htmlspecialchars($ord['order_code']) ?>')" class="w-8 h-8 rounded-full bg-white hover:bg-blue-50 text-gray-400 hover:text-[#0066FF] transition-all shadow-sm flex items-center justify-center" title="Update Status">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="7" class="p-12 text-center">
                        <img src="../image/empty-orders.svg" onerror="this.src='https://illustrations.popsy.co/gray/crashed-error.svg'" class="w-48 h-48 mx-auto mb-4 opacity-50">
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
                    <?php if (strtoupper($ord['status']) == 'PENDING'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wide">Pending</span>
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
                <button onclick='openOrderDetailsModal(<?= htmlspecialchars(json_encode([
                    "order_id" => $ord["order_code"],
                    "date" => date("M d, Y h:i A", strtotime($ord["order_date"])),
                    "status" => $ord["status"],
                    "tracking_number" => $ord["tracking_number"] ?? "",
                    "courier_company" => $ord["courier_company"] ?? "",
                    "product_name" => $ord["product_name"],
                    "brand_name" => $ord["brand_name"] ?? "",
                    "color_name" => $ord["color_name"] ?? "",
                    "product_image" => $ord["product_image"],
                    "size" => $ord["size"],
                    "qty" => $ord["quantity"],
                    "price" => $ord["unit_price"],
                    "total" => $ord["total_price"],
                    "buyer_name" => $ord["shipping_name"] ?? ($ord["first_name"] . " " . $ord["last_name"]),
                    "address" => ($ord["address_line1"] ?? "") . ", " . ($ord["city"] ?? "") . ", " . ($ord["province"] ?? ""),
                    "phone" => $ord["phone1"] ?? ""
                ], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)' class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs font-bold transition-colors flex items-center gap-1">
                    <i class="fas fa-eye"></i> View
                </button>
                <?php if(in_array(strtoupper($ord['status']), ['PENDING', 'ACCEPTED'])): ?>
                <button onclick="openSellerStatusModal('<?= $ord['order_id'] ?>', '<?= strtoupper($ord['status']) ?>', '<?= htmlspecialchars($ord['order_code']) ?>')" class="px-3 py-1.5 rounded-lg bg-blue-50 text-[#0066FF] hover:bg-blue-100 text-xs font-bold transition-colors flex items-center gap-1">
                    <i class="fas fa-edit"></i> Status
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="text-center p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
            <img src="../image/empty-orders.svg" onerror="this.src='https://illustrations.popsy.co/gray/crashed-error.svg'" class="w-32 h-32 mx-auto mb-4 opacity-50">
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

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeOrderDetailsModal()"></div>
    <div class="bg-white rounded-3xl w-full max-w-2xl relative z-10 shadow-2xl overflow-hidden flex flex-col max-h-[90vh] transform scale-95 transition-transform duration-300" id="orderDetailsModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0 bg-gradient-to-r from-[#0066FF] to-blue-800 text-white">
            <h3 class="font-black uppercase tracking-wide text-xl">Order Details</h3>
            <button onclick="closeOrderDetailsModal()" class="text-blue-200 hover:text-white transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto">
            <div class="flex flex-col sm:flex-row sm:justify-between items-start sm:items-center mb-6 pb-6 border-b border-gray-100 gap-4">
                <div>
                    <h4 class="text-2xl font-black text-navy" id="modal_view_order_id"></h4>
                    <p class="text-sm font-bold text-gray-400 mt-1" id="modal_view_date"></p>
                </div>
                <div id="modal_view_status_badge"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                <!-- Product Details -->
                <div>
                    <h5 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Product Info</h5>
                    <div class="flex gap-4">
                        <img id="modal_view_image" src="" class="w-20 h-20 rounded-xl object-cover bg-gray-50 border border-gray-100 shadow-sm">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5" id="modal_view_brand_name"></p>
                            <h6 class="font-bold text-navy text-sm mb-1" id="modal_view_product_name"></h6>
                            <p class="text-xs font-bold text-gray-500 mb-2">Size: <span id="modal_view_size" class="text-navy"></span> <span id="modal_view_color_container">| Color: <span id="modal_view_color" class="text-navy"></span></span> | Qty: <span id="modal_view_qty" class="text-navy"></span></p>
                            <p class="text-lg font-black text-[#0066FF]">Rs. <span id="modal_view_total"></span></p>
                        </div>
                    </div>
                </div>

                <!-- Shipping Details -->
                <div>
                    <h5 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Shipping Info</h5>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <p class="font-bold text-navy text-sm mb-1" id="modal_view_buyer_name"></p>
                        <p class="text-xs font-medium text-gray-500 mb-2"><i class="fas fa-map-marker-alt w-4 text-gray-400"></i> <span id="modal_view_address"></span></p>
                        <p class="text-xs font-medium text-gray-500"><i class="fas fa-phone-alt w-4 text-gray-400"></i> <span id="modal_view_phone"></span></p>
                    </div>
                </div>
            </div>

            <div id="modal_view_tracking_section" class="hidden bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                <h5 class="text-xs font-black text-[#0066FF] uppercase tracking-widest mb-2"><i class="fas fa-truck mr-1"></i> Delivery Information</h5>
                <p class="text-sm font-bold text-navy mt-1">Courier: <span id="modal_view_courier" class="text-gray-500"></span></p>
                <p class="text-sm font-bold text-navy mt-1">Tracking No: <span id="modal_view_tracking" class="text-gray-500"></span></p>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">Status updates are managed by the OXXA GEAR Admin team once the package is handed over to the collection center.</p>
            </div>
        </div>
    </div>
</div>

<!-- Seller Order Status Update Modal -->
<div id="sellerStatusModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeSellerStatusModal()"></div>
    <div class="bg-white rounded-3xl w-full max-w-md relative z-10 shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300" id="sellerStatusModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-[#F8FAFC]">
            <h3 class="font-black text-navy uppercase tracking-wide">Update Order</h3>
            <button onclick="closeSellerStatusModal()" class="text-gray-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form action="../Backend/seller-order-action.php" method="POST" class="p-6" id="sellerStatusForm">
            <input type="hidden" name="order_id" id="status_order_id">
            
            <div class="mb-6 text-center">
                <p class="text-sm text-gray-500 font-bold">Order ID</p>
                <p class="text-2xl font-black text-[#0066FF]" id="status_order_code_display"></p>
            </div>
            
            <div class="mb-8">
                <label class="block text-xs font-black text-navy uppercase tracking-widest mb-3">Status Option</label>
                
                <div class="space-y-3">
                    <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors relative group" id="status_option_accepted_container">
                        <input type="radio" name="new_status" value="accepted" id="status_option_accepted" class="w-5 h-5 text-[#0066FF] border-gray-300 focus:ring-[#0066FF]">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-navy">Accept Order</span>
                            <span class="block text-xs text-gray-500">I acknowledge this order and have stock to pack.</span>
                        </div>
                    </label>

                    <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors relative group" id="status_option_reject_container">
                        <input type="radio" name="new_status" value="cancelled" id="status_option_reject" class="w-5 h-5 text-[#0066FF] border-gray-300 focus:ring-[#0066FF]" onchange="toggleRejectionReason()">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-red-600">Reject Order</span>
                            <span class="block text-xs text-gray-500">I cannot fulfill this order (Out of stock, etc).</span>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors relative group" id="status_option_handover_container">
                        <input type="radio" name="new_status" value="handover_to_center" id="status_option_handover" class="w-5 h-5 text-[#0066FF] border-gray-300 focus:ring-[#0066FF]">
                        <div class="ml-3">
                            <span class="block text-sm font-bold text-navy">Handing over to Collecting Center</span>
                            <span class="block text-xs text-gray-500">I have handed over the item to the OXXA collection center.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Rejection Reason Text Area -->
            <div id="rejection_reason_container" class="mb-8 hidden">
                <label class="block text-xs font-black text-navy uppercase tracking-widest mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea name="cancellation_reason" id="cancellation_reason" rows="3" placeholder="Please state why you are rejecting this order. (Required)" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all"></textarea>
                <p class="text-xs text-gray-500 mt-1">If the buyer paid via Card, a 100% refund will be issued.</p>
            </div>
            
            <button type="submit" class="w-full bg-[#0066FF] hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-500/30">
                Update Status
            </button>
        </form>
    </div>
</div>

<script>
// Order Details Modal
function openOrderDetailsModal(order) {
    document.getElementById('modal_view_order_id').textContent = order.order_id;
    document.getElementById('modal_view_date').textContent = order.date;
    
    // Set status badge
    const badgeContainer = document.getElementById('modal_view_status_badge');
    let badgeClass = '';
    let badgeText = order.status;
    
    if (order.status.toUpperCase() === 'PENDING') badgeClass = 'bg-yellow-100 text-yellow-800';
    else if (order.status.toUpperCase() === 'ACCEPTED') badgeClass = 'bg-purple-100 text-purple-800';
    else if (order.status.toUpperCase() === 'HANDOVER_TO_CENTER') { badgeClass = 'bg-orange-100 text-orange-800'; badgeText = 'Handed to Center'; }
    else if (order.status.toUpperCase() === 'RECEIVED_AT_CENTER') { badgeClass = 'bg-indigo-100 text-indigo-800'; badgeText = 'At Center'; }
    else if (order.status.toUpperCase() === 'SHIPPED') badgeClass = 'bg-blue-100 text-blue-800';
    else if (order.status.toUpperCase() === 'OUT_FOR_DELIVERY') badgeClass = 'bg-teal-100 text-teal-800';
    else if (order.status.toUpperCase() === 'DELIVERED' || order.status.toUpperCase() === 'COMPLETED') badgeClass = 'bg-green-100 text-green-800';
    else if (order.status.toUpperCase() === 'CANCELLED') badgeClass = 'bg-red-100 text-red-800';
    else badgeClass = 'bg-gray-100 text-gray-800';
    
    badgeContainer.innerHTML = `<span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-wider ${badgeClass}">${badgeText}</span>`;
    
    // Set product details
    document.getElementById('modal_view_product_name').textContent = order.product_name;
    document.getElementById('modal_view_brand_name').textContent = order.brand_name || 'Generic';
    document.getElementById('modal_view_image').src = order.product_image ? '../assets/uploads/products/' + order.product_image : '../image/no-image.jpg';
    document.getElementById('modal_view_size').textContent = order.size;
    
    if (order.color_name) {
        document.getElementById('modal_view_color_container').style.display = 'inline';
        document.getElementById('modal_view_color').textContent = order.color_name;
    } else {
        document.getElementById('modal_view_color_container').style.display = 'none';
    }
    
    document.getElementById('modal_view_qty').textContent = order.qty;
    document.getElementById('modal_view_total').textContent = parseFloat(order.total).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    // Set shipping details
    document.getElementById('modal_view_buyer_name').textContent = order.buyer_name;
    document.getElementById('modal_view_address').textContent = order.address;
    document.getElementById('modal_view_phone').textContent = order.phone;
    
    // Set tracking details if available
    const trackingSection = document.getElementById('modal_view_tracking_section');
    if (order.tracking_number && order.courier_company) {
        trackingSection.classList.remove('hidden');
        document.getElementById('modal_view_courier').textContent = order.courier_company;
        document.getElementById('modal_view_tracking').textContent = order.tracking_number;
    } else {
        trackingSection.classList.add('hidden');
    }
    
    // Show modal with animation
    const modal = document.getElementById('orderDetailsModal');
    const content = document.getElementById('orderDetailsModalContent');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    }, 10);
}

function closeOrderDetailsModal() {
    const modal = document.getElementById('orderDetailsModal');
    const content = document.getElementById('orderDetailsModalContent');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}

// Seller Status Modal
function openSellerStatusModal(id, currentStatus, orderCode) {
    document.getElementById('status_order_id').value = id;
    document.getElementById('status_order_code_display').textContent = orderCode;
    
    // Handle available options based on current status
    const acceptedContainer = document.getElementById('status_option_accepted_container');
    const rejectContainer = document.getElementById('status_option_reject_container');
    const handoverContainer = document.getElementById('status_option_handover_container');
    
    const acceptedInput = document.getElementById('status_option_accepted');
    const rejectInput = document.getElementById('status_option_reject');
    const handoverInput = document.getElementById('status_option_handover');
    
    if (currentStatus === 'PENDING') {
        acceptedContainer.style.display = 'flex';
        rejectContainer.style.display = 'flex';
        handoverContainer.style.display = 'none';
        acceptedInput.checked = true;
    } else if (currentStatus === 'ACCEPTED') {
        acceptedContainer.style.display = 'none';
        rejectContainer.style.display = 'none';
        handoverContainer.style.display = 'flex';
        handoverInput.checked = true;
    }
    toggleRejectionReason();
    
    // Show modal
    const modal = document.getElementById('sellerStatusModal');
    const content = document.getElementById('sellerStatusModalContent');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    }, 10);
}

function closeSellerStatusModal() {
    const modal = document.getElementById('sellerStatusModal');
    const content = document.getElementById('sellerStatusModalContent');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}

// Make radio containers clickable
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Reset all borders
        document.querySelectorAll('input[type="radio"]').forEach(r => {
            r.closest('label').classList.remove('border-[#0066FF]', 'bg-blue-50/50');
            r.closest('label').classList.add('border-gray-200');
        });
        
        // Add active style to selected
        if (this.checked) {
            this.closest('label').classList.remove('border-gray-200');
            this.closest('label').classList.add('border-[#0066FF]', 'bg-blue-50/50');
        }
        
        toggleRejectionReason();
    });
});

function toggleRejectionReason() {
    const rejectInput = document.getElementById('status_option_reject');
    const reasonContainer = document.getElementById('rejection_reason_container');
    const reasonTextarea = document.getElementById('cancellation_reason');
    
    if (rejectInput && rejectInput.checked) {
        reasonContainer.classList.remove('hidden');
        reasonTextarea.required = true;
    } else {
        reasonContainer.classList.add('hidden');
        reasonTextarea.required = false;
        reasonTextarea.value = '';
    }
}

// Add ajax submit for sellerStatusForm
document.getElementById('sellerStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../Backend/seller-order-action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#0066FF'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An unexpected error occurred.',
            icon: 'error',
            confirmButtonColor: '#d33'
        });
    });
});

</script>
