<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../include/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_cart';
$user_id = $_SESSION['userid'] ?? null;
$session_id = session_id();

// Helper: Calculate effective live price and hot deal status
function getLiveProductPrice($product, $variant_price = 0) {
    $basePrice = (!empty($variant_price) && $variant_price > 0) ? (float)$variant_price : (float)$product['base_price'];
    
    $isHotDeal = ((int)($product['is_hot_deal'] ?? 0) === 1 && 
                  ($product['hot_deal_status'] ?? '') === 'approved' && 
                  (empty($product['hot_deal_expiry']) || strtotime($product['hot_deal_expiry']) >= time()));
                  
    if ($isHotDeal && !empty($product['sale_price']) && (float)$product['sale_price'] > 0) {
        $unitPrice = (float)$product['sale_price'];
        $origPrice = (float)($product['original_price'] > 0 ? $product['original_price'] : $basePrice);
        $discountPct = (int)($product['discount_percent'] > 0 ? $product['discount_percent'] : round((($origPrice - $unitPrice) / $origPrice) * 100));
        return [
            'price' => $unitPrice,
            'original_price' => $origPrice,
            'is_hot_deal' => true,
            'discount_percent' => $discountPct,
            'expiry' => $product['hot_deal_expiry'] ?? null
        ];
    }

    return [
        'price' => $basePrice,
        'original_price' => $basePrice,
        'is_hot_deal' => false,
        'discount_percent' => 0,
        'expiry' => null
    ];
}

