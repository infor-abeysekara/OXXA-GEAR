<?php
session_start();
include_once("../include/connection.php");

// Check if user is logged in and is a seller
if(!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header("Location: ../site/login.php");
    exit();
}

if(isset($_POST['add_product'])) {
    $user_id = $_SESSION['userid'];
    $pname = $_POST['pname'];
    $brand = $_POST['brand'];
    $categories = $_POST['categories'];
    $price = $_POST['price'];
    $qty = $_POST['qty'];
    $discription = $_POST['discription'];
    
    // Handle file upload
    $image = "";
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../image/";
        $image = time() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image;
        
        // Check if image file is actual image
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // File uploaded successfully
            } else {
                $_SESSION['error'] = "Sorry, there was an error uploading your file.";
                header("Location: ../site/add-product.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "File is not an image.";
            header("Location: ../site/add-product.php");
            exit();
        }
    }
    
    // Generate unique product ID
    $pid = 'P' . time() . rand(100, 999);
    
    // Insert product into database
    $insert_query = "INSERT INTO production (pid, user_id, pname, brand, categories, price, qty, discription, image, Add_date, approve) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("sssssdsss", $pid, $user_id, $pname, $brand, $categories, $price, $qty, $discription, $image);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "Product added successfully!";
        
        // Insert product sizes if provided
        if(isset($_POST['additional_sizes']) && !empty($_POST['additional_sizes'])) {
            $sizes = $_POST['additional_sizes'];
            $size_prices = $_POST['additional_prices'];
            $size_quantities = $_POST['additional_quantities'];
            
            for($i = 0; $i < count($sizes); $i++) {
                if(!empty($sizes[$i])) {
                    $size_insert = "INSERT INTO productsize (pid, size, price, qty) VALUES (?, ?, ?, ?)";
                    $size_stmt = $conn->prepare($size_insert);
                    $size_stmt->bind_param("ssdi", $pid, $sizes[$i], $size_prices[$i], $size_quantities[$i]);
                    $size_stmt->execute();
                }
            }
        }
        
        header("Location: ../site/add-product.php?success=1");
    } else {
        $_SESSION['error'] = "Error adding product: " . $conn->error;
        header("Location: ../site/add-product.php");
    }
} else {
    header("Location: ../site/add-product.php");
}
?>