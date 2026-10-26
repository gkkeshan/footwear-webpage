<?php
// PayHere IPN (Instant Payment Notification) Callback Handler
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payhere.php';
require_once __DIR__ . '/../includes/functions.php';

// Log notification request for auditing
error_log("PayHere IPN Received: " . json_encode($_POST));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$merchantId = $_POST['merchant_id'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);
$payhereAmount = $_POST['payhere_amount'] ?? '';
$payhereCurrency = $_POST['payhere_currency'] ?? '';
$statusCode = (int)($_POST['status_code'] ?? -1);
$paymentId = $_POST['payment_id'] ?? '';

// Verify notification signature hash
if (!verifyPayHereNotifyHash($_POST)) {
    error_log("PayHere IPN Error: Invalid hash signature for Order #$orderId");
    http_response_code(400);
    exit('Invalid Hash Signature');
}

$db = getDBConnection();

// Check if order exists
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    error_log("PayHere IPN Error: Order #$orderId not found");
    http_response_code(404);
    exit('Order Not Found');
}

if ($statusCode === 2) {
    // Payment Successful (status_code 2)
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
    $updateStmt->execute([$orderId]);
    error_log("PayHere IPN Success: Order #$orderId status updated to Paid / Processing (Payment ID: $paymentId)");
} elseif ($statusCode === 0) {
    // Payment Pending
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'pending' WHERE id = ?");
    $updateStmt->execute([$orderId]);
} else {
    // Payment Failed or Canceled (-1, -2, -3)
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'failed', status = 'cancelled' WHERE id = ?");
    $updateStmt->execute([$orderId]);
    error_log("PayHere IPN Notice: Order #$orderId payment status set to Failed (Status Code: $statusCode)");
}

http_response_code(200);
echo "OK";
