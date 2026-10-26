<?php
// Auth API - Handles AJAX auth operations
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$action = $_GET['action'] ?? '';

$response = ['success' => false];

switch ($action) {
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $email = sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            $response['message'] = 'Email and password are required';
            break;
        }

        if (!isValidEmail($email)) {
            $response['message'] = 'Invalid email format';
            break;
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Transfer guest cart to user
            $sessionId = getCurrentSessionId();
            $db->prepare("UPDATE cart_items SET user_id = ? WHERE session_id = ?")->execute([$user['id'], $sessionId]);

            $response['success'] = true;
            $response['message'] = 'Login successful';
            $response['redirect'] = $user['role'] === 'admin' ? 'pages/admin/index.php' : 'pages/index.php';
        } else {
            $response['message'] = 'Invalid email or password';
        }
        break;

    case 'register':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $name = sanitizeInput($input['name'] ?? '');
        $email = sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $phone = sanitizeInput($input['phone'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $response['message'] = 'Name, email, and password are required';
            break;
        }

        if (!isValidEmail($email)) {
            $response['message'] = 'Invalid email format';
            break;
        }

        if (!isValidPassword($password)) {
            $response['message'] = 'Password must be at least 8 characters';
            break;
        }

        // Check email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $response['message'] = 'Email already registered';
            break;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, 'customer')";
        $stmt = $db->prepare($sql);
        if ($stmt->execute([$name, $email, $hashedPassword, $phone])) {
            $response['success'] = true;
            $response['message'] = 'Account created successfully';
            $response['redirect'] = 'pages/login.php';
        } else {
            $response['message'] = 'Registration failed';
        }
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
        $response['success'] = true;
        $response['message'] = 'Logged out';
        $response['redirect'] = 'pages/login.php';
        break;

    case 'status':
        if (isLoggedIn()) {
            $response['success'] = true;
            $response['user'] = [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role'],
            ];
        } else {
            $response['success'] = false;
            $response['message'] = 'Not logged in';
        }
        break;

    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);
