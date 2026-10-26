<?php
// Common footer for customer pages
?>
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About BUYOURS</h3>
                <p style="opacity: 0.8; line-height: 1.8;">
                    Handcrafted embroidered footwear. Share your smile with the world.
                </p>
            </div>
            <div class="footer-section">
                <h3>Shop</h3>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>frontend/pages/products.php">All Products</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/pages/products.php?sort=newest">New Arrivals</a></li>
                    <li><a href="<?php echo BASE_URL; ?>frontend/pages/products.php?sort=featured">Featured</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Help</h3>
                <ul>
                    <li><a href="#">Shipping Information</a></li>
                    <li><a href="#">Returns & Exchanges</a></li>
                    <li><a href="#">Size Guide</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Connect</h3>
                <ul>
                    <li><a href="https://www.facebook.com/share/14pGAmfpcAc/" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i> Facebook</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                    <li><a href="#"><i class="fab fa-whatsapp"></i> WhatsApp</a></li>
                </ul>
                <div style="margin-top: 15px;">
                    <p style="opacity: 0.7; font-size: 14px;">
                        <i class="fas fa-envelope"></i> hello@buyours.lk<br>
                        <i class="fas fa-phone"></i> +94 XX XXX XXXX
                    </p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> BUYOURS. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?php echo BASE_URL; ?>frontend/assets/js/app.js"></script>
</body>
</html>