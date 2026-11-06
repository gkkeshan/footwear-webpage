
<?php
/**
 * Admin User Management Page
 * Handles: Viewing customer accounts, registration dates, contact details, order totals, and role management.
 * Security: Admin-only access, CSRF-protected actions, PDO prepared statements.
 */
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$db = getDBConnection();
$message = '';
$error = '';

// Handle Role Changes or User Deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if ($targetUserId === getCurrentUserId() && in_array($action, ['toggle_role', 'delete_user'])) {
            $error = 'You cannot modify your own administrative account from here.';
        } elseif ($action === 'toggle_role') {
            $newRole = ($_POST['new_role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $targetUserId]);
            $message = "User ID #{$targetUserId} role updated to {$newRole}.";
        } elseif ($action === 'delete_user') {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = "User ID #{$targetUserId} deleted successfully.";
        } elseif ($action === 'reset_password') {
            $newPass = trim($_POST['new_password'] ?? '');
            if (strlen($newPass) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $targetUserId]);
                $message = "Password for user ID #{$targetUserId} has been reset successfully.";
            }
        }
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$query = "
    SELECT u.id, u.name, u.email, u.phone, u.city, u.role, u.created_at,
           COUNT(o.id) as total_orders,
           COALESCE(SUM(o.total), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($roleFilter)) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$admin_title = "Manage Users - Admin Panel";
include __DIR__ . '/../../../backend/includes/admin-header.php';
?>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0; font-size: 24px; color: var(--dark);">User Management</h2>
        <p style="margin: 5px 0 0; color: var(--gray);">View and manage customer and administrator accounts</p>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="flash-message flash-success" style="margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?php echo escape($message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="flash-message flash-error" style="margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<!-- Search and Filter Bar -->
<div style="background: white; padding: 18px 20px; border-radius: 10px; box-shadow: var(--shadow); margin-bottom: 25px; border: 1px solid #f0f0f0;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--dark);">Search Users</label>
            <input type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Search by name, email or phone..." style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-border); border-radius: 6px;">
        </div>
        <div style="flex: 1; min-width: 160px;">
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--dark);">Account Role</label>
            <select name="role" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-border); border-radius: 6px;">
                <option value="">All Roles</option>
                <option value="customer" <?php echo $roleFilter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                <i class="fas fa-search"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($roleFilter)): ?>
                <a href="users.php" class="btn btn-outline" style="padding: 10px 16px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users Table -->
<div style="background: white; border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; border: 1px solid #f0f0f0;">
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #fafafa; border-bottom: 2px solid var(--gray-border);">
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray);">User</th>
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray);">Email / Phone</th>
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray);">Location</th>
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray);">Role</th>
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray);">Orders / Spend</th>
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray);">Registered</th>
                    <th style="padding: 14px 18px; font-size: 13px; color: var(--gray); text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" style="padding: 30px; text-align: center; color: var(--gray);">No users found matching your criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td style="padding: 14px 18px; font-weight: 600; color: var(--dark);">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                        <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div><?php echo escape($u['name']); ?></div>
                                        <div style="font-size: 11px; color: var(--gray); font-weight: normal;">ID #<?php echo $u['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 14px 18px; font-size: 13px;">
                                <div><i class="fas fa-envelope" style="color: var(--gray); width: 16px;"></i> <?php echo escape($u['email']); ?></div>
                                <?php if (!empty($u['phone'])): ?>
                                    <div style="color: var(--gray); margin-top: 4px;"><i class="fas fa-phone" style="color: var(--gray); width: 16px;"></i> <?php echo escape($u['phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; font-size: 13px; color: var(--dark);">
                                <?php echo !empty($u['city']) ? escape($u['city']) : '<span style="color: var(--gray);">-</span>'; ?>
                            </td>
                            <td style="padding: 14px 18px;">
                                <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: capitalize; background: <?php echo $u['role'] === 'admin' ? '#e8f0fe; color: #1a73e8;' : '#e6f4ea; color: #137333;'; ?>">
                                    <?php echo escape($u['role']); ?>
                                </span>
                            </td>
                            <td style="padding: 14px 18px; font-size: 13px;">
                                <div><strong><?php echo (int)$u['total_orders']; ?></strong> orders</div>
                                <div style="color: var(--gray); font-size: 12px;">Rs. <?php echo number_format((float)$u['total_spent'], 2); ?></div>
                            </td>
                            <td style="padding: 14px 18px; font-size: 13px; color: var(--gray);">
                                <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: right;">
                                <?php if ($u['id'] !== getCurrentUserId()): ?>
                                    <div style="display: inline-flex; gap: 8px;">
                                        <!-- Toggle Role Form -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Change role for <?php echo addslashes($u['name']); ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle_role">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="new_role" value="<?php echo $u['role'] === 'admin' ? 'customer' : 'admin'; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;">
                                                Make <?php echo $u['role'] === 'admin' ? 'Customer' : 'Admin'; ?>
                                            </button>
                                        </form>

                                        <!-- Reset Password Form -->
                                        <form method="POST" style="display: inline;" onsubmit="var p = prompt('Enter new password for <?php echo addslashes($u['name']); ?> (min 6 characters):'); if(!p || p.trim().length < 6){ if(p) alert('Password must be at least 6 characters.'); return false; } this.querySelector('input[name=new_password]').value = p.trim(); return true;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="new_password" value="">
                                            <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" title="Reset Password">
                                                <i class="fas fa-key"></i> Reset
                                            </button>
                                        </form>

                                        <!-- Delete User Form -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete user <?php echo addslashes($u['name']); ?>? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: var(--danger); border-color: var(--danger);">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--gray); font-style: italic;">Current Session</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
</div>
</body>
</html>
