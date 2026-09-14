<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userid = $_SESSION['userid'];
$action = $_POST['action'] ?? '';

if ($action == 'add' || $action == 'update') {
    $id = $_POST['id'] ?? null;
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $label = trim($_POST['label'] ?? 'Home');
    $is_default = isset($_POST['is_default']) && $_POST['is_default'] == '1' ? 1 : 0;

    if (empty($full_name) || empty($phone) || empty($address_line1) || empty($city) || empty($province)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Check if user has any addresses
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
        $checkStmt->execute([$userid]);
        $count = $checkStmt->fetchColumn();

        if ($count == 0) {
            $is_default = 1; // Auto default for first address
        }

        if ($is_default) {
            // Remove default from other addresses
            $updateDefaultStmt = $pdo->prepare("UPDATE user_addresses SET is_default_shipping = 0 WHERE user_id = ?");
            $updateDefaultStmt->execute([$userid]);
        }

        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, full_name, phone1, address_line1, address_line2, city, postal_code, province, label, is_default_shipping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userid, $full_name, $phone, $address_line1, $address_line2, $city, $postal_code, $province, $label, $is_default]);
            $message = 'Address added successfully';
        } else {
            $stmt = $pdo->prepare("UPDATE user_addresses SET full_name = ?, phone1 = ?, address_line1 = ?, address_line2 = ?, city = ?, postal_code = ?, province = ?, label = ?, is_default_shipping = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$full_name, $phone, $address_line1, $address_line2, $city, $postal_code, $province, $label, $is_default, $id, $userid]);
            $message = 'Address updated successfully';
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $message]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} elseif ($action == 'delete') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$id, $userid])) {
            echo json_encode(['success' => true, 'message' => 'Address deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete address']);
        }
    }
} elseif ($action == 'set_default') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE user_addresses SET is_default_shipping = 0 WHERE user_id = ?")->execute([$userid]);
            $pdo->prepare("UPDATE user_addresses SET is_default_shipping = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userid]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Default address updated']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
