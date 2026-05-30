<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/tenant.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';

// Single session for all tenant users — keeps things simple on one domain
if (session_status() === PHP_SESSION_NONE) {
    session_name('lv_app');
    session_start();
}

$uri_check = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// ── Landing page ──────────────────────────────────────────────────────────────
if ($uri_check === '') {
    require __DIR__ . '/pages/landing.php';
    exit;
}

// ── Admin panel ───────────────────────────────────────────────────────────────
if ($uri_check === 'admin' || str_starts_with($uri_check, 'admin/')) {
    require __DIR__ . '/admin/index.php';
    exit;
}

$subdomain = detect_subdomain();
if ($subdomain === 'admin') {
    require __DIR__ . '/admin/index.php';
    exit;
}

// ── Tenant resolution ─────────────────────────────────────────────────────────
$tenant = null;

// 1. Subdomain (tenant.louventory.uk) — still supported if DNS is set up
if ($subdomain !== null) {
    $tenant = load_tenant($subdomain);
}

// 2. Workspace slug stored in session after login
if ($tenant === null && !empty($_SESSION['tenant_slug'])) {
    $tenant = load_tenant($_SESSION['tenant_slug']);
}

// No tenant + not on login → redirect to login
if ($tenant === null && $uri_check !== 'login') {
    header('Location: /login');
    exit;
}

// ── Routing ───────────────────────────────────────────────────────────────────
$uri = $uri_check;

switch ($uri) {
    case 'login':
        require __DIR__ . '/pages/login.php';
        break;

    case 'logout':
        auth_logout();
        break;

    case 'dashboard':
        auth_require((int)$tenant['id']);
        require __DIR__ . '/pages/dashboard.php';
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
