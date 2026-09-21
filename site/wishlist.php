<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid'])) {
    header("Location: index.php?open=login");
    exit();
}

$userid = $_SESSION['userid'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userid]);
$user = $stmt->fetch();

include("../include/header.php");
?>

<div class="bg-gray-50 min-h-screen py-10 mt-20">
    <div class="container mx-auto px-4 max-w-6xl">
        
        <div class="flex items-center text-sm text-gray-500 mb-6">
            <a href="index.php" class="hover:text-[#0066FF] transition-colors"><i class="fas fa-home me-2"></i>Home</a>
            <i class="fas fa-chevron-right text-xs mx-3 text-gray-300"></i>
            <span class="text-navy font-bold">Wishlist</span>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full lg:w-[260px] flex-shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <div class="p-6 text-center border-b border-gray-50 flex flex-col items-center">
                        <?php if (!empty($user['profile_image']) && file_exists("../assets/uploads/profiles/" . $user['profile_image'])): ?>
                            <img src="../assets/uploads/profiles/<?php echo $user['profile_image']; ?>" class="w-24 h-24 rounded-full object-cover ring-4 ring-white shadow-xl mb-4">
                        <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-black text-white flex items-center justify-center text-3xl font-black ring-4 ring-white shadow-xl mb-4">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="font-black text-navy text-lg uppercase tracking-wide"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <div class="p-2 lg:p-3 flex overflow-x-auto lg:flex-col gap-3 lg:gap-1 hide-scrollbar">
                        <a href="profile.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-user-circle w-6 text-lg"></i> My Profile
                        </a>
                        <a href="address-book.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-address-book w-6 text-lg"></i> Address Book
                        </a>
                        <a href="my-orders.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-shopping-bag w-6 text-lg"></i> My Orders
                        </a>
                        <a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-heart w-6 text-lg"></i> Wishlist
                        </a>
                        <a href="reviews.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="far fa-star w-6 text-lg"></i> My Reviews
                        </a>
                        <a href="returns.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-undo-alt w-6 text-lg"></i> My Returns
                        </a>
                        <a href="coupons.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap">
                            <i class="fas fa-ticket-alt w-6 text-lg"></i> My Coupons
                        </a>
                        <a href="recently-viewed.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap lg:border-b border-gray-100 lg:pb-4 lg:mb-1">
                            <i class="far fa-eye w-6 text-lg"></i> Recently Viewed
                        </a>
                        <?php if($user['user_type'] == 'seller'): ?>
                        <a href="seller-dashboard.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors shrink-0 whitespace-nowrap lg:mt-2 lg:border-t border-gray-100 lg:pt-3">
                            <i class="fas fa-store w-6 text-lg"></i> Seller Dashboard
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Main Content -->
            <div class="flex-1">
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden p-6 md:p-10 min-h-[400px]">
                    
                    <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-6">
                        <h2 class="text-2xl font-black text-navy uppercase tracking-wide">My Wishlist</h2>
                    </div>

                    <?php
                    // Fetch wishlist items
                    $wishQuery = $pdo->prepare("
                        SELECT w.id as wishlist_id, p.*, c.name as category_name
                        FROM wishlists w
                        JOIN products p ON w.product_id = p.id
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE w.user_id = ?
                        ORDER BY w.id DESC
                    ");
                    $wishQuery->execute([$userid]);
                    $wishlistItems = $wishQuery->fetchAll(PDO::FETCH_ASSOC);

                    if (count($wishlistItems) > 0):
                    ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                            <?php foreach ($wishlistItems as $item): 
                                // Fetch primary image
                                $imgQuery = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
                                $imgQuery->execute([$item['id']]);
                                $img = $imgQuery->fetchColumn();
                                $imageSrc = $img ? "../assets/uploads/products/" . $img : "../image/no-image.jpg";
                            ?>
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-shadow flex flex-col relative">
                                    <button onclick="removeFromWishlist(<?= $item['wishlist_id'] ?>)" class="absolute top-2 right-2 z-10 w-8 h-8 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-red-500 hover:bg-white hover:text-red-700 shadow-sm transition-all" title="Remove from Wishlist">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                    <a href="product-details.php?id=<?= $item['id'] ?>" class="block relative aspect-square overflow-hidden bg-gray-50">
                                        <img src="<?= htmlspecialchars($imageSrc) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    </a>
                                    <div class="p-4 flex flex-col flex-1">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></p>
                                        <h3 class="font-bold text-navy text-sm mb-2 line-clamp-2 hover:text-[#0066FF] transition-colors"><a href="product-details.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['name']) ?></a></h3>
                                        <div class="mt-auto flex items-center justify-between">
                                            <span class="font-black text-[#0066FF]">Rs. <?= number_format($item['base_price']) ?></span>
                                        </div>
                                        <button onclick="CartManager.addToCart(<?= $item['id'] ?>, 1)" class="w-full mt-3 bg-navy hover:bg-[#0066FF] text-white py-2 rounded-xl text-xs font-bold uppercase tracking-wide transition-colors">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="text-center flex flex-col items-center justify-center py-12">
                            <div class="w-24 h-24 bg-blue-50 rounded-full flex items-center justify-center mb-6">
                                <i class="far fa-heart text-[#0066FF] text-4xl"></i>
                            </div>
                            <h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-2">Your Wishlist is Empty</h2>
                            <p class="text-gray-500 mb-8 max-w-md mx-auto">Found something you like? Tap on the heart icon next to the item to add it to your wishlist!</p>
                            <a href="shop.php" class="px-8 py-3 bg-[#0066FF] text-white font-bold rounded-full uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">
                                Continue Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function removeFromWishlist(wishlistId) {
    if (!confirm('Remove this item from your wishlist?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('wishlist_id', wishlistId);
        
        const res = await fetch('../Backend/wishlist-api.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            // Reload page to reflect changes
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Error removing item', 'error');
        console.error(e);
    }
}
</script>

<?php include("../include/footer.php"); ?>
