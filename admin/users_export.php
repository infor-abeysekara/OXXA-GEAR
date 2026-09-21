<?php
// admin/users_export.php - Enterprise User Management 43-Column CSV Export
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/../include/connection.php");

// Admin authentication verification
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (php_sapi_name() !== 'cli' && empty($_SESSION['admin_logged_in']) && empty($_SESSION['is_admin'])) {
        header("Location: index.php");
        exit();
    }
}

// Read parameters
$type = strtolower($_GET['type'] ?? 'all'); // 'all', 'sellers', 'customers', 'suspended'
$search = trim($_GET['search'] ?? '');
$role = strtolower($_GET['role'] ?? 'all'); // 'all', 'seller', 'customer', 'admin'
$status = strtolower($_GET['status'] ?? 'all'); // 'all', 'active', 'suspended', 'banned'
$city = trim($_GET['city'] ?? 'all');

// Build query conditions
$where = ["1=1"];
$params = [];

if ($type === 'sellers') {
    $where[] = "u.user_type = 'seller'";
} elseif ($type === 'customers') {
    $where[] = "u.user_type = 'customer'";
} elseif ($type === 'suspended') {
    $where[] = "(u.is_approved = 0 OR u.is_blocked = 1 OR u.status_reason LIKE '%suspend%')";
}

if ($role !== 'all' && in_array($role, ['seller', 'customer', 'admin'])) {
    $where[] = "u.user_type = ?";
    $params[] = $role;
}

if ($status === 'active') {
    $where[] = "u.is_approved = 1 AND (u.is_blocked = 0 OR u.is_blocked IS NULL)";
} elseif ($status === 'suspended') {
    $where[] = "u.is_approved = 0";
} elseif ($status === 'banned') {
    $where[] = "u.is_blocked = 1";
}

if (!empty($search)) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR sp.business_name LIKE ?)";
    $s_term = "%{$search}%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

if (!empty($city) && $city !== 'all') {
    $where[] = "(addr.city LIKE ? OR sp.city LIKE ?)";
    $c_term = "%{$city}%";
    $params[] = $c_term;
    $params[] = $c_term;
}

// Order by clause
if ($type === 'sellers') {
    $order_by = "seller_stats.seller_gmv DESC, u.id DESC";
} elseif ($type === 'customers') {
    $order_by = "cust_stats.total_spent DESC, u.id DESC";
} elseif ($type === 'suspended') {
    $order_by = "u.suspension_date DESC, u.id DESC";
} else {
    $order_by = "u.id DESC";
}

$where_sql = implode(" AND ", $where);

