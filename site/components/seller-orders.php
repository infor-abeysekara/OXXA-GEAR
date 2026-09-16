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
                    <th class="p-4 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                // Fetch order items that belong to the current seller's products
                $orderStmt = $pdo->prepare("
                    SELECT o.*, ord.id as order_id, ord.order_code, ord.status, ord.tracking_number, ord.courier_company, ord.created_at as order_date,
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
                        <?php elseif ($ord['status'] == 'confirmed'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 uppercase tracking-wider">Confirmed</span>
                        <?php elseif ($ord['status'] == 'shipped'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase tracking-wider">Shipped</span>
                        <?php elseif ($ord['status'] == 'delivered' || $ord['status'] == 'completed'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wider"><?= htmlspecialchars($ord['status']) ?></span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-800 uppercase tracking-wider"><?= htmlspecialchars($ord['status']) ?></span>
                        <?php endif; ?>
                        <?php if(!empty($ord['tracking_number'])): ?>
                            <div class="mt-1 text-[10px] font-bold text-gray-500 uppercase"><?= htmlspecialchars($ord['courier_company']) ?>: <?= htmlspecialchars($ord['tracking_number']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-center">
                        <button onclick='openUpdateOrderModal(<?= json_encode([
                            "order_id" => $ord["order_id"],
                            "status" => $ord["status"],
                            "tracking_number" => $ord["tracking_number"] ?? "",
                            "courier_company" => $ord["courier_company"] ?? ""
                        ]) ?>)' class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 hover:text-[#0066FF] hover:bg-blue-50 transition-colors flex items-center justify-center">
                            <i class="fas fa-edit"></i>
                        </button>
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

<!-- Update Order Modal -->
<div id="updateOrderModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeUpdateOrderModal()"></div>
    <div class="bg-white rounded-2xl w-full max-w-md relative z-10 shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-black text-navy uppercase tracking-wide">Update Order Status</h3>
            <button onclick="closeUpdateOrderModal()" class="text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
        </div>
        <form action="components/update-order-status.php" method="POST" class="p-6">
            <input type="hidden" name="order_id" id="modal_order_id">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Order Status</label>
                <select name="status" id="modal_status" onchange="toggleTrackingFields()" class="w-full bg-gray-50 border border-gray-200 text-navy font-bold rounded-xl px-4 py-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF]">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div id="tracking_fields" class="hidden">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Courier Company</label>
                    <select name="courier_company" id="modal_courier_company" class="w-full bg-gray-50 border border-gray-200 text-navy font-bold rounded-xl px-4 py-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF]">
                        <option value="">Select Courier</option>
                        <option value="Koombiyo">Koombiyo Delivery</option>
                        <option value="Pronto">Pronto Lanka</option>
                        <option value="Domex">Domex</option>
                        <option value="Fardar">Fardar Express</option>
                        <option value="Certis">Certis Lanka</option>
                        <option value="SellerDelivery">Seller Direct Delivery</option>
                    </select>
                </div>
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Tracking Number</label>
                    <input type="text" name="tracking_number" id="modal_tracking_number" placeholder="e.g. KMB12345678" class="w-full bg-gray-50 border border-gray-200 text-navy font-bold rounded-xl px-4 py-3 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF]">
                </div>
            </div>

            <button type="submit" class="w-full bg-[#0066FF] text-white font-bold rounded-xl py-3 hover:bg-blue-700 transition-colors uppercase tracking-wide">Update Status</button>
        </form>
    </div>
</div>

<script>
function openUpdateOrderModal(data) {
    document.getElementById('modal_order_id').value = data.order_id;
    document.getElementById('modal_status').value = data.status;
    document.getElementById('modal_courier_company').value = data.courier_company;
    document.getElementById('modal_tracking_number').value = data.tracking_number;
    
    toggleTrackingFields();
    
    const modal = document.getElementById('updateOrderModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeUpdateOrderModal() {
    const modal = document.getElementById('updateOrderModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function toggleTrackingFields() {
    const status = document.getElementById('modal_status').value;
    const fields = document.getElementById('tracking_fields');
    if (status === 'shipped' || status === 'delivered' || status === 'completed') {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
    }
}
</script>
