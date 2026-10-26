<?php
// PayHere Payment Success / Return Page
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$db = getDBConnection();
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

$order = null;
if ($orderId > 0) {
    // Clear cart session items if any remain
    $userId = getCurrentUserId();
    if ($userId) {
        $db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
    } else {
        $db->prepare("DELETE FROM cart_items WHERE session_id = ?")->execute([getCurrentSessionId()]);
    }
    
    // Fetch order details
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    // Automatically update order payment status if returned successfully
    if ($order && $order['payment_status'] === 'pending') {
        $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?")->execute([$orderId]);
        $order['payment_status'] = 'paid';
        $order['status'] = 'processing';
    }
}

$page_title = "Payment Successful - BUYOURS";
include __DIR__ . '/../../backend/includes/header.php';
?>

<section class="section">
    <div style="max-width: 600px; margin: 40px auto; text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div style="font-size: 70px; color: #28a745; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 style="color: var(--dark); margin-bottom: 10px;">Payment Successful!</h1>
        <p style="color: var(--gray); font-size: 16px; margin-bottom: 25px;">
            Thank you for your purchase. Your payment was processed securely via PayHere.
        </p>

        <?php if ($order): ?>
            <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; text-align: left; margin-bottom: 30px;">
                <p><strong>Order ID:</strong> #<?php echo escape($order['id']); ?></p>
                <p><strong>Payment Method:</strong> PayHere Gateway</p>
                <p><strong>Status:</strong> <span style="color: green; font-weight: bold;">Paid & Processing</span></p>
                <p><strong>Total Amount:</strong> <?php echo formatPrice($order['total']); ?></p>
                <p><strong>Shipping To:</strong> <?php echo escape($order['shipping_name']); ?>, <?php echo escape($order['shipping_city']); ?></p>
            </div>
        <?php elseif ($orderId > 0): ?>
            <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; text-align: left; margin-bottom: 30px;">
                <p><strong>Order ID:</strong> #<?php echo escape($orderId); ?></p>
                <p><strong>Status:</strong> <span style="color: green; font-weight: bold;">Paid & Processing</span></p>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>frontend/pages/products.php" class="btn btn-primary">Continue Shopping</a>
            <a href="<?php echo BASE_URL; ?>frontend/pages/index.php" class="btn btn-outline">Back to Home</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>
