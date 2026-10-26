<?php
// Product Detail Page
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$db = getDBConnection();

$slug = $_GET['slug'] ?? '';

// Get product details
$stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug
                   FROM products p
                   JOIN categories c ON p.category_id = c.id
                   WHERE p.slug = ? AND p.active = TRUE");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    include __DIR__ . '/../errors/404.php';
    exit;
}

// Get product images
$imagesStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$imagesStmt->execute([$product['id']]);
$images = $imagesStmt->fetchAll();

// Get related products (same category)
$relatedStmt = $db->prepare("SELECT p.*, c.name as category_name, pi.image_path as product_image
                           FROM products p
                           JOIN categories c ON p.category_id = c.id
                           LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                           WHERE p.category_id = ? AND p.id != ? AND p.active = TRUE
                           ORDER BY p.featured DESC
                           LIMIT 4");
$relatedStmt->execute([$product['category_id'], $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

// Get average rating and reviews count
$reviewsStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ?");
$reviewsStmt->execute([$product['id']]);
$reviewsData = $reviewsStmt->fetch();
$averageRating = $reviewsData['avg_rating'] ? round($reviewsData['avg_rating'], 1) : 0;
$reviewCount = $reviewsData['review_count'];

// Get all available sizes from stock
$sizes = json_decode($product['sizes'], true) ?? [];
$colors = json_decode($product['colors'], true) ?? [];

$page_title = $product['name'] . " - Girls' Footwear";
include __DIR__ . '/../../backend/includes/header.php';
?>

<style>
    /* Color swatches for product page */
    .color-swatch {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 2px solid var(--gray-border);
        cursor: pointer;
        transition: transform 0.2s, border-color 0.2s;
    }
    .color-swatch:hover {
        transform: scale(1.1);
        border-color: var(--primary);
    }
    .color-swatch.active {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light);
    }
</style>

<section class="section">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 14px; color: var(--gray);">
        <a href="index.php" style="color: var(--primary); text-decoration: none;">Home</a>
        <span>/</span>
        <a href="products.php?category=<?php echo $product['category_slug']; ?>" style="color: var(--primary); text-decoration: none;">
            <?php echo escape($product['category_name']); ?>
        </a>
        <span>/</span>
        <span><?php echo escape($product['name']); ?></span>
    </div>

    <div class="product-detail">
        <!-- Product Gallery -->
        <div class="product-gallery">
            <div class="gallery-thumbs">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $index => $img): ?>
                        <div class="gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>" onclick="showMainImage(<?php echo $index; ?>)">
                            <img src="../<?php echo escape($img['image_path']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gallery-thumb active">
                        <i class="fas fa-shoe-prints" style="font-size: 50px;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="gallery-main">
                <?php if (!empty($images)): ?>
                    <img id="main-product-image" src="../<?php echo escape($images[0]['image_path']); ?>" alt="<?php echo escape($product['name']); ?>" style="width:100%; height:100%; object-fit:contain;">
                <?php else: ?>
                    <i class="fas fa-shoe-prints" style="font-size: 200px;"></i>
                <?php endif; ?>
            </div>
        </div>

        <!-- Product Info -->
        <div class="product-detail-info">
            <h1><?php echo escape($product['name']); ?></h1>

            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <div class="product-detail-price">
                    <?php echo formatPrice($product['price']); ?>
                </div>
                <div style="color: var(--gray);">
                    SKU: #<?php echo $product['id']; ?>
                </div>
            </div>

            <?php if ($reviewCount > 0): ?>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 25px;">
                    <div style="color: #ffb347;">
                        <?php echo str_repeat('★', floor($averageRating)); echo str_repeat('☆', 5 - floor($averageRating)); ?>
                    </div>
                    <span style="color: var(--gray); font-size: 14px;">(<?php echo $reviewCount; ?> reviews)</span>
                </div>
            <?php endif; ?>

            <div class="product-description">
                <h3 style="margin-bottom: 10px;">Description</h3>
                <p><?php echo nl2br(escape($product['description'])); ?></p>
            </div>

            <div style="margin-bottom: 25px;">
                <h3 style="margin-bottom: 15px;">Available Sizes</h3>
                <div class="size-options">
                    <?php foreach ($sizes as $size): ?>
                        <label class="size-option">
                            <input type="radio" name="size" value="<?php echo escape($size); ?>" style="display: none;" required>
                            <span><?php echo escape($size); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                <h3 style="margin-bottom: 15px;">Available Colors</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <?php foreach ($colors as $index => $color): ?>
        <div class="color-swatch"
             data-color="<?php echo escape($color); ?>"
             title="<?php echo escape($color); ?>"
             style="background: <?php echo getColorHex($color); ?>;">
        </div>
    <?php endforeach; ?>
</div>
            </div>

            <div style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px;">Quantity</h3>
                <div class="quantity-selector">
                    <div class="quantity-controls">
                        <button type="button" class="qty-btn" onclick="this.parentElement.querySelector('.qty-input').value = Math.max(1, parseInt(this.parentElement.querySelector('.qty-input').value) - 1)">-</button>
                        <input type="text" class="qty-input" value="1" readonly>
                        <button type="button" class="qty-btn" onclick="this.parentElement.querySelector('.qty-input').value = Math.min(10, parseInt(this.parentElement.querySelector('.qty-input').value) + 1)">+</button>
                    </div>
                    <span style="color: var(--gray); font-size: 14px;">(Max 10 per order)</span>
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <?php if ($product['stock'] <= 10 && $product['stock'] > 0): ?>
                    <span style="color: var(--danger); font-weight: 600;">Only <?php echo $product['stock']; ?> left</span>
                <?php elseif ($product['stock'] <= 0): ?>
                    <span style="color: var(--danger); font-weight: 600;">Out of Stock</span>
                <?php else: ?>
                    <span style="color: var(--success); font-weight: 600;">In Stock</span>
                <?php endif; ?>
            </div>

            <button id="add-to-cart-btn" class="btn btn-primary btn-block add-to-cart"
                data-product-id="<?php echo $product['id']; ?>"
                <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-shopping-cart"></i> Add to Cart
            </button>
        </div>
    </div>
</section>

<!-- Related Products -->
<?php if (!empty($relatedProducts)): ?>
<section class="section">
    <h2 class="section-title">Related Products</h2>
    <div class="products-grid">
        <?php foreach ($relatedProducts as $related): ?>
            <div class="product-card">
                <a href="product.php?slug=<?php echo escape($related['slug']); ?>" class="product-image">
                    <?php if (!empty($related['product_image'])): ?>
                        <img src="../<?php echo escape($related['product_image']); ?>" alt="<?php echo escape($related['name']); ?>" style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0;">
                    <?php else: ?>
                        <i class="fas fa-shoe-prints" style="font-size: 80px;"></i>
                    <?php endif; ?>
                </a>
                <div class="product-info">
                    <a href="product.php?slug=<?php echo escape($related['slug']); ?>" class="product-name">
                        <?php echo escape($related['name']); ?>
                    </a>
                    <div class="product-price">
                        <?php echo formatPrice($related['price']); ?>
                    </div>
                    <div class="product-actions">
                        <a href="product.php?slug=<?php echo escape($related['slug']); ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Product Reviews -->
<section class="section">
    <h2 class="section-title">Customer Reviews</h2>

    <?php if ($reviewCount > 0): ?>
        <div style="margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 30px;">
                <div style="text-align: center;">
                    <div style="font-size: 48px; font-weight: bold; color: var(--primary);"><?php echo $averageRating; ?></div>
                    <div style="color: #ffb347; font-size: 20px;">
                        <?php echo str_repeat('★', floor($averageRating)); echo str_repeat('☆', 5 - floor($averageRating)); ?>
                    </div>
                    <div style="font-size: 14px; color: var(--gray);">(<?php echo $reviewCount; ?> reviews)</div>
                </div>
                <div style="flex: 1;">
                    <!-- Rating distribution would go here -->
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px;">
        <h3 style="margin-bottom: 20px;">Write a Review</h3>
        <form style="max-width: 600px;">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Rating</label>
                <div style="display: flex; gap: 5px;">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating-<?php echo $i; ?>">
                        <label for="rating-<?php echo $i; ?>">
                            <i class="fas fa-star" style="color: var(--gray); font-size: 24px; cursor: pointer;"></i>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
            <div style="margin-bottom: 20px;">
                <label for="review-text" style="display: block; margin-bottom: 8px; font-weight: 600;">Your Review</label>
                <textarea id="review-text" rows="4" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--gray-border);"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>

