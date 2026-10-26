<?php
// Admin Orders Management
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

$action = $_GET['action'] ?? 'list';
$message = '';

// Handle status updates
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token</div>';
    } else {
        $id = (int)$_POST['id'];
        $status = sanitizeInput($_POST['status']);
        $allowed = ['pending', 'paid', 'processing', 'shipped', 'completed', 'delivered', 'cancelled'];
        if (in_array($status, $allowed)) {
            if ($status === 'paid') {
                $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $dbStatus = ($status === 'completed') ? 'delivered' : $status;
                $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$dbStatus, $id]);
            }
            $message = '<div class="flash-message flash-success">Order status updated successfully</div>';
        }
    }
}

if ($action === 'view' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: orders.php");
        exit;
    }

    $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemStmt->execute([$id]);
    $orderItems = $itemStmt->fetchAll();

    $admin_title = "Order #" . $order['id'];
    include __DIR__ . '/../../../backend/includes/admin-header.php';

    echo $message;
    ?>
    <a href="orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Orders</a>

    <div class="admin-table" style="margin-top: 20px;">
        <div style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                <div>
                    <h2>Order #<?php echo $order['id']; ?></h2>
                    <p>Placed: <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <div>
                    <span class="badge badge-<?php
                        echo match($order['status']) {
                            'delivered' => 'success',
                            'cancelled' => 'danger',
                            'shipped', 'processing' => 'warning',
                            default => 'info'
                        };
                    ?>"><?php echo ucfirst($order['status']); ?></span>
                </div>
            </div>

            <h3>Customer Information</h3>
            <p><strong>Name:</strong> <?php echo escape($order['shipping_name']); ?></p>
            <p><strong>Address:</strong> <?php echo escape($order['shipping_address']); ?></p>
            <p><strong>City:</strong> <?php echo escape($order['shipping_city']); ?></p>
            <p><strong>Postal:</strong> <?php echo escape($order['shipping_postal']); ?></p>
            <p><strong>Phone:</strong> <?php echo escape($order['shipping_phone']); ?></p>

            <h3 style="margin-top: 30px;">Order Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td>
                                <?php echo escape($item['product_name']); ?>
                                <div style="font-size: 13px; color: var(--gray);">
                                    Size: <?php echo escape($item['size']); ?>
                                </div>
                            </td>
                            <td><?php echo formatPrice($item['price']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 30px; text-align: right;">
                <p>Subtotal: <?php echo formatPrice($order['subtotal']); ?></p>
                <p>Shipping: <?php echo formatPrice($order['shipping_cost']); ?></p>
                <h3 style="color: var(--primary);">Total: <?php echo formatPrice($order['total']); ?></h3>
            </div>

            <h3 style="margin-top: 30px;">Update Status</h3>
            <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                <select name="status" class="form-group" style="flex: 1; max-width: 200px; padding: 8px;">
                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo ($order['payment_status'] === 'paid' && $order['status'] === 'pending') ? 'selected' : ''; ?>>Paid</option>
                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="completed" <?php echo in_array($order['status'], ['completed', 'delivered']) ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </form>
        </div>
    </div>
    <?php
    include __DIR__ . '/../../../backend/includes/admin-footer.php';
    exit;
}

// Get all orders
$orders = $db->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();

$admin_title = "Orders";
include __DIR__ . '/../../../backend/includes/admin-header.php';

echo $message;
?>

<h1>Orders Management</h1>

<div class="admin-table" style="margin-top: 20px;">
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>No orders yet</h3>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo escape($order['shipping_name']); ?></td>
                        <td><?php echo formatPrice($order['total']); ?></td>
                        <td>
                            <span class="badge badge-<?php
                                echo match($order['status']) {
                                    'delivered' => 'success',
                                    'cancelled' => 'danger',
                                    'shipped', 'processing' => 'warning',
                                    default => 'info'
                                };
                            ?>"><?php echo ucfirst($order['status']); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../../backend/includes/admin-footer.php'; ?>