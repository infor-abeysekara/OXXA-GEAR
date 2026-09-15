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
?>

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
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
    <div class="p-6 border-b border-gray-100">
        <h3 class="font-black text-navy uppercase tracking-wide"><i class="fas fa-history text-gray-400 me-2"></i> My Withdrawal Requests</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-widest text-gray-400 font-bold">
                    <th class="p-4">Date</th>
                    <th class="p-4">Amount</th>
                    <th class="p-4">Bank Account</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4">Notes / Reference</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                $reqStmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE seller_id = ? ORDER BY id DESC");
                $reqStmt->execute([$_SESSION['userid']]);
                $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($requests) > 0):
                    foreach ($requests as $req):
                ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                    <td class="p-4">
                        <p class="font-bold text-navy"><?= date('M d, Y', strtotime($req['created_at'])) ?></p>
                        <p class="text-xs text-gray-400"><?= date('h:i A', strtotime($req['created_at'])) ?></p>
                    </td>
                    <td class="p-4 font-black text-navy">Rs. <?= number_format($req['amount'], 2) ?></td>
                    <td class="p-4">
                        <p class="font-bold text-gray-700"><?= htmlspecialchars($req['bank_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($req['account_number']) ?></p>
                    </td>
                    <td class="p-4 text-center">
                        <?php if ($req['status'] == 'Pending'): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800"><i class="fas fa-clock me-1"></i> Pending</span>
                        <?php elseif ($req['status'] == 'Approved'): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-[#0066FF]"><i class="fas fa-thumbs-up me-1"></i> Approved</span>
                        <?php elseif ($req['status'] == 'Paid'): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800"><i class="fas fa-check-circle me-1"></i> Paid</span>
                        <?php elseif ($req['status'] == 'Rejected'): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <?php if ($req['status'] == 'Paid'): ?>
                            <p class="text-xs text-green-600 font-bold">Ref: <?= htmlspecialchars($req['reference_no']) ?></p>
                            <p class="text-xs text-gray-400"><?= date('M d, Y', strtotime($req['paid_at'])) ?></p>
                        <?php elseif ($req['status'] == 'Rejected'): ?>
                            <p class="text-xs text-red-500">Reason: <?= htmlspecialchars($req['reject_reason']) ?></p>
                        <?php else: ?>
                            <p class="text-xs text-gray-400">-</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" class="p-8 text-center text-gray-400">No withdrawal requests yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
