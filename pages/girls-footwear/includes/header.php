<?php
// Common header for all pages
startSecureSession();
$cartCount = getCartItemCount();
$isAdminPage = (strpos($_SERVER['PHP_SELF'], 'admin') !== false);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape($page_title) : "Girls' Footwear - Sneakers, Sandals & More"; ?></title>
    <meta name="description" content="Shop girls' footwear: sneakers, sandals, party shoes, and flats for toddlers, kids, and teens.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php if ($isAdminPage): ?>
        <!-- Admin Header -->
        <div class="admin-layout">
            <aside class="admin-sidebar">
                <h2><i class="fas fa-shoe-prints"></i> Girls' Footwear</h2>
                <nav>
                    <ul class="admin-nav">
                        <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
                        <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                        <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                        <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li><a href="../index.php" class="active"><i class="fas fa-home"></i> View Site</a></li>
                    </ul>
                </nav>
            </aside>
            <main class="admin-content">
                <div class="admin-header">
                    <h1><?php echo isset($admin_title) ? escape($admin_title) : "Admin Dashboard"; ?></h1>
                    <div class="admin-user">
                        <span><i class="fas fa-user"></i> <?php echo escape($_SESSION['user_name']); ?></span>
                        <a href="../logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
    <?php else: ?>
        <!-- Site Header -->
        <header class="header">
            <div class="header-top">
                <span>Free shipping on orders over Rs. 5,000! <i class="fas fa-gift"></i></span>
            </div>
            <div class="header-main">
                <a href="index.php" class="logo">
                    <span class="logo-icon"><i class="fas fa-shoe-prints"></i></span>
                    <span>Girls' Footwear</span>
                </a>
                <div class="search-bar">
                    <input type="text" id="search-input" placeholder="Search for sneakers, sandals, party shoes..." aria-label="Search products">
                    <button type="button" aria-label="Search"><i class="fas fa-search"></i></button>
                </div>
                <div class="header-actions">
                    <a href="login.php"><i class="fas fa-user"></i> Account</a>
                    <a href="cart.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <span class="cart-count" style="display: <?php echo $cartCount > 0 ? 'flex' : 'none'; ?>">
                            <?php echo $cartCount; ?>
                        </span>
                    </a>
                </div>
            </div>
            <nav class="nav">
                <ul class="nav-main">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php?category=sneakers">Sneakers</a></li>
                    <li><a href="products.php?category=sandals">Sandals</a></li>
                    <li><a href="products.php?category=party-shoes">Party Shoes</a></li>
                    <li><a href="products.php?category=flats">Flats</a></li>
                    <li><a href="products.php">All Products</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/index.php"><i class="fas fa-cog"></i> Admin</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </header>
    <?php endif; ?>