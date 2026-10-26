<?php
// Products API - Handles AJAX product filtering and operations
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$action = $_GET['action'] ?? '';

$response = ['success' => false];

switch ($action) {
    case 'filter':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $category = sanitizeInput($input['category'] ?? '');
        $ageGroup = sanitizeInput($input['age_group'] ?? '');
        $priceMin = (float)($input['price_min'] ?? 0);
        $priceMax = (float)($input['price_max'] ?? 0);
        $search = sanitizeInput($input['search'] ?? '');
        $sort = sanitizeInput($input['sort'] ?? 'featured');

        $where = [];
        $params = [];

        if ($category) {
            $stmt = $db->prepare("SELECT id, age_group FROM categories WHERE slug = ?");
            $stmt->execute([$category]);
            $cat = $stmt->fetch();

            if ($cat) {
                if ($cat['age_group'] === 'all') {
                    $stmt = $db->prepare("SELECT id FROM categories WHERE parent_id = ?");
                    $stmt->execute([$cat['id']]);
                    $subIds = array_column($stmt->fetchAll(), 'id');
                    array_unshift($subIds, $cat['id']);
                    $placeholders = str_repeat('?,', count($subIds) - 1) . '?';
                    $where[] = "p.category_id IN ($placeholders)";
                    $params = array_merge($params, $subIds);
                } else {
                    $where[] = "p.category_id = ?";
                    $params[] = $cat['id'];
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

        $where[] = "p.active = TRUE";
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $orderBy = match($sort) {
            'price-low' => 'p.price ASC',
            'price-high' => 'p.price DESC',
            'name' => 'p.name ASC',
            'newest' => 'p.created_at DESC',
            default => 'p.featured DESC, p.created_at DESC',
        };

        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug
                FROM products p
                JOIN categories c ON p.category_id = c.id
                $whereSql
                ORDER BY $orderBy
                LIMIT 24";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        // Build HTML
        $html = '';
        foreach ($products as $product) {
            $html .= '<div class="product-card">';
            $html .= '<a href="product.php?slug=' . escape($product['slug']) . '" class="product-image">';
            if ($product['featured']) {
                $html .= '<span class="product-badge">Featured</span>';
            }
            $html .= '<i class="fas fa-shoe-prints"></i>';
            $html .= '</a>';
            $html .= '<div class="product-info">';
            $html .= '<a href="product.php?slug=' . escape($product['slug']) . '" class="product-name">';
            $html .= escape($product['name']);
            $html .= '</a>';
            $html .= '<div class="product-price">$' . number_format($product['price'], 2) . '</div>';
            $html .= '<div class="product-actions">';
            $html .= '<a href="product.php?slug=' . escape($product['slug']) . '" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View</a>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        if (empty($products)) {
            $html = '<div class="empty-state"><div class="empty-state-icon">🔍</div><h3>No products found</h3><p>Try adjusting your filters.</p></div>';
        }

        $response['success'] = true;
        $response['html'] = $html;
        $response['count'] = count($products);
        break;

    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = (int)($input['product_id'] ?? 0);

        if (!isAdmin()) {
            $response['message'] = 'Unauthorized';
            break;
        }

        if ($product_id <= 0) {
            $response['message'] = 'Invalid product';
            break;
        }

        $db->beginTransaction();
        $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);
        $db->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$product_id]);
        $db->prepare("DELETE FROM cart_items WHERE product_id = ?")->execute([$product_id]);
        $db->prepare("DELETE FROM reviews WHERE product_id = ?")->execute([$product_id]);
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);
        $db->commit();

        $response['success'] = true;
        $response['message'] = 'Product deleted';
        break;

    case 'detail':
        $slug = sanitizeInput($_GET['slug'] ?? '');
        if (empty($slug)) {
            $response['message'] = 'Invalid slug';
            break;
        }

        $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.slug = ?");
        $stmt->execute([$slug]);
        $product = $stmt->fetch();

        if ($product) {
            $response['success'] = true;
            $response['product'] = $product;
        } else {
            $response['message'] = 'Product not found';
        }
        break;

    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
