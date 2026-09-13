<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (!isset($_SESSION['userid'])) {
    echo '<div class="text-center py-8"><p class="text-gray-400">Please login to view cart</p></div>';
    exit;
}

$cartItems = getCartItems($conn, $_SESSION['userid']);
$subtotal = 0;
$totalItems = 0;

foreach ($cartItems as $item) {
    $subtotal += ($item['price'] * $item['qty']);
    $totalItems += $item['qty'];
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
            <div class="cart-item mb-3 p-3 bg-gray-800 rounded-lg" data-item-id="<?php echo $item['id']; ?>">
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
                        <?php if ($item['size'] != 'Standard'): ?>
                            <span class="badge bg-secondary" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($item['size']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <!-- Quantity and Price -->
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="quantity-controls d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="updateCartQuantity(<?php echo $item['id']; ?>, -1)" 
                                        style="width: 25px; height: 25px; padding: 0; font-size: 0.8rem;">-</button>
                                <span class="mx-2 text-white" style="font-size: 0.9rem;"><?php echo $item['qty']; ?></span>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        onclick="updateCartQuantity(<?php echo $item['id']; ?>, 1)" 
                                        style="width: 25px; height: 25px; padding: 0; font-size: 0.8rem;">+</button>
                            </div>
                            <div class="text-end">
                                <div class="text-success fw-bold" style="font-size: 0.9rem;">
                                    Rs. <?php echo number_format($item['price'] * $item['qty'], 2); ?>
                                </div>
                                <button class="btn btn-sm btn-outline-danger mt-1" 
                                        onclick="removeCartItem(<?php echo $item['id']; ?>)" 
                                        style="font-size: 0.7rem; padding: 2px 6px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Cart Summary -->
    <div class="cart-summary border-top border-gray-700 pt-3">
        <div class="d-flex justify-content-between mb-2">
            <span class="text-gray-400">Subtotal:</span>
            <span class="text-white">Rs. <?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-gray-400">Delivery:</span>
            <span class="text-white">Rs. <?php echo number_format($deliveryFee, 2); ?></span>
        </div>
        <hr class="border-gray-700">
        <div class="d-flex justify-content-between mb-3">
            <span class="text-white fw-bold">Total:</span>
            <span class="text-success fw-bold">Rs. <?php echo number_format($total, 2); ?></span>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-grid gap-2">
            <button onclick="goToCheckout()" class="btn btn-success">
                <i class="fas fa-credit-card me-2"></i>Checkout
            </button>
            <button onclick="toggleCartSidebar()" class="btn btn-outline-success">
                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
            </button>
        </div>
    </div>
<?php endif; ?>

<script>
function updateCartQuantity(itemId, change) {
    fetch('update_cart_quantity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `item_id=${itemId}&change=${change}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCartContent();
            updateCartBadge(data.cart_count);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function removeCartItem(itemId) {
    if (confirm('Remove this item from cart?')) {
        fetch('remove_cart_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `item_id=${itemId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadCartContent();
                updateCartBadge(data.cart_count);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
}

function goToCheckout() {
    // Close the cart sidebar first
    if (typeof toggleCartSidebar === 'function') {
        toggleCartSidebar();
    }
    
    // Add a small delay to ensure sidebar closes, then navigate
    setTimeout(function() {
        window.location.href = 'checkout.php';
    }, 300);
}
</script>