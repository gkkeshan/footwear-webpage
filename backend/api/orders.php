<?php
// Orders API - Handles AJAX order operations
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$action = $_GET['action'] ?? '';
$response = ['success' => false];

switch ($action) {
    case 'update_status':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $order_id = (int)($input['order_id'] ?? 0);
        $status = sanitizeInput($input['status'] ?? '');

        if (!isAdmin()) {
            $response['message'] = 'Unauthorized';
            break;
        }

        $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed)) {
            $response['message'] = 'Invalid status';
            break;
        }

        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        $response['success'] = true;
        $response['message'] = 'Status updated';
        break;

    case 'get':
        $order_id = (int)($_GET['order_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if ($order) {
            $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemStmt->execute([$order_id]);
            $order['items'] = $itemStmt->fetchAll();
            $response['success'] = true;
            $response['order'] = $order;
        } else {
            $response['message'] = 'Order not found';
        }
        break;

    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);