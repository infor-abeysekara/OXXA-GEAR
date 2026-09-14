<?php
include('../include/connection.php');

$limit = 12;
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$category_slug = isset($_POST['category']) ? trim($_POST['category']) : '';
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$brands = isset($_POST['brand']) ? (is_array($_POST['brand']) ? $_POST['brand'] : [$_POST['brand']]) : [];
$min_price = isset($_POST['min_price']) ? (float)$_POST['min_price'] : 0;
$max_price = isset($_POST['max_price']) ? (float)$_POST['max_price'] : 0;
$sort = isset($_POST['sort']) ? $_POST['sort'] : 'newest';

$category_id = null;
if (!empty($category_slug)) {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmt->execute([$category_slug]);
    $catRow = $stmt->fetch();
    if ($catRow) {
        $category_id = $catRow['id'];
    }
}

// Build query
$whereClause = "WHERE p.is_approved = 1 AND p.status = 'active'";
$params = [];

if ($category_id !== null) {
    $whereClause .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $whereClause .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($brands)) {
    $placeholders = str_repeat('?,', count($brands) - 1) . '?';
    $whereClause .= " AND p.brand_id IN ($placeholders)";
    $params = array_merge($params, $brands);
}

if ($min_price > 0) {
    $whereClause .= " AND p.base_price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $whereClause .= " AND p.base_price <= ?";
    $params[] = $max_price;
}

$orderBy = "ORDER BY p.created_at DESC";
if ($sort === 'price_asc') {
    $orderBy = "ORDER BY lowest_price ASC";
} elseif ($sort === 'price_desc') {
    $orderBy = "ORDER BY lowest_price DESC";
}

// Count total items
$countQuery = "SELECT COUNT(*) FROM products p $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Get items
$query = "SELECT p.*, 
                 COALESCE((SELECT MIN(ps.price) FROM product_variants ps WHERE ps.product_id = p.id AND ps.qty > 0 AND ps.price > 0), p.base_price) as lowest_price,
                 (SELECT SUM(ps.qty) FROM product_variants ps WHERE ps.product_id = p.id) as var_qty,
                 (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as image,
                 (SELECT name FROM brands b WHERE b.id = p.brand_id) as brand_name
          FROM products p 
          $whereClause
          GROUP BY p.id
          $orderBy
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

ob_start();

if (empty($products)) {
    ?>
    <div class="col-span-2 md:col-span-3 xl:col-span-4 bg-white rounded-3xl p-12 text-center border border-gray-100 flex flex-col items-center justify-center min-h-[400px]">
        <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-6">
            <i class="fas fa-box-open text-gray-300 text-4xl"></i>
        </div>
        <h3 class="text-2xl font-black text-navy mb-2 uppercase">No Products Found</h3>
        <p class="text-gray-500 max-w-md mx-auto mb-8">We couldn't find any products matching your current filters. Try adjusting your brands or price range.</p>
        <button type="button" onclick="clearAllFilters()" class="px-8 py-3 bg-navy text-white font-bold uppercase tracking-wide text-sm rounded-xl hover:bg-black transition-colors shadow-lg shadow-black/10 flex items-center gap-2">
            <i class="fas fa-undo-alt"></i> Clear All Filters
        </button>
    </div>
    <?php
} else {
    foreach ($products as $p) {
        ?>
        <div class="group relative bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col">
            
            <?php if ($p['var_qty'] <= 0): ?>
                <div class="absolute top-3 left-3 z-10 bg-red-500 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md shadow-sm">
                    Out of Stock
                </div>
            <?php endif; ?>

            <button class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-white transition-all shadow-sm">
                <i class="fas fa-heart text-sm"></i>
            </button>
            
            <a href="product-details.php?id=<?= $p['id'] ?>" class="block relative aspect-[4/5] bg-gray-50 overflow-hidden">
                <?php if ($p['image']): ?>
                    <img src="../assets/uploads/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <div class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                        <i class="fas fa-image text-3xl mb-2"></i>
                        <span class="text-xs uppercase font-bold">No Image</span>
                    </div>
                <?php endif; ?>
            </a>

            <div class="p-4 flex flex-col flex-grow">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">
                    <?= htmlspecialchars($p['brand_name'] ?? 'Generic') ?>
                </div>
                
                <a href="product-details.php?id=<?= $p['id'] ?>" class="text-sm font-bold text-navy leading-tight mb-2 hover:text-[#0066FF] transition-colors line-clamp-2">
                    <?= htmlspecialchars($p['name']) ?>
                </a>

                <div class="mt-auto pt-3 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-gray-500 font-bold">Rs.</span>
                        <span class="text-lg font-black text-navy"><?= number_format($p['lowest_price'], 2) ?></span>
                    </div>
                    <button class="w-9 h-9 bg-navy hover:bg-[#0066FF] text-white rounded-xl flex items-center justify-center transition-colors shadow-md <?php echo $p['var_qty'] <= 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $p['var_qty'] <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-cart-plus"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
$html = ob_get_clean();

ob_start();
if ($total_pages > 1) {
    ?>
    <nav class="flex items-center gap-2" aria-label="Pagination">
        <!-- Previous -->
        <button type="button" onclick="changePage(<?= $page - 1 ?>)" class="w-10 h-10 flex items-center justify-center rounded-xl font-bold text-sm transition-colors border border-gray-200 <?php echo $page <= 1 ? 'text-gray-300 bg-gray-50 cursor-not-allowed' : 'text-navy hover:bg-gray-50 hover:border-gray-300'; ?>" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
            <i class="fas fa-chevron-left"></i>
        </button>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php 
            if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)) {
                $is_current = ($i == $page);
                ?>
                <button type="button" onclick="changePage(<?= $i ?>)" class="w-10 h-10 flex items-center justify-center rounded-xl font-bold text-sm transition-colors border <?php echo $is_current ? 'bg-[#0066FF] text-white border-[#0066FF] shadow-md shadow-blue-500/20' : 'bg-white text-navy border-gray-200 hover:bg-gray-50 hover:border-gray-300'; ?>">
                    <?= $i ?>
                </button>
                <?php
            } elseif ($i == $page - 3 || $i == $page + 3) {
                echo '<span class="w-10 h-10 flex items-center justify-center text-gray-400 font-bold">...</span>';
            }
            ?>
        <?php endfor; ?>

        <!-- Next -->
        <button type="button" onclick="changePage(<?= $page + 1 ?>)" class="w-10 h-10 flex items-center justify-center rounded-xl font-bold text-sm transition-colors border border-gray-200 <?php echo $page >= $total_pages ? 'text-gray-300 bg-gray-50 cursor-not-allowed' : 'text-navy hover:bg-gray-50 hover:border-gray-300'; ?>" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
            <i class="fas fa-chevron-right"></i>
        </button>
    </nav>
    <?php
}
$pagination = ob_get_clean();

echo json_encode([
    'html' => $html,
    'pagination' => $pagination,
    'total_items' => $total_items
]);
?>
