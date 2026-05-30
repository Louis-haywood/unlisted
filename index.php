<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/tenant.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';

// Detect subdomain
$subdomain = detect_subdomain();

// Admin panel: accessed via admin.louventory.uk OR the root domain louventory.uk itself
$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$is_root_domain = ($host === APP_DOMAIN || $host === 'www.' . APP_DOMAIN);

if ($subdomain === 'admin' || $is_root_domain) {
    require __DIR__ . '/admin/index.php';
    exit;
}

// Resolve tenant
$tenant = null;
if ($subdomain !== null) {
    $tenant = load_tenant($subdomain);
}

if ($tenant === null) {
    http_response_code(404);
    require __DIR__ . '/templates/404.php';
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
