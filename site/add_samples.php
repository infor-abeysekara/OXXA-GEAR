<?php
include_once(__DIR__ . '/../include/connection.php');
include_once(__DIR__ . '/../include/functions.php');

$user_id = 4; // Target a specific user, or admin

// Create some dummy notifications
$samples = [
    [
        'message' => 'Business Verified! - Your business The Chance Sports has been approved. You can now add products and start selling.',
        'type' => 'success',
        'category' => 'Business',
        'action_url' => 'site/seller-add-product.php'
    ],
    [
        'message' => 'New Order #OX1234! - Rs.8500 - US 8 GREEN x1',
        'type' => 'order',
        'category' => 'Orders',
        'action_url' => 'site/order-details.php?id=OX1234'
    ],
    [
        'message' => 'Payout Paid - Payout Rs.15,500 paid to Commercial 80..456 - Ref #PAY123',
        'type' => 'success',
        'category' => 'Payouts',
        'action_url' => 'site/seller-dashboard.php'
    ],
    [
        'message' => 'Low Stock Alert! - Nike Vomero Plus US 9 GREEN - Only 2 left',
        'type' => 'warning',
        'category' => 'Business',
        'action_url' => 'site/seller-edit-product.php?id=P0001'
    ],
    [
        'message' => 'New 5★ Review - "Amazing shoes, very comfortable!" - Kamal P.',
        'type' => 'info',
        'category' => 'Reviews',
        'action_url' => 'site/product-details.php?id=P0001'
    ],
    [
        'message' => 'Return requested - Order #OX1222 return requested due to size issue.',
        'type' => 'error',
        'category' => 'Orders',
        'action_url' => 'site/returns.php'
    ]
];

foreach ($samples as $s) {
    addNotification($conn, $user_id, $s['message'], $s['type'], $s['category'], $s['action_url']);
}

echo "Sample notifications added successfully!";
?>
