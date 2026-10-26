<?php
// Multi-step Checkout Page with PayHere Gateway Integration
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/config/payhere.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$db = getDBConnection();

// Redirect to login if not logged in (or create guest cart)
$userId = getCurrentUserId();

// Get cart items
$cartItems = getCartItems();
$subtotal = getCartTotal();

if (empty($cartItems) && !isset($_POST['step'])) {
    header("Location: cart.php");
    exit;
}

$shipping = $subtotal > 5000 ? 0 : 400;
$total = $subtotal + $shipping;

// Handle checkout submission
$errors = [];
$success = false;
$payhereCheckout = false;
$createdOrderId = 0;
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
            $saveShipping = !isset($_POST['save_shipping_info']) || !empty($_POST['save_shipping_info']);

            // Save shipping info to session
            $_SESSION['checkout'] = [
                'shipping_name' => $shippingName,
                'shipping_address' => $shippingAddress,
                'shipping_city' => $shippingCity,
                'shipping_postal' => $shippingPostal,
                'shipping_phone' => $shippingPhone,
                'save_shipping_info' => $saveShipping ? 1 : 0
            ];

            // If user is logged in and opted to save (or default), save to user profile
            if ($userId && $saveShipping) {
                $uStmt = $db->prepare("
                    UPDATE users 
                    SET address = ?, city = ?, postal_code = ?, phone = ? 
                    WHERE id = ?
                ");
                $uStmt->execute([$shippingAddress, $shippingCity, $shippingPostal, $shippingPhone, $userId]);
            }

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
            $createdOrderId = $orderId;

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

            $db->commit();

            if (in_array($paymentMethod, ['payhere', 'credit_card', 'debit_card'])) {
                // Online payment via PayHere
                $payhereCheckout = true;
            } else {
                // Cash on delivery / Bank transfer: Clear cart immediately
                if ($userId) {
                    $db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
                } else {
                    $db->prepare("DELETE FROM cart_items WHERE session_id = ?")->execute([getCurrentSessionId()]);
                }
                unset($_SESSION['checkout']);
                $success = true;
            }

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to place order: ' . $e->getMessage();
        }
    }
}

// Get checkout data from session or pre-fill from user profile / past orders
$checkout = $_SESSION['checkout'] ?? [];

