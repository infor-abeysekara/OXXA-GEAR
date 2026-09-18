<?php
session_start();
include('../include/connection.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['userid'];
    $data = json_decode(file_get_contents('php://input'), true);
    $password = $data['password'] ?? '';
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required to delete account']);
        exit;
    }
    
    try {
        // Verify password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Verify password (using md5)
        if (md5($password) !== $user['password']) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            exit;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete related data
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM user_addresses WHERE user_id = ?")->execute([$user_id]);
        // Hard delete user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Destroy session
        session_destroy();
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>