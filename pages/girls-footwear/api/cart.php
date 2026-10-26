<?php
// Cart API - Handles AJAX cart operations
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$action = $_GET['action'] ?? '';
$session_id = getCurrentSessionId();
$user_id = getCurrentUserId();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'add':
        // Get POST data
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = (int)($input['product_id'] ?? 0);
        $size = sanitizeInput($input['size'] ?? '');
        $quantity = (int)($input['quantity'] ?? 1);
        $color = sanitizeInput($input['color'] ?? '');

        if ($product_id <= 0) {
            $response['message'] = 'Invalid product';
            break;
        }

        if (empty($size)) {
            $response['message'] = 'Please select a size';
            break;
        }

        if ($quantity < 1) $quantity = 1;
        if ($quantity > 10) $quantity = 10;

        // Check product exists and has stock
        $stmt = $db->prepare("SELECT id, stock, active FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product || !$product['active']) {
            $response['message'] = 'Product not found';
            break;
        }

        if ($product['stock'] < $quantity) {
            $response['message'] = 'Not enough stock available';
            break;
        }

        // Check if item already in cart
        if ($user_id) {
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND size = ?");
            $stmt->execute([$user_id, $product_id, $size]);
        } else {
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE session_id = ? AND product_id = ? AND size = ?");
            $stmt->execute([$session_id, $product_id, $size]);
        }
        $existing = $stmt->fetch();

        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            if ($newQuantity > 10) $newQuantity = 10;
            if ($newQuantity > $product['stock']) $newQuantity = $product['stock'];

            if ($user_id) {
                $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $existing['id']]);
            } else {
                $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $existing['id']]);
            }
        } else {
            // Insert new item
            if ($user_id) {
                $stmt = $db->prepare("INSERT INTO cart_items (user_id, session_id, product_id, quantity, size, color) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $session_id, $product_id, $quantity, $size, $color]);
            } else {
                $stmt = $db->prepare("INSERT INTO cart_items (session_id, product_id, quantity, size, color) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$session_id, $product_id, $quantity, $size, $color]);
            }
        }

        $response['success'] = true;
        $response['message'] = 'Added to cart';
        break;

    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $cart_item_id = (int)($input['cart_item_id'] ?? 0);
        $quantity = (int)($input['quantity'] ?? 1);

        if ($cart_item_id <= 0) {
            $response['message'] = 'Invalid cart item';
            break;
        }

        if ($quantity < 1) $quantity = 1;
        if ($quantity > 10) $quantity = 10;

        // Verify ownership
        if ($user_id) {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cart_item_id, $user_id]);
        } else {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND session_id = ?");
            $stmt->execute([$quantity, $cart_item_id, $session_id]);
        }

        // Calculate new total
        $items = getCartItems();
        $total = 0;
        $itemTotal = 0;
        foreach ($items as $item) {
            $itemTotalCalc = $item['price'] * $item['quantity'];
            $total += $itemTotalCalc;
            if ($item['id'] == $cart_item_id) {
                $itemTotal = $itemTotalCalc;
            }
        }

        $response['success'] = true;
        $response['total'] = $total;
        $response['item_total'] = $itemTotal;
        break;

    case 'remove':
        $input = json_decode(file_get_contents('php://input'), true);
        $cart_item_id = (int)($input['cart_item_id'] ?? 0);

        if ($cart_item_id <= 0) {
            $response['message'] = 'Invalid cart item';
            break;
        }

        if ($user_id) {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_item_id, $user_id]);
        } else {
            $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND session_id = ?");
            $stmt->execute([$cart_item_id, $session_id]);
        }

        // Calculate new total
        $items = getCartItems();
        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        $response['success'] = true;
        $response['total'] = $total;
        break;

    case 'count':
        $response['success'] = true;
        $response['count'] = getCartItemCount();
        break;

    case 'get':
        $items = getCartItems();
        $response['success'] = true;
        $response['items'] = $items;
        $response['total'] = getCartTotal();
        break;

    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
