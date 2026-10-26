<?php
// Admin Dashboard
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

// Get dashboard stats
$totalProducts = $db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
$totalOrders = $db->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
$totalUsers = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch()['count'];
$totalRevenue = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'")->fetch()['total'];

// Get recent orders
$recentOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get low stock products
$lowStock = $db->query("SELECT * FROM products WHERE stock < 10 AND active = TRUE ORDER BY stock ASC LIMIT 5")->fetchAll();

$admin_title = "Dashboard";
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-stats">
    <div class="stat-card">
        <div class="stat-label">Total Products</div>
        <div class="stat-value"><?php echo $totalProducts; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?php echo $totalOrders; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Customers</div>
        <div class="stat-value"><?php echo $totalUsers; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value"><?php echo formatPrice($totalRevenue); ?></div>
    </div>
</div>

<div class="admin-table">
    <h2 style="padding: 20px; margin: 0;">Recent Orders</h2>
    <?php if (empty($recentOrders)): ?>
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
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
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
                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="admin-table" style="margin-top: 30px;">
    <h2 style="padding: 20px; margin: 0;">Low Stock Alert</h2>
    <?php if (empty($lowStock)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <h3>All products are well stocked</h3>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Stock</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStock as $product): ?>
                    <tr>
                        <td><?php echo escape($product['name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $product['stock'] == 0 ? 'danger' : 'warning'; ?>">
                                <?php echo $product['stock']; ?> left
                            </span>
                        </td>
                        <td>
                            <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline">Restock</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>