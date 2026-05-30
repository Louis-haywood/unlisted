<?php
/** @var array $tenant */
// Redirect if already logged in
if (auth_check((int)$tenant['id'])) {
    redirect('/dashboard');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $user     = auth_login((int)$tenant['id'], $email, $password);
        if ($user) {
            redirect('/dashboard');
        } else {
            $error = 'Invalid email address or password.';
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
        <div class="auth-tenant"><?= h($tenant['name']) ?></div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="you@example.com"
                    value="<?= h($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="••••••••"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
