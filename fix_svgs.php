<?php
$files = [
    'site/components/seller-products-list.php' => [
        '<img src="../image/empty-products.svg" onerror="this.src=\'https://illustrations.popsy.co/gray/crashed-error.svg\'" class="w-48 h-48 mx-auto mb-4 opacity-50">' => '<div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-4xl"><i class="fas fa-box-open"></i></div>',
        '<img src="../image/empty-products.svg" onerror="this.src=\'https://illustrations.popsy.co/gray/crashed-error.svg\'" class="w-32 h-32 mx-auto mb-4 opacity-50">' => '<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-2xl"><i class="fas fa-box-open"></i></div>'
    ],
    'site/components/seller-orders.php' => [
        '<img src="../image/empty-orders.svg" onerror="this.src=\'https://illustrations.popsy.co/gray/crashed-error.svg\'" class="w-48 h-48 mx-auto mb-4 opacity-50">' => '<div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-4xl"><i class="fas fa-shopping-bag"></i></div>',
        '<img src="../image/empty-orders.svg" onerror="this.src=\'https://illustrations.popsy.co/gray/crashed-error.svg\'" class="w-32 h-32 mx-auto mb-4 opacity-50">' => '<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-2xl"><i class="fas fa-shopping-bag"></i></div>'
    ],
    'site/components/seller-earnings.php' => [
        '<img src="../image/empty-wallet.svg" onerror="this.src=\'https://illustrations.popsy.co/gray/crashed-error.svg\'" class="w-48 h-48 mx-auto mb-4 opacity-50">' => '<div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-4xl"><i class="fas fa-wallet"></i></div>',
        '<img src="../image/empty-wallet.svg" onerror="this.src=\'https://illustrations.popsy.co/gray/crashed-error.svg\'" class="w-32 h-32 mx-auto mb-4 opacity-50">' => '<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400 text-2xl"><i class="fas fa-wallet"></i></div>'
    ]
];

foreach ($files as $file => $replacements) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
?>
