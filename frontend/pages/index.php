<?php
// Homepage - Girls' Footwear E-Commerce
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$db = getDBConnection();

// Get featured products
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug,
           pi.image_path as product_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE p.featured = TRUE AND p.active = TRUE
    ORDER BY p.created_at DESC
    LIMIT 8
");
$stmt->execute();
$featuredProducts = $stmt->fetchAll();

// Get all main categories
$categories = $db->query("
    SELECT * FROM categories
    WHERE parent_id IS NULL
    ORDER BY name
")->fetchAll();

// Category icon mapping (FontAwesome)
$categoryIcons = [
    'Sneakers' => '<i class="fas fa-shoe-prints"></i>',
    'Sandals' => '<i class="fas fa-shoe-prints"></i>',
    'Party Shoes' => '<i class="fas fa-gem"></i>',
    'Flats' => '<i class="fas fa-shoe-prints"></i>'
];

$page_title = "Girls' Footwear - Shop Sneakers, Sandals, Party Shoes & More";
include __DIR__ . '/../../backend/includes/header.php';
?>

<!-- Hero Banner -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-text">
            <h1>Stylish Steps for Every Girl</h1>
            <p>From toddler to teen, find the perfect pair for play and parties. Comfortable, durable, and on-trend footwear for every occasion.</p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="products.php" class="btn btn-primary">Shop All</a>
                <a href="products.php?category=sneakers" class="btn btn-outline">Browse Sneakers</a>
            </div>
        </div>
        <div class="hero-image"><img src="<?php echo BASE_URL; ?>buy-yours-icon.svg" alt="BUYOURS" class="hero-logo-img"></div>
    </div>
</section>

<!-- Featured Products -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-star"></i> Featured Products</h2>
        <p class="section-subtitle">Our most loved picks for your little ones</p>
    </div>
    <div class="products-grid">
        <?php foreach ($featuredProducts as $product):
            $sizes = json_decode($product['sizes'], true) ?? [];
            $colors = json_decode($product['colors'], true) ?? [];
        ?>
            <div class="product-card">
                <a href="product.php?slug=<?php echo escape($product['slug']); ?>" class="product-image">
                    <span class="product-badge">Featured</span>
                    <?php if (!empty($product['product_image'])): ?>
                        <img src="../<?php echo escape($product['product_image']); ?>" alt="<?php echo escape($product['name']); ?>" style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0;">
                    <?php else: ?>
                        <i class="fas fa-shoe-prints"></i>
                    <?php endif; ?>
                </a>
                <div class="product-info">
                    <a href="product.php?slug=<?php echo escape($product['slug']); ?>" class="product-name">
                        <?php echo escape($product['name']); ?>
                    </a>
                    <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                    <div class="product-actions">
                        <a href="product.php?slug=<?php echo escape($product['slug']); ?>" class="btn btn-outline btn-sm">View Details</a>
                        <button class="btn btn-primary btn-sm add-cart-btn"
                            onclick="quickAddToCart(<?php echo $product['id']; ?>, '<?php echo escape($product['name']); ?>', '<?php echo escape($product['sizes']); ?>')">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Categories -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Shop by Category</h2>
        <p class="section-subtitle">Find exactly what you need</p>
    </div>
    <div class="categories-grid">
        <?php foreach ($categories as $category):
            $icon = $categoryIcons[$category['name']] ?? '<i class="fas fa-shoe-prints"></i>';
        ?>
            <a href="products.php?category=<?php echo escape($category['slug']); ?>" class="category-card">
                <div class="category-icon"><?php echo $icon; ?></div>
                <div class="category-name"><?php echo escape($category['name']); ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Why Shop With Us -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Why Shop With Us</h2>
    </div>
    <div class="categories-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="category-card">
            <div class="category-icon"><i class="fas fa-truck"></i></div>
            <div class="category-name">Free Shipping</div>
            <p style="color: var(--gray); font-size: 14px; margin-top: 8px;">On orders over Rs. 5,000</p>
        </div>
        <div class="category-card">
            <div class="category-icon"><i class="fas fa-rotate-left"></i></div>
            <div class="category-name">Easy Returns</div>
            <p style="color: var(--gray); font-size: 14px; margin-top: 8px;">30-day return policy</p>
        </div>
        <div class="category-card">
            <div class="category-icon"><i class="fas fa-lock"></i></div>
            <div class="category-name">Secure Payment</div>
            <p style="color: var(--gray); font-size: 14px; margin-top: 8px;">SSL encrypted checkout</p>
        </div>
        <div class="category-card">
            <div class="category-icon"><i class="fas fa-award"></i></div>
            <div class="category-name">Quality Guarantee</div>
            <p style="color: var(--gray); font-size: 14px; margin-top: 8px;">Hand-picked products</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>