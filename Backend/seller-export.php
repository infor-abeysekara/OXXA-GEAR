<?php
session_start();
require '../include/connection.php';
require '../include/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || $_SESSION['user_type'] !== 'seller') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$seller_id = $_SESSION['userid'];
$type = $_GET['type'] ?? '';

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Fetch Business Name
$business_name = "Store";
$stmt = $pdo->prepare("SELECT business_name FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$seller_id]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $business_name = $row['business_name'] ?: 'Store';
}
// Clean business name for filename
$business_name_clean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($business_name)));

$response = [
    'business_name_clean' => $business_name_clean,
    'data' => []
];

switch ($type) {
    case 'products':
        $params = [$seller_id];
        $sql = "SELECT p.name as 'Product Name', p.product_code as 'Code', c.name as 'Category', 
                       p.base_price as 'Selling Price', p.cost_price as 'Cost Price', 
                       p.total_qty as 'Stock', p.status as 'Status', DATE(p.created_at) as 'Created At'
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.seller_id = ?";
                
        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        if (!empty($search)) {
            $sql .= " AND (p.pname LIKE ? OR p.product_code LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($status)) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($data as &$row) {
            $row['Selling Price'] = 'Rs.' . number_format((float)$row['Selling Price'], 2);
            $row['Cost Price'] = 'Rs.' . number_format((float)$row['Cost Price'], 2);
        }
        $response['data'] = $data;
        break;

    case 'withdrawals':
        $params = [$seller_id];
        $sql = "SELECT DATE(created_at) as 'Date', id as 'Withdrawal ID', amount as 'Amount', bank_name as 'Bank', 
                       account_number as 'Account No', status as 'Status', 
                       0 as 'Fee', amount as 'Net Received', DATE(processed_at) as 'Processed Date'
                FROM withdrawal_requests 
                WHERE seller_id = ?";

        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($data as &$row) {
            $row['Amount'] = 'Rs.' . number_format((float)$row['Amount'], 2);
            $row['Fee'] = 'Rs.' . number_format(0, 2); // Modify if fees are added later
            $row['Net Received'] = $row['Amount']; // Amount is the Net Received for now
            $row['Status'] = strtoupper($row['Status']);
            
            // Masking Bank Account
            $acc = $row['Account No'];
            if (strlen($acc) > 4) {
                $row['Account No'] = substr($acc, 0, 4) . '****' . substr($acc, -2);
            }
            
            $row['Processed Date'] = $row['Processed Date'] ?: '-';
        }
        $response['data'] = $data;
        break;

    case 'orders':
        $params = [$seller_id];
        $sql = "SELECT o.order_code as 'Order ID', DATE(o.created_at) as 'Date', 
                       COALESCE(o.shipping_name, CONCAT(o.first_name, ' ', o.last_name)) as 'Customer',
                       COALESCE(ua.phone1, u.phone) as 'Customer Phone',
                       oi.product_name as 'Product', oi.size as 'Size', oi.quantity as 'Qty', 
                       oi.selling_price as 'Total', oi.seller_earning as 'Your Earning', 
                       oi.settlement_status as 'Status', o.payment_method as 'Payment Method', 
                       CONCAT(o.address_line1, ', ', o.city) as 'Delivery Address',
                       DATE_ADD(o.created_at, INTERVAL 7 DAY) as 'Return Window Ends'
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
                WHERE p.seller_id = ?";
                
        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(o.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        if (!empty($status)) {
            $sql .= " AND oi.settlement_status = ?"; // Or o.status based on requirement
            $params[] = $status;
        }

        $sql .= " ORDER BY o.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($data as &$row) {
            $row['Total'] = 'Rs.' . number_format((float)$row['Total'], 2);
            $row['Your Earning'] = 'Rs.' . number_format((float)$row['Your Earning'], 2);
            $row['Status'] = ucfirst($row['Status']);
            $row['Payment Method'] = strtoupper($row['Payment Method']);
            
            // Mask Customer Phone (e.g., 077****567)
            $phone = $row['Customer Phone'];
            if ($phone && strlen($phone) >= 10) {
                $row['Customer Phone'] = substr($phone, 0, 3) . '****' . substr($phone, -3);
            } else if ($phone) {
                $row['Customer Phone'] = '***';
            } else {
                $row['Customer Phone'] = '-';
            }
            
            // Return Window Ends only if delivered, else '-' (Optional logic, let's keep it simple as Date for now)
            $row['Return Window Ends'] = DATE("Y-m-d", strtotime($row['Return Window Ends']));
        }
        $response['data'] = $data;
        break;

    case 'reviews':
        $params = [$seller_id];
        $sql = "SELECT DATE(r.created_at) as 'Date', p.pname as 'Product', 
                       CONCAT(u.first_name, ' ', u.last_name) as 'Customer', 
                       r.rating as 'Rating', r.review_text as 'Review Text', 
                       r.seller_reply as 'Reply', DATE(r.updated_at) as 'Reply Date'
                FROM reviews r
                JOIN products p ON r.product_id = p.id
                JOIN users u ON r.user_id = u.id
                WHERE p.seller_id = ?";
                
        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(r.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }

        if (!empty($status)) {
            $sql .= " AND r.reply_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY r.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($data as &$row) {
            $row['Reply'] = $row['Reply'] ?: '-';
            $row['Reply Date'] = ($row['Reply'] !== '-') ? $row['Reply Date'] : '-';
        }
        $response['data'] = $data;
        break;

    default:
        echo json_encode(['error' => 'Invalid type']);
        exit;
}

echo json_encode($response);
?>
