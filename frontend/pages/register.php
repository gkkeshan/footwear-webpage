<?php
// Register Page
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

$db = getDBConnection();

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/index.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = sanitizeInput($_POST['phone'] ?? '');

        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!isValidPassword($password)) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $_POST['confirm_password'] ?? '') {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                // Create new user
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $sql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$name, $email, $hashedPassword]);

                if ($result) {
                    setFlashMessage('success', 'Account created successfully! Please login.');
                    header("Location: login.php");
                    exit;
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
        }
    }
}

$page_title = "Sign Up - Girls' Footwear";
include __DIR__ . '/../../backend/includes/header.php';
?>

<div class="auth-container">
    <h2 class="auth-title">Create an Account</h2>
    <p style="text-align: center; color: var(--gray); margin-bottom: 30px;">Join our community of happy customers</p>

    <?php if (!empty($error)): ?>
        <div class="flash-message flash-error">
            <?php echo escape($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="flash-message flash-success">
            <?php echo escape($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required
                   placeholder="Your full name">
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required
                   placeholder="your@email.com">
        </div>

        <div class="form-group">
            <label for="phone">Phone (optional)</label>
            <input type="tel" id="phone" name="phone" placeholder="(555) 123-4567">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   placeholder="Create a password">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required
                   placeholder="Confirm your password">
        </div>

        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>

    <div class="auth-footer">
        <p>Already have an account? <a href="login.php">Sign in</a></p>
    </div>
</div>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>