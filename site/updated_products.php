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
                <p class="lead text-muted">Discover our premium collection of health and nutrition products</p>
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
                            <div class="enhanced-product-card" data-product-id="<?php echo $product['pid']; ?>">
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
                                    
                                    <!-- Brand -->
                                    <div class="product-brand">
                                        <span class="brand-label">Brand:</span>
                                        <span class="brand-name"><?php echo htmlspecialchars($product['brand']); ?></span>
                                    </div>
                                    
                                    <!-- Category Badge -->
                                    <div class="category-badge">
                                        <span class="category-pill"><?php echo htmlspecialchars($product['categories']); ?></span>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="product-price">
                                        Rs. <?php echo number_format($product['lowest_price'], 2); ?>
                                        <?php if ($product['lowest_price_size']): ?>
                                            <small class="price-note">Starting from</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <div class="quick-actions">
                                        <?php if ($product['total_stock'] > 0 && isset($_SESSION['userid'])): ?>
                                            <div class="quantity-section" onclick="event.stopPropagation()">
                                                <label class="quantity-label">Qty:</label>
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
                                            
                                            <button type="button" class="quick-add-btn" 
                                                    onclick="quickAddToCart(event, '<?php echo $product['pid']; ?>')">
                                                <i class="fas fa-shopping-cart"></i>
                                            </button>
                                        <?php elseif ($product['total_stock'] == 0): ?>
                                            <div class="out-of-stock-section">
                                                <span class="out-of-stock-text">Out of Stock</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="login-section">
                                                <a href="login.php" class="login-link" onclick="event.stopPropagation()">
                                                    Login to Buy
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Product Details Modal -->
<div class="modal fade" id="newProductModal" tabindex="-1" aria-labelledby="newProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newProductModalLabel">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="newModalContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.enhanced-product-card {
    background: #ffffff;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.enhanced-product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.product-image-wrapper {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.enhanced-product-card:hover .product-image {
    transform: scale(1.05);
}

.product-placeholder {
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 3rem;
}

.stock-badge {
    position: absolute;
    top: 15px;
    right: 15px;
}

.badge-in-stock {
    background: #28a745;
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-out-stock {
    background: #dc3545;
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.product-info {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.product-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.8rem;
    line-height: 1.3;
}

.product-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.8rem;
}

.brand-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.brand-name {
    color: #495057;
    font-weight: 600;
}

.category-badge {
    margin-bottom: 1rem;
}

.category-pill {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.product-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: #28a745;
    margin-bottom: 1.5rem;
}

.price-note {
    display: block;
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 400;
}

.quick-actions {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.quantity-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.quantity-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 600;
}

.quantity-controls {
    display: flex;
    align-items: center;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
}

.qty-btn {
    background: #f8f9fa;
    border: none;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.3s ease;
    font-size: 0.9rem;
}

.qty-btn:hover {
    background: #e9ecef;
}

.qty-input {
    border: none;
    width: 40px;
    height: 30px;
    text-align: center;
    background: #ffffff;
    font-weight: 600;
    font-size: 0.9rem;
}

.quick-add-btn {
    background: #28a745;
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-add-btn:hover {
    background: #218838;
    transform: scale(1.05);
}

.out-of-stock-section {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.out-of-stock-text {
    color: #dc3545;
    font-weight: 600;
    font-size: 0.9rem;
}

.login-section {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-link {
    background: #007bff;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

.login-link:hover {
    background: #0056b3;
    color: white;
    text-decoration: none;
}

.modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    color: white;
    border-bottom: none;
}

.btn-close {
    filter: invert(1);
}

@media (max-width: 768px) {
    .enhanced-product-card {
        margin-bottom: 1rem;
    }
    
    .product-image-wrapper {
        height: 200px;
    }
    
    .product-title {
        font-size: 1.1rem;
    }
    
    .product-price {
        font-size: 1.3rem;
    }
    
    .quick-actions {
        flex-direction: column;
        gap: 0.8rem;
    }
    
    .quantity-section {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Enhanced product card click handler
document.addEventListener('DOMContentLoaded', function() {
    const productCards = document.querySelectorAll('.enhanced-product-card');
    
    productCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't open modal if clicking on quantity controls or add to cart button
            if (e.target.closest('.quick-actions')) {
                return;
            }
            
            const productId = this.dataset.productId;
            openNewProductModal(productId);
        });
    });
});

function changeQuantity(productId, change) {
    const quantityInput = document.getElementById('qty_' + productId);
    const currentValue = parseInt(quantityInput.value);
    const newValue = currentValue + change;
    const maxValue = parseInt(quantityInput.max);
    
    if (newValue >= 1 && newValue <= maxValue) {
        quantityInput.value = newValue;
    }
}

function quickAddToCart(event, productId) {
    event.stopPropagation();
    
    const quantityInput = document.getElementById('qty_' + productId);
    const quantity = quantityInput.value;
    const addBtn = event.target.closest('.quick-add-btn');
    const originalHTML = addBtn.innerHTML;
    
    // Show loading state
    addBtn.disabled = true;
    addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
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
            if (typeof updateCartBadge === 'function') {
                updateCartBadge(data.cart_count);
            }
            
            // Show success state
            addBtn.innerHTML = '<i class="fas fa-check"></i>';
            addBtn.style.background = '#28a745';
            
            // Reset after 2 seconds
            setTimeout(() => {
                addBtn.disabled = false;
                addBtn.innerHTML = originalHTML;
                addBtn.style.background = '';
            }, 2000);
        } else {
            alert('Error: ' + data.message);
            addBtn.disabled = false;
            addBtn.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        addBtn.disabled = false;
        addBtn.innerHTML = originalHTML;
    });
}

function openNewProductModal(productId) {
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('newProductModal'));
    modal.show();
    
    // Load product details via AJAX
    fetch('new_product_modal.php?id=' + productId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('newModalContent').innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('newModalContent').innerHTML = 
                '<div class="alert alert-danger m-4">Error loading product details. Please try again.</div>';
        });
}
</script>

<?php include("../include/footer.php"); ?>