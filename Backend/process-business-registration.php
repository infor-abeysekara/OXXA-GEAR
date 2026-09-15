<?php
session_start();
include('../include/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['userid'];
    $errors = [];

    // Helper to add error
    $addError = function($field, $message) use (&$errors) {
        if (!isset($errors[$field])) {
            $errors[$field] = $message;
        }
    };

    // 1. Business Info
    $business_name = trim($_POST['business_name'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    $business_reg_id = trim($_POST['business_reg_id'] ?? '');
    $date_of_incorporation = trim($_POST['date_of_incorporation'] ?? '');
    $nature_of_business = trim($_POST['nature_of_business'] ?? '');

    if (!$business_name || !preg_match('/^[a-zA-Z0-9 &\'.-]{3,100}$/', $business_name)) {
        $addError('business_name', 'Invalid business name.');
    }
    if (!in_array($business_type, ['Sole Proprietorship', 'Partnership', 'Private Limited Company', 'Other'])) {
        $addError('business_type', 'Invalid business type.');
    }
    if (!$business_reg_id || !preg_match('/^[A-Z]{1,3}-[A-Z]?-?\d{4,6}$/i', $business_reg_id)) {
        $addError('business_reg_id', 'Invalid BR format.');
    } else {
        $business_reg_id = strtoupper($business_reg_id); // auto uppercase
    }
    if ($date_of_incorporation) {
        $doi = new DateTime($date_of_incorporation);
        $now = new DateTime();
        $min = new DateTime('1975-01-01');
        if ($doi > $now) $addError('date_of_incorporation', 'Date cannot be in the future.');
        if ($doi < $min) $addError('date_of_incorporation', 'Date cannot be older than 1975.');
    } else {
        $addError('date_of_incorporation', 'Date is required.');
    }
    if (!$nature_of_business) $addError('nature_of_business', 'Nature of business is required.');

    // 2. Owner Info
    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_nic = trim($_POST['owner_nic'] ?? '');
    $personal_phone = trim($_POST['personal_phone'] ?? '');
    $personal_email = trim($_POST['personal_email'] ?? '');
    $business_phone = trim($_POST['business_phone'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');

    if (!$owner_name || !preg_match('/^[a-zA-Z\s]{3,100}$/', $owner_name)) {
        $addError('owner_name', 'Invalid owner name.');
    }
    if (!$owner_nic || !preg_match('/^([0-9]{9}[vVxX]|[0-9]{12})$/', $owner_nic)) {
        $addError('owner_nic', 'Invalid NIC format.');
    }
    $personal_phone = preg_replace('/\D/', '', $personal_phone);
    if (!$personal_phone || !preg_match('/^07[0-8]\d{7}$/', $personal_phone)) {
        $addError('personal_phone', 'Invalid personal SL mobile.');
    }
    $business_phone = preg_replace('/\D/', '', $business_phone);
    if (!$business_phone || !preg_match('/^0\d{9}$/', $business_phone)) {
        $addError('business_phone', 'Invalid business phone.');
    }
    if (!$personal_email || !filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
        $addError('personal_email', 'Invalid personal email.');
    }
    if ($business_email && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
        $addError('business_email', 'Invalid business email.');
    }

    // 3. Address
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $province = trim($_POST['province'] ?? '');

    if (strlen($address_line1) < 5) $addError('address_line1', 'Address Line 1 too short.');
    if (!$city) $addError('city', 'City is required.');
    if (!$postal_code || !preg_match('/^[0-9]{5}$/', $postal_code)) {
        $addError('postal_code', 'Invalid postal code.');
    }
    if (!$province) $addError('province', 'Province is required.');

    // 5. Bank Details
    $bank_name = trim($_POST['bank_name'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');

    if (!$bank_name) $addError('bank_name', 'Bank name is required.');
    if (!$branch_name) $addError('branch_name', 'Branch name is required.');
    $account_number = preg_replace('/\D/', '', $account_number);
    if (!$account_number || !preg_match('/^\d{10,16}$/', $account_number)) {
        $addError('account_number', 'Invalid account number.');
    }
    if (!$account_holder_name) $addError('account_holder_name', 'Account holder name is required.');

    // 6. Selling Info
    $categories = $_POST['categories'] ?? [];
    if (empty($categories) || !is_array($categories)) {
        $addError('categories', 'Select at least one category.');
    }
    $selling_categories = is_array($categories) ? implode(', ', $categories) : '';
    $estimated_products = trim($_POST['estimated_products'] ?? '');
    $social_website = trim($_POST['social_website'] ?? '');

    if ($social_website && !filter_var($social_website, FILTER_VALIDATE_URL)) {
        $addError('social_website', 'Invalid URL.');
    }

    // 7. Declaration
    $declaration = isset($_POST['declaration']) ? 1 : 0;
    if (!$declaration) {
        $addError('declaration', 'You must agree to the declaration.');
    }

    // Database UNIQUE checks
    try {
        // Check BR Number
        if (!isset($errors['business_reg_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM seller_profiles WHERE business_reg_id = ?");
            $stmt->execute([$business_reg_id]);
            if ($stmt->rowCount() > 0) $addError('business_reg_id', 'BR Number already registered.');
        }

        // Check NIC (Assuming NIC uniqueness in seller_profiles)
        if (!isset($errors['owner_nic'])) {
            $stmt = $pdo->prepare("SELECT id FROM seller_profiles WHERE owner_nic = ?");
            $stmt->execute([$owner_nic]);
            if ($stmt->rowCount() > 0) $addError('owner_nic', 'NIC already registered.');
        }

        // Check Email/Phone globally in users table (since they might be used for login)
        // Note: they are already logged in, so their main email/phone is there. We only check if it belongs to someone ELSE.
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $stmt->execute([$personal_phone, $user_id]);
        if ($stmt->rowCount() > 0) $addError('personal_phone', 'Phone is already registered to another account.');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$personal_email, $user_id]);
        if ($stmt->rowCount() > 0) $addError('personal_email', 'Email is already registered to another account.');

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error during validation.']);
        exit();
    }

    // If there are errors so far, stop.
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit();
    }

    // File upload function
    function uploadFile($fileInputName, $targetDir, $allowedTypes, $user_id, $prefix, $maxMB, $isRequired = true) {
        global $errors;
        
        if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            
            // Check file size
            if ($_FILES[$fileInputName]['size'] > $maxMB * 1024 * 1024) {
                $errors[$fileInputName] = "File exceeds {$maxMB}MB limit.";
                return null;
            }
            
            // Verify Mime Type reliably
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES[$fileInputName]['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowedTypes)) {
                $errors[$fileInputName] = "Invalid file type: " . $mime;
                return null;
            }

            // Specific check for logo dimensions
            if ($prefix === 'logo' && strpos($mime, 'image/') === 0) {
                $imgSize = getimagesize($_FILES[$fileInputName]['tmp_name']);
                if ($imgSize === false || $imgSize[0] < 200 || $imgSize[1] < 200) {
                    $errors[$fileInputName] = "Logo must be at least 200x200px.";
                    return null;
                }
            }
            
            $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
            // Sanitize file name
            $fileName = $prefix . '_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            
            if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetDir . $fileName)) {
                return $fileName;
            } else {
                $errors[$fileInputName] = "Failed to upload file.";
                return null;
            }
        } else {
            if ($isRequired) {
                $errors[$fileInputName] = "This file is required.";
            }
            return null;
        }
    }

    $imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $docTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    // Notice we use the same paths that admin/business-registrations.php expects
    $certificate_path = uploadFile('certificate_file', '../image/certificates/', $docTypes, $user_id, 'cert', 5, true);
    $nic_path = uploadFile('nic_file', '../image/certificates/', $docTypes, $user_id, 'nic', 5, true);
    $logo_path = uploadFile('logo_file', '../image/logos/', $imageTypes, $user_id, 'logo', 2, true);
    $shop_photo_path = uploadFile('shop_photo_file', '../image/logos/', $imageTypes, $user_id, 'shop', 3, false);
    $bank_book_path = uploadFile('bank_book_file', '../image/certificates/', $docTypes, $user_id, 'bank', 5, false);

    if (!empty($errors)) {
        // If files failed, stop and return errors
        echo json_encode(['success' => false, 'errors' => $errors]);
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

        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save record to database.', 'db' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
