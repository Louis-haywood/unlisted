<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/tenant.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';

// Admin panel lives at /admin (path-based, no subdomain needed)
$uri_check = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($uri_check === 'admin' || str_starts_with($uri_check, 'admin/')) {
    require __DIR__ . '/admin/index.php';
    exit;
}

// Also support subdomain-based admin (admin.louventory.uk) for backwards compat
$subdomain = detect_subdomain();
$host      = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
if ($subdomain === 'admin') {
    require __DIR__ . '/admin/index.php';
    exit;
}

// ── Tenant resolution (session-first, subdomain fallback) ─────────────────────
// Start a generic session to read the stored tenant slug from login
if (session_status() === PHP_SESSION_NONE) {
    session_name('lv_app');
    session_start();
}

$tenant = null;

// 1. Try subdomain (still works if someone uses tenant.louventory.uk)
if ($subdomain !== null) {
    $tenant = load_tenant($subdomain);
}

// 2. Fall back to tenant stored in session after workspace login
if ($tenant === null && !empty($_SESSION['tenant_slug'])) {
    $tenant = load_tenant($_SESSION['tenant_slug']);
}

// If we found a tenant, switch to the proper tenant-scoped session
if ($tenant !== null) {
    // Re-open with tenant-specific session name so auth works correctly
    $slug = $_SESSION['tenant_slug'] ?? null;
    session_write_close();
    session_name('lv_t' . $tenant['id']);
    session_start();
    // Carry the tenant_slug forward if it was set in the generic session
    if ($slug && empty($_SESSION['tenant_slug'])) {
        $_SESSION['tenant_slug'] = $slug;
    }
}

// No tenant resolved — send to login (workspace selector)
if ($tenant === null) {
    $uri_for_login = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if ($uri_for_login !== 'login') {
        header('Location: /login');
        exit;
    }
    require __DIR__ . '/pages/login.php';
    exit;
}

// Start tenant-scoped session
auth_session_start((int)$tenant['id']);

// Parse URI path
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = trim($uri, '/');

// Route
switch ($uri) {
    case '':
    case 'dashboard':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/dashboard.php';
        break;

    case 'login':
        require __DIR__ . '/pages/login.php';
        break;

    case 'logout':
        auth_session_start((int)$tenant['id']);
        auth_logout();
        break;

    case 'items':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/items.php';
        break;

    case 'items/add':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/item_add.php';
        break;

    case 'items/edit':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/item_edit.php';
        break;

    case 'categories':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/categories.php';
        break;

    case 'loans':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/loans.php';
        break;

    case 'loans/checkout':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/loan_checkout.php';
        break;

    case 'loans/return':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/loan_return.php';
        break;

    case 'borrowers':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/borrowers.php';
        break;

    case 'history':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/history.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/templates/404.php';
        break;
}
