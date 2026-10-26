<?php
// User Account Profile & Order History Page
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

startSecureSession();

if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "frontend/pages/login.php");
    exit;
}

$db = getDBConnection();
$userId = getCurrentUserId();

// Handle profile & shipping address updates
$profileSuccess = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $postal_code = sanitizeInput($_POST['postal_code'] ?? '');

    if (empty($name)) {
        $profileError = 'Name cannot be empty.';
    } else {
        $updateStmt = $db->prepare("
            UPDATE users 
            SET name = ?, phone = ?, address = ?, city = ?, postal_code = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$name, $phone, $address, $city, $postal_code, $userId]);
        $_SESSION['user_name'] = $name;
        $profileSuccess = 'Your profile and shipping details have been updated successfully!';
    }
}

// Fetch user profile details including address info
$userStmt = $db->prepare("SELECT id, name, email, phone, address, city, postal_code, role, created_at FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    // If session holds invalid user ID
    session_destroy();
    header("Location: " . BASE_URL . "frontend/pages/login.php");
    exit;
}

// Fetch user orders with item counts
$orderStmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) as item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC
");
$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll();

$page_title = "My Account - BUYOURS";
include __DIR__ . '/../../backend/includes/header.php';
?>

<section class="section">
    <div style="max-width: 1000px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid var(--gray-light); padding-bottom: 15px;">
            <div>
                <h1 style="margin: 0; font-size: 28px;">My Account</h1>
                <p style="color: var(--gray); margin-top: 5px;">Manage your profile and track your orders</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>frontend/pages/admin/index.php" class="btn btn-outline">
                        <i class="fas fa-cog"></i> Admin Dashboard
                    </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>frontend/pages/logout.php" class="btn btn-outline" style="color: var(--danger); border-color: var(--danger);">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
            <!-- Profile Card -->
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid var(--gray-border);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 70px; height: 70px; background: var(--primary-light); color: var(--primary); font-size: 32px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <h2 style="font-size: 20px; margin: 0;"><?php echo escape($user['name']); ?></h2>
                    <span style="display: inline-block; background: var(--primary-light); color: var(--primary); font-size: 12px; font-weight: bold; padding: 4px 10px; border-radius: 12px; margin-top: 6px; text-transform: capitalize;">
                        <?php echo escape($user['role']); ?> Account
                    </span>
                </div>

                <div style="border-top: 1px solid var(--gray-border); padding-top: 15px;">
                    <p style="margin-bottom: 10px;">
                        <i class="fas fa-envelope" style="color: var(--primary); width: 20px;"></i>
                        <strong>Email:</strong><br>
                        <span style="color: var(--gray);"><?php echo escape($user['email']); ?></span>
                    </p>
                    <?php if (!empty($user['phone'])): ?>
                        <p style="margin-bottom: 10px;">
                            <i class="fas fa-phone" style="color: var(--primary); width: 20px;"></i>
                            <strong>Phone:</strong><br>
                            <span style="color: var(--gray);"><?php echo escape($user['phone']); ?></span>
                        </p>
                    <?php endif; ?>
                    <p style="margin-bottom: 15px;">
                        <i class="fas fa-calendar-alt" style="color: var(--primary); width: 20px;"></i>
                        <strong>Member Since:</strong><br>
                        <span style="color: var(--gray);"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                    </p>
                </div>

                <!-- Saved Shipping Address -->
                <div style="border-top: 1px solid var(--gray-border); padding-top: 15px;">
                    <h3 style="font-size: 15px; margin: 0 0 10px 0; color: var(--dark); display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fas fa-map-marker-alt" style="color: var(--primary); margin-right: 6px;"></i> Saved Shipping Address</span>
                    </h3>
                    
                    <?php if (!empty($user['address']) || !empty($user['city'])): ?>
                        <div style="background: var(--gray-light); padding: 12px; border-radius: 8px; font-size: 13px; line-height: 1.5; color: var(--dark); border: 1px solid var(--gray-border); margin-bottom: 12px;">
                            <strong><?php echo escape($user['name']); ?></strong><br>
                            <?php if (!empty($user['address'])): ?>
                                <?php echo nl2br(escape($user['address'])); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($user['city'])): ?>
                                <?php echo escape($user['city']); ?>
                                <?php echo !empty($user['postal_code']) ? ' - ' . escape($user['postal_code']) : ''; ?><br>
                            <?php endif; ?>
                            <?php if (!empty($user['phone'])): ?>
                                <i class="fas fa-phone-alt" style="font-size: 11px; color: var(--gray);"></i> <?php echo escape($user['phone']); ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size: 13px; color: var(--gray); margin-bottom: 12px;">No shipping address saved yet.</p>
                    <?php endif; ?>

                    <button type="button" onclick="toggleEditProfile()" class="btn btn-outline btn-block" style="font-size: 13px; padding: 8px;">
                        <i class="fas fa-edit"></i> Edit Shipping Details
                    </button>
                </div>
            </div>

            <!-- Order History & Profile Form -->
            <div>
                <?php if (!empty($profileSuccess)): ?>
                    <div class="flash-message flash-success" style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo escape($profileSuccess); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profileError)): ?>
                    <div class="flash-message flash-error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo escape($profileError); ?>
                    </div>
                <?php endif; ?>

                <!-- Edit Profile Form (collapsible) -->
                <div id="edit-profile-card" style="display: none; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid var(--gray-border); margin-bottom: 25px;">
                    <h3 style="margin-top: 0; font-size: 18px; margin-bottom: 20px; color: var(--primary);"><i class="fas fa-user-edit"></i> Edit Profile & Saved Shipping Information</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo escape($user['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo escape($user['phone'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Shipping Address *</label>
                            <textarea id="address" name="address" rows="3" placeholder="House No, Street, Landmark..." required><?php echo escape($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" id="city" name="city" value="<?php echo escape($user['city'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Postal Code *</label>
                                <input type="text" id="postal_code" name="postal_code" value="<?php echo escape($user['postal_code'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <button type="button" onclick="toggleEditProfile()" class="btn btn-outline">Cancel</button>
                        </div>
                    </form>
                </div>

                <script>
                function toggleEditProfile() {
                    var el = document.getElementById('edit-profile-card');
                    if (el.style.display === 'none') {
                        el.style.display = 'block';
                        el.scrollIntoView({ behavior: 'smooth' });
                    } else {
                        el.style.display = 'none';
                    }
                }
                </script>

                <h2 style="font-size: 22px; margin-bottom: 20px;">Order History</h2>

                <?php if (empty($orders)): ?>
                    <div class="empty-state" style="background: white; padding: 40px; border-radius: 12px; text-align: center; border: 1px solid var(--gray-border);">
                        <div class="empty-state-icon" style="font-size: 50px; margin-bottom: 15px;">🛍️</div>
                        <h3>No orders yet</h3>
                        <p style="color: var(--gray);">You haven't placed any orders with BUYOURS yet.</p>
                        <a href="<?php echo BASE_URL; ?>frontend/pages/products.php" class="btn btn-primary" style="margin-top: 20px;">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($orders as $order): ?>
                            <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); border: 1px solid var(--gray-border);">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; border-bottom: 1px solid var(--gray-light); padding-bottom: 10px;">
                                    <div>
                                        <strong style="font-size: 16px;">Order #<?php echo $order['id']; ?></strong>
                                        <span style="color: var(--gray); font-size: 13px; margin-left: 10px;">
                                            <?php echo date('M j, Y - g:i A', strtotime($order['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <?php 
                                        $statusBg = match($order['status']) {
                                            'delivered' => '#d4edda; color: #155724',
                                            'shipped' => '#cce5ff; color: #004085',
                                            'processing' => '#fff3cd; color: #856404',
                                            'cancelled' => '#f8d7da; color: #721c24',
                                            default => '#e2e3e5; color: #383d41'
                                        };
                                        ?>
                                        <span style="font-size: 12px; font-weight: bold; padding: 4px 10px; border-radius: 12px; background: <?php echo $statusBg; ?>; text-transform: capitalize;">
                                            <?php echo escape($order['status']); ?>
                                        </span>
                                        <?php if ($order['payment_status'] === 'paid'): ?>
                                            <span style="font-size: 12px; font-weight: bold; padding: 4px 10px; border-radius: 12px; background: #d4edda; color: #155724;">
                                                Paid
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <p style="margin: 0; font-size: 14px; color: var(--dark);">
                                            <strong>Deliver to:</strong> <?php echo escape($order['shipping_name']); ?><br>
                                            <span style="color: var(--gray); font-size: 13px;">
                                                <i class="fas fa-map-marker-alt" style="color: var(--primary);"></i> <?php echo escape($order['shipping_address']); ?>, <?php echo escape($order['shipping_city']); ?> <?php echo escape($order['shipping_postal']); ?>
                                                <?php if (!empty($order['shipping_phone'])): ?>
                                                    | <i class="fas fa-phone"></i> <?php echo escape($order['shipping_phone']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </p>
                                        <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--gray);">
                                            Payment: <?php echo escape(ucwords(str_replace('_', ' ', $order['payment_method'] ?? ''))); ?>
                                        </p>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 18px; font-weight: bold; color: var(--primary);">
                                            <?php echo formatPrice($order['total']); ?>
                                        </div>
                                        <span style="font-size: 12px; color: var(--gray);"><?php echo $order['item_count']; ?> item(s)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>
