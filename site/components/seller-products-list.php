<?php
// Auto-sync products with 0 base_price / cost_price / total_qty from color_sizes
try {
    $pdo->query("UPDATE products p SET 
        p.base_price = (SELECT MIN(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.selling_price > 0),
        p.cost_price = (SELECT MIN(cs.cost_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.cost_price > 0),
        p.total_qty = COALESCE((SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id), p.total_qty)
        WHERE (p.base_price = 0 OR p.base_price IS NULL)
        AND EXISTS (SELECT 1 FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.selling_price > 0)");
} catch (Exception $e) {}

// Pagination and Filters Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'p.id';
$dir = $_GET['dir'] ?? 'DESC';

$allowed_sorts = ['p.name', 'p.base_price', 'p.created_at', 'p.total_qty', 'p.id'];
$allowed_dirs = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sorts)) $sort = 'p.id';
if (!in_array(strtoupper($dir), $allowed_dirs)) $dir = 'DESC';

// Build Query
$params = [$_SESSION['userid']];
$where = "WHERE p.seller_id = ?";

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== 'all') {
    if ($status === 'pending') {
        $where .= " AND p.is_approved = 0";
    } else {
        $where .= " AND p.status = ? AND p.is_approved = 1";
        $params[] = $status;
    }
}

// Count total for pagination
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
$countQuery->execute($params);
$total_products = $countQuery->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Fetch products with variant prices fallback
$sql = "SELECT p.*, c.name as category_name,
               COALESCE((SELECT MIN(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.selling_price > 0), p.base_price) as display_selling_price,
               COALESCE((SELECT MAX(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.selling_price > 0), p.base_price) as max_selling_price,
               COALESCE((SELECT MIN(cs.cost_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.cost_price > 0), p.cost_price) as display_cost_price,
               COALESCE((SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id), p.total_qty) as real_total_qty
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where 
        ORDER BY $sort $dir 
        LIMIT ? OFFSET ?";
$prodQuery = $pdo->prepare($sql);

// Bind params explicitly because of LIMIT/OFFSET issues with simple execute() arrays
$paramIndex = 1;
foreach ($params as $param) {
    $prodQuery->bindValue($paramIndex++, $param);
}
$prodQuery->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$prodQuery->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$prodQuery->execute();
$products = $prodQuery->fetchAll(PDO::FETCH_ASSOC);

// Helper function to build sort links
function sortLink($column, $label, $current_sort, $current_dir, $search, $status) {
    $new_dir = ($current_sort === $column && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort === $column) {
        $icon = $current_dir === 'ASC' ? '<i class="fas fa-sort-up ml-1"></i>' : '<i class="fas fa-sort-down ml-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort text-gray-300 ml-1"></i>';
    }
    
    $url = "?tab=products&search=".urlencode($search)."&status=".urlencode($status)."&sort=".urlencode($column)."&dir=$new_dir";
    return "<a href=\"$url\" class=\"hover:text-black transition-colors\">$label $icon</a>";
}
?>

<!-- Load Export Utilities -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="js/export-utils.js"></script>

<div class="bg-white rounded-2xl shadow-sm border border-[#F1F5F9] overflow-hidden">
    <!-- Top Bar: Search, Filters & Export -->
    <div class="p-6 border-b border-[#F1F5F9] bg-[#F8FAFC]">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <h2 class="font-black text-navy uppercase tracking-wide">My Products</h2>
            
            <form action="" method="GET" class="flex flex-col sm:flex-row gap-2 flex-1 lg:max-w-2xl lg:justify-end">
                <input type="hidden" name="tab" value="products">
                
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." 
                           class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all">
                </div>
                
                <select name="status" onchange="this.form.submit()" class="bg-white border border-gray-200 text-sm font-bold text-navy rounded-xl px-4 py-2 focus:outline-none focus:border-[#0066FF] focus:ring-1 focus:ring-[#0066FF] transition-all cursor-pointer">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
                
                <button type="submit" class="hidden sm:block bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-xl text-sm font-bold transition-colors">
                    Filter
                </button>
            </form>

            <!-- Export Buttons -->
            <div class="flex gap-2 border-l border-gray-200 pl-4 ml-2">
                <button type="button" onclick="openExportModal('products', 'Products', 'csv', [{value: 'active', label: 'Active'}, {value: 'pending', label: 'Pending Approval'}, {value: 'suspended', label: 'Suspended'}])" class="h-10 px-4 bg-white border border-gray-200 hover:bg-gray-50 rounded-xl text-sm font-bold text-gray-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-file-csv text-gray-400"></i> Export CSV
                </button>
                <button type="button" onclick="openExportModal('products', 'Products', 'pdf', [{value: 'active', label: 'Active'}, {value: 'pending', label: 'Pending Approval'}, {value: 'suspended', label: 'Suspended'}])" class="h-10 px-4 bg-black text-white hover:bg-gray-800 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-lg shadow-black/10">
                    <i class="fas fa-file-pdf text-red-400"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
    
    <!-- Results Info -->
    <div class="px-6 py-3 bg-white border-b border-[#F1F5F9] text-xs font-bold text-gray-400">
        Showing <?= min($offset + 1, $total_products) ?>-<?= min($offset + $limit, $total_products) ?> of <?= $total_products ?> products
    </div>

    <!-- Desktop Table View Premium Design -->
    <div class="hidden lg:block overflow-x-auto p-4">
        <table class="w-full text-left border-collapse rounded-2xl overflow-hidden border border-[#F1F5F9]" style="border-spacing: 0;">
            <thead>
                <tr>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 px-4 border-b border-[#F1F5F9]">
                        <?= sortLink('p.name', 'Product', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 border-b border-[#F1F5F9]">Category</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-right border-b border-[#F1F5F9]">
                        <?= sortLink('p.base_price', 'Price', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-right border-b border-[#F1F5F9]">
                        <?= sortLink('p.total_qty', 'Stock', $sort, $dir, $search, $status) ?>
                    </th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-center border-b border-[#F1F5F9]">Status</th>
                    <th class="bg-[#F8FAFC] text-[11px] uppercase tracking-[0.1em] font-extrabold text-[#64748B] p-3 text-right pr-4 border-b border-[#F1F5F9]">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php
                if (count($products) > 0):
                    foreach ($products as $p):
                        $imgQuery = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
                        $imgQuery->execute([$p['id']]);
                        $img = $imgQuery->fetchColumn();
                        $imageSrc = $img ? "../assets/uploads/products/" . $img : "../image/no-image.jpg";
                ?>
                <tr class="hover:bg-[#F8FAFC] transition-colors group">
                    <td class="p-4 border-t border-[#F1F5F9]">
                        <div class="flex items-center gap-3">
                            <img src="<?= htmlspecialchars($imageSrc) ?>" class="w-12 h-12 rounded-lg object-cover bg-white border border-gray-100 shadow-sm">
                            <div>
                                <p class="font-bold text-navy"><?= htmlspecialchars($p['name']) ?></p>
                                <p class="text-[11px] text-gray-400 font-bold tracking-wide"><?= htmlspecialchars($p['product_code']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-gray-600 font-medium">
                        <?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-right">
                        <p class="font-black text-navy">
                            Rs. <?= number_format($p['display_selling_price'] ?? $p['base_price'], 2) ?>
                            <?php if (!empty($p['max_selling_price']) && $p['max_selling_price'] > ($p['display_selling_price'] ?? $p['base_price'])): ?>
                                <span class="text-xs text-gray-400 font-semibold block sm:inline"> - <?= number_format($p['max_selling_price'], 2) ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="text-[10px] text-gray-400 font-bold">Cost: Rs. <?= number_format($p['display_cost_price'] ?? $p['cost_price'], 2) ?></p>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-right font-bold text-gray-700">
                        <?= $p['real_total_qty'] ?? $p['total_qty'] ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-center">
                        <?php if ($p['is_approved'] == 0): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700 uppercase">Pending</span>
                        <?php elseif ($p['status'] == 'suspended'): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 uppercase">Suspended</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 uppercase">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 border-t border-[#F1F5F9] text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="product-details.php?id=<?= $p['id'] ?>" target="_blank" class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 hover:bg-[#0066FF] hover:border-[#0066FF] hover:text-white flex items-center justify-center text-gray-500 transition-all shadow-xs" title="View in Store">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="seller-edit-product.php?id=<?= $p['id'] ?>" class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 hover:bg-emerald-600 hover:border-emerald-600 hover:text-white flex items-center justify-center text-gray-500 transition-all shadow-xs" title="Edit Product">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            <button type="button" onclick="deleteSellerProduct(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')" class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-200 hover:bg-rose-600 hover:border-rose-600 hover:text-white flex items-center justify-center text-gray-500 transition-all shadow-xs" title="Delete Product">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-4xl"><i class="fas fa-box-open"></i></div>
                        <h3 class="text-lg font-black text-navy mb-1">No Products Found</h3>
                        <p class="text-gray-500 mb-4">You haven't added any products matching this criteria yet.</p>
                        <a href="seller-add-product.php" class="bg-[#0066FF] hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold transition-colors inline-flex items-center gap-2">
                            <i class="fas fa-plus"></i> Add First Product
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="lg:hidden space-y-3 p-4 bg-gray-50">
        <?php
        if (count($products) > 0):
            foreach ($products as $p):
                $imgQuery = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
                $imgQuery->execute([$p['id']]);
                $img = $imgQuery->fetchColumn();
                $imageSrc = $img ? "../assets/uploads/products/" . $img : "../image/no-image.jpg";
        ?>
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col gap-3">
            <div class="flex gap-4">
                <img src="<?= htmlspecialchars($imageSrc) ?>" class="w-20 h-20 rounded-xl object-cover bg-gray-50 border border-gray-100 shrink-0">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-navy truncate"><?= htmlspecialchars($p['name']) ?></p>
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mt-0.5"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></p>
                    <p class="font-black text-[#0066FF] text-lg mt-1">Rs. <?= number_format($p['display_selling_price'] ?? $p['base_price'], 2) ?></p>
                    
                    <div class="mt-2">
                        <?php if ($p['is_approved'] == 0): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-yellow-100 text-yellow-800 uppercase">Pending</span>
                        <?php elseif ($p['status'] == 'suspended'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-100 text-red-800 uppercase">Suspended</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-green-100 text-green-800 uppercase">Active</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between items-center pt-3 border-t border-gray-50">
                <p class="text-xs font-bold text-gray-400">Stock: <?= $p['real_total_qty'] ?? $p['total_qty'] ?></p>
                <div class="flex gap-2">
                    <a href="product-details.php?id=<?= $p['id'] ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 hover:bg-[#0066FF] hover:text-white transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </a>
                    <a href="seller-edit-product.php?id=<?= $p['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 hover:bg-emerald-600 hover:text-white transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </a>
                    <button type="button" onclick="deleteSellerProduct(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-lg text-gray-600 hover:bg-rose-600 hover:text-white transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="text-center p-8 bg-white rounded-2xl shadow-sm border border-gray-100">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-2xl"><i class="fas fa-box-open"></i></div>
            <h3 class="text-lg font-black text-navy mb-1">No Products</h3>
            <p class="text-sm text-gray-500 mb-4">Start selling by adding your first product.</p>
            <a href="seller-add-product.php" class="bg-[#0066FF] hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors block w-full">
                Add Product
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-[#F1F5F9] bg-white flex justify-center">
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?tab=products&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page - 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-left text-xs"></i></a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?tab=products&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $i ?>" 
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors <?= $i === $page ? 'bg-black text-white' : 'border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?tab=products&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&dir=<?= $dir ?>&page=<?= $page + 1 ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors"><i class="fas fa-chevron-right text-xs"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function deleteSellerProduct(productId, productName) {
    if (typeof Swal === 'undefined') {
        if (confirm(`Are you sure you want to delete "${productName}"?`)) {
            executeDeleteProduct(productId);
        }
        return;
    }

    Swal.fire({
        title: 'Delete Product?',
        html: `Are you sure you want to delete <strong>${productName}</strong>?<br><span style="font-size: 12px; color: #EF4444;">All associated color variants, images and inventory records will be permanently removed.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#64748B',
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Yes, Delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting Product...',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('api/seller-product-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete',
                    product_id: productId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message || 'Product deleted successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: data.message || 'Could not delete product.',
                        confirmButtonColor: '#0066FF'
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'A network or server error occurred.',
                    confirmButtonColor: '#0066FF'
                });
            });
        }
    });
}
</script>
