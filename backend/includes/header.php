<?php
// Common header for all pages
require_once __DIR__ . '/functions.php';

// Determine BASE_URL dynamically (works from any subfolder)
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Strip trailing /pages or /pages/admin
    $base = preg_replace('#/frontend/pages(/admin)?$#', '', $scriptDir);
    define('BASE_URL', rtrim($base, '/') . '/');
}

startSecureSession();
$cartCount = getCartItemCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape($page_title) : "Buyours - Embroidered Footwear"; ?></title>
    <meta name="description" content="Buyours - Handcrafted embroidered footwear. Share your smile with the world.">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>frontend/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Expose base URL to JS so API calls always work -->
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
</head>
<body>
    <!-- Site Header -->
    <header class="header">
        <div class="header-top">
            <span>Free shipping on orders over Rs. 5,000! <i class="fas fa-gift"></i></span>
            <a href="https://www.facebook.com/share/14pGAmfpcAc/" target="_blank" rel="noopener noreferrer" style="color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fab fa-facebook"></i> Facebook
            </a>
        </div>
        <div class="header-main">
            <a href="<?php echo BASE_URL; ?>frontend/pages/index.php" class="logo">
                <img src="<?php echo BASE_URL; ?>buy-yours-header-logo.svg" alt="BUYOURS" class="header-logo-full">
            </a>
            <div class="search-bar">
                <input type="text" id="search-input" placeholder="Search for sandals, kolhapuri, flats..." aria-label="Search products">
                <button type="button" aria-label="Search"><i class="fas fa-search"></i></button>
            </div>
            <div class="header-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>frontend/pages/account.php"><i class="fas fa-user"></i> <?php echo escape($_SESSION['user_name']); ?></a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>frontend/pages/login.php"><i class="fas fa-user"></i> Account</a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>frontend/pages/cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i> Cart
                    <span class="cart-count" style="display: <?php echo $cartCount > 0 ? 'flex' : 'none'; ?>">
                        <?php echo $cartCount; ?>
                    </span>
                </a>
            </div>
        </div>
        <nav class="nav">
            <ul class="nav-main">
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/index.php">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>frontend/pages/products.php">All Products</a></li>
                <?php
                // Dynamic categories from DB
                try {
                    $dbNav = getDBConnection();
                    $navCats = $dbNav->query("SELECT name, slug FROM categories WHERE parent_id IS NULL ORDER BY name LIMIT 6")->fetchAll();
                    foreach ($navCats as $nc) {
                        echo '<li><a href="' . BASE_URL . 'frontend/pages/products.php?category=' . escape($nc['slug']) . '">' . escape($nc['name']) . '</a></li>';
                    }
                } catch (Exception $e) {}
                ?>
                <?php if (isAdmin()): ?>
                    <li><a href="<?php echo BASE_URL; ?>frontend/pages/admin/index.php"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>