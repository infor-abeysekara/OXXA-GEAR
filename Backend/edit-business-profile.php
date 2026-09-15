<?php
session_start();
include('../include/connection.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header("Location: ../site/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['userid'];

    // Retrieve inputs
    $business_name = trim($_POST['business_name'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    $nature_of_business = trim($_POST['nature_of_business'] ?? '');
    
    $owner_name = trim($_POST['owner_name'] ?? '');
    $personal_phone = trim($_POST['personal_phone'] ?? '');
    $business_number = trim($_POST['business_number'] ?? '');
    $personal_email = trim($_POST['personal_email'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $province = trim($_POST['province'] ?? '');
    
    // Bank Details
    $bank_name = trim($_POST['bank_name'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');

    // Selling Information
    $categories = $_POST['categories'] ?? [];
    $selling_categories = is_array($categories) ? implode(', ', $categories) : '';

    // Note: BR Number, Owner NIC, Date of Incorporation are NOT updated because they are locked.

    // Basic Validation
    if (empty($business_name) || empty($owner_name) || empty($personal_phone) || empty($address_line1) || empty($city)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: ../site/business-registration.php");
        exit();
    }

    // Update the database and set is_approved = 0
    $update_query = "UPDATE seller_profiles SET 
        business_name = ?, 
        business_type = ?, 
        nature_of_business = ?, 
        owner_name = ?, 
        personal_phone = ?, 
        business_number = ?, 
        personal_email = ?, 
        business_email = ?, 
        address_line1 = ?, 
        address_line2 = ?, 
        city = ?, 
        postal_code = ?, 
        province = ?,
        bank_name = ?,
        branch_name = ?,
        account_number = ?,
        account_holder_name = ?,
        selling_categories = ?,
        is_approved = 0
        WHERE user_id = ?";

    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssssssssssssssssssi", 
        $business_name, 
        $business_type, 
        $nature_of_business, 
        $owner_name, 
        $personal_phone, 
        $business_number, 
        $personal_email, 
        $business_email, 
        $address_line1, 
        $address_line2, 
        $city, 
        $postal_code, 
        $province,
        $bank_name,
        $branch_name,
        $account_number,
        $account_holder_name,
        $selling_categories,
        $user_id
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully. Your account is now under review.";
        header("Location: ../site/business-registration.php?success=profile_updated");
        exit();
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
        header("Location: ../site/business-registration.php");
        exit();
    }
} else {
    header("Location: ../site/business-registration.php");
    exit();
}
?>
