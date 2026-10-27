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

// --- Date and Status Filtering ---
$preset    = trim($_GET['preset'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate   = trim($_GET['end_date'] ?? '');
$status    = trim($_GET['status'] ?? '');

if ($preset === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
} elseif ($preset === 'last_month') {
    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate   = date('Y-m-t', strtotime('last day of last month'));
} elseif ($preset === 'today') {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d');
} elseif ($preset === 'all') {
    $startDate = '';
    $endDate   = '';
    $status    = '';
}

$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($startDate && !preg_match($datePattern, $startDate)) $startDate = '';
if ($endDate && !preg_match($datePattern, $endDate)) $endDate = '';

$where = [];
$params = [];
if (!empty($startDate)) {
    $where[] = "created_at >= ?";
    $params[] = $startDate . ' 00:00:00';
}
if (!empty($endDate)) {
    $where[] = "created_at <= ?";
    $params[] = $endDate . ' 23:59:59';
}
if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
$stmt = $db->prepare("SELECT * FROM orders $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Calculate total revenue for this filtered set (excluding cancelled)
$filteredRevTotal = 0;
foreach ($orders as $o) {
    if ($o['status'] !== 'cancelled') {
        $filteredRevTotal += (float)$o['total'];
    }
}

$isFiltered = (!empty($startDate) || !empty($endDate) || !empty($status));

$admin_title = "Orders";
include __DIR__ . '/../../../backend/includes/admin-header.php';

echo $message;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h1 style="margin: 0;">Orders Management</h1>
    <div style="background: var(--white); padding: 8px 16px; border-radius: 8px; box-shadow: var(--shadow); font-size: 14px;">
        Total: <strong><?php echo count($orders); ?></strong> orders &bull; Revenue: <strong style="color: var(--primary);"><?php echo formatPrice($filteredRevTotal); ?></strong>
    </div>
</div>

<!-- Date & Status Filter Bar -->
<div style="background: var(--white); border-radius: 12px; padding: 18px 22px; margin-bottom: 20px; box-shadow: var(--shadow); border: 1px solid #f0f0f0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <span style="font-weight: 700; font-size: 14px; color: #444;">
            <i class="fas fa-filter" style="color: var(--primary); margin-right: 6px;"></i> Filter Orders by Date
        </span>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <a href="?preset=this_month" style="padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; text-decoration: none; background: #f5f5f5; color: #555;">This Month</a>
            <a href="?preset=last_month" style="padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; text-decoration: none; background: #f5f5f5; color: #555;">Last Month</a>
            <a href="?preset=today" style="padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; text-decoration: none; background: #f5f5f5; color: #555;">Today</a>
            <a href="?preset=all" style="padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; text-decoration: none; background: #f5f5f5; color: #555;">All Orders</a>
        </div>
    </div>
    <form method="GET" action="orders.php" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 4px;">Start Date</label>
            <input type="date" name="start_date" value="<?php echo escape($startDate); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 4px;">End Date</label>
            <input type="date" name="end_date" value="<?php echo escape($endDate); ?>" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
        </div>
        <div style="min-width: 140px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 4px;">Status</label>
            <select name="status" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="processing" <?php echo ($status === 'processing') ? 'selected' : ''; ?>>Processing</option>
                <option value="shipped" <?php echo ($status === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo ($status === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($isFiltered): ?>
                <a href="orders.php" class="btn btn-outline" style="padding: 8px 14px; font-size: 13px; color: #666;"><i class="fas fa-times"></i> Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

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