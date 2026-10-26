<?php
// PayHere Payment Cancelled Page
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

$page_title = "Payment Cancelled - BUYOURS";
include __DIR__ . '/../../backend/includes/header.php';
?>

<section class="section">
    <div style="max-width: 600px; margin: 40px auto; text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div style="font-size: 70px; color: #dc3545; margin-bottom: 20px;">
            <i class="fas fa-times-circle"></i>
        </div>
        <h1 style="color: var(--dark); margin-bottom: 10px;">Payment Cancelled</h1>
        <p style="color: var(--gray); font-size: 16px; margin-bottom: 25px;">
            Your payment process was cancelled or interrupted. No charges were made.
        </p>

        <?php if ($orderId > 0): ?>
            <p style="color: var(--gray); margin-bottom: 25px;">Order #<?php echo escape($orderId); ?> is pending payment.</p>
        <?php endif; ?>

        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>frontend/pages/checkout.php?step=2" class="btn btn-primary"><i class="fas fa-redo"></i> Retry Payment</a>
            <a href="<?php echo BASE_URL; ?>frontend/pages/cart.php" class="btn btn-outline">Return to Cart</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>
