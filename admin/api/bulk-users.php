<?php
session_start();
include_once(__DIR__ . "/../../include/connection.php");

header('Content-Type: application/json');

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['action']) || !isset($data['ids']) || !is_array($data['ids']) || count($data['ids']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$action = $data['action'];
$ids = array_map('intval', $data['ids']);
$ids_list = implode(',', $ids);

if ($action === 'suspend') {
    // Suspend users (is_approved = 0)
    $update_query = "UPDATE users SET is_approved = 0 WHERE id IN ($ids_list)";
    // Hide their products if they are sellers
    $prod_query = "UPDATE products SET status = 'suspended' WHERE seller_id IN ($ids_list) AND status = 'active'";
    
    if ($conn->query($update_query)) {
        $conn->query($prod_query);
        echo json_encode(['success' => true, 'message' => count($ids) . ' user(s) suspended successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to suspend users.']);
    }
} elseif ($action === 'activate' || $action === 'unsuspend') {
    // Activate users (is_approved = 1)
    $update_query = "UPDATE users SET is_approved = 1 WHERE id IN ($ids_list)";
    // Restore their products if they are sellers
    $prod_query = "UPDATE products SET status = 'active' WHERE seller_id IN ($ids_list) AND status = 'suspended'";
    
    if ($conn->query($update_query)) {
        $conn->query($prod_query);
        echo json_encode(['success' => true, 'message' => count($ids) . ' user(s) activated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to activate users.']);
    }
} elseif ($action === 'delete') {
    // Delete users
    $delete_query = "DELETE FROM users WHERE id IN ($ids_list)";
    // Products will be deleted if foreign key cascades, else we delete them manually
    $prod_query = "DELETE FROM products WHERE seller_id IN ($ids_list)";
    
    if ($conn->query($delete_query)) {
        $conn->query($prod_query);
        echo json_encode(['success' => true, 'message' => count($ids) . ' user(s) deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete users.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
