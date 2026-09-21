<?php
session_start();
include_once("../include/connection.php");

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("<div class='alert alert-danger p-3 m-3'><i class='fas fa-lock me-2'></i>Unauthorized access. Admin privileges required.</div>");
}
if (!isset($_GET['id'])) {
    die("<div class='alert alert-danger p-3 m-3'><i class='fas fa-exclamation-triangle me-2'></i>Product ID missing.</div>");
}

$product_id = (int)$_GET['id'];

// 1. Transparently sanitize legacy %20 encoded size strings in database
$conn->query("UPDATE color_sizes SET size = REPLACE(size, '%20', ' ') WHERE size LIKE '%\%20%'");

// 2. Fetch Comprehensive Product Information with Category & Brand
$p_query = "SELECT p.*, 
                   c.id AS category_id, c.name AS category_name, 
                   b.name AS brand_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ?";
$stmt = $conn->prepare($p_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$p_res = $stmt->get_result();

if (!($p_row = $p_res->fetch_assoc())) {
    die("<div class='alert alert-danger p-3 m-3'><i class='fas fa-times-circle me-2'></i>Product not found.</div>");
}

$base_price = (float)$p_row['base_price'];
$product_name = $p_row['name'];
$product_sku = !empty($p_row['sku']) ? $p_row['sku'] : (!empty($p_row['product_code']) ? $p_row['product_code'] : 'PRD-' . $product_id);
$category_name = !empty($p_row['category_name']) ? $p_row['category_name'] : 'General';
$category_id = (int)$p_row['category_id'];
$brand_name = !empty($p_row['brand_name']) ? $p_row['brand_name'] : 'Generic';

// 3. Fetch Product Gallery Images
$images_query = "SELECT image_path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC";
$img_stmt = $conn->prepare($images_query);
$img_stmt->bind_param("i", $product_id);
$img_stmt->execute();
$img_res = $img_stmt->get_result();
$product_images = [];
$primary_image = null;

while ($img = $img_res->fetch_assoc()) {
    $product_images[] = $img['image_path'];
    if ($img['is_primary'] == 1 && $primary_image === null) {
        $primary_image = $img['image_path'];
    }
}
if ($primary_image === null && !empty($product_images)) {
    $primary_image = $product_images[0];
}

// 4. Fetch Variants (Colors/Flavors and Sizes)
$colors_query = "SELECT * FROM product_colors WHERE product_id = ? ORDER BY id ASC";
$stmt = $conn->prepare($colors_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$colors_result = $stmt->get_result();

if ($colors_result->num_rows == 0) {
    ?>
    <div class="text-center py-5">
        <div class="mb-3">
            <span class="d-inline-flex p-3 rounded-circle bg-light border">
                <i class="fas fa-boxes-stacked fa-2x text-muted"></i>
            </span>
        </div>
        <h5 class="fw-bold text-dark">No Variants Configured</h5>
        <p class="text-muted small max-w-sm mx-auto">This product doesn't have any variants (colors, flavors, or sizes) set up yet.</p>
    </div>
    <?php
    exit;
}

$variants = [];
$sizes = [];
$colors = [];
$color_meta = [];
$color_stock_totals = [];
$total_qty = 0;
$num_variants = 0;
$out_of_stock_count = 0;

while ($c = $colors_result->fetch_assoc()) {
    $color_id = $c['id'];
    $color_name = trim($c['color_name']);
    
    if (!in_array($color_name, $colors)) {
        $colors[] = $color_name;
        $color_meta[$color_name] = [
            'id' => $color_id,
            'hex' => $c['color_hex'] ?? '',
            'thumbnail' => $c['thumbnail_path'] ?? ''
        ];
        $color_stock_totals[$color_name] = 0;
    }
    
    $sizes_query = "SELECT * FROM color_sizes WHERE color_id = ? ORDER BY id ASC";
    $stmt2 = $conn->prepare($sizes_query);
    $stmt2->bind_param("i", $color_id);
    $stmt2->execute();
    $sizes_result = $stmt2->get_result();
    
    while ($s = $sizes_result->fetch_assoc()) {
        // Clean and urldecode size (e.g. "Single%20Bar" -> "Single Bar")
        $raw_size = $s['size'];
        $size_name = trim(urldecode(str_replace('%20', ' ', $raw_size)));
        if (empty($size_name)) $size_name = 'Standard';
        
        if (!in_array($size_name, $sizes)) {
            $sizes[] = $size_name;
        }
        
        $variants[$size_name][$color_name] = $s;
        $qty = (int)$s['qty'];
        $total_qty += $qty;
        $color_stock_totals[$color_name] += $qty;
        $num_variants++;
        if ($qty === 0) {
            $out_of_stock_count++;
        }
    }
}

// Natural sort sizes (e.g. XS, S, M, L or 1 KG, 2 KG, 5 KG or 8, 9, 10)
usort($sizes, 'strnatcasecmp');

// 5. Category-Aware Dimensional Terminology
$catLower = strtolower($category_name);
$is_nutrition = ($category_id === 4 || strpos($catLower, 'nutrition') !== false || strpos($catLower, 'supplement') !== false);
$is_footwear = ($category_id === 2 || strpos($catLower, 'footwear') !== false || strpos($catLower, 'shoe') !== false);
$is_apparel = ($category_id === 1 || $category_id === 3 || strpos($catLower, 'wear') !== false || strpos($catLower, 'clothing') !== false || strpos($catLower, 'gym') !== false);

if ($is_nutrition) {
    $col_term = 'Flavor';
    $row_term = 'Pack Size / Weight';
    $axis_title = 'PACK SIZE \ FLAVOR';
    $primary_icon = 'fa-cookie-bite';
    $cat_badge_color = 'bg-success-subtle text-success border-success-subtle';
} elseif ($is_footwear) {
    $col_term = 'Colorway';
    $row_term = 'Shoe Size';
    $axis_title = 'SHOE SIZE \ COLOR';
    $primary_icon = 'fa-shoe-prints';
    $cat_badge_color = 'bg-primary-subtle text-primary border-primary-subtle';
} elseif ($is_apparel) {
    $col_term = 'Color';
    $row_term = 'Apparel Size';
    $axis_title = 'SIZE \ COLOR';
    $primary_icon = 'fa-tshirt';
    $cat_badge_color = 'bg-indigo-subtle text-indigo border-indigo-subtle';
} else {
    $col_term = 'Color / Style';
    $row_term = 'Specification';
    $axis_title = 'OPTION \ STYLE';
    $primary_icon = 'fa-tags';
    $cat_badge_color = 'bg-secondary-subtle text-secondary border-secondary-subtle';
}
?>

<div class="variant-manager-wrapper">

    <!-- ========================================== -->
    <!-- 1. PRODUCT HEADER & INVENTORY OVERVIEW BANNER -->
    <!-- ========================================== -->
    <div class="card border border-light-subtle rounded-3 shadow-xs mb-4 overflow-hidden">
        <div class="card-body p-3 p-md-4 bg-white">
            <div class="row align-items-center g-3">
                
                <!-- Product Thumbnail + Name & Meta -->
                <div class="col-lg-7 d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <?php if (!empty($primary_image) && file_exists(__DIR__ . '/../assets/uploads/products/' . $primary_image)): ?>
                            <img src="../assets/uploads/products/<?php echo htmlspecialchars($primary_image); ?>" 
                                 alt="<?php echo htmlspecialchars($product_name); ?>" 
                                 class="rounded-3 border object-fit-cover shadow-xs" 
                                 style="width: 72px; height: 72px; cursor: pointer;"
                                 title="Primary Product Photo">
                        <?php else: ?>
                            <div class="rounded-3 border bg-light d-flex align-items-center justify-content-center text-muted" 
                                 style="width: 72px; height: 72px;">
                                <i class="fas fa-box-open fa-2x"></i>
                            </div>
                        <?php endif; ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary border border-white" style="font-size: 10px;">
                            <i class="fas fa-check"></i>
                        </span>
                    </div>

                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="badge <?php echo $cat_badge_color; ?> border px-2 py-1 small fw-semibold">
                                <i class="fas <?php echo $primary_icon; ?> me-1"></i> <?php echo htmlspecialchars($category_name); ?>
                            </span>
                            <span class="badge bg-light text-dark border px-2 py-1 small fw-medium">
                                <i class="fas fa-shield-alt text-muted me-1"></i> <?php echo htmlspecialchars($brand_name); ?>
                            </span>
                            <span class="badge bg-light text-secondary font-monospace border px-2 py-1 small">
                                SKU: <?php echo htmlspecialchars($product_sku); ?>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1 lh-sm"><?php echo htmlspecialchars($product_name); ?></h5>
                        <div class="text-muted small">
                            Base Price: <strong class="text-dark">Rs. <?php echo number_format($base_price, 2); ?></strong>
                            &bull; Matrix Dimensions: <span class="text-primary fw-semibold"><?php echo count($sizes); ?> <?php echo htmlspecialchars($row_term); ?>s &times; <?php echo count($colors); ?> <?php echo htmlspecialchars($col_term); ?>s</span>
                        </div>
                    </div>
                </div>

                <!-- KPI Metric Badges -->
                <div class="col-lg-5">
                    <div class="d-flex gap-2 justify-content-lg-end">
                        <div class="bg-light px-3 py-2 rounded-3 border text-center flex-fill flex-lg-grow-0" style="min-width: 100px;">
                            <span class="text-muted d-block small" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Total Variants</span>
                            <div class="fw-bold fs-5 text-dark"><?php echo number_format($num_variants); ?></div>
                        </div>
                        <div class="bg-light px-3 py-2 rounded-3 border text-center flex-fill flex-lg-grow-0" style="min-width: 110px;">
                            <span class="text-muted d-block small" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Total Stock</span>
                            <div class="fw-bold fs-5 text-primary"><?php echo number_format($total_qty); ?> <small class="text-muted fs-6 fw-normal">units</small></div>
                        </div>
                        <?php if ($out_of_stock_count > 0): ?>
                            <div class="bg-danger-subtle px-3 py-2 rounded-3 border border-danger-subtle text-center flex-fill flex-lg-grow-0" style="min-width: 100px;">
                                <span class="text-danger d-block small" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Out of Stock</span>
                                <div class="fw-bold fs-5 text-danger"><?php echo number_format($out_of_stock_count); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Product Photos Gallery Strip -->
            <?php if (!empty($product_images)): ?>
                <div class="mt-3 pt-3 border-top d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-bold me-2" style="font-size: 11px; text-transform: uppercase;">
                        <i class="fas fa-images text-primary me-1"></i> Product Photos (<?php echo count($product_images); ?>):
                    </span>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php foreach ($product_images as $idx => $pImg): 
                            $fullImgPath = __DIR__ . '/../assets/uploads/products/' . $pImg;
                            if (file_exists($fullImgPath)):
                        ?>
                            <div class="position-relative gallery-thumb-wrap" title="<?php echo ($idx === 0) ? 'Primary Image' : 'Gallery Image #' . ($idx + 1); ?>">
                                <img src="../assets/uploads/products/<?php echo htmlspecialchars($pImg); ?>" 
                                     alt="Photo <?php echo $idx + 1; ?>" 
                                     class="rounded-2 border object-fit-cover shadow-xs gallery-img-item" 
                                     style="width: 44px; height: 44px; transition: transform 0.2s, border-color 0.2s;">
                                <?php if ($idx === 0): ?>
                                    <span class="position-absolute bottom-0 start-0 end-0 bg-primary text-white text-center" 
                                          style="font-size: 8px; font-weight: 800; line-height: 12px; border-bottom-left-radius: 4px; border-bottom-right-radius: 4px;">
                                        MAIN
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. INVENTORY MATRIX TABLE -->
    <!-- ========================================== -->
    <div class="table-responsive bg-white rounded-3 border shadow-sm mb-3">
        <table class="table table-bordered mb-0 align-middle" style="min-width: 860px;">
            
            <!-- Table Header -->
            <thead class="bg-light">
                <tr>
                    <!-- Axis Label Column (Rows: Size / Pack Size) -->
                    <th class="text-center align-middle bg-light text-secondary fw-bold p-3" 
                        style="width: 170px; font-size: 12px; letter-spacing: 0.5px;">
                        <div class="d-flex flex-column align-items-center justify-content-center">
                            <span class="text-primary small fw-bold mb-1">
                                <i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($axis_title); ?>
                            </span>
                            <span class="text-muted" style="font-size: 10px;">Row: <?php echo htmlspecialchars($row_term); ?></span>
                        </div>
                    </th>

                    <!-- Variant Columns (Flavors / Colors) -->
                    <?php foreach ($colors as $color): 
                        $cMeta = $color_meta[$color] ?? [];
                        $thumbPath = $cMeta['thumbnail'] ?? '';
                        $hexColor = $cMeta['hex'] ?? '';
                        $colStock = $color_stock_totals[$color] ?? 0;
                        $hasThumb = (!empty($thumbPath) && file_exists(__DIR__ . '/../assets/uploads/products/' . $thumbPath));
                    ?>
                        <th class="text-center align-middle bg-light p-3">
                            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                
                                <!-- Thumbnail / Swatch Preview -->
                                <?php if ($hasThumb): ?>
                                    <div class="position-relative variant-thumb-container" title="<?php echo htmlspecialchars($color); ?> thumbnail">
                                        <img src="../assets/uploads/products/<?php echo htmlspecialchars($thumbPath); ?>" 
                                             alt="<?php echo htmlspecialchars($color); ?>" 
                                             class="rounded-circle border object-fit-cover shadow-xs" 
                                             style="width: 38px; height: 38px;">
                                    </div>
                                <?php elseif (!empty($hexColor)): ?>
                                    <span class="color-swatch-sm rounded-circle border shadow-xs d-inline-block" 
                                          style="width: 26px; height: 26px; background-color: <?php echo htmlspecialchars($hexColor); ?>;" 
                                          title="<?php echo htmlspecialchars($hexColor); ?>"></span>
                                <?php else: ?>
                                    <span class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center text-primary" 
                                          style="width: 32px; height: 32px;" title="<?php echo htmlspecialchars($color); ?>">
                                        <i class="fas <?php echo $primary_icon; ?>"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Variant Name -->
                                <div class="fw-bold text-dark text-nowrap" style="font-size: 13.5px;">
                                    <?php echo htmlspecialchars($color); ?>
                                </div>

                                <!-- Column Stock Summary Badge -->
                                <span class="badge <?php echo ($colStock == 0) ? 'bg-danger-subtle text-danger' : 'bg-white text-secondary border'; ?> rounded-pill px-2 py-1" style="font-size: 11px;">
                                    <?php echo number_format($colStock); ?> in stock
                                </span>

                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>

            <!-- Table Body -->
            <tbody>
                <?php foreach ($sizes as $size): ?>
                    <tr>
                        <!-- Row Header (Clean Size / Pack Size) -->
                        <th class="text-center align-middle bg-light-subtle fw-bold p-3 border-end" 
                            style="font-size: 13px; color: #1e293b;">
                            <div class="py-1">
                                <span class="badge bg-white text-dark border px-2.5 py-1.5 fs-6 fw-bold shadow-xs">
                                    <?php echo htmlspecialchars($size); ?>
                                </span>
                            </div>
                        </th>

                        <!-- Data Cells -->
                        <?php foreach ($colors as $color): ?>
                            <?php 
                            if (isset($variants[$size][$color])) {
                                $v = $variants[$size][$color];
                                $qty = (int)$v['qty'];
                                $sku = !empty($v['sku']) ? $v['sku'] : $product_sku . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $color), 0, 3));
                                $price = ($v['selling_price'] > 0) ? (float)$v['selling_price'] : $base_price;
                                
                                // Color badges for stock status
                                if ($qty === 0) {
                                    $qtyClass = 'text-danger bg-danger-subtle border-danger-subtle';
                                    $cellBg = 'bg-danger-subtle-soft';
                                } elseif ($qty < 5) {
                                    $qtyClass = 'text-warning bg-warning-subtle border-warning-subtle';
                                    $cellBg = '';
                                } else {
                                    $qtyClass = 'text-success bg-success-subtle border-success-subtle';
                                    $cellBg = '';
                                }
                            ?>
                                <td class="text-center p-3 align-middle hover-cell position-relative <?php echo $cellBg; ?>" style="transition: background 0.15s;">
                                    
                                    <!-- SKU -->
                                    <div class="small text-muted mb-2 font-monospace" style="font-size: 11px;" title="Variant SKU">
                                        <i class="fas fa-barcode text-muted me-1"></i><?php echo htmlspecialchars($sku); ?>
                                    </div>
                                    
                                    <!-- Qty (Editable) -->
                                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                                        <span class="text-muted fw-medium" style="font-size: 12px;">Qty:</span>
                                        <div class="editable-qty fw-bold px-2.5 py-1 rounded border <?php echo $qtyClass; ?>" 
                                             data-id="<?php echo $v['id']; ?>" 
                                             style="cursor: pointer; min-width: 44px; display: inline-block;" 
                                             title="Double click to edit quantity">
                                            <?php echo $qty; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Price (Editable) -->
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span class="text-muted fw-medium" style="font-size: 12px;">Price:</span>
                                        <div class="editable-price fw-bold px-2.5 py-1 rounded bg-light border text-dark" 
                                             data-id="<?php echo $v['id']; ?>" 
                                             style="cursor: pointer; font-size: 12.5px;" 
                                             title="Double click to edit price">
                                            Rs. <?php echo number_format($price, 2); ?>
                                        </div>
                                    </div>

                                </td>
                            <?php } else { ?>
                                <!-- Not Configured Placeholder -->
                                <td class="text-center align-middle bg-light text-muted" style="opacity: 0.45;" title="Variant combination not defined">
                                    <i class="fas fa-minus text-muted"></i>
                                </td>
                            <?php } ?>
                        <?php endforeach; ?>

                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>

    <!-- ========================================== -->
    <!-- 3. FOOTER LEGEND & QUICK TIPS -->
    <!-- ========================================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 pt-2">
        <div class="text-muted small">
            <i class="fas fa-info-circle text-primary me-1"></i> Double-click any <strong class="text-dark">Qty</strong> or <strong class="text-dark">Price</strong> badge to edit values inline. Press <kbd class="bg-light text-dark border px-1">Enter</kbd> to save.
        </div>
        <div class="d-flex gap-3 text-muted small fw-medium flex-wrap">
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle bg-success" style="width: 10px; height: 10px;"></span> 
                <span>Healthy (&ge; 5)</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle bg-warning" style="width: 10px; height: 10px;"></span> 
                <span>Low Stock (&lt; 5)</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="d-inline-block rounded-circle bg-danger" style="width: 10px; height: 10px;"></span> 
                <span>Out of Stock (0)</span>
            </div>
        </div>
    </div>

</div>

<style>
.hover-cell:hover { 
    background-color: #f8fafc !important; 
    box-shadow: inset 0 0 0 2px #3b82f6; 
}
.bg-danger-subtle-soft {
    background-color: #fff5f5 !important;
}
.gallery-img-item:hover {
    transform: scale(1.1);
    border-color: #0066FF !important;
}
.bg-success-subtle { background-color: #d1fae5; color: #065f46; }
.bg-warning-subtle { background-color: #ffedd5; color: #9a3412; }
.bg-danger-subtle { background-color: #fee2e2; color: #991b1b; }
.bg-primary-subtle { background-color: #eff6ff; color: #1d4ed8; }
.bg-indigo-subtle { background-color: #e0e7ff; color: #4338ca; }
.bg-secondary-subtle { background-color: #f1f5f9; color: #475569; }
.shadow-xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
input[type=number]::-webkit-inner-spin-button { opacity: 1; }
</style>
