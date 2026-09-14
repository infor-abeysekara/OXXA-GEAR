<?php
$page_title = 'Shop - OXXA GEAR';
include('../include/header.php');

$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$category_slug = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$brands = isset($_GET['brand']) ? (is_array($_GET['brand']) ? $_GET['brand'] : [$_GET['brand']]) : [];
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$category_name = 'All Products';
$category_id = null;
if (!empty($category_slug)) {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE slug = ?");
    $stmt->execute([$category_slug]);
    $catRow = $stmt->fetch();
    if ($catRow) {
        $category_name = $catRow['name'];
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
    // We check against p.base_price directly
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

// Get all categories for sidebar
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$all_categories = $catStmt->fetchAll();

// Get brands for sidebar filter
if ($category_id !== null) {
    $brandQuery = "SELECT DISTINCT b.* FROM brands b JOIN brand_category bc ON b.id = bc.brand_id WHERE bc.category_id = ? AND b.is_active = 1 ORDER BY b.name ASC";
    $brandStmt = $pdo->prepare($brandQuery);
    $brandStmt->execute([$category_id]);
    $all_brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $brandQuery = "SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC";
    $brandStmt = $pdo->query($brandQuery);
    $all_brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper for generating filter URLs
function buildFilterUrl($updates) {
    $query = $_GET;
    // reset page to 1 on filter change
    if(!isset($updates['page'])) {
        $query['page'] = 1;
    }
    foreach ($updates as $key => $val) {
        if ($val === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $val;
        }
    }
    return 'shop.php?' . http_build_query($query);
}
?>
<!-- noUiSlider CSS & JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js"></script>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
    
    <!-- Active Filter Tags Container -->
    <div id="activeFiltersContainer" class="flex flex-wrap gap-2 mb-4 empty:hidden"></div>
    
    <!-- Mobile Filter Toggle -->
    <div class="lg:hidden mb-6 flex justify-between items-center bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
        <h1 class="text-xl font-black text-navy uppercase"><?php echo htmlspecialchars($category_name); ?></h1>
        <button id="mobileFilterBtn" class="flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-xl font-bold text-sm text-navy hover:bg-gray-100 transition-colors">
            <i class="fas fa-filter"></i> Filters
        </button>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- Left Sidebar (Filters) -->
        <div id="filterSidebar" class="w-full lg:w-1/4 hidden lg:block space-y-8 bg-white p-6 rounded-2xl shadow-sm border border-gray-100 h-fit sticky top-24">
            <div class="flex justify-between items-center lg:hidden mb-6">
                <h3 class="font-black text-navy uppercase text-lg">Filters</h3>
                <button id="closeFilterBtn" class="text-gray-400 hover:text-navy"><i class="fas fa-times text-xl"></i></button>
            </div>

            <form action="shop.php" method="GET" id="filterForm">
                <input type="hidden" name="category" id="ajaxCategory" value="<?php echo htmlspecialchars($category_slug); ?>">
                <input type="hidden" name="search" id="ajaxSearch" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="sort" id="ajaxSort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="page" id="ajaxPage" value="<?php echo $page; ?>">

                <!-- Brands -->
                <?php if(!empty($all_brands)): ?>
                <div class="mb-8">
                    <h4 class="font-black text-navy uppercase text-sm mb-4 tracking-wide">Brands</h4>
                    <div class="space-y-3 max-h-60 overflow-y-auto custom-scrollbar pr-2">
                        <?php foreach($all_brands as $b): ?>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <div class="relative flex items-center justify-center">
                                    <input type="checkbox" name="brand[]" value="<?php echo htmlspecialchars($b['id']); ?>" data-name="<?php echo htmlspecialchars($b['name']); ?>" <?php echo in_array($b['id'], $brands) ? 'checked' : ''; ?> class="ajax-filter-input peer appearance-none w-5 h-5 border-2 border-gray-300 rounded focus:outline-none focus:ring-0 checked:bg-[#0066FF] checked:border-[#0066FF] transition-all cursor-pointer">
                                    <i class="fas fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                                </div>
                                <?php if($b['logo_image']): ?>
                                    <img src="../assets/uploads/brands/<?php echo htmlspecialchars($b['logo_image']); ?>" class="w-6 h-6 rounded object-cover" alt="">
                                <?php else: ?>
                                    <div class="w-6 h-6 bg-gray-100 rounded flex items-center justify-center text-[10px] text-gray-400"><i class="fas fa-tag"></i></div>
                                <?php endif; ?>
                                <span class="text-sm font-bold text-gray-600 group-hover:text-navy transition-colors flex-1"><?php echo htmlspecialchars($b['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <hr class="border-gray-100 mb-8">

                <!-- Price Range Slider -->
                <div class="mb-8">
                    <h4 class="font-black text-navy uppercase text-sm mb-6 tracking-wide">Price Range</h4>
                    
                    <div id="priceSlider" class="mb-6 mx-2"></div>
                    
                    <div class="flex items-center gap-2 mb-6">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-xs text-gray-400 font-bold">Rs.</span>
                            <input type="number" id="inputMinPrice" name="min_price" value="<?php echo $min_price > 0 ? $min_price : 0; ?>" class="w-full pl-8 pr-2 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-navy focus:outline-none focus:border-[#0066FF] transition-colors">
                        </div>
                        <span class="text-gray-400">-</span>
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-xs text-gray-400 font-bold">Rs.</span>
                            <input type="number" id="inputMaxPrice" name="max_price" value="<?php echo $max_price > 0 ? $max_price : 50000; ?>" class="w-full pl-8 pr-2 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-navy focus:outline-none focus:border-[#0066FF] transition-colors">
                        </div>
                    </div>

                    <!-- Quick Price Chips -->
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="price-chip px-3 py-1 bg-gray-50 border border-gray-200 rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100 hover:border-gray-300 transition-colors" data-min="0" data-max="5000">Under 5,000</button>
                        <button type="button" class="price-chip px-3 py-1 bg-gray-50 border border-gray-200 rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100 hover:border-gray-300 transition-colors" data-min="5000" data-max="10000">5k - 10k</button>
                        <button type="button" class="price-chip px-3 py-1 bg-gray-50 border border-gray-200 rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100 hover:border-gray-300 transition-colors" data-min="10000" data-max="20000">10k - 20k</button>
                        <button type="button" class="price-chip px-3 py-1 bg-gray-50 border border-gray-200 rounded-full text-xs font-bold text-gray-600 hover:bg-gray-100 hover:border-gray-300 transition-colors" data-min="20000" data-max="50000">Over 20k</button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Right Side (Product Grid) -->
        <div class="w-full lg:w-3/4">
            
            <!-- Header/Sorting -->
            <div class="hidden lg:flex justify-between items-end mb-8 border-b border-gray-100 pb-4">
                <div>
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                        <a href="index.php" class="hover:text-[#0066FF] transition-colors">Home</a>
                        <span class="mx-2">/</span>
                        <span class="text-navy"><?php echo htmlspecialchars($category_name); ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-black text-navy uppercase tracking-wide"><?php echo htmlspecialchars($category_name); ?></h1>
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs font-bold rounded-full"><?php echo $total_items; ?> Items</span>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <label class="text-sm font-bold text-gray-500 uppercase">Sort By:</label>
                    <select id="sortSelect" onchange="document.getElementById('ajaxSort').value = this.value; fetchProducts();" class="bg-gray-50 border border-gray-200 text-navy text-sm font-bold rounded-xl px-4 py-2 focus:outline-none focus:border-[#0066FF] cursor-pointer appearance-none pr-8 relative">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Products Grid Container -->
            <div id="productGridContainer">
            <?php if (empty($products)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100 flex flex-col items-center justify-center min-h-[400px]">
                    <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-box-open text-gray-300 text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-navy mb-2 uppercase">No Products Found</h3>
                    <p class="text-gray-500 max-w-md mx-auto mb-8">We couldn't find any products matching your current filters. Try adjusting your categories, brands, or price range.</p>
                    <a href="shop.php" class="px-8 py-3 bg-navy text-white font-bold uppercase tracking-wide text-sm rounded-xl hover:bg-black transition-colors shadow-lg shadow-black/10 flex items-center gap-2">
                        <i class="fas fa-undo-alt"></i> Clear All Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
                    <?php foreach ($products as $p): ?>
                        <div class="group relative bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col">
                            
                            <!-- Wishlist Button -->
                            <button class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-white transition-all shadow-sm">
                                <i class="far fa-heart"></i>
                            </button>

                            <!-- Image -->
                            <a href="product.php?id=<?php echo $p['id']; ?>" class="block relative aspect-square bg-gray-50 overflow-hidden">
                                <?php if(!empty($p['image'])): ?>
                                    <img src="../image/<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center"><i class="fas fa-image text-gray-300 text-4xl"></i></div>
                                <?php endif; ?>
                            </a>

                            <!-- Content -->
                            <div class="p-4 flex flex-col flex-grow">
                                <?php if(!empty($p['brand'])): ?>
                                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-wider mb-1 line-clamp-1"><?php echo htmlspecialchars($p['brand']); ?></span>
                                <?php endif; ?>
                                
                                <a href="product.php?id=<?php echo $p['id']; ?>" class="text-navy font-bold text-sm mb-2 line-clamp-2 hover:text-[#0066FF] transition-colors leading-tight">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </a>
                                
                                <div class="mt-auto pt-3 flex items-center justify-between border-t border-gray-50">
                                    <div class="font-black text-navy text-sm md:text-base">
                                        Rs. <?php echo number_format($p['lowest_price'], 2); ?>
                                    </div>
                                    <button class="w-8 h-8 rounded-full bg-gray-50 text-gray-600 hover:bg-[#0066FF] hover:text-white transition-colors flex items-center justify-center">
                                        <i class="fas fa-plus text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <!-- Pagination -->
            </div>
            
            <div id="paginationContainer" class="mt-12 flex justify-center">
            <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center gap-2" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <button type="button" onclick="changePage(<?php echo $page - 1; ?>)" class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </button>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="w-10 h-10 rounded-xl bg-[#0066FF] text-white font-bold flex items-center justify-center shadow-md shadow-blue-500/30">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <button type="button" onclick="changePage(<?php echo $i; ?>)" class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-600 font-bold hover:bg-gray-50 hover:text-navy transition-colors">
                                    <?php echo $i; ?>
                                </button>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <button type="button" onclick="changePage(<?php echo $page + 1; ?>)" class="w-10 h-10 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-navy transition-colors">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        <?php endif; ?>
                    </nav>
            <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileFilterBtn = document.getElementById('mobileFilterBtn');
    const closeFilterBtn = document.getElementById('closeFilterBtn');
    const filterSidebar = document.getElementById('filterSidebar');

    if (mobileFilterBtn && filterSidebar) {
        mobileFilterBtn.addEventListener('click', () => {
            filterSidebar.classList.remove('hidden');
            filterSidebar.classList.add('fixed', 'inset-0', 'z-50', 'w-full', 'h-full', 'overflow-y-auto', 'rounded-none');
            // Hide header so it doesn't overlap
            document.querySelector('header').style.zIndex = '0';
        });
    }

    if (closeFilterBtn && filterSidebar) {
        closeFilterBtn.addEventListener('click', () => {
            filterSidebar.classList.add('hidden');
            filterSidebar.classList.remove('fixed', 'inset-0', 'z-50', 'w-full', 'h-full', 'overflow-y-auto', 'rounded-none');
            document.querySelector('header').style.zIndex = '50';
        });
    }

    // --- AJAX & NO UI SLIDER LOGIC ---
    const slider = document.getElementById('priceSlider');
    const inputMin = document.getElementById('inputMinPrice');
    const inputMax = document.getElementById('inputMaxPrice');
    const form = document.getElementById('filterForm');
    const activeFiltersContainer = document.getElementById('activeFiltersContainer');
    const gridContainer = document.getElementById('productGridContainer');
    const paginationContainer = document.getElementById('paginationContainer');

    // Initialize Slider
    noUiSlider.create(slider, {
        start: [<?php echo $min_price ?: 0; ?>, <?php echo $max_price ?: 50000; ?>],
        connect: true,
        step: 500,
        range: {
            'min': 0,
            'max': 50000
        },
        format: {
            to: function (value) { return Math.round(value); },
            from: function (value) { return Number(value); }
        }
    });

    slider.noUiSlider.on('update', function (values, handle) {
        if (handle) {
            inputMax.value = values[handle];
        } else {
            inputMin.value = values[handle];
        }
    });

    slider.noUiSlider.on('change', function () {
        document.getElementById('ajaxPage').value = 1;
        fetchProducts();
    });

    inputMin.addEventListener('change', function () {
        slider.noUiSlider.set([this.value, null]);
        document.getElementById('ajaxPage').value = 1;
        fetchProducts();
    });

    inputMax.addEventListener('change', function () {
        slider.noUiSlider.set([null, this.value]);
        document.getElementById('ajaxPage').value = 1;
        fetchProducts();
    });

    // Price Chips
    document.querySelectorAll('.price-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            let min = this.getAttribute('data-min');
            let max = this.getAttribute('data-max');
            slider.noUiSlider.set([min, max]);
            document.getElementById('ajaxPage').value = 1;
            fetchProducts();
        });
    });

    // Brand Checkboxes
    document.querySelectorAll('.ajax-filter-input').forEach(input => {
        input.addEventListener('change', function() {
            document.getElementById('ajaxPage').value = 1;
            fetchProducts();
        });
    });

}); // end DOMContentLoaded

function fetchProducts() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    
    // Add loading state
    document.getElementById('productGridContainer').style.opacity = '0.5';

    fetch('../Backend/shop-ajax-backend.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('productGridContainer').innerHTML = data.html;
        document.getElementById('productGridContainer').style.opacity = '1';
        document.getElementById('paginationContainer').innerHTML = data.pagination;
        updateActiveTags();
    })
    .catch(error => {
        console.error('Error fetching products:', error);
        document.getElementById('productGridContainer').style.opacity = '1';
    });
}

