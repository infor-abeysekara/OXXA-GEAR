<?php
session_start();
include_once("../include/connection.php");
include_once("../include/functions.php");

header('Content-Type: application/json');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$seller_id = $_SESSION['userid'];

try {
    // Get seller analytics
    $analytics = getSellerAnalytics($conn, $seller_id);
    
    // Get recent orders
    $recent_orders = getSellerOrders($conn, $seller_id, 10);
    
    // Get monthly sales data for chart (last 6 months)
    $monthly_sales = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        
        $salesQuery = "SELECT 
                          COUNT(*) as orders,
                          COALESCE(SUM(price * qty), 0) as revenue
                       FROM ordertable 
                       WHERE seller_id = ? 
                       AND DATE_FORMAT(orderdate, '%Y-%m') = ?
                       AND status = 'confirmed'";
        $stmt = $conn->prepare($salesQuery);
        $stmt->bind_param("ss", $seller_id, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        $monthly_sales[] = [
            'month' => $monthName,
            'orders' => $data['orders'],
            'revenue' => $data['revenue'],
            'seller_earnings' => $data['revenue'] * 0.9
        ];
    }
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics,
        'recent_orders' => $recent_orders,
        'monthly_sales' => $monthly_sales
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>