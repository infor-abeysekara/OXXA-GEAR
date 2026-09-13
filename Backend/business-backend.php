<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: ../site/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_business'])) {
    
    $user_id = $_SESSION['userid'];
    $bname = trim($_POST['bname']);
    $btype = trim($_POST['btype']);
    $bregid = trim($_POST['bregid']);
    $bnumber = trim($_POST['bnumber']);

    // Validation
    if(empty($bname) || empty($btype) || empty($bregid) || empty($bnumber)) {
        header('Location: ../site/business-registration.php?error=missing_fields');
        exit();
    }

    // Handle File Uploads
    $uploadDir = '../assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $certificate_path = '';
    if (isset($_FILES['bcertificate']) && $_FILES['bcertificate']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['bcertificate']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'])) {
            header('Location: ../site/business-registration.php?error=format');
            exit();
        }
        $certificate_path = 'cert_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['bcertificate']['tmp_name'], $uploadDir . $certificate_path);
    } else {
        header('Location: ../site/business-registration.php?error=missing_certificate');
        exit();
    }

    $logo_path = '';
    if (isset($_FILES['blogo']) && $_FILES['blogo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['blogo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $logo_path = 'logo_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['blogo']['tmp_name'], $uploadDir . $logo_path);
        }
    }

    try {
        // Delete any existing rejected application to start fresh
        $stmt = $pdo->prepare("DELETE FROM seller_profiles WHERE user_id = ? AND is_approved = 0");
        $stmt->execute([$user_id]);
        
        $insertStmt = $pdo->prepare("INSERT INTO seller_profiles (user_id, business_name, business_type, business_reg_id, business_number, certificate_path, logo_path, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        
        if ($insertStmt->execute([$user_id, $bname, $btype, $bregid, $bnumber, $certificate_path, $logo_path])) {
            header('Location: ../site/business-registration.php?success=submitted');
            exit();
        } else {
            header('Location: ../site/business-registration.php?error=database');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: ../site/business-registration.php?error=database');
        exit();
    }
} else {
    header('Location: ../site/business-registration.php');
    exit();
}
?>
