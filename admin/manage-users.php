<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

// Fetch all users with subqueries for stats
$usersQuery = "
    SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.phone, u.profile_image, 
           u.user_type, u.is_approved, u.created_at,
           sp.business_name, sp.business_type, sp.is_approved as business_approved,
           (SELECT COUNT(*) FROM products WHERE seller_id = u.id) as total_products,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as buyer_orders,
           (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status = 'delivered') as total_spent,
           (SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE p.seller_id = u.id) as seller_orders,
           (SELECT SUM(oi.unit_price * oi.quantity) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.seller_id = u.id AND o.status = 'delivered') as total_earned
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
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
    if ($user['is_approved'] == 1) {
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
    <title>Manage Users - Admin Panel</title>
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
            <div class="mb-8 flex justify-between items-end">
                <div>
                    <h2 class="text-3xl font-black uppercase tracking-tight">Manage Users</h2>
                    <p class="text-gray-500 font-medium mt-1">Search, filter, and manage all platform accounts.</p>
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

            <!-- Search + Filters Bar -->
            <div class="premium-card p-4 mb-6 flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[250px] relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 text-sm font-medium transition-colors" placeholder="Search users, business, email...">
                </div>
                
                <div class="w-48">
                    <select id="statusFilter" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-sm font-bold text-gray-600">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                
                <div class="w-48">
                    <select id="typeFilter" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-sm font-bold text-gray-600">
                        <option value="all">All Types</option>
                        <option value="Sole Proprietorship">Sole Proprietorship</option>
                        <option value="Partnership">Partnership</option>
                        <option value="Private Limited">Private Limited</option>
                        <option value="Public Limited">Public Limited</option>
                    </select>
                </div>
                
                <button onclick="exportCSV()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold text-sm transition-colors flex items-center gap-2">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
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
        const statusFilter = document.getElementById('statusFilter');
        const typeFilter = document.getElementById('typeFilter');
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
            return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
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
                    avatarHtml = `<img src="../assets/uploads/profiles/${user.profile_image}" class="w-10 h-10 rounded-full object-cover shadow-sm">`;
                } else {
                    avatarHtml = `<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-black shadow-sm">${getInitials(user.first_name, user.last_name)}</div>`;
                }
                
                // Role pill
                let roleHtml = '';
                if(user.user_type === 'seller') {
                    const statusText = user.business_approved == 1 ? 'Approved' : (user.business_approved == 0 ? 'Pending' : 'Rejected');
                    const statusColor = user.business_approved == 1 ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                    const bType = user.business_type ? `<span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full font-semibold ml-2">${user.business_type}</span>` : '';
                    
                    roleHtml = `
                        <div class="font-bold text-navy">${user.business_name || 'Not Setup'}</div>
                        <div class="mt-1 flex items-center">
                            <span class="${statusColor} text-[10px] uppercase font-bold px-2 py-0.5 rounded-full">${statusText}</span>
                            ${bType}
                        </div>
                    `;
                } else {
                    roleHtml = `<span class="bg-blue-50 text-blue-600 font-bold px-3 py-1 rounded-full text-xs uppercase tracking-wider">Customer</span>`;
                }
                
                // Stats HTML
                let statsHtml = '';
                if(user.user_type === 'seller') {
                    statsHtml = `<div class="text-xs font-semibold text-gray-600">Products: <span class="text-navy">${user.total_products}</span> | Orders: <span class="text-navy">${user.seller_orders}</span> <br> Earned: <span class="text-green-600 font-bold">Rs.${Number(user.total_earned).toLocaleString()}</span></div>`;
                } else {
                    statsHtml = `<div class="text-xs font-semibold text-gray-600">Orders: <span class="text-navy">${user.buyer_orders}</span> <br> Spent: <span class="text-green-600 font-bold">Rs.${Number(user.total_spent).toLocaleString()}</span></div>`;
                }
                
                // Status HTML
                let statusHtml = '';
                if(user.is_approved == 1) {
                    statusHtml = `<div class="flex items-center gap-2"><span class="pulse-dot pulse-active"></span><span class="font-bold text-green-600 text-xs uppercase tracking-wider">Active</span></div>`;
                } else {
                    statusHtml = `<div class="flex items-center gap-2"><span class="pulse-dot pulse-suspended"></span><span class="font-bold text-yellow-600 text-xs uppercase tracking-wider">Suspended</span></div>`;
                }
                
                tr.innerHTML = `
                    <td class="p-4 pl-6"><input type="checkbox" class="custom-checkbox row-cb" value="${user.id}" ${isSelected ? 'checked' : ''}></td>
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            ${avatarHtml}
                            <div>
                                <div class="font-bold text-navy">${user.first_name || ''} ${user.last_name || ''}</div>
                                <div class="text-xs text-gray-400">@${user.username} <span class="mx-1">•</span> Joined ${formatDate(user.created_at)}</div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <div class="font-semibold text-gray-700 flex items-center gap-1"><a href="mailto:${user.email}" class="hover:text-primary transition-colors">${user.email}</a> <i class="fas fa-check-circle text-green-500 text-xs ml-1" title="Verified"></i></div>
                        <div class="text-xs text-gray-500 mt-1 cursor-pointer flex items-center gap-1" onclick="copyToClipboard('${user.phone || ''}')"><i class="fas fa-phone-alt"></i> ${user.phone || 'N/A'} <i class="far fa-copy opacity-0 group-hover:opacity-100 ml-1"></i></div>
                    </td>
                    <td class="p-4">${roleHtml}</td>
                    <td class="p-4">${statsHtml}</td>
                    <td class="p-4">${statusHtml}</td>
                    <td class="p-4 pr-6 text-right relative">
                        <button onclick="viewUser(${user.id})" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors mr-2">View</button>
                        
                        <div class="inline-block relative">
                            <button onclick="toggleDropdown(this)" class="w-8 h-8 rounded-lg hover:bg-gray-100 text-gray-500 flex items-center justify-center transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu absolute right-0 top-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl w-40 z-50 hidden text-left overflow-hidden">
                                <a href="#" class="block px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-user-circle w-5 text-gray-400"></i> Profile</a>
                                ${user.user_type === 'seller' ? `<a href="#" class="block px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fas fa-box w-5 text-gray-400"></i> Products</a>` : ''}
                                <a href="#" onclick="actionSingle('suspend', ${user.id}); return false;" class="block px-4 py-2 text-sm font-semibold text-yellow-600 hover:bg-yellow-50"><i class="fas fa-pause-circle w-5 text-yellow-400"></i> Suspend</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="#" onclick="actionSingle('delete', ${user.id}); return false;" class="block px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"><i class="fas fa-trash w-5 text-red-400"></i> Delete</a>
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
            const search = searchInput.value.toLowerCase();
            const status = statusFilter.value;
            const bType = typeFilter.value;
            
            filteredUsers = usersData.filter(user => {
                // Tab filter
                if(currentTab === 'seller' && user.user_type !== 'seller') return false;
                if(currentTab === 'customer' && user.user_type !== 'customer') return false;
                if(currentTab === 'suspended' && user.is_approved == 1) return false;
                if(currentTab === 'pending' && (user.user_type !== 'seller' || user.business_approved == 1)) return false;
                
                // Status Filter
                if(status === 'active' && user.is_approved == 0) return false;
                if(status === 'suspended' && user.is_approved == 1) return false;
                if(status === 'pending' && user.business_approved == 1) return false;
                
                // Type filter
                if(bType !== 'all' && user.business_type !== bType) return false;
                
                // Search
                if(search) {
                    const text = `${user.first_name} ${user.last_name} ${user.username} ${user.email} ${user.business_name || ''}`.toLowerCase();
                    if(!text.includes(search)) return false;
                }
                
                return true;
            });
            
            renderTable();
        }
        
        // Event Listeners for Filters
        if (searchInput) searchInput.addEventListener('keyup', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);
        if (typeFilter) typeFilter.addEventListener('change', applyFilters);
        
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
        
        // Dropdown toggle
        function toggleDropdown(btn) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
            const menu = btn.nextElementSibling;
            menu.classList.toggle('hidden');
            event.stopPropagation();
        }
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
        });
        
        // Modal Logic
        function viewUser(id) {
            const user = usersData.find(u => u.id == id);
            if(!user) return;
            
            const modal = document.getElementById('viewUserModal');
            const content = document.getElementById('modalContent');
            
            let avatarHtml = user.profile_image 
                ? `<img src="../assets/uploads/profiles/${user.profile_image}" class="w-24 h-24 rounded-2xl object-cover shadow-lg border-4 border-white mb-4">`
                : `<div class="w-24 h-24 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600 font-black text-4xl shadow-lg border-4 border-white mb-4">${getInitials(user.first_name, user.last_name)}</div>`;
                
            let leftHtml = `
                <div>
                    ${avatarHtml}
                    <h2 class="text-2xl font-black">${user.first_name} ${user.last_name}</h2>
                    <p class="text-gray-500 font-bold mb-4">@${user.username}</p>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3"><i class="fas fa-envelope text-gray-400 w-5"></i> <a href="mailto:${user.email}" class="font-semibold text-gray-700 hover:text-primary">${user.email}</a></div>
                        <div class="flex items-center gap-3"><i class="fas fa-phone text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">${user.phone || 'N/A'}</span></div>
                        <div class="flex items-center gap-3"><i class="fas fa-calendar-alt text-gray-400 w-5"></i> <span class="font-semibold text-gray-700">Joined ${formatDate(user.created_at)}</span></div>
                    </div>
                    
                    ${user.user_type === 'seller' ? `
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Business Details</h4>
                        <div class="font-bold text-navy">${user.business_name || 'N/A'}</div>
                        <div class="text-sm font-semibold text-gray-600">${user.business_type || ''}</div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            let rightHtml = `
                <div>
                    <h4 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Performance Stats</h4>
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="bg-blue-50 rounded-xl p-4">
                            <div class="text-xs font-bold text-blue-400 uppercase tracking-wider">${user.user_type === 'seller' ? 'Products' : 'Orders'}</div>
                            <div class="text-2xl font-black text-blue-700">${user.user_type === 'seller' ? user.total_products : user.buyer_orders}</div>
                        </div>
                        <div class="bg-green-50 rounded-xl p-4">
                            <div class="text-xs font-bold text-green-500 uppercase tracking-wider">${user.user_type === 'seller' ? 'Revenue' : 'Spent'}</div>
                            <div class="text-xl font-black text-green-700">Rs.${Number(user.user_type === 'seller' ? user.total_earned : user.total_spent).toLocaleString()}</div>
                        </div>
                    </div>
                    
                    <h4 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Recent Activity Log</h4>
                    <div class="border-l-2 border-gray-100 ml-2 pl-4 py-2 space-y-4 mb-8">
                        <div class="relative">
                            <div class="absolute -left-[21px] top-1 w-3 h-3 rounded-full bg-primary ring-4 ring-white"></div>
                            <p class="text-sm font-semibold text-navy">Account Created</p>
                            <p class="text-xs font-bold text-gray-400">${formatDate(user.created_at)}</p>
                        </div>
                        <!-- Placeholder for more logs -->
                    </div>
                    
                    <div class="flex gap-2">
                        <button onclick="actionSingle('suspend', ${user.id})" class="flex-1 bg-yellow-50 hover:bg-yellow-100 text-yellow-600 font-bold py-3 rounded-xl transition-colors">Suspend User</button>
                        <button onclick="actionSingle('delete', ${user.id})" class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-bold py-3 rounded-xl transition-colors">Delete User</button>
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
        
        function exportCSV() {
            // Simple CSV export of visible rows
            let csv = "ID,Name,Username,Email,Phone,Role,Status\n";
            filteredUsers.forEach(u => {
                let role = u.user_type === 'seller' ? `Seller (${u.business_name || 'No Biz'})` : 'Customer';
                let status = u.is_approved == 1 ? 'Active' : 'Suspended';
                csv += `${u.id},"${u.first_name} ${u.last_name}",${u.username},${u.email},${u.phone},"${role}",${status}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'oxxa_users_export.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        function toggleDropdown(btn) {
            const menu = btn.nextElementSibling;
            const isHidden = menu.classList.contains('hidden');
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
            
            if (isHidden) {
                menu.classList.remove('hidden');
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-menu') && !e.target.closest('button[onclick="toggleDropdown(this)"]')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
            }
        });
        
        // Initial render
        renderTable();

    </script>
</body>
</html>