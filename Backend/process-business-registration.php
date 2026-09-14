<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: ../site/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['userid'];

    // 1. Business Info
    $business_name = trim($_POST['business_name']);
    $business_type = trim($_POST['business_type']);
    $business_reg_id = trim($_POST['business_reg_id']);
    $date_of_incorporation = !empty($_POST['date_of_incorporation']) ? $_POST['date_of_incorporation'] : null;
    $nature_of_business = trim($_POST['nature_of_business']);

    // 2. Owner Info
    $owner_name = trim($_POST['owner_name']);
    $owner_nic = trim($_POST['owner_nic']);
    $personal_phone = trim($_POST['personal_phone']);
    $personal_email = trim($_POST['personal_email']);
    $business_phone = trim($_POST['business_phone']);
    $business_email = trim($_POST['business_email'] ?? '');

    // 3. Address
    $address_line1 = trim($_POST['address_line1']);
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $province = trim($_POST['province']);

    // 5. Bank Details
    $bank_name = trim($_POST['bank_name']);
    $branch_name = trim($_POST['branch_name']);
    $account_number = trim($_POST['account_number']);
    $account_holder_name = trim($_POST['account_holder_name']);

    // 6. Selling Info
    $selling_categories = isset($_POST['categories']) ? implode(', ', $_POST['categories']) : '';
    $estimated_products = trim($_POST['estimated_products'] ?? '');
    $social_website = trim($_POST['social_website'] ?? '');

    // 7. Declaration
    $declaration = isset($_POST['declaration']) ? 1 : 0;

    // Check if BR number is unique
    $checkStmt = $pdo->prepare("SELECT id FROM seller_profiles WHERE business_reg_id = ?");
    $checkStmt->execute([$business_reg_id]);
    if ($checkStmt->rowCount() > 0) {
        header("Location: ../site/business-registration.php?error=BR Number already registered");
        exit();
    }
    
    // File upload function
    function uploadFile($fileInputName, $targetDir, $allowedTypes, $user_id, $prefix) {
        if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            
            if ($_FILES[$fileInputName]['size'] > 5 * 1024 * 1024) return 'size_error';
            
            $fileType = mime_content_type($_FILES[$fileInputName]['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) return 'type_error';
            
            $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
            $fileName = $prefix . '_' . $user_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetDir . $fileName)) {
                return $fileName;
            }
        }
        return null;
    }

    $imageTypes = ['image/jpeg', 'image/png'];
    $docTypes = ['image/jpeg', 'image/png', 'application/pdf'];

    // Notice we use the same paths that admin/business-registrations.php expects
    $certificate_path = uploadFile('certificate_file', '../image/certificates/', $docTypes, $user_id, 'cert');
    $nic_path = uploadFile('nic_file', '../image/certificates/', $docTypes, $user_id, 'nic');
    $logo_path = uploadFile('logo_file', '../image/logos/', $imageTypes, $user_id, 'logo');
    $shop_photo_path = uploadFile('shop_photo_file', '../image/logos/', $imageTypes, $user_id, 'shop');
    $bank_book_path = uploadFile('bank_book_file', '../image/certificates/', $docTypes, $user_id, 'bank');

    if ($certificate_path === 'size_error' || $nic_path === 'size_error' || $logo_path === 'size_error') {
        header("Location: ../site/business-registration.php?error=A file exceeds 5MB limit");
        exit();
    }
    if ($certificate_path === 'type_error' || $nic_path === 'type_error' || $logo_path === 'type_error') {
        header("Location: ../site/business-registration.php?error=Invalid file format (Use JPG, PNG, PDF)");
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO seller_profiles (
            user_id, business_name, business_type, business_reg_id, business_number, 
            date_of_incorporation, nature_of_business, 
            owner_name, owner_nic, personal_phone, personal_email, business_email,
            address_line1, address_line2, city, postal_code, province,
            certificate_path, logo_path, nic_path, shop_photo_path,
            bank_name, branch_name, account_number, account_holder_name, bank_book_path,
            selling_categories, estimated_products, social_website, declaration, is_approved
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, 
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, 0
        )");
        
        $stmt->execute([
            $user_id, $business_name, $business_type, $business_reg_id, $business_phone,
            $date_of_incorporation, $nature_of_business,
            $owner_name, $owner_nic, $personal_phone, $personal_email, $business_email,
            $address_line1, $address_line2, $city, $postal_code, $province,
            $certificate_path, $logo_path, $nic_path, $shop_photo_path,
            $bank_name, $branch_name, $account_number, $account_holder_name, $bank_book_path,
            $selling_categories, $estimated_products, $social_website, $declaration
        ]);

        header("Location: ../site/business-registration.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        header("Location: ../site/business-registration.php?error=Database Error: " . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../site/business-registration.php");
    exit();
}
?>
