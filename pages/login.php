<?php
// Login Page
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: pages/admin/index.php");
    } else {
        header("Location: pages/index.php");
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // Transfer guest cart to user
                $sessionId = getCurrentSessionId();
                $stmt = $db->prepare("UPDATE cart_items SET user_id = ? WHERE session_id = ?");
                $stmt->execute([$user['id'], $sessionId]);

                setFlashMessage('success', 'Welcome back, ' . $user['name'] . '!');

                if ($user['role'] === 'admin') {
                    header("Location: pages/admin/index.php");
                } else {
                    header("Location: pages/index.php");
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$page_title = "Login - Girls' Footwear";
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <h2 class="auth-title">Welcome Back!</h2>
    <p style="text-align: center; color: var(--gray); margin-bottom: 30px;">Sign in to your account</p>

    <?php if (!empty($error)): ?>
        <div class="flash-message flash-error">
            <?php echo escape($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required
                   value="<?php echo escape($email); ?>"
                   placeholder="your@email.com">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   placeholder="Your password">
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>

    <div class="auth-footer">
        <p>Don't have an account? <a href="register.php">Sign up</a></p>
        <p style="margin-top: 10px;"><a href="forgot-password.php">Forgot password?</a></p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>