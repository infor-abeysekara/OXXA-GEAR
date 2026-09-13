<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="p-8 border-b border-gray-100 bg-gray-50/50">
        <h2 class="font-black text-navy uppercase tracking-wide mb-6"><i class="fas fa-wallet text-[#0066FF] me-2"></i> Financial Overview</h2>
        
        <?php
        // Fetch financial summary
        $summaryStmt = $pdo->prepare("
            SELECT 
                SUM(selling_price) as total_sales,
                SUM(profit) as total_profit,
                SUM(admin_commission) as total_commission,
                SUM(seller_earning) as total_earned,
                SUM(CASE WHEN payout_status = 'pending' THEN seller_earning ELSE 0 END) as pending_payout,
                SUM(CASE WHEN payout_status = 'paid' THEN seller_earning ELSE 0 END) as paid_payout
            FROM seller_payouts 
            WHERE seller_id = ?
        ");
        $summaryStmt->execute([$_SESSION['userid']]);
        $fin = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Sales (Gross)</p>
                <p class="text-xl font-black text-navy mt-1">Rs. <?= number_format($fin['total_sales'] ?? 0, 2) ?></p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">OXXA Commission (10%)</p>
                <p class="text-xl font-black text-red-500 mt-1">- Rs. <?= number_format($fin['total_commission'] ?? 0, 2) ?></p>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 relative overflow-hidden">
                <p class="text-xs font-bold text-blue-600 uppercase tracking-wide relative z-10">Total Net Earned</p>
                <p class="text-2xl font-black text-[#0066FF] mt-1 relative z-10">Rs. <?= number_format($fin['total_earned'] ?? 0, 2) ?></p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <p class="text-xs font-bold text-green-600 uppercase tracking-wide">Ready for Payout</p>
                <p class="text-xl font-black text-green-600 mt-1">Rs. <?= number_format($fin['pending_payout'] ?? 0, 2) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
        <h3 class="font-black text-navy uppercase tracking-wide">Payout History</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-widest text-gray-400 font-bold">
                    <th class="p-4">Date</th>
                    <th class="p-4">Order Item</th>
                    <th class="p-4 text-right">Selling Price</th>
                    <th class="p-4 text-right">Cost Price</th>
                    <th class="p-4 text-right">Admin Cut</th>
                    <th class="p-4 text-right">Your Earning</th>
                    <th class="p-4 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                $payoutStmt = $pdo->prepare("
                    SELECT p.*, o.order_id, o.product_name, ord.order_code, ord.created_at as order_date
                    FROM seller_payouts p
                    JOIN order_items o ON p.order_item_id = o.id
                    JOIN orders ord ON o.order_id = ord.id
                    WHERE p.seller_id = ?
                    ORDER BY p.id DESC
                ");
                $payoutStmt->execute([$_SESSION['userid']]);
                $payouts = $payoutStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($payouts) > 0):
                    foreach ($payouts as $pay):
                ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                    <td class="p-4">
                        <p class="font-bold text-navy"><?= date('M d, Y', strtotime($pay['created_at'])) ?></p>
                        <p class="text-xs text-gray-400"><?= date('h:i A', strtotime($pay['created_at'])) ?></p>
                    </td>
                    <td class="p-4">
                        <p class="font-bold text-navy"><?= htmlspecialchars($pay['product_name']) ?></p>
                        <p class="text-xs text-[#0066FF] font-medium">Order: <?= htmlspecialchars($pay['order_code']) ?></p>
                    </td>
                    <td class="p-4 text-right font-medium text-gray-600">Rs. <?= number_format($pay['selling_price'], 2) ?></td>
                    <td class="p-4 text-right font-medium text-gray-600">Rs. <?= number_format($pay['cost_price'], 2) ?></td>
                    <td class="p-4 text-right text-red-400">-Rs. <?= number_format($pay['admin_commission'], 2) ?></td>
                    <td class="p-4 text-right font-black text-green-500">Rs. <?= number_format($pay['seller_earning'], 2) ?></td>
                    <td class="p-4 text-center">
                        <?php if ($pay['payout_status'] == 'pending'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wider"><i class="fas fa-clock me-1"></i> Pending</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wider"><i class="fas fa-check-circle me-1"></i> Paid</span>
                            <div class="text-[9px] text-gray-400 mt-1"><?= date('M d, Y', strtotime($pay['paid_at'])) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-receipt text-2xl text-gray-300"></i>
                        </div>
                        <p>No payout history yet. Start selling to see your earnings here!</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
