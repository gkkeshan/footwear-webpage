<?php
// ONE-TIME admin password reset
// DELETE THIS FILE IMMEDIATELY AFTER RUNNING

require_once __DIR__ . '/backend/config/database.php';

$email = 'admin@girlsfootwear.com';
$newPassword = 'admin123';

$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$db = getDBConnection();

// Update existing admin
$stmt = $db->prepare("UPDATE users SET password_hash = ?, role = 'admin' WHERE email = ?");
$stmt->execute([$hash, $email]);

if ($stmt->rowCount() > 0) {
    echo "<h2>✅ Admin password reset for {$email}</h2>";
} else {
    // Insert if missing
    $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('Admin User', ?, ?, 'admin')");
    $stmt->execute([$email, $hash]);
    echo "<h2>✅ Admin user created: {$email}</h2>";
}

echo "<p><strong>Email:</strong> {$email}</p>";
echo "<p><strong>Password:</strong> {$newPassword}</p>";
echo "<p><strong>Hash saved:</strong> " . htmlspecialchars($hash) . "</p>";
echo "<hr>";
echo "<p><a href='frontend/pages/login.php' style='font-size:18px;'>👉 Go to Login</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ DELETE THIS FILE NOW (reset-admin.php)</p>";