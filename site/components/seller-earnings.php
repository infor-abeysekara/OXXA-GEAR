<?php
// Fetch wallet info
$walletStmt = $pdo->prepare("SELECT * FROM seller_wallets WHERE seller_id = ?");
$walletStmt->execute([$_SESSION['userid']]);
$wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

$pending_balance = $wallet['pending_balance'] ?? 0;
$locked_balance = $wallet['locked_balance'] ?? 0;
$paid_balance = $wallet['paid_balance'] ?? 0;
$total_earnings = $wallet['total_earnings'] ?? 0;

// Check if any pending withdrawal exists
$checkReqStmt = $pdo->prepare("SELECT id FROM withdrawal_requests WHERE seller_id = ? AND status = 'Pending'");
$checkReqStmt->execute([$_SESSION['userid']]);
$has_pending_request = $checkReqStmt->fetchColumn() ? true : false;

// Pagination and Filters Setup for Withdrawals
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'id';
$dir = $_GET['dir'] ?? 'DESC';

$allowed_sorts = ['amount', 'created_at', 'status', 'id'];
$allowed_dirs = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sorts)) $sort = 'id';
if (!in_array(strtoupper($dir), $allowed_dirs)) $dir = 'DESC';

// Build Query
$params = [$_SESSION['userid']];
$where = "WHERE seller_id = ?";

if ($status !== 'all') {
    $where .= " AND status = ?";
    $params[] = ucfirst($status);
}

// Count total
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests $where");
$countQuery->execute($params);
$total_requests = $countQuery->fetchColumn();
$total_pages = ceil($total_requests / $limit);

// Fetch requests
$sql = "SELECT * FROM withdrawal_requests $where ORDER BY $sort $dir LIMIT ? OFFSET ?";
$reqQuery = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $param) {
    $reqQuery->bindValue($paramIndex++, $param);
}
$reqQuery->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$reqQuery->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$reqQuery->execute();
$requests = $reqQuery->fetchAll(PDO::FETCH_ASSOC);

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
        $url = "?tab=earnings$searchQuery&status=".urlencode($status)."&sort=".urlencode($column)."&dir=$new_dir";
        return "<a href=\"$url\" class=\"hover:text-black transition-colors\">$label $icon</a>";
    }
}
?>

