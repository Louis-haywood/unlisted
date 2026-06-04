<?php
/** @var array|null $tenant */

if (isset($tenant) && auth_check((int)$tenant['id'])) {
    redirect('/dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $password = trim($_POST['password'] ?? '');

        if ($tenant) {
            $user = auth_login_password_only((int)$tenant['id'], $password);
            if ($user) {
                $_SESSION['tenant_slug'] = $tenant['subdomain'];
                redirect('/dashboard');
            } else {
                $error = 'Invalid email address or password.';
            }
        } else {
            $error = 'Could not resolve workspace. Please contact the administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — LouVentory</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="center-card-page">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="brand-lou">Lou</span><span class="brand-ventory">Ventory</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="••••••••"
                    required
                    autofocus
                >
            </div>

            <button type="submit" class="btn btn-primary btn-full">Sign in</button>

            <div style="text-align:center; margin-top:1rem">
                <a href="/" style="font-size:0.8rem; color:#6B7280; display:inline-flex; align-items:center; gap:4px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>Back to home</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
