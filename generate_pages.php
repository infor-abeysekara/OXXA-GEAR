<?php
$pages = [
    'settings.php' => ['title' => 'Settings', 'icon' => 'fas fa-cog', 'msg' => 'Manage your account preferences and settings.', 'btn' => 'Back to Profile', 'link' => 'profile.php', 'active' => ''],
    'reviews.php' => ['title' => 'My Reviews', 'icon' => 'far fa-star', 'msg' => 'You haven\'t written any reviews yet.', 'btn' => 'Review Past Purchases', 'link' => 'my-orders.php', 'active' => 'reviews.php'],
    'returns.php' => ['title' => 'My Returns & Refunds', 'icon' => 'fas fa-undo-alt', 'msg' => 'You have no active returns or refunds.', 'btn' => 'View Orders', 'link' => 'my-orders.php', 'active' => 'returns.php'],
    'coupons.php' => ['title' => 'My Coupons', 'icon' => 'fas fa-ticket-alt', 'msg' => 'No active coupons available.', 'btn' => 'Discover Offers', 'link' => 'products.php', 'active' => 'coupons.php'],
    'recently-viewed.php' => ['title' => 'Recently Viewed', 'icon' => 'far fa-eye', 'msg' => 'Your recently viewed items will appear here.', 'btn' => 'Start Exploring', 'link' => 'products.php', 'active' => 'recently-viewed.php'],
];

$template = file_get_contents('site/wishlist.php');

foreach ($pages as $filename => $data) {
    // Replace active state
    $content = str_replace(
        '<a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors">',
        '<a href="wishlist.php" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">',
        $template
    );
    
    if ($data['active'] != '') {
        $content = str_replace(
            '<a href="'.$data['active'].'" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors">',
            '<a href="'.$data['active'].'" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors">',
            $content
        );
        $content = str_replace(
            '<a href="'.$data['active'].'" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-50 hover:text-[#0066FF] transition-colors border-b border-gray-100 pb-4 mb-1">',
            '<a href="'.$data['active'].'" class="flex items-center px-4 py-3 rounded-xl font-bold text-sm bg-blue-50 text-[#0066FF] transition-colors border-b border-gray-100 pb-4 mb-1">',
            $content
        );
    }
    
    // Replace title
    $content = str_replace('Wishlist', $data['title'], $content);
    $content = str_replace('Your '.$data['title'].' is Empty', $data['title'], $content);
    $content = preg_replace('/<h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-2">.*?<\/h2>/s', '<h2 class="text-2xl font-black text-navy uppercase tracking-wide mb-2">'.$data['title'].'</h2>', $content);
    
    // Replace icon
    $content = str_replace('far fa-heart text-[#0066FF] text-4xl', $data['icon'].' text-[#0066FF] text-4xl', $content);
    
    // Replace message
    $content = preg_replace('/<p class="text-gray-500 mb-8 max-w-md mx-auto">.*?<\/p>/s', '<p class="text-gray-500 mb-8 max-w-md mx-auto">'.$data['msg'].'</p>', $content);
    
    // Replace button
    $content = preg_replace('/<a href="products\.php" class="px-8 py-3 bg-\[#0066FF\].*?<\/a>/s', '<a href="'.$data['link'].'" class="px-8 py-3 bg-[#0066FF] text-white font-bold rounded-full uppercase tracking-wide hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/30">'.$data['btn'].'</a>', $content);

    file_put_contents('site/'.$filename, $content);
}
echo "Done";
?>
