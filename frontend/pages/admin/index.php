<?php
/**
 * Admin Dashboard
 * Displays business KPIs, sales statistics, low-stock alerts, and recent orders.
 * Feature: Start-to-End Date filter for Revenue and Sales analysis (e.g. Month-end revenue).
 */
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

// --- Date Filtering for Revenue & Sales ---
$preset    = trim($_GET['preset'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate   = trim($_GET['end_date'] ?? '');

// Handle quick preset shortcuts
if ($preset === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');
} elseif ($preset === 'last_month') {
    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate   = date('Y-m-t', strtotime('last day of last month'));
} elseif ($preset === 'last_30_days') {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate   = date('Y-m-d');
} elseif ($preset === 'today') {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d');
} elseif ($preset === 'all') {
    $startDate = '';
    $endDate   = '';
}

// Validate date inputs (YYYY-MM-DD)
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($startDate && !preg_match($datePattern, $startDate)) {
    $startDate = '';
}
if ($endDate && !preg_match($datePattern, $endDate)) {
    $endDate = '';
}

$isFiltered = (!empty($startDate) || !empty($endDate));

// Identify active preset for button styling
$activePreset = '';
if (!$isFiltered) {
    $activePreset = 'all';
} elseif ($startDate === date('Y-m-01') && $endDate === date('Y-m-t')) {
    $activePreset = 'this_month';
} elseif ($startDate === date('Y-m-01', strtotime('first day of last month')) && $endDate === date('Y-m-t', strtotime('last day of last month'))) {
    $activePreset = 'last_month';
} elseif ($startDate === date('Y-m-d', strtotime('-30 days')) && $endDate === date('Y-m-d')) {
    $activePreset = 'last_30_days';
} elseif ($startDate === date('Y-m-d') && $endDate === date('Y-m-d')) {
    $activePreset = 'today';
}

// 1. General stats
$totalProducts  = (int)$db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
$totalOrdersAll = (int)$db->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
$lowStockCount  = (int)$db->query("SELECT COUNT(*) as count FROM products WHERE stock < 10 AND active = TRUE")->fetch()['count'];
$totalUsers     = (int)$db->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch()['count'];
$allTimeRevenue = (float)$db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'")->fetch()['total'];

// 2. Revenue & Orders within the filtered date range
$revWhere  = ["status != 'cancelled'"];
$revParams = [];
if (!empty($startDate)) {
    $revWhere[]  = "created_at >= ?";
    $revParams[] = $startDate . ' 00:00:00';
}
if (!empty($endDate)) {
    $revWhere[]  = "created_at <= ?";
    $revParams[] = $endDate . ' 23:59:59';
}

$revSql  = "SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as order_count FROM orders WHERE " . implode(' AND ', $revWhere);
$revStmt = $db->prepare($revSql);
$revStmt->execute($revParams);
$revData             = $revStmt->fetch();
$filteredRevenue     = (float)$revData['total'];
$filteredOrdersCount = (int)$revData['order_count'];
$avgOrderValue       = $filteredOrdersCount > 0 ? ($filteredRevenue / $filteredOrdersCount) : 0;

// 3. Orders table data (Filtered period orders or recent 5)
if ($isFiltered) {
    $orderWhere  = [];
    $orderParams = [];
    if (!empty($startDate)) {
        $orderWhere[]  = "created_at >= ?";
        $orderParams[] = $startDate . ' 00:00:00';
    }
    if (!empty($endDate)) {
        $orderWhere[]  = "created_at <= ?";
        $orderParams[] = $endDate . ' 23:59:59';
    }
    $whereClause = !empty($orderWhere) ? "WHERE " . implode(' AND ', $orderWhere) : "";
    $ordersSql   = "SELECT * FROM orders $whereClause ORDER BY created_at DESC LIMIT 50";
    $ordersStmt  = $db->prepare($ordersSql);
    $ordersStmt->execute($orderParams);
    $displayOrders = $ordersStmt->fetchAll();
} else {
    $displayOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();
}

// 4. Low stock products alert
$lowStock = $db->query("SELECT * FROM products WHERE stock < 10 AND active = TRUE ORDER BY stock ASC LIMIT 5")->fetchAll();

$admin_title = "Dashboard";
include __DIR__ . '/../../../backend/includes/admin-header.php';
?>

<!-- Header Status Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #e6f4ea; color: #137333; font-weight: 600; font-size: 14px; padding: 6px 16px; border-radius: 20px; border: 1px solid #ceead6;">
        <i class="fas fa-check-circle"></i> Database connected ✓
    </div>
    <div style="color: var(--gray); font-size: 14px;">
        <i class="far fa-clock"></i> <?php echo date('l, F j, Y'); ?>
    </div>
</div>

<!-- ===================================================================
     REVENUE & SALES DATE RANGE FILTER
     =================================================================== -->
<div style="background: var(--white); border-radius: 12px; padding: 20px 24px; margin-bottom: 25px; box-shadow: var(--shadow); border: 1px solid #f0f0f0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #FFF0F6; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #333;">Revenue Date Filter</h3>
                <p style="margin: 2px 0 0; font-size: 12px; color: #777;">Track revenue by month-end, custom date range, or historical periods</p>
            </div>
        </div>

        <!-- Quick Preset Buttons -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?preset=this_month"
               style="padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; <?php echo ($activePreset === 'this_month') ? 'background: var(--primary); color: white;' : 'background: #f5f5f5; color: #555;'; ?>">
                <i class="fas fa-calendar-check"></i> This Month
            </a>
            <a href="?preset=last_month"
               style="padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; <?php echo ($activePreset === 'last_month') ? 'background: var(--primary); color: white;' : 'background: #f5f5f5; color: #555;'; ?>">
                Last Month
            </a>
            <a href="?preset=last_30_days"
               style="padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; <?php echo ($activePreset === 'last_30_days') ? 'background: var(--primary); color: white;' : 'background: #f5f5f5; color: #555;'; ?>">
                Last 30 Days
            </a>
            <a href="?preset=today"
               style="padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; <?php echo ($activePreset === 'today') ? 'background: var(--primary); color: white;' : 'background: #f5f5f5; color: #555;'; ?>">
                Today
            </a>
            <a href="?preset=all"
               style="padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all 0.2s; <?php echo ($activePreset === 'all') ? 'background: var(--primary); color: white;' : 'background: #f5f5f5; color: #555;'; ?>">
                All Time
            </a>
        </div>
    </div>

    <!-- Date Range Form -->
    <form method="GET" action="index.php" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 170px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px;">
                <i class="far fa-calendar"></i> Start Date
            </label>
            <input type="date" name="start_date" value="<?php echo escape($startDate); ?>"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; color: #333; background: #fafafa;">
        </div>
        <div style="flex: 1; min-width: 170px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px;">
                <i class="far fa-calendar"></i> End Date
            </label>
            <input type="date" name="end_date" value="<?php echo escape($endDate); ?>"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; color: #333; background: #fafafa;">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="padding: 9px 20px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($isFiltered): ?>
                <a href="index.php" class="btn btn-outline" style="padding: 9px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: #666;">
                    <i class="fas fa-times"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Active Filter Summary Banner -->
    <?php if ($isFiltered): ?>
        <div style="margin-top: 15px; padding: 12px 16px; background: #FFF5F9; border-left: 4px solid var(--primary); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 13px; color: #444;">
                <span style="font-weight: 700; color: var(--primary);"><i class="fas fa-info-circle"></i> Active Filter:</span>
                Showing revenue from <strong><?php echo !empty($startDate) ? date('M j, Y', strtotime($startDate)) : 'Beginning'; ?></strong>
                to <strong><?php echo !empty($endDate) ? date('M j, Y', strtotime($endDate)) : 'Today'; ?></strong>
                &bull; <strong><?php echo $filteredOrdersCount; ?></strong> valid order(s)
                &bull; Avg Order: <strong><?php echo formatPrice($avgOrderValue); ?></strong>
            </div>
            <a href="index.php" style="font-size: 12px; color: var(--primary); font-weight: 600; text-decoration: none;">
                <i class="fas fa-times-circle"></i> Clear filter
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- ===================================================================
     KPI STATS CARDS
     =================================================================== -->
<div class="admin-stats">
    <!-- Products -->
    <div class="stat-card">
        <div class="stat-label">Products</div>
        <div class="stat-value"><?php echo $totalProducts; ?></div>
        <div style="font-size: 12px; color: #999; margin-top: 6px;">Catalog items</div>
    </div>

    <!-- Orders -->
    <div class="stat-card">
        <div class="stat-label"><?php echo $isFiltered ? 'Orders (Filtered)' : 'Orders'; ?></div>
        <div class="stat-value"><?php echo $isFiltered ? $filteredOrdersCount : $totalOrdersAll; ?></div>
        <div style="font-size: 12px; color: #999; margin-top: 6px;">
            <?php if ($isFiltered): ?>
                All-time: <?php echo $totalOrdersAll; ?> total
            <?php else: ?>
                All-time orders
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="stat-card" style="<?php echo $lowStockCount > 0 ? 'border-left: 4px solid #f2994a;' : ''; ?>">
        <div class="stat-label">Low Stock</div>
        <div class="stat-value" style="<?php echo $lowStockCount > 0 ? 'color: #e65100;' : ''; ?>"><?php echo $lowStockCount; ?></div>
        <div style="font-size: 12px; color: #999; margin-top: 6px;">Needs restock (&lt; 10 units)</div>
    </div>

    <!-- Total / Filtered Revenue -->
    <div class="stat-card" style="<?php echo $isFiltered ? 'border: 2px solid var(--primary); background: #FFFDFE;' : ''; ?>">
        <div class="stat-label" style="display: flex; justify-content: space-between; align-items: center;">
            <span><?php echo $isFiltered ? 'Revenue (Filtered)' : 'Total Revenue'; ?></span>
            <?php if ($isFiltered): ?>
                <span style="background: var(--primary); color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px;">FILTERED</span>
            <?php endif; ?>
        </div>
        <div class="stat-value"><?php echo formatPrice($isFiltered ? $filteredRevenue : $allTimeRevenue); ?></div>
        <div style="font-size: 12px; color: #888; margin-top: 6px;">
            <?php if ($isFiltered): ?>
                <span title="All-time revenue: <?php echo formatPrice($allTimeRevenue); ?>">All-time: <?php echo formatPrice($allTimeRevenue); ?></span>
            <?php else: ?>
                All-time revenue (excl. cancelled)
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================================================================
     ORDERS TABLE (FILTERED OR RECENT)
     =================================================================== -->
<div class="admin-table">
    <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin: 0; font-size: 18px;">
            <?php if ($isFiltered): ?>
                <i class="fas fa-list" style="color: var(--primary); margin-right: 6px;"></i>
                Orders in Selected Period (<?php echo count($displayOrders); ?>)
            <?php else: ?>
                Recent Orders
            <?php endif; ?>
        </h2>
        <div>
            <a href="orders.php" style="font-size: 13px; color: var(--primary); font-weight: 600; text-decoration: none;">
                View All Orders <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <?php if (empty($displayOrders)): ?>
        <div class="empty-state" style="padding: 40px; text-align: center;">
            <div class="empty-state-icon" style="font-size: 40px; margin-bottom: 10px;">📋</div>
            <h3 style="margin-bottom: 6px;">No orders found</h3>
            <p style="color: #888; font-size: 14px;">
                <?php if ($isFiltered): ?>
                    No orders were recorded between <?php echo !empty($startDate) ? date('M j, Y', strtotime($startDate)) : 'start'; ?> and <?php echo !empty($endDate) ? date('M j, Y', strtotime($endDate)) : 'end'; ?>.
                <?php else: ?>
                    No orders have been placed yet.
                <?php endif; ?>
            </p>
            <?php if ($isFiltered): ?>
                <a href="index.php" class="btn btn-outline" style="margin-top: 10px;">Clear Filter</a>
            <?php endif; ?>
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
                    <th>Date & Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayOrders as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                        <td><?php echo escape($order['shipping_name']); ?></td>
                        <td><strong><?php echo formatPrice($order['total']); ?></strong></td>
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
                            <span class="badge badge-<?php echo ($order['payment_status'] ?? 'pending') === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y, g:i A', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ===================================================================
     LOW STOCK ALERT
     =================================================================== -->
<div class="admin-table" style="margin-top: 30px;">
    <h2 style="padding: 20px; margin: 0; font-size: 18px; border-bottom: 1px solid #eee;">
        <i class="fas fa-exclamation-triangle" style="color: #f2994a; margin-right: 6px;"></i> Low Stock Alert
    </h2>
    <?php if (empty($lowStock)): ?>
        <div class="empty-state" style="padding: 30px; text-align: center;">
            <div class="empty-state-icon" style="font-size: 32px; margin-bottom: 8px;">✅</div>
            <h3>All products are well stocked</h3>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Stock Remaining</th>
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