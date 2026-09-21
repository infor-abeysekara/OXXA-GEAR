<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once("../include/connection.php");
require_once("../include/functions.php");

// Strict admin authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized: Admin privileges required']);
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 1;

// Auto-create export_logs table if it does not exist
$create_log_table = "
CREATE TABLE IF NOT EXISTS `export_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(50) NOT NULL,
  `format` VARCHAR(20) NOT NULL DEFAULT 'csv',
  `count` INT NOT NULL DEFAULT 0,
  `filters` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
mysqli_query($conn, $create_log_table);

// Read incoming filter parameters (support both GET and POST)
$action = $_REQUEST['action'] ?? 'data'; // 'count', 'data', or 'download_csv'
$date_range = $_REQUEST['date_range'] ?? 'all'; // 'all', 'last_7', 'this_month', 'custom'
$from_date = $_REQUEST['from_date'] ?? '';
$to_date = $_REQUEST['to_date'] ?? '';
$category_id = $_REQUEST['category_id'] ?? 'All';
$seller_id = $_REQUEST['seller_id'] ?? 'All';
$status = $_REQUEST['status'] ?? 'All';
$search = trim($_REQUEST['search'] ?? '');
$format = strtolower($_REQUEST['format'] ?? 'csv');
$include_images = isset($_REQUEST['include_images']) ? intval($_REQUEST['include_images']) : 1;
$include_cost = isset($_REQUEST['include_cost']) ? intval($_REQUEST['include_cost']) : 1;

// Base query filters
$where_clauses = ["1=1"];

// Date range filtering
if ($date_range === 'last_7') {
    $where_clauses[] = "p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_range === 'this_month') {
    $where_clauses[] = "p.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
} elseif ($date_range === 'custom') {
    if (!empty($from_date)) {
        $where_clauses[] = "DATE(p.created_at) >= '" . mysqli_real_escape_string($conn, $from_date) . "'";
    }
    if (!empty($to_date)) {
        $where_clauses[] = "DATE(p.created_at) <= '" . mysqli_real_escape_string($conn, $to_date) . "'";
    }
}

// Category filter
if ($category_id !== 'All' && $category_id !== '' && is_numeric($category_id)) {
    $where_clauses[] = "p.category_id = " . intval($category_id);
}

// Seller filter
if ($seller_id !== 'All' && $seller_id !== '' && is_numeric($seller_id)) {
    $where_clauses[] = "p.seller_id = " . intval($seller_id);
}

// Status filter
if ($status !== 'All' && $status !== '') {
    $st = strtolower($status);
    if ($st === 'active') {
        $where_clauses[] = "p.status = 'active' AND p.is_approved = 1";
    } elseif ($st === 'pending') {
        $where_clauses[] = "p.is_approved = 0 AND p.status != 'suspended'";
    } elseif ($st === 'rejected' || $st === 'suspended') {
        $where_clauses[] = "p.status = 'suspended'";
    } elseif ($st === 'out_of_stock') {
        $where_clauses[] = "(p.total_qty <= 0 OR NOT EXISTS (SELECT 1 FROM product_colors pc JOIN color_sizes cs ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.qty > 0))";
    }
}

// Search filter
if (!empty($search)) {
    $s_esc = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(p.name LIKE '%$s_esc%' OR p.product_code LIKE '%$s_esc%' OR b.name LIKE '%$s_esc%' OR sp.business_name LIKE '%$s_esc%' OR u.first_name LIKE '%$s_esc%' OR u.last_name LIKE '%$s_esc%')";
}

$where_sql = implode(" AND ", $where_clauses);

// --- ACTION: COUNT (Live counter for export modal) ---
if ($action === 'count') {
    header('Content-Type: application/json');
    $count_sql = "
        SELECT COUNT(DISTINCT p.id) as total_count 
        FROM products p 
        JOIN users u ON p.seller_id = u.id 
        LEFT JOIN seller_profiles sp ON u.id = sp.user_id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $where_sql
    ";
    $count_res = mysqli_query($conn, $count_sql);
    $count_row = mysqli_fetch_assoc($count_res);
    echo json_encode([
        'success' => true,
        'count' => intval($count_row['total_count'] ?? 0)
    ]);
    exit();
}

// --- FETCH FULL PRODUCTS DATA (22 COLUMNS) ---
$main_sql = "
    SELECT p.*,
           u.first_name, u.last_name, u.email as seller_email,
           sp.business_name,
           b.name as brand_name,
           c.name as category_name,
           (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.id) as sales_count,
           (SELECT COALESCE(ROUND(AVG(r.rating), 1), 5.0) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') as avg_rating,
           (SELECT COALESCE(SUM(cs.qty), p.total_qty, 0) FROM product_colors pc JOIN color_sizes cs ON pc.id = cs.color_id WHERE pc.product_id = p.id) as real_stock,
           (SELECT MIN(IF(cs.selling_price > 0, cs.selling_price, p.base_price)) FROM product_colors pc JOIN color_sizes cs ON pc.id = cs.color_id WHERE pc.product_id = p.id) as min_variant_price,
           (SELECT MAX(IF(cs.selling_price > 0, cs.selling_price, p.base_price)) FROM product_colors pc JOIN color_sizes cs ON pc.id = cs.color_id WHERE pc.product_id = p.id) as max_variant_price
    FROM products p
    JOIN users u ON p.seller_id = u.id
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
";

$result = mysqli_query($conn, $main_sql);

// Determine base web URL for images
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$app_base_url = $protocol . $host . '/OXXA%20GEAR/';

$products_data = [];
$total_products_count = 0;
$active_products_count = 0;
$pending_products_count = 0;
$out_of_stock_count = 0;
$total_inventory_value = 0.0;
$ratings_sum = 0.0;
$ratings_count = 0;
$sellers_map = [];

while ($row = mysqli_fetch_assoc($result)) {
    $pid = $row['id'];
    $total_products_count++;

    // Stock
    $stock = intval($row['real_stock'] ?? $row['total_qty']);
    if ($stock <= 0) {
        $out_of_stock_count++;
    }

    // Status normalization
    $raw_status = strtolower($row['status']);
    if ($row['is_approved'] == 0 && $raw_status !== 'suspended') {
        $status_label = 'PENDING';
        $pending_products_count++;
    } elseif ($raw_status === 'suspended') {
        $status_label = 'REJECTED';
    } elseif ($stock <= 0) {
        $status_label = 'OUT_OF_STOCK';
    } else {
        $status_label = 'ACTIVE';
        $active_products_count++;
    }

    // Pricing
    $selling_price = floatval($row['min_variant_price'] > 0 ? $row['min_variant_price'] : $row['base_price']);
    $cost_price = floatval($row['cost_price']);
    $profit = $selling_price - $cost_price;
    $margin_percent = ($selling_price > 0) ? round(($profit / $selling_price) * 100, 1) : 0;

    // Inventory value accumulation
    $total_inventory_value += ($selling_price * $stock);

    // Rating
    $rating = floatval($row['avg_rating'] ?: 5.0);
    $ratings_sum += $rating;
    $ratings_count++;

    // Plain text description (HTML stripped)
    $clean_description = trim(strip_tags(html_entity_decode($row['description'] ?? '')));
    $clean_description = preg_replace('/\s+/', ' ', $clean_description);

    // Images list (Pipe separated)
    $images_pipe = 'Excluded';
    if ($include_images) {
        $image_urls = [];
        // From product_images
        $pi_res = mysqli_query($conn, "SELECT image_path FROM product_images WHERE product_id = $pid ORDER BY is_primary DESC, sort_order ASC");
        while ($pi = mysqli_fetch_assoc($pi_res)) {
            if (!empty($pi['image_path'])) {
                $image_urls[] = $app_base_url . 'assets/uploads/products/' . $pi['image_path'];
            }
        }
        // From color_images
        $ci_res = mysqli_query($conn, "SELECT ci.image_path FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = $pid ORDER BY ci.is_primary DESC, ci.sort_order ASC");
        while ($ci = mysqli_fetch_assoc($ci_res)) {
            if (!empty($ci['image_path'])) {
                $url = $app_base_url . 'assets/uploads/products/' . $ci['image_path'];
                if (!in_array($url, $image_urls)) {
                    $image_urls[] = $url;
                }
            }
        }
        $images_pipe = !empty($image_urls) ? implode(' | ', $image_urls) : 'No Images';
    }

    // Variants breakdown (e.g. "Color: Blue, Sizes: 40(5), 41(10) | Color: White, Sizes: 42(8)")
    $var_parts = [];
    $colors_res = mysqli_query($conn, "SELECT id, color_name FROM product_colors WHERE product_id = $pid");
    if ($colors_res && mysqli_num_rows($colors_res) > 0) {
        while ($col = mysqli_fetch_assoc($colors_res)) {
            $cid = $col['id'];
            $cname = $col['color_name'];
            $sizes_res = mysqli_query($conn, "SELECT size, qty, selling_price FROM color_sizes WHERE color_id = $cid");
            $sizes_str = [];
            while ($sz = mysqli_fetch_assoc($sizes_res)) {
                $sizes_str[] = $sz['size'] . " (Qty:" . $sz['qty'] . ")";
            }
            if (!empty($sizes_str)) {
                $var_parts[] = $cname . " [" . implode(', ', $sizes_str) . "]";
            } else {
                $var_parts[] = $cname;
            }
        }
    }
    $variants_summary = !empty($var_parts) ? implode(' | ', $var_parts) : 'Standard / Single Variant';

    // Seller Info
    $seller_business = !empty($row['business_name']) ? $row['business_name'] : trim($row['first_name'] . ' ' . $row['last_name']);
    $seller_email = $row['seller_email'] ?: '-';

    // Track Seller Breakdown for Sheet 3
    $sid = $row['seller_id'];
    if (!isset($sellers_map[$sid])) {
        $sellers_map[$sid] = [
            'seller_id' => $sid,
            'seller_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'business_name' => $seller_business,
            'seller_email' => $seller_email,
            'products_count' => 0,
            'total_stock' => 0,
            'total_sales_count' => 0,
            'total_sales_value' => 0.0
        ];
    }
    $sellers_map[$sid]['products_count']++;
    $sellers_map[$sid]['total_stock'] += $stock;
    $sales_num = intval($row['sales_count'] ?? 0);
    $sellers_map[$sid]['total_sales_count'] += $sales_num;
    $sellers_map[$sid]['total_sales_value'] += ($sales_num * $selling_price);

    // Build 22-column record
    $item = [
        'product_id' => intval($row['id']),
        'product_name' => $row['name'],
        'sku' => $row['product_code'],
        'brand' => $row['brand_name'] ?: ($row['brand'] ?: 'OXXA'),
        'category' => $row['category_name'] ?: 'General',
        'subcategory' => '-',
        'selling_price' => $selling_price,
        'selling_price_formatted' => 'Rs. ' . number_format($selling_price, 2),
        'cost_price' => $include_cost ? $cost_price : null,
        'cost_price_formatted' => $include_cost ? 'Rs. ' . number_format($cost_price, 2) : 'N/A',
        'profit' => $include_cost ? $profit : null,
        'profit_formatted' => $include_cost ? 'Rs. ' . number_format($profit, 2) : 'N/A',
        'margin_percent' => $include_cost ? $margin_percent . '%' : 'N/A',
        'profit_margin' => $include_cost ? 'Rs. ' . number_format($profit, 2) . ' (' . $margin_percent . '%)' : 'N/A',
        'stock_qty' => $stock,
        'status' => $status_label,
        'seller_business' => $seller_business,
        'seller_email' => $seller_email,
        'created_at' => date('Y-m-d H:i:s', strtotime($row['created_at'])),
        'updated_at' => date('Y-m-d H:i:s', strtotime($row['updated_at'])),
        'views' => intval($row['views'] ?? 0),
        'sales_count' => intval($row['sales_count'] ?? 0),
        'rating' => floatval(number_format($rating, 1, '.', '')),
        'description' => $clean_description,
        'images' => $images_pipe,
        'variants' => $variants_summary,
        'weight' => !empty($row['weight']) ? $row['weight'] : '0.8kg',
        'is_featured' => !empty($row['is_hot_deal']) ? 'YES' : 'NO',
        'is_hot_deal' => !empty($row['is_hot_deal']) ? 'YES' : 'NO',
        'discount_percent' => !empty($row['discount_percent']) ? $row['discount_percent'] . '%' : '0%'
    ];

    $products_data[] = $item;
}

// Average rating
$avg_rating_overall = $ratings_count > 0 ? round($ratings_sum / $ratings_count, 1) : 5.0;

// Log the export event
$filter_summary = "Range:$date_range; Cat:$category_id; Seller:$seller_id; Status:$status; IncCost:$include_cost; IncImg:$include_images";
$log_stmt = $conn->prepare("INSERT INTO export_logs (admin_id, type, format, count, filters, created_at) VALUES (?, 'products_full', ?, ?, ?, NOW())");
$log_stmt->bind_param("isis", $admin_id, $format, $total_products_count, $filter_summary);
$log_stmt->execute();

// --- ACTION: DIRECT CSV STREAMING ---
if ($action === 'download_csv' || ($action === 'download' && $format === 'csv')) {
    $filename = "oxxa-full-products-" . date('Y-m-d-His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Sinhala / Unicode characters
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // CSV Header row (22 columns)
    $headers = [
        'Product ID',
        'Product Name',
        'SKU',
        'Brand',
        'Category',
        'Subcategory',
        'Selling Price (Rs.)',
        'Cost Price (Rs.)',
        'Profit + Margin',
        'Stock Qty',
        'Status',
        'Seller Business',
        'Seller Email',
        'Created At',
        'Updated At',
        'Views',
        'Sales Count',
        'Rating',
        'Description',
        'Images URL',
        'Variants',
        'Weight',
        'Featured / Hot Deal',
        'Discount %'
    ];
    fputcsv($out, $headers);

    foreach ($products_data as $p) {
        fputcsv($out, [
            $p['product_id'],
            $p['product_name'],
            $p['sku'],
            $p['brand'],
            $p['category'],
            $p['subcategory'],
            $p['selling_price_formatted'],
            $p['cost_price_formatted'],
            $p['profit_margin'],
            $p['stock_qty'],
            $p['status'],
            $p['seller_business'],
            $p['seller_email'],
            ' ' . date('Y-m-d H:i', strtotime($p['created_at'])),
            ' ' . date('Y-m-d H:i', strtotime($p['updated_at'])),
            $p['views'],
            $p['sales_count'],
            $p['rating'],
            $p['description'],
            $p['images'],
            $p['variants'],
            $p['weight'],
            $p['is_hot_deal'],
            $p['discount_percent']
        ]);
    }

    fclose($out);
    exit();
}

// --- ACTION: FULL JSON RESPONSE (For 3-Sheet Excel, PDF, and interactive UI) ---
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'total_count' => $total_products_count,
    'summary' => [
        'total_products' => $total_products_count,
        'active_products' => $active_products_count,
        'pending_products' => $pending_products_count,
        'out_of_stock_products' => $out_of_stock_count,
        'total_inventory_value' => number_format($total_inventory_value, 2, '.', ','),
        'avg_rating' => number_format($avg_rating_overall, 1, '.', '')
    ],
    'sellers_breakdown' => array_values($sellers_map),
    'products' => $products_data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();
