<?php
/** @var array|null $tenant */

// Already logged into this tenant? Go straight to dashboard.
if (isset($tenant) && auth_check((int)$tenant['id'])) {
    redirect('/dashboard');
}

$no_tenant = ($tenant === null);
$error     = '';
$slug      = trim($_GET['workspace'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Resolve tenant from the workspace field when not known from subdomain
        if ($no_tenant) {
            $slug = strtolower(trim($_POST['workspace'] ?? ''));
            if ($slug === '') {
                $error = 'Please enter your workspace name.';
            } else {
                $tenant = load_tenant($slug);
                if (!$tenant) {
                    $error = 'Workspace "' . h($slug) . '" not found.';
                }
            }
        }

        if (!$error && $tenant) {
            $user = auth_login((int)$tenant['id'], $email, $password);
            if ($user) {
                // Store workspace slug so index.php can resolve tenant on every request
                $_SESSION['tenant_slug'] = $tenant['subdomain'];
                redirect('/dashboard');
            } else {
                $error = 'Invalid email address or password.';
            }
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

        <div class="auth-tenant">
            <?= $tenant ? h($tenant['name']) : 'Sign in to your workspace' ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <?= csrf_field() ?>

            <?php if ($no_tenant): ?>
            <div class="form-group">
                <label class="form-label" for="workspace">Workspace</label>
                <div class="input-addon-wrap">
                    <input
                        type="text"
                        id="workspace"
                        name="workspace"
                        class="form-input"
                        placeholder="yourworkspace"
                        value="<?= h($slug) ?>"
                        required
                        autofocus
                        autocomplete="off"
                        style="border-radius:6px 0 0 6px; border-right:none"
                    >
                    <span class="input-addon">.louventory.uk</span>
                </div>
                <span class="form-hint">Your workspace name — given to you by your administrator.</span>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="you@example.com"
                    value="<?= h($_POST['email'] ?? '') ?>"
                    required
                    <?= $no_tenant ? '' : 'autofocus' ?>
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
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

            <div style="text-align:center; margin-top:1rem">
                <a href="/" style="font-size:0.8rem; color:#6B7280">← Back to home</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
