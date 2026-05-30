<?php
/** @var array|null $tenant */

// If tenant is already resolved (subdomain mode), check if already logged in
if (isset($tenant) && auth_check((int)$tenant['id'])) {
    redirect('/dashboard');
}

// If no tenant yet, this is the workspace+login page
$no_tenant = !isset($tenant) || $tenant === null;

$error  = '';
$slug   = trim($_POST['workspace'] ?? $_GET['workspace'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF — use the generic session token for the no-tenant login flow
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Resolve tenant from POST workspace field if not already known
        if ($no_tenant) {
            $slug = strtolower(trim($_POST['workspace'] ?? ''));
            if ($slug === '') {
                $error = 'Please enter your workspace name.';
            } else {
                $tenant = load_tenant($slug);
                if (!$tenant) {
                    $error = 'Workspace "' . htmlspecialchars($slug, ENT_QUOTES) . '" not found.';
                }
            }
        }

        if (!$error && $tenant) {
            // Switch to tenant session
            session_write_close();
            session_name('lv_t' . $tenant['id']);
            session_start();

            $user = auth_login((int)$tenant['id'], $email, $password);
            if ($user) {
                // Remember the workspace slug so future requests can resolve tenant
                $_SESSION['tenant_slug'] = $tenant['subdomain'];
                redirect('/dashboard');
            } else {
                $error = 'Invalid email address or password.';
                // Drop back to generic session so the form re-renders
                session_write_close();
                session_name('lv_app');
                session_start();
            }
        }
    }
} else {
    // Ensure CSRF token exists in current session
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$csrf = $_SESSION['csrf_token'];
$tenant_name = $tenant['name'] ?? null;
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

        <?php if ($tenant_name): ?>
            <div class="auth-tenant"><?= htmlspecialchars($tenant_name, ENT_QUOTES) ?></div>
        <?php else: ?>
            <div class="auth-tenant">Sign in to your workspace</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

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
                        value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>"
                        required
                        autofocus
                        autocomplete="off"
                        style="border-radius:6px 0 0 6px; border-right:none"
                    >
                    <span class="input-addon">.louventory.uk</span>
                </div>
                <span class="form-hint">Enter the workspace name given to you by your administrator.</span>
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
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>"
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
        </form>
    </div>
</div>
</body>
</html>
