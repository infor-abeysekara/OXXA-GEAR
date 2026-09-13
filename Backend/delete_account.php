<?php
session_start();
include('../include/connection.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header('Location: ../site/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['userid'];
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        $_SESSION['error'] = 'Password is required to delete account';
        header('Location: ../site/profile.php');
        exit;
    }
    
    try {
        // Verify password
        $query = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error'] = 'User not found';
            header('Location: ../site/profile.php');
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Check password (assuming MD5 hash based on the database structure)
        if (md5($password) !== $user['password']) {
            $_SESSION['error'] = 'Incorrect password';
            header('Location: ../site/profile.php');
            exit;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete user data in order (respecting foreign key constraints)
            $tables = [
                'cart' => 'Userid',
                'orderhistory' => 'user_id', 
                'ordertable' => 'user_id',
                'production' => 'user_id',
                'businessregistration' => 'user_id',
                'notifications' => 'user_id',
                'users' => 'user_id'
            ];
            
            foreach ($tables as $table => $column) {
                $deleteQuery = "DELETE FROM $table WHERE $column = ?";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->bind_param("s", $user_id);
                $deleteStmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Destroy session
            session_destroy();
            
            // Redirect to home with success message
            header('Location: ../site/index.php?success=account_deleted');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $_SESSION['error'] = 'Failed to delete account. Please try again.';
            header('Location: ../site/profile.php');
            exit;
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Database error occurred';
        header('Location: ../site/profile.php');
        exit;
    }
} else {
    header('Location: ../site/profile.php');
    exit;
}
?>