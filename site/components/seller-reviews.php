<?php
// site/components/seller-reviews.php
$seller_id = $_SESSION['userid'];

// 1. Get Stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(id) as total_reviews, 
        COALESCE(AVG(rating), 0) as avg_rating 
    FROM reviews 
    WHERE seller_id = ? AND status != 'hidden'
");
$statsStmt->execute([$seller_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$pendingStmt = $pdo->prepare("
    SELECT COUNT(id) FROM reviews 
    WHERE seller_id = ? AND status != 'hidden' 
    AND reply_status = 'pending'
");
$pendingStmt->execute([$seller_id]);
$pending_replies = $pendingStmt->fetchColumn();

// Pagination and Filters Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'r.created_at';
$dir = $_GET['dir'] ?? 'DESC';

$allowed_sorts = ['r.created_at', 'r.rating', 'p.name'];
$allowed_dirs = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sorts)) $sort = 'r.created_at';
if (!in_array(strtoupper($dir), $allowed_dirs)) $dir = 'DESC';

// Build Query
$params = [$seller_id];
$where = "WHERE r.seller_id = ? AND r.status != 'hidden'";

if (!empty($search)) {
    $where .= " AND (p.pname LIKE ? OR u.first_name LIKE ? OR r.review_text LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== 'all') {
    if ($status === 'pending') {
        $where .= " AND r.reply_status = 'pending'";
    } else if ($status === 'replied') {
        $where .= " AND r.reply_status = 'replied'";
    } else if ($status === 'reported') {
        $where .= " AND r.status = 'reported'";
    }
}

// Count total
$countQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.user_id = u.id
    $where
");
$countQuery->execute($params);
$total_reviews = $countQuery->fetchColumn();
$total_pages = ceil($total_reviews / $limit);

// 2. Get Reviews
$sql = "
    SELECT r.*, 
           p.pname as product_name, (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as product_image, 
           CONCAT(u.first_name, ' ', u.last_name) as customer_name
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.user_id = u.id
    $where
    ORDER BY $sort $dir
    LIMIT ? OFFSET ?
";
$reviewsStmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $param) {
    $reviewsStmt->bindValue($paramIndex++, $param);
}
$reviewsStmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$reviewsStmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$reviewsStmt->execute();
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $url = "?tab=reviews$searchQuery&status=".urlencode($status)."&sort=".urlencode($column)."&dir=$new_dir";
        return "<a href=\"$url\" class=\"hover:text-black transition-colors\">$label $icon</a>";
    }
}
?>

