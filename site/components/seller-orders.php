<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h2 class="font-black text-navy uppercase tracking-wide">My Orders</h2>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-widest text-gray-400 font-bold">
                    <th class="p-4">Date</th>
                    <th class="p-4">Order ID</th>
                    <th class="p-4">Product</th>
                    <th class="p-4 text-center">Qty</th>
                    <th class="p-4">Buyer</th>
                    <th class="p-4 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                // Fetch order items that belong to the current seller's products
                $orderStmt = $pdo->prepare("
                    SELECT o.*, ord.order_code, ord.status, ord.created_at as order_date,
                           u.first_name, u.last_name
                    FROM order_items o
                    JOIN orders ord ON o.order_id = ord.id
                    JOIN products p ON o.product_id = p.id
                    JOIN users u ON ord.user_id = u.id
                    WHERE p.seller_id = ?
                    ORDER BY ord.id DESC
                ");
                $orderStmt->execute([$_SESSION['userid']]);
                $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($orders) > 0):
                    foreach ($orders as $ord):
                ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                    <td class="p-4">
                        <p class="font-bold text-navy"><?= date('M d, Y', strtotime($ord['order_date'])) ?></p>
                        <p class="text-xs text-gray-400"><?= date('h:i A', strtotime($ord['order_date'])) ?></p>
                    </td>
                    <td class="p-4 font-bold text-[#0066FF]"><?= htmlspecialchars($ord['order_code']) ?></td>
                    <td class="p-4">
                        <p class="font-bold text-navy"><?= htmlspecialchars($ord['product_name']) ?></p>
                    </td>
                    <td class="p-4 text-center font-bold text-slate">1</td>
                    <td class="p-4 text-slate">
                        <?= htmlspecialchars($ord['first_name'] . ' ' . $ord['last_name']) ?>
                    </td>
                    <td class="p-4 text-center">
                        <?php if ($ord['status'] == 'pending'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wider">Pending</span>
                        <?php elseif ($ord['status'] == 'shipped'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">Shipped</span>
                        <?php elseif ($ord['status'] == 'delivered'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wider">Delivered</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-800 uppercase tracking-wider"><?= htmlspecialchars($ord['status']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-box text-2xl text-gray-300"></i>
                        </div>
                        <p>No orders yet.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
