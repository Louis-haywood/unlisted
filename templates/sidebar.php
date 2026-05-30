<?php
/** @var array $tenant */
$user = current_user();
$uri  = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

function nav_active(string $uri, string|array $match): string {
    $matches = is_array($match) ? $match : [$match];
    foreach ($matches as $m) {
        if ($uri === $m || str_starts_with($uri, $m . '/')) return ' active';
    }
    return '';
}
?>
<aside class="sidebar">
    <button class="sidebar-close" id="nav-close" aria-label="Close navigation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
    <div class="sidebar-brand">
        <span class="brand-lou">Lou</span><span class="brand-ventory">Ventory</span>
    </div>

    <?php if (isset($tenant)): ?>
    <div class="sidebar-tenant"><?= h($tenant['name']) ?></div>
    <?php endif; ?>

    <nav class="sidebar-nav">
        <a href="/dashboard" class="nav-item<?= nav_active($uri, ['', 'dashboard']) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="/items" class="nav-item<?= nav_active($uri, 'items') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Items
        </a>
        <a href="/categories" class="nav-item<?= nav_active($uri, 'categories') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            Categories
        </a>
        <a href="/loans/checkout" class="nav-item<?= nav_active($uri, 'loans/checkout') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Check Out
        </a>
        <a href="/loans" class="nav-item<?= nav_active($uri, 'loans') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Active Loans
        </a>
        <a href="/borrowers" class="nav-item<?= nav_active($uri, 'borrowers') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Borrowers
        </a>
        <a href="/history" class="nav-item<?= nav_active($uri, 'history') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 1 .5 4M3 21v-4h4"/></svg>
            Loan History
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
            <div class="user-info">
                <span class="user-name"><?= h($user['name']) ?></span>
                <span class="user-role"><?= h(ucfirst($user['role'])) ?></span>
            </div>
        </div>
        <a href="/logout" class="logout-link" title="Log out">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>
