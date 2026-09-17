<?php
$page_title = 'Notifications - OXXA GEAR';
include('../include/header.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['userid'];

// Process Actions
if (isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    
    if ($action === 'mark_read' && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        $updateQuery = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("is", $notification_id, $user_id);
        $stmt->execute();
    } elseif ($action === 'mark_all_read') {
        $updateQuery = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
    } elseif ($action === 'delete' && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        $deleteQuery = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("is", $notification_id, $user_id);
        $stmt->execute();
    }
    
    // Redirect to avoid form resubmission
    header('Location: notifications.php');
    exit();
}

// Get notifications
$notificationsQuery = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100";
$stmt = $conn->prepare($notificationsQuery);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$notificationsResult = $stmt->get_result();

$notifications = [];
$counts = [
    'All' => 0,
    'Orders' => 0,
    'Business' => 0,
    'Payouts' => 0,
    'Reviews' => 0,
    'System' => 0
];
$unread_count = 0;

while ($row = $notificationsResult->fetch_assoc()) {
    $notifications[] = $row;
    
    // Default empty or null category to System
    $cat = empty($row['category']) ? 'System' : $row['category'];
    
    $counts['All']++;
    if (isset($counts[$cat])) {
        $counts[$cat]++;
    } else {
        $counts['System']++;
    }
    
    if ($row['is_read'] == 0) {
        $unread_count++;
    }
}
?>

<div class="bg-[#F8F9FA] min-h-screen py-6 lg:py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black text-black font-space flex items-center gap-3">
                    Inbox
                    <?php if ($unread_count > 0): ?>
                        <span class="bg-primary text-white text-xs px-2.5 py-1 rounded-full font-bold"><?php echo $unread_count; ?> new</span>
                    <?php endif; ?>
                </h1>
                <p class="text-gray-500 text-sm mt-1 font-medium">Manage your updates, alerts, and account activity.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if ($counts['All'] > 0): ?>
                <form method="POST" class="m-0">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="text-sm font-semibold text-gray-600 hover:text-black transition-colors px-4 py-2 rounded-full hover:bg-gray-200">
                        <i class="fas fa-check-double me-2"></i>Mark all as read
                    </button>
                </form>
                <?php endif; ?>
                <button class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="flex overflow-x-auto hide-scrollbar border-b border-gray-100">
                <button onclick="filterNotifications('All')" class="tab-btn active shrink-0 px-6 py-4 text-sm font-bold border-b-2 border-black text-black transition-colors whitespace-nowrap" data-target="All">
                    All <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $counts['All']; ?></span>
                </button>
                <button onclick="filterNotifications('Orders')" class="tab-btn shrink-0 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-black transition-colors whitespace-nowrap" data-target="Orders">
                    Orders <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $counts['Orders']; ?></span>
                </button>
                <?php if(isset($_SESSION['type']) && $_SESSION['type'] == 'seller'): ?>
                <button onclick="filterNotifications('Business')" class="tab-btn shrink-0 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-black transition-colors whitespace-nowrap" data-target="Business">
                    Business <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $counts['Business']; ?></span>
                </button>
                <button onclick="filterNotifications('Payouts')" class="tab-btn shrink-0 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-black transition-colors whitespace-nowrap" data-target="Payouts">
                    Payouts <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $counts['Payouts']; ?></span>
                </button>
                <?php endif; ?>
                <button onclick="filterNotifications('Reviews')" class="tab-btn shrink-0 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-black transition-colors whitespace-nowrap" data-target="Reviews">
                    Reviews <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $counts['Reviews']; ?></span>
                </button>
                <button onclick="filterNotifications('System')" class="tab-btn shrink-0 px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-black transition-colors whitespace-nowrap" data-target="System">
                    System <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs ml-1"><?php echo $counts['System']; ?></span>
                </button>
            </div>
            
            <!-- Notifications List -->
            <div id="notificationsContainer" class="divide-y divide-gray-100">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): 
                        $category = empty($notif['category']) ? 'System' : $notif['category'];
                        
                        // Icon mapping
                        $iconClass = 'fa-info text-blue-500';
                        $iconBg = 'bg-blue-100';
                        if ($notif['type'] === 'success') {
                            $iconClass = 'fa-check text-green-600';
                            $iconBg = 'bg-green-100';
                        } elseif ($notif['type'] === 'warning') {
                            $iconClass = 'fa-exclamation text-orange-500';
                            $iconBg = 'bg-orange-100';
                        } elseif ($notif['type'] === 'error') {
                            $iconClass = 'fa-times text-red-500';
                            $iconBg = 'bg-red-100';
                        } elseif ($notif['type'] === 'order') {
                            $iconClass = 'fa-shopping-bag text-primary';
                            $iconBg = 'bg-blue-100';
                        }

                        $isUnread = $notif['is_read'] == 0;
                    ?>
                        <div class="notification-item flex gap-4 p-5 hover:bg-gray-50 transition-all <?php echo $isUnread ? 'bg-[#F0F7FF]' : 'bg-white'; ?> group" data-category="<?php echo $category; ?>">
                            
                            <!-- Unread Dot -->
                            <div class="w-2 flex shrink-0 pt-3">
                                <?php if ($isUnread): ?>
                                    <div class="w-2 h-2 rounded-full bg-primary mt-1"></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Icon Circle -->
                            <div class="w-12 h-12 rounded-full <?php echo $iconBg; ?> flex items-center justify-center shrink-0">
                                <i class="fas <?php echo $iconClass; ?> text-xl"></i>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-grow min-w-0">
                                <div class="flex justify-between items-start gap-4">
                                    <div>
                                        <!-- Extract Title from Message if possible, otherwise use category as title -->
                                        <?php 
                                            $parts = explode('!', $notif['message'], 2);
                                            $title = $category . " Update";
                                            $desc = $notif['message'];
                                            if (count($parts) == 2 && strlen($parts[0]) < 50) {
                                                $title = $parts[0] . '!';
                                                $desc = trim($parts[1]);
                                            } else {
                                                // Try looking for ' - ' separator
                                                $parts = explode(' - ', $notif['message'], 2);
                                                if (count($parts) == 2 && strlen($parts[0]) < 50) {
                                                    $title = $parts[0];
                                                    $desc = trim($parts[1]);
                                                }
                                            }
                                        ?>
                                        <h4 class="text-sm font-bold text-gray-900 <?php echo $isUnread ? 'text-black' : ''; ?>">
                                            <?php echo htmlspecialchars($title); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                                            <?php echo htmlspecialchars($desc); ?>
                                        </p>
                                        
                                        <!-- Action Button -->
                                        <?php if (!empty($notif['action_url'])): ?>
                                            <a href="<?php echo $base_path . $notif['action_url']; ?>" class="inline-flex mt-3 text-xs font-bold text-primary bg-blue-50 px-4 py-1.5 rounded-full hover:bg-blue-100 transition-colors uppercase tracking-wide">
                                                View Details <i class="fas fa-arrow-right ml-2 mt-0.5"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <div class="flex items-center gap-3 mt-3">
                                            <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide"><?php echo date('M j, Y • g:i A', strtotime($notif['created_at'])); ?></span>
                                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                            <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide"><?php echo $category; ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions Menu -->
                                    <div class="shrink-0 relative opacity-0 group-hover:opacity-100 transition-opacity lg:block hidden">
                                        <div class="dropdown">
                                            <button class="w-8 h-8 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-xl border-0 py-2 rounded-xl text-sm">
                                                <?php if ($isUnread): ?>
                                                <li>
                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                                        <button type="submit" class="dropdown-item py-2 px-4 hover:bg-gray-50 font-medium">
                                                            <i class="fas fa-check me-2 text-gray-400"></i> Mark as read
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                                        <button type="submit" class="dropdown-item py-2 px-4 hover:bg-red-50 text-red-600 font-medium">
                                                            <i class="far fa-trash-alt me-2"></i> Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile Swipe Indicator (Visual only for now) -->
                                    <div class="lg:hidden shrink-0 text-gray-300">
                                        <i class="fas fa-chevron-left text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Empty States -->
                <div id="emptyStateContainer" class="py-16 px-6 text-center <?php echo count($notifications) > 0 ? 'hidden' : ''; ?>">
                    <div id="empty-icon" class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="far fa-bell-slash text-4xl text-gray-300"></i>
                    </div>
                    <h3 id="empty-title" class="text-xl font-bold text-gray-900 mb-2 font-space">All caught up! 🎉</h3>
                    <p id="empty-desc" class="text-gray-500 max-w-sm mx-auto mb-6">You'll get updates about orders, business, and payouts here.</p>
                    <a href="<?php echo $base_path; ?>site/shop.php" class="inline-flex items-center justify-center px-6 py-3 bg-black text-white rounded-full font-bold text-sm uppercase tracking-wider hover:bg-primary transition-colors">
                        Explore Products
                    </a>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
