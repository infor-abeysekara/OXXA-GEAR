<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Fetch all users with subqueries for stats and enterprise moderation data
$usersQuery = "
    SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.phone, u.profile_image, 
           u.user_type, u.is_approved, u.status_reason, u.created_at,
           u.nic_no, u.gender, u.dob, u.last_login, u.last_login_ip, u.total_logins, u.login_method, u.two_factor_enabled, u.is_blocked, u.suspension_date, u.suspended_by,
           sp.business_name, sp.business_type, sp.business_reg_id, sp.is_approved as business_approved,
           COALESCE(addr.city, sp.city, 'N/A') as city,
           COALESCE(addr.province, sp.province, '') as province,
           (SELECT COUNT(*) FROM products WHERE seller_id = u.id) as total_products,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status != 'cancelled') as buyer_orders,
           (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND status != 'cancelled') as total_spent,
           (SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = u.id AND o.status != 'cancelled') as seller_orders,
           (SELECT COALESCE(SUM(oi.unit_price * oi.quantity), 0) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.seller_id = u.id AND o.status != 'cancelled') as total_earned
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    LEFT JOIN (
        SELECT user_id, city, province
        FROM user_addresses
        WHERE is_default_shipping = 1 OR id IN (SELECT MIN(id) FROM user_addresses GROUP BY user_id)
        GROUP BY user_id
    ) addr ON u.id = addr.user_id
    ORDER BY u.id DESC
";
$stmt = $conn->prepare($usersQuery);
$stmt->execute();
$result = $stmt->get_result();
$usersData = [];
while($row = $result->fetch_assoc()) {
    $usersData[] = $row;
}
$usersJson = json_encode($usersData);

// Fetch distinct cities for dropdown
$cityList = [];
$cityRes = $conn->query("SELECT DISTINCT city FROM (
    SELECT city FROM user_addresses WHERE city IS NOT NULL AND TRIM(city) != ''
    UNION 
    SELECT city FROM seller_profiles WHERE city IS NOT NULL AND TRIM(city) != ''
) c ORDER BY city ASC");
if ($cityRes) {
    while($cRow = $cityRes->fetch_assoc()) {
        $cityList[] = $cRow['city'];
    }
}

// Calculate top stats
$totalSellers = 0;
$totalBuyers = 0;
$activeUsers = 0;
$suspendedUsers = 0;
$pendingUsers = 0;