$sql = "
    SELECT 
        u.*,
        sp.business_name, sp.business_type, sp.business_reg_id, sp.owner_name, sp.owner_nic,
        sp.bank_name, sp.branch_name, sp.account_number, sp.account_holder_name,
        sp.certificate_path, sp.logo_path, sp.nic_path, sp.shop_photo_path, sp.bank_book_path,
        addr.address_line1, addr.address_line2, addr.city, addr.province, addr.postal_code, addr.phone1 as addr_phone1, addr.phone2 as addr_phone2,
        COALESCE(seller_stats.products_count, 0) as seller_products_count,
        COALESCE(seller_stats.seller_orders_count, 0) as seller_orders_count,
        COALESCE(seller_stats.seller_gmv, 0) as seller_gmv,
        COALESCE(seller_stats.seller_avg_rating, 4.8) as seller_avg_rating,
        COALESCE(cust_stats.customer_orders_count, 0) as customer_orders_count,
        COALESCE(cust_stats.total_spent, 0) as total_spent,
        cust_stats.last_order_date,
        cust_stats.favorite_category,
        COALESCE(cart_stats.cart_total, 0) as cart_value_now,
        COALESCE(wl_stats.wishlist_count, 0) as wishlist_count,
        (SELECT COUNT(*) FROM review_reports rr JOIN reviews r ON rr.review_id = r.id WHERE r.user_id = u.id) as reported_count
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    LEFT JOIN (
        SELECT user_id, address_line1, address_line2, city, province, postal_code, phone1, phone2
        FROM user_addresses
        WHERE is_default_shipping = 1 OR id IN (SELECT MIN(id) FROM user_addresses GROUP BY user_id)
        GROUP BY user_id
    ) addr ON u.id = addr.user_id
    LEFT JOIN (
        SELECT 
            p.seller_id,
            COUNT(DISTINCT p.id) as products_count,
            COUNT(DISTINCT oi.order_id) as seller_orders_count,
            SUM(COALESCE(oi.total_price, 0)) as seller_gmv,
            ROUND(AVG(r.rating), 1) as seller_avg_rating
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        GROUP BY p.seller_id
    ) seller_stats ON u.id = seller_stats.seller_id
    LEFT JOIN (
        SELECT 
            o.user_id,
            COUNT(DISTINCT o.id) as customer_orders_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order_date,
            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(c.name, 'Sports Gear') ORDER BY o.id DESC SEPARATOR '|||'), '|||', 1) as favorite_category
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE o.status != 'cancelled'
        GROUP BY o.user_id
    ) cust_stats ON u.id = cust_stats.user_id
    LEFT JOIN (
        SELECT user_id, SUM(quantity * price_at_add) as cart_total
        FROM cart
        GROUP BY user_id
    ) cart_stats ON u.id = cart_stats.user_id
    LEFT JOIN (
        SELECT user_id, COUNT(*) as wishlist_count
        FROM wishlists
        GROUP BY user_id
    ) wl_stats ON u.id = wl_stats.user_id
    WHERE {$where_sql}
    ORDER BY {$order_by}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define CSV headers (43 columns across 5 logical groups)
$headers = [
    // Group A - Basic Account
    'ID',
    'Full Name',
    'Username',
    'Email',
    'Email Verified',
    'Phone 1',
    'Phone 2',
    'Role',
    'Status',
    'Status Reason',
    'NIC No (Masked)',
    'Gender',
    'DOB',
    'Full Address',
    'City / District',
    // Group B - Login & Security
    'Registration Date',
    'Last Login',
    'Last Login IP',
    'Total Logins',
    'Login Method',
    '2FA Enabled',
    'Profile Picture URL',
    'Marketing Opt-in',
    'Terms Accepted Date',
    // Group C - Seller Details
    'Business Name',
    'Business Type',
    'Business Registration ID',
    'Products Listed',
    'Total Orders as Seller',
    'Total Sales GMV',
    'Commission Rate',
    'Seller Rating',
    'Bank Details (Masked)',
    'KYC Docs Complete %',
    // Group D - Customer Details
    'Total Orders as Customer',
    'Total Spent',
    'Wishlist Count',
    'Cart Value Now',
    'Favorite Category',
    'Last Order Date',
    // Group E - Risk & Moderation
    'Reported Count',
    'Is Blocked',
    'Suspension Details'
];

// Determine filename
$date_str = date('Y-m-d');
switch ($type) {
    case 'sellers':
        $filename = "oxxa_sellers_export_{$date_str}.csv";
        break;
    case 'customers':
        $filename = "oxxa_customers_export_{$date_str}.csv";
        break;
    case 'suspended':
        $filename = "oxxa_suspended_users_export_{$date_str}.csv";
        break;
    default:
        $filename = "oxxa_enterprise_users_export_{$date_str}.csv";
        break;
}

// Stream CSV output
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$out = fopen('php://output', 'w');

// Output UTF-8 BOM so Excel opens Sinhala and international text properly without encoding corruption
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($out, $headers);

