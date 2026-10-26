<?php
// Admin Image Manager - Link downloaded photos to products
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();
$imageDir = __DIR__ . '/../../assets/images/products/';
$message = '';

// Handle image assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $imageName = basename($_POST['image_name'] ?? '');

    if ($productId > 0 && !empty($imageName)) {
        $imagePath = $imageDir . $imageName;
        if (file_exists($imagePath)) {
            // Set as primary image
            $stmt = $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)
                                   ON DUPLICATE KEY UPDATE image_path = ?, is_primary = 1");
            $dbPath = 'assets/images/products/' . $imageName;
            $stmt->execute([$productId, $dbPath, $dbPath]);
            $message = '<div class="flash-message flash-success">Image linked to product!</div>';
        } else {
            $message = '<div class="flash-message flash-error">Image file not found</div>';
        }
    }
}

// Get all products and existing images
$products = $db->query("
    SELECT p.id, p.name, c.name as category, pi.image_path
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    ORDER BY p.id
")->fetchAll();

// Scan images folder
$uploadedImages = [];
if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    foreach ($files as $file) {
        if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $uploadedImages[] = $file;
        }
    }
}
$admin_title = "Image Manager";
include __DIR__ . '/../../../backend/includes/admin-header.php';
?>
<style>
    .image-manager { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding: 1rem 0; }
    .image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
    .image-card { border: 2px solid #ddd; border-radius: 8px; padding: 0.5rem; text-align: center; }
    .image-card img { max-width: 100%; height: 120px; object-fit: cover; border-radius: 4px; }
    .image-card .filename { font-size: 0.75rem; margin-top: 0.5rem; word-break: break-all; }
    .product-list table { width: 100%; }
    .product-list img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <a href="products.php" class="btn btn-primary">Manage Products</a>
</div>
<?php echo $message; ?>

<div class="image-manager">
    <div class="image-list">
        <h2>📁 Uploaded Images (<?= count($uploadedImages) ?>)</h2>
        <p style="color:#666; margin-bottom:1rem;">Drop your photos into: <code>frontend/assets/images/products/</code></p>
        <div class="image-grid">
            <?php foreach ($uploadedImages as $img): ?>
                <div class="image-card">
                    <img src="<?= BASE_URL ?>frontend/assets/images/products/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($img) ?>">
                    <div class="filename"><?= htmlspecialchars($img) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($uploadedImages)): ?>
                <p>No images yet. Download photos from your Facebook page and save them to the products folder.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="product-list">
        <h2>📦 Products (<?= count($products) ?>)</h2>
        <table>
            <thead>
                <tr><th>Image</th><th>Product</th><th>Category</th><th>Link Image</th></tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['image_path']): ?>
                            <img src="<?= BASE_URL ?><?= htmlspecialchars($p['image_path']) ?>" alt="">
                        <?php else: ?>
                            <span style="color:#999;">No image</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                    <td>
                        <form method="POST" style="display:flex; gap:0.5rem;">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <select name="image_name" required style="padding:0.25rem;">
                                <option value="">Choose image...</option>
                                <?php foreach ($uploadedImages as $img): ?>
                                    <option value="<?= htmlspecialchars($img) ?>"><?= htmlspecialchars($img) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Link</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../../backend/includes/admin-footer.php'; ?>
