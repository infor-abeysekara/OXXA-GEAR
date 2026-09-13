<?php
$page_title = 'Products - OXXA GEAR';
include('../include/header.php');

// Get filter parameters
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query based on filters
$whereClause = "WHERE p.approve = 1 AND p.status = 'active'";
$params = [];
$types = "";

if (!empty($category)) {
    $whereClause .= " AND p.categories = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($search)) {
    $whereClause .= " AND (p.pname LIKE ? OR p.brand LIKE ? OR p.discription LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Get products with their lowest available price and total stock
$query = "SELECT p.*, 
                 COALESCE(MIN(ps.price), p.price) as lowest_price,
                 COALESCE(SUM(ps.qty), p.qty) as total_stock,
                 (SELECT ps2.size FROM productsize ps2 WHERE ps2.pid = p.pid AND ps2.qty > 0 ORDER BY ps2.price ASC LIMIT 1) as lowest_price_size
          FROM production p 
          LEFT JOIN productsize ps ON p.pid = ps.pid AND ps.qty > 0
          $whereClause
          GROUP BY p.id
          ORDER BY p.Add_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

// Get all categories for dropdown
$categoriesQuery = "SELECT DISTINCT categories FROM production WHERE approve = 1 AND status = 'active' ORDER BY categories";
$categoriesResult = $conn->query($categoriesQuery);
$categories = $categoriesResult->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-5">
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-4">
                <h2 class="display-5 fw-bold text-success mb-3">
                    <i class="fas fa-box me-2"></i>Our Products
                </h2>
                <p class="lead text-white">Discover our premium collection of health and nutrition products</p>
            </div>
        </div>
    </div>

    <!-- Filter and Search Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search products..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <?php if (!empty($category)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <?php endif; ?>
                <button class="btn btn-success" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <select name="category" class="form-select me-2" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['categories']); ?>" 
                                <?php echo ($category === $cat['categories']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['categories']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <?php if (!empty($category) || !empty($search)): ?>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Products Grid -->
        <div class="col-lg-12" id="productsContainer">
            <div class="row g-4">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-box-open fa-5x text-muted mb-3"></i>
                        <h4 class="text-muted">No products found</h4>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-lg-4 col-md-6 col-sm-12">
                            <div class="product-card" onclick="openProductModal('<?php echo $product['pid']; ?>')">
                                <!-- Product Image Container -->
                                <div class="product-image-wrapper">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="../image/<?php echo htmlspecialchars($product['image']); ?>" 
                                             class="product-image" alt="<?php echo htmlspecialchars($product['pname']); ?>">
                                    <?php else: ?>
                                        <div class="product-image product-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Stock Badge -->
                                    <div class="stock-badge">
                                        <?php if ($product['total_stock'] > 0): ?>
                                            <span class="badge-in-stock">In Stock</span>
                                        <?php else: ?>
                                            <span class="badge-out-stock">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Product Info -->
                                <div class="product-info">
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['pname']); ?></h3>
                                    
                                    <!-- Category Badge -->
                                    <div class="category-badge">
                                        <span class="category-pill"><?php echo htmlspecialchars($product['categories']); ?></span>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="product-price">
                                        Rs. <?php echo number_format($product['lowest_price'], 2); ?>
                                    </div>
                                    
                                    <!-- Quantity Controls -->
                                    <?php if ($product['total_stock'] > 0 && isset($_SESSION['userid'])): ?>
                                        <form class="add-to-cart-form" onclick="event.stopPropagation()" onsubmit="return addToCart(event, '<?php echo $product['pid']; ?>')">
                                            <div class="quantity-section">
                                                <label class="quantity-label">Quantity:</label>
                                                <div class="quantity-controls">
                                                    <button type="button" class="qty-btn qty-minus" 
                                                            onclick="changeQuantity('<?php echo $product['pid']; ?>', -1)">-</button>
                                                    <input type="number" name="quantity" class="qty-input" 
                                                           id="qty_<?php echo $product['pid']; ?>" value="1" min="1" 
                                                           max="<?php echo $product['total_stock']; ?>" readonly>
                                                    <button type="button" class="qty-btn qty-plus" 
                                                            onclick="changeQuantity('<?php echo $product['pid']; ?>', 1)">+</button>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="add-to-cart-btn">
                                                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                            </button>
                                        </form>
                                        <?php elseif ($product['total_stock'] == 0): ?>
                                        <div class="quantity-section">
                                            <label class="quantity-label">Quantity:</label>
                                            <div class="quantity-controls">
                                                <button type="button" class="qty-btn" disabled>-</button>
                                                <input type="number" class="qty-input" value="0" disabled>
                                                <button type="button" class="qty-btn" disabled>+</button>
                                            </div>
                                        </div>
                                        <button class="add-to-cart-btn disabled" disabled>
                                            <i class="fas fa-times me-2"></i>Out of Stock
                                        </button>
                                        <?php else: ?>
                                        <div class="quantity-section">
                                            <label class="quantity-label">Quantity:</label>
                                            <div class="quantity-controls">
                                                <button type="button" class="qty-btn" disabled>-</button>
                                                <input type="number" class="qty-input" value="1" disabled>
                                                <button type="button" class="qty-btn" disabled>+</button>
                                            </div>
                                        </div>
                                        <a href="login.php" class="add-to-cart-btn login-btn" onclick="event.stopPropagation()">
                                            <i class="fas fa-sign-in-alt me-2"></i>Login to Buy
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Product Details Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="productModalLabel">Product Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeQuantity(productId, change) {
    const quantityInput = document.getElementById('qty_' + productId);
    const currentValue = parseInt(quantityInput.value);
    const newValue = currentValue + change;
    const maxValue = parseInt(quantityInput.max);
    
    if (newValue >= 1 && newValue <= maxValue) {
        quantityInput.value = newValue;
    }
}

function addToCart(event, productId) {
    event.preventDefault();
    
    const quantityInput = document.getElementById('qty_' + productId);
    const quantity = quantityInput.value;
    const submitBtn = event.target.querySelector('.add-to-cart-btn');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    
    // FIXED: Changed from add_to_order.php to ../Backend/add_to_cart.php
    fetch('../Backend/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `product_id=${productId}&quantity=${quantity}&size=Standard`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart badge
            updateCartBadge(data.cart_count);
            
            // Show success message
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
            submitBtn.style.background = '#38a169';
            
            // Reset button after 2 seconds
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = originalText;
                submitBtn.style.background = '';
            }, 2000);
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        submitBtn.innerHTML = originalText;
    });
    
    return false;
}

function openProductModal(productId) {
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
    
    // Load product details via AJAX
    fetch('product_details_modal.php?id=' + productId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('modalContent').innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalContent').innerHTML = 
                '<div class="alert alert-danger">Error loading product details. Please try again.</div>';
        });
}
</script>

<?php include("../include/footer.php"); ?>