<!-- Load Export Utilities -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="js/export-utils.js"></script>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">My Reviews</h2>
            <p class="text-gray-500 text-sm mt-1 font-bold">Manage customer feedback and build trust.</p>
        </div>
        
        <!-- Export Buttons -->
        <div class="flex gap-2 border-l border-gray-200 pl-4 ml-2">
            <button type="button" onclick="openExportModal('reviews', 'Reviews', 'csv', [{value: 'pending', label: 'Pending Reply'}, {value: 'replied', label: 'Replied'}, {value: 'reported', label: 'Reported'}])" class="h-10 px-4 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl text-sm font-bold text-gray-700 transition-colors flex items-center gap-2 shadow-sm">
                <i class="fas fa-file-csv text-gray-400"></i> Export CSV
            </button>
            <button type="button" onclick="openExportModal('reviews', 'Reviews', 'pdf', [{value: 'pending', label: 'Pending Reply'}, {value: 'replied', label: 'Replied'}, {value: 'reported', label: 'Reported'}])" class="h-10 px-4 bg-black text-white hover:bg-gray-800 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-sm shadow-black/10">
                <i class="fas fa-file-pdf text-red-400"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Reviews</p>
                <h3 class="text-3xl font-black text-navy"><?= number_format($stats['total_reviews']) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-2xl border border-blue-100">
                <i class="fas fa-comment-alt"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Average Rating</p>
                <h3 class="text-3xl font-black text-yellow-500"><?= number_format($stats['avg_rating'], 1) ?> <i class="fas fa-star text-lg"></i></h3>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-yellow-50 text-yellow-500 flex items-center justify-center text-2xl border border-yellow-100">
                <i class="fas fa-star-half-alt"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border <?= $pending_replies > 0 ? 'border-red-200 bg-red-50/30' : 'border-gray-100' ?> flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold <?= $pending_replies > 0 ? 'text-red-500' : 'text-gray-400' ?> uppercase tracking-widest mb-1">Pending Replies</p>
                <h3 class="text-3xl font-black <?= $pending_replies > 0 ? 'text-red-600' : 'text-navy' ?>"><?= number_format($pending_replies) ?></h3>
            </div>
            <div class="w-14 h-14 rounded-2xl <?= $pending_replies > 0 ? 'bg-red-100 text-red-600 border border-red-200' : 'bg-gray-100 text-gray-400 border border-gray-200' ?> flex items-center justify-center text-2xl">
                <i class="fas fa-reply"></i>
            </div>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#F1F5F9] overflow-hidden">
        <div class="p-6 border-b border-[#F1F5F9] bg-[#F8FAFC]">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <h3 class="font-black text-navy uppercase tracking-wide text-sm">Customer Reviews</h3>
                
                <form action="" method="GET" class="flex flex-col sm:flex-row gap-2">
                    <input type="hidden" name="tab" value="reviews">
                    
                    <div class="relative w-full sm:w-64">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search reviews..." 
                               class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                    </div>
                    
                    <select name="status" onchange="this.form.submit()" class="bg-white border border-gray-200 text-sm font-bold text-navy rounded-xl px-4 py-2 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all cursor-pointer">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Reviews</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Reply</option>
                        <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                        <option value="reported" <?= $status === 'reported' ? 'selected' : '' ?>>Reported</option>
                    </select>
                </form>
            </div>
        </div>
        
        <div class="px-6 py-3 bg-white border-b border-[#F1F5F9] text-xs font-bold text-gray-400 flex justify-between items-center">
            <span>Showing <?= min($offset + 1, $total_reviews) ?>-<?= min($offset + $limit, $total_reviews) ?> of <?= $total_reviews ?> reviews</span>
            
            <div class="flex gap-4">
                <?= sortLink('r.created_at', 'Date', $sort, $dir, $search, $status) ?>
                <?= sortLink('r.rating', 'Rating', $sort, $dir, $search, $status) ?>
            </div>
        </div>
        
        <?php if(count($reviews) == 0): ?>
            <div class="p-12 text-center text-slate">
                <img src="../image/empty-reviews.svg" onerror="this.src='https://illustrations.popsy.co/gray/crashed-error.svg'" class="w-48 h-48 mx-auto mb-4 opacity-50">
                <h3 class="text-lg font-black text-navy mb-1">No Reviews Found</h3>
                <p class="text-gray-500">There are no reviews matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-[#F1F5F9]">
                <?php foreach($reviews as $r): ?>
                    <div class="p-6 hover:bg-[#F8FAFC] transition-colors">
                        <div class="flex flex-col sm:flex-row gap-5">
                            <!-- Product Image -->
                            <div class="w-full sm:w-24 h-24 shrink-0 bg-white rounded-xl border border-gray-100 p-2 flex items-center justify-center overflow-hidden shadow-sm">
                                <?php $imageSrc = $r['product_image'] ? "../assets/uploads/products/" . $r['product_image'] : "../image/no-image.jpg"; ?>
                                <img src="<?= htmlspecialchars($imageSrc) ?>" class="w-full h-full object-contain">
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 sm:gap-0">
                                    <div>
                                        <a href="product-details.php?id=<?= $r['product_id'] ?>" class="text-sm font-black text-navy hover:text-[#0066FF] transition-colors line-clamp-1" target="_blank">
                                            <?= htmlspecialchars($r['product_name']) ?>
                                        </a>
                                        <div class="text-xs text-slate mt-1 flex flex-wrap items-center gap-2">
                                            <span class="font-bold text-gray-600"><?= htmlspecialchars($r['customer_name']) ?></span>
                                            <span class="text-gray-300">|</span>
                                            <span class="font-medium"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                            
                                            <?php if($r['status'] == 'reported'): ?>
                                                <span class="text-[9px] font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded ml-1"><i class="fas fa-flag"></i> REPORTED</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-yellow-500 text-sm flex gap-1 bg-yellow-50 px-2 py-1 rounded-lg border border-yellow-100 w-fit">
                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $r['rating']) ? '<i class="fas fa-star drop-shadow-sm"></i>' : '<i class="far fa-star text-yellow-300"></i>'; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-3 bg-white p-4 rounded-xl border border-gray-100 shadow-sm relative">
                                    <div class="absolute -top-2 left-6 text-gray-100">
                                        <i class="fas fa-quote-left text-2xl"></i>
                                    </div>
                                    <p class="text-sm text-gray-700 relative z-10 leading-relaxed">"<?= nl2br(htmlspecialchars($r['review_text'])) ?>"</p>
                                </div>
                                
                                <?php if($r['seller_reply']): ?>
                                    <!-- Existing Reply -->
                                    <div class="mt-3 bg-[#F8FAFC] p-4 rounded-xl border border-blue-100 ml-0 sm:ml-8 relative">
                                        <div class="absolute -left-3 top-4 text-blue-200 hidden sm:block">
                                            <i class="fas fa-reply fa-flip-horizontal"></i>
                                        </div>
                                        <div class="flex justify-between items-center mb-1">
                                            <p class="text-[10px] font-black text-[#0066FF] uppercase tracking-wider">Your Reply</p>
                                            <span class="text-[10px] text-gray-400 font-bold"><i class="fas fa-check-circle text-green-500"></i> Published</span>
                                        </div>
                                        <p class="text-sm text-navy"><?= nl2br(htmlspecialchars($r['seller_reply'])) ?></p>
                                    </div>
                                <?php else: ?>
                                    <!-- Reply Area -->
                                    <div class="mt-3 hidden ml-0 sm:ml-8" id="reply-box-<?= $r['id'] ?>">
                                        <div class="relative">
                                            <textarea id="reply-text-<?= $r['id'] ?>" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#0066FF] focus:ring-2 focus:ring-[#0066FF]/20 transition-all shadow-sm" rows="3" placeholder="Write your reply... (This will be visible to everyone on the product page)"></textarea>
                                        </div>
                                        <div class="flex justify-end gap-2 mt-2">
                                            <button onclick="toggleReplyBox(<?= $r['id'] ?>)" class="px-4 py-2 text-xs font-bold text-gray-500 hover:text-navy hover:bg-gray-100 rounded-lg transition-colors">Cancel</button>
                                            <button onclick="submitReply(<?= $r['id'] ?>)" class="px-4 py-2 text-xs font-bold bg-[#0066FF] text-white rounded-lg shadow-md shadow-blue-500/20 hover:bg-blue-700 hover:shadow-lg transition-all flex items-center gap-2">
                                                <i class="fas fa-paper-plane"></i> Post Reply
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Actions -->
                                <div class="mt-4 flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                                    <?php if(!$r['seller_reply']): ?>
                                        <button onclick="toggleReplyBox(<?= $r['id'] ?>)" class="px-3 py-1.5 rounded-lg bg-blue-50 text-xs font-bold text-[#0066FF] hover:bg-[#0066FF] hover:text-white transition-colors flex items-center gap-1.5">
                                            <i class="fas fa-reply"></i> Reply
                                        </button>
                                    <?php endif; ?>
                                    <?php if($r['status'] != 'reported'): ?>
                                        <button onclick="openReportModal(<?= $r['id'] ?>)" class="px-3 py-1.5 rounded-lg bg-gray-50 text-xs font-bold text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors flex items-center gap-1.5">
                                            <i class="fas fa-flag"></i> Report
                                        </button>
                                    <?php endif; ?>
                                    <a href="product-details.php?id=<?= $r['product_id'] ?>" target="_blank" class="px-3 py-1.5 rounded-lg bg-gray-50 text-xs font-bold text-gray-500 hover:bg-gray-200 hover:text-navy transition-colors flex items-center gap-1.5">
                                        <i class="fas fa-external-link-alt"></i> View Product
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="p-4 border-t border-[#F1F5F9] bg-white flex justify-center">
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?tab=reviews&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-left text-xs"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?tab=reviews&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $i ?>" 
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors <?= $i === $page ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?tab=reviews&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-right text-xs"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeReportModal()"></div>
    <div class="bg-white rounded-3xl w-full max-w-md relative z-10 shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300" id="reportModalContent">
        <div class="bg-red-50 p-6 flex flex-col items-center border-b border-red-100">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-red-500 text-2xl shadow-sm mb-3">
                <i class="fas fa-flag"></i>
            </div>
            <h3 class="text-xl font-black text-navy uppercase tracking-wide">Report Review</h3>
            <p class="text-sm text-gray-500 text-center mt-2 font-medium">Please provide a reason for reporting this review to our moderation team.</p>
        </div>
        
        <div class="p-6">
            <input type="hidden" id="report-review-id">
            
            <div class="space-y-4">
                <label class="block">
                    <span class="block text-xs font-black text-navy uppercase tracking-widest mb-2">Reason</span>
                    <select id="report-reason" class="w-full text-sm rounded-xl border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 p-3 bg-gray-50 font-medium">
                        <option value="Fake / Spam">Fake / Spam Account</option>
                        <option value="Abusive Language">Abusive / Inappropriate Language</option>
                        <option value="Unrelated to Product">Unrelated to Product</option>
                        <option value="Competitor Sabotage">Competitor Sabotage</option>
                    </select>
                </label>
            </div>
            
            <div class="mt-8 flex gap-3">
                <button type="button" onclick="closeReportModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition-colors">Cancel</button>
                <button type="button" onclick="submitReport()" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-red-500/30">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleReplyBox(id) {
    const box = document.getElementById('reply-box-' + id);
    if(box.classList.contains('hidden')) {
        // Hide all other open boxes first
        document.querySelectorAll('[id^="reply-box-"]').forEach(b => {
            if(!b.classList.contains('hidden')) b.classList.add('hidden');
        });
        box.classList.remove('hidden');
        setTimeout(() => document.getElementById('reply-text-' + id).focus(), 50);
    } else {
        box.classList.add('hidden');
    }
}

function submitReply(id) {
    const text = document.getElementById('reply-text-' + id).value.trim();
    if(!text) {
        alert('Please enter a reply');
        return;
    }
    
    fetch('../Backend/seller-reviews-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reply&review_id=${id}&reply_text=${encodeURIComponent(text)}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error posting reply');
        }
    })
    .catch(err => {
        console.error(err);
        alert('A network error occurred. Please try again.');
    });
}

function openReportModal(id) {
    document.getElementById('report-review-id').value = id;
    const modal = document.getElementById('reportModal');
    const content = document.getElementById('reportModalContent');
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    setTimeout(() => {
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
    }, 10);
}

function closeReportModal() {
    const modal = document.getElementById('reportModal');
    const content = document.getElementById('reportModalContent');
    
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}

function submitReport() {
    const id = document.getElementById('report-review-id').value;
    const reason = document.getElementById('report-reason').value;
    
    fetch('../Backend/seller-reviews-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=report&review_id=${id}&reason=${encodeURIComponent(reason)}`
    })
    .then(res => res.json())
    .then(data => {
        closeReportModal();
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error reporting review');
        }
    })
    .catch(err => {
        console.error(err);
        closeReportModal();
        alert('A network error occurred. Please try again.');
    });
}
</script>
