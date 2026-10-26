<?php
// Multi-step Checkout Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();

// Redirect to login if not logged in (or create guest cart)
$userId = getCurrentUserId();

// Get cart items
$cartItems = getCartItems();
$subtotal = getCartTotal();

if (empty($cartItems)) {
    header("Location: cart.php");
    exit;
}

$shipping = $subtotal > 5000 ? 0 : 400;
$tax = ($subtotal + $shipping) * 0.08;
$total = $subtotal + $shipping + $tax;

// Handle checkout submission
$errors = [];
$success = false;
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentStep = (int)($_POST['step'] ?? 1);

    if ($currentStep === 1) {
        // Shipping info validation
        $shippingName = sanitizeInput($_POST['shipping_name'] ?? '');
        $shippingAddress = sanitizeInput($_POST['shipping_address'] ?? '');
        $shippingCity = sanitizeInput($_POST['shipping_city'] ?? '');
        $shippingPostal = sanitizeInput($_POST['shipping_postal'] ?? '');
        $shippingPhone = sanitizeInput($_POST['shipping_phone'] ?? '');

        if (empty($shippingName)) $errors[] = 'Shipping name is required';
        if (empty($shippingAddress)) $errors[] = 'Shipping address is required';
        if (empty($shippingCity)) $errors[] = 'City is required';
        if (empty($shippingPostal)) $errors[] = 'Postal code is required';
        if (empty($shippingPhone)) $errors[] = 'Phone is required';

        if (empty($errors)) {
            // Save shipping info to session
            $_SESSION['checkout'] = [
                'shipping_name' => $shippingName,
                'shipping_address' => $shippingAddress,
                'shipping_city' => $shippingCity,
                'shipping_postal' => $shippingPostal,
                'shipping_phone' => $shippingPhone,
            ];
            header("Location: checkout.php?step=2");
            exit;
        }
    } elseif ($currentStep === 2) {
        // Payment info
        $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');

        if (empty($paymentMethod)) {
            $errors[] = 'Payment method is required';
        }

        if (empty($errors)) {
            $_SESSION['checkout']['payment_method'] = $paymentMethod;
            header("Location: checkout.php?step=3");
            exit;
        }
    } elseif ($currentStep === 3) {
        // Review and place order
        if (empty($_SESSION['checkout'])) {
            header("Location: cart.php");
            exit;
        }

        // Create order
        try {
            $db->beginTransaction();

            $checkout = $_SESSION['checkout'];
            $paymentMethod = $checkout['payment_method'];
            $paymentStatus = 'pending';

            // Insert order
            $sql = "INSERT INTO orders (user_id, subtotal, shipping_cost, total, shipping_name, shipping_address, shipping_city, shipping_postal, shipping_phone, payment_method, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $userId ?: null,
                $subtotal,
                $shipping,
                $total,
                $checkout['shipping_name'],
                $checkout['shipping_address'],
                $checkout['shipping_city'],
                $checkout['shipping_postal'],
                $checkout['shipping_phone'],
                $paymentMethod,
                $paymentStatus
            ]);
            $orderId = $db->lastInsertId();

            // Insert order items
            foreach ($cartItems as $item) {
                $sql = "INSERT INTO order_items (order_id, product_id, product_name, price, quantity, size, color)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $item['size'],
                    $item['color']
                ]);

                // Reduce stock
                $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?")->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $item['quantity']
                ]);
            }

            // Clear cart
            if ($userId) {
                $db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
            } else {
                $db->prepare("DELETE FROM cart_items WHERE session_id = ?")->execute([getCurrentSessionId()]);
            }

            $db->commit();
            unset($_SESSION['checkout']);
            $success = true;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to place order: ' . $e->getMessage();
        }
    }
}

// Get checkout data from session
$checkout = $_SESSION['checkout'] ?? [];

