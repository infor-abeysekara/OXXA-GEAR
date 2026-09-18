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
    AND id NOT IN (SELECT review_id FROM review_replies)
");
$pendingStmt->execute([$seller_id]);
$pending_replies = $pendingStmt->fetchColumn();

// 2. Get Reviews
$reviewsStmt = $pdo->prepare("
    SELECT r.*, 
           p.name as product_name, p.image as product_image, 
           u.first_name as customer_name,
           (SELECT reply_text FROM review_replies rep WHERE rep.review_id = r.id LIMIT 1) as seller_reply
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.user_id = u.id
    WHERE r.seller_id = ? AND r.status != 'hidden'
    ORDER BY r.created_at DESC
");
$reviewsStmt->execute([$seller_id]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-black text-navy uppercase tracking-wide">My Reviews</h2>
            <p class="text-slate text-sm mt-1">Manage customer feedback and build trust.</p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Total Reviews</p>
                <h3 class="text-xl font-black text-navy"><?= number_format($stats['total_reviews']) ?></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0066FF] flex items-center justify-center text-lg">
                <i class="fas fa-comment-alt"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Average Rating</p>
                <h3 class="text-xl font-black text-yellow-500"><?= number_format($stats['avg_rating'], 1) ?> <i class="fas fa-star text-sm"></i></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-yellow-50 text-yellow-500 flex items-center justify-center text-lg">
                <i class="fas fa-star-half-alt"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border <?= $pending_replies > 0 ? 'border-red-200 bg-red-50' : 'border-gray-100' ?> flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold <?= $pending_replies > 0 ? 'text-red-500' : 'text-gray-400' ?> uppercase tracking-wide mb-1">Pending Replies</p>
                <h3 class="text-xl font-black <?= $pending_replies > 0 ? 'text-red-600' : 'text-navy' ?>"><?= number_format($pending_replies) ?></h3>
            </div>
            <div class="w-10 h-10 rounded-xl <?= $pending_replies > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400' ?> flex items-center justify-center text-lg">
                <i class="fas fa-reply"></i>
            </div>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-black text-navy uppercase tracking-wide text-sm">Customer Reviews</h3>
        </div>
        
        <?php if(count($reviews) == 0): ?>
            <div class="p-10 text-center text-slate">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300 text-2xl">
                    <i class="fas fa-comment-slash"></i>
                </div>
                <p>No reviews yet.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach($reviews as $r): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex gap-4">
                            <!-- Product Image -->
                            <div class="w-16 h-16 shrink-0 bg-white rounded-xl border border-gray-100 p-1 flex items-center justify-center overflow-hidden">
                                <img src="<?= strpos($r['product_image'], 'http') === 0 ? $r['product_image'] : '../' . $r['product_image'] ?>" class="w-full h-full object-contain mix-blend-multiply">
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <a href="product-details.php?id=<?= $r['product_id'] ?>" class="text-xs font-black text-navy hover:text-[#0066FF] transition-colors truncate block max-w-sm" target="_blank">
                                            <?= htmlspecialchars($r['product_name']) ?>
                                        </a>
                                        <div class="text-xs text-slate mt-1 flex items-center gap-2">
                                            <span class="font-bold text-navy"><?= htmlspecialchars($r['customer_name']) ?></span>
                                            <span class="text-gray-300">|</span>
                                            <span><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                                            <?php if($r['is_verified_purchase']): ?>
                                                <span class="text-gray-300">|</span>
                                                <span class="text-[9px] font-bold text-green-600 bg-green-100 px-1.5 py-0.5 rounded"><i class="fas fa-check-circle"></i> VERIFIED</span>
                                            <?php endif; ?>
                                            <?php if($r['status'] == 'reported'): ?>
                                                <span class="text-[9px] font-bold text-red-600 bg-red-100 px-1.5 py-0.5 rounded"><i class="fas fa-flag"></i> REPORTED</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-yellow-500 text-xs">
                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $r['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                    </div>
                                </div>
                                
                                <p class="text-sm text-slate mt-3 italic">"<?= nl2br(htmlspecialchars($r['comment'])) ?>"</p>
                                
                                <?php if($r['seller_reply']): ?>
                                    <!-- Existing Reply -->
                                    <div class="mt-4 bg-blue-50 p-4 rounded-xl border border-blue-100">
                                        <p class="text-[10px] font-black text-[#0066FF] uppercase mb-1">Your Reply</p>
                                        <p class="text-sm text-navy"><?= nl2br(htmlspecialchars($r['seller_reply'])) ?></p>
                                    </div>
                                <?php else: ?>
                                    <!-- Reply Area -->
                                    <div class="mt-4 hidden" id="reply-box-<?= $r['id'] ?>">
                                        <textarea id="reply-text-<?= $r['id'] ?>" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#0066FF] focus:ring focus:ring-blue-200 focus:ring-opacity-50" rows="3" placeholder="Write your reply... (This will be visible on the product page)"></textarea>
                                        <div class="flex justify-end gap-2 mt-2">
                                            <button onclick="toggleReplyBox(<?= $r['id'] ?>)" class="px-3 py-1.5 text-xs font-bold text-gray-500 hover:text-navy transition-colors">Cancel</button>
                                            <button onclick="submitReply(<?= $r['id'] ?>)" class="px-3 py-1.5 text-xs font-bold bg-[#0066FF] text-white rounded-lg shadow hover:bg-blue-700 transition-colors">Post Reply</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Actions -->
                                <div class="mt-4 flex gap-3 border-t border-gray-100 pt-3">
                                    <?php if(!$r['seller_reply']): ?>
                                        <button onclick="toggleReplyBox(<?= $r['id'] ?>)" class="text-xs font-bold text-[#0066FF] hover:underline"><i class="fas fa-reply me-1"></i> Reply</button>
                                    <?php endif; ?>
                                    <?php if($r['status'] != 'reported'): ?>
                                        <button onclick="openReportModal(<?= $r['id'] ?>)" class="text-xs font-bold text-gray-400 hover:text-red-500 transition-colors"><i class="fas fa-flag me-1"></i> Report</button>
                                    <?php endif; ?>
                                    <a href="product-details.php?id=<?= $r['product_id'] ?>" target="_blank" class="text-xs font-bold text-gray-400 hover:text-navy transition-colors"><i class="fas fa-external-link-alt me-1"></i> View Product</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" aria-hidden="true" onclick="closeReportModal()"></div>
        <div class="relative inline-block bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-flag text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-black text-navy uppercase" id="modal-title">Report Review</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 mb-4">Are you sure this review violates our policies? (e.g., Spam, Abusive language, Fake review)</p>
                            <input type="hidden" id="report-review-id">
                            <select id="report-reason" class="w-full text-sm rounded-xl border-gray-300 focus:border-[#0066FF] focus:ring focus:ring-blue-200">
                                <option value="Fake / Spam">Fake / Spam</option>
                                <option value="Abusive Language">Abusive Language</option>
                                <option value="Unrelated to Product">Unrelated to Product</option>
                                <option value="Competitor Sabotage">Competitor Sabotage</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-100">
                <button type="button" onclick="submitReport()" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Submit Report</button>
                <button type="button" onclick="closeReportModal()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleReplyBox(id) {
    const box = document.getElementById('reply-box-' + id);
    if(box.classList.contains('hidden')) {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
}

function submitReply(id) {
    const text = document.getElementById('reply-text-' + id).value.trim();
    if(!text) return showToast('Please enter a reply', 'warning');
    
    fetch('../Backend/seller-reviews-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reply&review_id=${id}&reply_text=${encodeURIComponent(text)}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showToast('Reply posted successfully');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function openReportModal(id) {
    document.getElementById('report-review-id').value = id;
    document.getElementById('reportModal').classList.remove('hidden');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
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
            showToast('Review reported to Admin', 'info');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>
