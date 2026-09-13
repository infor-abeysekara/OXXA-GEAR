<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

$product_id = sanitizeInput($_GET['id'] ?? '');

if (empty($product_id)) {
    echo '<div class="alert alert-danger">Invalid product ID</div>';
    exit;
}

// Get product details with sizes
$query = "SELECT p.*, u.firstname, u.lastname, br.bname 
          FROM production p 
          LEFT JOIN users u ON p.user_id = u.user_id 
          LEFT JOIN businessregistration br ON p.user_id = br.user_id 
          WHERE p.pid = ? AND p.approve = 1 AND p.status = 'active'";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Product not found</div>';
    exit;
}

$product = $result->fetch_assoc();

// Get product sizes
$sizesQuery = "SELECT * FROM productsize WHERE pid = ? AND qty > 0 ORDER BY price ASC";
$sizesStmt = $conn->prepare($sizesQuery);
$sizesStmt->bind_param("s", $product_id);
$sizesStmt->execute();
$sizesResult = $sizesStmt->get_result();
$sizes = $sizesResult->fetch_all(MYSQLI_ASSOC);

// Calculate total stock
$totalStock = $product['qty'];
foreach ($sizes as $size) {
    $totalStock += $size['qty'];
}
?>

<div class="row">
    <!-- Product Image -->
    <div class="col-md-6">
        <div class="product-image-container mb-4">
            <?php if (!empty($product['image'])): ?>
                <img src="../image/<?php echo htmlspecialchars($product['image']); ?>" 
                     class="img-fluid rounded shadow" 
                     alt="<?php echo htmlspecialchars($product['pname']); ?>"
                     style="width: 100%; max-height: 400px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-gray-600 rounded d-flex align-items-center justify-content-center" 
                     style="width: 100%; height: 400px;">
                    <i class="fas fa-image fa-5x text-gray-400"></i>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Seller Information -->
        <div class="seller-info bg-gray-800 rounded p-3">
            <h6 class="text-success mb-2">
                <i class="fas fa-store me-2"></i>Seller Information
            </h6>
            <p class="text-white mb-1">
                <strong><?php echo htmlspecialchars($product['firstname'] . ' ' . $product['lastname']); ?></strong>
            </p>
            <?php if (!empty($product['bname'])): ?>
                <p class="text-gray-400 mb-0">
                    <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($product['bname']); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Product Details -->
    <div class="col-md-6">
        <div class="product-details">
            <!-- Product Title and Category -->
            <h3 class="text-white mb-2"><?php echo htmlspecialchars($product['pname']); ?></h3>
            <div class="mb-3">
                <span class="badge bg-success me-2"><?php echo htmlspecialchars($product['brand']); ?></span>
                <span class="badge bg-secondary"><?php echo htmlspecialchars($product['categories']); ?></span>
            </div>
            
            <!-- Description -->
            <div class="mb-4">
                <h6 class="text-success">Description</h6>
                <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($product['discription'])); ?></p>
            </div>
            
            <!-- Size and Price Selection -->
            <div class="mb-4">
                <h6 class="text-success">Select Size & Price</h6>
                <div class="size-options">
                    <!-- Standard Size -->
                    <?php if ($product['qty'] > 0): ?>
                        <div class="form-check size-option mb-2 p-3 border border-gray-600 rounded" 
                             data-size="Standard" data-price="<?php echo $product['price']; ?>" 
                             data-stock="<?php echo $product['qty']; ?>">
                            <input class="form-check-input" type="radio" name="product_size" 
                                   id="size_standard" value="Standard" checked>
                            <label class="form-check-label w-100 text-white" for="size_standard">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Standard</strong>
                                        <small class="d-block text-gray-400">Stock: <?php echo $product['qty']; ?></small>
                                    </div>
                                    <div class="text-success fw-bold">
                                        Rs. <?php echo number_format($product['price'], 2); ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Additional Sizes -->
                    <?php foreach ($sizes as $size): ?>
                        <div class="form-check size-option mb-2 p-3 border border-gray-600 rounded" 
                             data-size="<?php echo $size['size']; ?>" data-price="<?php echo $size['price']; ?>" 
                             data-stock="<?php echo $size['qty']; ?>">
                            <input class="form-check-input" type="radio" name="product_size" 
                                   id="size_<?php echo $size['id']; ?>" value="<?php echo $size['size']; ?>"
                                   <?php echo ($product['qty'] == 0 && $size === reset($sizes)) ? 'checked' : ''; ?>>
                            <label class="form-check-label w-100 text-white" for="size_<?php echo $size['id']; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($size['size']); ?></strong>
                                        <small class="d-block text-gray-400">Stock: <?php echo $size['qty']; ?></small>
                                    </div>
                                    <div class="text-success fw-bold">
                                        Rs. <?php echo number_format($size['price'], 2); ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ($totalStock > 0 && isset($_SESSION['userid'])): ?>
                <!-- Quantity Selection -->
                <div class="mb-4">
                    <h6 class="text-success">Quantity</h6>
                    <div class="quantity-controls d-flex align-items-center">
                        <button type="button" class="btn btn-outline-secondary qty-btn" 
                                onclick="changeModalQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="form-control mx-3 text-center" 
                               id="modal_quantity" value="1" min="1" max="1" 
                               style="width: 80px; background: #374151; border: 1px solid #6b7280; color: white;">
                        <button type="button" class="btn btn-outline-secondary qty-btn" 
                                onclick="changeModalQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <small class="text-gray-400" id="stock_info">Stock: 0</small>
                </div>
                
                <!-- Add to Cart Button -->
                <div class="d-grid">
                    <button class="btn btn-success btn-lg" onclick="addToCartFromModal()">
                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                    </button>
                </div>
            <?php elseif ($totalStock == 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This product is currently out of stock.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please <a href="login.php" class="text-success">login</a> to purchase this product.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentPrice = <?php echo $product['price']; ?>;
let currentStock = <?php echo $product['qty']; ?>;
let selectedSize = 'Standard';

document.addEventListener('DOMContentLoaded', function() {
    // Initialize with first available size
    const firstAvailableSize = document.querySelector('input[name="product_size"]:checked');
    if (firstAvailableSize) {
        updateSizeSelection(firstAvailableSize);
    }
    
    // Add event listeners to size options
    document.querySelectorAll('input[name="product_size"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateSizeSelection(this);
        });
    });
});

