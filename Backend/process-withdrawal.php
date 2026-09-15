<?php
session_start();
include_once("../include/connection.php");

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if (!$request_id || !$action) {
        die("Invalid request parameters.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Fetch the withdrawal request and lock it
        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            throw new Exception("Withdrawal request not found.");
        }

        $seller_id = $req['seller_id'];
        $amount = $req['amount'];

        // 2. Fetch the seller's wallet and lock it
        $walletStmt = $pdo->prepare("SELECT * FROM seller_wallets WHERE seller_id = ? FOR UPDATE");
        $walletStmt->execute([$seller_id]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            throw new Exception("Seller wallet not found.");
        }

        if ($action == 'approve') {
            if ($req['status'] != 'Pending') {
                throw new Exception("Only Pending requests can be approved.");
            }
            
            $updateReq = $pdo->prepare("UPDATE withdrawal_requests SET status = 'Approved' WHERE id = ?");
            $updateReq->execute([$request_id]);

        } elseif ($action == 'reject') {
            if ($req['status'] != 'Pending') {
                throw new Exception("Only Pending requests can be rejected.");
            }
            
            $reject_reason = trim($_POST['reject_reason'] ?? '');
            if(empty($reject_reason)) {
                throw new Exception("Rejection reason is required.");
            }

            // Update request status
            $updateReq = $pdo->prepare("UPDATE withdrawal_requests SET status = 'Rejected', reject_reason = ? WHERE id = ?");
            $updateReq->execute([$reject_reason, $request_id]);

            // Release locked funds back to pending balance
            $updateWallet = $pdo->prepare("UPDATE seller_wallets SET locked_balance = locked_balance - ?, pending_balance = pending_balance + ? WHERE seller_id = ?");
            $updateWallet->execute([$amount, $amount, $seller_id]);

        } elseif ($action == 'pay') {
            if ($req['status'] != 'Approved') {
                throw new Exception("Only Approved requests can be marked as Paid.");
            }
            
            $reference_no = trim($_POST['reference_no'] ?? '');
            if(empty($reference_no)) {
                throw new Exception("Reference number is required.");
            }

            // Handle Proof Slip Upload
            $proof_path = '';
            if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $filename = $_FILES['proof_image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file format for proof. Use JPG, PNG or PDF.");
                }

                $new_filename = 'proof_' . $request_id . '_' . time() . '.' . $ext;
                $upload_dir = '../assets/uploads/';
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $proof_path = $new_filename;
                } else {
                    throw new Exception("Failed to upload proof slip.");
                }
            } else {
                throw new Exception("Proof slip is required.");
            }

            // Update request status
            $updateReq = $pdo->prepare("UPDATE withdrawal_requests SET status = 'Paid', reference_no = ?, proof_image = ?, paid_at = NOW() WHERE id = ?");
            $updateReq->execute([$reference_no, $proof_path, $request_id]);

            // Move funds from locked to paid
            $updateWallet = $pdo->prepare("UPDATE seller_wallets SET locked_balance = locked_balance - ?, paid_balance = paid_balance + ? WHERE seller_id = ?");
            $updateWallet->execute([$amount, $amount, $seller_id]);

        } else {
            throw new Exception("Invalid action.");
        }

        $pdo->commit();
        
        // Redirect back with success message
        header("Location: ../admin/withdrawal-requests.php?success=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage() . " <br><a href='../admin/withdrawal-requests.php'>Go Back</a>");
    }
}
?>
