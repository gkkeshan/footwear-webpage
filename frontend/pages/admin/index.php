<?php
// Admin Dashboard
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

// Get dashboard stats
$totalProducts = $db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
$totalOrders = $db->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
$lowStockCount = $db->query("SELECT COUNT(*) as count FROM products WHERE stock < 10 AND active = TRUE")->fetch()['count'];
$totalUsers = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch()['count'];
$totalRevenue = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'")->fetch()['total'];

// Get recent orders
$recentOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get low stock products
$lowStock = $db->query("SELECT * FROM products WHERE stock < 10 AND active = TRUE ORDER BY stock ASC LIMIT 5")->fetchAll();

$admin_title = "Dashboard";
include __DIR__ . '/../../../backend/includes/admin-header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #e6f4ea; color: #137333; font-weight: 600; font-size: 14px; padding: 6px 16px; border-radius: 20px; border: 1px solid #ceead6;">
        <i class="fas fa-check-circle"></i> Database connected ✓
    </div>
    <div style="color: var(--gray); font-size: 14px;">
        <i class="far fa-clock"></i> <?php echo date('l, F j, Y'); ?>
    </div>
</div>

<div class="admin-stats">
    <div class="stat-card">
        <div class="stat-label">Products</div>
        <div class="stat-value"><?php echo $totalProducts; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Orders</div>
        <div class="stat-value"><?php echo $totalOrders; ?></div>
    </div>
    <div class="stat-card" style="<?php echo $lowStockCount > 0 ? 'border-left: 4px solid #f2994a;' : ''; ?>">
        <div class="stat-label">Low Stock</div>
        <div class="stat-value" style="<?php echo $lowStockCount > 0 ? 'color: #e65100;' : ''; ?>"><?php echo $lowStockCount; ?></div>
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

<?php include __DIR__ . '/../../../backend/includes/admin-footer.php'; ?>