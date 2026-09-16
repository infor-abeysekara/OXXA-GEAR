<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h2 class="font-black text-navy uppercase tracking-wide">My Products</h2>
        <!-- Filters -->
        <div class="flex gap-2">
            <select class="bg-white border border-gray-200 text-sm font-bold text-navy rounded-lg px-3 py-2 focus:outline-none focus:border-[#0066FF]">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="pending">Pending Approval</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs uppercase tracking-widest text-gray-400 font-bold">
                    <th class="p-4">Product</th>
                    <th class="p-4">Category</th>
                    <th class="p-4 text-right">Selling Price</th>
                    <th class="p-4 text-right">Cost Price</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                // Fetch products for this seller
                $prodQuery = $pdo->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.seller_id = ? 
                    ORDER BY p.id DESC
                ");
                $prodQuery->execute([$_SESSION['userid']]);
                $products = $prodQuery->fetchAll(PDO::FETCH_ASSOC);

                if (count($products) > 0):
                    foreach ($products as $p):
                        // Fetch primary image
                        $imgQuery = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
                        $imgQuery->execute([$p['id']]);
                        $img = $imgQuery->fetchColumn();
                        $imageSrc = $img ? "../assets/uploads/products/" . $img : "../image/no-image.jpg";
                ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <img src="<?= htmlspecialchars($imageSrc) ?>" class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                            <div>
                                <p class="font-bold text-navy"><?= htmlspecialchars($p['name']) ?></p>
                                <p class="text-xs text-gray-400">Code: <?= htmlspecialchars($p['product_code']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 text-slate"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                    <td class="p-4 text-right font-bold text-navy">Rs. <?= number_format($p['base_price'], 2) ?></td>
                    <td class="p-4 text-right text-gray-500">Rs. <?= number_format($p['cost_price'], 2) ?></td>
                    <td class="p-4 text-center">
                        <?php if ($p['is_approved'] == 0): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase tracking-wider">Pending</span>
                        <?php elseif ($p['status'] == 'suspended'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wider">Suspended</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 uppercase tracking-wider">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-right">
                        <a href="seller-edit-product.php?id=<?= $p['id'] ?>" class="text-gray-400 hover:text-[#0066FF] transition-colors"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-box-open text-2xl text-gray-300"></i>
                        </div>
                        <p>You haven't added any products yet.</p>
                        <a href="seller-add-product.php" class="text-[#0066FF] font-bold text-xs uppercase tracking-wide mt-2 inline-block hover:underline">Add Your First Product</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
