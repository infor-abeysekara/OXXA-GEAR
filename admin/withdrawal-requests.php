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
$search_filter = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$seller_filter = $_GET['seller_id'] ?? 'all';

$where_clauses = ["1=1"];
$params = [];

if ($status_filter != 'all') {
    $where_clauses[] = "w.status = ?";
    $params[] = $status_filter;
}
if ($seller_filter != 'all') {
    $where_clauses[] = "w.seller_id = ?";
    $params[] = $seller_filter;
}
if (!empty($search_filter)) {
    $where_clauses[] = "(w.withdrawal_code LIKE ? OR w.seller_name LIKE ? OR w.business_name LIKE ? OR w.bank_name LIKE ?)";
    $search_term = "%{$search_filter}%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch requests
$stmt = $pdo->prepare("
    SELECT w.*, u.email as seller_email 
    FROM withdrawals w
    LEFT JOIN users u ON w.seller_id = u.id
    WHERE $where_sql 
    ORDER BY w.requested_at DESC
");
$stmt->execute($params);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counters
$countStmt = $pdo->query("
    SELECT 
        COUNT(id) as total_c,
        SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END) as pending_c,
        SUM(CASE WHEN status='PENDING' THEN amount ELSE 0 END) as pending_amt,
        SUM(CASE WHEN status='APPROVED' THEN 1 ELSE 0 END) as approved_c,
        SUM(CASE WHEN status='APPROVED' THEN amount ELSE 0 END) as approved_amt,
        SUM(CASE WHEN status='PROCESSING' THEN 1 ELSE 0 END) as processing_c,
        SUM(CASE WHEN status='COMPLETED' THEN 1 ELSE 0 END) as completed_c,
        SUM(CASE WHEN status='COMPLETED' AND MONTH(completed_at) = MONTH(CURRENT_DATE()) THEN amount ELSE 0 END) as completed_amt_month,
        SUM(CASE WHEN status='COMPLETED' THEN amount ELSE 0 END) as total_payout,
        SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END) as rejected_c
    FROM withdrawals
");
$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$sellersStmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as owner_name FROM users WHERE user_type='Seller'");
$sellers = $sellersStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Management | OXXA GEAR Enterprise</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <!-- Tailwind CSS (assuming admin is moving towards Tailwind as requested for Enterprise UI) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap');
        body { background-color: #F8FAFC; font-family: 'Inter', sans-serif; color: #0F172A; }
        .tabular-nums { font-family: 'JetBrains Mono', monospace; }
        
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        /* Drawer Styles */
        .drawer-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);
            z-index: 1040; opacity: 0; visibility: hidden; transition: 0.3s ease;
        }
        .drawer-overlay.active { opacity: 1; visibility: visible; }
        
        .drawer {
            position: fixed; top: 0; right: -650px; width: 650px; height: 100vh;
            background: #fff; z-index: 1050; box-shadow: -10px 0 25px -5px rgba(0,0,0,0.1);
            transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column;
        }
        .drawer.active { right: 0; }
        @media (max-width: 650px) { .drawer { width: 100%; right: -100%; } }
        
        .drawer-body { flex-grow: 1; overflow-y: auto; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
        
        /* Status Dots */
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
        .status-dot.pending { background: #EAB308; box-shadow: 0 0 0 3px rgba(234,179,8,0.2); animation: pulse 2s infinite; }
        .status-dot.approved { background: #3B82F6; }
        .status-dot.processing { background: #A855F7; }
        .status-dot.completed { background: #22C55E; }
        .status-dot.rejected { background: #EF4444; }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(234,179,8,0.4); }
            70% { box-shadow: 0 0 0 6px rgba(234,179,8,0); }
            100% { box-shadow: 0 0 0 0 rgba(234,179,8,0); }
        }
    </style>
</head>
<body>

    <?php 
    // Fallback if sidebar doesn't load cleanly with Tailwind
    $sidebarPath = "components/sidebar.php";
    if(file_exists($sidebarPath)) { include($sidebarPath); } 
    ?>

    <div class="main-content">
        <?php 
        $topbarPath = "components/topbar.php";
        if(file_exists($topbarPath)) { include($topbarPath); } 
        ?>

        <!-- Standardized Page Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6 pt-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-black shadow-sm shrink-0">
                    <i class="fas fa-university"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 m-0">
                        Withdrawal Management
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 uppercase tracking-widest">Enterprise</span>
                    </h1>
                    <p class="text-xs font-medium text-slate-500 m-0 mt-1">Review, approve, and track seller payouts securely.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-slate-50 transition">
                    <i class="fas fa-file-export mr-2 text-slate-400"></i> Export PDF
                </button>
                <button class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-slate-50 transition">
                    <i class="fas fa-download mr-2 text-slate-400"></i> CSV
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm cursor-pointer hover:shadow-md transition" onclick="window.location.href='?status=all'">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1 flex justify-between">
                    Total Requests <i class="fas fa-list text-slate-300"></i>
                </div>
                <div class="text-2xl font-black text-slate-800 tabular-nums"><?= $counts['total_c'] ?? 0 ?></div>
            </div>
            
            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm cursor-pointer hover:border-yellow-300 transition relative overflow-hidden" onclick="window.location.href='?status=PENDING'">
                <div class="absolute right-0 top-0 w-1 h-full bg-yellow-400"></div>
                <div class="text-[10px] font-bold text-yellow-600 uppercase tracking-widest mb-1 flex justify-between">
                    Pending <span class="bg-yellow-100 text-yellow-800 px-1.5 rounded-sm">Action</span>
                </div>
                <div class="text-2xl font-black text-slate-800 tabular-nums flex items-end gap-2">
                    <?= $counts['pending_c'] ?? 0 ?>
                    <span class="text-sm text-slate-500 font-semibold mb-1">Rs.<?= number_format($counts['pending_amt'] ?? 0) ?></span>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm cursor-pointer hover:border-blue-300 transition" onclick="window.location.href='?status=APPROVED'">
                <div class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-1 flex justify-between">
                    Approved <i class="fas fa-check-circle text-blue-200"></i>
                </div>
                <div class="text-2xl font-black text-slate-800 tabular-nums flex items-end gap-2">
                    <?= $counts['approved_c'] ?? 0 ?>
                    <span class="text-sm text-slate-500 font-semibold mb-1">Rs.<?= number_format($counts['approved_amt'] ?? 0) ?></span>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm cursor-pointer hover:border-green-300 transition" onclick="window.location.href='?status=COMPLETED'">
                <div class="text-[10px] font-bold text-green-600 uppercase tracking-widest mb-1 flex justify-between">
                    Completed (This Month) <i class="fas fa-money-check-alt text-green-200"></i>
                </div>
                <div class="text-2xl font-black text-slate-800 tabular-nums flex items-end gap-2">
                    <?= $counts['completed_c'] ?? 0 ?>
                    <span class="text-sm text-slate-500 font-semibold mb-1">Rs.<?= number_format($counts['completed_amt_month'] ?? 0) ?></span>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-3 rounded-t-xl border border-slate-200 border-b-0 shadow-sm">
            <form method="GET" action="" class="flex flex-col gap-3">
                <div class="flex flex-wrap md:flex-nowrap gap-3">
                    <div class="flex-grow relative">
                        <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-sm"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_filter) ?>" placeholder="Search ID, Seller, Business, Bank..." class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-medium text-slate-700">
                    </div>
                    
                    <select name="status" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 focus:outline-none w-40">
                        <option value="all">Status: All</option>
                        <option value="PENDING" <?= $status_filter=='PENDING'?'selected':'' ?>>Pending</option>
                        <option value="APPROVED" <?= $status_filter=='APPROVED'?'selected':'' ?>>Approved</option>
                        <option value="PROCESSING" <?= $status_filter=='PROCESSING'?'selected':'' ?>>Processing</option>
                        <option value="COMPLETED" <?= $status_filter=='COMPLETED'?'selected':'' ?>>Completed</option>
                        <option value="REJECTED" <?= $status_filter=='REJECTED'?'selected':'' ?>>Rejected</option>
                    </select>
                    
                    <select name="seller_id" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 focus:outline-none w-48">
                        <option value="all">Seller: All Sellers</option>
                        <?php foreach($sellers as $sel): ?>
                            <option value="<?= $sel['id'] ?>" <?= $seller_filter==$sel['id']?'selected':'' ?>><?= htmlspecialchars($sel['owner_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="bg-slate-800 text-white px-5 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-slate-900 transition">
                        Apply
                    </button>
                    
                    <?php if($search_filter || $status_filter != 'all' || $seller_filter != 'all'): ?>
                    <a href="withdrawal-requests.php" class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-bold hover:bg-slate-50 transition flex items-center">
                        Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white border border-slate-200 rounded-b-xl shadow-sm overflow-hidden mb-8">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-200">
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider w-10 text-center"><input type="checkbox" class="rounded border-slate-300"></th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Withdrawal Code</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Seller / Business</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">Amount</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">Fee</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">Net Payout</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Bank</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="p-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($withdrawals) > 0): ?>
                            <?php foreach ($withdrawals as $w): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group cursor-pointer" onclick="openDrawer(<?= htmlspecialchars(json_encode($w)) ?>)">
                                    <td class="p-3 text-center" onclick="event.stopPropagation()"><input type="checkbox" class="rounded border-slate-300"></td>
                                    
                                    <td class="p-3 whitespace-nowrap">
                                        <div class="font-bold text-blue-600 hover:underline"><?= htmlspecialchars($w['withdrawal_code']) ?></div>
                                    </td>
                                    
                                    <td class="p-3 whitespace-nowrap">
                                        <div class="font-bold text-slate-800 text-xs"><?= date('M d, Y', strtotime($w['requested_at'])) ?></div>
                                        <div class="text-[10px] text-slate-400 font-medium"><?= date('h:i A', strtotime($w['requested_at'])) ?></div>
                                    </td>
                                    
                                    <td class="p-3">
                                        <div class="font-bold text-slate-800 text-xs max-w-[150px] truncate" title="<?= htmlspecialchars($w['business_name']) ?>">
                                            <?= htmlspecialchars($w['business_name']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500"><?= htmlspecialchars($w['seller_name']) ?></div>
                                    </td>
                                    
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <div class="font-black text-slate-800 text-sm tabular-nums">Rs.<?= number_format($w['amount'], 2) ?></div>
                                        <div class="text-[9px] text-slate-400 font-medium">Avail: Rs.<?= number_format($w['available_balance_before'], 0) ?> &rarr; Rs.<?= number_format($w['available_balance_after'], 0) ?></div>
                                    </td>
                                    
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <div class="font-bold text-red-500 text-xs tabular-nums">-Rs.<?= number_format($w['fee'], 2) ?></div>
                                    </td>
                                    
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <div class="font-black text-green-600 text-[15px] tabular-nums">Rs.<?= number_format($w['net_amount'], 2) ?></div>
                                    </td>
                                    
                                    <td class="p-3">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                                            <i class="fas fa-university text-slate-400 text-[10px]"></i> <?= htmlspecialchars($w['bank_name']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500 tabular-nums"><?= htmlspecialchars($w['bank_account_no_masked']) ?></div>
                                    </td>
                                    
                                    <td class="p-3 whitespace-nowrap">
                                        <div class="flex items-center text-xs font-bold text-slate-700">
                                            <span class="status-dot <?= strtolower($w['status']) ?>"></span>
                                            <?= ucfirst(strtolower($w['status'])) ?>
                                        </div>
                                        <?php if($w['status'] == 'PENDING'): ?>
                                            <div class="text-[9px] text-slate-400 mt-0.5">Need Approval</div>
                                        <?php elseif($w['status'] == 'APPROVED'): ?>
                                            <div class="text-[9px] text-blue-500 mt-0.5">Ready to Transfer</div>
                                        <?php elseif($w['status'] == 'REJECTED'): ?>
                                            <div class="text-[9px] text-red-500 mt-0.5 max-w-[100px] truncate" title="<?= htmlspecialchars($w['rejection_reason']) ?>"><?= htmlspecialchars($w['rejection_reason']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="p-3 text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                        <button onclick="openDrawer(<?= htmlspecialchars(json_encode($w)) ?>)" class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-blue-600 transition inline-flex items-center justify-center">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                        <button class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition inline-flex items-center justify-center">
                                            <i class="fas fa-ellipsis-v text-sm"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="p-8 text-center text-slate-500">
                                    <div class="w-16 h-16 mx-auto bg-slate-50 rounded-full flex items-center justify-center mb-3">
                                        <i class="fas fa-inbox text-2xl text-slate-300"></i>
                                    </div>
                                    <div class="font-bold text-slate-700">No requests found</div>
                                    <div class="text-sm mt-1">Try adjusting your filters</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="bg-slate-50 border-t border-slate-200 p-3 flex justify-between items-center text-xs font-bold text-slate-500">
                <div>Showing <?= count($withdrawals) ?> requests</div>
                <div class="flex gap-2">
                    <button class="px-3 py-1.5 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-50" disabled>Previous</button>
                    <button class="px-3 py-1.5 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-50" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Overlay -->
    <div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>

    <!-- Enterprise Drawer -->
    <div class="drawer flex flex-col" id="detailDrawer">
        <!-- Drawer Header -->
        <div class="p-6 border-b border-slate-100 bg-white flex justify-between items-start shrink-0">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h2 class="text-xl font-black text-slate-900" id="dw_code">WTH-000</h2>
                    <span id="dw_status_badge" class="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider bg-yellow-100 text-yellow-800 border border-yellow-200 shadow-sm">
                        PENDING
                    </span>
                </div>
                <div class="text-sm font-bold text-slate-500 tabular-nums">
                    <span id="dw_amount" class="text-slate-800">Rs.0</span> • Requested <span id="dw_date"></span> by <span id="dw_seller_name" class="text-slate-700"></span>
                </div>
            </div>
            <button onclick="closeDrawer()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Drawer Tabs -->
        <div class="px-6 border-b border-slate-200 bg-slate-50/50 flex gap-6 text-sm font-bold shrink-0">
            <button class="py-3 border-b-2 border-blue-600 text-blue-600 tab-btn" onclick="switchTab('details', this)">Details</button>
            <button class="py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 tab-btn" onclick="switchTab('balance', this)">Seller Balance</button>
            <button class="py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 tab-btn" onclick="switchTab('risk', this)">Risk Check <span class="ml-1 w-2 h-2 inline-block rounded-full bg-green-500"></span></button>
            <button class="py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 tab-btn" onclick="switchTab('receipt', this)">Receipt</button>
        </div>

        <!-- Drawer Body -->
        <div class="drawer-body p-6 bg-slate-50 relative">
            
            <!-- Details Tab -->
            <div id="tab_details" class="tab-content space-y-5">
                
                <!-- Request Box -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2 border-b border-slate-100 text-[10px] font-black text-slate-500 uppercase tracking-widest flex justify-between">
                        Request Breakdown <i class="fas fa-coins text-slate-400"></i>
                    </div>
                    <div class="p-4 grid grid-cols-3 gap-4 divide-x divide-slate-100">
                        <div>
                            <div class="text-[10px] text-slate-400 font-bold mb-1">AMOUNT REQUESTED</div>
                            <div class="text-lg font-black text-slate-800 tabular-nums" id="dwd_amount">Rs.0</div>
                        </div>
                        <div class="pl-4">
                            <div class="text-[10px] text-slate-400 font-bold mb-1">PLATFORM FEE (1%)</div>
                            <div class="text-lg font-black text-red-500 tabular-nums" id="dwd_fee">-Rs.0</div>
                        </div>
                        <div class="pl-4">
                            <div class="text-[10px] text-slate-400 font-bold mb-1">NET PAYOUT</div>
                            <div class="text-xl font-black text-green-600 tabular-nums" id="dwd_net">Rs.0</div>
                        </div>
                    </div>
                </div>

                <!-- Bank Box -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2 border-b border-slate-100 text-[10px] font-black text-slate-500 uppercase tracking-widest flex justify-between">
                        Transfer Destination <i class="fas fa-university text-slate-400"></i>
                    </div>
                    <div class="p-4 flex gap-4 items-center">
                        <div class="w-12 h-12 rounded-lg bg-blue-50 border border-blue-100 text-blue-500 flex items-center justify-center text-xl shrink-0">
                            <i class="fas fa-building-columns"></i>
                        </div>
                        <div class="flex-grow">
                            <div class="text-xs font-bold text-slate-400 mb-0.5">BANK ACCOUNT</div>
                            <div class="font-black text-slate-800 text-base" id="dwd_bank">Bank Name</div>
                            <div class="text-sm font-bold text-slate-600 tabular-nums" id="dwd_acc">****0000</div>
                            <div class="text-xs font-semibold text-slate-500 mt-1" id="dwd_holder">Holder Name</div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Balance Calculation Box -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Balance Impact</div>
                    <div class="flex items-center justify-between text-sm font-bold text-slate-600 mb-2">
                        <span>Available Before</span>
                        <span class="tabular-nums" id="dwd_bal_before">Rs.0</span>
                    </div>
                    <div class="flex items-center justify-between text-sm font-bold text-red-500 mb-3 pb-3 border-b border-slate-100">
                        <span>Withdrawal Amount</span>
                        <span class="tabular-nums" id="dwd_bal_deduct">-Rs.0</span>
                    </div>
                    <div class="flex items-center justify-between text-base font-black text-slate-800">
                        <span>Available After</span>
                        <span class="tabular-nums" id="dwd_bal_after">Rs.0</span>
                    </div>
                    <div class="mt-3 p-2 bg-green-50 border border-green-100 rounded text-xs font-bold text-green-700 flex items-center gap-2">
                        <i class="fas fa-shield-check"></i> Sufficient balance available
                    </div>
                </div>
                
                <input type="hidden" id="current_wth_id">
            </div>

            <!-- Seller Balance Tab -->
            <div id="tab_balance" class="tab-content hidden">
                <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500 font-bold shadow-sm">
                    <i class="fas fa-chart-pie text-4xl text-slate-300 mb-3 block"></i>
                    Real-time Balance Sheet<br>
                    <span class="text-xs font-medium mt-1 block">Component coming in Phase 2</span>
                </div>
            </div>

            <!-- Risk Check Tab -->
            <div id="tab_risk" class="tab-content hidden space-y-3">
                <div class="bg-white rounded-xl border border-green-200 shadow-sm p-4 flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl mt-0.5"></i>
                    <div>
                        <div class="font-bold text-slate-800 text-sm">Bank Verified</div>
                        <div class="text-xs font-medium text-slate-500">Account details match seller KYC records.</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-green-200 shadow-sm p-4 flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl mt-0.5"></i>
                    <div>
                        <div class="font-bold text-slate-800 text-sm">Sufficient Balance</div>
                        <div class="text-xs font-medium text-slate-500">Requested amount is within available balance.</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-green-200 shadow-sm p-4 flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl mt-0.5"></i>
                    <div>
                        <div class="font-bold text-slate-800 text-sm">Return Holds Applied</div>
                        <div class="text-xs font-medium text-slate-500">14-day hold for recent orders is correctly deducted.</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-green-200 shadow-sm p-4 flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl mt-0.5"></i>
                    <div>
                        <div class="font-bold text-slate-800 text-sm">No Fraud Flags</div>
                        <div class="text-xs font-medium text-slate-500">0 reports against this seller in the last 30 days.</div>
                    </div>
                </div>
            </div>

            <!-- Receipt Tab -->
            <div id="tab_receipt" class="tab-content hidden">
                <div id="receipt_upload_area" class="bg-white rounded-xl border-2 border-dashed border-slate-200 p-8 text-center shadow-sm">
                    <i class="fas fa-cloud-upload-alt text-4xl text-blue-300 mb-3 block"></i>
                    <div class="font-bold text-slate-700 mb-1">Upload Bank Slip</div>
                    <div class="text-xs font-medium text-slate-400 mb-4">PNG, JPG or PDF (Max 5MB)</div>
                    <button class="bg-blue-50 text-blue-600 px-4 py-2 rounded border border-blue-100 text-sm font-bold hover:bg-blue-100 transition">
                        Select File
                    </button>
                </div>
            </div>

        </div>

        <!-- Drawer Footer Actions -->
        <div class="p-4 border-t border-slate-200 bg-white shrink-0 flex justify-between items-center gap-3" id="drawer_actions">
            <!-- Dynamic Actions populated by JS based on status -->
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[2000] hidden flex items-center justify-center" id="rejectModal">
        <div class="bg-white rounded-2xl shadow-xl w-[400px] overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-black text-slate-800">Reject Withdrawal</h3>
                <button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-5">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Rejection Reason</label>
                <textarea id="reject_reason" rows="3" class="w-full border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-400" placeholder="e.g. Bank account name mismatch..."></textarea>
                <div class="text-[10px] text-slate-400 mt-2">This reason will be emailed to the seller. The requested amount will be refunded to their available balance.</div>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 text-sm font-bold">Cancel</button>
                <button onclick="submitReject()" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 shadow-sm">Confirm Reject</button>
            </div>
        </div>
    </div>

    <script>
        function formatMoney(num) {
            return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function openDrawer(data) {
            document.getElementById('drawerOverlay').classList.add('active');
            document.getElementById('detailDrawer').classList.add('active');
            
            // Populate Header
            document.getElementById('current_wth_id').value = data.id;
            document.getElementById('dw_code').textContent = data.withdrawal_code;
            document.getElementById('dw_amount').textContent = 'Rs.' + formatMoney(data.amount);
            
            // Format Date
            const d = new Date(data.requested_at);
            document.getElementById('dw_date').textContent = d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit'});
            
            document.getElementById('dw_seller_name').textContent = data.seller_name;

            // Status Badge
            const badge = document.getElementById('dw_status_badge');
            badge.textContent = data.status;
            badge.className = 'text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider border shadow-sm ' + getStatusClasses(data.status);

            // Populate Details Tab
            document.getElementById('dwd_amount').textContent = 'Rs.' + formatMoney(data.amount);
            document.getElementById('dwd_fee').textContent = '-Rs.' + formatMoney(data.fee);
            document.getElementById('dwd_net').textContent = 'Rs.' + formatMoney(data.net_amount);
            
            document.getElementById('dwd_bank').textContent = data.bank_name;
            document.getElementById('dwd_acc').textContent = data.bank_account_no_masked;
            document.getElementById('dwd_holder').textContent = data.bank_holder;

            document.getElementById('dwd_bal_before').textContent = 'Rs.' + formatMoney(data.available_balance_before);
            document.getElementById('dwd_bal_deduct').textContent = '-Rs.' + formatMoney(data.amount);
            document.getElementById('dwd_bal_after').textContent = 'Rs.' + formatMoney(data.available_balance_after);

            // Setup Footer Actions based on status
            setupActions(data.status, data.id, data.amount);
            
            // Reset to Details tab
            document.querySelector('.tab-btn').click();
        }

        function getStatusClasses(status) {
            switch(status) {
                case 'PENDING': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
                case 'APPROVED': return 'bg-blue-100 text-blue-800 border-blue-200';
                case 'PROCESSING': return 'bg-purple-100 text-purple-800 border-purple-200';
                case 'COMPLETED': return 'bg-green-100 text-green-800 border-green-200';
                case 'REJECTED': return 'bg-red-100 text-red-800 border-red-200';
                default: return 'bg-slate-100 text-slate-800 border-slate-200';
            }
        }

        function setupActions(status, id, amount) {
            const container = document.getElementById('drawer_actions');
            let html = '';
            
            if (status === 'PENDING') {
                html = `
                    <button onclick="showRejectModal()" class="px-4 py-2.5 bg-white border border-red-200 text-red-500 rounded-lg text-sm font-bold hover:bg-red-50 transition">Reject Request</button>
                    <button onclick="updateStatus(${id}, 'APPROVED')" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-bold shadow-md hover:bg-blue-700 transition flex items-center gap-2">
                        <i class="fas fa-check"></i> Approve Rs.${formatMoney(amount)}
                    </button>
                `;
            } else if (status === 'APPROVED') {
                html = `
                    <button onclick="updateStatus(${id}, 'PENDING')" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-50 transition">Back to Pending</button>
                    <button onclick="updateStatus(${id}, 'PROCESSING')" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg text-sm font-bold shadow-md hover:bg-purple-700 transition flex items-center gap-2">
                        <i class="fas fa-spinner"></i> Mark Processing
                    </button>
                `;
            } else if (status === 'PROCESSING') {
                html = `
                    <button onclick="updateStatus(${id}, 'APPROVED')" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-50 transition">Back to Approved</button>
                    <button onclick="updateStatus(${id}, 'COMPLETED')" class="px-6 py-2.5 bg-green-600 text-white rounded-lg text-sm font-bold shadow-md hover:bg-green-700 transition flex items-center gap-2">
                        <i class="fas fa-check-circle"></i> Upload Receipt & Complete
                    </button>
                `;
            } else {
                html = `
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest"><i class="fas fa-lock text-slate-300 mr-1"></i> Workflow Completed</div>
                    <button onclick="closeDrawer()" class="px-6 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-bold hover:bg-slate-900 transition">Close</button>
                `;
            }
            
            container.innerHTML = html;
        }

        function closeDrawer() {
            document.getElementById('drawerOverlay').classList.remove('active');
            document.getElementById('detailDrawer').classList.remove('active');
        }

        function switchTab(tabId, btn) {
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            // Show target content
            document.getElementById('tab_' + tabId).classList.remove('hidden');
            
            // Update button styles
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-blue-600', 'text-blue-600');
                el.classList.add('border-transparent', 'text-slate-500');
            });
            btn.classList.remove('border-transparent', 'text-slate-500');
            btn.classList.add('border-blue-600', 'text-blue-600');
        }
        
        function showRejectModal() {
            document.getElementById('rejectModal').classList.remove('hidden');
            document.getElementById('reject_reason').value = '';
        }
        
        function submitReject() {
            const reason = document.getElementById('reject_reason').value.trim();
            if(!reason) { alert("Please enter a rejection reason."); return; }
            const id = document.getElementById('current_wth_id').value;
            updateStatus(id, 'REJECTED', reason);
        }

        function updateStatus(id, status, reason = '') {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', status);
            if(reason) formData.append('reason', reason);
            
            fetch('api/withdrawal_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert("Network error occurred.");
            });
        }
    </script>
</body>
</html>