if ($userId) {
    // 1. Fetch user's saved shipping details from users profile
    $uStmt = $db->prepare("SELECT name, phone, address, city, postal_code FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $savedUser = $uStmt->fetch();

    if ($savedUser) {
        if (empty($checkout['shipping_name']) && !empty($savedUser['name'])) {
            $checkout['shipping_name'] = $savedUser['name'];
        }
        if (empty($checkout['shipping_phone']) && !empty($savedUser['phone'])) {
            $checkout['shipping_phone'] = $savedUser['phone'];
        }
        if (empty($checkout['shipping_address']) && !empty($savedUser['address'])) {
            $checkout['shipping_address'] = $savedUser['address'];
        }
        if (empty($checkout['shipping_city']) && !empty($savedUser['city'])) {
            $checkout['shipping_city'] = $savedUser['city'];
        }
        if (empty($checkout['shipping_postal']) && !empty($savedUser['postal_code'])) {
            $checkout['shipping_postal'] = $savedUser['postal_code'];
        }
    }

    // 2. If address is still empty, look up user's most recent order
    if (empty($checkout['shipping_address'])) {
        $lastOrderStmt = $db->prepare("
            SELECT shipping_name, shipping_address, shipping_city, shipping_postal, shipping_phone 
            FROM orders 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $lastOrderStmt->execute([$userId]);
        $lastOrder = $lastOrderStmt->fetch();
        if ($lastOrder) {
            if (empty($checkout['shipping_name'])) $checkout['shipping_name'] = $lastOrder['shipping_name'];
            if (empty($checkout['shipping_phone'])) $checkout['shipping_phone'] = $lastOrder['shipping_phone'];
            if (empty($checkout['shipping_address'])) $checkout['shipping_address'] = $lastOrder['shipping_address'];
            if (empty($checkout['shipping_city'])) $checkout['shipping_city'] = $lastOrder['shipping_city'];
            if (empty($checkout['shipping_postal'])) $checkout['shipping_postal'] = $lastOrder['shipping_postal'];
        }
    }
}

$page_title = "Checkout - BUYOURS";
include __DIR__ . '/../../backend/includes/header.php';
?>

<section class="section">
    <h1 class="section-title">Checkout</h1>

    <?php if (!$payhereCheckout && !$success): ?>
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
    <?php endif; ?>

    <?php if ($payhereCheckout): ?>
        <!-- PayHere Payment Gateway Redirection -->
        <div style="max-width: 600px; margin: 40px auto; text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
            <div style="font-size: 60px; color: var(--primary); margin-bottom: 20px;">
                <i class="fas fa-credit-card"></i>
            </div>
            <h2>Order #<?php echo $createdOrderId; ?> Created!</h2>
            <p style="color: var(--gray); font-size: 16px; margin: 15px 0;">
                Connecting to <strong>PayHere Payment Gateway</strong> for secure checkout...
            </p>

            <?php
            // Build full absolute URLs for local server redirect
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $absoluteBaseUrl = $protocol . $host . rtrim(BASE_URL, '/') . '/';
            $payhereReturnUrl = $absoluteBaseUrl . 'frontend/pages/payhere_return.php?order_id=' . $createdOrderId;
            $payhereCancelUrl = $absoluteBaseUrl . 'frontend/pages/payhere_cancel.php?order_id=' . $createdOrderId;
            $payhereNotifyUrl = $absoluteBaseUrl . 'backend/api/payhere_notify.php';
            ?>
            <form id="payhere-checkout-form" action="<?php echo getPayHereUrl(); ?>" method="POST">
                <input type="hidden" name="merchant_id" value="<?php echo PAYHERE_MERCHANT_ID; ?>">
                <input type="hidden" name="return_url" value="<?php echo escape($payhereReturnUrl); ?>">
                <input type="hidden" name="cancel_url" value="<?php echo escape($payhereCancelUrl); ?>">
                <input type="hidden" name="notify_url" value="<?php echo escape($payhereNotifyUrl); ?>">
                <input type="hidden" name="order_id" value="<?php echo $createdOrderId; ?>">
                <input type="hidden" name="items" value="<?php echo escape('BUYOURS Footwear Order #' . $createdOrderId); ?>">
                <input type="hidden" name="currency" value="<?php echo PAYHERE_CURRENCY; ?>">
                <input type="hidden" name="amount" value="<?php echo number_format($total, 2, '.', ''); ?>">
                <input type="hidden" name="first_name" value="<?php echo escape($checkout['shipping_name'] ?? ''); ?>">
                <input type="hidden" name="last_name" value="">
                <input type="hidden" name="email" value="<?php echo escape($_SESSION['user_email'] ?? 'customer@buyours.lk'); ?>">
                <input type="hidden" name="phone" value="<?php echo escape($checkout['shipping_phone'] ?? ''); ?>">
                <input type="hidden" name="address" value="<?php echo escape($checkout['shipping_address'] ?? ''); ?>">
                <input type="hidden" name="city" value="<?php echo escape($checkout['shipping_city'] ?? ''); ?>">
                <input type="hidden" name="country" value="Sri Lanka">
                <input type="hidden" name="hash" value="<?php echo generatePayHereHash($createdOrderId, $total); ?>">

                <button type="submit" class="btn btn-primary btn-lg" style="margin-top: 20px; padding: 14px 30px; font-size: 18px; width: 100%;">
                    <i class="fas fa-lock"></i> Pay Now with PayHere (<?php echo formatPrice($total); ?>)
                </button>
            </form>

            <script>
                // Auto-submit to PayHere gateway with absolute URL verification
                setTimeout(function() {
                    var form = document.getElementById('payhere-checkout-form');
                    if (form) {
                        ['return_url', 'cancel_url', 'notify_url'].forEach(function(fieldName) {
                            var input = form.querySelector('input[name="' + fieldName + '"]');
                            if (input && input.value && !input.value.startsWith('http://') && !input.value.startsWith('https://')) {
                                input.value = window.location.origin + (input.value.startsWith('/') ? '' : '/') + input.value;
                            }
                        });
                        form.submit();
                    }
                }, 1200);
            </script>
        </div>

    <?php elseif ($success): ?>
        <!-- Order Confirmation (COD / Bank Transfer) -->
        <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto;">
            <div style="font-size: 80px; color: #28a745; margin-bottom: 20px;">✅</div>
            <h2>Thank You for Your Order!</h2>
            <p style="color: var(--gray); margin: 15px 0;">Your order #<?php echo $createdOrderId; ?> has been placed successfully.</p>
            <p style="color: var(--primary); font-size: 24px; font-weight: bold; margin: 15px 0;">
                Total: <?php echo formatPrice($total); ?>
            </p>
            <p style="color: var(--gray);">Payment Method: <?php echo ucwords(str_replace('_', ' ', $checkout['payment_method'] ?? '')); ?></p>
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>frontend/pages/products.php" class="btn btn-primary">Continue Shopping</a>
                <a href="<?php echo BASE_URL; ?>frontend/pages/index.php" class="btn btn-outline">Back to Home</a>
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

                <?php if ($userId): ?>
                <div class="form-group" style="margin-top: 15px; margin-bottom: 25px; padding: 12px 15px; background: var(--gray-light); border-radius: 8px; border: 1px solid var(--gray-border);">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; font-size: 14px; font-weight: 500; color: var(--dark);">
                        <input type="checkbox" name="save_shipping_info" value="1" 
                               <?php echo (!isset($checkout['save_shipping_info']) || !empty($checkout['save_shipping_info'])) ? 'checked' : ''; ?>
                               style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                        <span><i class="fas fa-bookmark" style="color: var(--primary); margin-right: 4px;"></i> Save this shipping information to my account for future orders</span>
                    </label>
                </div>
                <?php endif; ?>

                <div class="checkout-actions">
                    <a href="<?php echo BASE_URL; ?>frontend/pages/cart.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Cart</a>
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
                    <select id="payment_method" name="payment_method" required style="font-size: 16px; padding: 12px;">
                        <option value="payhere" <?php echo ($checkout['payment_method'] ?? 'payhere') === 'payhere' ? 'selected' : ''; ?>>
                            💳 PayHere Online Payment (Visa, MasterCard, eZ Cash, mCash)
                        </option>
                        <option value="cod" <?php echo ($checkout['payment_method'] ?? '') === 'cod' ? 'selected' : ''; ?>>
                            💵 Cash on Delivery (COD)
                        </option>
                        <option value="bank_transfer" <?php echo ($checkout['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>
                            🏦 Bank Transfer
                        </option>
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
                <p><?php echo escape($checkout['shipping_name'] ?? ''); ?></p>
                <p><?php echo escape($checkout['shipping_address'] ?? ''); ?></p>
                <p><?php echo escape($checkout['shipping_city'] ?? ''); ?> <?php echo escape($checkout['shipping_postal'] ?? ''); ?></p>
                <p><?php echo escape($checkout['shipping_phone'] ?? ''); ?></p>
            </div>

            <!-- Payment Method -->
            <div style="background: var(--gray-light); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;">Payment Method</h3>
                <p>
                    <?php 
                    $pm = $checkout['payment_method'] ?? 'payhere';
                    if ($pm === 'payhere') echo '💳 PayHere Online Payment Gateway';
                    elseif ($pm === 'cod') echo '💵 Cash on Delivery';
                    elseif ($pm === 'bank_transfer') echo '🏦 Bank Transfer';
                    else echo escape(ucwords(str_replace('_', ' ', $pm)));
                    ?>
                </p>
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
                        <i class="fas fa-check"></i> Place Order & Pay
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </div>

    <?php endif; ?>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>