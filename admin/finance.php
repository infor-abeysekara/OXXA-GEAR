<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
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

// Category Filter (Requires joining order_items and products)
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
    // An order might have multiple sellers, so we filter orders that have items from this seller
    $where_clauses_orders[] = "o.id IN (SELECT DISTINCT oi.order_id FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.seller_id = ?)";
    $params_orders[] = $seller_filter;
    
    $where_clauses_payouts[] = "sp.seller_id = ?";
    $params_payouts[] = $seller_filter;
}

$where_orders = implode(" AND ", $where_clauses_orders);
$where_payouts = implode(" AND ", $where_clauses_payouts);

// -------------------------------------------------------------
// Summary Cards
// -------------------------------------------------------------
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_rev, COUNT(id) as total_ord FROM orders o WHERE $where_orders");
$stmt->execute($params_orders);
$orders_summary = $stmt->fetch();
$total_revenue = $orders_summary['total_rev'] ?? 0;
$total_orders_count = $orders_summary['total_ord'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(admin_commission) as admin_comm, SUM(CASE WHEN payout_status='pending' THEN seller_earning ELSE 0 END) as pending_payouts FROM seller_payouts sp WHERE $where_payouts");
$stmt->execute($params_payouts);
$payouts_summary = $stmt->fetch();
$admin_commission = $payouts_summary['admin_comm'] ?? 0;
$pending_payouts = $payouts_summary['pending_payouts'] ?? 0;

// -------------------------------------------------------------
// Chart Data Fetching
// -------------------------------------------------------------
// 1. Line Chart: Revenue Over Time
// Simplified to last 7 days for demo if 'all' is selected, otherwise follows date logic
$chart_revenue_labels = [];
$chart_revenue_data = [];
$stmt = $pdo->prepare("SELECT DATE(created_at) as date, SUM(total_amount) as daily_revenue FROM orders o WHERE $where_orders GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30");
$stmt->execute($params_orders);
while ($row = $stmt->fetch()) {
    $chart_revenue_labels[] = $row['date'];
    $chart_revenue_data[] = $row['daily_revenue'];
}

// 2. Doughnut Chart: Income by Category
$chart_cat_labels = [];
$chart_cat_data = [];
$stmt = $pdo->prepare("
    SELECT c.name as cat_name, SUM(sp.admin_commission) as total_comm 
    FROM seller_payouts sp 
    JOIN order_items oi ON sp.order_item_id = oi.id 
    JOIN products p ON oi.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    WHERE $where_payouts 
    GROUP BY c.id
");
$stmt->execute($params_payouts);
while ($row = $stmt->fetch()) {
    $chart_cat_labels[] = $row['cat_name'];
    $chart_cat_data[] = $row['total_comm'];
}

// 3. Bar Chart: Revenue by Payment Method
$chart_pm_labels = [];
$chart_pm_data = [];
$stmt = $pdo->prepare("SELECT payment_method, SUM(total_amount) as total FROM orders o WHERE $where_orders GROUP BY payment_method");
$stmt->execute($params_orders);
while ($row = $stmt->fetch()) {
    $chart_pm_labels[] = $row['payment_method'];
    $chart_pm_data[] = $row['total'];
}

// 4. Stacked Bar Chart (Sales vs Commission vs Payout)
// Using overall totals for simplicity
$chart_stacked_data = [
    'Sales' => (float)$total_revenue,
    'Commission' => (float)$admin_commission,
    'Seller Payouts' => (float)($total_revenue - $admin_commission) // Approx for stacked demo
];

// Fetch lists for filters
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$sellers = $pdo->query("SELECT u.id, sp.business_name FROM users u JOIN seller_profiles sp ON u.id = sp.user_id WHERE u.user_type='seller' ORDER BY sp.business_name")->fetchAll();

// Handle Payout Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_paid'])) {
    $payout_id = $_POST['payout_id'];
    $updateStmt = $pdo->prepare("UPDATE seller_payouts SET payout_status = 'paid', paid_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$payout_id]);
    header("Location: finance.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance & Analytics - Admin Panel</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .stat-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #EFF6FF;
            color: #0066FF;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }
        .stat-label {
            font-size: 14px;
            color: #6B7280;
            font-weight: 500;
        }
        .section-card {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .section-header {
            background: #fff;
            border-bottom: 1px solid #E5E7EB;
            border-left: 4px solid #0066FF;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            padding: 20px;
        }
    </style>
</head>
<body>

    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        <?php include("components/topbar.php"); ?>

        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-0">Finance & Analytics</h2>
                    <p class="text-muted mb-0">Track revenue, commissions, and seller payouts.</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary shadow-sm rounded-pill px-4 me-2" onclick="window.print()">
                        <i class="fas fa-file-pdf me-2"></i>Export PDF
                    </button>
                    <button class="btn btn-primary shadow-sm rounded-pill px-4" onclick="exportCSV()">
                        <i class="fas fa-file-csv me-2"></i>Export CSV
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="section-card p-3 mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-bold">Date Range</label>
                        <select name="date_filter" class="form-select border-0 bg-light" id="dateFilter">
                            <option value="all" <?php echo $date_filter=='all'?'selected':''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter=='today'?'selected':''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter=='week'?'selected':''; ?>>This Week</option>
                            <option value="month" <?php echo $date_filter=='month'?'selected':''; ?>>This Month</option>
                            <option value="year" <?php echo $date_filter=='year'?'selected':''; ?>>This Year</option>
                            <option value="custom" <?php echo $date_filter=='custom'?'selected':''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3 custom-date-row <?php echo $date_filter=='custom'?'':'d-none'; ?>">
                        <div class="d-flex gap-2">
                            <div>
                                <label class="form-label text-muted small fw-bold">Start Date</label>
                                <input type="date" name="start_date" class="form-control border-0 bg-light" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div>
                                <label class="form-label text-muted small fw-bold">End Date</label>
                                <input type="date" name="end_date" class="form-control border-0 bg-light" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-bold">Category</label>
                        <select name="category_id" class="form-select border-0 bg-light">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter==$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">Seller</label>
                        <select name="seller_id" class="form-select border-0 bg-light">
                            <option value="">All Sellers</option>
                            <?php foreach($sellers as $sel): ?>
                                <option value="<?php echo $sel['id']; ?>" <?php echo $seller_filter==$sel['id']?'selected':''; ?>><?php echo htmlspecialchars($sel['business_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="fas fa-filter me-2"></i>Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Total Revenue (GMV)</span>
                            <div class="stat-icon-wrapper" style="background:#f0fdf4; color:#16a34a;">
                                <i class="fas fa-chart-line fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number">Rs. <?php echo number_format($total_revenue, 2); ?></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Admin Commission Income</span>
                            <div class="stat-icon-wrapper">
                                <i class="fas fa-wallet fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number text-primary">Rs. <?php echo number_format($admin_commission, 2); ?></div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Total Orders</span>
                            <div class="stat-icon-wrapper" style="background:#fdf4ff; color:#d946ef;">
                                <i class="fas fa-shopping-cart fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo number_format($total_orders_count); ?></div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="stat-label">Pending Payouts</span>
                            <div class="stat-icon-wrapper" style="background:#fefce8; color:#ca8a04;">
                                <i class="fas fa-clock fs-5"></i>
                            </div>
                        </div>
                        <div class="stat-number text-warning">Rs. <?php echo number_format($pending_payouts, 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-area me-2 text-primary"></i>Revenue Over Time</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Income by Category</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-credit-card me-2 text-primary"></i>Revenue by Payment Method</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-layer-group me-2 text-primary"></i>Sales vs Commission</h5>
                        </div>
                        <div class="chart-container">
                            <canvas id="stackedChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Section -->
            
            <div class="row">
                <!-- Top Sellers -->
                <div class="col-lg-6 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-trophy me-2 text-warning"></i>Top Sellers by Income</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Business Name</th>
                                            <th>Sales Generated</th>
                                            <th class="text-end pe-4">Commission Earned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("
                                            SELECT u.business_name, SUM(sp.selling_price) as total_sales, SUM(sp.admin_commission) as total_comm 
                                            FROM seller_payouts sp 
                                            JOIN seller_profiles u ON sp.seller_id = u.user_id 
                                            WHERE $where_payouts 
                                            GROUP BY sp.seller_id 
                                            ORDER BY total_comm DESC LIMIT 5
                                        ");
                                        $stmt->execute($params_payouts);
                                        while ($row = $stmt->fetch()):
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-medium text-dark"><?php echo htmlspecialchars($row['business_name']); ?></td>
                                            <td>Rs. <?php echo number_format($row['total_sales'], 2); ?></td>
                                            <td class="text-end pe-4 text-primary fw-bold">Rs. <?php echo number_format($row['total_comm'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Payouts Action Table -->
                <div class="col-lg-6 mb-4">
                    <div class="section-card h-100">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-money-bill-wave me-2 text-success"></i>Seller Payouts</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Seller</th>
                                            <th>Earning</th>
                                            <th>Status</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Simplified: Show individual pending payouts. In a real app, these might be grouped by seller and week.
                                        $stmt = $pdo->prepare("
                                            SELECT sp.id, u.business_name, sp.seller_earning, sp.payout_status 
                                            FROM seller_payouts sp 
                                            JOIN seller_profiles u ON sp.seller_id = u.user_id 
                                            WHERE $where_payouts 
                                            ORDER BY sp.created_at DESC LIMIT 5
                                        ");
                                        $stmt->execute($params_payouts);
                                        while ($row = $stmt->fetch()):
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-medium text-dark"><?php echo htmlspecialchars($row['business_name']); ?></td>
                                            <td class="fw-bold">Rs. <?php echo number_format($row['seller_earning'], 2); ?></td>
                                            <td>
                                                <?php if($row['payout_status'] == 'paid'): ?>
                                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning px-2 py-1 rounded-pill">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <?php if($row['payout_status'] == 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="payout_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" name="mark_paid" class="btn btn-sm btn-primary rounded-pill px-3">Mark Paid</button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-light rounded-pill px-3" disabled><i class="fas fa-check"></i></button>
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
            </div>

            <!-- Recent Orders Breakdown -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="section-card">
                        <div class="section-header">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list-alt me-2 text-primary"></i>Recent Orders Commission Breakdown</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="ordersTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Order Code</th>
                                            <th>Item</th>
                                            <th>Seller</th>
                                            <th>Total (Rs)</th>
                                            <th class="text-primary">Commission (Rs)</th>
                                            <th class="text-success">Payout (Rs)</th>
                                            <th class="text-end pe-4">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("
                                            SELECT o.order_code, oi.product_name, u.business_name, sp.selling_price, sp.admin_commission, sp.seller_earning, DATE(o.created_at) as order_date
                                            FROM orders o 
                                            JOIN order_items oi ON o.id = oi.order_id 
                                            JOIN seller_payouts sp ON oi.id = sp.order_item_id
                                            JOIN seller_profiles u ON sp.seller_id = u.user_id 
                                            WHERE $where_orders 
                                            ORDER BY o.created_at DESC LIMIT 15
                                        ");
                                        $stmt->execute($params_orders);
                                        while ($row = $stmt->fetch()):
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($row['order_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['business_name']); ?></span></td>
                                            <td><?php echo number_format($row['selling_price'], 2); ?></td>
                                            <td class="text-primary fw-medium"><?php echo number_format($row['admin_commission'], 2); ?></td>
                                            <td class="text-success fw-medium"><?php echo number_format($row['seller_earning'], 2); ?></td>
                                            <td class="text-end pe-4 text-muted small"><?php echo $row['order_date']; ?></td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Custom Date Fields
        document.getElementById('dateFilter').addEventListener('change', function() {
            if(this.value === 'custom') {
                document.querySelector('.custom-date-row').classList.remove('d-none');
            } else {
                document.querySelector('.custom-date-row').classList.add('d-none');
            }
        });

        // Common Chart Options
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#6B7280';
        
        // 1. Revenue Line Chart
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_revenue_labels); ?>,
                datasets: [{
                    label: 'Revenue (Rs)',
                    data: <?php echo json_encode($chart_revenue_data); ?>,
                    borderColor: '#0066FF',
                    backgroundColor: 'rgba(0, 102, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Category Doughnut Chart
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_cat_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_cat_data); ?>,
                    backgroundColor: ['#0066FF', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#dbeafe'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                },
                cutout: '70%'
            }
        });

        // 3. Payment Method Bar Chart
        new Chart(document.getElementById('paymentChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_pm_labels); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($chart_pm_data); ?>,
                    backgroundColor: '#0066FF',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 4. Stacked Bar Chart
        new Chart(document.getElementById('stackedChart'), {
            type: 'bar',
            data: {
                labels: ['Financial Overview'],
                datasets: [
                    {
                        label: 'Admin Commission',
                        data: [<?php echo $chart_stacked_data['Commission']; ?>],
                        backgroundColor: '#0066FF',
                        borderRadius: 5
                    },
                    {
                        label: 'Seller Payouts',
                        data: [<?php echo $chart_stacked_data['Seller Payouts']; ?>],
                        backgroundColor: '#10b981',
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });

        // CSV Export Function
        function exportCSV() {
            let csv = [];
            let rows = document.querySelectorAll("#ordersTable tr");
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (let j = 0; j < cols.length; j++) 
                    row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
                
                csv.push(row.join(","));        
            }

            let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
            let downloadLink = document.createElement("a");
            downloadLink.download = "OXXA_Finance_Report.csv";
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
    <?php include("../include/footer.php"); ?>
</body>
</html>