// -------------------------------------------------------------
// 1. GET_CART
// -------------------------------------------------------------
if ($action === 'get_cart') {
    $items = [];
    
    if ($user_id) {
        $sql = "
            SELECT c.id as cart_id, c.quantity, c.price_at_add, c.variant_id,
                   p.id as product_id, p.name as product_name, p.base_price, p.cost_price,
                   p.is_hot_deal, p.sale_price, p.original_price, p.discount_percent, p.hot_deal_status, p.hot_deal_expiry,
                   p.seller_id,
                   cs.size, cs.selling_price as variant_price, cs.qty as variant_stock,
                   pc.color_name,
                   COALESCE(
                       (SELECT ci.image_path FROM color_images ci WHERE ci.color_id = pc.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1),
                       (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1)
                   ) as image,
                   sp.business_name as seller_business_name,
                   u.first_name as seller_first_name, u.last_name as seller_last_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN color_sizes cs ON c.variant_id = cs.id
            LEFT JOIN product_colors pc ON cs.color_id = pc.id
            LEFT JOIN seller_profiles sp ON p.seller_id = sp.user_id
            LEFT JOIN users u ON p.seller_id = u.id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $priceData = getLiveProductPrice($row, $row['variant_price'] ?? 0);
            $livePrice = $priceData['price'];
            $stock = isset($row['variant_stock']) ? (int)$row['variant_stock'] : 999;
            
            // Check price change
            $priceChangeNotice = null;
            $priceAtAdd = (float)$row['price_at_add'];
            if ($priceAtAdd > 0 && abs($priceAtAdd - $livePrice) >= 0.01) {
                $priceChangeNotice = [
                    'from' => $priceAtAdd,
                    'to' => $livePrice,
                    'is_lower' => ($livePrice < $priceAtAdd)
                ];
            }

            // Stock label
            if ($stock <= 0) {
                $stockStatus = 'out_of_stock';
                $stockLabel = 'Out of Stock';
            } elseif ($stock <= 3) {
                $stockStatus = 'low_stock';
                $stockLabel = "Only {$stock} left!";
            } else {
                $stockStatus = 'in_stock';
                $stockLabel = 'In stock ✓';
            }

            $sellerName = !empty($row['seller_business_name']) ? $row['seller_business_name'] : trim($row['seller_first_name'] . ' ' . $row['seller_last_name']);
            if (empty($sellerName)) $sellerName = 'OXXA Official Store';

            $items[] = [
                'cart_id' => (int)$row['cart_id'],
                'product_id' => (int)$row['product_id'],
                'variant_id' => $row['variant_id'] ? (int)$row['variant_id'] : null,
                'seller_id' => (int)($row['seller_id'] ?? 0),
                'seller_name' => $sellerName,
                'name' => $row['product_name'],
                'size' => $row['size'] ?? 'Standard',
                'color' => $row['color_name'] ?? null,
                'image' => !empty($row['image']) ? '../assets/uploads/products/' . $row['image'] : '../image/placeholder.png',
                'quantity' => (int)$row['quantity'],
                'unit_price' => $livePrice,
                'original_price' => $priceData['original_price'],
                'is_hot_deal' => $priceData['is_hot_deal'],
                'discount_percent' => $priceData['discount_percent'],
                'item_total' => $livePrice * (int)$row['quantity'],
                'stock' => $stock,
                'stock_status' => $stockStatus,
                'stock_label' => $stockLabel,
                'price_change' => $priceChangeNotice
            ];
        }
    } else {
        // Guest cart: client passes items payload in POST/GET or we validate empty
        $guestPayload = $_POST['guest_cart'] ?? $_GET['guest_cart'] ?? null;
        if ($guestPayload) {
            $guestItems = is_array($guestPayload) ? $guestPayload : json_decode($guestPayload, true);
            if (!empty($guestItems)) {
                foreach ($guestItems as $gi) {
                    $pid = (int)($gi['product_id'] ?? 0);
                    $vid = !empty($gi['variant_id']) ? (int)$gi['variant_id'] : null;
                    $qty = min(max((int)($gi['quantity'] ?? 1), 1), 10);
                    $priceAtAdd = (float)($gi['price_at_add'] ?? 0);

                    // Fetch live product and variant details
                    $pStmt = $conn->prepare("
                        SELECT p.id as product_id, p.name as product_name, p.base_price, p.seller_id,
                               p.is_hot_deal, p.sale_price, p.original_price, p.discount_percent, p.hot_deal_status, p.hot_deal_expiry,
                               sp.business_name as seller_business_name,
                               u.first_name as seller_first_name, u.last_name as seller_last_name
                        FROM products p
                        LEFT JOIN seller_profiles sp ON p.seller_id = sp.user_id
                        LEFT JOIN users u ON p.seller_id = u.id
                        WHERE p.id = ? AND p.is_approved = 1 AND p.status = 'active'
                    ");
                    $pStmt->bind_param("i", $pid);
                    $pStmt->execute();
                    $pRow = $pStmt->get_result()->fetch_assoc();
                    if (!$pRow) continue;

                    $variantPrice = 0;
                    $variantStock = 999;
                    $size = 'Standard';
                    $color = null;
                    $image = null;

                    if ($vid) {
                        $vStmt = $conn->prepare("
                            SELECT cs.size, cs.selling_price as variant_price, cs.qty as variant_stock,
                                   pc.color_name,
                                   (SELECT ci.image_path FROM color_images ci WHERE ci.color_id = pc.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1) as image
                            FROM color_sizes cs
                            JOIN product_colors pc ON cs.color_id = pc.id
                            WHERE cs.id = ?
                        ");
                        $vStmt->bind_param("i", $vid);
                        $vStmt->execute();
                        if ($vRow = $vStmt->get_result()->fetch_assoc()) {
                            $variantPrice = (float)$vRow['variant_price'];
                            $variantStock = (int)$vRow['variant_stock'];
                            $size = $vRow['size'];
                            $color = $vRow['color_name'];
                            $image = $vRow['image'];
                        }
                    }

                    if (!$image) {
                        $imgRes = $conn->query("SELECT image_path FROM product_images WHERE product_id = {$pid} ORDER BY is_primary DESC LIMIT 1");
                        if ($imgRow = $imgRes->fetch_assoc()) $image = $imgRow['image_path'];
                    }

                    $priceData = getLiveProductPrice($pRow, $variantPrice);
                    $livePrice = $priceData['price'];

                    $priceChangeNotice = null;
                    if ($priceAtAdd > 0 && abs($priceAtAdd - $livePrice) >= 0.01) {
                        $priceChangeNotice = [
                            'from' => $priceAtAdd,
                            'to' => $livePrice,
                            'is_lower' => ($livePrice < $priceAtAdd)
                        ];
                    }

                    if ($variantStock <= 0) {
                        $stockStatus = 'out_of_stock';
                        $stockLabel = 'Out of Stock';
                    } elseif ($variantStock <= 3) {
                        $stockStatus = 'low_stock';
                        $stockLabel = "Only {$variantStock} left!";
                    } else {
                        $stockStatus = 'in_stock';
                        $stockLabel = 'In stock ✓';
                    }

                    $sellerName = !empty($pRow['seller_business_name']) ? $pRow['seller_business_name'] : trim($pRow['seller_first_name'] . ' ' . $pRow['seller_last_name']);
                    if (empty($sellerName)) $sellerName = 'OXXA Official Store';

                    $items[] = [
                        'cart_id' => 'guest_' . $pid . '_' . ($vid ?: '0'),
                        'product_id' => $pid,
                        'variant_id' => $vid,
                        'seller_id' => (int)($pRow['seller_id'] ?? 0),
                        'seller_name' => $sellerName,
                        'name' => $pRow['product_name'],
                        'size' => $size,
                        'color' => $color,
                        'image' => !empty($image) ? '../assets/uploads/products/' . $image : '../image/placeholder.png',
                        'quantity' => $qty,
                        'unit_price' => $livePrice,
                        'original_price' => $priceData['original_price'],
                        'is_hot_deal' => $priceData['is_hot_deal'],
                        'discount_percent' => $priceData['discount_percent'],
                        'item_total' => $livePrice * $qty,
                        'stock' => $variantStock,
                        'stock_status' => $stockStatus,
                        'stock_label' => $stockLabel,
                        'price_change' => $priceChangeNotice
                    ];
                }
            }
        }
    }

    // Group items by seller
    $sellers = [];
    $subtotal = 0;
    $totalCount = 0;

    foreach ($items as $item) {
        $sid = $item['seller_id'];
        if (!isset($sellers[$sid])) {
            $sellers[$sid] = [
                'seller_id' => $sid,
                'seller_name' => $item['seller_name'],
                'items' => [],
                'seller_subtotal' => 0
            ];
        }
        $sellers[$sid]['items'][] = $item;
        $sellers[$sid]['seller_subtotal'] += $item['item_total'];
        $subtotal += $item['item_total'];
        $totalCount += $item['quantity'];
    }

    // Shipping calculation (Free over Rs. 5000)
    $freeShippingThreshold = 5000.00;
    $isFreeShipping = ($subtotal >= $freeShippingThreshold);
    $shippingFee = ($subtotal > 0 && !$isFreeShipping) ? 450.00 : 0.00;
    $freeShippingRemaining = max(0, $freeShippingThreshold - $subtotal);

    // Coupon calculation
    $couponDiscount = 0;
    $appliedCoupon = $_SESSION['applied_coupon'] ?? null;
    if ($appliedCoupon && $subtotal > 0) {
        if ($appliedCoupon['type'] === 'percentage') {
            $couponDiscount = round(($subtotal * ($appliedCoupon['value'] / 100)), 2);
        } else {
            $couponDiscount = min($subtotal, (float)$appliedCoupon['value']);
        }
    }

    $finalTotal = max(0, ($subtotal + $shippingFee) - $couponDiscount);

    echo json_encode([
        'success' => true,
        'is_logged_in' => !empty($user_id),
        'count' => $totalCount,
        'badge_count' => $totalCount > 9 ? '9+' : (string)$totalCount,
        'subtotal' => $subtotal,
        'shipping_fee' => $shippingFee,
        'is_free_shipping' => $isFreeShipping,
        'free_shipping_remaining' => $freeShippingRemaining,
        'coupon_discount' => $couponDiscount,
        'coupon' => $appliedCoupon,
        'total' => $finalTotal,
        'sellers' => array_values($sellers),
        'items' => $items
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. ADD TO CART
// -------------------------------------------------------------
if ($action === 'add') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $variant_id = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $quantity = min(max((int)($_POST['quantity'] ?? 1), 1), 10);

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product selected.']);
        exit;
    }

    // Verify Product exists and is active
    $pStmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_approved = 1 AND status = 'active'");
    $pStmt->bind_param("i", $product_id);
    $pStmt->execute();
    $product = $pStmt->get_result()->fetch_assoc();
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product is currently unavailable.']);
        exit;
    }

    $seller_id = (int)($product['seller_id'] ?? 0);
    $variantPrice = 0;
    $availableStock = (int)($product['total_qty'] ?? 999);

    // If variant is specified, check variant stock
    if ($variant_id) {
        $vStmt = $conn->prepare("
            SELECT cs.*, pc.product_id as color_product_id 
            FROM color_sizes cs 
            JOIN product_colors pc ON cs.color_id = pc.id 
            WHERE cs.id = ? AND pc.product_id = ?
        ");
        $vStmt->bind_param("ii", $variant_id, $product_id);
        $vStmt->execute();
        $variant = $vStmt->get_result()->fetch_assoc();
        if (!$variant) {
            echo json_encode(['success' => false, 'message' => 'Selected variant not found.']);
            exit;
        }
        $variantPrice = (float)$variant['selling_price'];
        $availableStock = (int)$variant['qty'];
    }

    if ($availableStock <= 0) {
        echo json_encode(['success' => false, 'message' => 'This product/variant is out of stock.']);
        exit;
    }

    // Calculate price at add
    $priceData = getLiveProductPrice($product, $variantPrice);
    $priceAtAdd = $priceData['price'];

    if ($user_id) {
        // Check if item already exists in cart
        if ($variant_id) {
            $chk = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND variant_id = ?");
            $chk->bind_param("iii", $user_id, $product_id, $variant_id);
        } else {
            $chk = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND variant_id IS NULL");
            $chk->bind_param("ii", $user_id, $product_id);
        }
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            if ($newQty > 10) {
                echo json_encode(['success' => false, 'message' => 'Maximum 10 units allowed per item.']);
                exit;
            }
            if ($newQty > $availableStock) {
                echo json_encode(['success' => false, 'message' => "Only {$availableStock} units available in stock."]);
                exit;
            }

            $upd = $conn->prepare("UPDATE cart SET quantity = ?, price_at_add = ? WHERE id = ?");
            $upd->bind_param("idi", $newQty, $priceAtAdd, $existing['id']);
            $upd->execute();
        } else {
            if ($quantity > $availableStock) {
                echo json_encode(['success' => false, 'message' => "Only {$availableStock} units available in stock."]);
                exit;
            }

            $ins = $conn->prepare("INSERT INTO cart (user_id, session_id, product_id, variant_id, seller_id, quantity, price_at_add) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("isiiidd", $user_id, $session_id, $product_id, $variant_id, $seller_id, $quantity, $priceAtAdd);
            $ins->execute();
        }

        // Get total count
        $cntStmt = $conn->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $cntStmt->bind_param("i", $user_id);
        $cntStmt->execute();
        $newCount = (int)$cntStmt->get_result()->fetch_row()[0];

        echo json_encode([
            'success' => true,
            'message' => "Added to cart!",
            'count' => $newCount,
            'badge_count' => $newCount > 9 ? '9+' : (string)$newCount,
            'is_guest' => false
        ]);
        exit;
    } else {
        // Guest mode
        echo json_encode([
            'success' => true,
            'message' => "Added to cart!",
            'is_guest' => true,
            'item' => [
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity' => $quantity,
                'price_at_add' => $priceAtAdd,
                'available_stock' => $availableStock
            ]
        ]);
        exit;
    }
}

// -------------------------------------------------------------
// 3. UPDATE_QTY
// -------------------------------------------------------------
if ($action === 'update_qty') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $new_qty = (int)($_POST['quantity'] ?? 0);

    if ($user_id && $cart_id > 0) {
        if ($new_qty <= 0) {
            $del = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $cart_id, $user_id);
            $del->execute();
            echo json_encode(['success' => true, 'message' => 'Item removed from cart.']);
            exit;
        }

        if ($new_qty > 10) {
            echo json_encode(['success' => false, 'message' => 'Maximum limit is 10 units per item.']);
            exit;
        }

        // Check stock
        $chk = $conn->prepare("
            SELECT c.variant_id, cs.qty as variant_stock, p.total_qty
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN color_sizes cs ON c.variant_id = cs.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $chk->bind_param("ii", $cart_id, $user_id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();

        if ($row) {
            $stock = isset($row['variant_stock']) ? (int)$row['variant_stock'] : (int)$row['total_qty'];
            if ($new_qty > $stock) {
                echo json_encode(['success' => false, 'message' => "Only {$stock} units available."]);
                exit;
            }

            $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $upd->bind_param("iii", $new_qty, $cart_id, $user_id);
            $upd->execute();
            echo json_encode(['success' => true, 'message' => 'Quantity updated.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Failed to update item quantity.']);
    exit;
}

// -------------------------------------------------------------
// 4. REMOVE ITEM
// -------------------------------------------------------------
if ($action === 'remove') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    if ($user_id && $cart_id > 0) {
        $del = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $cart_id, $user_id);
        $del->execute();
        echo json_encode(['success' => true, 'message' => 'Item removed from cart.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Removed.']);
    exit;
}

// -------------------------------------------------------------
// 5. MOVE TO WISHLIST (Save for Later)
// -------------------------------------------------------------
if ($action === 'move_to_wishlist') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Please sign in to save items to your wishlist.']);
        exit;
    }

    // Get item product_id
    $stmt = $conn->prepare("SELECT product_id FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $pid = $row['product_id'];
        // Insert into wishlists if not exists
        $wStmt = $conn->prepare("INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)");
        $wStmt->bind_param("ii", $user_id, $pid);
        $wStmt->execute();

        // Delete from cart
        $conn->query("DELETE FROM cart WHERE id = {$cart_id} AND user_id = {$user_id}");

        echo json_encode(['success' => true, 'message' => 'Item moved to your Wishlist!']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Item not found.']);
    exit;
}

// -------------------------------------------------------------
// 6. APPLY COUPON
// -------------------------------------------------------------
if ($action === 'apply_coupon') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a coupon code.']);
        exit;
    }

    $cStmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
    $cStmt->bind_param("s", $code);
    $cStmt->execute();
    $coupon = $cStmt->get_result()->fetch_assoc();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code.']);
        exit;
    }

    if ($coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'This coupon has reached its maximum redemptions.']);
        exit;
    }

    $_SESSION['applied_coupon'] = [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'type' => $coupon['discount_type'], // 'percentage' or 'fixed'
        'value' => (float)$coupon['discount_value'],
        'min_amount' => (float)$coupon['min_order_amount']
    ];

    echo json_encode([
        'success' => true, 
        'message' => "Coupon {$coupon['code']} applied successfully!",
        'coupon' => $_SESSION['applied_coupon']
    ]);
    exit;
}