/* Hide scrollbar for tabs */
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<script>
function filterNotifications(category) {
    // Update tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.target === category) {
            btn.classList.remove('border-transparent', 'text-gray-500');
            btn.classList.add('border-black', 'text-black', 'active');
        } else {
            btn.classList.add('border-transparent', 'text-gray-500');
            btn.classList.remove('border-black', 'text-black', 'active');
        }
    });

    // Update items
    const items = document.querySelectorAll('.notification-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        if (category === 'All' || item.dataset.category === category) {
            item.classList.remove('hidden');
            visibleCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    // Handle Empty State
    const emptyState = document.getElementById('emptyStateContainer');
    const emptyTitle = document.getElementById('empty-title');
    const emptyDesc = document.getElementById('empty-desc');
    const emptyIcon = document.getElementById('empty-icon');

    if (visibleCount === 0) {
        emptyState.classList.remove('hidden');
        
        switch(category) {
            case 'Orders':
                emptyIcon.innerHTML = '<i class="fas fa-box-open text-4xl text-gray-300"></i>';
                emptyTitle.textContent = 'No order updates yet';
                emptyDesc.textContent = 'When you buy or sell items, tracking and status updates will appear here.';
                break;
            case 'Business':
                emptyIcon.innerHTML = '<i class="far fa-building text-4xl text-gray-300"></i>';
                emptyTitle.textContent = 'No business updates';
                emptyDesc.textContent = 'Updates regarding your seller account and product approvals will show up here.';
                break;
            case 'Payouts':
                emptyIcon.innerHTML = '<i class="fas fa-money-check-alt text-4xl text-gray-300"></i>';
                emptyTitle.textContent = 'No payouts yet';
                emptyDesc.textContent = 'Track your earnings and withdrawals in this tab.';
                break;
            case 'Reviews':
                emptyIcon.innerHTML = '<i class="far fa-star text-4xl text-gray-300"></i>';
                emptyTitle.textContent = 'No reviews yet';
                emptyDesc.textContent = 'See what customers are saying about your products here.';
                break;
            default:
                emptyIcon.innerHTML = '<i class="far fa-bell-slash text-4xl text-gray-300"></i>';
                emptyTitle.textContent = 'All caught up! 🎉';
                emptyDesc.textContent = "You'll get updates about orders, business, and payouts here.";
        }
    } else {
        emptyState.classList.add('hidden');
    }
}
</script>

<?php include("../include/footer.php"); ?>