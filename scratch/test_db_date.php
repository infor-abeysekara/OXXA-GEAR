<?php
include('../include/connection.php');
try {
    $stmt = $pdo->prepare("UPDATE users SET dob = ? WHERE id = 4");
    $stmt->execute(['']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
