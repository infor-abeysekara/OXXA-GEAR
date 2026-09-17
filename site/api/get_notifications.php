<?php
session_start();
header('Content-Type: application/json');

include_once(__DIR__ . '/../../include/connection.php');
include_once(__DIR__ . '/../../include/functions.php');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['userid'];

try {
    // Get unread count
    $count = getUnreadNotificationsCount($conn, $user_id);
    
    // Get latest 5 notifications
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'notifications' => $notifications
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'count' => 0,
        'notifications' => []
    ]);
}
?>
