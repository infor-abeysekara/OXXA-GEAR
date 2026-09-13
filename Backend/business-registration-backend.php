<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: ../site/login.php');
    exit();
}

if (isset($_POST['register_business']) || isset($_POST['update_business'])) {
    $user_id = $_SESSION['userid'];
    $bname = trim($_POST['bname']);
    $bnumber = trim($_POST['bnumber']);
    $bregid = trim($_POST['bregid']);
    $date = $_POST['date'];
    $btype = $_POST['btype'];
    $is_update = isset($_POST['update_business']);

    // Validation
    if (empty($bname)) {
        header('Location: ../site/business-registration.php?error=bname');
        exit();
    }
    
    if (empty($bnumber)) {
        header('Location: ../site/business-registration.php?error=bnumber');
        exit();
    }
    
    if (empty($bregid)) {
        header('Location: ../site/business-registration.php?error=bregid');
        exit();
    }
    
    if (empty($btype)) {
        header('Location: ../site/business-registration.php?error=btype');
        exit();
    }

    // Handle certificate upload
    $certificateName = '';
    if (isset($_FILES['bcertificate']) && $_FILES['bcertificate']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../image/certificates/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $fileType = $_FILES['bcertificate']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            header('Location: ../site/business-registration.php?error=format');
            exit();
        }
        
        if ($_FILES['bcertificate']['size'] > 10 * 1024 * 1024) { // 10MB limit
            header('Location: ../site/business-registration.php?error=large');
            exit();
        }
        
        $extension = pathinfo($_FILES['bcertificate']['name'], PATHINFO_EXTENSION);
        $certificateName = 'cert_' . $user_id . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $certificateName;
        
        if (!move_uploaded_file($_FILES['bcertificate']['tmp_name'], $targetPath)) {
            header('Location: ../site/business-registration.php?error=upload');
            exit();
        }
    } elseif (!$is_update) {
        // Certificate is required for new registrations
        header('Location: ../site/business-registration.php?error=bcertificate');
        exit();
    }

    // Handle logo upload (optional)
    $logoName = '';
    if (isset($_FILES['blogo']) && $_FILES['blogo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../image/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $fileType = $_FILES['blogo']['type'];
        
        if (in_array($fileType, $allowedTypes) && $_FILES['blogo']['size'] <= 5 * 1024 * 1024) {
            $extension = pathinfo($_FILES['blogo']['name'], PATHINFO_EXTENSION);
            $logoName = 'logo_' . $user_id . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $logoName;
            
            move_uploaded_file($_FILES['blogo']['tmp_name'], $targetPath);
        }
    }

    if ($is_update) {
        // Update existing registration
        $updateFields = [
            "bname = ?",
            "bnumber = ?", 
            "bregid = ?",
            "date = ?",
            "btype = ?",
            "approve = 0" // Reset approval status
        ];
        $params = [$bname, $bnumber, $bregid, $date, $btype];
        $types = "sisss";

        if ($certificateName) {
            $updateFields[] = "bcertificate = ?";
            $params[] = $certificateName;
            $types .= "s";
        }

        if ($logoName) {
            $updateFields[] = "blogo = ?";
            $params[] = $logoName;
            $types .= "s";
        }

        $params[] = $user_id;
        $types .= "s";

        $updateQuery = "UPDATE businessregistration SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Add notification for admin
            addNotification($conn, 'U001', "Business registration updated by " . $_SESSION['firstname'] . " " . $_SESSION['lastname'] . " (ID: $user_id)", 'info');
            header('Location: ../site/business-registration.php?success=updated');
        } else {
            header('Location: ../site/business-registration.php?error=database');
        }
    } else {
        // Insert new registration
        $insertQuery = "INSERT INTO businessregistration (user_id, bname, bnumber, bregid, date, btype, bcertificate, blogo, approve) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ssisssss", $user_id, $bname, $bnumber, $bregid, $date, $btype, $certificateName, $logoName);
        
        if ($stmt->execute()) {
            // Add notification for admin
            addNotification($conn, 'U001', "New business registration from " . $_SESSION['firstname'] . " " . $_SESSION['lastname'] . " (ID: $user_id)", 'info');
            header('Location: ../site/business-registration.php?success=registered');
        } else {
            header('Location: ../site/business-registration.php?error=database');
        }
    }
} else {
    header('Location: ../site/business-registration.php');
}
?>