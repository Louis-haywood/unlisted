<?php
// Admin panel — accessible at louventory.uk/admin/
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/tenant.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

auth_admin_session_start();

$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Strip /admin prefix to get the sub-path (e.g. /admin/logout → logout)
$uri = trim(preg_replace('#^/admin/?#', '', $current_path), '/');

// Logout
if ($uri === 'logout') {
    admin_logout();
}

// Login page
if (!admin_check()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        if (!hash_equals($_SESSION['admin_csrf'], $token)) {
            $error = 'Invalid token.';
        } else {
            $email    = trim($_POST['email']    ?? '');
            $password = trim($_POST['password'] ?? '');
            if (admin_login($email, $password)) {
                unset($_SESSION['admin_csrf']);
                header('Location: /admin/');
                exit;
            } else {
                $error = 'Invalid credentials.';
            }
        }
    }
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login — LouVentory</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="center-card-page">
    <div class="auth-card">
        <div class="auth-logo"><span class="brand-lou">Lou</span><span class="brand-ventory">Ventory</span></div>
        <div class="auth-tenant">Superadmin Panel</div>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/admin/" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['admin_csrf']) ?>">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign in to Admin</button>
        </form>
    </div>
</div>
</body>
</html>
    <?php
    exit;
}

// Dashboard
require __DIR__ . '/dashboard.php';