function changePage(page) {
    document.getElementById('ajaxPage').value = page;
    fetchProducts();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateActiveTags() {
    const container = document.getElementById('activeFiltersContainer');
    container.innerHTML = '';
    
    let hasFilters = false;

    // Category
    const cat = document.getElementById('ajaxCategory').value;
    if (cat) {
        hasFilters = true;
        container.innerHTML += `<span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 text-[#0066FF] border border-blue-200 rounded-full text-xs font-bold">Category: ${cat}</span>`;
    }

    // Brands
    document.querySelectorAll('input[name="brand[]"]:checked').forEach(cb => {
        hasFilters = true;
        let name = cb.getAttribute('data-name');
        container.innerHTML += `
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded-full text-xs font-bold">
                Brand: ${name} 
                <button type="button" onclick="removeBrandFilter('${cb.value}')" class="hover:text-red-500 ms-1"><i class="fas fa-times"></i></button>
            </span>`;
    });

    // Price
    const min = document.getElementById('inputMinPrice').value;
    const max = document.getElementById('inputMaxPrice').value;
    if (min > 0 || max < 50000) {
        hasFilters = true;
        container.innerHTML += `
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded-full text-xs font-bold">
                Price: Rs.${min} - Rs.${max}
                <button type="button" onclick="removePriceFilter()" class="hover:text-red-500 ms-1"><i class="fas fa-times"></i></button>
            </span>`;
    }

    if (hasFilters) {
        container.innerHTML += `<button type="button" onclick="clearAllFilters()" class="text-xs font-bold text-red-500 hover:underline ms-2">Clear All</button>`;
    }
}

function removeBrandFilter(val) {
    document.querySelector(`input[name="brand[]"][value="${val}"]`).checked = false;
    document.getElementById('ajaxPage').value = 1;
    fetchProducts();
}

function removePriceFilter() {
    const slider = document.getElementById('priceSlider');
    slider.noUiSlider.set([0, 50000]);
    document.getElementById('ajaxPage').value = 1;
    fetchProducts();
}

function clearAllFilters() {
    document.querySelectorAll('input[name="brand[]"]').forEach(cb => cb.checked = false);
    const slider = document.getElementById('priceSlider');
    slider.noUiSlider.set([0, 50000]);
    document.getElementById('ajaxSearch').value = '';
    // Optionally clear category too, or keep it. We usually keep category context in clear all.
    document.getElementById('ajaxPage').value = 1;
    fetchProducts();
}

// Call update tags on initial load
document.addEventListener('DOMContentLoaded', updateActiveTags);

<style>
/* Custom Scrollbar for brands list */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1; 
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db; 
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af; 
}
</style>

<?php include("../include/footer.php"); ?>
