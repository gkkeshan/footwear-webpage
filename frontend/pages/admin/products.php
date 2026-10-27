<?php
/**
 * Admin Products Management Page
 * Handles: listing, creating (with image upload), editing, and deleting products.
 * Security: Admin-only, CSRF-protected, PDO prepared statements for all DB queries.
 */
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

// --- Access Control: Redirect non-admins ---
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

// Directory where product images are stored (relative to this script)
$uploadDir = __DIR__ . '/../../assets/images/products/';
$uploadDirWeb = '../../assets/images/products/'; // Web-accessible path for <img> tags

// --- Handle form submissions (POST) ---
$action  = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token. Please refresh and try again.</div>';
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price'] ?? 0);
        $stock       = (int)($_POST['stock'] ?? 0);
        $sizes       = $_POST['sizes'] ?? [];
        $colors      = $_POST['colors'] ?? [];
        $featured    = isset($_POST['featured']) ? 1 : 0;
        $active      = isset($_POST['active']) ? 1 : 0;

        // Validate required fields
        if (empty($name) || $category_id === 0 || $price <= 0 || $stock < 0) {
            $message = '<div class="flash-message flash-error">Please fill in all required fields (Name, Category, Price, Stock).</div>';
        } else {
            $sizesJson  = json_encode($sizes);
            $colorsJson = json_encode($colors);

            // --- Handle image upload ---
            $uploadedImagePath = null;
            if (!empty($_FILES['product_image']['name'])) {
                $file      = $_FILES['product_image'];
                $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

                // Validate file type and size (max 5MB)
                if (!in_array($ext, $allowedExt)) {
                    $message = '<div class="flash-message flash-error">Invalid image type. Allowed: JPG, PNG, WEBP.</div>';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $message = '<div class="flash-message flash-error">Image must be smaller than 5MB.</div>';
                } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                    $message = '<div class="flash-message flash-error">Upload failed (error code: ' . $file['error'] . ').</div>';
                } else {
                    // Generate a unique, safe filename (timestamp + random + slug)
                    $safeName  = preg_replace('/[^a-z0-9-]/', '-', strtolower($name));
                    $filename  = time() . '-' . $safeName . '.' . $ext;
                    $destPath  = $uploadDir . $filename;

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        // Path stored in DB (relative to frontend root)
                        $uploadedImagePath = 'assets/images/products/' . $filename;
                    } else {
                        $message = '<div class="flash-message flash-error">Failed to save image. Check folder permissions.</div>';
                    }
                }
            }

            // Only proceed with DB insert/update if no error message was set
            if (empty($message)) {
                $formAction = $_POST['action'] ?? $action;
                if ($formAction === 'create' || $formAction === 'add') {
                    // Generate slug from name
                    $slug = generateSlug($name);

                    // Insert new product
                    $sql  = "INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$category_id, $name, $slug, $description, $price, $stock, $sizesJson, $colorsJson, $featured, $active]);

                    $newProductId = (int)$db->lastInsertId();

                    // Insert uploaded image into product_images table
                    if ($uploadedImagePath) {
                        $imgStmt = $db->prepare(
                            "INSERT INTO product_images (product_id, image_path, is_primary, sort_order)
                             VALUES (?, ?, 1, 1)"
                        );
                        $imgStmt->execute([$newProductId, $uploadedImagePath]);
                    }

                    $message = '<div class="flash-message flash-success">✓ Product created successfully' . ($uploadedImagePath ? ' with image!' : '. You can add an image from the Images page.') . '</div>';
                    $action  = 'list'; // Return to list view after create

                } elseif (($formAction === 'edit' || $action === 'edit') && $id > 0) {
                    // Update product details
                    $sql  = "UPDATE products SET category_id=?, name=?, description=?, price=?, stock=?, sizes=?, colors=?, featured=?, active=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$category_id, $name, $description, $price, $stock, $sizesJson, $colorsJson, $featured, $active, $id]);

                    // Replace primary image if a new one was uploaded
                    if ($uploadedImagePath) {
                        // Delete existing primary image record
                        $db->prepare("DELETE FROM product_images WHERE product_id = ? AND is_primary = 1")->execute([$id]);

                        // Insert new primary image
                        $imgStmt = $db->prepare(
                            "INSERT INTO product_images (product_id, image_path, is_primary, sort_order)
                             VALUES (?, ?, 1, 1)"
                        );
                        $imgStmt->execute([$id, $uploadedImagePath]);
                    }

                    $message = '<div class="flash-message flash-success">✓ Product updated successfully.</div>';
                    $action  = 'list';
                }
            }
        }
    }
}

