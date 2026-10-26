<?php
// Admin Categories Management
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();

$message = '';
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token</div>';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $slug = generateSlug($name);
        $ageGroup = $_POST['age_group'] ?? 'all';
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if (empty($name)) {
            $message = '<div class="flash-message flash-error">Category name is required</div>';
        } else {
            if ($action === 'create') {
                $sql = "INSERT INTO categories (name, slug, parent_id, age_group) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $slug, $parentId, $ageGroup]);
                $message = '<div class="flash-message flash-success">Category added successfully</div>';
            } elseif ($action === 'edit') {
                $sql = "UPDATE categories SET name = ?, slug = ?, parent_id = ?, age_group = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $slug, $parentId, $ageGroup, $id]);
                $message = '<div class="flash-message flash-success">Category updated successfully</div>';
            }
        }
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    if (!validateCSRFToken($_GET['csrf_token'] ?? '')) {
        $message = '<div class="flash-message flash-error">Invalid security token</div>';
    } else {
        $id = (int)$_GET['id'];
        $db->prepare("DELETE FROM categories WHERE id = ? OR parent_id = ?")->execute([$id, $id]);
        header("Location: categories.php");
        exit;
    }
}

$categories = $db->query("
    SELECT c1.*, COUNT(c2.id) as subcategory_count
    FROM categories c1
    LEFT JOIN categories c2 ON c1.id = c2.parent_id
    GROUP BY c1.id
    ORDER BY c1.parent_id, c1.age_group
")->fetchAll();

$mainCategories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL")->fetchAll();

$admin_title = "Categories";
include __DIR__ . '/../../includes/header.php';

echo $message;
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h1>Categories Management</h1>
    <a href="?action=add" class="btn btn-primary">Add New Category</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php
    $category = ['id' => '', 'name' => '', 'parent_id' => '', 'age_group' => 'all'];
    if ($action === 'edit' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
    }
    ?>
    <div class="admin-table">
        <h2 style="padding: 20px; margin: 0;">
            <?php echo $action === 'add' ? 'Add New Category' : 'Edit Category'; ?>
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">

            <div class="form-group">
                <label for="name">Category Name *</label>
                <input type="text" id="name" name="name" required value="<?php echo escape($category['name']); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="parent_id">Parent Category</label>
                    <select id="parent_id" name="parent_id">
                        <option value="">Main Category</option>
                        <?php foreach ($mainCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category['parent_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="age_group">Age Group</label>
                    <select id="age_group" name="age_group">
                        <option value="all" <?php echo $category['age_group'] === 'all' ? 'selected' : ''; ?>>All Ages</option>
                        <option value="toddler" <?php echo $category['age_group'] === 'toddler' ? 'selected' : ''; ?>>Toddler</option>
                        <option value="kids" <?php echo $category['age_group'] === 'kids' ? 'selected' : ''; ?>>Kids</option>
                        <option value="teens" <?php echo $category['age_group'] === 'teens' ? 'selected' : ''; ?>>Teens</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Category</button>
            <a href="categories.php" class="btn btn-outline">Cancel</a>
        </form>
    </div>
<?php else: ?>
    <div class="admin-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Parent</th>
                    <th>Age Group</th>
                    <th>Subcategories</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <strong><?php echo escape($cat['name']); ?></strong>
                            <?php if (!$cat['parent_id']): ?>
                                <br><small style="color: var(--primary);">Main Category</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo escape($cat['slug']); ?></td>
                        <td>
                            <?php
                            if ($cat['parent_id']) {
                                $parent = $db->prepare("SELECT name FROM categories WHERE id = ?");
                                $parent->execute([$cat['parent_id']]);
                                echo escape($parent->fetchColumn());
                            } else {
                                echo '<span style="color: var(--gray);">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo ucfirst($cat['age_group']); ?></span>
                        </td>
                        <td><?php echo $cat['subcategory_count']; ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                            <a href="?action=delete&id=<?php echo $cat['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" class="btn btn-sm btn-outline" onclick="return confirm('Delete this category? This may affect products.')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>