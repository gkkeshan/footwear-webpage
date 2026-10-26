<?php
// Product Listing Page with Filters and Sorting
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$db = getDBConnection();

// Get filter parameters
$categorySlug = $_GET['category'] ?? '';
$ageGroup = $_GET['age_group'] ?? '';
$sort = $_GET['sort'] ?? 'featured';
$priceMin = $_GET['price_min'] ?? '';
$priceMax = $_GET['price_max'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$where = [];
$params = [];

if ($categorySlug) {
    // Get category and its subcategories
    $stmt = $db->prepare("SELECT id, age_group FROM categories WHERE slug = ?");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();

    if ($category) {
        if ($category['age_group'] === 'all') {
            // Get all subcategory IDs for this category
            $stmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ?");
            $stmt->execute([$category['id']]);
            $subIds = array_column($stmt->fetchAll(), 'id');
            array_unshift($subIds, $category['id']);
            $placeholders = str_repeat('?,', count($subIds) - 1) . '?';
            $where[] = "p.category_id IN ($placeholders)";
            $params = array_merge($params, $subIds);
        } else {
            $where[] = "p.category_id = ?";
            $params[] = $category['id'];
        }
    }
}

if ($ageGroup && $ageGroup !== 'all') {
    $where[] = "c.age_group = ?";
    $params[] = $ageGroup;
}

if ($priceMin) {
    $where[] = "p.price >= ?";
    $params[] = $priceMin;
}

if ($priceMax) {
    $where[] = "p.price <= ?";
    $params[] = $priceMax;
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Build ORDER BY clause
$orderBy = match($sort) {
    'price-low' => 'p.price ASC',
    'price-high' => 'p.price DESC',
    'name' => 'p.name ASC',
    'newest' => 'p.created_at DESC',
    default => 'p.featured DESC, p.created_at DESC',
};

// Get total count
$countSql = "SELECT COUNT(*) as total FROM products p JOIN categories c ON p.category_id = c.id $whereSql";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalProducts = $stmt->fetch()['total'];
$totalPages = ceil($totalProducts / $perPage);

// Get products
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, pi.image_path as product_image
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        $whereSql
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get all main categories for filter dropdown
$categories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

// Build query string for filters
function buildQueryString($exclude = []) {
    $params = [];
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $exclude) && $value !== '') {
            $params[] = "$key=" . urlencode($value);
        }
    }
    return implode('&', $params);
}

$page_title = "All Products - Girls' Footwear";
include __DIR__ . '/../../backend/includes/header.php';
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">
            <?php echo $search ? "Search Results for \"$search\"" : "All Products"; ?>
        </h1>
        <p class="section-subtitle">
            <?php echo $totalProducts; ?> product(s) found
        </p>
    </div>

    <div class="products-layout">
        <!-- Filters Sidebar -->
        <aside class="filters">
            <form id="filter-form">

                <div class="filter-group">
                    <h3 class="filter-title">Price Range (Rs.)</h3>
                    <div class="price-inputs">
                        <input type="number" name="price_min" placeholder="1000" min="1000" max="5000" step="100"
                            value="<?php echo escape($priceMin); ?>">
                        <span>-</span>
                        <input type="number" name="price_max" placeholder="5000" min="1000" max="5000" step="100"
                            value="<?php echo escape($priceMax); ?>">
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm" style="margin-top: 10px; width: 100%;">Apply</button>
                </div>

                <div class="filter-group">
                    <h3 class="filter-title">Sizes</h3>
                    <div class="filter-option">
                        <input type="checkbox" id="size-3-7" value="toddler">
                        <label for="size-3-7">Toddler (3-7)</label>
                    </div>
                    <div class="filter-option">
                        <input type="checkbox" id="size-8-13" value="kids">
                        <label for="size-8-13">Kids (8-13)</label>
                    </div>
                    <div class="filter-option">
                        <input type="checkbox" id="size-1-6" value="teens">
                        <label for="size-1-6">Teens (1-6)</label>
                    </div>
                </div>

                <button type="button" class="btn btn-outline btn-block" onclick="location.reload()">Clear All</button>
            </form>
        </aside>

        <!-- Products Grid -->
        <div>
            <!-- Toolbar -->
            <div class="products-toolbar">
                <span class="products-count"><?php echo $totalProducts; ?> products</span>
                <select class="sort-select" onchange="window.location.href='?<?php echo buildQueryString(['sort', 'page']); ?>&sort=' + this.value">
                    <option value="featured" <?php echo ($sort === 'featured') ? 'selected' : ''; ?>>Featured</option>
                    <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest</option>
                    <option value="price-low" <?php echo ($sort === 'price-low') ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price-high" <?php echo ($sort === 'price-high') ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name" <?php echo ($sort === 'name') ? 'selected' : ''; ?>>Name A-Z</option>
                </select>
            </div>

            <!-- Products Grid -->
            <div class="products-grid">
                <?php foreach ($products as $product):
                    $sizes = json_decode($product['sizes'], true) ?? [];
                    $colors = json_decode($product['colors'], true) ?? [];
                ?>
                    <div class="product-card">
                        <a href="product.php?slug=<?php echo escape($product['slug']); ?>" class="product-image">
                            <?php if ($product['featured']): ?>
                                <span class="product-badge">Featured</span>
                            <?php endif; ?>
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
                            <div style="font-size: 13px; color: var(--gray); margin-bottom: 12px;">
                                <?php echo count($sizes); ?> sizes · <?php echo count($colors); ?> colors
                            </div>
                            <div class="product-actions">
                                <a href="product.php?slug=<?php echo escape($product['slug']); ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn btn-outline btn-sm add-cart-btn"
                                    onclick="quickAddToCart(<?php echo $product['id']; ?>, '<?php echo escape($product['name']); ?>', '<?php echo escape($product['sizes']); ?>')">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 8px; margin-top: 30px;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?<?php echo buildQueryString(['page']); ?>&page=<?php echo $i; ?>"
                           style="padding: 8px 14px; border-radius: 6px; text-decoration: none;
                           <?php echo ($i === $page) ? 'background: var(--primary); color: white;' : 'background: var(--gray-light);'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3>No products found</h3>
                    <p>Try adjusting your filters or search terms.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>