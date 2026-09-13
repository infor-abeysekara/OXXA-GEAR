<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Product ID not provided</div>';
    exit;
}

$productId = sanitizeInput($_GET['id']);

// Get product details
$query = "SELECT * FROM production WHERE pid = ? AND approve = 1 AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $productId);
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
$sizesStmt->bind_param("s", $productId);
$sizesStmt->execute();
$sizesResult = $sizesStmt->get_result();
$sizes = $sizesResult->fetch_all(MYSQLI_ASSOC);
?>

<div class="row">
    <div class="col-md-6">
        <!-- Product Image -->
        <div class="product-detail-image">
            <?php if (!empty($product['image'])): ?>
                <img src="../image/<?php echo htmlspecialchars($product['image']); ?>" 
                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['pname']); ?>">
            <?php else: ?>
                <div class="no-image-placeholder d-flex align-items-center justify-content-center bg-gray-700 rounded" style="height: 400px;">
                    <i class="fas fa-image fa-5x text-gray-400"></i>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Product Information -->
        <div class="product-detail-info">
            <h3 class="text-white mb-3"><?php echo htmlspecialchars($product['pname']); ?></h3>
            
            <div class="mb-3">
                <span class="badge bg-primary"><?php echo htmlspecialchars($product['categories']); ?></span>
                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($product['brand']); ?></span>
            </div>
            
            <div class="mb-4">
                <h4 class="text-success">
                    <?php if (!empty($sizes)): ?>
                        Starting from Rs. <?php echo number_format(min(array_column($sizes, 'price')), 2); ?>
                    <?php else: ?>
                        Rs. <?php echo number_format($product['price'], 2); ?>
                    <?php endif; ?>
                </h4>
            </div>
            
            <!-- Product Description -->
            <div class="mb-4">
                <h5 class="text-white">Description</h5>
                <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($product['discription'])); ?></p>
            </div>
            
            <!-- Available Sizes -->
            <?php if (!empty($sizes)): ?>
                <div class="mb-4">
                    <h5 class="text-white">Available Sizes</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sizes as $size): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($size['size']); ?></td>
                                        <td class="text-success">Rs. <?php echo number_format($size['price'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $size['qty'] > 10 ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $size['qty']; ?> available
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <h5 class="text-white">Stock Information</h5>
                    <p class="text-gray-300">
                        <span class="badge <?php echo $product['qty'] > 10 ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo $product['qty']; ?> units available
                        </span>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Add to Cart Form -->
            <?php if (isset($_SESSION['userid'])): ?>
                <form id="modalAddToCartForm" class="add-to-cart-form">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    
                    <?php if (!empty($sizes)): ?>
                        <div class="mb-3">
                            <label class="form-label text-white">Select Size:</label>
                            <select name="size" class="form-select" required onchange="updateModalPrice()">
                                <option value="">Choose size...</option>
                                <?php foreach ($sizes as $size): ?>
                                    <option value="<?php echo htmlspecialchars($size['size']); ?>" 
                                            data-price="<?php echo $size['price']; ?>"
                                            data-stock="<?php echo $size['qty']; ?>">
                                        <?php echo htmlspecialchars($size['size']); ?> - Rs. <?php echo number_format($size['price'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="size" value="Standard">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label text-white">Quantity:</label>
                        <div class="input-group" style="max-width: 150px;">
                            <button type="button" class="btn btn-outline-secondary" onclick="changeModalQuantity(-1)">-</button>
                            <input type="number" name="quantity" id="modalQuantity" class="form-control text-center" value="1" min="1" readonly>
                            <button type="button" class="btn btn-outline-secondary" onclick="changeModalQuantity(1)">+</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="../site/login.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to Purchase
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentMaxStock = <?php echo !empty($sizes) ? 'null' : $product['qty']; ?>;

function updateModalPrice() {
    const sizeSelect = document.querySelector('select[name="size"]');
    const quantityInput = document.getElementById('modalQuantity');
    
    if (sizeSelect && sizeSelect.selectedOptions.length > 0) {
        const selectedOption = sizeSelect.selectedOptions[0];
        const stock = parseInt(selectedOption.dataset.stock);
        
        currentMaxStock = stock;
        quantityInput.max = stock;
        
        if (parseInt(quantityInput.value) > stock) {
            quantityInput.value = Math.min(1, stock);
        }
    }
}

function changeModalQuantity(change) {
    const quantityInput = document.getElementById('modalQuantity');
    const currentValue = parseInt(quantityInput.value);
    const newValue = currentValue + change;
    const maxValue = currentMaxStock || parseInt(quantityInput.max) || 999;
    
    if (newValue >= 1 && newValue <= maxValue) {
        quantityInput.value = newValue;
    }
}

// Handle form submission
document.getElementById('modalAddToCartForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    
    fetch('../Backend/add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
            submitBtn.style.background = '#38a169';
            
            // Update cart badge if function exists
            if (typeof updateCartBadge === 'function') {
                updateCartBadge(data.cart_count);
            }
            
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                submitBtn.style.background = '';
            }, 2000);
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>