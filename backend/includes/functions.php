<?php
// Helper Functions for Girls' Footwear E-Commerce

// XSS Prevention - sanitize output
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        // Only set cookie_secure when on HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', 1);
        }
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
}

// Get current session ID
function getCurrentSessionId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return session_id();
}

// Check if user is logged in
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
    startSecureSession();
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Check if current user is admin
function isAdmin() {
    startSecureSession();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Redirect to page
function redirect($page) {
    header("Location: " . $page);
    exit();
}

// Get flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate password strength (min 8 chars)
function isValidPassword($password) {
    return strlen($password) >= 8;
}

// Format price for display (Sri Lankan Rupees)
function formatPrice($price) {
    return 'Rs. ' . number_format((float)$price, 0);
}

// Generate slug from name
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Get all categories with age group subcategories
function getCategoriesWithAgeGroups($db) {
    $sql = "SELECT c.id, c.name, c.slug, c.age_group,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = TRUE) as product_count
            FROM categories c
            WHERE c.parent_id IS NULL
            ORDER BY c.name ASC";
    $stmt = $db->query($sql);
    $categories = $stmt->fetchAll();

    foreach ($categories as &$category) {
        $sql = "SELECT id, name, slug, age_group
                FROM categories
                WHERE parent_id = ? AND age_group != 'all'
                ORDER BY FIELD(age_group, 'toddler', 'kids', 'teens')";
        $stmt = $db->prepare($sql);
        $stmt->execute([$category['id']]);
        $category['subcategories'] = $stmt->fetchAll();
    }

    return $categories;
}

// Get category age group mapping for filtering
function getCategoryAgeGroups($category_id) {
    $db = getDBConnection();
    $sql = "SELECT c.age_group FROM categories c WHERE c.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if ($category && $category['age_group'] === 'all') {
        return ['toddler', 'kids', 'teens'];
    }
    return [$category['age_group']];
}

// Calculate cart item count
function getCartItemCount() {
    $db = getDBConnection();
    $session_id = getCurrentSessionId();
    $user_id = getCurrentUserId();

    if ($user_id) {
        $sql = "SELECT SUM(quantity) as count FROM cart_items WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $sql = "SELECT SUM(quantity) as count FROM cart_items WHERE session_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$session_id]);
    }

    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

// Get cart items
function getCartItems() {
    $db = getDBConnection();
    $session_id = getCurrentSessionId();
    $user_id = getCurrentUserId();

    if ($user_id) {
        $sql = "SELECT ci.*, p.name, p.price, p.slug, p.stock
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.user_id = ? AND p.active = TRUE";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        $sql = "SELECT ci.*, p.name, p.price, p.slug, p.stock
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.session_id = ? AND p.active = TRUE";
        $stmt = $db->prepare($sql);
        $stmt->execute([$session_id]);
    }

    return $stmt->fetchAll();
}

// Get cart total
function getCartTotal() {
    $items = getCartItems();
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}