// --- Handle deletion (GET with CSRF) ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token.</div>';
    } else {
        $db->beginTransaction();
        try {
            // Get image paths to delete files from disk
            $imgStmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $imgStmt->execute([$id]);
            $imgRows = $imgStmt->fetchAll();
            foreach ($imgRows as $imgRow) {
                $filePath = __DIR__ . '/../../' . $imgRow['image_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath); // Delete physical file
                }
            }

            $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM cart_items WHERE product_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM reviews WHERE product_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $message = '<div class="flash-message flash-error">Error deleting product. Please try again.</div>';
        }
        if (empty($message)) {
            header("Location: products.php");
            exit;
        }
    }
}

// --- Load products list ---
$filter = $_GET['filter'] ?? '';
if ($filter === 'inventory' || $filter === 'low_stock') {
    $products = $db->query("SELECT p.*, c.name as category_name, pi.image_path
                            FROM products p
                            JOIN categories c ON p.category_id = c.id
                            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                            WHERE p.stock < 10 ORDER BY p.stock ASC")->fetchAll();
    $admin_title = "Inventory & Stock Management";
} else {
    $products = $db->query("SELECT p.*, c.name as category_name, pi.image_path
                            FROM products p
                            JOIN categories c ON p.category_id = c.id
                            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                            ORDER BY p.created_at DESC")->fetchAll();
    $admin_title = "Manage Products";
}

// Load categories for the dropdown
$categories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

include __DIR__ . '/../../../backend/includes/admin-header.php';
?>

<?php if (!empty($message)) echo $message; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h1>Products Management</h1>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <a href="?action=add" class="btn btn-primary">Add New Product</a>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php
    // Set defaults for add form, or load existing product for edit
    $product = [
        'id' => '', 'name' => '', 'description' => '', 'category_id' => '',
        'price' => '', 'stock' => '', 'sizes' => [], 'colors' => [],
        'featured' => 0, 'active' => 1, 'image_path' => null
    ];
    if ($action === 'edit' && isset($_GET['id'])) {
        $editId  = (int)$_GET['id'];
        $stmt    = $db->prepare("SELECT p.*, pi.image_path FROM products p
                                 LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                                 WHERE p.id = ?");
        $stmt->execute([$editId]);
        $product = $stmt->fetch();
        $product['sizes']  = json_decode($product['sizes'], true) ?? [];
        $product['colors'] = json_decode($product['colors'], true) ?? [];
    }
    ?>

    <div class="admin-table">
        <h2 style="padding: 20px 20px 0; margin: 0;">
            <?php echo $action === 'add' ? '➕ Add New Product' : '✏️ Edit Product'; ?>
        </h2>

        <!-- enctype="multipart/form-data" is REQUIRED for file uploads -->
        <form method="POST" enctype="multipart/form-data" style="padding: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action"     value="<?php echo $action === 'add' ? 'create' : 'edit'; ?>">
            <input type="hidden" name="id"         value="<?php echo $product['id']; ?>">

            <!-- Row 1: Name & Category -->
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" required
                           placeholder="e.g. Cotton Candy Cloud Sneakers"
                           value="<?php echo escape($product['name']); ?>">
                </div>
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Description -->
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"
                          placeholder="Describe the product style, material, and features..."><?php echo escape($product['description']); ?></textarea>
            </div>

            <!-- Row 3: Price & Stock -->
            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price (Rs.) *</label>
                    <input type="number" id="price" name="price" step="1" min="100" required
                           placeholder="e.g. 2900"
                           value="<?php echo escape($product['price']); ?>">
                </div>
                <div class="form-group">
                    <label for="stock">Stock Quantity *</label>
                    <input type="number" id="stock" name="stock" min="0" required
                           placeholder="e.g. 30"
                           value="<?php echo escape($product['stock']); ?>">
                </div>
            </div>

            <!-- ===================================================
                 IMAGE UPLOAD SECTION (New feature)
                 =================================================== -->
            <div class="form-group" style="margin-top: 10px;">
                <label>Product Image <?php echo $action === 'add' ? '' : '(Upload to replace current)'; ?></label>

                <!-- Show current image when editing -->
                <?php if ($action === 'edit' && !empty($product['image_path'])): ?>
                    <div style="margin-bottom: 10px;">
                        <p style="font-size: 0.85rem; color: #666; margin-bottom: 6px;">Current image:</p>
                        <img id="current-image"
                             src="<?php echo $uploadDirWeb . basename($product['image_path']); ?>"
                             alt="Current product image"
                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #eee;">
                    </div>
                <?php endif; ?>

                <!-- File input with JS preview -->
                <div style="display: flex; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
                    <div>
                        <input type="file"
                               id="product_image"
                               name="product_image"
                               accept="image/jpeg,image/png,image/webp"
                               onchange="previewImage(event)"
                               style="padding: 8px 0;">
                        <p style="font-size: 0.8rem; color: #888; margin-top: 4px;">
                            Accepted: JPG, PNG, WEBP &bull; Max size: 5MB
                        </p>
                    </div>

                    <!-- Live preview box -->
                    <div id="image-preview-wrap" style="display:none;">
                        <p style="font-size: 0.85rem; color: #666; margin-bottom: 6px;">Preview:</p>
                        <img id="image-preview"
                             src=""
                             alt="Preview"
                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #F0679D;">
                    </div>
                </div>
            </div>

            <!-- Sizes -->
            <div class="form-group">
                <label>Available Sizes</label>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px;">
                    <?php
                    $allSizes = ["3","4","5","6","7","8","9","10","11","12","13","1","2","3","4","5","6"];
                    $uniqueSizes = array_unique($allSizes);
                    foreach ($uniqueSizes as $size): ?>
                        <label style="display: inline-flex; align-items: center; gap: 4px; background: #f5f5f5;
                                      padding: 4px 10px; border-radius: 20px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="sizes[]" value="<?php echo $size; ?>"
                                   <?php echo in_array($size, $product['sizes']) ? 'checked' : ''; ?>>
                            <?php echo $size; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Colors -->
            <div class="form-group">
                <label>Available Colors</label>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px;">
                    <?php
                    $allColors = [
                        "Pink" => "#ffb6c1", "White" => "#e0e0e0", "Purple" => "#8b5fbf",
                        "Black" => "#333333", "Gold" => "#ffd700", "Brown" => "#8b4513",
                        "Silver" => "#c0c0c0", "Navy" => "#000080", "Tan" => "#d2b48c",
                        "Sky Blue" => "#87ceeb", "Lavender" => "#e6e6fa", "Mint" => "#98ff98",
                        "Multi" => "linear-gradient(135deg, #f00, #ff0, #0f0, #00f, #f0f)"
                    ];
                    foreach ($allColors as $colorName => $colorHex): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
                                      font-size: 0.9rem; padding: 4px 10px; background: #f5f5f5; border-radius: 20px;">
                            <input type="checkbox" name="colors[]" value="<?php echo $colorName; ?>"
                                   <?php echo in_array($colorName, $product['colors']) ? 'checked' : ''; ?>>
                            <span style="width: 16px; height: 16px; border-radius: 50%; display: inline-block;
                                         background: <?php echo $colorHex; ?>; border: 1px solid #ccc;"></span>
                            <?php echo $colorName; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Toggles: Featured & Active -->
            <div class="form-row" style="margin-top: 10px;">
                <div class="form-group">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="featured" value="1"
                               <?php echo $product['featured'] ? 'checked' : ''; ?>>
                        ⭐ Featured Product (shown on homepage)
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="active" value="1"
                               <?php echo $product['active'] ? 'checked' : ''; ?>>
                        ✅ Active (visible on site)
                    </label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <?php echo $action === 'add' ? '➕ Save Product' : '💾 Update Product'; ?>
                </button>
                <a href="products.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- ===================== Product List ===================== -->
    <div class="admin-table">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No products found</h3>
                <p>Click "Add New Product" to get started.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['image_path'])): ?>
                                    <!-- Show actual product image from DB -->
                                    <img src="<?php echo '../../' . escape($p['image_path']); ?>"
                                         width="55" height="55"
                                         style="object-fit: cover; border-radius: 6px; border: 1px solid #eee;"
                                         alt="<?php echo escape($p['name']); ?>">
                                <?php else: ?>
                                    <!-- Placeholder when no image is uploaded -->
                                    <div style="width:55px; height:55px; background:#f0f0f0; border-radius:6px;
                                                display:flex; align-items:center; justify-content:center; font-size:1.5rem;">
                                        👟
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($p['name']); ?></td>
                            <td><?php echo escape($p['category_name']); ?></td>
                            <td><?php echo formatPrice($p['price']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $p['stock'] < 5 ? 'danger' : ($p['stock'] < 10 ? 'warning' : 'success'); ?>">
                                    <?php echo $p['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $p['featured'] ? 'success' : 'gray'; ?>">
                                    <?php echo $p['featured'] ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=edit&id=<?php echo $p['id']; ?>"
                                   class="btn btn-sm btn-outline" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $p['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>"
                                   class="btn btn-sm btn-outline"
                                   style="color: #e74c3c;"
                                   onclick="return confirm('Permanently delete this product and its image?')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
/**
 * previewImage()
 * Shows a live client-side thumbnail preview as soon as a file is selected,
 * before the form is submitted — no server round-trip required.
 */
function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (e) {
        const preview = document.getElementById('image-preview');
        const wrap    = document.getElementById('image-preview-wrap');
        preview.src   = e.target.result;
        wrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
}
</script>

<?php include __DIR__ . '/../../../backend/includes/admin-footer.php'; ?>