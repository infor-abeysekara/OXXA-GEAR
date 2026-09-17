<?php
session_start();
include_once("../include/connection.php");

if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("<div class='alert alert-danger'>Unauthorized access.</div>");
}
if(!isset($_GET['id'])) {
    die("<div class='alert alert-danger'>Product ID missing.</div>");
}

$product_id = (int)$_GET['id'];
$p_query = "SELECT base_price FROM products WHERE id = ?";
$stmt = $conn->prepare($p_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$p_res = $stmt->get_result();
if($p_row = $p_res->fetch_assoc()) {
    $base_price = $p_row['base_price'];
} else {
    die("<div class='alert alert-danger'>Product not found.</div>");
}

$colors_query = "SELECT * FROM product_colors WHERE product_id = ?";
$stmt = $conn->prepare($colors_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$colors_result = $stmt->get_result();

if($colors_result->num_rows == 0) {
    echo "<div class='text-center py-4'><i class='fas fa-box-open fa-3x text-muted mb-3'></i><h5>No variants setup</h5><p class='text-muted'>This product currently has no colors or sizes defined.</p></div>";
    exit;
}

$variants = [];
$sizes = [];
$colors = [];
$total_qty = 0;
$num_variants = 0;

while($c = $colors_result->fetch_assoc()) {
    $color_id = $c['id'];
    $color_name = $c['color_name'];
    if(!in_array($color_name, $colors)) $colors[] = $color_name;
    
    $sizes_query = "SELECT * FROM color_sizes WHERE color_id = ?";
    $stmt2 = $conn->prepare($sizes_query);
    $stmt2->bind_param("i", $color_id);
    $stmt2->execute();
    $sizes_result = $stmt2->get_result();
    
    while($s = $sizes_result->fetch_assoc()) {
        $size_name = $s['size'];
        if(!in_array($size_name, $sizes)) $sizes[] = $size_name;
        
        $variants[$size_name][$color_name] = $s;
        $total_qty += $s['qty'];
        $num_variants++;
    }
}
usort($sizes, 'strnatcmp'); 
sort($colors);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold text-dark mb-1">Inventory Matrix</h5>
        <p class="text-muted mb-0 font-size-14">View and adjust stock levels across all variations</p>
    </div>
    <div class="d-flex gap-2">
        <div class="bg-light px-3 py-2 rounded-3 border">
            <span class="text-muted font-size-14">Total Variants</span>
            <div class="fw-bold fs-5"><?php echo number_format($num_variants); ?></div>
        </div>
        <div class="bg-light px-3 py-2 rounded-3 border">
            <span class="text-muted font-size-14">Total Stock</span>
            <div class="fw-bold fs-5 text-primary"><?php echo number_format($total_qty); ?></div>
        </div>
    </div>
</div>

<div class="table-responsive bg-white rounded-3 border shadow-sm">
    <table class="table table-bordered mb-0" style="min-width: 800px;">
        <thead class="bg-light">
            <tr>
                <th class="text-center align-middle bg-light text-muted fw-bold" style="width: 150px; text-transform: uppercase; font-size: 13px;">Size \ Color</th>
                <?php foreach($colors as $color): ?>
                    <th class="text-center align-middle bg-light fw-bold">
                        <div class="d-inline-flex align-items-center gap-2">
                            <span class="color-swatch-sm" style="width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo strtolower($color); ?>; border: 1px solid #ddd;"></span>
                            <?php echo htmlspecialchars($color); ?>
                        </div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($sizes as $size): ?>
                <tr>
                    <th class="text-center align-middle bg-light fw-bold" style="font-size: 14px;"><?php echo htmlspecialchars($size); ?></th>
                    <?php foreach($colors as $color): ?>
                        <?php 
                        if(isset($variants[$size][$color])) {
                            $v = $variants[$size][$color];
                            $qty = $v['qty'];
                            $sku = $v['sku'];
                            $price = ($v['selling_price'] > 0) ? $v['selling_price'] : $base_price;
                            
                            $qtyClass = ($qty == 0) ? 'text-danger bg-danger-subtle' : (($qty < 5) ? 'text-warning bg-warning-subtle' : 'text-success bg-success-subtle');
                        ?>
                            <td class="text-center p-3 align-middle hover-cell position-relative" style="transition: background 0.2s;">
                                <div class="small text-muted mb-1 font-monospace" style="font-size: 11px;"><?php echo htmlspecialchars($sku); ?></div>
                                
                                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                    <span class="text-muted" style="font-size: 12px;">Qty:</span>
                                    <div class="editable-qty fw-bold px-2 py-1 rounded <?php echo $qtyClass; ?>" data-id="<?php echo $v['id']; ?>" style="cursor: pointer; min-width: 40px; display: inline-block;" title="Double click to edit quantity">
                                        <?php echo $qty; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <span class="text-muted" style="font-size: 12px;">Price:</span>
                                    <div class="editable-price fw-medium px-2 py-1 rounded bg-light" data-id="<?php echo $v['id']; ?>" style="cursor: pointer; font-size: 13px;" title="Double click to edit price">
                                        Rs. <?php echo number_format($price, 2); ?>
                                    </div>
                                </div>
                            </td>
                        <?php } else { ?>
                            <td class="text-center align-middle bg-light text-muted" style="opacity: 0.5;">
                                <i class="fas fa-minus"></i>
                            </td>
                        <?php } ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center">
    <div class="text-muted small">
        <i class="fas fa-info-circle text-primary me-1"></i> Double-click any <strong class="text-dark">Qty</strong> or <strong class="text-dark">Price</strong> cell to quickly edit inline.
    </div>
    <div class="d-flex gap-3 text-muted small fw-medium">
        <div><span class="d-inline-block w-20px h-20px rounded bg-success-subtle me-1" style="width: 12px; height: 12px;"></span> Healthy Stock</div>
        <div><span class="d-inline-block w-20px h-20px rounded bg-warning-subtle me-1" style="width: 12px; height: 12px;"></span> Low Stock (< 5)</div>
        <div><span class="d-inline-block w-20px h-20px rounded bg-danger-subtle me-1" style="width: 12px; height: 12px;"></span> Out of Stock</div>
    </div>
</div>

<style>
.hover-cell:hover { background-color: #f8fafc !important; }
.bg-success-subtle { background-color: #d1fae5; color: #065f46; }
.bg-warning-subtle { background-color: #ffedd5; color: #9a3412; }
.bg-danger-subtle { background-color: #fee2e2; color: #991b1b; }
input[type=number]::-webkit-inner-spin-button { opacity: 1; }
</style>
