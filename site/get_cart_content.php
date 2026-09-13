<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid'])) {
    echo '<div class="text-center py-8"><p class="text-gray-400">Please login to view cart</p></div>';
    exit;
}

// Get cart items with proper joins
$query = "SELECT c.Id, c.PID, c.Qty, c.Size, c.AddedAt, 
                 p.pname, p.brand, p.price, p.image, p.qty as stock_qty
          FROM cart c 
          JOIN production p ON c.PID = p.pid 
          WHERE c.Userid = ? 
          ORDER BY c.AddedAt DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $_SESSION['userid']);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = [];
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    // Get size-specific price if applicable
    $itemPrice = $row['price'];
    $stockQty = $row['stock_qty'];
    
    if ($row['Size'] !== 'Standard') {
        $sizeQuery = "SELECT price, qty FROM productsize WHERE pid = ? AND size = ?";
        $sizeStmt = $conn->prepare($sizeQuery);
        $sizeStmt->bind_param("ss", $row['PID'], $row['Size']);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();
        
        if ($sizeResult->num_rows > 0) {
            $sizeData = $sizeResult->fetch_assoc();
            $itemPrice = $sizeData['price'];
            $stockQty = $sizeData['qty'];
        }
    }
    
    $row['current_price'] = $itemPrice;
    $row['available_stock'] = $stockQty;
    $subtotal += ($itemPrice * $row['Qty']);
    $cartItems[] = $row;
}

$deliveryFee = 450.00;
$total = $subtotal + $deliveryFee;
?>

<?php if (empty($cartItems)): ?>
    <div class="text-center py-8">
        <i class="fas fa-shopping-cart fa-3x text-gray-400 mb-3"></i>
        <p class="text-gray-400 mb-4">Your cart is empty</p>
        <button onclick="toggleCartSidebar()" class="btn btn-success btn-sm">
            Continue Shopping
        </button>
    </div>
<?php else: ?>
    <div class="cart-items mb-4">
        <h6 class="text-gray-300 mb-3">Items (<?php echo count($cartItems); ?>)</h6>

        <?php foreach ($cartItems as $item): ?>
            <div class="cart-item mb-3 p-3 bg-gray-800 rounded-lg" data-item-id="<?php echo $item['Id']; ?>" id="cart-item-<?php echo $item['Id']; ?>">
                <div class="d-flex align-items-center">
                    <!-- Product Image -->
                    <div class="flex-shrink-0 me-3">
                        <?php if (!empty($item['image'])): ?>
                            <img src="../image/<?php echo htmlspecialchars($item['image']); ?>"
                                alt="<?php echo htmlspecialchars($item['pname']); ?>"
                                class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-gray-600 rounded d-flex align-items-center justify-content-center"
                                style="width: 50px; height: 50px;">
                                <i class="fas fa-image text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Product Details -->
                    <div class="flex-grow-1">
                        <h6 class="text-white mb-1" style="font-size: 0.9rem;"><?php echo htmlspecialchars($item['pname']); ?></h6>
                        <p class="text-gray-400 mb-1" style="font-size: 0.8rem;">
                            <?php echo htmlspecialchars($item['brand']); ?>
                        </p>
                        <?php if ($item['Size'] != 'Standard'): ?>
                            <span class="badge bg-secondary" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($item['Size']); ?>
                            </span>
                        <?php endif; ?>

                        <!-- Quantity and Price -->
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="quantity-controls d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-secondary qty-btn"
                                    onclick="updateCartQuantity(<?php echo $item['Id']; ?>, -1)"
                                    style="width: 30px; height: 30px; padding: 0; font-size: 0.8rem; border-radius: 4px;">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="mx-3 text-white fw-bold qty-display" style="font-size: 1rem; min-width: 20px; text-align: center;" id="qty-<?php echo $item['Id']; ?>">
                                    <?php echo $item['Qty']; ?>
                                </span>
                                <button class="btn btn-sm btn-outline-secondary qty-btn"
                                    onclick="updateCartQuantity(<?php echo $item['Id']; ?>, 1)"
                                    style="width: 30px; height: 30px; padding: 0; font-size: 0.8rem; border-radius: 4px;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="text-end">
                                <div class="text-success fw-bold item-total" style="font-size: 1rem;" id="total-<?php echo $item['Id']; ?>">
                                    Rs. <?php echo number_format($item['current_price'] * $item['Qty'], 2); ?>
                                </div>
                                <button class="btn btn-sm btn-outline-danger mt-1 remove-btn"
                                    onclick="removeCartItem(<?php echo $item['Id']; ?>)"
                                    style="font-size: 0.8rem; padding: 4px 8px; border-radius: 4px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Hidden data for JS calculations -->
                        <input type="hidden" class="item-price" value="<?php echo $item['current_price']; ?>">
                        <input type="hidden" class="item-stock" value="<?php echo $item['available_stock']; ?>">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Cart Summary -->
    <div class="cart-summary border-top border-gray-700 pt-3">
        <div class="d-flex justify-content-between mb-2">
            <span class="text-gray-400">Subtotal:</span>
            <span class="text-white" id="cart-subtotal">Rs. <?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-gray-400">Delivery:</span>
            <span class="text-white" id="cart-delivery">Rs. <?php echo number_format($deliveryFee, 2); ?></span>
        </div>
        <hr class="border-gray-700">
        <div class="d-flex justify-content-between mb-3">
            <span class="text-white fw-bold">Total:</span>
            <span class="text-success fw-bold" id="cart-total">Rs. <?php echo number_format($total, 2); ?></span>
        </div>

        <!-- Action Buttons -->
        <div class="d-grid gap-2">
            <a href="checkout.php" class="btn btn-success fw-semibold btn-lg px-4 py-2 rounded-2 shadow d-flex align-items-center justify-content-center" id="checkoutBtn">
                <i class="fas fa-credit-card me-2"></i>Checkout
            </a>
            <button onclick="toggleCartSidebar()" class="btn btn-outline-success">
                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
            </button>
        </div>
    </div>
