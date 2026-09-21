<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: ../site/index.php?open=login');
    exit();
}

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $userid = $_SESSION['userid'];
    $uploadDir = '../image/logos/';
    
    // Check if seller profile exists
    $stmt = $pdo->prepare("SELECT logo_path FROM seller_profiles WHERE user_id = ?");
    $stmt->execute([$userid]);
    $business = $stmt->fetch();
    
    if (!$business) {
        $_SESSION['error'] = 'Business profile not found.';
        header('Location: ../site/business-registration.php');
        exit();
    }
    
    $uploadedFileName = uploadImage($_FILES['logo'], $uploadDir, 'logo_' . $userid . '_');
    
    if ($uploadedFileName === 'type_error') {
        $_SESSION['error'] = 'Invalid image format. Use JPG, JPEG, or PNG.';
    } elseif ($uploadedFileName === 'size_error') {
        $_SESSION['error'] = 'Image size too large. Maximum 10MB allowed.';
    } elseif ($uploadedFileName) {
        // Delete old logo
        if (!empty($business['logo_path']) && file_exists($uploadDir . $business['logo_path'])) {
            unlink($uploadDir . $business['logo_path']);
        }
        
        $updateStmt = $pdo->prepare("UPDATE seller_profiles SET logo_path = ? WHERE user_id = ?");
        if ($updateStmt->execute([$uploadedFileName, $userid])) {
            $_SESSION['success'] = 'Business logo updated successfully!';
        } else {
            $_SESSION['error'] = 'Database error occurred.';
        }
    } else {
        $_SESSION['error'] = 'Failed to upload image.';
    }
}

header('Location: ../site/business-registration.php');
exit();
?>
