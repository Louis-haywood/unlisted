<?php
// Expects: $page_title (string), $tenant (array)
$page_title = $page_title ?? 'LouVentory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= h($page_title) ?> — LouVentory</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- Mobile topbar — only visible on phones / small tablets -->
<div class="mobile-topbar">
    <button class="hamburger" id="nav-toggle" aria-label="Open navigation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>
    <div class="mobile-brand">
        <span class="brand-lou">Lou</span><span class="brand-ventory">Ventory</span>
    </div>
</div>

<div class="app-layout">