<?php endif; ?>

<script>
function updateCartQuantity(itemId, change) {
    console.log('Updating cart quantity:', itemId, change);
    
    // Show loading state
    const cartItem = document.getElementById(`cart-item-${itemId}`);
    const buttons = cartItem.querySelectorAll('.qty-btn');
    const originalButtons = [];
    
    buttons.forEach((btn, index) => {
        originalButtons[index] = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    });

    fetch('../Backend/update_cart_quantity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `item_id=${itemId}&change=${change}`
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
            if (data.removed) {
                // Item was removed
                cartItem.style.transition = 'all 0.3s ease';
                cartItem.style.opacity = '0';
                cartItem.style.transform = 'translateX(100%)';
                
                setTimeout(() => {
                    cartItem.remove();
                    updateCartTotals();
                    updateCartBadge(data.cart_count);
                    
                    // Check if cart is empty
                    if (data.cart_count === 0) {
                        loadCartContent();
                    }
                }, 300);
                
                showMessage('Item removed from cart', 'success');
            } else {
                // Update quantity and total
                const qtyElement = document.getElementById(`qty-${itemId}`);
                const totalElement = document.getElementById(`total-${itemId}`);
                
                if (qtyElement) qtyElement.textContent = data.new_quantity;
                if (totalElement) totalElement.textContent = `Rs. ${data.item_total}`;
                
                updateCartTotals();
                updateCartBadge(data.cart_count);
                showMessage('Cart updated successfully', 'success');
            }
        } else {
            showMessage(data.message || 'Failed to update cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'error');
    })
    .finally(() => {
        // Restore buttons
        buttons.forEach((btn, index) => {
            btn.disabled = false;
            btn.innerHTML = originalButtons[index];
        });
    });
}

function removeCartItem(itemId) {
    if (!confirm('Remove this item from cart?')) {
        return;
    }
    
    console.log('Removing cart item:', itemId);
    
    const cartItem = document.getElementById(`cart-item-${itemId}`);
    const removeBtn = cartItem.querySelector('.remove-btn');
    const originalBtn = removeBtn.innerHTML;
    
    removeBtn.disabled = true;
    removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('../Backend/remove_cart_item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `item_id=${itemId}`
    })
    .then(response => {
        console.log('Remove response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Remove response data:', data);
        
        if (data.success) {
            cartItem.style.transition = 'all 0.3s ease';
            cartItem.style.opacity = '0';
            cartItem.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                cartItem.remove();
                updateCartTotals();
                updateCartBadge(data.cart_count);
                
                // Check if cart is empty
                if (data.cart_count === 0) {
                    loadCartContent();
                }
            }, 300);
            
            showMessage('Item removed successfully', 'success');
        } else {
            showMessage(data.message || 'Failed to remove item', 'error');
            removeBtn.disabled = false;
            removeBtn.innerHTML = originalBtn;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'error');
        removeBtn.disabled = false;
        removeBtn.innerHTML = originalBtn;
    });
}

function updateCartTotals() {
    let subtotal = 0;
    const deliveryFee = 450.00;
    
    // Calculate subtotal from all items
    document.querySelectorAll('.cart-item').forEach(item => {
        const qtyElement = item.querySelector('.qty-display');
        const priceInput = item.querySelector('.item-price');
        
        if (qtyElement && priceInput) {
            const qty = parseInt(qtyElement.textContent);
            const price = parseFloat(priceInput.value);
            subtotal += (price * qty);
        }
    });
    
    const total = subtotal + deliveryFee;
    
    // Update displays
    const subtotalElement = document.getElementById('cart-subtotal');
    const totalElement = document.getElementById('cart-total');
    
    if (subtotalElement) {
        subtotalElement.textContent = `Rs. ${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }
    
    if (totalElement) {
        totalElement.textContent = `Rs. ${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }
}

function showMessage(message, type) {
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert ${type === 'success' ? 'alert-success' : 'alert-danger'} alert-dismissible fade show`;
    messageDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    messageDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 3000);
}
</script>