// -------------------------------------------------------------
// 7. REMOVE COUPON
// -------------------------------------------------------------
if ($action === 'remove_coupon') {
    unset($_SESSION['applied_coupon']);
    echo json_encode(['success' => true, 'message' => 'Coupon removed.']);
    exit;
}

// -------------------------------------------------------------
// 8. SYNC GUEST CART
// -------------------------------------------------------------
if ($action === 'sync_guest_cart') {
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $guestItems = $_POST['guest_items'] ?? [];
    if (is_string($guestItems)) {
        $guestItems = json_decode($guestItems, true);
    }

    $synced = 0;
    if (!empty($guestItems)) {
        foreach ($guestItems as $gi) {
            $pid = (int)($gi['product_id'] ?? 0);
            $vid = !empty($gi['variant_id']) ? (int)$gi['variant_id'] : null;
            $qty = min(max((int)($gi['quantity'] ?? 1), 1), 10);
            $priceAtAdd = (float)($gi['price_at_add'] ?? 0);

            if ($pid <= 0) continue;

            $pRes = $conn->query("SELECT seller_id, base_price, is_hot_deal, sale_price, hot_deal_status, hot_deal_expiry FROM products WHERE id = {$pid} AND is_approved = 1 AND status = 'active'");
            $pRow = $pRes ? $pRes->fetch_assoc() : null;
            if (!$pRow) continue;

            $sellerId = (int)$pRow['seller_id'];
            $priceData = getLiveProductPrice($pRow);
            if ($priceAtAdd <= 0) $priceAtAdd = $priceData['price'];

            // Upsert into cart
            if ($vid) {
                $chk = $conn->query("SELECT id, quantity FROM cart WHERE user_id = {$user_id} AND product_id = {$pid} AND variant_id = {$vid}");
            } else {
                $chk = $conn->query("SELECT id, quantity FROM cart WHERE user_id = {$user_id} AND product_id = {$pid} AND variant_id IS NULL");
            }

            if ($row = $chk->fetch_assoc()) {
                $combinedQty = min(10, $row['quantity'] + $qty);
                $conn->query("UPDATE cart SET quantity = {$combinedQty}, price_at_add = {$priceAtAdd} WHERE id = {$row['id']}");
            } else {
                $ins = $conn->prepare("INSERT INTO cart (user_id, session_id, product_id, variant_id, seller_id, quantity, price_at_add) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("isiiidd", $user_id, $session_id, $pid, $vid, $sellerId, $qty, $priceAtAdd);
                $ins->execute();
            }
            $synced++;
        }
    }

    echo json_encode(['success' => true, 'synced_count' => $synced]);
    exit;
}

// -------------------------------------------------------------
// 9. GET PRODUCT VARIANTS (For Quick Add Modal)
// -------------------------------------------------------------
if ($action === 'get_product_variants') {
    $product_id = (int)($_GET['product_id'] ?? 0);
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    $pStmt = $conn->prepare("
        SELECT p.id, p.name, p.base_price, p.is_hot_deal, p.sale_price, p.original_price, p.discount_percent, p.hot_deal_status, p.hot_deal_expiry,
               b.name as brand_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) as primary_image
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ? AND p.is_approved = 1 AND p.status = 'active'
    ");
    $pStmt->bind_param("i", $product_id);
    $pStmt->execute();
    $product = $pStmt->get_result()->fetch_assoc();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not available.']);
        exit;
    }

    // Fetch colors & variants
    $vSql = "
        SELECT cs.id as variant_id, cs.size, cs.selling_price, cs.qty as stock,
               pc.id as color_id, pc.color_name, pc.thumbnail_path,
               (SELECT ci.image_path FROM color_images ci WHERE ci.color_id = pc.id ORDER BY ci.is_primary DESC, ci.sort_order ASC LIMIT 1) as color_image
        FROM product_colors pc
        LEFT JOIN color_sizes cs ON cs.color_id = pc.id
        WHERE pc.product_id = ?
        ORDER BY pc.id ASC, cs.id ASC
    ";
    $vStmt = $conn->prepare($vSql);
    $vStmt->bind_param("i", $product_id);
    $vStmt->execute();
    $vRes = $vStmt->get_result();

    $colors = [];
    $hasVariants = false;

    while ($row = $vRes->fetch_assoc()) {
        $cid = $row['color_id'];
        if (!isset($colors[$cid])) {
            $colors[$cid] = [
                'color_id' => $cid,
                'color_name' => $row['color_name'] ?: 'Default',
                'color_image' => !empty($row['color_image']) ? '../assets/uploads/products/' . $row['color_image'] : null,
                'sizes' => []
            ];
        }

        if (!empty($row['variant_id'])) {
            $hasVariants = true;
            $colors[$cid]['sizes'][] = [
                'variant_id' => (int)$row['variant_id'],
                'size' => $row['size'] ?: 'Standard',
                'price' => (float)$row['selling_price'],
                'stock' => (int)$row['stock']
            ];
        }
    }

    $priceData = getLiveProductPrice($product);

    echo json_encode([
        'success' => true,
        'product' => [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'brand' => $product['brand_name'] ?? '',
            'image' => !empty($product['primary_image']) ? '../assets/uploads/products/' . $product['primary_image'] : '../image/placeholder.png',
            'unit_price' => $priceData['price'],
            'original_price' => $priceData['original_price'],
            'is_hot_deal' => $priceData['is_hot_deal'],
            'discount_percent' => $priceData['discount_percent'],
            'has_variants' => $hasVariants,
            'colors' => array_values($colors)
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