foreach ($usersData as $user) {
    if ($user['user_type'] === 'seller') {
        $totalSellers++;
        if ($user['business_approved'] == 0) $pendingUsers++;
    }
    if ($user['user_type'] === 'customer') {
        $totalBuyers++;
    }
    if ($user['is_approved'] == 1 && $user['is_blocked'] != 1) {
        $activeUsers++;
    } else {
        $suspendedUsers++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | OXXA GEAR Control Center</title>
    <link rel="icon" type="image/png" href="../image/oxxa_gear_logo.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { navy: '#0F172A', primary: '#0066FF' }
                }
            }
        }
    </script>
    
    <style>
        body { background-color: #F8FAFF; }
        
        .premium-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }
        
        /* Table styles */
        .table-container { overflow-x: auto; }
        .table-row { transition: background-color 0.2s ease; border-bottom: 1px solid #F1F5F9; }
        .table-row:hover { background-color: #F8FAFF; }
        
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .pulse-active { background-color: #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
        .pulse-suspended { background-color: #F59E0B; }
        .pulse-pending { background-color: #3B82F6; }
        
        /* Tabs */
        .tab-btn {
            padding: 12px 24px;
            font-weight: 600;
            color: #64748B;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #8B5CF6; /* Purple active */
            border-bottom: 3px solid #8B5CF6;
        }
        .tab-btn:hover:not(.active) { color: #334155; }
        
        /* Checkbox */
        .custom-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #CBD5E1;
            cursor: pointer;
            accent-color: #0066FF;
        }
    </style>
</head>
<body class="text-navy overflow-x-hidden">
    
    <?php include("components/sidebar.php"); ?>
    
    <!-- Bulk Action Floating Bar -->
    <div id="bulkActionBar" class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-navy text-white px-6 py-4 rounded-full shadow-2xl flex items-center gap-6 z-50 transition-all duration-300 translate-y-24 opacity-0">
        <span class="font-bold"><span id="selectedCount">0</span> Users Selected</span>
        <div class="h-6 w-px bg-white/20"></div>
        <button onclick="bulkSuspend()" class="text-sm font-bold text-yellow-400 hover:text-yellow-300"><i class="fas fa-pause mr-2"></i>Suspend</button>
        <button onclick="bulkDelete()" class="text-sm font-bold text-red-400 hover:text-red-300"><i class="fas fa-trash mr-2"></i>Delete</button>
        <button onclick="bulkExport()" class="text-sm font-bold text-green-400 hover:text-green-300"><i class="fas fa-file-export mr-2"></i>Export</button>
    </div>

    <!-- View User Modal -->
    <div id="viewUserModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
            <!-- Header -->
            <div class="flex justify-between items-center p-6 border-b border-gray-100">
                <h3 class="text-xl font-black uppercase tracking-wider">User Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-50 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            
            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8" id="modalContent">
                <!-- Injected via JS -->
            </div>
        </div>
    </div>

    <div class="main-content min-h-screen pb-20">
        <?php include("components/topbar.php"); ?>
        
        <div class="p-8">
            <!-- Standardized Page Header -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg font-black shadow-sm" style="width: 42px; height: 42px; border-radius: 12px; background: #EFF6FF; color: #0066FF; flex-shrink: 0;">
                        <i class="fas fa-users-gear"></i>
                    </span>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2" style="font-size: 1.35rem; font-weight: 900; color: #0F172A; letter-spacing: -0.025em; margin: 0; line-height: 1.2;">
                            Manage Users
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 tracking-normal uppercase" style="font-size: 0.65rem; font-weight: 800; padding: 3px 8px; border-radius: 9999px; background: #DBEAFE; color: #1E40AF; letter-spacing: 0.05em;">Enterprise Hub</span>
                        </h1>
                        <p class="text-xs font-medium text-slate-500 mt-0.5 mb-0" style="color: #64748B; font-size: 0.78rem; font-weight: 500;">Identity verification, role assignment, seller KYC audits & risk moderation.</p>
                    </div>
                </div>
            </div>


            <!-- Top Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="premium-card p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 text-xl"><i class="fas fa-store"></i></div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Sellers</p>
                        <h4 class="text-2xl font-black mt-1"><?php echo number_format($totalSellers); ?></h4>
                    </div>
                </div>
                
                <div class="premium-card p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-500 text-xl"><i class="fas fa-shopping-bag"></i></div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Buyers</p>
                        <h4 class="text-2xl font-black mt-1"><?php echo number_format($totalBuyers); ?></h4>
                    </div>
                </div>
                
                <div class="premium-card p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Active</p>
                        <h4 class="text-2xl font-black mt-1"><?php echo number_format($activeUsers); ?></h4>
                    </div>
                </div>
                
                <div class="premium-card p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-500 text-xl"><i class="fas fa-pause-circle"></i></div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Suspended</p>
                        <h4 class="text-2xl font-black mt-1"><?php echo number_format($suspendedUsers); ?></h4>
                    </div>
                </div>
                
                <div class="premium-card p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 text-xl"><i class="fas fa-clock"></i></div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Pending</p>
                        <h4 class="text-2xl font-black mt-1"><?php echo number_format($pendingUsers); ?></h4>
                    </div>
                </div>
            </div>

            <!-- Enterprise Filter & Export Toolbar -->
            <div class="premium-card p-5 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4 mb-4">
                    <div>
                        <h2 class="text-base font-black text-navy flex items-center gap-2">
                            <i class="fas fa-users-cog text-primary"></i> Enterprise User Directory & Audit Intelligence
                        </h2>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Filter by role, security status, location, and export complete 43-column audit files</p>
                    </div>
                    <!-- Export Actions Group -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button onclick="triggerUsersExport('all')" class="bg-navy hover:bg-slate-800 text-white px-4 py-2.5 rounded-xl font-bold text-xs tracking-wide shadow-sm hover:shadow transition-all flex items-center gap-2" title="Export all users with all 43 columns">
                            <i class="fas fa-file-csv text-emerald-400 text-sm"></i> Export Full CSV (43 Cols)
                        </button>
                        <button onclick="triggerUsersExport('sellers')" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-3.5 py-2.5 rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5" title="Export Sellers sorted by Sales GMV">
                            <i class="fas fa-store text-blue-600"></i> Export Sellers
                        </button>
                        <button onclick="triggerUsersExport('customers')" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-3.5 py-2.5 rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5" title="Export Customers sorted by Total Spent (VIP Top)">
                            <i class="fas fa-shopping-bag text-emerald-600"></i> Export Customers
                        </button>
                        <button onclick="triggerUsersExport('suspended')" class="bg-rose-50 border border-rose-100 hover:bg-rose-100 text-rose-700 px-3.5 py-2.5 rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5" title="Export Suspended and Banned Users with reasons">
                            <i class="fas fa-user-slash text-rose-600"></i> Export Suspended
                        </button>
                    </div>
                </div>

                <!-- Filter Controls Bar -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="searchInput" class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-xs font-semibold text-gray-700 placeholder-gray-400 transition-colors" placeholder="Search name, phone, email, biz...">
                    </div>
                    
                    <div>
                        <select id="roleFilter" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-xs font-bold text-gray-600">
                            <option value="all">Role: All Roles</option>
                            <option value="seller">Role: Sellers</option>
                            <option value="customer">Role: Customers</option>
                            <option value="admin">Role: Administrators</option>
                        </select>
                    </div>

                    <div>
                        <select id="statusFilter" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-xs font-bold text-gray-600">
                            <option value="all">Status: All Status</option>
                            <option value="active">Status: Active Only</option>
                            <option value="suspended">Status: Suspended Only</option>
                            <option value="banned">Status: Banned Only</option>
                        </select>
                    </div>

                    <div>
                        <select id="cityFilter" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-xs font-bold text-gray-600">
                            <option value="all">City: All Cities</option>
                            <?php foreach($cityList as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <select id="loginFilter" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-xs font-bold text-gray-600">
                            <option value="all">Last Login: All</option>
                            <option value="active_30">Active (Last 30 Days)</option>
                            <option value="inactive_30">Inactive (>30 Days)</option>
                        </select>
                    </div>
                </div>
            </div>

            
            <!-- Tabs -->
            <div class="flex items-center gap-2 border-b border-gray-200 mb-6 px-2 overflow-x-auto">
                <button class="tab-btn active" data-tab="all">All Users <span class="bg-blue-100 text-blue-700 text-xs py-0.5 px-2 rounded-full ml-2"><?php echo count($usersData); ?></span></button>
                <button class="tab-btn" data-tab="seller">Sellers <span class="bg-blue-100 text-blue-700 text-xs py-0.5 px-2 rounded-full ml-2"><?php echo $totalSellers; ?></span></button>
                <button class="tab-btn" data-tab="customer">Buyers <span class="bg-green-100 text-green-700 text-xs py-0.5 px-2 rounded-full ml-2"><?php echo $totalBuyers; ?></span></button>
                <button class="tab-btn" data-tab="suspended">Suspended <span class="bg-yellow-100 text-yellow-700 text-xs py-0.5 px-2 rounded-full ml-2"><?php echo $suspendedUsers; ?></span></button>
                <button class="tab-btn" data-tab="pending">Pending <span class="bg-gray-100 text-gray-700 text-xs py-0.5 px-2 rounded-full ml-2"><?php echo $pendingUsers; ?></span></button>
            </div>
            
            <!-- Table Container -->
            <div class="premium-card overflow-hidden">
                <div class="table-container">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="p-4 pl-6 w-12"><input type="checkbox" id="selectAll" class="custom-checkbox"></th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">User</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Contact</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Business / Role</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Stats</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="p-4 pr-6 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Injected via JS -->
                        </tbody>
                    </table>
                </div>
                
                <div id="noResults" class="hidden text-center py-12">
                    <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                    <h4 class="text-lg font-bold text-gray-500">No users found</h4>
                    <p class="text-sm text-gray-400">Try adjusting your search criteria</p>
                </div>
            </div>

        </div>
    </div>
    
    <script>
        const usersData = <?php echo $usersJson; ?>;
        let filteredUsers = [...usersData];
        let currentTab = 'all';
        let selectedIds = new Set();
        
        const tableBody = document.getElementById('tableBody');
        const noResults = document.getElementById('noResults');
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const cityFilter = document.getElementById('cityFilter');
        const loginFilter = document.getElementById('loginFilter');
        const selectAll = document.getElementById('selectAll');
        const bulkActionBar = document.getElementById('bulkActionBar');
        const selectedCount = document.getElementById('selectedCount');
        
        function getInitials(firstName, lastName) {
            return ((firstName || '').charAt(0) + (lastName || '').charAt(0)).toUpperCase() || 'U';
        }
        
        function formatDate(dateString) {
            if(!dateString || dateString === '0000-00-00 00:00:00') return 'Unknown';
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return 'Unknown';
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function renderTable() {
            tableBody.innerHTML = '';
            
            if(filteredUsers.length === 0) {
                noResults.classList.remove('hidden');
                return;
            } else {
                noResults.classList.add('hidden');
            }
            
            filteredUsers.forEach(user => {
                try {
                    const tr = document.createElement('tr');
                    tr.className = 'table-row text-sm';
                    
                    const isSelected = selectedIds.has(parseInt(user.id));
                
                // Avatar html
                let avatarHtml = '';
                if(user.profile_image) {
                    avatarHtml = `<img src="../assets/uploads/profiles/${user.profile_image}" onerror="this.src='https://oxxagear.lk/assets/images/default-avatar.png'" class="w-10 h-10 rounded-full object-cover shadow-sm">`;
                } else {
                    avatarHtml = `<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-black shadow-sm">${getInitials(user.first_name, user.last_name)}</div>`;
                }
                
                // Role pill & Full Business Name
                let roleHtml = '';
                if(user.user_type === 'seller') {
                    let statusText = 'Approved';
                    let statusColor = 'bg-green-100 text-green-700';
                    if (user.business_approved == 0) {
                        statusText = 'Pending';
                        statusColor = 'bg-amber-100 text-amber-700';
                    }
                    const bType = user.business_type ? `<span class="bg-gray-100 text-gray-600 text-[10px] px-2 py-0.5 rounded-full font-semibold">${user.business_type}</span>` : '';
                    
                    roleHtml = `
                        <div class="font-bold text-navy text-xs leading-tight max-w-[210px]" title="${user.business_name || 'Seller'}">${user.business_name || 'Seller (Pending Setup)'}</div>
                        <div class="mt-1 flex items-center flex-wrap gap-1">
                            <span class="${statusColor} text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-full">${statusText}</span>
                            ${bType}
                        </div>
                    `;
                } else if(user.user_type === 'admin') {
                    roleHtml = `<span class="bg-purple-100 text-purple-700 font-bold px-2.5 py-1 rounded-full text-xs uppercase tracking-wider">Administrator</span>`;
                } else {
                    roleHtml = `<span class="bg-blue-50 text-blue-600 font-bold px-2.5 py-1 rounded-full text-xs uppercase tracking-wider">Customer</span>`;
                }
                
                // Stats HTML
                let statsHtml = '';
                if(user.user_type === 'seller') {
                    statsHtml = `<div class="text-xs font-semibold text-gray-600">Products: <span class="text-navy font-bold">${user.total_products}</span> | Orders: <span class="text-navy font-bold">${user.seller_orders}</span> <br> GMV: <span class="text-green-600 font-bold">Rs.${Number(user.total_earned).toLocaleString()}</span></div>`;
                } else {
                    statsHtml = `<div class="text-xs font-semibold text-gray-600">Orders: <span class="text-navy font-bold">${user.buyer_orders}</span> <br> Spent: <span class="text-green-600 font-bold">Rs.${Number(user.total_spent).toLocaleString()}</span></div>`;
                }
                
                // Status HTML with Reason Badge
                let statusHtml = '';
                if(user.is_blocked == 1) {
                    statusHtml = `<div>
                        <div class="flex items-center gap-1.5"><span class="pulse-dot bg-red-600"></span><span class="font-bold text-red-600 text-xs uppercase tracking-wider">Banned</span></div>
                        <div class="text-[11px] text-red-600 font-semibold mt-0.5 max-w-[150px] truncate" title="${user.status_reason || 'Policy Violation'}">${user.status_reason || 'Policy Violation'}</div>
                    </div>`;
                } else if(user.is_approved == 1) {
                    statusHtml = `<div class="flex items-center gap-1.5"><span class="pulse-dot pulse-active"></span><span class="font-bold text-green-600 text-xs uppercase tracking-wider">Active</span></div>`;
                } else {
                    statusHtml = `<div>
                        <div class="flex items-center gap-1.5"><span class="pulse-dot pulse-suspended"></span><span class="font-bold text-yellow-600 text-xs uppercase tracking-wider">Suspended</span></div>
                        <div class="text-[11px] text-yellow-700 font-semibold mt-0.5 max-w-[150px] truncate" title="${user.status_reason || 'Account under compliance review'}">${user.status_reason || 'Under Review'}</div>
                    </div>`;
                }

                // Phone format with copy and location
                let phoneDisplay = user.phone || 'N/A';
                let cityDisplay = user.city && user.city !== 'N/A' ? `<div class="text-[11px] text-gray-400 mt-0.5"><i class="fas fa-map-marker-alt text-gray-300 mr-1"></i>${user.city}</div>` : '';
                
                tr.innerHTML = `
                    <td class="p-4 pl-6"><input type="checkbox" class="custom-checkbox row-cb" value="${user.id}" ${isSelected ? 'checked' : ''}></td>
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            ${avatarHtml}
                            <div>
                                <div class="font-bold text-navy">${user.first_name || ''} ${user.last_name || ''}</div>
                                <div class="text-xs text-gray-400">@${user.username} <span class="mx-1">•</span> ID: #${user.id}</div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <div class="font-semibold text-gray-700 flex items-center gap-1"><a href="mailto:${user.email}" class="hover:text-primary transition-colors">${user.email}</a> <i class="fas fa-check-circle text-green-500 text-xs ml-1" title="Verified"></i></div>
                        <div class="text-xs text-gray-500 mt-1 cursor-pointer flex items-center gap-1" onclick="copyToClipboard('${phoneDisplay}')"><i class="fas fa-phone-alt"></i> ${phoneDisplay} <i class="far fa-copy opacity-0 group-hover:opacity-100 ml-1"></i></div>
                        ${cityDisplay}
                    </td>
                    <td class="p-4">${roleHtml}</td>
                    <td class="p-4">${statsHtml}</td>
                    <td class="p-4">${statusHtml}</td>
                    <td class="p-4 pr-6 text-right relative">
                        <button onclick="viewUser(${user.id})" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors mr-2">View</button>
                        
                        <div class="inline-block relative user-dropdown-container">
                            <button type="button" onclick="toggleActionDropdown(event, this)" class="w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-navy flex items-center justify-center transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="action-dropdown-menu absolute right-0 top-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl w-44 z-[60] hidden text-left overflow-hidden py-1">
                                <button type="button" onclick="viewUser(${user.id})" class="w-full text-left px-4 py-2 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-primary transition-colors flex items-center gap-2.5">
                                    <i class="fas fa-user-circle w-4 text-gray-400"></i> View Profile
                                </button>
                                ${user.user_type === 'seller' ? `
                                    <a href="manage-products.php?seller_id=${user.id}" class="block px-4 py-2 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-primary transition-colors flex items-center gap-2.5">
                                        <i class="fas fa-box w-4 text-gray-400"></i> Products (${user.total_products || 0})
                                    </a>
                                ` : ''}
                                ${user.is_approved == 1 ? `
                                    <button type="button" onclick="actionSingle('suspend', ${user.id})" class="w-full text-left px-4 py-2 text-xs font-bold text-amber-600 hover:bg-amber-50 transition-colors flex items-center gap-2.5">
                                        <i class="fas fa-pause-circle w-4 text-amber-500"></i> Suspend User
                                    </button>
                                ` : `
                                    <button type="button" onclick="actionSingle('activate', ${user.id})" class="w-full text-left px-4 py-2 text-xs font-bold text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center gap-2.5">
                                        <i class="fas fa-check-circle w-4 text-emerald-500"></i> Activate User
                                    </button>
                                `}
                                <div class="border-t border-gray-100 my-1"></div>
                                <button type="button" onclick="actionSingle('delete', ${user.id})" class="w-full text-left px-4 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors flex items-center gap-2.5">
                                    <i class="fas fa-trash-alt w-4 text-rose-500"></i> Delete User
                                </button>
                            </div>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
                } catch(e) {
                    console.error("Error rendering row for user:", user, e);
                }
            });
            
            // Attach checkbox events
            document.querySelectorAll('.row-cb').forEach(cb => {
                cb.addEventListener('change', function() {
                    if(this.checked) selectedIds.add(parseInt(this.value));
                    else selectedIds.delete(parseInt(this.value));
                    updateBulkBar();
                    
                    // Update select all state
                    const allVisibleCb = Array.from(document.querySelectorAll('.row-cb'));
                    selectAll.checked = allVisibleCb.length > 0 && allVisibleCb.every(c => c.checked);
                });
            });
        }
        
        function applyFilters() {
            const search = (searchInput?.value || '').toLowerCase().trim();
            const role = roleFilter ? roleFilter.value : 'all';
            const status = statusFilter ? statusFilter.value : 'all';
            const city = cityFilter ? cityFilter.value.toLowerCase() : 'all';
            const login = loginFilter ? loginFilter.value : 'all';
            const now = new Date();
            const thirtyDaysAgo = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));
            
            filteredUsers = usersData.filter(user => {
                // Tab filter
                if(currentTab === 'seller' && user.user_type !== 'seller') return false;
                if(currentTab === 'customer' && user.user_type !== 'customer') return false;
                if(currentTab === 'suspended' && user.is_approved == 1 && user.is_blocked != 1) return false;
                if(currentTab === 'pending' && (user.user_type !== 'seller' || user.business_approved == 1)) return false;
                
                // Role filter
                if(role !== 'all' && user.user_type !== role) return false;

                // Status Filter
                if(status === 'active' && (user.is_approved == 0 || user.is_blocked == 1)) return false;
                if(status === 'suspended' && user.is_approved == 1) return false;
                if(status === 'banned' && user.is_blocked != 1) return false;
                
                // City filter
                if(city !== 'all') {
                    const userCity = (user.city || '').toLowerCase();
                    const userProv = (user.province || '').toLowerCase();
                    if(!userCity.includes(city) && !userProv.includes(city)) return false;
                }

                // Last login filter
                if(login !== 'all') {
                    if(!user.last_login) {
                        if(login === 'active_30') return false;
                    } else {
                        const lDate = new Date(user.last_login);
                        if(login === 'active_30' && lDate < thirtyDaysAgo) return false;
                        if(login === 'inactive_30' && lDate >= thirtyDaysAgo) return false;
                    }
                }
                
                // Search
                if(search) {
                    const text = `${user.first_name || ''} ${user.last_name || ''} ${user.username || ''} ${user.email || ''} ${user.phone || ''} ${user.business_name || ''} ${user.city || ''}`.toLowerCase();
                    if(!text.includes(search)) return false;
                }
                
                return true;
            });
            
            renderTable();
        }
        
        // Event Listeners for Filters
        if (searchInput) searchInput.addEventListener('keyup', applyFilters);
        if (roleFilter) roleFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
        if (cityFilter) cityFilter.addEventListener('change', applyFilters);
        if (loginFilter) loginFilter.addEventListener('change', applyFilters);
        
        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                try {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentTab = this.getAttribute('data-tab');
                    applyFilters();
                } catch (err) {
                    console.error("Tab click error:", err);
                }
            });
        });
        
        // Select All
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('.row-cb').forEach(cb => {
                    cb.checked = isChecked;
                    if(isChecked) selectedIds.add(parseInt(cb.value));
                    else selectedIds.delete(parseInt(cb.value));
                });
                updateBulkBar();
            });
        }
        
        function updateBulkBar() {
            selectedCount.textContent = selectedIds.size;
            if(selectedIds.size > 0) {
                bulkActionBar.classList.remove('translate-y-24', 'opacity-0');
                bulkActionBar.classList.add('translate-y-0', 'opacity-100');
            } else {
                bulkActionBar.classList.add('translate-y-24', 'opacity-0');
                bulkActionBar.classList.remove('translate-y-0', 'opacity-100');
            }
        }
        
        // Modal Logic
        function viewUser(id) {
            const user = usersData.find(u => u.id == id);
            if(!user) return;
            
            const modal = document.getElementById('viewUserModal');
            const content = document.getElementById('modalContent');
            
            let avatarHtml = user.profile_image 
                ? `<img src="../assets/uploads/profiles/${user.profile_image}" onerror="this.src='https://oxxagear.lk/assets/images/default-avatar.png'" class="w-24 h-24 rounded-2xl object-cover shadow-lg border-4 border-white mb-4">`
                : `<div class="w-24 h-24 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600 font-black text-4xl shadow-lg border-4 border-white mb-4">${getInitials(user.first_name, user.last_name)}</div>`;
                
            let maskedNic = user.nic_no && user.nic_no.length >= 8 
                ? user.nic_no.substring(0, 4) + '****' + user.nic_no.substring(user.nic_no.length - 4) 
                : (user.nic_no || 'N/A');

            let statusBadge = user.is_approved == 1 
                ? '<span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full font-bold">Active</span>'
                : '<span class="bg-rose-100 text-rose-700 text-xs px-2.5 py-1 rounded-full font-bold">Suspended</span>';

            let leftHtml = `
                <div>
                    ${avatarHtml}
                    <div class="flex items-center gap-2 mb-1">
                        <h2 class="text-2xl font-black text-navy">${user.first_name || ''} ${user.last_name || ''}</h2>
                        ${statusBadge}
                    </div>
                    <p class="text-gray-500 font-bold mb-4">@${user.username} <span class="text-xs text-gray-400">• ID: #${user.id}</span></p>
                    
                    <div class="space-y-3 mb-6 text-sm">
                        <div class="flex items-center gap-3"><i class="fas fa-envelope text-gray-400 w-5"></i> <a href="mailto:${user.email}" class="font-semibold text-gray-700 hover:text-primary">${user.email}</a> <i class="fas fa-check-circle text-green-500 text-xs"></i></div>
                        <div class="flex items-center gap-3"><i class="fas fa-phone text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">${user.phone || 'N/A'}</span></div>
                        <div class="flex items-center gap-3"><i class="fas fa-id-card text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">NIC: ${maskedNic}</span></div>
                        <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">${user.city || 'N/A'} ${user.province ? '• ' + user.province : ''}</span></div>
                        <div class="flex items-center gap-3"><i class="fas fa-calendar-alt text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">Joined ${formatDate(user.created_at)}</span></div>
                    </div>
                    
                    ${user.user_type === 'seller' ? `
                    <div class="bg-blue-50/50 rounded-xl p-4 border border-blue-100">
                        <h4 class="text-xs font-black uppercase tracking-wider text-blue-500 mb-2">Business & KYC Profile</h4>
                        <div class="font-black text-navy text-sm">${user.business_name || 'Pending Registration'}</div>
                        <div class="text-xs font-semibold text-gray-600 mt-1">${user.business_type || 'Company'} • Reg: ${user.business_reg_id || 'Pending'}</div>
                    </div>
                    ` : ''}

                    ${user.is_approved == 0 ? `
                    <div class="mt-4 bg-rose-50 rounded-xl p-4 border border-rose-100">
                        <h4 class="text-xs font-black uppercase tracking-wider text-rose-500 mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Moderation Flag</h4>
                        <p class="text-xs font-bold text-rose-800">${user.status_reason || 'Account under compliance review'}</p>
                        <p class="text-[11px] text-rose-600 mt-1">Suspension Date: ${user.suspension_date ? user.suspension_date.substring(0,10) : '2026-09-19'} by ${user.suspended_by || 'Admin'}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            let rightHtml = `
                <div>
                    <h4 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Performance & Security Stats</h4>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-blue-50 rounded-xl p-4">
                            <div class="text-xs font-bold text-blue-400 uppercase tracking-wider">${user.user_type === 'seller' ? 'Products Listed' : 'Customer Orders'}</div>
                            <div class="text-2xl font-black text-blue-700">${user.user_type === 'seller' ? user.total_products : user.buyer_orders}</div>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4">
                            <div class="text-xs font-bold text-green-500 uppercase tracking-wider">${user.user_type === 'seller' ? 'Total GMV' : 'Total Spent'}</div>
                            <div class="text-xl font-black text-green-700">Rs.${Number(user.user_type === 'seller' ? user.total_earned : user.total_spent).toLocaleString()}</div>
                        </div>
                    </div>
                    
                    <h4 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-3">Security & Audit Tracking</h4>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 text-xs space-y-2 mb-6">
                        <div class="flex justify-between"><span class="text-gray-500">Last Login:</span> <span class="font-bold text-navy">${user.last_login || 'Never'}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Last Login IP:</span> <span class="font-bold font-mono text-gray-700">${user.last_login_ip || '127.0.0.1'}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Total Logins:</span> <span class="font-bold text-navy">${user.total_logins || 1} times</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Login Method:</span> <span class="font-bold text-navy">${user.login_method || 'Email'}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">2FA Protected:</span> <span class="font-bold ${user.two_factor_enabled ? 'text-green-600' : 'text-gray-500'}">${user.two_factor_enabled ? 'Enabled' : 'Disabled'}</span></div>
                    </div>
                    
                    <div class="flex gap-2">
                        ${user.is_approved == 1 ? `
                            <button onclick="actionSingle('suspend', ${user.id})" class="flex-1 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 font-bold py-3 rounded-xl transition-colors text-xs uppercase tracking-wider">Suspend User</button>
                        ` : `
                            <button onclick="actionSingle('activate', ${user.id})" class="flex-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold py-3 rounded-xl transition-colors text-xs uppercase tracking-wider">Activate User</button>
                        `}
                        <button onclick="actionSingle('delete', ${user.id})" class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-bold py-3 rounded-xl transition-colors text-xs uppercase tracking-wider">Delete User</button>
                    </div>
                </div>
            `;
            
            content.innerHTML = leftHtml + rightHtml;
            document.getElementById('viewUserModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1500 });
        }
        
        // --- Actions ---
        
        function processAction(action, userIds) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to ${action} ${userIds.length} user(s).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'delete' ? '#EF4444' : '#F59E0B',
                cancelButtonColor: '#64748B',
                confirmButtonText: `Yes, ${action}!`
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/bulk-users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: action, ids: userIds })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire('Success!', data.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }
        
        function actionSingle(action, id) {
            closeModal();
            processAction(action, [id]);
        }
        
        function bulkSuspend() {
            processAction('suspend', Array.from(selectedIds));
        }
        
        function bulkDelete() {
            processAction('delete', Array.from(selectedIds));
        }
        
        // Enterprise CSV Export Triggers
        function triggerUsersExport(type = 'all') {
            const search = encodeURIComponent(document.getElementById('searchInput')?.value.trim() || '');
            const role = encodeURIComponent(document.getElementById('roleFilter')?.value || 'all');
            const status = encodeURIComponent(document.getElementById('statusFilter')?.value || 'all');
            const city = encodeURIComponent(document.getElementById('cityFilter')?.value || 'all');
            
            const url = `users_export.php?type=${type}&search=${search}&role=${role}&status=${status}&city=${city}`;
            window.location.href = url;
        }
        
        function exportCSV() {
            triggerUsersExport('all');
        }

        
        function toggleActionDropdown(e, btn) {
            if (e) {
                e.stopPropagation();
            }
            const container = btn.closest('.user-dropdown-container');
            const menu = container ? container.querySelector('.action-dropdown-menu') : btn.nextElementSibling;
            if (!menu) return;

            const isCurrentlyHidden = menu.classList.contains('hidden');

            // Close all open action menus and reset z-indexes
            document.querySelectorAll('.action-dropdown-menu').forEach(m => {
                m.classList.add('hidden');
                const tr = m.closest('tr');
                if (tr) tr.classList.remove('z-30', 'relative');
            });

            if (isCurrentlyHidden) {
                menu.classList.remove('hidden');
                const tr = btn.closest('tr');
                if (tr) tr.classList.add('z-30', 'relative');
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.action-dropdown-menu') && !e.target.closest('button[onclick*="toggleActionDropdown"]')) {
                document.querySelectorAll('.action-dropdown-menu').forEach(m => {
                    m.classList.add('hidden');
                    const tr = m.closest('tr');
                    if (tr) tr.classList.remove('z-30', 'relative');
                });
            }
        });
        
        // Initial render
        renderTable();

    </script>
</body>
</html>