function updateSizeSelection(radio) {
    const sizeOption = radio.closest('.size-option');
    selectedSize = radio.value;
    currentPrice = parseFloat(sizeOption.dataset.price);
    currentStock = parseInt(sizeOption.dataset.stock);
    
    // Update quantity input max value
    const quantityInput = document.getElementById('modal_quantity');
    if (quantityInput) {
        quantityInput.max = currentStock;
        quantityInput.value = Math.min(parseInt(quantityInput.value), currentStock);
        
        // Update stock info
        document.getElementById('stock_info').textContent = `Stock: ${currentStock}`;
    }
    
    // Highlight selected option
    document.querySelectorAll('.size-option').forEach(option => {
        option.classList.remove('border-success');
    });
    sizeOption.classList.add('border-success');
}

function changeModalQuantity(change) {
    const quantityInput = document.getElementById('modal_quantity');
    const currentValue = parseInt(quantityInput.value);
    const newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= currentStock) {
        quantityInput.value = newValue;
    }
}

function addToCartFromModal() {
    const quantity = document.getElementById('modal_quantity').value;
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    
    fetch('../Backend/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `product_id=<?php echo $product_id; ?>&quantity=${quantity}&size=${selectedSize}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart badge
            updateCartBadge(data.cart_count);
            
            // Show success message
            button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-success');
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
                button.classList.remove('btn-outline-success');
                button.classList.add('btn-success');
            }, 2000);
            
            // Show toast notification
            showToast('Product added to cart successfully!', 'success');
        } else {
            button.disabled = false;
            button.innerHTML = originalText;
            showToast(data.message || 'Failed to add product to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.disabled = false;
        button.innerHTML = originalText;
        showToast('Network error occurred', 'error');
    });
}

function showToast(message, type) {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `alert ${type === 'success' ? 'alert-success' : 'alert-danger'} alert-dismissible fade show`;
    toast.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 3000);
}
</script>

<style>
.size-option {
    cursor: pointer;
    transition: all 0.3s ease;
}

.size-option:hover {
    border-color: #10b981 !important;
    background-color: rgba(16, 185, 129, 0.1);
}

.size-option.border-success {
    border-color: #10b981 !important;
    background-color: rgba(16, 185, 129, 0.1);
}

.quantity-controls .btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image-container img {
    transition: transform 0.3s ease;
}

.product-image-container:hover img {
    transform: scale(1.05);
}
</style>