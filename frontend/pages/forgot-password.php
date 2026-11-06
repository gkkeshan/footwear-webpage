<?php
// Forgot / Reset Password Page
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/includes/functions.php';

startSecureSession();

// If user is already logged in, redirect them to home or admin
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/index.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

// Allow user to reset flow and enter a different email
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_user_name']);
    header("Location: forgot-password.php");
    exit;
}

$db = getDBConnection();
$message = '';
$error = '';
$step = 1; // Step 1: Request reset, Step 2: New password
$email = '';

// Check if user has an active verified reset session
if (!empty($_SESSION['reset_email'])) {
    $step = 2;
    $email = $_SESSION['reset_email'];
    $userName = $_SESSION['reset_user_name'] ?? 'User';
    $message = 'Verification successful for ' . $userName . '. Please set your new password below.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'request_reset') {
            $email = sanitizeInput($_POST['email'] ?? '');
            if (empty($email) || !isValidEmail($email)) {
                $error = 'Please enter a valid email address.';
            } else {
                $stmt = $db->prepare("SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(?)");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Save verified user in session for step 2
                    $_SESSION['reset_email'] = $user['email'];
                    $_SESSION['reset_user_name'] = $user['name'];
                    $step = 2;
                    $email = $user['email'];
                    $message = 'Verification successful for ' . $user['name'] . '. Please set your new password below.';
                } else {
                    $error = 'No account found with this email address.';
                }
            }
        } elseif ($action === 'set_new_password') {
            $resetEmail = $_SESSION['reset_email'] ?? sanitizeInput($_POST['email'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($resetEmail)) {
                $error = 'Session expired. Please enter your email again.';
                $step = 1;
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
                $step = 2;
                $email = $resetEmail;
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
                $step = 2;
                $email = $resetEmail;
            } else {
                $newHash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE LOWER(email) = LOWER(?)");
                $stmt->execute([$newHash, $resetEmail]);
                
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_name']);
                
                setFlashMessage('success', 'Your password has been successfully reset! You can now sign in with your new password.');
                header("Location: login.php");
                exit;
            }
        }
    }
}

$page_title = "Reset Password - Girls' Footwear";
include __DIR__ . '/../../backend/includes/header.php';
?>

<div class="auth-container">
    <h2 class="auth-title"><i class="fas fa-key" style="color: var(--primary);"></i> Reset Password</h2>
    <p style="text-align: center; color: var(--gray); margin-bottom: 25px;">
        <?php echo $step === 1 ? 'Enter your registered email to reset your account password.' : 'Enter and confirm your new password.'; ?>
    </p>

    <?php if (!empty($message)): ?>
        <div class="flash-message flash-success" style="margin-bottom: 20px;">
            <i class="fas fa-info-circle"></i> <?php echo escape($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="flash-message flash-error" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="request_reset">

            <div class="form-group">
                <label for="email">Registered Email Address</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo escape($email); ?>"
                       placeholder="e.g. emma@example.com">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-arrow-right"></i> Verify Email & Reset
            </button>
        </form>
    <?php else: ?>
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="set_new_password">
            <input type="hidden" name="email" value="<?php echo escape($email); ?>">

            <div class="form-group">
                <label for="new_password">New Password (minimum 6 characters)</label>
                <input type="password" id="new_password" name="new_password" required minlength="6"
                       placeholder="Enter new password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                       placeholder="Confirm new password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-check-circle"></i> Update Password
            </button>
            <div style="text-align: center; margin-top: 15px;">
                <a href="forgot-password.php?restart=1" style="font-size: 13px; color: var(--gray); text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Use a different email
                </a>
            </div>
        </form>
    <?php endif; ?>

    <div class="auth-footer" style="margin-top: 25px;">
        <p>Remember your password? <a href="login.php">Back to Sign In</a></p>
    </div>
</div>

<?php include __DIR__ . '/../../backend/includes/footer.php'; ?>
