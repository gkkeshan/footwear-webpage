<?php
// Admin Products Management
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token</div>';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $sizes = $_POST['sizes'] ?? [];
        $colors = $_POST['colors'] ?? [];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;

        if ($action === 'create' || $action === 'edit') {
            if (empty($name) || empty($description) || $category_id === 0 || $price <= 0 || $stock < 0) {
                $message = '<div class="flash-message flash-error">Please fill in all required fields</div>';
            } else {
                $sizesJson = json_encode($sizes);
                $colorsJson = json_encode($colors);

                if ($action === 'create') {
                    $sql = "INSERT INTO products (category_id, name, description, price, stock, sizes, colors, featured, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$category_id, $name, $description, $price, $stock, $sizesJson, $colorsJson, $featured, $active]);
                    $message = '<div class="flash-message flash-success">Product created successfully</div>';
                } else {
                    $sql = "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, sizes = ?, colors = ?, featured = ?, active = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$category_id, $name, $description, $price, $stock, $sizesJson, $colorsJson, $featured, $active, $id]);
                    $message = '<div class="flash-message flash-success">Product updated successfully</div>';
                }
            }
        }
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token</div>';
    } else {
        $db->beginTransaction();
        $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM cart_items WHERE product_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM reviews WHERE product_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $db->commit();
        header("Location: products.php");
        exit;
    }
}

// Get products
$filter = $_GET['filter'] ?? '';
if ($filter === 'inventory' || $filter === 'low_stock') {
    $products = $db->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.stock < 10 ORDER BY p.stock ASC")->fetchAll();
    $admin_title = "Inventory & Stock Management";
} else {
    $products = $db->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC")->fetchAll();
    $admin_title = "Manage Products";
}

// Get categories for dropdown
$categories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

include __DIR__ . '/../../../backend/includes/admin-header.php';
?>

<?php if (isset($message)) echo $message; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h1>Products Management</h1>
    <a href="?action=add" class="btn btn-primary">Add New Product</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php
    $product = [
        'id' => '',
        'name' => '',
        'description' => '',
        'category_id' => '',
        'price' => '',
        'stock' => '',
        'sizes' => [],
        'colors' => [],
        'featured' => 0,
        'active' => 1
    ];
    if ($action === 'edit' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        $product['sizes'] = json_decode($product['sizes'], true) ?? [];
        $product['colors'] = json_decode($product['colors'], true) ?? [];
    }
    ?>
    <div class="admin-table">
        <h2 style="padding: 20px; margin: 0;">
            <?php echo $action === 'add' ? 'Add New Product' : 'Edit Product'; ?>
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" required value="<?php echo escape($product['name']); ?>">
                </div>
                <div class="form-group">
                    <label for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo escape($product['description']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price (Rs.) *</label>
                    <input type="number" id="price" name="price" step="1" min="1000" max="5000" required value="<?php echo escape($product['price']); ?>">
                </div>
                <div class="form-group">
                    <label for="stock">Stock Quantity *</label>
                    <input type="number" id="stock" name="stock" min="0" required value="<?php echo escape($product['stock']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Available Sizes</label>
                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                    <?php
                    $allSizes = ["3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "1", "2", "3", "4", "5", "6"];
                    foreach ($allSizes as $size): ?>
                        <label style="margin: 5px 0; display: inline-flex; align-items: center;">
                            <input type="checkbox" name="sizes[]" value="<?php echo $size; ?>" <?php echo in_array($size, $product['sizes']) ? 'checked' : ''; ?>>
                            <span style="margin-left: 5px;"><?php echo $size; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Available Colors</label>
                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                    <?php
                    $allColors = ["Pink", "White", "Purple", "Black", "Gold", "Brown", "Silver", "Navy", "Tan"];
                    foreach ($allColors as $color): ?>
                        <div style="margin: 5px 0; display: inline-flex; align-items: center;">
                            <input type="checkbox" name="colors[]" value="<?php echo $color; ?>" <?php echo in_array($color, $product['colors']) ? 'checked' : ''; ?>>
                            <div style="width: 20px; height: 20px; margin-left: 5px; border-radius: 50%; background: <?php echo getColorValue($color); ?>;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="featured" value="1" <?php echo $product['featured'] ? 'checked' : ''; ?>>
                        Featured Product
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" value="1" <?php echo $product['active'] ? 'checked' : ''; ?>>
                        Active (visible on site)
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="products.php" class="btn btn-outline">Cancel</a>
        </form>
    </div>
<?php else: ?>
    <!-- Product List -->
    <div class="admin-table">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h3>No products found</h3>
                <p>Add your first product to get started.</p>
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
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><img src="uploads/products/placeholder.jpg" width="50" height="50" alt="Product"></td>
                            <td><?php echo escape($product['name']); ?></td>
                            <td><?php echo escape($product['category_name']); ?></td>
                            <td><?php echo formatPrice($product['price']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $product['stock'] < 5 ? 'danger' : ($product['stock'] < 10 ? 'warning' : 'success'); ?>">
                                    <?php echo $product['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $product['featured'] ? 'success' : 'gray'; ?>">
                                    <?php echo $product['featured'] ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                                <a href="?action=delete&id=<?php echo $product['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" class="btn btn-sm btn-outline" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Update selected sizes/colors display
function getColorValue(colorName) {
    const colorMap = {
        'Pink': '#ffb6c1',
        'White': '#ffffff',
        'Purple': '#8b5fbf',
        'Black': '#000000',
        'Gold': '#ffd700',
        'Brown': '#8b4513',
        'Silver': '#c0c0c0',
        'Navy': '#000080',
        'Tan': '#d2b48c'
    };
    return colorMap[colorName] || '#808080';
}
</script>

<?php include __DIR__ . '/../../../backend/includes/admin-footer.php'; ?>