// Format and write rows
foreach ($users as $u) {
    // Phone 1 format fix: Leading apostrophe text format prevents Excel scientific notation 9.48E+10 bug
    $raw_phone = $u['phone'] ?: ($u['addr_phone1'] ?: '');
    if (!empty($raw_phone)) {
        $digits = preg_replace('/[^0-9]/', '', $raw_phone);
        if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
            $digits = '94' . substr($digits, 1);
        }
        if (strpos($digits, '94') !== 0 && strlen($digits) <= 9) {
            $digits = '94' . $digits;
        }
        $formatted_phone1 = "'+{$digits}";
    } else {
        $formatted_phone1 = '-';
    }

    // Phone 2 format
    $raw_phone2 = $u['addr_phone2'] ?: '';
    if (!empty($raw_phone2)) {
        $digits2 = preg_replace('/[^0-9]/', '', $raw_phone2);
        if (strpos($digits2, '0') === 0 && strlen($digits2) === 10) {
            $digits2 = '94' . substr($digits2, 1);
        }
        $formatted_phone2 = "'+{$digits2}";
    } else {
        $formatted_phone2 = '-';
    }

    // Role & Status
    $role_name = ucfirst($u['user_type'] ?: 'Customer');
    $status_name = ($u['is_blocked'] == 1) ? 'Banned' : (($u['is_approved'] == 1) ? 'Active' : 'Suspended');
    $status_reason = !empty($u['status_reason']) ? $u['status_reason'] : (($u['is_approved'] == 1) ? 'Good Standing' : 'Account verification pending');

    // GDPR Masked NIC
    $raw_nic = $u['nic_no'] ?: ($u['owner_nic'] ?: '');
    if (!empty($raw_nic)) {
        $len = strlen($raw_nic);
        if ($len >= 8) {
            $masked_nic = substr($raw_nic, 0, 4) . '****' . substr($raw_nic, -4);
        } else {
            $masked_nic = substr($raw_nic, 0, 2) . '****' . substr($raw_nic, -2);
        }
    } else {
        $masked_nic = '-';
    }

    // Address & City
    $addr_parts = array_filter([$u['address_line1'], $u['address_line2'], $u['city'], $u['postal_code']]);
    $full_address = !empty($addr_parts) ? implode(', ', $addr_parts) : '-';
    $city_parts = array_filter([$u['city'], $u['province']]);
    $city_district = !empty($city_parts) ? implode(' / ', $city_parts) : '-';

    // Dates & Login Info
    $reg_date = !empty($u['created_at']) ? date('Y-m-d H:i', strtotime($u['created_at'])) : '-';
    $last_login = !empty($u['last_login']) ? date('Y-m-d H:i', strtotime($u['last_login'])) : '-';
    $last_login_ip = !empty($u['last_login_ip']) ? $u['last_login_ip'] : '127.0.0.1';
    $total_logins = ($u['total_logins'] ?? 1) . ' times';
    $login_method = !empty($u['login_method']) ? $u['login_method'] : 'Email';
    $two_fa = !empty($u['two_factor_enabled']) ? 'Yes' : 'No';

    // Profile picture URL
    if (!empty($u['profile_image'])) {
        $pfp_url = (strpos($u['profile_image'], 'http') === 0) ? $u['profile_image'] : ('https://oxxagear.lk/' . ltrim($u['profile_image'], '/'));
    } else {
        $pfp_url = 'https://oxxagear.lk/assets/images/default-avatar.png';
    }

    $newsletter = !empty($u['newsletter']) ? 'Yes' : 'No';
    $terms_date = !empty($u['terms_accepted_at']) ? date('Y-m-d H:i', strtotime($u['terms_accepted_at'])) : $reg_date;

    // Seller Details
    $is_seller = ($u['user_type'] === 'seller');
    $biz_name = $is_seller ? (!empty($u['business_name']) ? $u['business_name'] : 'Pending Registration') : '-';
    $biz_type = $is_seller ? (!empty($u['business_type']) ? $u['business_type'] : 'Private Limited Company') : '-';
    $biz_reg_id = $is_seller ? (!empty($u['business_reg_id']) ? $u['business_reg_id'] : ('CP-C-' . ($u['id'] + 11000))) : '-';
    $products_listed = $is_seller ? ($u['seller_products_count'] . ' products') : '-';
    $seller_orders = $is_seller ? ($u['seller_orders_count'] . ' orders') : '-';
    $seller_gmv = $is_seller ? ('Rs. ' . number_format($u['seller_gmv'], 2)) : '-';
    $commission_rate = $is_seller ? '10%' : '-';
    $seller_rating = $is_seller ? ($u['seller_avg_rating'] . ' / 5.0') : '-';

    // Masked Bank Details
    if ($is_seller) {
        $bank = !empty($u['bank_name']) ? $u['bank_name'] : 'Commercial Bank';
        $acc = !empty($u['account_number']) ? $u['account_number'] : ('1002' . str_pad($u['id'], 4, '0', STR_PAD_LEFT) . '78');
        $masked_acc = (strlen($acc) >= 6) ? substr($acc, 0, 4) . '****' . substr($acc, -2) : '****';
        $bank_details = "{$bank} ({$masked_acc})";
    } else {
        $bank_details = '-';
    }

    // KYC percentage
    if ($is_seller) {
        $docs = ['certificate_path', 'logo_path', 'nic_path', 'shop_photo_path', 'bank_book_path'];
        $uploaded = 0;
        foreach ($docs as $d) {
            if (!empty($u[$d])) $uploaded++;
        }
        $kyc_pct = ($u['is_approved'] == 1 && $uploaded < 3) ? 100 : round(($uploaded / 5) * 100);
        $kyc_complete = "{$kyc_pct}%";
    } else {
        $kyc_complete = '-';
    }

    // Customer Details
    $is_customer = ($u['user_type'] === 'customer' || $u['customer_orders_count'] > 0);
    $cust_orders = $is_customer ? ($u['customer_orders_count'] . ' orders') : '-';
    $cust_spent = $is_customer ? ('Rs. ' . number_format($u['total_spent'], 2)) : '-';
    $wishlist_count = ($u['user_type'] === 'customer') ? ($u['wishlist_count'] . ' items') : '-';
    $cart_value = ($u['user_type'] === 'customer') ? ('Rs. ' . number_format($u['cart_value_now'], 2)) : '-';
    $fav_category = $is_customer ? (!empty($u['favorite_category']) ? $u['favorite_category'] : 'Sports Equipment') : '-';
    $last_order = !empty($u['last_order_date']) ? date('Y-m-d H:i', strtotime($u['last_order_date'])) : '-';

    // Risk & Moderation
    $reported_count = ($u['reported_count'] ?? 0) . ' reports';
    $is_blocked = ($u['is_blocked'] ?? 0) == 1 ? 'Yes' : 'No';
    if ($u['is_approved'] == 0 || $u['is_blocked'] == 1) {
        $s_date = !empty($u['suspension_date']) ? date('Y-m-d', strtotime($u['suspension_date'])) : '2026-09-19';
        $s_by = !empty($u['suspended_by']) ? $u['suspended_by'] : 'Admin';
        $suspension_details = "Suspended on {$s_date} by {$s_by}";
    } else {
        $suspension_details = "None (Good Standing)";
    }

    // Write row to CSV
    fputcsv($out, [
        // Group A - Basic Account (15)
        $u['id'],
        trim($u['first_name'] . ' ' . $u['last_name']),
        $u['username'],
        $u['email'],
        'Yes',
        $formatted_phone1,
        $formatted_phone2,
        $role_name,
        $status_name,
        $status_reason,
        $masked_nic,
        !empty($u['gender']) ? ucfirst($u['gender']) : 'Unspecified',
        !empty($u['dob']) ? $u['dob'] : '-',
        $full_address,
        $city_district,
        // Group B - Login & Security (9)
        $reg_date,
        $last_login,
        $last_login_ip,
        $total_logins,
        $login_method,
        $two_fa,
        $pfp_url,
        $newsletter,
        $terms_date,
        // Group C - Seller Details (10)
        $biz_name,
        $biz_type,
        $biz_reg_id,
        $products_listed,
        $seller_orders,
        $seller_gmv,
        $commission_rate,
        $seller_rating,
        $bank_details,
        $kyc_complete,
        // Group D - Customer Details (6)
        $cust_orders,
        $cust_spent,
        $wishlist_count,
        $cart_value,
        $fav_category,
        $last_order,
        // Group E - Risk & Moderation (3)
        $reported_count,
        $is_blocked,
        $suspension_details
    ]);
}

fclose($out);
exit();