$page_title = "Checkout - Girls' Footwear";
include __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1 class="section-title">Checkout</h1>

    <!-- Steps Indicator -->
    <div class="checkout-steps">
        <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">
            <span class="step-number"><?php echo $step > 1 ? '✓' : '1'; ?></span>
            <span>Shipping</span>
        </div>
        <div class="step-divider"></div>
        <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
            <span class="step-number"><?php echo $step > 2 ? '✓' : '2'; ?></span>
            <span>Payment</span>
        </div>
        <div class="step-divider"></div>
        <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
            <span class="step-number">3</span>
            <span>Review</span>
        </div>
    </div>

    <?php if ($success): ?>
        <!-- Order Confirmation -->
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 80px; margin-bottom: 20px;">✅</div>
            <h2>Thank You for Your Order!</h2>
            <p style="color: var(--gray); margin: 15px 0;">Your order has been placed successfully.</p>
            <p style="color: var(--primary); font-size: 24px; font-weight: bold; margin: 15px 0;">
                Total: <?php echo formatPrice($total); ?>
            </p>
            <p style="color: var(--gray);">You'll receive a confirmation email shortly.</p>
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                <a href="pages/products.php" class="btn btn-primary">Continue Shopping</a>
                <a href="pages/index.php" class="btn btn-outline">Back to Home</a>
            </div>
        </div>
    <?php else: ?>

    <!-- Checkout Form -->
    <div class="checkout-form">
        <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo escape($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Shipping -->
        <?php if ($step === 1): ?>
            <h2 style="margin-bottom: 25px;">Shipping Information</h2>
            <form method="POST">
                <input type="hidden" name="step" value="1">

                <div class="form-group">
                    <label for="shipping_name">Full Name *</label>
                    <input type="text" id="shipping_name" name="shipping_name"
                           value="<?php echo escape($checkout['shipping_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="shipping_address">Address *</label>
                    <textarea id="shipping_address" name="shipping_address" rows="3" required><?php echo escape($checkout['shipping_address'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="shipping_city">City *</label>
                        <input type="text" id="shipping_city" name="shipping_city"
                               value="<?php echo escape($checkout['shipping_city'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="shipping_postal">Postal Code *</label>
                        <input type="text" id="shipping_postal" name="shipping_postal"
                               value="<?php echo escape($checkout['shipping_postal'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="shipping_phone">Phone *</label>
                    <input type="tel" id="shipping_phone" name="shipping_phone"
                           value="<?php echo escape($checkout['shipping_phone'] ?? ''); ?>" required>
                </div>

                <div class="checkout-actions">
                    <a href="pages/cart.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Cart</a>
                    <button type="submit" class="btn btn-primary">Continue to Payment</button>
                </div>
            </form>

        <!-- Step 2: Payment -->
        <?php elseif ($step === 2): ?>
            <h2 style="margin-bottom: 25px;">Payment Method</h2>
            <form method="POST">
                <input type="hidden" name="step" value="2">

                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select payment method</option>
                        <option value="credit_card" <?php echo ($checkout['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="debit_card" <?php echo ($checkout['payment_method'] ?? '') === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                        <option value="paypal" <?php echo ($checkout['payment_method'] ?? '') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                        <option value="bank_transfer" <?php echo ($checkout['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>

                <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 10px;">Order Summary</h3>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo $shipping === 0 ? 'FREE' : formatPrice($shipping); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax</span>
                        <span><?php echo formatPrice($tax); ?></span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>

                <div class="checkout-actions">
                    <button type="button" class="btn btn-outline" onclick="location.href='checkout.php?step=1'">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn btn-primary">Continue to Review</button>
                </div>
            </form>

        <!-- Step 3: Review -->
        <?php elseif ($step === 3): ?>
            <h2 style="margin-bottom: 25px;">Review Your Order</h2>

            <!-- Shipping Info -->
            <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;">Shipping To</h3>
                <p><?php echo escape($checkout['shipping_name']); ?></p>
                <p><?php echo escape($checkout['shipping_address']); ?></p>
                <p><?php echo escape($checkout['shipping_city']); ?> <?php echo escape($checkout['shipping_postal']); ?></p>
                <p><?php echo escape($checkout['shipping_phone']); ?></p>
            </div>

            <!-- Payment Method -->
            <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;">Payment Method</h3>
                <p><?php echo ucwords(str_replace('_', ' ', $checkout['payment_method'] ?? '')); ?></p>
            </div>

            <!-- Order Items -->
            <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px;">Order Items</h3>
                <?php foreach ($cartItems as $item): ?>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--gray-border);">
                        <div>
                            <strong><?php echo escape($item['name']); ?></strong>
                            <div style="font-size: 14px; color: var(--gray);">
                                Size: <?php echo escape($item['size']); ?> · Qty: <?php echo $item['quantity']; ?>
                            </div>
                        </div>
                        <div><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Total -->
            <div style="background: var(--primary-light); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo formatPrice($subtotal); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $shipping === 0 ? 'FREE' : formatPrice($shipping); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span><?php echo formatPrice($tax); ?></span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span><?php echo formatPrice($total); ?></span>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="3">
                <div class="checkout-actions">
                    <button type="button" class="btn btn-outline" onclick="location.href='checkout.php?step=2'">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="submit" class="btn btn-primary" style="background: var(--success);">
                        <i class="fas fa-check"></i> Place Order
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </div>

    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>