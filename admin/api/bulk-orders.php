<?php
// admin/api/bulk-orders.php - Enterprise Bulk Order Actions API
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../../include/connection.php");
include_once(__DIR__ . "/../../include/functions.php");

header('Content-Type: application/json');

// Check admin authorization
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Read payload (supports JSON or Form data)
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true) ?? $_POST;

$action = $data['action'] ?? '';
$order_ids = $data['order_ids'] ?? [];

// Single order fallback
if (!empty($data['order_id']) && empty($order_ids)) {
    $order_ids = [(int)$data['order_id']];
}

if (is_string($order_ids)) {
    $order_ids = array_filter(array_map('intval', explode(',', $order_ids)));
}

if (empty($action) || empty($order_ids)) {
    echo json_encode(['success' => false, 'message' => 'Missing action or target order IDs']);
    exit();
}

$idPlaceholders = implode(',', array_fill(0, count($order_ids), '?'));

try {
    $pdo->beginTransaction();

    switch ($action) {
        case 'mark_packed':
            $stmt = $pdo->prepare("UPDATE orders SET status = 'PACKED', packed_at = NOW() WHERE id IN ($idPlaceholders)");
            $stmt->execute($order_ids);
            $msg = count($order_ids) . " order(s) marked as PACKED (Ready to Ship)";
            break;

        case 'mark_shipped':
            $courier = trim($data['courier_company'] ?? 'Koombiyo Delivery');
            $trackingPrefix = trim($data['tracking_prefix'] ?? 'KMB-');
            
            // If default courier/tracking provided
            $stmt = $pdo->prepare("SELECT id, order_code, tracking_number, courier_company FROM orders WHERE id IN ($idPlaceholders)");
            $stmt->execute($order_ids);
            $ordersToShip = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($ordersToShip as $ord) {
                $track = !empty($ord['tracking_number']) ? $ord['tracking_number'] : ($trackingPrefix . rand(100000, 999999));
                $cour = !empty($ord['courier_company']) ? $ord['courier_company'] : $courier;
                
                $upd = $pdo->prepare("UPDATE orders SET status = 'SHIPPED', shipped_at = NOW(), courier_company = ?, tracking_number = ? WHERE id = ?");
                $upd->execute([$cour, $track, $ord['id']]);
            }
            $msg = count($order_ids) . " order(s) marked as SHIPPED with courier tracking";
            break;

        case 'mark_delivered':
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'DELIVERED', 
                    delivered_at = NOW(), 
                    return_window_ends = DATE_ADD(CURDATE(), INTERVAL 14 DAY) 
                WHERE id IN ($idPlaceholders)
            ");
            $stmt->execute($order_ids);
            $msg = count($order_ids) . " order(s) marked as DELIVERED (14-day Return Window started)";
            break;

        case 'mark_return_window':
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'RETURN_WINDOW', 
                    delivered_at = COALESCE(delivered_at, NOW()),
                    return_window_ends = COALESCE(return_window_ends, DATE_ADD(CURDATE(), INTERVAL 14 DAY)) 
                WHERE id IN ($idPlaceholders)
            ");
            $stmt->execute($order_ids);
            $msg = count($order_ids) . " order(s) moved to RETURN_WINDOW (Active customer inspection)";
            break;

        case 'mark_completed':
            $stmt = $pdo->prepare("UPDATE orders SET status = 'COMPLETED', completed_at = NOW() WHERE id IN ($idPlaceholders)");
            $stmt->execute($order_ids);
            $msg = count($order_ids) . " order(s) marked as COMPLETED (Escrow Payouts Released)";
            break;

        case 'mark_cod_collected':
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET cod_collected = 1, 
                    cod_collected_at = NOW(), 
                    payment_status = 'paid' 
                WHERE id IN ($idPlaceholders) AND UPPER(payment_method) = 'COD'
            ");
            $stmt->execute($order_ids);
            $msg = "COD Doorstep collection confirmed for selected order(s)";
            break;

        case 'cancel':
            $stmt = $pdo->prepare("UPDATE orders SET status = 'CANCELLED' WHERE id IN ($idPlaceholders)");
            $stmt->execute($order_ids);
            $msg = count($order_ids) . " order(s) marked as CANCELLED";
            break;

        case 'add_note':
            $note = trim($data['note'] ?? '');
            if (empty($note)) {
                echo json_encode(['success' => false, 'message' => 'Note text cannot be empty']);
                exit();
            }
            $targetId = (int)$order_ids[0];
            $formattedNote = "[" . date('M d, Y h:i A') . " by Admin]: " . $note;
            
            $stmt = $pdo->prepare("UPDATE orders SET internal_notes = CONCAT(COALESCE(CONCAT(internal_notes, '\n'), ''), ?) WHERE id = ?");
            $stmt->execute([$formattedNote, $targetId]);
            $msg = "Internal note saved successfully";
            break;

        default:
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => "Unsupported bulk action '$action'"]);
            exit();
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'action' => $action,
        'affected_count' => count($order_ids)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
