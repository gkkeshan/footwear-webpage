<?php
// Shopping Cart Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $cartItemId = (int)$_POST['cart_item_id'];
        $quantity = (int)$_POST['quantity'];
        if ($quantity < 1) $quantity = 1;
        if ($quantity > 10) $quantity = 10;

        $session_id = getCurrentSessionId();
        $user_id = getCurrentUserId();

        if ($user_id) {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cartItemId, $user_id]);
        } else {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND session_id = ?");
            $stmt->execute([$quantity, $cartItemId, $session_id]);
        }

        header("Location: cart.php");
        exit;
    }

    if ($action === 'remove') {
        $cartItemId = (int)$_POST['cart_item_id'];
        $session_id = getCurrentSessionId();
        $user_id = getCurrentUserId();

        if ($user_id) {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartItemId, $user_id]);
        } else {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND session_id = ?");
            $stmt->execute([$cartItemId, $session_id]);
        }

        header("Location: cart.php");
        exit;
    }
}

// Get cart items
$cartItems = getCartItems();
$subtotal = getCartTotal();
$shipping = $subtotal > 5000 ? 0 : 400;
$tax = ($subtotal + $shipping) * 0.08;
$total = $subtotal + $shipping + $tax;

$page_title = "Your Shopping Cart - Girls' Footwear";
include __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1 class="section-title">Your Shopping Cart</h1>
    <p class="section-subtitle">Review your items before checkout</p>

    <div class="cart-container">
        <!-- Cart Items -->
        <div class="cart-items">
            <?php if (empty($cartItems)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items yet.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">Shop Now</a>
                </div>
            <?php else: ?>
                <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item" data-cart-item="<?php echo $item['id']; ?>">
                        <div class="cart-item-image">
                            <i class="fas fa-shoe-prints" style="font-size: 40px;"></i>
                        </div>
                        <div class="cart-item-info">
                            <h3><?php echo escape($item['name']); ?></h3>
                            <div class="cart-item-meta">
                                Size: <?php echo escape($item['size']); ?> · Color: <?php echo escape($item['color']); ?>
                            </div>
                            <div class="cart-item-price">
                                <?php echo formatPrice($item['price']); ?> each
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn cart-qty-btn"
                                        data-cart-item-id="<?php echo $item['id']; ?>"
                                        data-action="decrease">-</button>
                                    <input type="text" class="qty-input" value="<?php echo $item['quantity']; ?>" readonly>
                                    <button type="button" class="qty-btn cart-qty-btn"
                                        data-cart-item-id="<?php echo $item['id']; ?>"
                                        data-action="increase">+</button>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="action" value="remove"
                                        class="remove-item-btn" data-cart-item-id="<?php echo $item['id']; ?>"
                                        style="background: none; border: none; color: var(--danger); cursor: pointer; font-size: 14px;">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="cart-item-total" style="text-align: right; font-weight: bold; color: var(--primary); font-size: 18px;">
                            <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                        </div>
                        <form method="POST" style="display: none;" id="remove-form-<?php echo $item['id']; ?>">
                            <input type="hidden" name="cart_item_id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="action" value="remove">
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Cart Summary -->
        <div class="cart-summary">
            <h3 style="margin-bottom: 20px;">Order Summary</h3>

            <div class="summary-row">
                <span>Subtotal</span>
                <span class="cart-subtotal"><?php echo formatPrice($subtotal); ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span class="cart-shipping" style="color: <?php echo $shipping === 0 ? 'var(--success)' : 'var(--dark)'; ?>">
                    <?php echo $shipping === 0 ? 'FREE' : formatPrice($shipping); ?>
                </span>
            </div>
            <div class="summary-row">
                <span>Tax (8%)</span>
                <span class="cart-tax"><?php echo formatPrice($tax); ?></span>
            </div>
            <div class="summary-row summary-total">
                <span>Total</span>
                <span class="cart-total" style="color: var(--primary);"><?php echo formatPrice($total); ?></span>
            </div>

            <?php if ($shipping > 0): ?>
                <div style="background: var(--primary-light); padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center;">
                    <i class="fas fa-gift"></i> Add <?php echo formatPrice(5000 - $subtotal); ?> more for FREE shipping!
                </div>
            <?php else: ?>
                <div style="background: var(--success); color: white; padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; text-align: center;">
                    <i class="fas fa-truck"></i> You get FREE shipping!
                </div>
            <?php endif; ?>

            <a href="checkout.php" class="btn btn-primary btn-block" style="margin-bottom: 10px;">
                <i class="fas fa-lock"></i> Proceed to Checkout
            </a>
            <a href="products.php" class="btn btn-outline btn-block">
                <i class="fas fa-arrow-left"></i> Continue Shopping
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>