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
            <span class="text-navy font-bold">Recently Viewed</span>
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
                    
                    <div class="p-3 flex flex-col gap-1">
                        <a href="profile.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-user-circle w-6 text-lg"></i> My Profile
                        </a>
                        <a href="address-book.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-address-book w-6 text-lg"></i> Address Book
                        </a>
                        <a href="my-orders.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="fas fa-shopping-bag w-6 text-lg"></i> My Orders
                        </a>
                        <a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-heart w-6 text-lg"></i> Recently Viewed
                        </a>
                        <a href="reviews.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="far fa-star w-6 text-lg"></i> My Reviews
                        </a>
                        <a href="returns.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="fas fa-undo-alt w-6 text-lg"></i> My Returns
                        </a>
                        <a href="coupons.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">
                            <i class="fas fa-ticket-alt w-6 text-lg"></i> My Coupons
                        </a>
                        <a href="recently-viewed.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors border-b border-gray-100 pb-4 mb-1">
                            <i class="far fa-eye w-6 text-lg"></i> Recently Viewed
                        </a>
                        <?php if($user['user_type'] == 'seller'): ?>
                        <a href="seller-dashboard.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors mt-2 border-t border-gray-100 pt-3">
                            <i class="fas fa-store w-6 text-lg"></i> Seller Dashboard
                        </a>
                        <?php else: ?>
                        <a href="sell-on-oxxa.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors mt-2 border-t border-gray-100 pt-3">
                            <i class="fas fa-store w-6 text-lg"></i> Sell on OXXA
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Main Content -->
            <div class="flex-1">
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden p-8 md:p-10 text-center flex flex-col items-center justify-center min-h-[400px]">
                    <div class="w-24 h-24 bg-blue-50 rounded-full flex items-center justify-center mb-6">
                        <i class="far fa-eye text-[#0066FF] text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-2">Recently Viewed</h2>
                    <p class="text-gray-500 mb-8 max-w-md mx-auto">Your recently viewed items will appear here.</p>
                    <a href="products.php" class="px-8 py-3 bg-[#0066FF] text-white font-bold rounded-full uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">Start Exploring</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../include/footer.php"); ?>
