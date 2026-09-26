<?php
session_start();
include('../../include/connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['userid'])) {
    $user_id = $_SESSION['userid'];
    $order_id = $_POST['order_id'];
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'] ?? 5;
    $title = trim($_POST['title']);
    $comment = trim($_POST['comment']);
    $fit_feedback = $_POST['fit_feedback'] ?? 'True to Size';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    // Check if review already exists
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? AND product_id = ?");
    $checkStmt->execute([$order_id, $product_id]);
    if ($checkStmt->rowCount() > 0) {
        $_SESSION['error_msg'] = "You have already reviewed this item for this order.";
        header("Location: ../order-details.php?id=" . $order_id);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Get seller_id from products
        $sellerStmt = $pdo->prepare("SELECT seller_id FROM products WHERE id = ?");
        $sellerStmt->execute([$product_id]);
        $seller_id = $sellerStmt->fetchColumn();

        // Insert review
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, seller_id, order_id, rating, title, comment, fit_feedback, is_anonymous, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$user_id, $product_id, $seller_id, $order_id, $rating, $title, $comment, $fit_feedback, $is_anonymous]);
        $review_id = $pdo->lastInsertId();

        // Handle Image Uploads
        if (!empty($_FILES['review_images']['name'][0])) {
            $upload_dir = '../../assets/uploads/reviews/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $imgCount = count($_FILES['review_images']['name']);
            // Enforce max 4 images
            if ($imgCount > 4) $imgCount = 4;

            for ($i = 0; $i < $imgCount; $i++) {
                if ($_FILES['review_images']['error'][$i] === 0) {
                    $ext = pathinfo($_FILES['review_images']['name'][$i], PATHINFO_EXTENSION);
                    $new_name = 'rev_' . uniqid() . '_' . time() . '.' . $ext;
                    $target_file = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($_FILES['review_images']['tmp_name'][$i], $target_file)) {
                        $imgStmt = $pdo->prepare("INSERT INTO review_images (review_id, image_path) VALUES (?, ?)");
                        $imgStmt->execute([$review_id, $new_name]);
                    }
                }
            }
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Your review has been submitted successfully! Thank you.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Failed to submit review: " . $e->getMessage();
    }
    
    header("Location: ../order-details.php?id=" . $order_id);
    exit();
} else {
    header("Location: ../index.php");
    exit();
}
?>
