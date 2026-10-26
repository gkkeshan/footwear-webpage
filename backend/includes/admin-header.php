<?php
// Admin Header for BUYOURS
require_once __DIR__ . '/functions.php';

// Determine BASE_URL dynamically
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base = preg_replace('#/frontend/pages(/admin)?$#', '', $scriptDir);
    define('BASE_URL', rtrim($base, '/') . '/');
}

startSecureSession();

if (!isAdmin()) {
    header("Location: " . BASE_URL . "frontend/pages/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($admin_title) ? escape($admin_title) : "Admin Dashboard - BUYOURS"; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>frontend/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <h2><img src="<?php echo BASE_URL; ?>buy-yours-icon.svg" alt="BUYOURS" class="logo-img" style="height: 28px; width: 28px; display: inline-block; vertical-align: middle; margin-right: 6px;"> BUYOURS</h2>
        <nav>
            <ul class="admin-nav">
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/products.php"><i class="fas fa-box"></i> Products</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/products.php?filter=inventory"><i class="fas fa-warehouse"></i> Inventory</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/manage-images.php"><i class="fas fa-images"></i> Images</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/index.php"><i class="fas fa-home"></i> View Site</a></li>
            </ul>
        </nav>
    </aside>
    <main class="admin-content">
        <div class="admin-header">
            <h1><?php echo isset($admin_title) ? escape($admin_title) : "Admin Dashboard"; ?></h1>
            <div class="admin-user">
                <span><i class="fas fa-user"></i> <?php echo escape($_SESSION['user_name'] ?? 'Admin'); ?></span>
                <a href="<?php echo BASE_URL; ?>frontend/pages/logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
