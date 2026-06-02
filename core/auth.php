<?php

// Session is started by index.php before any page is loaded.
// These functions simply read/write to whichever session is open.

function auth_session_start(int $tenant_id): void {
    // No-op: index.php already started the session.
    // Kept for backwards compatibility with any page that calls it.
}

function auth_login(int $tenant_id, string $email, string $password): array|false {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE tenant_id = ? AND email = ? LIMIT 1'
    );
    $stmt->execute([$tenant_id, $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['tenant_id']  = $tenant_id;
        return $user;
    }
    return false;
}

function auth_login_password_only(int $tenant_id, string $password): array|false {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE tenant_id = ?');
    $stmt->execute([$tenant_id]);
    while ($user = $stmt->fetch()) {
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['tenant_id']  = $tenant_id;
            return $user;
        }
    }
    return false;
}

function auth_check(int $tenant_id): bool {
    return isset($_SESSION['user_id'])
        && isset($_SESSION['tenant_id'])
        && (int)$_SESSION['tenant_id'] === $tenant_id;
}

function auth_require(int $tenant_id): void {
    if (!auth_check($tenant_id)) {
        header('Location: /login');
        exit;
    }
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: /login');
    exit;
}

function current_user(): array {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? 'Unknown',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? 'staff',
    ];
}

// ── Admin auth ────────────────────────────────────────────────────────────────

function auth_admin_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('lv_admin');
        session_start();
    }
}

function admin_login(string $email, string $password): bool {
    if ($email === ADMIN_EMAIL && password_verify($password, ADMIN_PASSWORD)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email']     = $email;
        return true;
    }
    return false;
}

function admin_check(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

function admin_require(): void {
    if (!admin_check()) {
        header('Location: /admin/');
        exit;
    }
}

function admin_logout(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: /admin/');
    exit;
}