<!-- Load Export Utilities -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="js/export-utils.js"></script>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
    <div class="p-8 border-b border-gray-100 bg-gradient-to-b from-blue-50/50 to-white">
        <h2 class="font-black text-navy uppercase tracking-wide mb-6"><i class="fas fa-wallet text-[#0066FF] me-2"></i> Wallet & Withdrawals</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-[#0066FF] rounded-2xl p-6 text-white shadow-lg shadow-blue-500/30 relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white opacity-10 rounded-full"></div>
                <p class="text-xs font-bold text-blue-100 uppercase tracking-wide">Available to Withdraw</p>
                <p class="text-3xl font-black mt-2">Rs. <?= number_format($pending_balance, 2) ?></p>
                <p class="text-xs text-blue-200 mt-2"><i class="fas fa-info-circle me-1"></i> Minimum withdrawal Rs. 2,500</p>
            </div>
            
            <div class="bg-white border border-yellow-200 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute right-0 top-0 w-16 h-16 bg-yellow-50 rounded-bl-3xl flex items-center justify-center text-yellow-500 text-xl"><i class="fas fa-lock"></i></div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Locked (Processing)</p>
                <p class="text-2xl font-black text-yellow-600 mt-2">Rs. <?= number_format($locked_balance, 2) ?></p>
                <p class="text-xs text-gray-400 mt-2">Requested & waiting for transfer</p>
            </div>
            
            <div class="bg-white border border-green-200 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute right-0 top-0 w-16 h-16 bg-green-50 rounded-bl-3xl flex items-center justify-center text-green-500 text-xl"><i class="fas fa-check-circle"></i></div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Paid Out</p>
                <p class="text-2xl font-black text-green-600 mt-2">Rs. <?= number_format($paid_balance, 2) ?></p>
                <p class="text-xs text-gray-400 mt-2">Lifetime earnings transferred</p>
            </div>
        </div>

        <div class="flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-200">
            <div>
                <h4 class="font-bold text-navy">Ready to cash out?</h4>
                <p class="text-sm text-gray-500">You can request one withdrawal per day.</p>
            </div>
            
            <?php if ($has_pending_request): ?>
                <button disabled class="bg-gray-300 text-gray-500 px-6 py-3 rounded-xl font-bold uppercase tracking-wide cursor-not-allowed">
                    <i class="fas fa-clock me-2"></i> Request Pending
                </button>
            <?php elseif ($pending_balance < 2500): ?>
                <button disabled class="bg-blue-100 text-[#0066FF] px-6 py-3 rounded-xl font-bold uppercase tracking-wide cursor-not-allowed opacity-70">
                    <i class="fas fa-lock me-2"></i> Min Rs. 2,500 Needed
                </button>
            <?php else: ?>
                <button onclick="openWithdrawalModal()" class="bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold uppercase tracking-wide transition-all shadow-lg shadow-blue-500/30">
                    <i class="fas fa-money-bill-wave me-2"></i> Request Withdrawal
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Withdrawal Requests History -->
<div class="bg-white rounded-2xl shadow-sm border border-[#F1F5F9] overflow-hidden mb-8">
    <div class="p-6 border-b border-[#F1F5F9] bg-[#F8FAFC]">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <h3 class="font-black text-navy uppercase tracking-wide"><i class="fas fa-history text-gray-400 me-2"></i> My Withdrawal Requests</h3>
            
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-2">
                <input type="hidden" name="tab" value="earnings">
                <select name="status" onchange="this.form.submit()" class="bg-white border border-gray-200 text-sm font-bold text-navy rounded-xl px-4 py-2 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all cursor-pointer">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </form>

            <!-- Export Buttons -->
            <div class="flex gap-2 border-l border-gray-200 pl-4 ml-2">
                <button type="button" onclick="openExportModal('withdrawals', 'Withdrawals', 'csv', [{value: 'pending', label: 'Pending'}, {value: 'approved', label: 'Approved'}, {value: 'paid', label: 'Paid'}, {value: 'rejected', label: 'Rejected'}])" class="h-10 px-4 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl text-sm font-bold text-gray-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-file-csv text-gray-400"></i> Export CSV
                </button>
                <button type="button" onclick="openExportModal('withdrawals', 'Withdrawals', 'pdf', [{value: 'pending', label: 'Pending'}, {value: 'approved', label: 'Approved'}, {value: 'paid', label: 'Paid'}, {value: 'rejected', label: 'Rejected'}])" class="h-10 px-4 bg-black text-white hover:bg-gray-800 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-lg shadow-black/10">
                    <i class="fas fa-file-pdf text-red-400"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
    
    <div class="px-6 py-3 bg-white border-b border-[#F1F5F9] text-xs font-bold text-gray-400">
        Showing <?= min($offset + 1, $total_requests) ?>-<?= min($offset + $limit, $total_requests) ?> of <?= $total_requests ?> requests
    </div>

    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto p-4">
        <table class="w-full text-left border-collapse rounded-2xl overflow-hidden border border-[#F1F5F9]" style="border-spacing: 0;">
            <thead>
                <tr>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 px-4 border-b border-[#F1F5F9]">
                        <?= sortLink('created_at', 'Date', $sort, $dir, null, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">
                        <?= sortLink('amount', 'Amount', $sort, $dir, null, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Bank Account</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">Status</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Notes / Reference</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php if (count($requests) > 0): foreach ($requests as $req): ?>
                <tr class="hover:bg-[#F8FAFC] transition-colors">
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <p class="font-bold text-navy"><?= date('M d, Y', strtotime($req['created_at'])) ?></p>
                        <p class="text-[11px] text-gray-400 font-bold tracking-wide"><?= date('h:i A', strtotime($req['created_at'])) ?></p>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] font-black text-[#0066FF] text-lg">
                        Rs. <?= number_format($req['amount'], 2) ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <p class="font-bold text-gray-700"><?= htmlspecialchars($req['bank_name']) ?></p>
                        <p class="text-[11px] text-gray-500 font-bold tracking-wide"><?= htmlspecialchars($req['account_number']) ?></p>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-center">
                        <?php if ($req['status'] == 'Pending'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700 uppercase tracking-wide"><i class="fas fa-clock me-1"></i> Pending</span>
                        <?php elseif ($req['status'] == 'Approved'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-[#0066FF] uppercase tracking-wide"><i class="fas fa-thumbs-up me-1"></i> Approved</span>
                        <?php elseif ($req['status'] == 'Paid'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 uppercase tracking-wide"><i class="fas fa-check-circle me-1"></i> Paid</span>
                        <?php elseif ($req['status'] == 'Rejected'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 uppercase tracking-wide"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <?php if ($req['status'] == 'Paid'): ?>
                            <p class="text-xs text-green-600 font-bold uppercase tracking-wide">Ref: <?= htmlspecialchars($req['reference_no']) ?></p>
                            <p class="text-[11px] text-gray-400 font-bold mt-0.5"><?= date('M d, Y', strtotime($req['paid_at'])) ?></p>
                        <?php elseif ($req['status'] == 'Rejected'): ?>
                            <p class="text-xs text-red-500 font-bold">Reason: <?= htmlspecialchars($req['reject_reason']) ?></p>
                        <?php else: ?>
                            <p class="text-xs text-gray-400">-</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" class="p-12 text-center">
                        <img src="../image/empty-wallet.svg" onerror="this.src='https://illustrations.popsy.co/gray/crashed-error.svg'" class="w-48 h-48 mx-auto mb-4 opacity-50">
                        <h3 class="text-lg font-black text-navy mb-1">No Withdrawals Yet</h3>
                        <p class="text-gray-500">You haven't requested any payouts matching this criteria.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="lg:hidden space-y-3 p-4 bg-gray-50">
        <?php if (count($requests) > 0): foreach ($requests as $req): ?>
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col gap-3">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-black text-[#0066FF] text-xl">Rs. <?= number_format($req['amount'], 2) ?></p>
                    <p class="text-xs text-gray-500 font-bold mt-1"><?= date('M d, Y h:i A', strtotime($req['created_at'])) ?></p>
                </div>
                <div>
                    <?php if ($req['status'] == 'Pending'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wide">Pending</span>
                    <?php elseif ($req['status'] == 'Approved'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-[#0066FF] uppercase tracking-wide">Approved</span>
                    <?php elseif ($req['status'] == 'Paid'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wide">Paid</span>
                    <?php elseif ($req['status'] == 'Rejected'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wide">Rejected</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                <p class="font-bold text-gray-700 text-sm"><?= htmlspecialchars($req['bank_name']) ?></p>
                <p class="text-[11px] text-gray-500 font-bold"><?= htmlspecialchars($req['account_number']) ?></p>
            </div>
            
            <?php if ($req['status'] == 'Paid'): ?>
                <p class="text-[11px] text-green-600 font-bold uppercase">Ref: <?= htmlspecialchars($req['reference_no']) ?></p>
            <?php elseif ($req['status'] == 'Rejected'): ?>
                <p class="text-[11px] text-red-500 font-bold">Reason: <?= htmlspecialchars($req['reject_reason']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <div class="text-center p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
            <img src="../image/empty-wallet.svg" onerror="this.src='https://illustrations.popsy.co/gray/crashed-error.svg'" class="w-32 h-32 mx-auto mb-4 opacity-50">
            <h3 class="text-lg font-black text-navy mb-1">No Withdrawals</h3>
            <p class="text-sm text-gray-500 mb-4">You haven't requested any payouts yet.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-[#F1F5F9] bg-white flex justify-center">
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?tab=earnings&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-left text-xs"></i></a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?tab=earnings&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $i ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors <?= $i === $page ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?tab=earnings&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-right text-xs"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>


<!-- Withdrawal Modal -->
<div id="withdrawalModal" class="fixed inset-0 z-[100] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300" id="withdrawalModalContent">
        <div class="bg-[#0066FF] p-6 text-white relative">
            <h3 class="text-2xl font-black uppercase tracking-wide">Request Withdrawal</h3>
            <p class="text-blue-100 mt-1">Transfer funds to your bank account</p>
            <button onclick="closeWithdrawalModal()" class="absolute top-6 right-6 text-blue-200 hover:text-white transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form id="withdrawalForm" onsubmit="submitWithdrawal(event)" class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Amount to Withdraw</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-gray-500">Rs.</span>
                    <input type="number" id="withdraw_amount" name="amount" min="2500" max="<?= $pending_balance ?>" step="0.01" value="<?= $pending_balance ?>" required
                           class="w-full border-gray-200 rounded-xl py-3 pl-12 pr-4 focus:ring-[#0066FF] focus:border-[#0066FF] font-black text-xl text-navy">
                </div>
                <div class="flex justify-between mt-2 text-xs font-bold">
                    <span class="text-gray-400">Min: Rs. 2,500</span>
                    <span class="text-[#0066FF] cursor-pointer hover:underline" onclick="document.getElementById('withdraw_amount').value='<?= $pending_balance ?>'; updateBreakdown();">Max: Rs. <?= number_format($pending_balance, 2) ?></span>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-slate font-medium">Available Pending:</span>
                    <span class="font-bold text-navy">Rs. <?= number_format($pending_balance, 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mb-3 pb-3 border-b border-blue-200">
                    <span class="text-slate font-medium">You Request:</span>
                    <span class="font-bold text-red-500" id="preview_request">- Rs. <?= number_format($pending_balance, 2) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate font-bold">Remaining Pending:</span>
                    <span class="font-black text-[#0066FF]" id="preview_remaining">Rs. 0.00</span>
                </div>
            </div>

            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-sm font-bold text-gray-700 uppercase tracking-wide">Bank Details</label>
                    <label class="flex items-center text-xs text-[#0066FF] cursor-pointer font-bold">
                        <input type="checkbox" id="edit_bank_toggle" class="mr-2 rounded text-[#0066FF] focus:ring-[#0066FF]" onchange="toggleBankEdit()">
                        Edit Details
                    </label>
                </div>
                
                <div class="space-y-3 bg-gray-50 p-4 rounded-xl border border-gray-200" id="bank_details_container">
                    <input type="text" name="bank_name" id="req_bank_name" value="<?= htmlspecialchars($business['bank_name']) ?>" class="w-full border-gray-200 rounded-lg py-2 px-3 text-sm font-bold bg-white cursor-not-allowed" readonly required>
                    <input type="text" name="branch_name" id="req_branch_name" value="<?= htmlspecialchars($business['branch_name']) ?>" class="w-full border-gray-200 rounded-lg py-2 px-3 text-sm font-bold bg-white cursor-not-allowed" readonly required>
                    <input type="text" name="account_number" id="req_account_number" value="<?= htmlspecialchars($business['account_number']) ?>" class="w-full border-gray-200 rounded-lg py-2 px-3 text-sm font-black text-navy bg-white cursor-not-allowed tracking-widest" readonly required>
                    <input type="text" name="account_holder_name" id="req_account_holder" value="<?= htmlspecialchars($business['account_holder_name']) ?>" class="w-full border-gray-200 rounded-lg py-2 px-3 text-sm font-bold bg-white cursor-not-allowed" readonly required>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">Note (Optional)</label>
                <input type="text" name="note" placeholder="e.g., Urgent" class="w-full border-gray-200 rounded-xl py-2 px-4 focus:ring-[#0066FF] focus:border-[#0066FF]">
            </div>

            <button type="submit" id="btnSubmitWithdrawal" class="w-full bg-[#0066FF] hover:bg-blue-700 text-white py-4 rounded-xl font-black uppercase tracking-widest transition-all shadow-lg shadow-blue-500/30">
                Submit Request <i class="fas fa-arrow-right ms-2"></i>
            </button>
            <p id="withdraw_error" class="text-red-500 text-xs font-bold text-center mt-3 hidden"></p>
        </form>
    </div>
</div>

<script>
    const maxBalance = <?= $pending_balance ?>;

    function openWithdrawalModal() {
        const modal = document.getElementById('withdrawalModal');
        const content = document.getElementById('withdrawalModalContent');
        modal.classList.remove('hidden');
        // trigger reflow
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        updateBreakdown();
    }

    function closeWithdrawalModal() {
        const modal = document.getElementById('withdrawalModal');
        const content = document.getElementById('withdrawalModalContent');
        modal.classList.add('opacity-0');
        content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    document.getElementById('withdraw_amount').addEventListener('input', updateBreakdown);

    function updateBreakdown() {
        let amount = parseFloat(document.getElementById('withdraw_amount').value) || 0;
        if(amount > maxBalance) amount = maxBalance;
        
        const remaining = maxBalance - amount;
        
        document.getElementById('preview_request').innerText = `- Rs. ${amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById('preview_remaining').innerText = `Rs. ${remaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }

    function toggleBankEdit() {
        const isEdit = document.getElementById('edit_bank_toggle').checked;
        const inputs = document.querySelectorAll('#bank_details_container input');
        
        inputs.forEach(input => {
            if(isEdit) {
                input.removeAttribute('readonly');
                input.classList.remove('cursor-not-allowed', 'bg-white');
                input.classList.add('bg-blue-50', 'border-blue-300');
            } else {
                input.setAttribute('readonly', 'readonly');
                input.classList.add('cursor-not-allowed', 'bg-white');
                input.classList.remove('bg-blue-50', 'border-blue-300');
            }
        });
    }

    function submitWithdrawal(e) {
        e.preventDefault();
        
        const btn = document.getElementById('btnSubmitWithdrawal');
        const errorMsg = document.getElementById('withdraw_error');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        errorMsg.classList.add('hidden');

        const formData = new FormData(e.target);

        fetch('../Backend/request-withdrawal.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                btn.innerHTML = '<i class="fas fa-check"></i> Requested!';
                btn.classList.replace('bg-[#0066FF]', 'bg-green-500');
                btn.classList.replace('hover:bg-blue-700', 'hover:bg-green-600');
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                errorMsg.innerText = data.message;
                errorMsg.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = 'Submit Request <i class="fas fa-arrow-right ms-2"></i>';
            }
        })
        .catch(error => {
            errorMsg.innerText = "An error occurred. Please try again.";
            errorMsg.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = 'Submit Request <i class="fas fa-arrow-right ms-2"></i>';
        });
    }
</script>
