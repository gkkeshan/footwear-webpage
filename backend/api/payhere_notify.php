<?php
/**
 * ============================================================================
 * PAYHERE IPN (INSTANT PAYMENT NOTIFICATION) WEBHOOK HANDLER
 * ============================================================================
 * Purpose: Asynchronous server-to-server callback endpoint called by PayHere
 *          to notify our application of payment outcomes.
 * Why this is vital:
 * If the user's browser closes or their device disconnects after entering card details,
 * PayHere still calls this URL in the background, ensuring orders are never left in limbo.
 *
 * Security:
 * 1. Rejects non-POST requests (HTTP 405).
 * 2. Validates the MD5 signature hash using our private Merchant Secret (HTTP 400).
 * 3. Uses PDO prepared statements to look up and update the order safely.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payhere.php';
require_once __DIR__ . '/../includes/functions.php';

// Log notification request for audit trail and debugging
error_log("PayHere IPN Received: " . json_encode($_POST));

// 1. Enforce HTTP POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// 2. Extract notification parameters sent by PayHere
$merchantId      = $_POST['merchant_id'] ?? '';
$orderId         = (int)($_POST['order_id'] ?? 0);
$payhereAmount   = $_POST['payhere_amount'] ?? '';
$payhereCurrency = $_POST['payhere_currency'] ?? '';
$statusCode      = (int)($_POST['status_code'] ?? -1); // 2 = Success, 0 = Pending, -1/-2 = Failed/Canceled
$paymentId       = $_POST['payment_id'] ?? '';

// 3. Cryptographic Verification: Verify the signature hash
if (!verifyPayHereNotifyHash($_POST)) {
    error_log("PayHere IPN Error: Invalid hash signature for Order #$orderId");
    http_response_code(400);
    exit('Invalid Hash Signature');
}

$db = getDBConnection();

// 4. Verify that the order exists in our database
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    error_log("PayHere IPN Error: Order #$orderId not found");
    http_response_code(404);
    exit('Order Not Found');
}

// 5. Update order and payment status based on PayHere status_code
if ($statusCode === 2) {
    // Payment Successful (status_code = 2)
    // Transition order to 'paid' and status to 'processing' (ready for warehouse fulfillment)
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
    $updateStmt->execute([$orderId]);
    error_log("PayHere IPN Success: Order #$orderId status updated to Paid / Processing (Payment ID: $paymentId)");
} elseif ($statusCode === 0) {
    // Payment Pending (e.g. offline bank transfer or review)
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'pending' WHERE id = ?");
    $updateStmt->execute([$orderId]);
} else {
    // Payment Failed or Canceled (-1: Canceled, -2: Failed, -3: Chargedback)
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'failed', status = 'cancelled' WHERE id = ?");
    $updateStmt->execute([$orderId]);
    error_log("PayHere IPN Notice: Order #$orderId payment status set to Failed (Status Code: $statusCode)");
}

// 6. Return HTTP 200 OK to acknowledge receipt to PayHere's server
http_response_code(200);
echo "OK";

