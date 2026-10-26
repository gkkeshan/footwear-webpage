<?php
// 404 Error Page
header('HTTP/1.0 404 Not Found');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="header">
    <div class="header-main">
        <a href="index.php" class="logo">
            <span class="logo-icon">👟</span>
            <span>Girls' Footwear</span>
        </a>
    </div>
</header>

<main style="max-width: 1280px; margin: 100px auto; text-align: center; padding: 0 20px;">

    <div style="font-size: 100px; margin-bottom: 30px;">❌</div>

    <h1>Page Not Found</h1>
    <p style="color: var(--gray); font-size: 20px;">The page you're looking for doesn't exist.</p>

    <a href="index.php" class="btn btn-primary" style="margin-top: 30px;">Back to Home</a>

</main>

<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3>Girls' Footwear</h3>
            <p style="opacity: 0.8;">Shop sneakers, sandals, boots & more.</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="pages/products.php">Shop</a></li>
                <li><a href="pages/cart.php">Cart</a></li>
                <li><a href="pages/login.php">Account</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> Girls' Footwear. All rights reserved.</p>
    </div>
</footer>

</body>
</html>