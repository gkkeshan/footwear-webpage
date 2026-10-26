<?php
// Common footer for all pages
$isAdminPage = (strpos($_SERVER['PHP_SELF'], 'admin') !== false);
?>

<?php if ($isAdminPage): ?>
    </main><!-- End admin-content -->
</div><!-- End admin-layout -->
<?php else: ?>
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p style="opacity: 0.8; line-height: 1.8;">
                    Girls' Footwear is your destination for stylish, comfortable shoes for girls of all ages. From toddler to teen, we've got every occasion covered.
                </p>
            </div>
            <div class="footer-section">
                <h3>Shop</h3>
                <ul>
                    <li><a href="pages/products.php?category=sneakers">Sneakers</a></li>
                    <li><a href="pages/products.php?category=sandals">Sandals</a></li>
                    <li><a href="pages/products.php?category=boots">Boots</a></li>
                    <li><a href="pages/products.php?category=school-shoes">School Shoes</a></li>
                    <li><a href="pages/products.php?category=party-shoes">Party Shoes</a></li>
                    <li><a href="pages/products.php?category=flats">Flats</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Help</h3>
                <ul>
                    <li><a href="#">Shipping Information</a></li>
                    <li><a href="#">Returns & Exchanges</a></li>
                    <li><a href="#">Size Guide</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">FAQs</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Connect</h3>
                <ul>
                    <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                    <li><a href="#"><i class="fab fa-pinterest"></i> Pinterest</a></li>
                </ul>
                <div style="margin-top: 15px;">
                    <p style="opacity: 0.7; font-size: 14px;">
                        <i class="fas fa-envelope"></i> hello@girlsfootwear.com<br>
                        <i class="fas fa-phone"></i> 1-800-GIRLSHOE
                    </p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Girls' Footwear. All rights reserved. Made with ❤️ for stylish girls everywhere.</p>
        </div>
    </footer>
<?php endif; ?>

    <script src="../assets/js/app.js"></script>
</body>
</html>