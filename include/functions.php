<?php
// Prevent multiple inclusions
if (!function_exists('uploadImage')) {

    // Image upload and compression function
    function uploadImage($file, $uploadDir, $prefix = '', $maxSizeMB = 10)
    {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            return 'type_error';
        }

        // We'll allow up to the specified size (default 10MB)
        if ($file['size'] > $maxSizeMB * 1024 * 1024) { 
            return 'size_error';
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = $prefix . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // If PDF, just move it without compression
        if ($extension === 'pdf' || $fileType === 'application/pdf') {
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $fileName;
            }
            return false;
        }

        // For images, compress and resize
        list($width, $height) = getimagesize($file['tmp_name']);
        
        $maxWidth = 800;
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = floor($height * ($maxWidth / $width));
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $image_p = imagecreatetruecolor($newWidth, $newHeight);
        
        // Handle transparency for PNG
        if ($extension == 'png') {
            imagealphablending($image_p, false);
            imagesavealpha($image_p, true);
            $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
            imagefilledrectangle($image_p, 0, 0, $newWidth, $newHeight, $transparent);
            $image = imagecreatefrompng($file['tmp_name']);
        } else {
            $image = imagecreatefromjpeg($file['tmp_name']);
        }

        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save image (Quality 70 for JPEG usually hits ~100-200KB for 800px)
        if ($extension == 'png') {
            $success = imagepng($image_p, $targetPath, 8); // Compression level 0-9
        } else {
            $success = imagejpeg($image_p, $targetPath, 70); // Quality 0-100
        }

        imagedestroy($image_p);
        imagedestroy($image);

        if ($success) {
            return $fileName;
        }

        return false;
    }

    // Generate sequential User ID starting from U0001
    function generateUserId($conn)
    {
        $query = "SELECT user_code FROM users ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $lastId = $row['user_code'];
            // Extract number from U0001 format
            $number = intval(substr($lastId, 1));
            $newNumber = $number + 1;
            return 'U' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        } else {
            return 'U0001';
        }
    }

    // Generate sequential Product ID starting from P0001
    function generateProductId($conn)
    {
        $query = "SELECT pid FROM production ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $lastId = $row['pid'];
            // Extract number from P0001 format
            $number = intval(substr($lastId, 1));
            $newNumber = $number + 1;
            return 'P' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        } else {
            return 'P0001';
        }
    }

    // Generate next product ID (alternative function name used in add_product.php)
    function generateNextProductId($conn)
    {
        return generateProductId($conn);
    }

    // Generate Order ID
    function generateOrderId()
    {
        return 'ORD' . date('Ymd') . rand(1000, 9999);
    }

    // Get cart count for a user - Updated to use cart table
    function getCartCount($conn, $user_id)
    {
        $query = "SELECT SUM(c.Qty) as total_count 
                  FROM cart c 
                  JOIN production p ON c.PID = p.pid
                  WHERE c.Userid = ? AND p.approve = 1 AND p.status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['total_count'] ? $row['total_count'] : 0;
        }
        return 0;
    }

    // Get order count for a user (items in ordertable with pending status)
    function getOrderCount($conn, $user_id)
    {
        $query = "SELECT SUM(qty) as total_count 
                  FROM ordertable 
                  WHERE user_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['total_count'] ? $row['total_count'] : 0;
        }
        return 0;
    }

    // Get cart items from cart table with proper pricing
    function getCartItems($conn, $user_id)
    {
        $query = "SELECT c.Id as id, c.PID as pid, c.Size as size, c.Qty as qty, c.AddedAt,
                         p.pname, p.brand, p.image, p.categories, p.discription, p.price as base_price,
                         COALESCE(ps.price, p.price) as price
                  FROM cart c 
                  JOIN production p ON c.PID = p.pid 
                  LEFT JOIN productsize ps ON c.PID = ps.pid AND c.Size = ps.size
                  WHERE c.Userid = ? AND p.approve = 1 AND p.status = 'active'
                  ORDER BY c.AddedAt DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Calculate cart totals
    function calculateCartTotal($conn, $user_id)
    {
        $cartItems = getCartItems($conn, $user_id);
        $subtotal = 0;
        
        foreach ($cartItems as $item) {
            $subtotal += ($item['price'] * $item['qty']);
        }
        
        return [
            'subtotal' => $subtotal,
            'delivery' => 450.00,
            'total' => $subtotal + 450.00
        ];
    }

    // Get product price based on size, fallback to default price
    function getProductPrice($conn, $pid, $size = 'Standard')
    {
        // First try to get size-specific price
        $query = "SELECT price FROM productsize WHERE pid = ? AND size = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $pid, $size);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['price'];
        }

        // If no size-specific price, get default price from production table
        $query = "SELECT price FROM production WHERE pid = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['price'];
        }

        return 0; // Return 0 if no price found
    }

    // Get product categories
    function getProductCategories()
    {
        return [
            'Protein Powder',
            'Pre-Workout',
            'Creatine',
            'Mass Gainers',
            'Fat Burners',
            'Recovery',
            'Vitamins'
        ];
    }

    // Check if user has approved business registration
    function hasApprovedBusiness($conn, $user_id)
    {
        $query = "SELECT is_approved FROM seller_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['is_approved'] == 1;
        }
        return false;
    }

    // Get business registration details
    function getBusinessRegistration($conn, $user_id)
    {
        $query = "SELECT * FROM seller_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    // Calculate seller commission (90% of product price, 10% goes to admin)
    function calculateSellerEarning($price)
    {
        return $price * 0.9;
    }

    // Get product with lowest price size
    function getProductLowestPrice($conn, $pid)
    {
        $query = "SELECT MIN(price) as min_price, size FROM productsize WHERE pid = ? AND qty > 0 GROUP BY pid ORDER BY price ASC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // Fallback to production table price
        $query = "SELECT price, 'Standard' as size FROM production WHERE pid = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return ['min_price' => $row['price'], 'size' => $row['size']];
        }
        
        return null;
    }

    // Get total stock for a product (sum of all sizes)
    function getTotalProductStock($conn, $pid)
    {
        $query = "SELECT COALESCE(SUM(ps.qty), p.qty) as total_qty 
                  FROM production p 
                  LEFT JOIN productsize ps ON p.pid = ps.pid 
                  WHERE p.pid = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['total_qty'] ? $row['total_qty'] : 0;
        }
        return 0;
    }

    // Sri Lankan provinces
    function getSriLankanProvinces()
    {
        return [
            'Western Province',
            'Central Province',
            'Southern Province',
            'Northern Province',
            'Eastern Province',
            'North Western Province',
            'North Central Province',
            'Uva Province',
            'Sabaragamuwa Province'
        ];
    }

    // Validate coupon code
    function validateCoupon($conn, $coupon_code)
    {
        $query = "SELECT * FROM coupons WHERE code = ? AND active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    // Add notification with user validation
    function addNotification($conn, $user_id, $message, $type = 'info')
    {
        // First check if the target user exists
        $checkQuery = "SELECT user_id FROM users WHERE user_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            // User doesn't exist, try to find any admin user
            $adminQuery = "SELECT user_id FROM users WHERE type = 'admin' LIMIT 1";
            $adminResult = mysqli_query($conn, $adminQuery);
            
            if ($adminResult && mysqli_num_rows($adminResult) > 0) {
                $adminRow = mysqli_fetch_assoc($adminResult);
                $user_id = $adminRow['user_id'];
            } else {
                // No admin found, skip notification
                return false;
            }
        }
        
        // Now insert the notification
        $query = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $user_id, $message, $type);
        return $stmt->execute();
    }

    // Get unread notifications count
    function getUnreadNotificationsCount($conn, $user_id)
    {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['count'];
        }
        return 0;
    }

    // Get seller analytics
    function getSellerAnalytics($conn, $user_id)
    {
        $analytics = [
            'total_products' => 0,
            'active_products' => 0,
            'total_orders' => 0,
            'total_revenue' => 0,
            'seller_earnings' => 0,
            'admin_commission' => 0,
            'pending_orders' => 0,
            'confirmed_orders' => 0,
            'cod_orders' => 0,
            'online_orders' => 0
        ];

        // Get product statistics
        $productQuery = "SELECT 
                            COUNT(*) as total_products,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products
                         FROM production WHERE user_id = ?";
        $stmt = $conn->prepare($productQuery);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $analytics['total_products'] = $row['total_products'];
            $analytics['active_products'] = $row['active_products'];
        }

        // Get order statistics from ordertable
        $orderQuery = "SELECT 
                          COUNT(*) as total_orders,
                          SUM(price * qty) as total_revenue,
                          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                          SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
                          SUM(CASE WHEN payment_method = 'COD' THEN 1 ELSE 0 END) as cod_orders,
                          SUM(CASE WHEN payment_method = 'PayHere' THEN 1 ELSE 0 END) as online_orders
                       FROM ordertable WHERE seller_id = ?";
        $stmt = $conn->prepare($orderQuery);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $analytics['total_orders'] = $row['total_orders'] ?: 0;
            $analytics['total_revenue'] = $row['total_revenue'] ?: 0;
            $analytics['pending_orders'] = $row['pending_orders'] ?: 0;
            $analytics['confirmed_orders'] = $row['confirmed_orders'] ?: 0;
            $analytics['cod_orders'] = $row['cod_orders'] ?: 0;
            $analytics['online_orders'] = $row['online_orders'] ?: 0;
        }

        // Calculate seller earnings (90% of revenue)
        $analytics['seller_earnings'] = $analytics['total_revenue'] * 0.9;
        $analytics['admin_commission'] = $analytics['total_revenue'] * 0.1;

        return $analytics;
    }

    // Get seller orders
    function getSellerOrders($conn, $seller_id, $limit = 50)
    {
        $query = "SELECT o.*, p.pname, p.image 
                  FROM ordertable o
                  LEFT JOIN production p ON o.pid = p.pid
                  WHERE o.seller_id = ? 
                  ORDER BY o.orderdate DESC 
                  LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $seller_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get product sizes
    function getProductSizes($conn, $pid)
    {
        $query = "SELECT * FROM productsize WHERE pid = ? ORDER BY price ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Check if product has sizes
    function productHasSizes($conn, $pid)
    {
        $query = "SELECT COUNT(*) as count FROM productsize WHERE pid = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }

    // Get available product sizes with stock
    function getAvailableProductSizes($conn, $pid)
    {
        $query = "SELECT * FROM productsize WHERE pid = ? AND qty > 0 ORDER BY price ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Update product stock after order
    function updateProductStock($conn, $pid, $size, $quantity)
    {
        if ($size === 'Standard') {
            // Update main product table
            $query = "UPDATE production SET qty = qty - ? WHERE pid = ? AND qty >= ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isi", $quantity, $pid, $quantity);
        } else {
            // Update productsize table
            $query = "UPDATE productsize SET qty = qty - ? WHERE pid = ? AND size = ? AND qty >= ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issi", $quantity, $pid, $size, $quantity);
        }
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    // Get seller ID for a product
    function getProductSeller($conn, $pid)
    {
        $query = "SELECT user_id FROM production WHERE pid = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['user_id'];
        }
        return null;
    }

    // Format currency
    function formatCurrency($amount)
    {
        return 'Rs. ' . number_format($amount, 2);
    }

    // Sanitize input
    function sanitizeInput($input)
    {
        return htmlspecialchars(strip_tags(trim($input)));
    }

    // Get product details with sizes
    function getProductWithSizes($conn, $pid)
    {
        // Get main product details
        $query = "SELECT * FROM production WHERE pid = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        if (!$product) {
            return null;
        }
        
        // Get product sizes
        $product['sizes'] = getProductSizes($conn, $pid);
        
        // Calculate total stock
        $product['total_stock'] = getTotalProductStock($conn, $pid);
        
        // Get lowest price info
        $lowestPrice = getProductLowestPrice($conn, $pid);
        $product['lowest_price'] = $lowestPrice ? $lowestPrice['min_price'] : $product['price'];
        $product['lowest_price_size'] = $lowestPrice ? $lowestPrice['size'] : 'Standard';
        
        return $product;
    }

} // End of function_exists check
?>